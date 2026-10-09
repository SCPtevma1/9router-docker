<?php
declare(strict_types=1);

require_once __DIR__ . '/../check_session.php';
require_once __DIR__ . '/../admin_nav.php';

$pdo = db();
$items = $pdo->query(
	'SELECT * FROM content
	WHERE content_type IN ("news", "university", "faculty", "event", "announcement")
	ORDER BY created_at DESC, id DESC'
)->fetchAll();
$typeLabels = [
	'news' => 'ข่าวมหาวิทยาลัย',
	'university' => 'ข่าวมหาวิทยาลัย',
	'faculty' => 'ข่าวคณะ',
	'event' => 'กิจกรรม / ปฏิทินกิจกรรม',
	'announcement' => 'ประกาศ',
];
?>
<!doctype html>
<html lang="th">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>รายการข่าวสารและกิจกรรม</title>
	<style>
		* { box-sizing: border-box; }
		body {
			display: grid;
			grid-template-columns: 250px minmax(0, 1fr);
			min-height: 100vh;
			margin: 0;
			background: #f4f7fa;
			color: #17324d;
			font-family: Arial, sans-serif;
		}
		.wrap { width: min(1100px, calc(100% - 4rem)); margin: 0 auto; }
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
		.top nav { display: grid; gap: .35rem; }
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
		.nav-user-label { color: rgba(255, 255, 255, .65); font-size: .85rem; }
		.nav-user a { margin-top: .35rem; color: #fff; }
		.dashboard-main {
			width: min(1100px, calc(100% - 4rem));
			min-width: 0;
			padding: 2rem 0;
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
		.panel-heading h1 { margin: 0; font-size: 1.5rem; }
		.button,
		button {
			display: inline-block;
			padding: .6rem .8rem;
			border: 0;
			border-radius: 5px;
			background: #1e4a7a;
			color: #fff;
			font: inherit;
			text-decoration: none;
			cursor: pointer;
		}
		.secondary { border: 1px solid #bdc9d6; background: #fff; color: #1e4a7a; }
		.danger { background: #a42121; }
		.item { padding: 1rem 0; border-top: 1px solid #e4eaf0; }
		.item:first-of-type { border-top: 0; }
		.item h2 { margin: .4rem 0; font-size: 1.1rem; }
		.tag { padding: .2rem .45rem; border-radius: 4px; background: #e7eef6; font-size: .8rem; }
		.muted { color: #66788a; }
		.actions { display: flex; align-items: center; gap: .5rem; margin-top: .7rem; }
		.inline-form { display: inline; }
		@media (max-width: 760px) {
			body { grid-template-columns: 1fr; }
			.top { position: static; height: auto; padding: .9rem 0; }
			.top .wrap { width: min(1100px, 92vw); height: auto; gap: .9rem; }
			.top nav { display: flex; flex-wrap: wrap; gap: .25rem; }
			.top nav a { padding: .5rem .65rem; }
			.nav-user {
				width: 100%;
				display: flex;
				align-items: center;
				flex-wrap: wrap;
				gap: .4rem .75rem;
				margin: 0;
				padding-top: .75rem;
			}
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
			<?php render_admin_nav('news', '../'); ?>
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
				<h1>รายการข่าวสารและกิจกรรม</h1>
				<a class="button" href="news_add.php">เพิ่มข่าวสาร</a>
			</div>

			<?php if (!$items): ?>
				<p class="muted">ยังไม่มีข่าวสารหรือกิจกรรม</p>
			<?php endif; ?>

			<?php foreach ($items as $item): ?>
				<article class="item">
					<span class="tag">
						<?= escape($typeLabels[$item['content_type']] ?? 'ข่าวสาร') ?>
						· <?= $item['status'] === 'published' ? 'เผยแพร่แล้ว' : 'ฉบับร่าง' ?>
					</span>
					<h2><?= escape($item['title']) ?></h2>
					<p><?= escape($item['summary']) ?></p>
					<small class="muted">
						สร้างเมื่อ <?= escape($item['created_at']) ?>
						<?= $item['event_date'] ? ' · วันที่กิจกรรม ' . escape($item['event_date']) : '' ?>
					</small>

					<div class="actions">
						<a class="button secondary" href="news_edit.php?id=<?= (int) $item['id'] ?>">
							แก้ไข
						</a>
						<form
							class="inline-form"
							method="post"
							action="news_delete.php"
							onsubmit="return confirm('ยืนยันการลบรายการนี้หรือไม่?')"
						>
							<input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
							<input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
							<button class="danger" type="submit">ลบ</button>
						</form>
					</div>
				</article>
			<?php endforeach; ?>
		</section>
	</main>
</body>
</html>