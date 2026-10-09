<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true,
    ]);
}
require_once __DIR__ . '/admin/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.html');
    exit;
}

if (!empty($_SESSION['member_role']) && !empty($_SESSION['member_id'])) {
    header('Location: intranet.php');
    exit;
}

$roles = [
    'student' => ['table' => 'students', 'id' => 'student_id'],
    'personnel' => ['table' => 'personnel', 'id' => 'personnel_id'],
    'alumni' => ['table' => 'alumni', 'id' => 'alumni_id'],
];
$roleKey = $_POST['role'] ?? '';
$memberId = trim((string) ($_POST['member_id'] ?? ''));
$password = (string) ($_POST['password'] ?? '');

if (!is_string($roleKey) || !isset($roles[$roleKey]) || $memberId === '' || strlen($memberId) > 15 || $password === '') {
    header('Location: login.html?error=invalid');
    exit;
}

try {
    $role = $roles[$roleKey];
    $statement = db()->prepare(
        'SELECT `' . $role['id'] . '`, `password` FROM `' . $role['table'] . '` WHERE `' . $role['id'] . '` = :member_id LIMIT 1'
    );
    $statement->execute(['member_id' => $memberId]);
    $member = $statement->fetch();

    if (!$member || !password_verify($password, (string) $member['password'])) {
        header('Location: login.html?error=invalid');
        exit;
    }

    session_regenerate_id(true);
    $_SESSION['member_role'] = $roleKey;
    $_SESSION['member_id'] = (string) $member[$role['id']];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    header('Location: intranet.php');
    exit;
} catch (Throwable $exception) {
    error_log('Member login failed: ' . $exception->getMessage());
    header('Location: login.html?error=unavailable');
    exit;
}