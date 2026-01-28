<?php
// MySQLデータベースに接続
$dbh = new PDO('mysql:host=mysql;dbname=example_db', 'root', '');
// セッション開始
session_start();

// いままで保存してきたものを取得
$select_sth = $dbh->prepare('SELECT * FROM bbs_entries ORDER BY created_at DESC');
// 投稿データを取得。紐づく会員情報も結合し同時に取得する。
// サブクエリを使ってユーザー名とアイコンファイル名を取得
// INNER JOINでbbs_entriesテーブルとusersテーブルを結合
$select_sth = $dbh->prepare(
  'SELECT bbs_entries.*,'
  . '(SELECT name FROM users WHERE id = bbs_entries.user_id) user_name,' // ユーザー名をサブクエリで取得
  . '(SELECT icon_filename FROM users WHERE id = bbs_entries.user_id) user_icon_filename' // アイコンファイル名をサブクエリで取得
  . ' FROM bbs_entries INNER JOIN users ON bbs_entries.user_id = users.id'
  . ' ORDER BY bbs_entries.created_at DESC' // 新しい投稿から順に表示
);
$select_sth->execute();

// 投稿本文をHTML表示用に加工する関数
function bodyFilter (string $body): string
{
  $body = htmlspecialchars($body); // XSS対策：特殊文字をエスケープ
  $body = nl2br($body); // 改行文字を<br>要素に変換して改行を表示
  // レスアンカー機能：>>1 のような文字列を該当投稿へのリンクに変換
  // 「>」(半角の大なり記号)は htmlspecialchars() でエスケープされているため注意
  $body = preg_replace('/&gt;&gt;(\d+)/', '<a href="#entry$1">&gt;&gt;$1</a>', $body);
  return $body;
}

?>
<?php if(empty($_SESSION['login_user_id'])): ?>
  <!-- 未ログイン時の表示 -->
  <a href="/login.php">ログイン</a>して自分のタイムラインを閲覧しましょう！
<?php else: ?>
  <!-- ログイン済みの場合はタイムラインへのリンクを表示 -->
  <a href="/timeline.php">タイムラインはこちら</a>
<?php endif; ?>

<hr>

<?php foreach($select_sth as $entry): ?>
  <!-- 投稿を1件ずつ表示 -->
  <dl style="margin-bottom: 1em; padding-bottom: 1em; border-bottom: 1px solid #ccc;">
    <dt id="entry<?= htmlspecialchars($entry['id']) ?>">
      番号
    </dt>
    <dd>
      <!-- 投稿ID（レスアンカーのリンク先として使用） -->
      <?= htmlspecialchars($entry['id']) ?>
    </dd>
    <dt>
      投稿者
    </dt>
    <dd>
      <!-- 投稿者のプロフィールへのリンク -->
      <a href="/profile.php?user_id=<?= $entry['user_id'] ?>">
        <?php if(!empty($entry['user_icon_filename'])): // アイコン画像がある場合は表示 ?>
          <!-- ユーザーのアイコン画像を円形で表示 -->
          <img src="/image/<?= $entry['user_icon_filename'] ?>"
            style="height: 2em; width: 2em; border-radius: 50%; object-fit: cover;">
        <?php endif; ?>
        <!-- ユーザー名とIDを表示 -->
        <?= htmlspecialchars($entry['user_name']) ?>
        (ID: <?= htmlspecialchars($entry['user_id']) ?>)
      </a>
    </dd>
    <dt>日時</dt>
    <dd><?= $entry['created_at'] ?></dd>
    <dt>内容</dt>
    <dd>
      <!-- 投稿本文を表示（エスケープ・改行変換・レスアンカー処理済み） -->
      <?= bodyFilter($entry['body']) ?>
      <?php if(!empty($entry['image_filename'])): ?>
      <!-- 画像が投稿されている場合は表示 -->
      <div>
        <img src="/image/<?= $entry['image_filename'] ?>" style="max-height: 10em;">
      </div>
      <?php endif; ?>
    </dd>
  </dl>
<?php endforeach ?>
