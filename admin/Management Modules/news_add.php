<?php
declare(strict_types=1);
require_once __DIR__ . '/../check_session.php';
require_once __DIR__ . '/../admin_nav.php';
require_once __DIR__ . '/../image_uploads.php';

$error = '';
$item = [];
$images = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $title = trim((string) ($_POST['title'] ?? ''));
    $summary = trim((string) ($_POST['summary'] ?? ''));
    $body = trim((string) ($_POST['body'] ?? ''));
    $type = in_array($_POST['content_type'] ?? '', ['university', 'faculty', 'event', 'announcement'], true) ? $_POST['content_type'] : 'university';
    $status = ($_POST['status'] ?? '') === 'published' ? 'published' : 'draft';
    $eventDate = trim((string) ($_POST['event_date'] ?? '')) ?: null;
    $item = ['title' => $title, 'summary' => $summary, 'body' => $body, 'content_type' => $type, 'status' => $status, 'event_date' => $eventDate];
    if ($title === '' || $summary === '' || $body === '') {
        $error = 'กรุณากรอกหัวข้อ สรุป และรายละเอียดให้ครบถ้วน';
    } else {
        $pdo = db();
        $newFiles = [];
        try {
            $newFiles = save_uploaded_images(
                $_FILES['image_upload'] ?? [],
                dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'news',
                'img/news',
                'content_'
            );
            $pdo->beginTransaction();
            $statement = $pdo->prepare('INSERT INTO content (title, summary, body, content_type, status, event_date) VALUES (:title,:summary,:body,:type,:status,:event_date)');
            $statement->execute(['title'=>$title, 'summary'=>$summary, 'body'=>$body, 'type'=>$type, 'status'=>$status, 'event_date'=>$eventDate]);
            $contentId = (int) $pdo->lastInsertId();
            if ($newFiles) {
                $imageStatement = $pdo->prepare(
                    'INSERT INTO content_images (content_id, image_path, display_order)
                    VALUES (:content_id, :image_path, :display_order)'
                );
                foreach ($newFiles as $index => $newFile) {
                    $imageStatement->execute([
                        'content_id' => $contentId,
                        'image_path' => $newFile['path'],
                        'display_order' => $index,
                    ]);
                }
            }
            $pdo->commit();
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
            error_log('Content insert failed: ' . $exception->getMessage());
            $error = $exception instanceof ImageUploadException
                ? $exception->getMessage()
                : 'บันทึกข้อมูลไม่สำเร็จ กรุณาตรวจสอบฐานข้อมูลแล้วลองอีกครั้ง';
        }
    }
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>เพิ่มข่าวสาร</title>
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
        <h1>เพิ่มข่าวสารใหม่</h1>
        <?php if ($error): ?><p class="error" role="alert"><?= escape($error) ?></p><?php endif; ?>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
            <?php require __DIR__ . '/news_form_fields.php'; ?>
            <button type="submit">บันทึก</button>
            <a class="button secondary" href="news_list.php">ยกเลิก</a>
        </form>
    </main>
</body>
</html>