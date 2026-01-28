<?php
// セッション開始
session_start();

// ログインチェック：ログインしていなければログイン画面にリダイレクト
if (empty($_SESSION['login_user_id'])) {
  header("HTTP/1.1 302 Found");
  header("Location: /login.php");
  return;
}

// MySQLデータベースに接続
$dbh = new PDO('mysql:host=mysql;dbname=example_db', 'root', '');

// 自分をフォローしているユーザー一覧を取得（フォロワー一覧）
// INNER JOINでuser_relationshipsテーブルとusersテーブルを結合し、
// フォローしてくれているユーザー情報（名前、アイコン）も同時に取得
$select_sth = $dbh->prepare(
  'SELECT user_relationships.*, users.name AS follower_user_name, users.icon_filename AS follower_user_icon_filename'
  . ' FROM user_relationships INNER JOIN users ON user_relationships.follower_user_id = users.id'
  . ' WHERE user_relationships.followee_user_id = :followee_user_id' // 自分がフォローされている関係を検索
  . ' ORDER BY user_relationships.id DESC' // 新しいフォロワーから順に表示
);
$select_sth->execute([
  ':followee_user_id' => $_SESSION['login_user_id'], // ログイン中のユーザーをフォローしている人を検索
]);
?>

<h1>フォローされている一覧</h1>

<ul>
  <?php foreach($select_sth as $relationship): ?>
  <!-- フォロワーを1件ずつ表示 -->
  <li>
    <a href="/profile.php?user_id=<?= $relationship['follower_user_id'] ?>">
      <?php if(!empty($relationship['follower_user_icon_filename'])): // アイコン画像がある場合は表示 ?>
      <!-- フォロワーのアイコン画像を円形で表示 -->
      <img src="/image/<?= $relationship['follower_user_icon_filename'] ?>"
        style="height: 2em; width: 2em; border-radius: 50%; object-fit: cover;">
      <?php endif; ?>

      <!-- フォロワーのユーザー名とIDを表示 -->
      <?= htmlspecialchars($relationship['follower_user_name']) ?>
      (ID: <?= htmlspecialchars($relationship['follower_user_id']) ?>)
    </a>
    <!-- フォローされた日時を表示 -->
    (<?= $relationship['created_at'] ?>にフォローされました)
  </li>
  <?php endforeach; ?>
</ul>
