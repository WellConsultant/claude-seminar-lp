<?php
/**
 * Traingo 資料請求 受信スクリプト
 * 設置先：fp-1.info（Xserver・PHP稼働サーバー）の公開ディレクトリ直下
 *   例) https://fp-1.info/toraigo-shiryo-submit.php
 * 動作：資料請求内容を管理者(info@fp-1.info)へメール送信＋Chatwork通知し、
 *       請求者へは「資料ダウンロードリンク入り」の自動返信メールを送り、
 *       完了後 lp.well-c.biz の資料請求サンクスページ(shiryo-thanks.html)へ転送する。
 *
 * GitHub Pages(lp.well-c.biz)は静的のためPHPは動かない。
 * 資料請求フォーム(toraigo/index.html #shiryo)のHTMLはlp.well-c.bizに置き、
 * フォームのPOST先だけこのファイル(fp-1.info)を指す構成。
 * 既存の keiei-kashika-submit.php と同じ方式に揃えている。
 */

mb_language("Japanese");
mb_internal_encoding("UTF-8");

/* ===== 設定 ===== */
$ADMIN_TO    = "info@fp-1.info";                 // 受信先（社内）
$MAIL_FROM   = "info@fp-1.info";                 // 送信元（同一ドメイン推奨：なりすまし判定回避）
$THANKS_URL  = "https://lp.well-c.biz/toraigo/shiryo-thanks.html"; // 完了後の転送先
$FORM_URL    = "https://lp.well-c.biz/toraigo/#shiryo";            // 失敗時の戻り先
$SUBJECT_ADMIN = "【Traingo】資料請求が届きました";

/* ===== 資料ダウンロードURL =====
 * 自動返信メールに記載する「資料のダウンロードリンク」。
 * 阿久津さんが配布する資料ファイルを fp-1.info にアップロードのうえ、
 * 下記 $SHIRYO_URL を確定URLに差し替える（暫定で機能概要のリンクを置いている）。
 */
$SHIRYO_URL  = "https://fp-1.info/hojokin/traingo-shiryo.pdf"; // ★要差し替え：実際の資料ファイルURL

/* ===== Chatwork 通知設定 =====
 * トークン・ルームIDは同階層の toraigo-shiryo-config.php（サーバーのみ・GitHub非公開）で定義する。
 *   <?php
 *   define('TORAIGO_CW_TOKEN', 'Chatwork APIトークン');
 *   define('TORAIGO_CW_ROOM',  '通知先ルームID');
 * 設定ファイルが無い／定数が空のときは通知をスキップし、フォーム本体は通常どおり動作する。
 */
@include __DIR__ . "/toraigo-shiryo-config.php";

/* ===== POST以外は弾く ===== */
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: " . $FORM_URL);
    exit;
}

/* ===== スパム対策（ハニーポット：人間は空のはず） ===== */
if (!empty($_POST["company_url"])) {
    // ボット送信とみなし、何もせず完了ページへ（攻撃者に検知させない）
    header("Location: " . $THANKS_URL);
    exit;
}

/* ===== 入力取得・整形 ===== */
function v($key) {
    return isset($_POST[$key]) ? trim((string)$_POST[$key]) : "";
}
/* メールヘッダーインジェクション対策（改行除去） */
function clean_header($s) {
    return str_replace(array("\r", "\n", "%0a", "%0d"), "", $s);
}

$fields = array(
    "お名前"            => v("お名前"),
    "会社名 / 団体名"   => v("会社名"),
    "メールアドレス"    => v("メールアドレス"),
    "電話番号"          => v("電話番号"),
    "ご相談内容"        => v("ご相談内容"),
);

/* ===== 必須チェック ===== */
$required = array("お名前", "会社名 / 団体名", "メールアドレス");
$errors = array();
foreach ($required as $key) {
    if ($fields[$key] === "") $errors[] = $key;
}
$applicant_email = $fields["メールアドレス"];
if ($applicant_email !== "" && !filter_var($applicant_email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "メールアドレス（形式）";
}

if (!empty($errors)) {
    /* 入力不備：簡易エラー表示＋戻る導線 */
    header("Content-Type: text/html; charset=UTF-8");
    echo '<!DOCTYPE html><html lang="ja"><head><meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>入力内容をご確認ください</title>';
    echo '<style>body{font-family:"Hiragino Kaku Gothic ProN",sans-serif;background:#f7f7f5;color:#1f2937;line-height:1.8;padding:48px 20px;text-align:center}';
    echo '.box{max-width:520px;margin:0 auto;background:#fff;border-radius:14px;padding:32px;box-shadow:0 8px 28px rgba(15,31,51,.08)}';
    echo 'h1{font-size:20px;color:#c0392b;margin-bottom:14px}a{display:inline-block;margin-top:22px;background:#f59e0b;color:#0f1f33;font-weight:800;text-decoration:none;padding:14px 30px;border-radius:8px}</style>';
    echo '</head><body><div class="box"><h1>入力内容をご確認ください</h1>';
    echo '<p>次の項目が未入力、または形式が正しくありません。</p><p style="color:#c0392b;font-weight:700">'
        . htmlspecialchars(implode(" / ", $errors), ENT_QUOTES, "UTF-8") . '</p>';
    echo '<a href="' . htmlspecialchars($FORM_URL, ENT_QUOTES, "UTF-8") . '">フォームに戻って修正する</a>';
    echo '</div></body></html>';
    exit;
}

/* ===== 管理者宛メール本文 ===== */
$body  = "Traingo「資料請求フォーム」に申し込みがありました。\n";
$body .= "受信日時：" . date("Y-m-d H:i:s") . "\n";
$body .= str_repeat("=", 40) . "\n\n";
foreach ($fields as $label => $val) {
    $body .= "■ " . $label . "\n";
    $body .= ($val !== "" ? $val : "（未入力）") . "\n\n";
}
$body .= str_repeat("=", 40) . "\n";
$body .= "送信元IP：" . (isset($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : "-") . "\n";

$headers_admin  = "From: " . clean_header($MAIL_FROM) . "\r\n";
$headers_admin .= "Reply-To: " . clean_header($applicant_email) . "\r\n";

mb_send_mail($ADMIN_TO, $SUBJECT_ADMIN, $body, $headers_admin);

/* ===== 請求者への自動返信（資料ダウンロードリンク入り） ===== */
$auto_subject = "【Traingo】資料請求ありがとうございます（資料ダウンロードのご案内）";
$auto_body  = $fields["お名前"] . " 様\n\n";
$auto_body .= "この度は Traingo の資料請求をいただき、ありがとうございます。\n";
$auto_body .= "下記より資料をダウンロードのうえ、ご検討ください。\n\n";
$auto_body .= "▼ 資料ダウンロードはこちら\n";
$auto_body .= $SHIRYO_URL . "\n\n";
$auto_body .= "資料には、Traingoの機能・助成金活用の流れ・導入の進め方をまとめています。\n";
$auto_body .= "ご不明点や個別のご相談がございましたら、本メールへの返信、または\n";
$auto_body .= "下記の無料相談からお気軽にお問い合わせください。\n\n";
$auto_body .= "▼ 無料相談はこちら\n";
$auto_body .= "https://fp-1.info/hojokin/#cta\n\n";
$auto_body .= "無理な勧誘は一切いたしませんので、ご安心ください。\n\n";
$auto_body .= str_repeat("-", 36) . "\n";
$auto_body .= "Traingo（法人向け研修プラットフォーム）\n";
$auto_body .= "運営：Well Consultant 合同会社\n";
$auto_body .= "Mail: info@fp-1.info\n";
$auto_body .= str_repeat("-", 36) . "\n";

$headers_auto  = "From: " . clean_header($MAIL_FROM) . "\r\n";
$headers_auto .= "Reply-To: " . clean_header($MAIL_FROM) . "\r\n";

if (filter_var($applicant_email, FILTER_VALIDATE_EMAIL)) {
    mb_send_mail($applicant_email, $auto_subject, $auto_body, $headers_auto);
}

/* ===== Chatwork へ通知（設定があるときだけ・失敗してもフォームは止めない） ===== */
function notify_chatwork($fields) {
    if (!defined("TORAIGO_CW_TOKEN") || !defined("TORAIGO_CW_ROOM")) return;
    $token = trim((string)TORAIGO_CW_TOKEN);
    $room  = trim((string)TORAIGO_CW_ROOM);
    if ($token === "" || $room === "") return;

    $msg  = "[info][title]【Traingo】資料請求が届きました[/title]";
    $msg .= "受信日時：" . date("Y-m-d H:i:s") . "\n";
    $msg .= "お名前：" . ($fields["お名前"] !== "" ? $fields["お名前"] : "（未入力）") . "\n";
    $msg .= "会社名：" . ($fields["会社名 / 団体名"] !== "" ? $fields["会社名 / 団体名"] : "（未入力）") . "\n";
    $msg .= "メール：" . ($fields["メールアドレス"] !== "" ? $fields["メールアドレス"] : "（未入力）") . "\n";
    $msg .= "電話：" . ($fields["電話番号"] !== "" ? $fields["電話番号"] : "（未入力）") . "\n";
    $msg .= "ご相談内容：" . ($fields["ご相談内容"] !== "" ? $fields["ご相談内容"] : "（未入力）") . "\n";
    $msg .= "詳細は info@fp-1.info 宛メールをご確認ください。[/info]";

    $url = "https://api.chatwork.com/v2/rooms/" . rawurlencode($room) . "/messages";
    $payload = http_build_query(array("body" => $msg, "self_unread" => 1));

    if (function_exists("curl_init")) {
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => array("X-ChatWorkToken: " . $token),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ));
        curl_exec($ch);
        curl_close($ch);
    } else {
        $ctx = stream_context_create(array("http" => array(
            "method"  => "POST",
            "header"  => "X-ChatWorkToken: " . $token . "\r\nContent-Type: application/x-www-form-urlencoded\r\n",
            "content" => $payload,
            "timeout" => 15,
        )));
        @file_get_contents($url, false, $ctx);
    }
}
notify_chatwork($fields);

/* ===== 完了 → サンクスページへ転送 ===== */
header("Location: " . $THANKS_URL);
exit;
