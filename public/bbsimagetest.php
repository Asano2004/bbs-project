<?php
// MySQLデータベースに接続
$dbh = new PDO('mysql:host=mysql;dbname=example_db', 'root', '');

// フォームからの投稿処理
if (isset($_POST['body'])) {
  // POSTで送られてくるフォームパラメータ body がある場合

  $image_filename = null;

  // 画像がアップロードされている場合の処理
  if (!empty($_POST['image_base64'])) {
    // Base64エンコードされた画像データを処理
    // 先頭の data:~base64, のところは削る
    $base64 = preg_replace('/^data:.+base64,/', '', $_POST['image_base64']);

    // Base64文字列をバイナリデータにデコード
    $image_binary = base64_decode($base64);

    // ファイル名を生成（タイムスタンプ + ランダムな文字列 + .jpg）
    $image_filename = strval(time()) . bin2hex(random_bytes(25)) . '.jpg';
    $filepath =  '/var/www/upload/image/' . $image_filename;
    // バイナリデータをファイルとして保存
    file_put_contents($filepath, $image_binary);
  }

  // 投稿内容と画像ファイル名をデータベースに保存
  $insert_sth = $dbh->prepare("INSERT INTO bbs_entries (body, image_filename) VALUES (:body, :image_filename)");
  $insert_sth->execute([
    ':body' => $_POST['body'],
    ':image_filename' => $image_filename,
  ]);

  // PRGパターン：処理完了後にリダイレクト
  // リダイレクトしないと，リロード時にまた同じ内容でPOSTすることになる
  header("HTTP/1.1 302 Found");
  header("Location: ./bbsimagetest.php");
  return;
}

// 投稿一覧をデータベースから取得（新しい順）
$select_sth = $dbh->prepare('SELECT * FROM bbs_entries ORDER BY created_at DESC');
$select_sth->execute();

// 投稿本文をHTML表示用に加工する関数
function bodyFilter (string $body): string
{
  $body = htmlspecialchars($body); // XSS対策：特殊文字をエスケープ
  $body = nl2br($body); // 改行文字を<br>要素に変換

  // レスアンカー機能：>>1 といった文字列を該当番号の投稿へのページ内リンクとする
  // 「>」(半角の大なり記号)は htmlspecialchars() でエスケープされているため注意
  $body = preg_replace('/&gt;&gt;(\d+)/', '<a href="#entry$1">&gt;&gt;$1</a>', $body);

  return $body;
}
?>
<head>
  <title>画像投稿できる掲示板</title>
</head>

<!-- 投稿フォーム -->
<!-- フォームのPOST先はこのファイル自身にする -->
<form method="POST" action="./bbsimagetest.php" enctype="multipart/form-data">
  <!-- 本文入力欄 -->
  <textarea name="body" required></textarea>
  <div style="margin: 1em 0;">
    <!-- 画像選択input（画像ファイルのみ受け付ける） -->
    <input type="file" accept="image/*" name="image" id="imageInput">
    <!-- 画像プレビュー表示エリア（最初は非表示） -->
    <div id="imagePreviewArea" style="display: none;">
      <div style="display: flex; align-items: start; margin: 1em 0;">
        <span style="margin-right: 1em;">プレビュー:</span>
        <!-- プレビュー用のcanvas要素 -->
        <canvas id="imagePreviewCanvas" style=""></canvas>
      </div>
    </div>
  </div>
  <!-- Base64エンコードされた画像データを送信するための隠しinput -->
  <input id="imageBase64Input" type="hidden" name="image_base64"><!-- base64を送る用のinput (非表示) -->
  <!-- 画像縮小処理用のcanvas（非表示） -->
  <canvas id="imageCanvas" style="display: none;"></canvas><!-- 画像縮小に使うcanvas (非表示) -->
  <button type="submit">送信</button>
</form>

<hr>

<?php foreach($select_sth as $entry): ?>
  <!-- 投稿を1件ずつ表示 -->
  <dl style="margin-bottom: 1em; padding-bottom: 1em; border-bottom: 1px solid #ccc;">
    <dt id="entry<?= htmlspecialchars($entry['id']) ?>">ID</dt>
    <dd><?= $entry['id'] ?></dd>
    <dt>日時</dt>
    <dd><?= $entry['created_at'] ?></dd>
    <dt>内容</dt>
    <dd>
      <!-- 投稿本文を表示（エスケープ・改行変換・レスアンカー処理済み） -->
      <?= bodyFilter($entry['body']) ?>
      <?php if(!empty($entry['image_filename'])): // 画像がある場合は img 要素を使って表示 ?>
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
  const previewArea = document.getElementById("imagePreviewArea"); // プレビューエリア(div)
  const previewCanvas = document.getElementById("imagePreviewCanvas"); // プレビューを描画するcanvas
  
  // ファイルが選択されたときの処理
  imageInput.addEventListener("change", () => {
    // プレビューエリアを一旦非表示に
    previewArea.style.display = 'none';

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

    // 画像縮小処理 & base64のテキストに変換して name="image_base64" なinput要素につっこむ
    const imageBase64Input = document.getElementById("imageBase64Input"); // base64を送るようのinput
    const canvas = document.getElementById("imageCanvas"); // 描画するcanvas
    const reader = new FileReader(); // ファイル読み込み用
    const image = new Image(); // 画像オブジェクト
    
    // ファイル読み込み完了時の処理
    reader.onload = () => { // ファイルの読み込み完了したら動く処理を指定
      // 画像読み込み完了時の処理
      image.onload = () => { // 画像として読み込み完了したら動く処理を指定

        // 縦横比を保ったまま2000px以下に縮小
        // 元の縦横比を保ったまま縮小するサイズを決めてcanvasの縦横に指定する
        const originalWidth = image.naturalWidth; // 元画像の横幅
        const originalHeight = image.naturalHeight; // 元画像の高さ
        const maxLength = 2000; // 横幅も高さも2000px以下に縮小するものとする
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

        // canvasに実際に画像を描画 (canvasはdisplay:noneで隠れているためわかりにくいが...)
        const context = canvas.getContext("2d");
        context.drawImage(image, 0, 0, canvas.width, canvas.height);

        // canvasの内容をJPEG形式のBase64文字列に変換してhidden inputに設定
        // canvasの内容をjpeg形式のbase64に変換しinputのvalueに設定
        imageBase64Input.value = canvas.toDataURL('image/jpeg', 0.9);

        // 元のfile inputをクリア（サーバーに生ファイルが送られないように）
        // 元のファイル選択を消す (このままだと送られてしまうから)
        imageInput.value = "";

        // プレビュー表示処理
        // プレビューエリアの display:none (非表示) を解除
        previewArea.style.display = '';
        // プレビューcanvasの高さを200px固定として、元画像の縦横比から横幅を設定
        previewCanvas.height = canvas.height = '200';
        previewCanvas.width = previewCanvas.height * originalWidth / originalHeight;
        // プレビューcanvasへ画像を描画
        const previewContext = previewCanvas.getContext("2d");
        previewContext.drawImage(image, 0, 0, previewCanvas.width, previewCanvas.height);
      };
      // FileReaderで読み込んだデータをImageオブジェクトに設定
      image.src = reader.result;
    };
    // ファイルをData URL形式で読み込み開始
    reader.readAsDataURL(file);
  });
});
</script>
