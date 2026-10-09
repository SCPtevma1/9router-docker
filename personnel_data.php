<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    require_once __DIR__ . '/admin/db.php';

    $pdo = db();
    $items = $pdo->query(
        "SELECT id, prefix, fullname, position, department, image
        FROM personnel_profiles
        WHERE status = 'active'
        ORDER BY display_order ASC, fullname ASC"
    )->fetchAll();
    $imageStatement = $pdo->query(
        "SELECT profile.personnel_profile_id, profile.image_path
        FROM personnel_profile_images AS profile
        INNER JOIN personnel_profiles AS personnel ON personnel.id = profile.personnel_profile_id
        WHERE personnel.status = 'active'
        ORDER BY profile.display_order ASC, profile.id ASC"
    );
    $imagesByPersonnel = [];
    foreach ($imageStatement->fetchAll() as $image) {
        $imagesByPersonnel[(int) $image['personnel_profile_id']][] = (string) $image['image_path'];
    }

    foreach ($items as &$item) {
        $images = [];
        if ((string) $item['image'] !== '') {
            $images[] = (string) $item['image'];
        }
        foreach ($imagesByPersonnel[(int) $item['id']] ?? [] as $imagePath) {
            if (!in_array($imagePath, $images, true)) {
                $images[] = $imagePath;
            }
        }
        $item['images'] = $images;
        unset($item['id']);
    }
    unset($item);

    echo json_encode(
        ['items' => $items],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
} catch (Throwable $exception) {
    error_log('Public personnel query failed: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['items' => [], 'error' => 'ไม่สามารถโหลดข้อมูลบุคลากรได้'], JSON_UNESCAPED_UNICODE);
}