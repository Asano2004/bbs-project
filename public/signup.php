<?php
// MySQLデータベースに接続
$dbh = new PDO('mysql:host=mysql;dbname=example_db', 'root', '');

// フォーム送信処理：name、email、passwordが全て送信された場合
if (!empty($_POST['name']) && !empty($_POST['email']) && !empty($_POST['password'])) {
  // POSTで name と email と password が送られてきた場合はDBへの登録処理をする

  // メールアドレス重複チェック：既に同じメールアドレスが登録されていないか確認
  $select_sth = $dbh->prepare("SELECT * FROM users WHERE email = :email ORDER BY id DESC LIMIT 1");
  $select_sth->execute([
    ':email' => $_POST['email'],
  ]);
  $user = $select_sth->fetch();
  if (!empty($user)) {
    // メールアドレスが既に使用されている場合、エラーメッセージを表示するためリダイレクト
    // 存在した場合 エラー用のクエリパラメータ付き会員登録画面にリダイレクトする
    header("HTTP/1.1 303 See Other");
    header("Location: ./signup.php?duplicate_email=1");
    return;
  }

  // 新規ユーザー登録処理
  // insertする
  $insert_sth = $dbh->prepare("INSERT INTO users (name, email, password) VALUES (:name, :email, :password)");
  $insert_sth->execute([
    ':name' => $_POST['name'],
    ':email' => $_POST['email'],
    ':password' => password_hash($_POST['password'], PASSWORD_DEFAULT), // パスワードはハッシュ化して保存（セキュリティ対策）
  ]);
  // 登録完了後、完了画面にリダイレクト（PRGパターン）
  // 処理が終わったら完了画面にリダイレクト
  header("HTTP/1.1 303 See Other");
  header("Location: ./signup_finish.php");
  return;
}
?>
<h1>会員登録</h1>

会員登録済の人は<a href="/login.php">ログイン</a>しましょう。
<hr>

<!-- 会員登録フォーム -->
<!-- 登録フォーム -->
<form method="POST">
  <!-- input要素のtype属性は全部textでも動くが、適切なものに設定すると利用者は使いやすい -->
  <label>
    名前:
    <input type="text" name="name">
  </label>
  <br>
  <label>
    メールアドレス:
    <!-- type="email"でメールアドレス形式のバリデーションが行われる -->
    <input type="email" name="email">
  </label>
  <br>
  <label>
    パスワード:
    <!-- type="password"で入力内容が隠される。minlength="6"で6文字以上を必須に -->
    <input type="password" name="password" minlength="6" autocomplete="new-password">
  </label>
  <br>
  <button type="submit">決定</button>
</form>

<?php if(!empty($_GET['duplicate_email'])): ?>
<!-- メールアドレス重複エラー表示：URLパラメータにduplicate_emailがある場合に表示 -->
<div style="color: red;">
  入力されたメールアドレスは既に使われています。
</div>
<?php endif; ?>
