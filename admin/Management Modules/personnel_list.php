<?php
declare(strict_types=1);
require_once __DIR__ . '/../check_session.php';
header('Location: personnel_directory.php', true, 302);
exit;

$pdo = db();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	verify_csrf();
	$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

	if (($_POST['action'] ?? '') !== 'delete' || !$id) {
		$error = 'คำขอลบบุคลากรไม่ถูกต้อง';
	} else {
		try {
			$statement = $pdo->prepare('SELECT image FROM personnel WHERE id = :id');
			$statement->execute(['id' => $id]);
			$person = $statement->fetch();

			if (!$person) {
				$message = 'ไม่พบบุคลากรที่ต้องการลบ';
			} else {
				$statement = $pdo->prepare('DELETE FROM personnel WHERE id = :id');
				$statement->execute(['id' => $id]);

				if ($statement->rowCount() > 0) {
					$imageFileName = basename((string) ($person['image'] ?? ''));
					if (preg_match('/\A[a-f0-9]{32}\.(?:jpg|png|gif|webp)\z/i', $imageFileName)) {
						$imagePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'img'
							. DIRECTORY_SEPARATOR . 'personnel' . DIRECTORY_SEPARATOR . $imageFileName;
						if (is_file($imagePath) && !@unlink($imagePath)) {
							error_log('Unable to remove personnel image: ' . $imagePath);
						}
					}

					$message = 'ลบบุคลากรเรียบร้อยแล้ว';
				} else {
					$message = 'ไม่พบบุคลากรที่ต้องการลบ';
				}
			}
		} catch (Throwable $exception) {
			error_log('Personnel deletion failed: ' . $exception->getMessage());
			$error = 'ลบบุคลากรไม่สำเร็จ กรุณาลองอีกครั้ง';
		}
	}
}

$items = $pdo->query(
		'SELECT id, prefix, fullname, position, department, email, phone, image, status
		FROM personnel
		ORDER BY department ASC, fullname ASC'
)->fetchAll();
?>
<!doctype html>
<html lang="th">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>รายชื่อบุคลากร</title>
	<style>
		* {
			box-sizing: border-box;
		}

		body {
			display: grid;
			grid-template-columns: 250px minmax(0, 1fr);
			min-height: 100vh;
			margin: 0;
			background: #f4f7fa;
			color: #17324d;
			font-family: Arial, sans-serif;
		}

		.wrap {
			width: min(1100px, calc(100% - 4rem));
			margin: 0 auto;
		}

		.top {
			position: sticky;
			top: 0;
			height: 100vh;
			padding: 1.5rem 0;
			background: #17324d;
			color: #fff;
		}

		.top .wrap {
			display: flex;
			width: 100%;
			height: 100%;
			padding: 0 1.25rem;
			flex-direction: column;
			align-items: stretch;
			gap: 2rem;
		}

		.brand {
			display: flex;
			align-items: center;
			gap: .75rem;
			color: #fff;
			font-weight: 700;
			line-height: 1.35;
			text-decoration: none;
		}

		.brand-mark {
			width: 38px;
			height: 38px;
			display: grid;
			flex: 0 0 auto;
			place-items: center;
			border: 1px solid rgba(255, 255, 255, .45);
			border-left: 3px solid #c8a35c;
			font-family: Georgia, serif;
			font-size: 1.35rem;
		}

		.top nav {
			display: grid;
			gap: .35rem;
		}

		.top nav a {
			display: block;
			padding: .65rem .75rem;
			border-radius: 5px;
			color: rgba(255, 255, 255, .82);
			text-decoration: none;
		}

		.top nav a:hover,
		.top nav a:focus-visible,
		.top nav a[aria-current="page"] {
			background: rgba(255, 255, 255, .12);
			color: #fff;
		}

		.top nav a:focus-visible,
		.nav-user a:focus-visible,
		.brand:focus-visible {
			outline: 2px solid #c8a35c;
			outline-offset: 2px;
		}

		.nav-user {
			display: grid;
			gap: .25rem;
			margin-top: auto;
			padding-top: 1rem;
			border-top: 1px solid rgba(255, 255, 255, .2);
			overflow-wrap: anywhere;
		}

		.nav-user-label {
			color: rgba(255, 255, 255, .65);
			font-size: .85rem;
		}

		.nav-user a {
			margin-top: .35rem;
			color: #fff;
		}

		.dashboard-main {
			width: min(1100px, calc(100% - 4rem));
			min-width: 0;
			padding: 2rem 0;
		}

		.person {
			display: flex;
			align-items: flex-start;
			gap: 1rem;
			padding: 1rem 0;
			border-top: 1px solid #e4eaf0;
		}

		.panel {
			margin: 1.5rem 0;
			padding: 1.2rem;
			border: 1px solid #d7e0ea;
			border-radius: 8px;
			background: #fff;
		}

		.panel-heading {
			display: flex;
			align-items: center;
			justify-content: space-between;
			flex-wrap: wrap;
			gap: 1rem;
		}

		.panel-heading h1 {
			margin: 0;
			font-size: 1.5rem;
		}

		.button {
			display: inline-block;
			padding: .6rem .8rem;
			border-radius: 5px;
			background: #1e4a7a;
			color: #fff;
			text-decoration: none;
		}

		.danger {
			background: #a42121;
		}

		.person-actions {
			display: flex;
			flex-wrap: wrap;
			align-items: center;
			gap: .5rem;
			margin-top: .65rem;
		}

		.person-actions form {
			margin: 0;
		}

		button.button {
			border: 0;
			cursor: pointer;
			font: inherit;
		}

		.notice,
		.error {
			padding: .7rem;
			border-radius: 5px;
		}

		.notice {
			background: #eaf7ed;
			color: #17652a;
		}

		.error {
			background: #fff0f0;
			color: #a42121;
		}

		.person:first-of-type {
			border-top: 0;
		}

		.person-photo,
		.person-placeholder {
			width: 72px;
			height: 72px;
			flex: 0 0 72px;
			border-radius: 6px;
			object-fit: cover;
		}

		.person-placeholder {
			display: grid;
			place-items: center;
			background: #e7eef6;
			color: #1e4a7a;
			font-size: 1.4rem;
			font-weight: 700;
		}

		.person-info {
			min-width: 0;
		}

		.person-info h2 {
			margin: .25rem 0;
			font-size: 1.1rem;
		}

		.person-info p {
			margin: .3rem 0;
			overflow-wrap: anywhere;
		}

		.tag {
			display: inline-block;
			padding: .2rem .45rem;
			border-radius: 4px;
			background: #e7eef6;
			font-size: .8rem;
		}

		.tag.inactive {
			background: #f3e7e7;
			color: #8c3030;
		}

		.muted {
			color: #66788a;
		}

		.empty {
			padding: 1.5rem 0;
			color: #66788a;
		}

		@media (max-width: 760px) {
			body {
				grid-template-columns: 1fr;
			}

			.top {
				position: static;
				height: auto;
				padding: .9rem 0;
			}

			.top .wrap {
				width: min(1100px, 92vw);
				height: auto;
				gap: .9rem;
			}

			.top nav {
				display: flex;
				flex-wrap: wrap;
				gap: .25rem;
			}

			.top nav a {
				padding: .5rem .65rem;
			}

			.nav-user {
				width: 100%;
				display: flex;
				align-items: center;
				flex-wrap: wrap;
				gap: .4rem .75rem;
				margin: 0;
				padding-top: .75rem;
			}

			.nav-user a {
				margin: 0 0 0 auto;
			}

			.dashboard-main {
				width: min(1100px, 92vw);
				padding: 1.4rem 0;
			}
		}

		@media (max-width: 600px) {
			.panel {
				padding: 1rem;
			}

			.person-photo,
			.person-placeholder {
				width: 56px;
				height: 56px;
				flex-basis: 56px;
			}
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
			<nav aria-label="เมนูผู้ดูแล">
				<a href="../dashboard.php">ภาพรวม</a>
				<a href="news_list.php">ข่าวสาร</a>
				<a href="personnel_list.php" aria-current="page">บุคลากร</a>
				<a href="courses_list.php">หลักสูตร</a>
			</nav>
			<div class="nav-user">
				<span class="nav-user-label">ผู้ดูแลระบบ</span>
				<strong><?= escape((string) $_SESSION['admin_username']) ?></strong>
				<a href="../logout.php">ออกจากระบบ</a>
			</div>
		</div>
	</header>

	<main class="wrap dashboard-main">
		<section class="panel">
			<div class="panel-heading">
				<h1>รายชื่ออาจารย์และเจ้าหน้าที่</h1>
				<a class="button" href="personnel_add.php">เพิ่มบุคลากร</a>
			</div>

			<?php if ($message !== ''): ?>
				<p class="notice" role="status"><?= escape($message) ?></p>
			<?php endif; ?>

			<?php if ($error !== ''): ?>
				<p class="error" role="alert"><?= escape($error) ?></p>
			<?php endif; ?>

			<?php if (!$items): ?>
				<p class="empty">ยังไม่มีข้อมูลบุคลากร</p>
			<?php endif; ?>

			<?php foreach ($items as $item): ?>
				<?php
				$fullName = trim((string) ($item['prefix'] ?? '') . ' ' . (string) $item['fullname']);
				$image = trim((string) ($item['image'] ?? ''));
				$isActive = $item['status'] === 'active';
				?>
				<article class="person">
					<?php if ($image !== ''): ?>
						<img class="person-photo" src="<?= escape($image) ?>" alt="รูปของ <?= escape($fullName) ?>" loading="lazy">
					<?php else: ?>
						<span class="person-placeholder" aria-hidden="true">บุ</span>
					<?php endif; ?>

					<div class="person-info">
						<span class="tag <?= $isActive ? '' : 'inactive' ?>">
							<?= $isActive ? 'ปฏิบัติงาน' : 'ไม่ปฏิบัติงาน' ?>
						</span>
						<h2><?= escape($fullName) ?></h2>

						<?php if ($item['position'] || $item['department']): ?>
							<p>
								<?= escape((string) $item['position']) ?>
								<?= $item['position'] && $item['department'] ? ' · ' : '' ?>
								<?= escape((string) $item['department']) ?>
							</p>
						<?php endif; ?>

						<?php if ($item['email'] || $item['phone']): ?>
							<p class="muted">
								<?php if ($item['email']): ?>
									<a href="mailto:<?= escape($item['email']) ?>"><?= escape($item['email']) ?></a>
								<?php endif; ?>
								<?= $item['email'] && $item['phone'] ? ' · ' : '' ?>
								<?php if ($item['phone']): ?>
									<a href="tel:<?= escape($item['phone']) ?>"><?= escape($item['phone']) ?></a>
								<?php endif; ?>
							</p>
						<?php endif; ?>

						<div class="person-actions">
							<form method="post" onsubmit="return confirm('ยืนยันลบข้อมูลบุคลากรนี้หรือไม่?')">
								<input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
								<input type="hidden" name="action" value="delete">
								<input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
								<button class="button danger" type="submit">ลบ</button>
							</form>
						</div>
					</div>
				</article>
			<?php endforeach; ?>
		</section>
	</main>
</body>
</html>