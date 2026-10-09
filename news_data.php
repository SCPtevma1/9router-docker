<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
	require_once __DIR__ . '/admin/db.php';

	$pdo = db();
	$items = $pdo->query(
		"SELECT id, title, summary, body, content_type, event_date, created_at
		FROM content
		WHERE status = 'published'
		AND content_type IN ('news', 'university', 'faculty', 'event', 'announcement')
		ORDER BY created_at DESC, id DESC"
	)->fetchAll();
	$imagesByContent = [];
	if ($items) {
		$contentIds = array_map(static fn(array $item): int => (int) $item['id'], $items);
		$placeholders = implode(',', array_fill(0, count($contentIds), '?'));
		$imageStatement = $pdo->prepare(
			"SELECT content_id, image_path FROM content_images
			WHERE content_id IN ($placeholders) ORDER BY display_order ASC, id ASC"
		);
		$imageStatement->execute($contentIds);
		foreach ($imageStatement->fetchAll() as $image) {
			$imagesByContent[(int) $image['content_id']][] = (string) $image['image_path'];
		}
	}
	foreach ($items as &$item) {
		$item['images'] = $imagesByContent[(int) $item['id']] ?? [];
	}
	unset($item);

	echo json_encode(
		['items' => $items],
		JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
	);
} catch (Throwable $exception) {
	error_log('Public news query failed: ' . $exception->getMessage());
	http_response_code(500);
	echo json_encode(['items' => [], 'error' => 'ไม่สามารถโหลดข่าวสารได้'], JSON_UNESCAPED_UNICODE);
}