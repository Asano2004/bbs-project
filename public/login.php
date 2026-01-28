<?php
// セッション開始
session_start();

// MySQLデータベースに接続
// DBに接続
$dbh = new PDO('mysql:host=mysql;dbname=example_db', 'root', '');

// フォーム送信処理：emailとpasswordが両方送信された場合のみ実行
if (!empty($_POST['email']) && !empty($_POST['password'])) {
  // POSTで email と password が送られてきた場合のみログイン処理をする
  
  // メールアドレスからユーザー情報を検索
  // email から会員情報を引く
  $select_sth = $dbh->prepare("SELECT * FROM users WHERE email = :email ORDER BY id DESC LIMIT 1");
  $select_sth->execute([
    ':email' => $_POST['email'],
  ]);
  $user = $select_sth->fetch();

  // ユーザーが見つからない場合はエラー
  if (empty($user)) {
    // 入力されたメールアドレスに該当する会員が見つからなければ、処理を中断しエラー用クエリパラメータ付きのログイン画面URLにリダイレクト
    header("HTTP/1.1 303 See Other");
    header("Location: ./login.php?error=1");
    return;
  }

  // パスワード検証：ハッシュ化されたパスワードと照合
  // パスワードが正しいかチェック
  $correct_password = password_verify($_POST['password'], $user['password']);

  // パスワードが間違っている場合はエラー
  if (!$correct_password) {
    // パスワードが間違っていれば、処理を中断しエラー用クエリパラメータ付きのログイン画面URLにリダイレクト
    header("HTTP/1.1 303 See Other");
    header("Location: ./login.php?error=1");
    return;
  }

  // ログイン成功：セッションにユーザーIDを保存
  // セッションにログインIDを保存
  $_SESSION["login_user_id"] = $user['id'];

  // ログイン完了画面にリダイレクト
  // ログインが成功したらログイン完了画面にリダイレクト
  header("HTTP/1.1 303 See Other");
  header("Location: ./login_finish.php");
  return;
}
?>

<!-- 新規ユーザー向けの案内 -->
初めての人は<a href="/signup.php">会員登録</a>しましょう。
<hr>
<h1>ログイン</h1>
<!-- ログインフォーム -->
<!-- ログインフォーム -->
<form method="POST">
  <!-- input要素のtype属性は全部textでも動くが、適切なものに設定すると利用者は使いやすい -->
  <label>
    メールアドレス:
    <!-- type="email"でメールアドレス形式のバリデーションが行われる -->
    <input type="email" name="email">
  </label>
  <br>
  <label>
    パスワード:
    <!-- type="password"で入力内容が隠される。minlength="6"で6文字以上を必須に -->
    <input type="password" name="password" minlength="6">
  </label>
  <br>
  <button type="submit">決定</button>
</form>

<?php if(!empty($_GET['error'])): // エラー用のクエリパラメータがある場合はエラーメッセージ表示 ?>
<!-- ログインエラー表示：URLパラメータにerrorがある場合に表示 -->
<div style="color: red;">
  メールアドレスかパスワードが間違っています。
</div>
<?php endif; ?>
