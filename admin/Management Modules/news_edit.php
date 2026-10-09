<?php
declare(strict_types=1);
require_once __DIR__ . '/../check_session.php';
require_once __DIR__ . '/../admin_nav.php';
require_once __DIR__ . '/../image_uploads.php';

$id = filter_var($_GET['id'] ?? $_POST['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id) { header('Location: news_list.php'); exit; }
$pdo = db();
$statement = $pdo->prepare('SELECT * FROM content WHERE id=:id AND content_type IN ("news", "university", "faculty", "event", "announcement")');
$statement->execute(['id' => $id]);
$item = $statement->fetch();
if (!$item) { http_response_code(404); exit('ไม่พบข่าวสาร'); }
$imageStatement = $pdo->prepare(
    'SELECT id, image_path, display_order FROM content_images WHERE content_id = :content_id ORDER BY display_order ASC, id ASC'
);
$imageStatement->execute(['content_id' => $id]);
$images = $imageStatement->fetchAll();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $title = trim((string) ($_POST['title'] ?? '')); $summary = trim((string) ($_POST['summary'] ?? '')); $body = trim((string) ($_POST['body'] ?? ''));
    $type = in_array($_POST['content_type'] ?? '', ['university', 'faculty', 'event', 'announcement'], true) ? $_POST['content_type'] : 'university'; $status = ($_POST['status'] ?? '') === 'published' ? 'published' : 'draft'; $eventDate = trim((string) ($_POST['event_date'] ?? '')) ?: null;
    $item = array_merge($item, ['title'=>$title, 'summary'=>$summary, 'body'=>$body, 'content_type'=>$type, 'status'=>$status, 'event_date'=>$eventDate]);
    $selectedImageIds = $_POST['remove_images'] ?? [];
    $selectedImageIds = is_array($selectedImageIds)
        ? array_values(array_unique(array_filter(array_map(
            static fn(mixed $imageId): int => filter_var($imageId, FILTER_VALIDATE_INT) ?: 0,
            $selectedImageIds
        ))))
        : [];
    $existingImageIds = array_map(static fn(array $image): int => (int) $image['id'], $images);
    $removeImageIds = array_values(array_intersect($selectedImageIds, $existingImageIds));
    if ($title === '' || $summary === '' || $body === '') {
        $error = 'กรุณากรอกหัวข้อ สรุป และรายละเอียดให้ครบถ้วน';
    } else {
        $newFiles = [];
        try {
            $newFiles = save_uploaded_images(
                $_FILES['image_upload'] ?? [],
                dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'news',
                'img/news',
                'content_'
            );
            $pdo->beginTransaction();
            $statement = $pdo->prepare('UPDATE content SET title=:title, summary=:summary, body=:body, content_type=:type, status=:status, event_date=:event_date WHERE id=:id');
            $statement->execute(['title'=>$title, 'summary'=>$summary, 'body'=>$body, 'type'=>$type, 'status'=>$status, 'event_date'=>$eventDate, 'id'=>$id]);
            if ($removeImageIds) {
                $deleteImage = $pdo->prepare('DELETE FROM content_images WHERE id = :image_id AND content_id = :content_id');
                foreach ($removeImageIds as $imageId) {
                    $deleteImage->execute(['image_id' => $imageId, 'content_id' => $id]);
                }
            }
            if ($newFiles) {
                $imageInsert = $pdo->prepare(
                    'INSERT INTO content_images (content_id, image_path, display_order)
                    VALUES (:content_id, :image_path, :display_order)'
                );
                $nextImageOrder = $images
                    ? max(array_map(static fn(array $image): int => (int) $image['display_order'], $images)) + 1
                    : 0;
                foreach ($newFiles as $index => $newFile) {
                    $imageInsert->execute([
                        'content_id' => $id,
                        'image_path' => $newFile['path'],
                        'display_order' => $nextImageOrder + $index,
                    ]);
                }
            }
            $pdo->commit();
            foreach ($images as $image) {
                if (in_array((int) $image['id'], $removeImageIds, true)) {
                    remove_managed_image_file((string) $image['image_path'], 'img/news', 'content_');
                }
            }
            header('Location: news_list.php');
            exit;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            foreach ($newFiles as $newFile) {
                if (is_file($newFile['absolute_path']) && !@unlink($newFile['absolute_path'])) {
                    error_log('Unable to clean up failed content image upload: ' . $newFile['absolute_path']);
                }
            }
            error_log('Content update failed: ' . $exception->getMessage());
            $error = $exception instanceof ImageUploadException
                ? $exception->getMessage()
                : 'บันทึกข้อมูลไม่สำเร็จ กรุณาตรวจสอบข้อมูลแล้วลองใหม่';
        }
    }
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>แก้ไขข่าวสาร</title>
    <style><?php require __DIR__ . '/news_form_style.php'; ?></style>
    <link rel="stylesheet" href="../admin.css">
</head>
<body>
    <aside class="admin-header">
        <a class="admin-brand" href="../dashboard.php"><img src="../../img/images.jpg" alt="">ระบบจัดการเว็บไซต์</a>
        <?php render_admin_nav('news', '../'); ?>
        <div class="admin-user"><small>ผู้ดูแลระบบ</small><strong><?= escape((string) $_SESSION['admin_username']) ?></strong><a href="../logout.php">ออกจากระบบ</a></div>
    </aside>
    <main class="box">
        <h1>แก้ไขข่าวสาร</h1>
        <?php if ($error): ?><p class="error" role="alert"><?= escape($error) ?></p><?php endif; ?>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= (int) $id ?>">
            <?php require __DIR__ . '/news_form_fields.php'; ?>
            <button type="submit">บันทึกการแก้ไข</button>
            <a class="button secondary" href="news_list.php">ยกเลิก</a>
        </form>
    </main>
</body>
</html>