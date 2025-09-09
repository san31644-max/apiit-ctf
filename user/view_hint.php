<?php
session_start();
require_once __DIR__ . "/../includes/db.php";

// JSON response
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$hint_id = isset($input['hint_id']) ? (int)$input['hint_id'] : 0;
if ($hint_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid hint id']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// Fetch hint (content + cost) and ensure it exists
$stmt = $pdo->prepare("SELECT id, content, point_cost FROM hints WHERE id = ?");
$stmt->execute([$hint_id]);
$hint = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$hint) {
    echo json_encode(['success' => false, 'message' => 'Hint not found']);
    exit;
}
$cost = (int)$hint['point_cost'];

// Check if user already viewed
$stmt = $pdo->prepare("SELECT 1 FROM hint_views WHERE user_id = ? AND hint_id = ?");
$stmt->execute([$user_id, $hint_id]);
if ($stmt->fetch()) {
    // Already viewed — return content and current score
    $stmt2 = $pdo->prepare("SELECT score FROM users WHERE id = ?");
    $stmt2->execute([$user_id]);
    $score = (int)$stmt2->fetchColumn();
    echo json_encode(['success' => true, 'already_viewed' => true, 'new_score' => $score, 'content' => $hint['content']]);
    exit;
}

// Deduct points atomically and insert view record
// Use UPDATE ... WHERE score >= cost to avoid negatives
$pdo->beginTransaction();
try {
    $stmtUpd = $pdo->prepare("UPDATE users SET score = score - ? WHERE id = ? AND score >= ?");
    $stmtUpd->execute([$cost, $user_id, $cost]);
    if ($stmtUpd->rowCount() === 0) {
        // not enough score or user not found
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Not enough points to view this hint']);
        exit;
    }

    // record the hint view
    $stmtIns = $pdo->prepare("INSERT INTO hint_views (user_id, hint_id, viewed_at) VALUES (?, ?, NOW())");
    $stmtIns->execute([$user_id, $hint_id]);

    // get new score
    $stmt2 = $pdo->prepare("SELECT score FROM users WHERE id = ?");
    $stmt2->execute([$user_id]);
    $new_score = (int)$stmt2->fetchColumn();

    $pdo->commit();

    echo json_encode(['success' => true, 'new_score' => $new_score, 'content' => $hint['content']]);
    exit;
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("view_hint error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
    exit;
}
