<?php
declare(strict_types=1);

function db(): PDO
{
	static $pdo = null;

	if ($pdo instanceof PDO) {
		return $pdo;
	}

	$host = getenv('DB_HOST') ?: 'localhost';
	$port = getenv('DB_PORT') ?: '3306';
	$database = getenv('DB_NAME') ?: 'admin_db';
	$username = getenv('DB_USER') ?: 'root';
	$password = getenv('DB_PASSWORD') ?: '';
	$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database);

	$pdo = new PDO($dsn, $username, $password, [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		PDO::ATTR_EMULATE_PREPARES => false,
	]);

	return $pdo;
}
function escape(mixed $value): string
{
	return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
	if (empty($_SESSION['csrf_token'])) {
		$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
	}

	return (string) $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
	$submittedToken = $_POST['csrf_token'] ?? '';
	$sessionToken = $_SESSION['csrf_token'] ?? '';

	if (!is_string($submittedToken) || !is_string($sessionToken) || $sessionToken === '' || !hash_equals($sessionToken, $submittedToken)) {
		http_response_code(403);
		exit('คำขอไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง');
	}
}
