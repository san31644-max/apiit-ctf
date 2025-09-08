<?php
session_start();
require_once __DIR__ . "/../includes/db.php";

// Only admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

// Delete challenge if ?delete=id is set
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    // Optionally: delete uploaded file
    $filePath = $pdo->query("SELECT file_path FROM challenges WHERE id=$id")->fetchColumn();
    if ($filePath && file_exists(__DIR__ . "/../" . $filePath)) unlink(__DIR__ . "/../" . $filePath);

    $stmt = $pdo->prepare("DELETE FROM challenges WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: manage_challenges.php");
    exit;
}

// Fetch all challenges
$challenges = $pdo->query("SELECT c.*, cat.name AS category_name 
                           FROM challenges c 
                           LEFT JOIN categories cat ON c.category_id = cat.id
                           ORDER BY c.id DESC")->fetchAll();
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Manage Challenges — Admin</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-green-300 font-mono p-6">
<h1 class="text-2xl font-bold mb-4">Manage Challenges</h1>

<table class="table-auto border border-green-500 w-full">
    <thead>
        <tr class="bg-green-800">
            <th class="px-2 py-1 border">ID</th>
            <th class="px-2 py-1 border">Title</th>
            <th class="px-2 py-1 border">Category</th>
            <th class="px-2 py-1 border">Points</th>
            <th class="px-2 py-1 border">Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($challenges as $c): ?>
        <tr class="hover:bg-green-800/50">
            <td class="px-2 py-1 border"><?= $c['id'] ?></td>
            <td class="px-2 py-1 border"><?= htmlspecialchars($c['title']) ?></td>
            <td class="px-2 py-1 border"><?= htmlspecialchars($c['category_name']) ?></td>
            <td class="px-2 py-1 border"><?= $c['points'] ?></td>
            <td class="px-2 py-1 border">
                <a href="edit_challenge.php?id=<?= $c['id'] ?>" class="text-blue-400 hover:underline">Edit</a> | 
                <a href="?delete=<?= $c['id'] ?>" class="text-red-400 hover:underline"
                   onclick="return confirm('Are you sure you want to delete this challenge?')">Delete</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</body>
</html>
