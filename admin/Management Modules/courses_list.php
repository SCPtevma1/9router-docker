<?php
declare(strict_types=1);
require_once __DIR__ . '/../check_session.php';
require_once __DIR__ . '/../admin_nav.php';

$pdo = db();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	verify_csrf();
	$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

	if (($_POST['action'] ?? '') !== 'delete' || !$id) {
		$error = 'คำขอลบหลักสูตรไม่ถูกต้อง';
	} else {
		try {
			$statement = $pdo->prepare('DELETE FROM courses WHERE id = :id');
			$statement->execute(['id' => $id]);
			$message = $statement->rowCount() > 0
				? 'ลบหลักสูตรเรียบร้อยแล้ว'
				: 'ไม่พบหลักสูตรที่ต้องการลบ';
		} catch (Throwable $exception) {
			error_log('Course deletion failed: ' . $exception->getMessage());
			$error = 'ลบหลักสูตรไม่สำเร็จ กรุณาลองอีกครั้ง';
		}
	}
}

$items = $pdo->query(
	' SELECT id, course_code, course_name, faculty, degree, department, description, credits, status, created_at
	FROM courses
	ORDER BY department ASC, course_name ASC'
)->fetchAll();
?>
<!doctype html>
<html lang="th">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>รายการหลักสูตร</title>
	<style>
		*, *::before, *::after { box-sizing: border-box; }
		body { min-height: 100vh; margin: 0; display: grid; grid-template-columns: 250px minmax(0, 1fr); background: #f4f7fa; color: #17324d; font-family: Arial, sans-serif; }
		.wrap { width: min(1100px, calc(100% - 4rem)); margin: 0 auto; }
		.top { position: sticky; top: 0; height: 100vh; padding: 1.5rem 0; background: #17324d; color: #fff; }
		.top .wrap { display: flex; width: 100%; height: 100%; padding: 0 1.25rem; flex-direction: column; align-items: stretch; gap: 2rem; }
		.brand { display: flex; align-items: center; gap: .75rem; color: #fff; font-weight: 700; line-height: 1.35; text-decoration: none; }
		.brand-mark { width: 38px; height: 38px; display: grid; flex: 0 0 auto; place-items: center; border: 1px solid rgba(255, 255, 255, .45); border-left: 3px solid #c8a35c; font-family: Georgia, serif; font-size: 1.35rem; }
		.top nav { display: grid; gap: .35rem; }
		.top nav a { display: block; padding: .65rem .75rem; border-radius: 5px; color: rgba(255, 255, 255, .82); text-decoration: none; }
		.top nav a:hover, .top nav a:focus-visible, .top nav a[aria-current="page"] { background: rgba(255, 255, 255, .12); color: #fff; }
		.top nav a:focus-visible { outline: 2px solid #c8a35c; outline-offset: 2px; }
		.nav-user { display: grid; gap: .25rem; margin-top: auto; padding-top: 1rem; border-top: 1px solid rgba(255, 255, 255, .2); overflow-wrap: anywhere; }
		.nav-user-label { color: rgba(255, 255, 255, .65); font-size: .85rem; }
		.nav-user a { margin-top: .35rem; color: #fff; }
		.dashboard-main { width: min(1100px, calc(100% - 4rem)); min-width: 0; margin: 0 auto; padding: 2rem 0; }
		.panel { margin: 1.5rem 0; padding: 1.2rem; border: 1px solid #d7e0ea; border-radius: 8px; background: #fff; }
		.panel-heading { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; }
		.panel-heading h1 { margin: 0; font-size: 1.5rem; }
		.course { padding: 1rem 0; border-top: 1px solid #e4eaf0; }
		.course:first-of-type { border-top: 0; }
		.course h2 { margin: .35rem 0; font-size: 1.1rem; }
		.tag { display: inline-block; padding: .2rem .45rem; border-radius: 4px; background: #e7eef6; font-size: .8rem; }
		.button { display: inline-block; padding: .6rem .8rem; border-radius: 5px; background: #1e4a7a; color: #fff; text-decoration: none; }
		.secondary { border: 1px solid #bdc9d6; background: #fff; color: #1e4a7a; }
		.danger { background: #a42121; }
		.actions { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }
		.inline-form { margin: 0; }
		button.button { border: 0; cursor: pointer; font: inherit; }
		.muted { color: #66788a; }
		.notice, .error { padding: .7rem; border-radius: 5px; }
		.notice { background: #eaf7ed; color: #17652a; }
		.error { background: #fff0f0; color: #a42121; }
		@media (max-width: 760px) {
			body { grid-template-columns: 1fr; }
			.top { position: static; height: auto; padding: .9rem 0; }
			.top .wrap { width: min(1100px, 92vw); height: auto; gap: .9rem; }
			.top nav { display: flex; flex-wrap: wrap; gap: .25rem; }
			.top nav a { padding: .5rem .65rem; }
			.nav-user { width: 100%; display: flex; align-items: center; flex-wrap: wrap; gap: .4rem .75rem; margin: 0; padding-top: .75rem; }
			.nav-user a { margin: 0 0 0 auto; }
			.dashboard-main { width: min(1100px, 92vw); padding: 1.4rem 0; }
		}
	</style>
	<link rel="stylesheet" href="../admin.css">
</head>
<body>
	<header class="top">
		<div class="wrap">
			<a class="brand" href="../../index.html">
				<span class="brand-mark" aria-hidden="true"><img src="../../img/images.jpg" alt=""></span>
				<span>ระบบจัดการเว็บไซต์</span>
			</a>
			<?php render_admin_nav('courses', '../'); ?>
			<div class="nav-user">
				<span class="nav-user-label">ผู้ดูแลระบบ</span>
				<strong><?= escape((string) $_SESSION['admin_username']) ?></strong>
				<a href="../logout.php">ออกจากระบบ</a>
			</div>
		</div>
	</header>

	<main class="dashboard-main">
		<section class="panel">
			<div class="panel-heading">
				<h1>รายการหลักสูตร / คณะ / สาขา</h1>
				<a class="button" href="courses_add.php">เพิ่มหลักสูตร</a>
			</div>

			<?php if ($message !== ''): ?>
				<p class="notice" role="status"><?= escape($message) ?></p>
			<?php endif; ?>

			<?php if ($error !== ''): ?>
				<p class="error" role="alert"><?= escape($error) ?></p>
			<?php endif; ?>

			<?php if (!$items): ?>
				<p class="muted">ยังไม่มีข้อมูลหลักสูตร</p>
			<?php endif; ?>

			<?php foreach ($items as $item): ?>
				<?php
				$createdAtTimestamp = strtotime((string) ($item['created_at'] ?? ''));
				$createdAtText = $createdAtTimestamp === false
					? 'ไม่ระบุ'
					: date('d/m/', $createdAtTimestamp)
						. ((int) date('Y', $createdAtTimestamp) + 543)
						. ' ' . date('H:i', $createdAtTimestamp) . ' น.';
				?>
				<article class="course">
					<span class="tag">
						<?= $item['status'] === 'active' ? 'เปิดสอน' : 'ปิดการใช้งาน' ?>
						<?= $item['degree'] ? ' · ' . escape($item['degree']) : '' ?>
					</span>
					<h2><?= escape($item['course_name']) ?></h2>

					<?php if ($item['faculty']): ?>
						<p><strong>คณะ / วิทยาลัย:</strong> <?= escape($item['faculty']) ?></p>
					<?php endif; ?>

					<?php if ($item['course_code']): ?>
						<p class="muted">รหัสหลักสูตร: <?= escape($item['course_code']) ?></p>
					<?php endif; ?>

					<?php if ($item['department']): ?>
						<p><strong>สาขา:</strong> <?= escape($item['department']) ?></p>
					<?php endif; ?>

					<?php if ($item['credits'] !== null): ?>
						<p class="muted">หน่วยกิต <?= (int) $item['credits'] ?></p>
					<?php endif; ?>

					<?php if ($item['description']): ?>
						<p><?= nl2br(escape($item['description'])) ?></p>
					<?php endif; ?>

					<p class="muted">สร้างเมื่อ:
						<?php if ($createdAtTimestamp !== false): ?>
							<time datetime="<?= escape(date(DATE_ATOM, $createdAtTimestamp)) ?>"><?= escape($createdAtText) ?></time>
						<?php else: ?>
							<?= escape($createdAtText) ?>
						<?php endif; ?>
					</p>

					<div class="actions">
						<a class="button" href="courses_edit.php?id=<?= (int) $item['id'] ?>">แก้ไขข้อมูล</a>
						<form class="inline-form" method="post" onsubmit="return confirm('ยืนยันลบหลักสูตรนี้หรือไม่?')">
							<input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
							<input type="hidden" name="action" value="delete">
							<input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
							<button class="button danger" type="submit">ลบ</button>
						</form>
					</div>
				</article>
			<?php endforeach; ?>
		</section>
	</main>
</body>
</html>