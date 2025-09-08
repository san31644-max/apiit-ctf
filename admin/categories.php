<?php
session_start();
require_once __DIR__ . "/../includes/db.php";

// Only admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

// Add category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'], $_POST['description'])) {
    $stmt = $pdo->prepare("INSERT INTO categories (name, description, created_at) VALUES (?, ?, NOW())");
    $stmt->execute([$_POST['name'], $_POST['description']]);
    header("Location: categories.php");
    exit;
}

// Delete category
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header("Location: categories.php");
    exit;
}

// Fetch categories
$stmt = $pdo->query("SELECT * FROM categories ORDER BY created_at DESC");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Categories — Admin</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
body { font-family: 'Source Code Pro', monospace; background:#0b0f12; color:#c9f7e4; }
.sidebar { background:#071018; border-right:1px solid rgba(45,226,138,0.2); }
.sidebar a { display:block; padding:12px; color:#c9f7e4; border-bottom:1px solid rgba(255,255,255,0.05); }
.sidebar a:hover { background:rgba(45,226,138,0.1); color:#2de28a; }
.table-header { background: rgba(8,11,18,0.9); border-bottom:1px solid rgba(45,226,138,0.3); }
.table-row { border-bottom:1px solid rgba(45,226,138,0.1); transition: background 0.2s; }
.table-row:hover { background: rgba(45,226,138,0.1); }
input, textarea { background:#0f161d; border:1px solid rgba(45,226,138,0.3); color:#c9f7e4; }
input:focus, textarea:focus { outline:none; border-color:#2de28a; box-shadow:0 0 4px #2de28a; }
button { background:#2de28a; color:#0b0f12; font-weight:bold; }
button:hover { background:#24b96f; }
</style>
</head>
<body class="h-screen flex">

<!-- Sidebar -->
<div class="sidebar w-64">
  <h2 class="text-green-400 text-xl font-bold p-4 border-b border-green-500">APIIT CTF</h2>
  <a href="dashboard.php">🏠 Dashboard</a>
  <a href="add_challenge.php">➕ Add Challenge</a>
  <a href="categories.php">📂 Categories</a>
  <a href="manage_users.php">👥 Manage Users</a>
  <a href="leaderboard.php">🏆 Leaderboard</a>
  <a href="../index.php" class="text-red-400">🚪 Logout</a>
</div>

<!-- Main Content -->
<div class="flex-1 p-6 overflow-auto">
  <h1 class="text-3xl font-bold text-green-400 mb-6">Manage Categories</h1>

  <!-- Add New Category -->
  <form method="POST" class="mb-8 grid gap-4 max-w-lg">
    <input type="text" name="name" placeholder="Category Name" required class="p-3 rounded">
    <textarea name="description" placeholder="Category Description" required class="p-3 rounded"></textarea>
    <button type="submit" class="px-5 py-2 rounded">➕ Add Category</button>
  </form>

  <!-- Categories Table -->
  <div class="overflow-x-auto">
    <table class="w-full text-left">
      <thead class="table-header text-green-300">
        <tr>
          <th class="px-4 py-2">ID</th>
          <th class="px-4 py-2">Name</th>
          <th class="px-4 py-2">Description</th>
          <th class="px-4 py-2">Created At</th>
          <th class="px-4 py-2">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($categories as $cat): ?>
        <tr class="table-row">
          <td class="px-4 py-2"><?= htmlspecialchars($cat['id']) ?></td>
          <td class="px-4 py-2 font-semibold text-green-400"><?= htmlspecialchars($cat['name']) ?></td>
          <td class="px-4 py-2"><?= htmlspecialchars($cat['description']) ?></td>
          <td class="px-4 py-2 text-sm text-gray-400"><?= htmlspecialchars($cat['created_at']) ?></td>
          <td class="px-4 py-2">
            <a href="?delete=<?= $cat['id'] ?>" onclick="return confirm('Delete this category?')" class="text-red-400 hover:underline">🗑 Delete</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

</body>
</html>
