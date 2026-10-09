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

$pdo = db();
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS contact_messages (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(150) NOT NULL,
        email VARCHAR(150) NOT NULL,
        phone VARCHAR(50) DEFAULT NULL,
        topic VARCHAR(80) NOT NULL,
        message TEXT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../contact.html?error=invalid');
    exit;
}

$name = trim((string) ($_POST['name'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$phone = trim((string) ($_POST['phone'] ?? ''));
$topic = trim((string) ($_POST['topic'] ?? ''));
$message = trim((string) ($_POST['message'] ?? ''));

$allowedTopics = [
    'การรับสมัคร (Admissions)',
    'หลักสูตร (Programs)',
    'ทุนการศึกษา (Scholarships)',
    'ร้องเรียน/ร้องทุกข์',
    'อื่นๆ (Other)',
];

if ($name === '' || $email === '' || $message === '' || !in_array($topic, $allowedTopics, true)) {
    header('Location: ../contact.html?error=invalid');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ../contact.html?error=invalid');
    exit;
}

if (strlen($name) > 150 || strlen($email) > 150 || strlen($phone) > 50 || strlen($topic) > 80 || strlen($message) > 4000) {
    header('Location: ../contact.html?error=invalid');
    exit;
}

$statement = $pdo->prepare(
    'INSERT INTO contact_messages (name, email, phone, topic, message) VALUES (:name, :email, :phone, :topic, :message)'
);
$statement->execute([
    'name' => $name,
    'email' => $email,
    'phone' => $phone === '' ? null : $phone,
    'topic' => $topic,
    'message' => $message,
]);

header('Location: ../contact.html?success=1');
exit;
