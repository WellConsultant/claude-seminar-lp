<?php
// =====================================================================
// Stripe Embedded Checkout - Create Session
// 20260522b-s（勉強会アーカイブ動画＋PDF資料・5月22日開催分・2,980円）
// このファイルは fp-1.info/public_html/stripe/ に配置する
// =====================================================================

// ↓↓↓ ここを Stripe管理画面のライブ用Secret Keyに差し替える ↓↓↓
$stripe_sk = '__STRIPE_LIVE_SK__';
// ↑↑↑ 例: 'sk_live_51H83U6LEBuO6I3Wl...' ↑↑↑

// 2,980円 JPY のPrice ID（Stripe管理画面 → 商品 → 該当商品 → 価格IDをコピー）
$price_id  = '__STRIPE_PRICE_ID__';
// 例: 'price_1XXXXXXXXXXXXXXXX'

// 購入完了後の戻り先（変更不要）
$return_url = 'https://lp.well-c.biz/20260522b-s/thanks/?session_id={CHECKOUT_SESSION_ID}';

// =====================================================================
// CORS（lp.well-c.biz からの呼び出しを許可）
// =====================================================================
$allowed_origins = ['https://lp.well-c.biz', 'http://lp.well-c.biz'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Vary: Origin');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// =====================================================================
// Stripe API 呼び出し（Checkout Session を embedded モードで作成）
// =====================================================================
$post = [
    'mode' => 'payment',
    'ui_mode' => 'embedded',
    'line_items[0][price]' => $price_id,
    'line_items[0][quantity]' => 1,
    'return_url' => $return_url,
];

$ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $stripe_sk,
    'Content-Type: application/x-www-form-urlencoded',
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));

$response = curl_exec($ch);
$status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err      = curl_error($ch);
curl_close($ch);

if ($response === false) {
    http_response_code(500);
    echo json_encode(['error' => 'curl_error', 'message' => $err]);
    exit;
}

$data = json_decode($response, true);
if ($status !== 200 || !isset($data['client_secret'])) {
    http_response_code(500);
    echo json_encode(['error' => 'stripe_error', 'status' => $status, 'body' => $data]);
    exit;
}

echo json_encode(['client_secret' => $data['client_secret']]);
