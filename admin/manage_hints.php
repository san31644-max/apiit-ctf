<?php
session_start();
require_once __DIR__ . "/../includes/db.php";

// Only admin access
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../index.php");
    exit;
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$message = '';
$messageType = 'info'; // success | error | warn | info

// Fetch challenges for dropdown
$challenges = $pdo->query("SELECT id, title FROM challenges ORDER BY points DESC")
                  ->fetchAll(PDO::FETCH_ASSOC);

// Add new hint
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_hint'])) {
    $challenge_id = isset($_POST['challenge_id']) && $_POST['challenge_id'] !== '' ? (int)$_POST['challenge_id'] : null;
    $title = trim((string)($_POST['title'] ?? ''));
    $content = trim((string)($_POST['content'] ?? ''));
    $point_cost = (int)($_POST['point_cost'] ?? 10);

    if ($title === '' || $content === '' || $point_cost < 1) {
        $message = "⚠️ Please fill all fields correctly.";
        $messageType = "warn";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO hints (challenge_id, title, content, point_cost) VALUES (?, ?, ?, ?)");
            $stmt->execute([$challenge_id, $title, $content, $point_cost]);

            header("Location: manage_hints.php?msg=added");
            exit;
        } catch (Exception $e) {
            $message = "❌ Failed to add hint.";
            $messageType = "error";
        }
    }
}

// Delete hint (SAFE: POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_hint_id'])) {
    $hintId = (int)$_POST['delete_hint_id'];

    try {
        $pdo->beginTransaction();

        // ✅ FIX FK ERROR: delete child rows first
        $stmt = $pdo->prepare("DELETE FROM hint_views WHERE hint_id = ?");
        $stmt->execute([$hintId]);

        // Now delete the hint
        $stmt = $pdo->prepare("DELETE FROM hints WHERE id = ?");
        $stmt->execute([$hintId]);

        $pdo->commit();

        header("Location: manage_hints.php?msg=deleted");
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $message = "❌ Cannot delete hint. It has related records (views/usage).";
        $messageType = "error";
    }
}

// Friendly message via URL
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'added') {
        $message = "✅ Hint added successfully.";
        $messageType = "success";
    } elseif ($_GET['msg'] === 'deleted') {
        $message = "✅ Hint deleted successfully.";
        $messageType = "success";
    }
}

// Fetch all hints with challenge title
$hints = $pdo->query("
    SELECT h.*, c.title AS challenge_title
    FROM hints h
    LEFT JOIN challenges c ON h.challenge_id = c.id
    ORDER BY h.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Manage Hints — Admin</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
@import url('https://fonts.googleapis.com/css2?family=Source+Code+Pro:wght@400;600;800&display=swap');

body { font-family: 'Source Code Pro', monospace; background:#0b0f12; color:#c9f7e4; }
.container { max-width: 1200px; margin: auto; padding: 2rem; }
input, textarea, select {
  border:1px solid rgba(45,226,138,0.3);
  background: rgba(255,255,255,0.05);
  color:#c9f7e4;
  padding:10px;
  border-radius:6px;
  width:100%;
}
input:focus, textarea:focus, select:focus { outline:none; border-color:#2de28a; box-shadow:0 0 12px rgba(45,226,138,0.15); }
button {
  background:#2de28a; color:#000; font-weight:bold;
  padding:10px 16px; border:none; border-radius:6px; cursor:pointer; transition:0.3s;
}
button:hover { background:#1ab66b; }
table { width:100%; border-collapse: collapse; margin-top: 1rem; }
th, td { padding: 12px; border: 1px solid rgba(45,226,138,0.3); text-align: left; vertical-align: top; }
th { background: rgba(45,226,138,0.1); }

.alert{
  padding:12px 14px; border-radius:10px; margin-bottom:14px;
  border:1px solid rgba(45,226,138,0.3);
  background: rgba(255,255,255,0.04);
}
.alert.success{ border-color: rgba(34,197,94,0.35); background: rgba(34,197,94,0.10); color: rgba(187,255,220,0.95); }
.alert.warn{ border-color: rgba(245,158,11,0.35); background: rgba(245,158,11,0.10); color: rgba(255,240,205,0.95); }
.alert.error{ border-color: rgba(244,63,94,0.35); background: rgba(244,63,94,0.10); color: rgba(255,210,220,0.95); }
.small { font-size:12px; color: rgba(201,247,228,0.75); }
</style>
</head>
<body>
<div class="container">
  <h1 class="text-3xl font-bold text-green-400 mb-6">Manage Hints</h1>

  <?php if ($message): ?>
    <div class="alert <?= h($messageType) ?>"><?= h($message) ?></div>
  <?php endif; ?>

  <!-- Add new hint -->
  <form method="POST" class="mb-6 p-6 rounded" style="background: rgba(8,11,18,0.95); border:1px solid rgba(45,226,138,0.3);">
      <h2 class="text-xl font-semibold mb-1">Add New Hint</h2>
      <div class="small mb-4">Hints can be attached to a challenge or standalone.</div>

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
          <select name="challenge_id">
              <option value="">-- None --</option>
              <?php foreach($challenges as $c): ?>
                  <option value="<?= (int)$c['id'] ?>"><?= h($c['title']) ?></option>
              <?php endforeach; ?>
          </select>
      </div>

      <textarea name="content" placeholder="Hint content" required class="mb-4" rows="5"></textarea>
      <button type="submit" name="add_hint">Add Hint</button>
  </form>

  <!-- List hints -->
  <h2 class="text-xl font-semibold mb-2">All Hints</h2>
  <table>
      <thead>
          <tr>
              <th style="width:180px;">Title</th>
              <th>Content</th>
              <th style="width:220px;">Challenge</th>
              <th style="width:90px;">Points</th>
              <th style="width:180px;">Created</th>
              <th style="width:110px;">Actions</th>
          </tr>
      </thead>
      <tbody>
      <?php foreach($hints as $hint): ?>
          <tr>
              <td><?= h($hint['title']) ?></td>
              <td><?= nl2br(h($hint['content'])) ?></td>
              <td><?= h($hint['challenge_title'] ?? '—') ?></td>
              <td><?= (int)$hint['point_cost'] ?></td>
              <td><?= h($hint['created_at']) ?></td>
              <td>
                  <!-- ✅ POST delete to avoid dangerous GET deletes -->
                  <form method="POST" onsubmit="return confirm('Delete this hint? This will also remove its view logs.');">
                      <input type="hidden" name="delete_hint_id" value="<?= (int)$hint['id'] ?>">
                      <button type="submit" style="background:rgba(244,63,94,0.90);color:#fff;">Delete</button>
                  </form>
              </td>
          </tr>
      <?php endforeach; ?>

      <?php if (empty($hints)): ?>
          <tr><td colspan="6" class="small">No hints found.</td></tr>
      <?php endif; ?>
      </tbody>
  </table>
</div>
</body>
</html>
