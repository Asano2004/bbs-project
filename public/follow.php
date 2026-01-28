<?php
// セッション開始
session_start();

// ログインチェック：ログインしていなければログイン画面にリダイレクト
if (empty($_SESSION['login_user_id'])) {
  header("HTTP/1.1 302 Found");
  header("Location: ./login.php");
  return;
}

// MySQLデータベースに接続
$dbh = new PDO('mysql:host=mysql;dbname=example_db', 'root', '');

// フォロー対象のユーザー情報を取得
$followee_user = null;
if (!empty($_GET['followee_user_id'])) {
  // URLパラメータからフォロー対象のユーザーIDを取得
  $select_sth = $dbh->prepare("SELECT * FROM users WHERE id = :id");
  $select_sth->execute([
      ':id' => $_GET['followee_user_id'],
  ]);
  $followee_user = $select_sth->fetch();
}
// フォロー対象のユーザーが存在しない場合は404エラー
if (empty($followee_user)) {
  header("HTTP/1.1 404 Not Found");
  print("そのようなユーザーIDの会員情報は存在しません");
  return;
}

// 既にフォロー関係があるかチェック
$select_sth = $dbh->prepare(
  "SELECT * FROM user_relationships"
  . " WHERE follower_user_id = :follower_user_id AND followee_user_id = :followee_user_id"
);
$select_sth->execute([
  ':followee_user_id' => $followee_user['id'], // フォローされる側(フォロー対象)
  ':follower_user_id' => $_SESSION['login_user_id'], // フォローする側はログインしている会員
]);
$relationship = $select_sth->fetch();
// 既にフォローしている場合はエラーメッセージを表示して終了
if (!empty($relationship)) { // 既にフォロー関係がある場合は適当なエラー表示して終了
  print("既にフォローしています。");
  return;
}

// フォロー登録処理
$insert_result = false;
if ($_SERVER['REQUEST_METHOD'] == 'POST') { // フォームでPOSTした場合は実際のフォロー登録処理を行う
  // user_relationshipsテーブルにフォロー関係を登録
  $insert_sth = $dbh->prepare(
    "INSERT INTO user_relationships (follower_user_id, followee_user_id) VALUES (:follower_user_id, :followee_user_id)"
  );
  $insert_result = $insert_sth->execute([
    ':followee_user_id' => $followee_user['id'], // フォローされる側(フォロー対象)
    ':follower_user_id' => $_SESSION['login_user_id'], // フォローする側はログインしている会員
  ]);
}
?>

<?php if($insert_result): ?>
<!-- フォロー完了後の表示 -->
<div>
  <?= htmlspecialchars($followee_user['name']) ?> さんをフォローしました。<br>
  <a href="/profile.php?user_id=<?= $followee_user['id'] ?>">
    <?= htmlspecialchars($followee_user['name']) ?> さんのプロフィールへ
  </a>
  /
  <a href="/users.php">
    会員一覧へ
  </a>
</div>
<?php else: ?>
<!-- フォロー確認画面 -->
<div>
  <?= htmlspecialchars($followee_user['name']) ?> さんをフォローしますか?
  <form method="POST">
    <button type="submit">
      フォローする
    </button>
  </form>
</div>
<?php endif; ?>
