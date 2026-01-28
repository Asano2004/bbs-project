<?php
// MySQLデータベースに接続
$dbh = new PDO('mysql:host=mysql;dbname=example_db', 'root', '');

// セッション開始
session_start();
// ログインチェック：ログインしていなければログイン画面にリダイレクト
if (empty($_SESSION['login_user_id'])) { // 非ログインの場合利用不可
  header("HTTP/1.1 302 Found");
  header("Location: /login.php");
  return;
}

// 新規投稿処理
// 投稿処理
if (isset($_POST['body']) && !empty($_SESSION['login_user_id'])) {

  // 画像アップロード処理
  $image_filename = null;
  if (!empty($_POST['image_base64'])) {
    // Base64エンコードされた画像データを処理
    // 先頭の data:~base64, のところは削る
    $base64 = preg_replace('/^data:.+base64,/', '', $_POST['image_base64']);

    // Base64文字列をバイナリデータにデコード
    // base64からバイナリにデコードする
    $image_binary = base64_decode($base64);

    // ファイル名を生成（タイムスタンプ + ランダムな文字列 + .png）
    // 新しいファイル名を決めてバイナリを出力する
    $image_filename = strval(time()) . bin2hex(random_bytes(25)) . '.png';
    $filepath =  '/var/www/upload/image/' . $image_filename;
    // バイナリデータをファイルとして保存
    file_put_contents($filepath, $image_binary);
  }

  // 投稿データをデータベースに保存
  // insertする
  $insert_sth = $dbh->prepare("INSERT INTO bbs_entries (user_id, body, image_filename) VALUES (:user_id, :body, :image_filename)");
  $insert_sth->execute([
    ':user_id' => $_SESSION['login_user_id'], // ログインしている会員情報の主キー
    ':body' => $_POST['body'], // フォームから送られてきた投稿本文
    ':image_filename' => $image_filename, // 保存した画像の名前 (nullの場合もある)
  ]);

  // PRGパターン：投稿完了後にリダイレクト（リロードによる二重投稿を防ぐ）
  // 処理が終わったらリダイレクトする
  // リダイレクトしないと，リロード時にまた同じ内容でPOSTすることになる
  header("HTTP/1.1 303 See Other");
  header("Location: ./timeline_subquery.php");
  return;
}

// サブクエリを使ってタイムラインの投稿データを取得
// 投稿データを取得。サブクエリを使ってフォロー一覧を取得しそれによって表示対象投稿を絞っている。
// WHERE句でフォロー中のユーザーまたは自分の投稿のみを取得
$sql = 'SELECT bbs_entries.*, users.name AS user_name, users.icon_filename AS user_icon_filename'
  . ' FROM bbs_entries'
  . ' INNER JOIN users ON bbs_entries.user_id = users.id'
  . ' WHERE'
  . '   bbs_entries.user_id IN'
  . '     (SELECT followee_user_id FROM user_relationships WHERE follower_user_id = :login_user_id)' // サブクエリでフォロー中のユーザーIDを取得
  . '   OR bbs_entries.user_id = :login_user_id' // 自分自身の投稿も含める
  . ' ORDER BY bbs_entries.created_at DESC'; // 新しい投稿から順に表示
$select_sth = $dbh->prepare($sql);
$select_sth->execute([
    ':login_user_id' => $_SESSION['login_user_id'],
]);

// 投稿本文をHTML表示用に加工する関数
// bodyのHTMLを出力するための関数を用意する
function bodyFilter (string $body): string
{
    $body = htmlspecialchars($body); // XSS対策：特殊文字をエスケープ処理
    $body = nl2br($body); // 改行文字を<br>要素に変換

    // レスアンカー機能：>>1 といった文字列を該当番号の投稿へのページ内リンクとする (レスアンカー機能)
    // 「>」(半角の大なり記号)は htmlspecialchars() でエスケープされているため注意
    $body = preg_replace('/&gt;&gt;(\d+)/', '<a href="#entry$1">&gt;&gt;$1</a>', $body);

    return $body;
}
?>

<?php if(empty($_SESSION['login_user_id'])): ?>
  <!-- 未ログイン時の表示 -->
  投稿するには<a href="/login.php">ログイン</a>が必要です。
<?php else: ?>
  <!-- ログイン済みの場合：投稿フォームを表示 -->
  現在ログイン中 (<a href="/setting/index.php">設定画面はこちら</a>)
  <!-- 投稿フォーム -->
  <!-- フォームのPOST先はこのファイル自身にする -->
  <form method="POST">
    <!-- 投稿本文入力欄 -->
    <textarea name="body" required></textarea>
    <div style="margin: 1em 0;">
      <!-- 画像選択input（画像ファイルのみ受け付ける） -->
      <input type="file" accept="image/*" name="image" id="imageInput">
    </div>
    <!-- Base64エンコードされた画像データを送信するための隠しinput -->
    <input id="imageBase64Input" type="hidden" name="image_base64"><!-- base64を送る用のinput (非表示) -->
    <!-- 画像縮小処理用のcanvas（非表示） -->
    <canvas id="imageCanvas" style="display: none;"></canvas><!-- 画像縮小に使うcanvas (非表示) -->
    <button type="submit">送信</button>
  </form>
<?php endif; ?>
<hr>

<?php foreach($select_sth as $entry): ?>
  <!-- タイムラインの投稿を1件ずつ表示 -->
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

        <!-- ユーザー名とIDを表示（XSS対策でエスケープ） -->
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

<script>
// JavaScriptによる画像処理：ファイル選択時に画像を縮小してBase64化
document.addEventListener("DOMContentLoaded", () => {
  const imageInput = document.getElementById("imageInput"); // ファイル選択input
  imageInput.addEventListener("change", () => {
    // ファイルが選択されていない場合は処理終了
    if (imageInput.files.length < 1) {
      // 未選択の場合
      return;
    }

    const file = imageInput.files[0];
    // 画像ファイル以外はスキップ
    if (!file.type.startsWith('image/')){ // 画像でなければスキップ
      return;
    }

    // 画像縮小 & Base64エンコード処理
    // 画像縮小処理
    const imageBase64Input = document.getElementById("imageBase64Input"); // base64を送るようのinput
    const canvas = document.getElementById("imageCanvas"); // 描画するcanvas
    const reader = new FileReader(); // ファイル読み込み用
    const image = new Image(); // 画像オブジェクト
    
    // ファイル読み込み完了時の処理
    reader.onload = () => { // ファイルの読み込み完了したら動く処理を指定
      // 画像読み込み完了時の処理
      image.onload = () => { // 画像として読み込み完了したら動く処理を指定

        // 縦横比を保ったまま1000px以下に縮小
        // 元の縦横比を保ったまま縮小するサイズを決めてcanvasの縦横に指定する
        const originalWidth = image.naturalWidth; // 元画像の横幅
        const originalHeight = image.naturalHeight; // 元画像の高さ
        const maxLength = 1000; // 横幅も高さも1000以下に縮小するものとする
        if (originalWidth <= maxLength && originalHeight <= maxLength) { // どちらもmaxLength以下の場合そのまま
            canvas.width = originalWidth;
            canvas.height = originalHeight;
        } else if (originalWidth > originalHeight) { // 横長画像の場合
            canvas.width = maxLength;
            canvas.height = maxLength * originalHeight / originalWidth;
        } else { // 縦長画像の場合
            canvas.width = maxLength * originalWidth / originalHeight;
            canvas.height = maxLength;
        }

        // canvasに画像を描画（縮小後のサイズで）
        // canvasに実際に画像を描画 (canvasはdisplay:noneで隠れているためわかりにくいが...)
        const context = canvas.getContext("2d");
        context.drawImage(image, 0, 0, canvas.width, canvas.height);

        // canvasの内容をBase64文字列に変換してhidden inputに設定
        // canvasの内容をbase64に変換しinputのvalueに設定
        imageBase64Input.value = canvas.toDataURL();
      };
      // FileReaderで読み込んだデータをImageオブジェクトに設定
      image.src = reader.result;
    };
    // ファイルをData URL形式で読み込み開始
    reader.readAsDataURL(file);
  });
});
</script>
