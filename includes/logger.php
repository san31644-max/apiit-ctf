<?php
// includes/logger.php

if (!function_exists('log_activity')) {
    /**
     * Log user activity
     *
     * @param PDO $pdo       Database connection
     * @param int $user_id   User ID performing the action
     * @param string $action Description of the action
     * @param string|null $page_url Optional page URL
     */
    function log_activity(PDO $pdo, int $user_id, string $action, ?string $page_url = null) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO user_activity (user_id, ip_address, user_agent, action, page_url, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");

            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

            $stmt->execute([$user_id, $ip, $user_agent, $action, $page_url]);
        } catch (PDOException $e) {
            // Optional: log to a file instead of breaking the page
            error_log("Failed to log activity: " . $e->getMessage());
        }
    }
}
