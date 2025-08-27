<?php
$dbh = new PDO('mysql:host=mysql;dbname=example_db', 'root', '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['body'])) {
    $image_filename = null;

    if (isset($_FILES['image']) && !empty($_FILES['image']['tmp_name'])) {
        $tmpFile = $_FILES['image']['tmp_name'];

        // MIMEチェック
        $mimeType = mime_content_type($tmpFile);
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];

        if (!in_array($mimeType, $allowedTypes)) {
            echo "アップロードできるのはJPEG、PNG、GIFのみです。";
            exit;
        }

        // サイズチェック（サーバー側でも5MB超過チェック）
        if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
            echo "5MBを超える画像はアップロードできません。";
            exit;
        }

        // アップロード先ディレクトリ
        $uploadDir = '/var/www/upload/image/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // ファイル名生成（ユニークに）
        $pathinfo = pathinfo($_FILES['image']['name']);
        $extension = $pathinfo['extension'];
        $image_filename = strval(time()) . bin2hex(random_bytes(25)) . '.' . $extension;
        $destination = $uploadDir . $image_filename;

        if (!move_uploaded_file($tmpFile, $destination)) {
            echo "ファイルのアップロードに失敗しました。";
            exit;
        }
    }

    // データベースに登録
    $insert_sth = $dbh->prepare("INSERT INTO bbs_entries (body, image_filename) VALUES (:body, :image_filename)");
    $insert_sth->execute([
        ':body' => $_POST['body'],
        ':image_filename' => $image_filename,
    ]);

    // リダイレクト
    header("HTTP/1.1 302 Found");
    header("Location: ./bbsimagetest.php");
    exit;
}

// 投稿一覧取得
$select_sth = $dbh->prepare('SELECT * FROM bbs_entries ORDER BY created_at DESC');
$select_sth->execute();
?>

<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>画像付き掲示板</title>
  <style>
    body {
      font-family: sans-serif;
      max-width: 600px;
      margin: auto;
    }
    textarea {
      width: 100%;
      height: 5em;
    }
    img {
      max-height: 10em;
      margin-top: 0.5em;
    }
  </style>
</head>
<body>
  <h1>画像付き掲示板</h1>

  <!-- フォーム -->
  <form method="POST" action="./bbsimagetest.php" enctype="multipart/form-data">
    <textarea name="body" required placeholder="コメントを入力してください"></textarea>
    <div style="margin: 1em 0;">
      <input type="file" accept="image/*" name="image" id="imageInput">
    </div>
    <button type="submit">送信</button>
  </form>

  <!-- JavaScript: 5MBチェック -->
  <script>
    document.getElementById('imageInput').addEventListener('change', function () {
      const file = this.files[0];
      if (!file) return;

      const maxSize = 5 * 1024 * 1024; // 5MB
      if (file.size > maxSize) {
        alert('5MBを超える画像はアップロードできません。');
        this.value = ''; // ファイル選択をリセット
      }
    });
  </script>

  <hr>

  <!-- 投稿一覧 -->
  <?php foreach ($select_sth as $entry): ?>
    <dl style="margin-bottom: 1em; padding-bottom: 1em; border-bottom: 1px solid #ccc;">
      <dt>ID</dt>
      <dd><?= htmlspecialchars($entry['id'], ENT_QUOTES, 'UTF-8') ?></dd>
      <dt>日時</dt>
      <dd><?= htmlspecialchars($entry['created_at'], ENT_QUOTES, 'UTF-8') ?></dd>
      <dt>内容</dt>
      <dd>
        <?= nl2br(htmlspecialchars($entry['body'], ENT_QUOTES, 'UTF-8')) ?>
        <?php if (!empty($entry['image_filename'])): ?>
          <div>
            <img src="/image/<?= htmlspecialchars($entry['image_filename'], ENT_QUOTES, 'UTF-8') ?>">
          </div>
        <?php endif; ?>
      </dd>
    </dl>
  <?php endforeach; ?>
</body>
</html>



