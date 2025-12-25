<?php
/**
 * No-DB IP Logger (JSONL file)
 * Stores each record as one JSON line in logs/ip_logs.jsonl
 */

function get_client_ip_filelogger(array $trustedProxies = []): string {
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!$remote) return '0.0.0.0';

    $isTrusted = in_array($remote, $trustedProxies, true);

    // Only trust forwarded headers if REMOTE_ADDR is a trusted proxy
    if ($isTrusted) {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
            foreach ($parts as $ip) {
                if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
            }
        }
        if (!empty($_SERVER['HTTP_X_REAL_IP']) && filter_var($_SERVER['HTTP_X_REAL_IP'], FILTER_VALIDATE_IP)) {
            return $_SERVER['HTTP_X_REAL_IP'];
        }
    }

    return $remote;
}

function ip_log_path(): string {
    // logs folder at project root: /logs/ip_logs.jsonl
    return realpath(__DIR__ . "/..") . DIRECTORY_SEPARATOR . "logs" . DIRECTORY_SEPARATOR . "ip_logs.jsonl";
}

function log_ip_to_file(int $userId, string $username, string $role, string $event = 'login', array $trustedProxies = []): void {
    $path = ip_log_path();

    // Ensure logs folder exists
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $record = [
        'ts'       => date('c'),
        'event'    => $event,
        'user_id'  => $userId,
        'username' => $username,
        'role'     => $role,
        'ip'       => get_client_ip_filelogger($trustedProxies),
        'ua'       => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 180),
        'uri'      => $_SERVER['REQUEST_URI'] ?? '',
    ];

    $line = json_encode($record, JSON_UNESCAPED_SLASHES) . PHP_EOL;

    // LOCK to avoid corruption when many users login simultaneously
    $fp = @fopen($path, 'ab');
    if (!$fp) return;

    @flock($fp, LOCK_EX);
    @fwrite($fp, $line);
    @flock($fp, LOCK_UN);
    @fclose($fp);
}
