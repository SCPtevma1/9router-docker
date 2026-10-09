<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true,
    ]);
}

require_once __DIR__ . '/db.php';

if (empty($_SESSION['admin_id'])) {
    $scriptPath = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $target = basename($scriptPath !== '' ? $scriptPath : 'dashboard.php');
    $loginPath = 'login.php';
    if (str_contains($scriptPath, '/Management Modules/')) {
        $target = 'Management%20Modules/' . $target;
        $loginPath = '../login.php';
    }
    header('Location: ' . $loginPath . '?redirect=' . rawurlencode($target));
    exit;
}