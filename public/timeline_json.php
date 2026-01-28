<?php
// MySQLデータベースに接続
$dbh = new PDO('mysql:host=mysql;dbname=example_db', 'root', '');

// セッション開始
session_start();
// ログインチェック：ログインしていなければ401エラーを返す（JSON API用）
if (empty($_SESSION['login_user_id'])) { // 非ログインの場合利用不可 401 で空のものを返す
  header("HTTP/1.1 401 Unauthorized");
  header("Content-Type: application/json");
  print(json_encode(['entries' => []])); // 空の配列を返す
  return;
}

// ログイン中のユーザー情報を取得
// 現在のログイン情報を取得する
$user_select_sth = $dbh->prepare("SELECT * from users WHERE id = :id");
$user_select_sth->execute([':id' => $_SESSION['login_user_id']]);
$user = $user_select_sth->fetch();

// サブクエリを使ってタイムラインの投稿データを取得
// 投稿データを取得
// WHERE句でフォロー中のユーザーまたは自分の投稿のみを取得
$sql = 'SELECT bbs_entries.*, users.name AS user_name, users.icon_filename AS user_icon_filename'
  . ' FROM bbs_entries'
  . ' INNER JOIN users ON bbs_entries.user_id = users.id'
  . ' WHERE'
  . '   bbs_entries.user_id IN'
  . '     (SELECT followee_user_id FROM user_relationships WHERE follower_user_id = :login_user_id)' // サブクエリでフォロー中のユーザーIDを取得
  . '   OR bbs_entries.user_id = :login_user_id' // 自分自身の投稿も含める
  . ' ORDER BY bbs_entries.created_at DESC'; // 新しい投稿から順に表示
$select_sth = $dbh->prepare($sql);
$select_sth->execute([
  ':login_user_id' => $_SESSION['login_user_id'],
]);

// 投稿本文をHTML表示用に加工する関数（JSON用なのでレスアンカーは不要）
// bodyのHTMLを出力するための関数を用意する
function bodyFilter (string $body): string
{
  $body = htmlspecialchars($body); // XSS対策：特殊文字をエスケープ処理
  $body = nl2br($body); // 改行文字を<br>要素に変換

  return $body;
}

// JSON形式で返すためのデータを構築
// JSONに吐き出す用のentries
$result_entries = [];
foreach ($select_sth as $entry) {
  // 各投稿をJSON用の連想配列に変換
  $result_entry = [
    'id' => $entry['id'], // 投稿ID
    'user_name' => $entry['user_name'], // 投稿者名
    'user_profile_url' => '/profile.php?user_id=' . $entry['user_id'], // プロフィールURL
    'user_icon_file_url' => empty($entry['user_icon_filename']) ? '' : ('/image/' . $entry['user_icon_filename']), // アイコン画像URL（なければ空文字）
    'body' => bodyFilter($entry['body']), // 投稿本文（エスケープ・改行変換済み）
    'image_file_url' => empty($entry['image_filename']) ? '' : ('/image/' . $entry['image_filename']), // 投稿画像URL（なければ空文字）
    'created_at' => $entry['created_at'], // 投稿日時
  ];
  $result_entries[] = $result_entry;
}

// JSON形式でレスポンスを返す
header("HTTP/1.1 200 OK");
header("Content-Type: application/json");
print(json_encode(['entries' => $result_entries])); // 投稿配列をJSON形式で出力
