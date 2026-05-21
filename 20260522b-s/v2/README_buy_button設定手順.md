# Stripe Buy Button 設定手順（20260522b-s 試作版）

## このページの目的

現状LP（lp.well-c.biz/20260522b-s/）は「CTAを押すとStripeの決済ページに別タブ遷移する」型。
v2（lp.well-c.biz/20260522b-s/v2/）は「LPページの中で決済が完結する」型（Stripe Buy Button）。
両方を同時公開して、どちらが申込まで進む人が多いかを比較する。

## 阿久津さんにお願いしたい操作（1回だけ）

### 1. Stripe管理画面で「Buy Button」を作る

1. ブラウザで https://dashboard.stripe.com/payment-links を開く
2. 既存の Payment Link「20260522b-s（2,980円）」の行をクリック
   - URLが `https://buy.stripe.com/cNibJ2biidFE1x3ds6fMA2u` のもの
3. ページ右上にある「Buy Button」タブをクリック
4. 「Create Buy Button」ボタンを押す
5. ボタンの文言・色を整える（オレンジ系を推奨：現状LPと同じ色味）
6. 画面右下の「Get code」を押すと、HTML埋め込みコードが表示される
7. そのコードの中に下の2つの値があります：

   ```html
   <stripe-buy-button
     buy-button-id="buy_btn_XXXXXXXXXXXXXX"        ←この値（buy_btn_で始まる）
     publishable-key="pk_live_XXXXXXXXXXXXXXXXXX"  ←この値（pk_live_で始まる）
   >
   ```

### 2. 上の2つの値をClaudeに貼ってください

下のように1メッセージで送ってもらえれば、Claudeが30秒で v2/index.html に差し込みます：

```
buy-button-id: buy_btn_XXXXXXXXXXXXXX
publishable-key: pk_live_XXXXXXXXXXXXXXXXXX
```

## Claudeがこの後やること（阿久津さん操作後）

1. v2/index.html の `{{BUY_BUTTON_ID_REPLACE_ME}}` と `{{STRIPE_PUBLISHABLE_KEY_REPLACE_ME}}` を実値に置換
2. GitHub にプッシュ → lp.well-c.biz/20260522b-s/v2/ が公開される
3. 動作確認URLを返す
4. （任意）b-vipダッシュボードに「v2試作（埋め込み決済）」のカードを追加

## 離脱率の比較方法

両ページの「クリックされた回数」と「実際に購入完了した回数」を Stripe Dashboard と GA で比較する：

| 指標 | 現状版（リンク遷移型） | v2版（埋め込み型） |
|---|---|---|
| 公開URL | lp.well-c.biz/20260522b-s/ | lp.well-c.biz/20260522b-s/v2/ |
| CTAクリック数 | GAの click_payment_link イベント | Stripe Buy Buttonのview/click |
| 購入完了数 | Stripe Dashboard（Payment Link経由） | Stripe Dashboard（Buy Button経由） |
| 購入完了率 | 完了数 ÷ クリック数 | 完了数 ÷ 表示数 |

## 注意

- v2のmeta robotsは `noindex,nofollow` で公開する（検索エンジンに重複コンテンツ扱いされないため）
- Buy Button は本番モード（pk_live_…）で発行すること（テストモード pk_test_… だと実購入できない）
- Buy Button のドメイン制限が必要なら Stripe Dashboard で `lp.well-c.biz` を許可ドメインに追加
