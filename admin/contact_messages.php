<?php
declare(strict_types=1);

require_once __DIR__ . '/check_session.php';
require_once __DIR__ . '/admin_nav.php';

$pdo = db();
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS contact_messages (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(150) NOT NULL,
        email VARCHAR(150) NOT NULL,
        phone VARCHAR(50) DEFAULT NULL,
        topic VARCHAR(80) NOT NULL,
        message TEXT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

$selectedTopic = trim((string) ($_GET['topic'] ?? ''));
$topicList = $pdo->query(
    'SELECT topic, COUNT(*) AS total FROM contact_messages GROUP BY topic ORDER BY total DESC, topic ASC'
)->fetchAll();

$query = 'SELECT id, name, email, phone, topic, message, created_at FROM contact_messages';
$params = [];
if ($selectedTopic !== '') {
    $query .= ' WHERE topic = :topic';
    $params['topic'] = $selectedTopic;
}
$query .= ' ORDER BY created_at DESC, id DESC';

$statement = $pdo->prepare($query);
$statement->execute($params);
$messages = $statement->fetchAll();
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ข้อความติดต่อ</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            background: #f4f7fa;
            color: #17324d;
            font-family: Arial, sans-serif;
        }
        * { box-sizing: border-box; }
        .layout {
            display: grid;
            grid-template-columns: 250px minmax(0, 1fr);
            min-height: 100vh;
        }
        .sidebar {
            background: #17324d;
            color: #fff;
            padding: 24px 18px;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 28px;
            font-weight: 700;
        }
        .brand-mark {
            width: 38px;
            height: 38px;
            display: grid;
            place-items: center;
            border-radius: 10px;
            background: #fff;
            color: #17324d;
        }
        .sidebar nav {
            display: grid;
            gap: 8px;
        }
        .sidebar nav a {
            display: block;
            padding: 10px 12px;
            border-radius: 8px;
            color: #dfeaf5;
            text-decoration: none;
        }
        .sidebar nav a[aria-current="page"] {
            background: rgba(255,255,255,.12);
            color: #fff;
            font-weight: 700;
        }
        .content {
            padding: 32px;
        }
        .card {
            background: #fff;
            border: 1px solid #dfe7f1;
            border-radius: 14px;
            box-shadow: 0 8px 24px rgba(16, 24, 40, .04);
            padding: 24px;
        }
        .head-row {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        h1 {
            margin: 0;
            font-size: 2rem;
        }
        .stats {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .stat {
            background: #eef6ff;
            border: 1px solid #dfe8f8;
            border-radius: 10px;
            padding: 10px 14px;
            min-width: 120px;
        }
        .stat strong {
            display: block;
            font-size: 1.2rem;
        }
        .filters {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .filters a {
            display: inline-block;
            background: #f3f6f9;
            border: 1px solid #dfe7f1;
            color: #17324d;
            border-radius: 999px;
            padding: 8px 12px;
            text-decoration: none;
            font-size: 0.9rem;
        }
        .filters a.active {
            background: #17324d;
            color: #fff;
            border-color: #17324d;
        }
        .message-list {
            display: grid;
            gap: 18px;
        }
        .message-item {
            border: 1px solid #e0e7ef;
            border-radius: 12px;
            padding: 18px 18px 12px;
            background: #fbfdff;
        }
        .message-top {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }
        .topic {
            display: inline-block;
            background: #e7f4ff;
            color: #0b5ea8;
            border-radius: 999px;
            padding: 6px 10px;
            font-size: 0.8rem;
            font-weight: 700;
        }
        .meta {
            color: #55657d;
            font-size: 0.88rem;
        }
        .message-item p {
            margin: 0 0 10px;
            line-height: 1.7;
            white-space: pre-wrap;
        }
        .label {
            display: inline-block;
            min-width: 72px;
            color: #3f5470;
            font-weight: 700;
        }
        .empty {
            padding: 20px;
            border: 1px dashed #d5dde8;
            border-radius: 12px;
            color: #55657d;
            background: #fff;
        }
        @media (max-width: 900px) {
            .layout {
                grid-template-columns: 1fr;
            }
            .sidebar {
                padding-bottom: 10px;
            }
            .content {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-mark">U</div>
            <span>ระบบจัดการ</span>
        </div>
        <?php render_admin_nav('contact', './'); ?>
    </aside>
    
    <main class="content">
        <div class="card">
            <div class="head-row">
                <h1>ข้อความติดต่อ</h1>
            </div>
            <div class="stats">
                <div class="stat"><strong><?= count($messages) ?></strong><span>ทั้งหมด</span></div>
                <?php foreach ($topicList as $topicRow): ?>
                    <div class="stat"><strong><?= (int) $topicRow['total'] ?></strong><span><?= escape($topicRow['topic']) ?></span></div>
                <?php endforeach; ?>
            </div>

            <div class="filters">
                <a href="contact_messages.php" class="<?= $selectedTopic === '' ? 'active' : '' ?>">ทั้งหมด</a>
                <?php foreach ($topicList as $topicRow): ?>
                    <a href="contact_messages.php?topic=<?= rawurlencode((string) $topicRow['topic']) ?>" class="<?= $selectedTopic === (string) $topicRow['topic'] ? 'active' : '' ?>"><?= escape($topicRow['topic']) ?></a>
                <?php endforeach; ?>
            </div>

            <?php if ($messages === []): ?>
                <div class="empty">ยังไม่มีข้อความติดต่อในหมวดนี้</div>
            <?php else: ?>
                <div class="message-list">
                    <?php foreach ($messages as $message): ?>
                        <article class="message-item">
                            <div class="message-top">
                                <div>
                                    <strong><?= escape((string) $message['name']) ?></strong>
                                    <div class="meta"><?= escape((string) $message['email']) ?><?php if ((string) $message['phone'] !== ''): ?> · <?= escape((string) $message['phone']) ?><?php endif; ?></div>
                                </div>
                                <span class="topic"><?= escape((string) $message['topic']) ?></span>
                            </div>
                            <div class="meta" style="margin-bottom: 10px;">วันที่: <?= escape((string) $message['created_at']) ?></div>
                            <p><span class="label">ข้อความ:</span><br><?= nl2br(escape((string) $message['message'])) ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>
