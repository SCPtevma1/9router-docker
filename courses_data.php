<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
	require_once __DIR__ . '/admin/db.php';

	$items = db()->query(
		"SELECT course_code, course_name, faculty, degree, department, description, credits
		FROM courses
		WHERE status = 'active'
		ORDER BY department ASC, course_name ASC"
	)->fetchAll();

	echo json_encode(
		['items' => $items],
		JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
	);
} catch (Throwable $exception) {
	error_log('Public courses query failed: ' . $exception->getMessage());
	http_response_code(500);
	echo json_encode(['items' => [], 'error' => 'ไม่สามารถโหลดข้อมูลหลักสูตรได้'], JSON_UNESCAPED_UNICODE);
}