<?php
/**
 * Chatwork 通知設定（テンプレート）
 *
 * 使い方：
 *   1. このファイルを keiei-kashika-config.php という名前でコピーする
 *   2. fp-1.info サーバーの keiei-kashika-submit.php と同じ階層に置く
 *   3. 下の2つの値を実際のものに書き換える
 *
 * ※ keiei-kashika-config.php（実ファイル）は GitHub に上げない（.gitignore 済み）。
 *    トークンを公開リポジトリに焼き込まないため。
 */

define('KEIEI_CW_TOKEN', 'ここにChatwork APIトークンを入れる');
define('KEIEI_CW_ROOM',  'ここに通知先ルームIDを入れる'); // 例：https://www.chatwork.com/g/notification192 のルームID
