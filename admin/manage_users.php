<?php
session_start();
require_once __DIR__ . "/../includes/db.php";

// Only admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$success = $error = '';

// Handle Add User Form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $role = $_POST['role'];

    if ($username && $password && in_array($role, ['admin','user'])) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        try {
            $stmt->execute([$username, $hashedPassword, $role]);
            $success = "User '$username' added successfully!";
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    } else {
        $error = "Please fill all fields correctly.";
    }
}

// Fetch all users
$stmt = $pdo->query("SELECT * FROM users ORDER BY id DESC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Manage Users — Admin</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
body { font-family: 'Source Code Pro', monospace; background:#0b0f12; color:#c9f7e4; }
.sidebar { background:#071018; border-right:1px solid rgba(45,226,138,0.2); }
.sidebar a { display:block; padding:12px; color:#c9f7e4; border-bottom:1px solid rgba(255,255,255,0.05); }
.sidebar a:hover { background:rgba(45,226,138,0.1); color:#2de28a; }
.glass { backdrop-filter: blur(8px); background: rgba(0,0,0,0.5); border: 1px solid rgba(45,226,138,0.3); }
input, select { background: rgba(255,255,255,0.05); border:1px solid rgba(45,226,138,0.3); color:#c9f7e4; }
input:focus, select:focus { outline:none; border-color:#2de28a; }
table { width:100%; border-collapse: collapse; }
th, td { padding: 8px 12px; border-bottom: 1px solid rgba(45,226,138,0.2); text-align:left; }
th { color:#2de28a; }
tr:hover { background: rgba(45,226,138,0.05); }
</style>
</head>
<body class="h-screen flex">

<!-- Sidebar -->
<div class="sidebar w-64">
  <h2 class="text-green-400 text-xl font-bold p-4 border-b border-green-500">APIIT CTF</h2>
  <a href="dashboard.php">🏠 Dashboard</a>
  <a href="categories.php">➕ Add Categories</a>
  <a href="add_challenge.php">➕ Add Challenge</a>
  <a href="manage_users.php">👥 Manage Users</a>
  <a href="leaderboard.php">🏆 Leaderboard</a>
  <a href="../index.php" class="text-red-400">🚪 Logout</a>
</div>

<!-- Main Content -->
<div class="flex-1 p-6 overflow-auto">
  <h1 class="text-2xl font-bold text-green-400 mb-4">Manage Users</h1>

  <?php if($success): ?>
    <div class="mb-4 p-3 bg-green-900/30 border border-green-500 rounded text-green-300"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <?php if($error): ?>
    <div class="mb-4 p-3 bg-red-900/30 border border-red-500 rounded text-red-400"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <!-- Add User Form -->
  <div class="glass p-4 rounded mb-6 max-w-md">
    <h2 class="text-green-300 font-semibold mb-2">Add New User</h2>
    <form action="" method="POST" class="space-y-3">
      <div>
        <label>Username</label>
        <input type="text" name="username" required class="w-full px-3 py-2 rounded-md" />
      </div>
      <div>
        <label>Password</label>
        <input type="password" name="password" required class="w-full px-3 py-2 rounded-md" />
      </div>
      <div>
        <label>Role</label>
        <select name="role" required class="w-full px-3 py-2 rounded-md">
          <option value="user">User</option>
          <option value="admin">Admin</option>
        </select>
      </div>
      <button type="submit" class="px-6 py-2 rounded-md bg-green-500 text-black font-bold">Add User</button>
    </form>
  </div>

  <!-- Users Table -->
  <div class="glass p-4 rounded">
    <h2 class="text-green-300 font-semibold mb-2">All Users</h2>
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Username</th>
          <th>Role</th>
          <th>Score</th>
          <th>Created At</th>
          <th>Last Login</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($users as $u): ?>
          <tr>
            <td><?= $u['id'] ?></td>
            <td><?= htmlspecialchars($u['username']) ?></td>
            <td><?= $u['role'] ?></td>
            <td><?= $u['score'] ?></td>
            <td><?= $u['created_at'] ?></td>
            <td><?= $u['last_login'] ?? '-' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

</body>
</html>
