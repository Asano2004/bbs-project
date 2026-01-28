<?php
// セッション開始
session_start();

// ログインチェック：セッションにログインIDがなければログイン画面にリダイレクト
// セッションにログインIDが無ければ (=ログインされていない状態であれば) ログイン画面にリダイレクトさせる
if (empty($_SESSION['login_user_id'])) {
  header("HTTP/1.1 302 Found");
  header("Location: ./login.php");
  return;
}

// MySQLデータベースに接続
// DBに接続
$dbh = new PDO('mysql:host=mysql;dbname=example_db', 'root', '');

// ログイン中のユーザー情報を取得
// セッションにあるログインIDから、ログインしている対象の会員情報を引く
$insert_sth = $dbh->prepare("SELECT * FROM users WHERE id = :id");
$insert_sth->execute([
  ':id' => $_SESSION['login_user_id'],
]);
$user = $insert_sth->fetch();
?>

<!-- ログイン完了メッセージ -->
<h1>ログイン完了</h1>
<p>
  ログイン完了しました!<br>
  <a href="/timeline.php">タイムラインはこちら</a>
</p>
<hr>
<!-- ログイン中のユーザー情報を表示 -->
<p>
  現在ログインしている会員情報は以下のとおりです。
</p>
<dl> <!-- 登録情報を出力する際はXSS防止のため htmlspecialchars() を必ず使いましょう -->
  <dt>ID</dt>
  <dd><?= htmlspecialchars($user['id']) ?></dd>
  <dt>メールアドレス</dt>
  <dd><?= htmlspecialchars($user['email']) ?></dd>
  <dt>名前</dt>
  <!-- XSS対策：ユーザー入力値は必ずhtmlspecialchars()でエスケープ -->
  <dd><?= htmlspecialchars($user['name']) ?></dd>
</dl>
