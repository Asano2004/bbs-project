<?php
// セッション開始
session_start();
// MySQLデータベースに接続
$dbh = new PDO('mysql:host=mysql;dbname=example_db', 'root', '');

// 会員一覧取得用のSQL構築（検索条件に応じて動的に生成）
// 会員データを取得
$sql = 'SELECT * FROM users';
$prepare_params = []; // プレースホルダにバインドするパラメータ
$where_sql_array = []; // WHERE句の条件を格納する配列

// 名前検索：GETパラメータに名前が指定されている場合
if (!empty($_GET['name'])) {
  // LIKE句で部分一致検索（前後に%をつける）
  $where_sql_array[] = ' name LIKE :name';
  $prepare_params[':name'] = '%' . $_GET['name'] . '%';
}
// 生まれ年（開始）検索：GETパラメータに開始年が指定されている場合
if (!empty($_GET['year_from'])) {
  // その年の1月1日以降の生年月日を持つユーザーを検索
  $where_sql_array[] = ' birthday >= :year_from';
  $prepare_params[':year_from'] = $_GET['year_from'] . '-01-01'; // 入力年の1月1日
}
// 生まれ年（終了）検索：GETパラメータに終了年が指定されている場合
if (!empty($_GET['year_until'])) {
  // その年の12月31日以前の生年月日を持つユーザーを検索
  $where_sql_array[] = ' birthday <= :year_until';
  $prepare_params[':year_until'] = $_GET['year_until'] . '-12-31'; // 入力年の12月31日
}
// WHERE句が1つ以上ある場合はSQLに追加
if (!empty($where_sql_array)) {
  $sql .= ' WHERE ' . implode(' AND', $where_sql_array); // 複数条件をANDで連結
}
// ID降順でソート（新しいユーザーから順に表示）
$sql .= ' ORDER BY id DESC';
$select_sth = $dbh->prepare($sql);
$select_sth->execute($prepare_params); // パラメータをバインドして実行

// ログイン中のユーザーがフォローしているユーザーIDリストを取得
// ログインしている場合、フォローしている会員IDリストを取得
$followee_user_ids = [];
if (!empty($_SESSION['login_user_id'])) {
  // user_relationshipsテーブルからフォロー情報を取得
  $followee_users_select_sth = $dbh->prepare(
    'SELECT * FROM user_relationships WHERE follower_user_id = :follower_user_id'
  );
  $followee_users_select_sth->execute([
    ':follower_user_id' => $_SESSION['login_user_id'],
  ]);
  // array_mapを使ってfollowee_user_idカラムのみを抽出
  $followee_user_ids = array_map(
    function ($relationship) {
      return $relationship['followee_user_id'];
    },
    $followee_users_select_sth->fetchAll()
  ); // array_map で followee_user_id カラムだけ抜き出す
}
?>

<body>
  <h1>会員一覧</h1>

  <!-- ナビゲーションリンク -->
  <div style="margin-bottom: 1em;">
    <a href="/setting/index.php">設定画面</a>
    /
    <a href="/timeline.php">タイムライン</a>
  </div>

  <!-- 検索フォーム -->
  <div style="margin-bottom: 1em;">
    絞り込み<br>
    <form method="GET">
      <!-- 名前検索フィールド（既存の検索値を初期値として表示） -->
      名前: <input type="text" name="name" value="<?= htmlspecialchars($_GET['name'] ?? '') ?>"><br>
      <!-- 生まれ年検索フィールド（既存の検索値を初期値として表示） -->
      生まれ年:
      <input type="number" name="year_from" value="<?= htmlspecialchars($_GET['year_from'] ?? '') ?>">年
      ~
      <input type="number" name="year_until" value="<?= htmlspecialchars($_GET['year_until'] ?? '') ?>">年
      <br>
      <button type="submit">決定</button>
    </form>
  </div>

  <?php foreach($select_sth as $user): ?>
    <!-- ユーザーを1人ずつ表示 -->
    <div style="display: flex; justify-content: start; align-items: center; padding: 1em 2em;">
      <?php if(empty($user['icon_filename'])): ?>
        <!-- アイコンがない場合は同じサイズの空白を表示してレイアウトを揃える -->
        <!-- アイコン無い場合は同じ大きさの空白を表示して揃えておく -->
        <div style="height: 2em; width: 2em;"></div>
      <?php else: ?>
        <!-- ユーザーのアイコン画像を円形で表示 -->
        <img src="/image/<?= $user['icon_filename'] ?>"
          style="height: 2em; width: 2em; border-radius: 50%; object-fit: cover;">
      <?php endif; ?>
      <!-- ユーザー名とプロフィールへのリンク -->
      <a href="/profile.php?user_id=<?= $user['id'] ?>" style="margin-left: 1em;">
        <?= htmlspecialchars($user['name']) ?>
      </a>
      <!-- フォロー状態の表示 -->
      <div style="margin-left: 2em;">
        <?php if($user['id'] === $_SESSION['login_user_id']): ?>
          <!-- 自分自身の場合 -->
          これはあなたです!
        <?php elseif(in_array($user['id'], $followee_user_ids)): ?>
          <!-- 既にフォロー済みの場合 -->
          フォロー済
        <?php else: ?>
          <!-- フォローしていない場合：フォローボタンを表示 -->
          <a href="./follow.php?followee_user_id=<?= $user['id'] ?>">フォローする</a>
        <?php endif; ?>
      </div>
    </div>
    <!-- ユーザー間の区切り線 -->
    <hr style="border: none; border-bottom: 1px solid gray;">
  <?php endforeach; ?>
</body>
