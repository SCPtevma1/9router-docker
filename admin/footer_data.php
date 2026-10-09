<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/footer_common.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
    $pdo = db();
    ensure_footer_schema($pdo);
    echo json_encode(
        footer_public_data($pdo),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
} catch (Throwable $exception) {
    error_log('Public footer data failed: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'โหลด Footer ไม่สำเร็จ'], JSON_UNESCAPED_UNICODE);
}
