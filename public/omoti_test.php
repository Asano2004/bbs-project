<?php
date_default_timezone_set('Asia/Tokyo');
// --- DB接続 ---
try {
    $dbh = new PDO('mysql:host=mysql;dbname=example_db', 'root', '');
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB接続失敗: " . htmlspecialchars($e->getMessage()));
}

// --- いいね処理 (AJAX) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['like_id'])) {
    $like_id = (int)$_POST['like_id'];
    $stmt = $dbh->prepare("UPDATE bbs_entries SET likes = likes + 1 WHERE id = :id");
    $stmt->execute([':id' => $like_id]);
    exit(json_encode(["status" => "ok"]));
}

// --- 投稿処理 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['body']) && !isset($_POST['like_id'])) {
    $image_filename = null;

    if (isset($_FILES['image']) && !empty($_FILES['image']['tmp_name'])) {
        $tmpFile = $_FILES['image']['tmp_name'];
        $mimeType = mime_content_type($tmpFile);
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($mimeType, $allowedTypes)) exit("JPEG, PNG, GIFのみ対応");

        if ($_FILES['image']['size'] > 5 * 1024 * 1024) exit("5MBまで");

        $uploadDir = '/var/www/upload/image/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $pathinfo = pathinfo($_FILES['image']['name']);
        $image_filename = time() . bin2hex(random_bytes(16)) . '.' . $pathinfo['extension'];
        if (!move_uploaded_file($tmpFile, $uploadDir . $image_filename)) {
            exit("ファイルのアップロードに失敗しました。");
        }
    }

    $insert = $dbh->prepare(
        "INSERT INTO bbs_entries (body, image_filename, likes, created_at) VALUES (:body, :image_filename, 0, NOW())"
    );
    $insert->execute([
        ':body' => $_POST['body'],
        ':image_filename' => $image_filename
    ]);

    header("Location: ./omoti_test.php");
    exit;
}

// --- 投稿取得 ---
$entries = [];
try {
    $select = $dbh->query('SELECT * FROM bbs_entries ORDER BY created_at DESC LIMIT 20');
    if ($select !== false) {
        $entries = $select->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $entries = [];
}

// --- 相対時間関数 ---
function time_ago($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) return $diff . '秒前';
    if ($diff < 3600) return floor($diff / 60) . '分前';
    if ($diff < 86400) return floor($diff / 3600) . '時間前';
    return floor($diff / 86400) . '日前';
}

// --- アンカーリンク化 ---
function anchorize($text) {
    return preg_replace('/&gt;&gt;(\d+)/', '<a href="#post-$1">&gt;&gt;$1</a>', $text);
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>画像付き掲示板 改善版</title>
<style>
body { font-family: sans-serif; max-width: 700px; margin: auto; background:#f9f9f9; padding:10px; }
h1 { text-align:center; color:#333; font-size:1.5em; }
form { background:#fff; padding:1em; border-radius:10px; margin-bottom:1em; box-shadow:0 2px 5px rgba(0,0,0,0.1);}
textarea { width:100%; height:80px; padding:0.5em; border-radius:5px; border:1px solid #ccc; font-size:1em; }
input[type="file"] { margin-top:0.5em; }
button { background:#4CAF50; color:white; border:none; padding:0.5em 1em; border-radius:5px; cursor:pointer; font-size:1em; }
button:hover { background:#45a049; }
.card { background:#fff; padding:1em; margin-bottom:1em; border-radius:10px; box-shadow:0 2px 5px rgba(0,0,0,0.1);}
.card img { max-width:100%; border-radius:5px; margin-top:0.5em; }
.anchor { color:#007bff; cursor:pointer; text-decoration:none; }
.anchor:hover { text-decoration:underline; }
.like-btn { background:#e91e63; color:white; border:none; padding:0.3em 0.6em; border-radius:5px; cursor:pointer; margin-top:5px; }
.like-btn:hover { background:#c2185b; }
@media (max-width: 600px) {
    body { padding:5px; }
    h1 { font-size:1.2em; }
    textarea { font-size:0.9em; }
    button { width:100%; padding:0.8em; }
}
</style>
</head>
<body>
<h1>画像付き掲示板 改善版</h1>

<form method="POST" enctype="multipart/form-data" id="postForm">
    <textarea name="body" required placeholder="コメントを入力"></textarea>
    <input type="file" accept="image/*" name="image" id="imageInput">
    <img id="preview" style="display:none; max-width:200px; margin-top:0.5em; border-radius:5px;">
    <button type="submit">投稿</button>
</form>

<hr>

<?php if (!empty($entries)): ?>
    <?php foreach ($entries as $entry): ?>
        <div class="card" id="post-<?= htmlspecialchars($entry['id']) ?>">
            <div>
                <strong><a href="javascript:void(0);" class="anchor" data-id="<?= htmlspecialchars($entry['id']) ?>">#<?= htmlspecialchars($entry['id']) ?></a></strong>
                | <small><?= time_ago($entry['created_at']) ?></small>
            </div>
            <div><?= nl2br(anchorize(htmlspecialchars($entry['body'], ENT_QUOTES))) ?></div>
            <?php if (!empty($entry['image_filename'])): ?>
                <img src="/image/<?= htmlspecialchars($entry['image_filename'], ENT_QUOTES) ?>">
            <?php endif; ?>
            <div>
                <button class="like-btn" data-id="<?= htmlspecialchars($entry['id']) ?>">❤️ <?= htmlspecialchars($entry['likes']) ?></button>
            </div>
        </div>
    <?php endforeach; ?>
<?php else: ?>
    <p>投稿はまだありません。</p>
<?php endif; ?>

<script>
// レスアンカー挿入
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('anchor')) {
        const id = e.target.dataset.id;
        const textarea = document.querySelector('textarea[name="body"]');
        textarea.value += ">>" + id + "\n";
        textarea.focus();
    }
});

// いいね処理
document.querySelectorAll('.like-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const id = this.dataset.id;
        fetch("", {
            method: "POST",
            headers: {"Content-Type": "application/x-www-form-urlencoded"},
            body: "like_id=" + encodeURIComponent(id)
        }).then(res => res.json()).then(data => {
            if (data.status === "ok") {
                let count = parseInt(this.textContent.replace("❤️", "").trim());
                this.textContent = "❤️ " + (count + 1);
            }
        });
    });
});

// 画像プレビュー + 自動縮小
const input = document.getElementById('imageInput');
const preview = document.getElementById('preview');
const form = document.getElementById('postForm');

form.addEventListener('submit', function(e) {
    if (input.files.length === 0) return;
    e.preventDefault();
    const file = input.files[0];
    const reader = new FileReader();
    reader.onload = function(event) {
        const img = new Image();
        img.onload = function() {
            const canvas = document.createElement('canvas');
            const MAX_SIZE = 1200;
            let width = img.width;
            let height = img.height;
            if (width > height && width > MAX_SIZE) {
                height *= MAX_SIZE / width;
                width = MAX_SIZE;
            } else if (height > MAX_SIZE) {
                width *= MAX_SIZE / height;
                height = MAX_SIZE;
            }
            canvas.width = width;
            canvas.height = height;
            const ctx = canvas.getContext("2d");
            ctx.drawImage(img, 0, 0, width, height);

            canvas.toBlob(function(blob) {
                if (blob.size > 5*1024*1024) {
                    alert("縮小後も5MBを超えています。");
                    return;
                }
                const newFile = new File([blob], file.name, {type: blob.type});
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(newFile);
                input.files = dataTransfer.files;
                form.submit();
            }, file.type, 0.9);
        }
        img.src = event.target.result;
    }
    reader.readAsDataURL(file);
});

input.addEventListener('change', e => {
    const file = e.target.files[0];
    if (!file) { preview.style.display = 'none'; return; }
    const reader = new FileReader();
    reader.onload = e => { preview.src = e.target.result; preview.style.display = 'block'; };
    reader.readAsDataURL(file);
});
</script>
</body>
</html>

