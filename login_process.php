<?php
session_start();
require_once __DIR__ . "/includes/db.php";

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

// For hardcoded admin login (optional)
if($username === 'admin' && $password === 'admin123') {
    $_SESSION['user_id'] = 1;  // fixed admin ID
    $_SESSION['username'] = 'admin';
    $_SESSION['role'] = 'admin';
    header("Location: admin/dashboard.php");
    exit;
}

// Check DB for any user
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if($user) {
    // Use plain password check or password_verify depending on your DB
    if($password === $user['password'] || password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];  // <-- Important: take role from DB

        // Redirect based on role
        if($user['role'] === 'admin') {
            header("Location: admin/dashboard.php");
        } else {
            header("Location: user/dashboard.php");
        }
        exit;
    } else {
        header("Location: index.php?error=invalid_password");
        exit;
    }
} else {
    header("Location: index.php?error=user_not_found");
    exit;
}
