<?php
session_start();
require_once __DIR__ . "/../includes/db.php";

// Only admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$success = $error = '';

// Fetch categories for dropdown
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $points = (int)$_POST['points'];
    $link = trim($_POST['link']);
    $flag = trim($_POST['flag']);
    $tags = trim($_POST['tags']);
    $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $new_category = trim($_POST['new_category']);

    // If admin typed a new category → insert and use its id
    if ($new_category !== '') {
        $stmtCat = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
        $stmtCat->execute([$new_category]);
        $category_id = $pdo->lastInsertId();
    }

    // File upload
    $file_path = null;
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../uploads/challenges/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $filename = time() . '_' . basename($_FILES['file']['name']);
        $targetFile = $uploadDir . $filename;

        if (move_uploaded_file($_FILES['file']['tmp_name'], $targetFile)) {
            $file_path = 'uploads/challenges/' . $filename;
        } else {
            $error = "File upload failed!";
        }
    }

    if (!$error) {
        $stmt = $pdo->prepare("INSERT INTO challenges (title, description, points, file_path, link, flag, tags, category_id)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$title, $description, $points, $file_path, $link, $flag, $tags, $category_id])) {
            $success = "✅ Challenge added successfully!";
        } else {
            $error = "Database error!";
        }
    }
}
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Add Challenge — Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    body { font-family: 'Source Code Pro', monospace; background:#0b0f12; color:#c9f7e4; }
    .sidebar { background:#071018; border-right:1px solid rgba(45,226,138,0.2); }
    .sidebar a { display:block; padding:12px; color:#c9f7e4; border-bottom:1px solid rgba(255,255,255,0.05); transition:0.2s; }
    .sidebar a:hover { background:rgba(45,226,138,0.1); color:#2de28a; }
    input, textarea, select { background: rgba(255,255,255,0.05); border:1px solid rgba(45,226,138,0.3); color:#c9f7e4; }
    input:focus, textarea:focus, select:focus { outline:none; border-color:#2de28a; }
  </style>
</head>
<body class="h-screen flex">

<div class="sidebar w-64">
  <h2 class="text-green-400 text-xl font-bold p-4 border-b border-green-500">APIIT CTF</h2>
  <a href="dashboard.php">🏠 Dashboard</a>
  <a href="add_challenge.php">➕ Add Challenge</a>
  <a href="manage_challenges.php">📋 Manage Challenges</a>
  <a href="manage_users.php">👥 Manage Users</a>
  <a href="manage_hints.php">👥 Manage Hints</a>
  <a href="leaderboard.php">🏆 Leaderboard</a>
  <a href="../logout.php" class="text-red-400">🚪 Logout</a>
</div>

  <!-- Main Content -->
  <div class="flex-1 p-6">
    <h1 class="text-2xl font-bold text-green-400 mb-4">Add New Challenge</h1>

    <?php if($success): ?>
      <div class="mb-4 p-3 bg-green-900/30 border border-green-500 rounded text-green-300"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if($error): ?>
      <div class="mb-4 p-3 bg-red-900/30 border border-red-500 rounded text-red-400"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form action="" method="POST" enctype="multipart/form-data" class="space-y-4 max-w-xl">
      <div>
        <label>Title</label>
        <input type="text" name="title" required class="w-full px-3 py-2 rounded-md" />
      </div>

      <!-- Category -->
      <div>
        <label>Select Category</label>
        <select name="category_id" class="w-full px-3 py-2 rounded-md mb-2">
          <option value="">-- Select Existing Category --</option>
          <?php foreach ($categories as $cat): ?>
              <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Or Add New Category</label>
        <input type="text" name="new_category" placeholder="e.g., Web, Crypto, Forensics" class="w-full px-3 py-2 rounded-md" />
      </div>

      <div>
        <label>Description</label>
        <textarea name="description" rows="4" required class="w-full px-3 py-2 rounded-md"></textarea>
      </div>
      <div>
        <label>Points</label>
        <input type="number" name="points" required class="w-full px-3 py-2 rounded-md" />
      </div>
      <div>
        <label>File Upload (optional)</label>
        <input type="file" name="file" class="w-full px-3 py-2 rounded-md" />
      </div>
      <div>
        <label>Link (optional)</label>
        <input type="url" name="link" class="w-full px-3 py-2 rounded-md" />
      </div>
      <div>
        <label>Correct Flag</label>
        <input type="text" name="flag" required class="w-full px-3 py-2 rounded-md" />
      </div>
      <div>
        <label>Tags (comma-separated, e.g., web, crypto)</label>
        <input type="text" name="tags" placeholder="web,crypto" class="w-full px-3 py-2 rounded-md" />
      </div>
      <button type="submit" class="px-6 py-2 rounded-md bg-green-500 text-black font-bold hover:bg-green-400">
        Add Challenge
      </button>
    </form>
  </div>

</body>
</html>
