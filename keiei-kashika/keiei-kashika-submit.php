<?php
/**
 * 経営可視化パッケージ 無料ヒアリング 事前シート 受信スクリプト
 * 設置先：fp-1.info（Xserver・PHP稼働サーバー）の公開ディレクトリ直下
 *   例) https://fp-1.info/keiei-kashika-submit.php
 * 動作：申請内容を管理者(info@fp-1.info)へメール送信＋申込者へ自動返信し、
 *       完了後 lp.well-c.biz の日程予約ページ(thanks.html)へ転送する。
 *
 * GitHub Pages(lp.well-c.biz)は静的のためPHPは動かない。
 * 申請フォーム(index.html)のHTMLはlp.well-c.bizに置き、
 * フォームのPOST先だけこのファイル(fp-1.info)を指す構成。
 */

mb_language("Japanese");
mb_internal_encoding("UTF-8");

/* ===== 設定 ===== */
$ADMIN_TO    = "info@fp-1.info";                 // 受信先（社内）
$MAIL_FROM   = "info@fp-1.info";                 // 送信元（同一ドメイン推奨：なりすまし判定回避）
$THANKS_URL  = "https://lp.well-c.biz/product/price-setting-worksheet/apps-thanks"; // 完了後の転送先
$FORM_URL    = "https://lp.well-c.biz/product/price-setting-worksheet/apps";        // 失敗時の戻り先
$SUBJECT_ADMIN = "【経営可視化】無料ヒアリング 事前シート 受信";

/* ===== Chatwork 通知設定 =====
 * トークン・ルームIDは同階層の keiei-kashika-config.php（サーバーのみ・GitHub非公開）で定義する。
 *   <?php
 *   define('KEIEI_CW_TOKEN', 'Chatwork APIトークン');
 *   define('KEIEI_CW_ROOM',  '通知先ルームID');
 * 設定ファイルが無い／定数が空のときは通知をスキップし、フォーム本体は通常どおり動作する。
 */
@include __DIR__ . "/keiei-kashika-config.php";

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
    "会社名 / 屋号"            => v("会社名"),
    "お名前"                  => v("お名前"),
    "メールアドレス"          => v("メールアドレス"),
    "電話番号"                => v("電話番号"),
    "業種"                    => v("業種"),
    "年商規模"                => v("年商規模"),
    "社員数"                  => v("社員数"),
    "一番の経営の悩み"        => v("一番の悩み"),
    "過去3年の出来事"         => v("過去3年の出来事"),
    "打ちたい打ち手・止まる理由" => v("打ちたい打ち手"),
    "顧客理解（誰が・なぜ）"   => v("顧客理解"),
    "3年後の理想"             => v("3年後の理想"),
    "取り組み意欲"            => v("取り組み意欲"),
    "月次の時間確保"          => v("時間確保"),
    "最も解決したいこと"      => v("最も解決したいこと"),
    "その他"                  => v("その他"),
);

/* ===== 必須チェック ===== */
$required = array("会社名 / 屋号","お名前","メールアドレス","電話番号","業種","年商規模",
                  "一番の経営の悩み","3年後の理想","取り組み意欲","月次の時間確保","最も解決したいこと");
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
    echo '<style>body{font-family:"Hiragino Kaku Gothic ProN",sans-serif;background:#f6f7f9;color:#1a2b4a;line-height:1.8;padding:48px 20px;text-align:center}';
    echo '.box{max-width:520px;margin:0 auto;background:#fff;border-radius:14px;padding:32px;box-shadow:0 8px 28px rgba(20,33,61,.08)}';
    echo 'h1{font-size:20px;color:#c0392b;margin-bottom:14px}a{display:inline-block;margin-top:22px;background:#c9a227;color:#14213d;font-weight:800;text-decoration:none;padding:14px 30px;border-radius:999px}</style>';
    echo '</head><body><div class="box"><h1>入力内容をご確認ください</h1>';
    echo '<p>次の項目が未入力、または形式が正しくありません。</p><p style="color:#c0392b;font-weight:700">'
        . htmlspecialchars(implode(" / ", $errors), ENT_QUOTES, "UTF-8") . '</p>';
    echo '<a href="' . htmlspecialchars($FORM_URL, ENT_QUOTES, "UTF-8") . '">フォームに戻って修正する</a>';
    echo '</div></body></html>';
    exit;
}

/* ===== 管理者宛メール本文 ===== */
$body  = "経営可視化パッケージ「無料ヒアリング 事前シート」に回答がありました。\n";
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

$sent = mb_send_mail($ADMIN_TO, $SUBJECT_ADMIN, $body, $headers_admin);

/* ===== 申込者への自動返信 ===== */
$auto_subject = "【経営可視化パッケージ】事前シートを受け付けました";
$auto_body  = $fields["お名前"] . " 様\n\n";
$auto_body .= "この度は「経営可視化パッケージ」無料ヒアリングへ\n";
$auto_body .= "事前シートのご回答をいただき、ありがとうございます。\n\n";
$auto_body .= "内容は確かに受け付けました。\n";
$auto_body .= "続いて、下記より無料ヒアリング（60分）のご都合の良い日程を\n";
$auto_body .= "ご予約ください。\n\n";
$auto_body .= "▼ 日程予約はこちら\n";
$auto_body .= "https://calendar.app.google/9JJEp2xT3F5MDjdL8\n\n";
$auto_body .= "日程確定後、当日の進め方をあらためてご案内いたします。\n";
$auto_body .= "無理な勧誘は一切いたしませんので、ご安心ください。\n\n";
$auto_body .= str_repeat("-", 36) . "\n";
$auto_body .= "経営可視化パッケージ\n";
$auto_body .= "現在地を1枚に、未来を月次の数字に。\n";
$auto_body .= "Mail: info@fp-1.info / Tel: 050-3707-3507（平日9-18時）\n";
$auto_body .= str_repeat("-", 36) . "\n";

$headers_auto  = "From: " . clean_header($MAIL_FROM) . "\r\n";
$headers_auto .= "Reply-To: " . clean_header($MAIL_FROM) . "\r\n";

if (filter_var($applicant_email, FILTER_VALIDATE_EMAIL)) {
    mb_send_mail($applicant_email, $auto_subject, $auto_body, $headers_auto);
}

/* ===== Chatwork へ回答通知（設定があるときだけ・失敗してもフォームは止めない） ===== */
function notify_chatwork($fields) {
    if (!defined("KEIEI_CW_TOKEN") || !defined("KEIEI_CW_ROOM")) return;
    $token = trim((string)KEIEI_CW_TOKEN);
    $room  = trim((string)KEIEI_CW_ROOM);
    if ($token === "" || $room === "") return;

    $msg  = "[info][title]【経営可視化】無料ヒアリング 事前シートに回答がありました[/title]";
    $msg .= "受信日時：" . date("Y-m-d H:i:s") . "\n";
    $msg .= "会社名：" . ($fields["会社名 / 屋号"] !== "" ? $fields["会社名 / 屋号"] : "（未入力）") . "\n";
    $msg .= "お名前：" . ($fields["お名前"] !== "" ? $fields["お名前"] : "（未入力）") . "\n";
    $msg .= "メール：" . ($fields["メールアドレス"] !== "" ? $fields["メールアドレス"] : "（未入力）") . "\n";
    $msg .= "電話：" . ($fields["電話番号"] !== "" ? $fields["電話番号"] : "（未入力）") . "\n";
    $msg .= "業種：" . ($fields["業種"] !== "" ? $fields["業種"] : "（未入力）") . "\n";
    $msg .= "年商規模：" . ($fields["年商規模"] !== "" ? $fields["年商規模"] : "（未入力）") . "\n";
    $msg .= "取り組み意欲：" . ($fields["取り組み意欲"] !== "" ? $fields["取り組み意欲"] : "（未入力）") . "\n";
    $msg .= "一番の悩み：" . ($fields["一番の経営の悩み"] !== "" ? $fields["一番の経営の悩み"] : "（未入力）") . "\n";
    $msg .= "最も解決したいこと：" . ($fields["最も解決したいこと"] !== "" ? $fields["最も解決したいこと"] : "（未入力）") . "\n";
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

/* ===== 完了 → 日程予約ページへ転送 ===== */
header("Location: " . $THANKS_URL);
exit;
