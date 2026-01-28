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

// フォロー解除対象のユーザー情報を取得
$followee_user = null;
if (!empty($_GET['followee_user_id'])) {
  // URLパラメータからフォロー解除対象のユーザーIDを取得
  $select_sth = $dbh->prepare("SELECT * FROM users WHERE id = :id");
  $select_sth->execute([
      ':id' => $_GET['followee_user_id'],
  ]); 
  $followee_user = $select_sth->fetch();
}
// フォロー解除対象のユーザーが存在しない場合は404エラー
if (empty($followee_user)) {
  header("HTTP/1.1 404 Not Found");
  print("そのようなユーザーIDの会員情報は存在しません");
  return;
}

// 現在フォロー関係があるかチェック
$select_sth = $dbh->prepare(
  "SELECT * FROM user_relationships"
  . " WHERE follower_user_id = :follower_user_id AND followee_user_id = :followee_user_id"
);
$select_sth->execute([
  ':followee_user_id' => $followee_user['id'], // フォローされている側
  ':follower_user_id' => $_SESSION['login_user_id'], // フォローしている側はログインしている会員
]);
$relationship = $select_sth->fetch();
// フォロー関係がない場合はエラーメッセージを表示して終了
if (empty($relationship)) { // フォロー関係がない場合は適当なエラー表示して終了
  print("フォローしていません。");
  return;
}

// フォロー解除処理
$delete_result = false;
if ($_SERVER['REQUEST_METHOD'] == 'POST') { // フォームでPOSTした場合は実際のフォロー解除処理を行う
  // user_relationshipsテーブルからフォロー関係を削除
  $delete_sth = $dbh->prepare(
    "DELETE FROM user_relationships"
    . " WHERE follower_user_id = :follower_user_id AND followee_user_id = :followee_user_id"
  );  
  $delete_result = $delete_sth->execute([
    ':followee_user_id' => $followee_user['id'], // フォローしている側
    ':follower_user_id' => $_SESSION['login_user_id'], // フォローしている側はログインしている会員
  ]); 
}
?>

<?php if($delete_result): ?>
<!-- フォロー解除完了後の表示 -->
<div>
  <?= htmlspecialchars($followee_user['name']) ?> さんのフォローを解除しました。<br>
  <a href="/profile.php?user_id=<?= $followee_user['id'] ?>">
    <?= htmlspecialchars($followee_user['name']) ?> さんのプロフィールに戻る
  </a>
</div>
<?php else: ?>
<!-- フォロー解除確認画面 -->
<div>
  <?= htmlspecialchars($followee_user['name']) ?> さんのフォローを解除しますか? 
  <form method="POST">
    <button type="submit">
      フォロー解除する
    </button>
  </form>
</div>
<?php endif; ?>
