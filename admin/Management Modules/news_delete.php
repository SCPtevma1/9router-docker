<?php
declare(strict_types=1);
require_once __DIR__ . '/../check_session.php';
require_once __DIR__ . '/../image_uploads.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: news_list.php'); exit; }
verify_csrf();
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
if ($id) {
	$pdo = db();
	$imageStatement = $pdo->prepare('SELECT image_path FROM content_images WHERE content_id = :id');
	$imageStatement->execute(['id' => $id]);
	$images = $imageStatement->fetchAll();
	$pdo->beginTransaction();
	$statement = $pdo->prepare('DELETE FROM content WHERE id=:id AND content_type IN ("news", "university", "faculty", "event", "announcement")');
	$statement->execute(['id'=>$id]);
	if ($statement->rowCount() === 1) {
		$pdo->prepare('DELETE FROM content_images WHERE content_id = :id')->execute(['id' => $id]);
	}
	$pdo->commit();
	if ($statement->rowCount() === 1) {
		foreach ($images as $image) {
			remove_managed_image_file((string) $image['image_path'], 'img/news', 'content_');
		}
	}
}
header('Location: news_list.php');
exit;