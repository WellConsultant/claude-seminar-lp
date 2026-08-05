<?php
/**
 * Traingo 資料請求 Chatwork 通知設定（テンプレート）
 *
 * 使い方：
 *   1. このファイルを toraigo-shiryo-config.php という名前でコピーする
 *   2. fp-1.info サーバーの toraigo-shiryo-submit.php と同じ階層に置く
 *   3. 下の2つの値を実際のものに書き換える
 *
 * ※ toraigo-shiryo-config.php（実ファイル）は GitHub に上げない（.gitignore 済み）。
 *    トークンを公開リポジトリに焼き込まないため。
 *    設定ファイルが無い／値が空でも、フォーム本体（メール通知・自動返信）は通常どおり動作する。
 */

define('TORAIGO_CW_TOKEN', 'ここにChatwork APIトークンを入れる');
define('TORAIGO_CW_ROOM',  'ここに通知先ルームIDを入れる'); // 例：https://www.chatwork.com/g/notification192 のルームID
