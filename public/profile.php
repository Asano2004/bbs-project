<?php
// ユーザー情報取得処理
$user = null;
if (!empty($_GET['user_id'])) {
  // URLパラメータからユーザーIDを取得
  $user_id = $_GET['user_id'];
  // MySQLデータベースに接続
  // DBに接続
  $dbh = new PDO('mysql:host=mysql;dbname=example_db', 'root', '');
  // ユーザーIDから会員情報を取得
  // 対象の会員情報を引く
  $select_sth = $dbh->prepare("SELECT * FROM users WHERE id = :id");
  $select_sth->execute([
    ':id' => $user_id,
  ]);
  $user = $select_sth->fetch();
}
// ユーザーが見つからない場合は404エラー
if (empty($user)) {
  header("HTTP/1.1 404 Not Found");
  print("そのようなユーザーIDの会員情報は存在しません");
  return;
}

// このユーザーの投稿一覧を取得
// この人の投稿データを取得
$select_sth = $dbh->prepare(
  'SELECT bbs_entries.*, users.name AS user_name, users.icon_filename AS user_icon_filename'
  . ' FROM bbs_entries INNER JOIN users ON bbs_entries.user_id = users.id'
  . ' WHERE user_id = :user_id' // 特定ユーザーの投稿のみ取得
  . ' ORDER BY bbs_entries.created_at DESC' // 新しい投稿から順に表示
);
$select_sth->execute([
  ':user_id' => $user_id,
]);

// ログイン中のユーザーがこのプロフィールのユーザーをフォローしているか確認
// フォロー状態を取得
$relationship = null;
session_start();
if (!empty($_SESSION['login_user_id'])) { // ログインしている場合
  // フォロー関係をデータベースから取得
  // フォロー状態をDBから取得
  $select_sth = $dbh->prepare(
    "SELECT * FROM user_relationships"
    . " WHERE follower_user_id = :follower_user_id AND followee_user_id = :followee_user_id"
  );
  $select_sth->execute([
    ':followee_user_id' => $user['id'], // フォローされる側は閲覧しようとしているプロフィールの会員
    ':follower_user_id' => $_SESSION['login_user_id'], // フォローする側はログインしている会員
  ]);
  $relationship = $select_sth->fetch();
}

// このプロフィールのユーザーがログイン中のユーザーをフォローしているか確認（フォローバック確認）
// フォローされている状態を取得
$follower_relationship = null;
if (!empty($_SESSION['login_user_id'])) { // ログインしている場合
  // フォローされている関係をデータベースから取得
  // フォローされている状態をDBから取得
  $select_sth = $dbh->prepare(
    "SELECT * FROM user_relationships"
    . " WHERE follower_user_id = :follower_user_id AND followee_user_id = :followee_user_id"
  );
  $select_sth->execute([
    ':follower_user_id' => $user['id'], // フォローしている側は閲覧しようとしているプロフィールの会員
    ':followee_user_id' => $_SESSION['login_user_id'], // フォローされる側はログインしている会員
  ]);
  $follower_relationship = $select_sth->fetch();
}
?>
<!-- タイムラインへ戻るリンク -->
<a href="/timeline.php">タイムラインに戻る</a>

<!-- カバー画像エリア（画像がある場合は背景として表示） -->
<div style="
  width: 100%; height: 15em;
  <?php if(!empty($user['cover_filename'])): ?>
  background: url('/image/<?= $user['cover_filename'] ?>') center;
  background-size: cover;
  <?php endif; ?>
"></div>

<!-- プロフィールアイコンとユーザー名表示エリア -->
<div style="position: relative; height: 5em; margin-bottom: 1em;">
  <div style="position: absolute; top: -5em;">
    <div style="display: flex; align-items: end; justify-content: start;">
      <!-- アイコン画像（円形表示） -->
      <div style="margin: 0 1em; height: 10em; width: 10em; border: 3px solid white; border-radius: 50%;">
        <?php if(empty($user['icon_filename'])): ?>
        <!-- アイコンが未設定の場合の表示 -->
        <div style="height: 100%; width: 100%; border-radius: 50%; background-color: lightgray; display: flex; justify-content: center; align-items: center;">
          <div>アイコン未設定</div>
        </div>
        <?php else: ?>
        <!-- アイコン画像を表示 -->
        <img src="/image/<?= $user['icon_filename'] ?>"
          style="height: 100%; width: 100%; border-radius: 50%; object-fit: cover;">
        <?php endif; ?>
      </div>
      <!-- ユーザー名を表示（XSS対策でエスケープ） -->
      <h1><?= htmlspecialchars($user['name']) ?></h1>
    </div>
  </div>
</div>

<?php if($user['id'] === $_SESSION['login_user_id']): // 自分自身の場合 ?>
<!-- 自分のプロフィールを見ている場合 -->
<div style="margin: 1em 0;">
  これはあなたです！<br>
  <a href="/setting/index.php">設定画面はこちら</a>
</div>
<?php else: // 他人の場合 ?>
<!-- 他人のプロフィールを見ている場合 -->
<div style="margin: 1em 0;">
  <?php if(empty($relationship)): // フォローしていない場合 ?>
  <!-- フォローしていない場合：フォローボタンを表示 -->
  <div>
    <a href="./follow.php?followee_user_id=<?= $user['id'] ?>">フォローする</a>
  </div>
  <?php else: // フォローしている場合 ?>
  <!-- フォロー済みの場合：フォロー日時を表示 -->
  <div>
    <?= $relationship['created_at'] ?> にフォローしました。
  </div>
  <?php endif; ?>
  <?php if(!empty($follower_relationship)): // フォローされている場合 ?>
  <!-- このユーザーからフォローされている場合の表示 -->
  <div>
    フォローされています。
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- 年齢表示（生年月日から計算） -->
<div>
<?php if(!empty($user['birthday'])): ?>
<?php
  // 生年月日から年齢を計算
  $birthday = DateTime::createFromFormat('Y-m-d', $user['birthday']);
  $today = new DateTime('now');
?>
  <?= $today->diff($birthday)->y ?>歳
<?php else: ?>
  <!-- 生年月日が未設定の場合 -->
  生年月日未設定
<?php endif; ?>
</div>

<!-- 自己紹介文を表示（改行を<br>に変換、XSS対策でエスケープ） -->
<div>
  <?= nl2br(htmlspecialchars($user['introduction'] ?? '')) ?>
</div>

<hr>
<!-- このユーザーの投稿一覧を表示 -->
<?php foreach($select_sth as $entry): ?>
  <dl style="margin-bottom: 1em; padding-bottom: 1em; border-bottom: 1px solid #ccc;">
    <dt>日時</dt>
    <dd><?= $entry['created_at'] ?></dd>
    <dt>内容</dt>
    <dd>
      <!-- 投稿本文を表示（XSS対策でエスケープ） -->
      <?= htmlspecialchars($entry['body']) ?>
      <?php if(!empty($entry['image_filename'])): ?>
      <!-- 画像が投稿されている場合は表示 -->
      <div>
        <img src="/image/<?= $entry['image_filename'] ?>" style="max-height: 10em;">
      </div>
      <?php endif; ?>
    </dd>
  </dl>
<?php endforeach ?>
