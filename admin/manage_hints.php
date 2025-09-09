<?php
session_start();
require_once __DIR__ . "/../includes/db.php";

// Only admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

// Fetch challenges for dropdown
$challenges = $pdo->query("SELECT id, title FROM challenges ORDER BY points DESC")->fetchAll(PDO::FETCH_ASSOC);

// Add new hint
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_hint'])) {
    $challenge_id = !empty($_POST['challenge_id']) ? (int)$_POST['challenge_id'] : null;
    $title = $_POST['title'];
    $content = $_POST['content'];
    $point_cost = (int)$_POST['point_cost'];
    $stmt = $pdo->prepare("INSERT INTO hints (challenge_id, title, content, point_cost) VALUES (?, ?, ?, ?)");
    $stmt->execute([$challenge_id, $title, $content, $point_cost]);
    header("Location: manage_hints.php");
    exit;
}

// Delete hint
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM hints WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header("Location: manage_hints.php");
    exit;
}

// Fetch all hints with challenge title
$hints = $pdo->query("SELECT h.*, c.title AS challenge_title FROM hints h LEFT JOIN challenges c ON h.challenge_id = c.id ORDER BY h.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Manage Hints — Admin</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
body { font-family: 'Source Code Pro', monospace; background:#0b0f12; color:#c9f7e4; }
.container { max-width: 1200px; margin: auto; padding: 2rem; }
input, textarea, select { border:1px solid rgba(45,226,138,0.3); background: rgba(255,255,255,0.05); color:#c9f7e4; padding:10px; border-radius:6px; width:100%; }
input:focus, textarea:focus, select:focus { outline:none; border-color:#2de28a; }
button { background:#2de28a; color:#000; font-weight:bold; padding:10px 16px; border:none; border-radius:6px; cursor:pointer; transition:0.3s; }
button:hover { background:#1ab66b; }
table { width:100%; border-collapse: collapse; margin-top: 1rem; }
th, td { padding: 12px; border: 1px solid rgba(45,226,138,0.3); text-align: left; }
th { background: rgba(45,226,138,0.1); }
</style>
</head>
<body>
<div class="container">
<h1 class="text-3xl font-bold text-green-400 mb-6">Manage Hints</h1>

<!-- Add new hint -->
<form method="POST" class="mb-6 p-6 rounded" style="background: rgba(8,11,18,0.95); border:1px solid rgba(45,226,138,0.3);">
    <h2 class="text-xl font-semibold mb-4">Add New Hint</h2>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
        <div class="md:col-span-2">
            <input type="text" name="title" placeholder="Hint title" required>
        </div>
        <div>
            <input type="number" name="point_cost" placeholder="Points to Deduct" min="1" value="10" required>
        </div>
    </div>

    <div class="mb-4">
        <label class="block mb-1">Associate with Challenge (Optional)</label>
        <select name="challenge_id" class="w-full p-3 rounded bg-gray-700 text-white border border-green-500 focus:border-green-400">
            <option value="">-- None --</option>
            <?php foreach($challenges as $c): ?>
                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['title']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <textarea name="content" placeholder="Hint content" required class="w-full p-4 rounded bg-gray-700 text-white border border-green-500 focus:border-green-400 mb-4"></textarea>
    <button type="submit" name="add_hint">Add Hint</button>
</form>

<!-- List hints -->
<h2 class="text-xl font-semibold mb-2">All Hints</h2>
<table>
    <thead>
        <tr>
            <th>Title</th>
            <th>Content</th>
            <th>Challenge</th>
            <th>Points</th>
            <th>Created</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach($hints as $hint): ?>
        <tr>
            <td><?= htmlspecialchars($hint['title']) ?></td>
            <td><?= nl2br(htmlspecialchars($hint['content'])) ?></td>
            <td><?= htmlspecialchars($hint['challenge_title'] ?? '—') ?></td>
            <td><?= $hint['point_cost'] ?></td>
            <td><?= $hint['created_at'] ?></td>
            <td>
                <a href="manage_hints.php?delete=<?= $hint['id'] ?>" onclick="return confirm('Delete this hint?')" class="text-red-400 hover:underline">Delete</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
</body>
</html>
