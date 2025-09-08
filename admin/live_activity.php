<?php
session_start();
require_once __DIR__ . "/../includes/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit;
}

$stmt = $pdo->query("SELECT ua.*, u.username 
                     FROM user_activity ua 
                     JOIN users u ON ua.user_id = u.id
                     ORDER BY ua.created_at DESC
                     LIMIT 50");
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($activities);
