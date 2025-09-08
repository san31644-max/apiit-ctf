<?php
session_start();
require_once __DIR__ . "/../includes/db.php";

// Only admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$challenge = $pdo->prepare("SELECT * FROM challenges WHERE id = ?");
$challenge->execute([$id]);
$challenge = $challenge->fetch();

if (!$challenge) {
    die("Challenge not found!");
}

// Fetch categories
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $points = (int)$_POST['points'];
    $link = trim($_POST['link']);
    $flag = trim($_POST['flag']);
    $tags = trim($_POST['tags']);
    $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    
    // File upload
    $file_path = $challenge['file_path'];
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../uploads/challenges/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $filename = time() . '_' . basename($_FILES['file']['name']);
        $targetFile = $uploadDir . $filename;
        if (move_uploaded_file($_FILES['file']['tmp_name'], $targetFile)) {
            if ($file_path && file_exists(__DIR__ . '/../' . $file_path)) unlink(__DIR__ . '/../' . $file_path);
            $file_path = 'uploads/challenges/' . $filename;
        } else {
            $error = "File upload failed!";
        }
    }

    if (!$error) {
        $stmt = $pdo->prepare("UPDATE challenges SET title=?, description=?, points=?, file_path=?, link=?, flag=?, tags=?, category_id=? WHERE id=?");
        if ($stmt->execute([$title, $description, $points, $file_path, $link, $flag, $tags, $category_id, $id])) {
            $success = "✅ Challenge updated!";
            $challenge = array_merge($challenge, $_POST); // Update local array
            $challenge['file_path'] = $file_path;
        } else {
            $error = "Database error!";
        }
    }
}
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Edit Challenge — Admin</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-green-300 font-mono p-6">
<h1 class="text-2xl font-bold mb-4">Edit Challenge</h1>

<?php if($success) echo "<div class='mb-4 p-3 bg-green-900/30 border border-green-500 rounded'>$success</div>"; ?>
<?php if($error) echo "<div class='mb-4 p-3 bg-red-900/30 border border-red-500 rounded'>$error</div>"; ?>

<form method="POST" enctype="multipart/form-data" class="space-y-4 max-w-xl">
    <div>
        <label>Title</label>
        <input type="text" name="title" value="<?= htmlspecialchars($challenge['title']) ?>" required class="w-full px-3 py-2 rounded-md" />
    </div>
    <div>
        <label>Category</label>
        <select name="category_id" class="w-full px-3 py-2 rounded-md">
            <option value="">-- Select Existing Category --</option>
            <?php foreach($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= $cat['id']==$challenge['category_id']?'selected':'' ?>>
                    <?= htmlspecialchars($cat['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label>Description</label>
        <textarea name="description" rows="4" class="w-full px-3 py-2 rounded-md"><?= htmlspecialchars($challenge['description']) ?></textarea>
    </div>
    <div>
        <label>Points</label>
        <input type="number" name="points" value="<?= $challenge['points'] ?>" class="w-full px-3 py-2 rounded-md" />
    </div>
    <div>
        <label>File Upload (optional)</label>
        <input type="file" name="file" class="w-full px-3 py-2 rounded-md" />
        <?php if($challenge['file_path']): ?>
            <p>Current file: <a href="../<?= $challenge['file_path'] ?>" class="text-blue-400"><?= basename($challenge['file_path']) ?></a></p>
        <?php endif; ?>
    </div>
    <div>
        <label>Link</label>
        <input type="url" name="link" value="<?= htmlspecialchars($challenge['link']) ?>" class="w-full px-3 py-2 rounded-md" />
    </div>
    <div>
        <label>Flag</label>
        <input type="text" name="flag" value="<?= htmlspecialchars($challenge['flag']) ?>" class="w-full px-3 py-2 rounded-md" />
    </div>
    <div>
        <label>Tags</label>
        <input type="text" name="tags" value="<?= htmlspecialchars($challenge['tags']) ?>" class="w-full px-3 py-2 rounded-md" />
    </div>
    <button type="submit" class="px-6 py-2 rounded-md bg-green-500 text-black font-bold hover:bg-green-400">Update Challenge</button>
</form>
</body>
</html>
