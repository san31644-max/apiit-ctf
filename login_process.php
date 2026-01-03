<?php
session_start();
require_once __DIR__ . "/includes/db.php";

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
  exit;
}

$username = trim($_POST['username'] ?? '');
$password = (string)($_POST['password'] ?? '');

if ($username === '' || $password === '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'message' => 'Missing credentials']);
  exit;
}

/* OPTIONAL hardcoded admin */
if ($username === 'admin' && $password === 'admin123') {
  session_regenerate_id(true);
  $_SESSION['user_id'] = 1;
  $_SESSION['username'] = 'admin';
  $_SESSION['role'] = 'admin';

  echo json_encode(['ok' => true, 'redirect' => 'admin/dashboard.php']);
  exit;
}

/* DB user */
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'message' => 'User not found']);
  exit;
}

/**
 * IMPORTANT SECURITY NOTE:
 * Your code checks BOTH plain compare and password_verify.
 * Keep it only if your DB contains some plain passwords.
 * Best: store only password_hash and use password_verify only.
 */
$passOk = false;
if (isset($user['password'])) {
  // supports both plain + hashed (your current behavior)
  if ($password === $user['password'] || password_verify($password, $user['password'])) {
    $passOk = true;
  }
}

if (!$passOk) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'message' => 'Invalid password']);
  exit;
}

session_regenerate_id(true);

$_SESSION['user_id']  = $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['role']     = $user['role']; // from DB

$redirect = ($user['role'] === 'admin') ? 'admin/dashboard.php' : 'user/dashboard.php';
echo json_encode(['ok' => true, 'redirect' => $redirect]);
