<?php

// ########################## セッションの処理ここから
// セッションIDをCookieから取得、なければランダムに生成
$session_cookie_name = 'session_id';
$session_id = $_COOKIE[$session_cookie_name] ?? base64_encode(random_bytes(64));
// CookieにセッションIDがなければ新しく設定
if (!isset($_COOKIE[$session_cookie_name])) {
    setcookie($session_cookie_name, $session_id);
}
// Redisサーバーに接続（セッションデータの保存先）
$redis = new Redis();
$redis->connect('redis', 6379);
// Redisに保存するキー名を生成
$redis_session_key = "session-" . $session_id;
// Redisからセッションデータを取得。既存データがあればそれを、なければ空の配列を取得
$session_values = $redis->exists($redis_session_key)
  ? json_decode($redis->get($redis_session_key), true)
  : [];
// ########################## セッションの処理ここまで


// ログインチェック：セッションにログインユーザーIDがなければログイン画面にリダイレクト
if (empty($session_values['login_user_id'])) {
  header("HTTP/1.1 302 Found");
  header("Location: ./login.php");
  return;
}

// MySQLデータベースに接続
$dbh = new PDO('mysql:host=mysql;dbname=example_db', 'root', '');

// ログイン中のユーザー情報をデータベースから取得
$insert_sth = $dbh->prepare("SELECT * FROM users WHERE id = :id");
$insert_sth->execute([
    ':id' => $session_values['login_user_id'],
]);
$user = $insert_sth->fetch();

// フォーム送信処理：POSTでnameが送られてきた場合
if (isset($_POST['name'])) {
  // フォームから name が送信されてきた場合の処理

  // データベースのユーザー名を更新
  $insert_sth = $dbh->prepare("UPDATE users SET name = :name WHERE id = :id");
  $insert_sth->execute([
      ':id' => $user['id'],
      ':name' => $_POST['name'],
  ]);
  // 更新成功後、成功メッセージを表示するためにリダイレクト（PRGパターン）
  header("HTTP/1.1 303 See Other");
  header("Location: ./edit_name.php?success=1");
  return;
}
?>

<h1>名前変更</h1>
<!-- 名前変更フォーム：現在の名前を初期値として表示 -->
<form method="POST">
  <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>">
  <button type="submit">決定</button>
</form>

<?php if(!empty($_GET['success'])): ?>
<!-- 成功メッセージ：URLパラメータにsuccessがある場合に表示 -->
<div style="color: green;">
  名前の変更処理が完了しました。
</div>
<?php endif; ?>
