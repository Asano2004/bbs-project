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

// ログイン中のユーザー情報を取得
// 現在のログイン情報を取得する
$user_select_sth = $dbh->prepare("SELECT * from users WHERE id = :id");
$user_select_sth->execute([':id' => $_SESSION['login_user_id']]);
$user = $user_select_sth->fetch();

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
  header("Location: ./timeline.php");
  return;
}
?>

<!-- ログイン情報表示 -->
<div>
  現在 <?= htmlspecialchars($user['name']) ?> (ID: <?= $user['id'] ?>) さんでログイン中
</div>
<div style="margin-bottom: 1em;">
  <!-- ナビゲーションリンク -->
  <a href="/setting/index.php">設定画面</a>
  /
  <a href="/users.php">会員一覧画面</a>
</div>
<!-- 投稿フォーム -->
<!-- フォームのPOST先はこのファイル自身にする -->
<form method="POST" action="./timeline.php"><!-- enctypeは外しておきましょう -->
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
<hr>

<!-- 投稿表示用のテンプレート（非表示、JavaScriptでクローンして使用） -->
<dl id="entryTemplate" style="display: none; margin-bottom: 1em; padding-bottom: 1em; border-bottom: 1px solid #ccc;">
  <dt>番号</dt>
  <dd data-role="entryIdArea"></dd>
  <dt>投稿者</dt>
  <dd>
    <a href="" data-role="entryUserAnchor">
      <img data-role="entryUserIconImage"
        style="height: 2em; width: 2em; border-radius: 50%; object-fit: cover;">
      <span data-role="entryUserNameArea"></span>
    </a>
  </dd>
  <dt>日時</dt>
  <dd data-role="entryCreatedAtArea"></dd>
  <dt>内容</dt>
  <dd data-role="entryBodyArea">
  </dd>
</dl>
<!-- タイムライン投稿の描画エリア -->
<div id="entriesRenderArea"></div>

<script>
// JavaScriptによるタイムライン表示処理（Ajax通信でJSONを取得して表示）
document.addEventListener("DOMContentLoaded", () => {
  const entryTemplate = document.getElementById('entryTemplate'); // テンプレート要素
  const entriesRenderArea = document.getElementById('entriesRenderArea'); // 描画先エリア

  // Ajax通信で投稿データを取得
  const request = new XMLHttpRequest();
  request.onload = (event) => {
    // レスポンスデータを取得
    const response = event.target.response;
    // 各投稿データを1件ずつ処理
    response.entries.forEach((entry) => {
      // テンプレート要素をクローン（複製）
      // テンプレートとするものから要素をコピー
      const entryCopied = entryTemplate.cloneNode(true);

      // テンプレートの display:none を解除して表示
      // display: none を display: block に書き換える
      entryCopied.style.display = 'block';

      // 投稿ID（番号）を表示
      // 番号(ID)を表示
      entryCopied.querySelector('[data-role="entryIdArea"]').innerText = entry.id.toString();

      // ユーザーアイコン画像の表示処理
      // アイコン画像が存在する場合は表示 なければimg要素ごと非表示に
      if (entry.user_icon_file_url !== undefined && entry.user_icon_file_url !== '') {
        entryCopied.querySelector('[data-role="entryUserIconImage"]').src = entry.user_icon_file_url;
      } else {
        entryCopied.querySelector('[data-role="entryUserIconImage"]').display = 'none';
      }

      // ユーザー名を表示
      // 名前を表示
      entryCopied.querySelector('[data-role="entryUserNameArea"]').innerText = entry.user_name;

      // プロフィールページへのリンクを設定
      // 名前のところのリンク先(プロフィール)のURLを設定
      entryCopied.querySelector('[data-role="entryUserAnchor"]').href = entry.user_profile_url;

      // 投稿日時を表示
      // 投稿日時を表示
      entryCopied.querySelector('[data-role="entryCreatedAtArea"]').innerText = entry.created_at;

      // 投稿本文を表示（HTMLを含むのでinnerHTMLを使用）
      // 本文を表示 (ここはHTMLなのでinnerHTMLで)
      entryCopied.querySelector('[data-role="entryBodyArea"]').innerHTML = entry.body;

      // 投稿画像がある場合は表示
      // 画像が存在する場合に本文の下部に画像を表示
      if (entry.image_file_url !== undefined && entry.image_file_url !== '') {
        const imageElement = new Image();
        imageElement.src = entry.image_file_url; // 画像URLを設定
        imageElement.style.display = 'block'; // ブロック要素にする (img要素はデフォルトではインライン要素のため)
        imageElement.style.marginTop = '1em'; // 画像上部の余白を設定
        imageElement.style.maxHeight = '300px'; // 画像を表示する最大サイズ(縦)を設定
        imageElement.style.maxWidth = '300px'; // 画像を表示する最大サイズ(横)を設定
        entryCopied.querySelector('[data-role="entryBodyArea"]').appendChild(imageElement); // 本文エリアに画像を追加
      }

      // クローンした要素を描画エリアに追加
      // 最後に実際の描画を行う
      entriesRenderArea.appendChild(entryCopied);
    });
  }
  // timeline_json.phpにGETリクエストを送信してタイムラインデータを取得
  request.open('GET', '/timeline_json.php', true); // timeline_json.php を叩く
  request.responseType = 'json';
  request.send();


  // ====================================
  // 画像アップロード処理（縮小 + Base64化）
  // ====================================
  // 以下画像縮小用
  const imageInput = document.getElementById("imageInput");
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
