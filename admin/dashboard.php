<?php
declare(strict_types=1);

require_once __DIR__ . '/check_session.php';
require_once __DIR__ . '/admin_nav.php';

$dashboardError = '';
$counts = ['content' => 0, 'published' => 0, 'draft' => 0, 'courses' => 0, 'staff_profiles' => 0, 'personnel' => 0, 'students' => 0, 'alumni' => 0];
$recentContent = [];
$recentMembers = [];
$chartSources = [
    'content' => ['table' => 'content', 'label' => 'ข่าวสารและกิจกรรม'],
    'courses' => ['table' => 'courses', 'label' => 'หลักสูตร'],
    'staff_profiles' => ['table' => 'personnel_profiles', 'label' => 'บุคลากรบนเว็บไซต์'],
    'personnel' => ['table' => 'personnel', 'label' => 'บัญชีบุคลากร'],
    'students' => ['table' => 'students', 'label' => 'นักศึกษา'],
    'alumni' => ['table' => 'alumni', 'label' => 'ศิษย์เก่า'],
];
$currentYear = (int) date('Y');
$requestedYear = filter_var($_GET['year'] ?? $currentYear, FILTER_VALIDATE_INT);
$selectedYear = $requestedYear !== false && $requestedYear >= 2000 && $requestedYear <= $currentYear
    ? $requestedYear
    : $currentYear;
$availableYears = [$currentYear, $selectedYear];
$monthlyChartData = array_fill_keys(array_keys($chartSources), array_fill(0, 12, 0));
$dailyChartData = array_fill_keys(
    array_keys($chartSources),
    array_map(static fn(int $_month): array => array_fill(1, 31, 0), range(1, 12))
);
$chartTotal = 0;
$typeLabels = [
    'news' => 'ข่าวมหาวิทยาลัย',
    'university' => 'ข่าวมหาวิทยาลัย',
    'faculty' => 'ข่าวคณะ',
    'event' => 'กิจกรรม / ปฏิทินกิจกรรม',
    'announcement' => 'ประกาศ',
];

try {
    $pdo = db();
    $contentCounts = $pdo->query(
        "SELECT COUNT(*) AS total,
            COALESCE(SUM(status = 'published'), 0) AS published,
            COALESCE(SUM(status = 'draft'), 0) AS draft
        FROM content"
    )->fetch();
    $counts['content'] = (int) $contentCounts['total'];
    $counts['published'] = (int) $contentCounts['published'];
    $counts['draft'] = (int) $contentCounts['draft'];
    foreach (['courses', 'personnel', 'students', 'alumni'] as $table) {
        $counts[$table] = (int) $pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
    }
    $counts['staff_profiles'] = (int) $pdo->query('SELECT COUNT(*) FROM personnel_profiles')->fetchColumn();
    $recentContent = $pdo->query(
        'SELECT id, title, content_type, status, created_at
        FROM content
        ORDER BY created_at DESC, id DESC
        LIMIT 5'
    )->fetchAll();
    $recentMembers = $pdo->query(
        "SELECT 'นักศึกษา' AS member_type, student_id AS member_id,
            CONCAT_WS(' ', NULLIF(prefix, ''), firstname, lastname) AS full_name, created_at
        FROM students
        UNION ALL
        SELECT 'บุคลากร', personnel_id, CONCAT_WS(' ', NULLIF(prefix, ''), firstname, lastname), created_at
        FROM personnel
        UNION ALL
        SELECT 'ศิษย์เก่า', alumni_id, CONCAT_WS(' ', NULLIF(prefix, ''), firstname, lastname), created_at
        FROM alumni
        ORDER BY created_at DESC
        LIMIT 6"
    )->fetchAll();
    foreach ($chartSources as $key => $source) {
        $yearStatement = $pdo->query(
            'SELECT YEAR(created_at) AS record_year
            FROM `' . $source['table'] . '`
            WHERE created_at IS NOT NULL
            GROUP BY YEAR(created_at)'
        );
        foreach ($yearStatement->fetchAll(PDO::FETCH_COLUMN) as $recordYear) {
            $availableYears[] = (int) $recordYear;
        }

        $monthStatement = $pdo->prepare(
            'SELECT MONTH(created_at) AS record_month, COUNT(*) AS total
            FROM `' . $source['table'] . '`
            WHERE created_at >= :start_date AND created_at < :end_date
            GROUP BY MONTH(created_at)'
        );
        $monthStatement->execute([
            'start_date' => sprintf('%04d-01-01', $selectedYear),
            'end_date' => sprintf('%04d-01-01', $selectedYear + 1),
        ]);
        foreach ($monthStatement->fetchAll() as $monthRow) {
            $monthlyChartData[$key][(int) $monthRow['record_month'] - 1] = (int) $monthRow['total'];
        }
        $dayStatement = $pdo->prepare(
            'SELECT MONTH(created_at) AS record_month, DAY(created_at) AS record_day, COUNT(*) AS total
            FROM `' . $source['table'] . '`
            WHERE created_at >= :start_date AND created_at < :end_date
            GROUP BY MONTH(created_at), DAY(created_at)'
        );
        $dayStatement->execute([
            'start_date' => sprintf('%04d-01-01', $selectedYear),
            'end_date' => sprintf('%04d-01-01', $selectedYear + 1),
        ]);
        foreach ($dayStatement->fetchAll() as $dayRow) {
            $dailyChartData[$key][(int) $dayRow['record_month'] - 1][(int) $dayRow['record_day']] = (int) $dayRow['total'];
        }
    }
    $availableYears = array_values(array_unique($availableYears));
    rsort($availableYears, SORT_NUMERIC);
    $chartTotal = array_sum(array_map('array_sum', $monthlyChartData));
} catch (Throwable $exception) {
    error_log('Admin overview query failed: ' . $exception->getMessage());
    $dashboardError = 'โหลดข้อมูลภาพรวมไม่สำเร็จ กรุณาตรวจสอบการเชื่อมต่อฐานข้อมูล';
}
$shareSummary = [
    'title' => 'สรุปภาพรวมเว็บไซต์',
    'year' => $selectedYear + 543,
    'content' => $counts['content'],
    'courses' => $counts['courses'],
    'staff_profiles' => $counts['staff_profiles'],
    'personnel_accounts' => $counts['personnel'],
    'students' => $counts['students'],
    'alumni' => $counts['alumni'],
    'total' => $counts['personnel'] + $counts['students'] + $counts['alumni'],
    'published' => $counts['published'],
    'draft' => $counts['draft'],
];
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>แดชบอร์ดผู้ดูแล</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            grid-template-columns: 250px minmax(0, 1fr);
            background: #f4f7fa;
            color: #17324d;
            font-family: Arial, sans-serif;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
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
            justify-content: flex-start;
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

        .top nav a:focus-visible {
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
            min-width: 0;
            padding: 2rem 0;
        }

        .page-heading {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 1.25rem;
        }
        .heading-actions { display: grid; justify-items: end; gap: .55rem; }
        .header-actions { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; }
        .header-action { display: inline-flex; align-items: center; gap: .4rem; min-height: 38px; padding: .45rem .7rem; border: 1px solid #bdc9d6; border-radius: 4px; background: #fff; color: #1e4a7a; font: inherit; font-size: .88rem; cursor: pointer; }
        .header-action:hover { border-color: #1e4a7a; background: #f3f7fb; }
        .header-action:focus-visible { outline: 2px solid #6c9dce; outline-offset: 2px; }
        .action-status { min-height: 1.1rem; margin: 0; color: #66788a; font-size: .8rem; text-align: right; }

        h1 { margin: 0; font-size: 1.6rem; }
        h2 { margin: 0; font-size: 1.1rem; }
        .muted { margin: .35rem 0 0; color: #66788a; }
        .connection {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            padding: .4rem .65rem;
            border: 1px solid #b9dec3;
            border-radius: 4px;
            background: #eff9f1;
            color: #17652a;
            font-size: .88rem;
            font-weight: 700;
        }
        .connection::before { width: .5rem; height: .5rem; border-radius: 50%; background: currentColor; content: ""; }
        .stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .8rem;
            margin: 1.25rem 0;
        }
        .stat {
            min-height: 118px;
            padding: 1rem;
            border: 1px solid #d7e0ea;
            border-left: 3px solid #1e4a7a;
            border-radius: 5px;
            background: #fff;
            color: inherit;
            text-decoration: none;
        }
        .stat:hover, .stat:focus-visible { border-color: #9ab3cb; background: #fbfdff; }
        .stat-label { display: block; color: #66788a; font-size: .9rem; }
        .stat-value { display: block; margin-top: .3rem; font-size: 1.8rem; font-weight: 700; line-height: 1.2; }
        .stat-detail { display: block; margin-top: .35rem; color: #66788a; font-size: .82rem; }
        .content-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; }
        .panel { min-width: 0; padding: 1rem 1.1rem; border: 1px solid #d7e0ea; border-radius: 6px; background: #fff; }
        .panel-heading { display: flex; align-items: center; justify-content: space-between; gap: .75rem; margin-bottom: .65rem; }
        .button {
            display: inline-block;
            padding: .5rem .7rem;
            border: 1px solid #1e4a7a;
            border-radius: 4px;
            background: #1e4a7a;
            color: #fff;
            font-size: .9rem;
            text-decoration: none;
        }
        .row { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; padding: .7rem 0; border-top: 1px solid #e4eaf0; }
        .row:first-of-type { border-top: 0; }
        .row-main { min-width: 0; }
        .row-title { display: block; overflow-wrap: anywhere; font-weight: 700; }
        .row-meta { display: block; margin-top: .2rem; color: #66788a; font-size: .82rem; }
        .row-link { flex: 0 0 auto; color: #1e4a7a; font-size: .88rem; }
        .empty, .error { padding: .8rem; border-radius: 4px; background: #f4f7fa; color: #66788a; }
        .error { background: #fff0f0; color: #a42121; }
        .chart-panel { margin: 1rem 0; }
        .chart-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
        .chart-heading p { margin: .3rem 0 0; color: #66788a; font-size: .88rem; }
        .chart-controls { display: flex; align-items: flex-end; gap: .75rem; flex-wrap: wrap; margin: 1rem 0; }
        .chart-controls label { display: grid; gap: .3rem; color: #425a70; font-size: .82rem; font-weight: 700; }
        .chart-controls select { min-width: 150px; min-height: 38px; padding: .45rem .6rem; border: 1px solid #bdc9d6; border-radius: 4px; background: #fff; color: #17324d; font: inherit; }
        .chart-modes { display: flex; gap: .25rem; flex-wrap: wrap; padding: .2rem; border: 1px solid #d7e0ea; border-radius: 4px; background: #f4f7fa; }
        .chart-mode { min-height: 34px; padding: .35rem .6rem; border: 0; border-radius: 3px; background: transparent; color: #425a70; font: inherit; font-size: .85rem; cursor: pointer; }
        .chart-mode[aria-pressed="true"] { background: #1e4a7a; color: #fff; }
        .chart-mode:disabled { color: #8793a0; cursor: not-allowed; opacity: .6; }
        .chart-mode:focus-visible, .chart-controls select:focus-visible { outline: 2px solid #6c9dce; outline-offset: 2px; }
        .chart-unavailable { flex-basis: 100%; color: #66788a; font-size: .82rem; }
        .chart-unavailable summary { width: fit-content; cursor: pointer; }
        .chart-unavailable .chart-modes { margin-top: .5rem; }
        .chart-area { position: relative; height: 340px; min-height: 250px; }
        .chart-status { margin: .65rem 0 0; color: #66788a; font-size: .85rem; }
        .chart-fallback { padding: 2rem 1rem; color: #a42121; text-align: center; }

        :root { --admin-ink: #202a26; --admin-muted: #68736d; --admin-line: #d9dfda; --admin-red: #a5323d; --admin-red-soft: #f7ebeb; --admin-green: #176b50; --admin-paper: #fff; --admin-canvas: #f2f4f1; }
        body { background: var(--admin-canvas); color: var(--admin-ink); font-family: "Segoe UI", Tahoma, sans-serif; }
        .top { background: #1d2925; box-shadow: 3px 0 18px rgba(22, 35, 29, .12); }
        .brand-mark { overflow: hidden; padding: 2px; border-color: rgba(255, 255, 255, .5); border-left-color: #bd4650; background: #fff; }
        .brand-mark img { width: 100%; height: 100%; object-fit: contain; }
        .top nav a[aria-current="page"] { border-left: 3px solid #d29064; background: rgba(255, 255, 255, .12); }
        .dashboard-main { width: min(1360px, calc(100% - 4rem)); margin: 0 auto; }
        .page-heading { align-items: center; padding-bottom: 1rem; border-bottom: 1px solid var(--admin-line); }
        .page-overline { display: block; margin-bottom: .3rem; color: var(--admin-red); font-family: ui-monospace, Consolas, monospace; font-size: .74rem; font-weight: 700; letter-spacing: .04em; }
        h1 { color: var(--admin-ink); }
        h2 { color: var(--admin-ink); }
        .muted, .stat-label, .stat-detail, .row-meta, .chart-heading p, .chart-status { color: var(--admin-muted); }
        .stats { grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .85rem; }
        .stat { border: 1px solid var(--admin-line); border-top: 3px solid var(--admin-green); border-left-width: 1px; border-radius: 6px; background: var(--admin-paper); box-shadow: 0 4px 14px rgba(22, 35, 29, .035); }
        .stat:nth-child(3n + 2) { border-top-color: #a5323d; }
        .stat:nth-child(3n) { border-top-color: #bd8743; }
        .stat:hover, .stat:focus-visible { border-color: #b5c7bc; background: #fff; box-shadow: 0 8px 20px rgba(22, 35, 29, .08); }
        .stat-value { color: var(--admin-ink); font-variant-numeric: tabular-nums; }
        .panel { border-color: var(--admin-line); border-radius: 6px; box-shadow: 0 4px 14px rgba(22, 35, 29, .035); }
        .panel-heading { padding-bottom: .55rem; border-bottom: 1px solid #e8ece8; }
        .button { border-color: var(--admin-red); background: var(--admin-red); }
        .header-action { border-color: var(--admin-line); color: var(--admin-ink); }
        .header-action:hover { border-color: var(--admin-red); background: var(--admin-red-soft); }
        .connection { border-color: #b9d8c8; background: #edf7f1; color: var(--admin-green); }
        .chart-mode[aria-pressed="true"] { background: var(--admin-red); }
        .chart-controls select:focus-visible, .chart-mode:focus-visible, .header-action:focus-visible { outline-color: #bd4650; }

        @media (max-width: 900px) {
            .stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .content-grid { grid-template-columns: 1fr; }
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
                flex-direction: column;
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
        @media (max-width: 480px) { .stats { grid-template-columns: 1fr; } .stat { min-height: 0; } }
        @media (max-width: 600px) { .chart-controls { align-items: stretch; flex-direction: column; } .chart-controls select { width: 100%; } .chart-modes { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); } .chart-mode { padding-inline: .25rem; } .chart-area { height: 290px; } }
        @media print {
            @page { margin: 14mm; }
            body { display: block; min-height: 0; background: #fff; color: #111; }
            .top, .header-actions, .action-status, .chart-controls, .chart-fallback { display: none !important; }
            .wrap, .dashboard-main { width: 100%; margin: 0; padding: 0; }
            .page-heading { margin-bottom: 1rem; }
            .stats { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .4rem; }
            .stat { min-height: 0; padding: .65rem; border-color: #b8c0c8; background: #fff; break-inside: avoid; }
            .stat-value { font-size: 1.35rem; }
            .chart-panel, .panel { border-color: #b8c0c8; box-shadow: none; break-inside: avoid; }
            .chart-area { height: 260px; }
            .content-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .5rem; }
            a { color: inherit; text-decoration: none; }
        }
    </style>
    <link rel="stylesheet" href="admin.css">
</head>
<body>
    <header class="top">
        <div class="wrap">
            <a class="brand" href="../index.html">
                <span class="brand-mark" aria-hidden="true"><img src="../img/images.jpg" alt=""></span>
                <span>ระบบจัดการเว็บไซต์</span>
            </a>
            <?php render_admin_nav('overview'); ?>
            <div class="nav-user">
                <span class="nav-user-label">ผู้ดูแลระบบ</span>
                <strong><?= escape((string) $_SESSION['admin_username']) ?></strong>
                <a href="logout.php">ออกจากระบบ</a>
            </div>
        </div>
    </header>

    <main class="wrap dashboard-main">
        <div class="page-heading">
            <div><span class="page-overline">ADMIN CONTROL / OVERVIEW</span><h1>ภาพรวม</h1><p class="muted">สรุปข้อมูลล่าสุดจากฐานข้อมูลของเว็บไซต์</p></div>
            <div class="heading-actions">
                <?php if ($dashboardError === ''): ?><span class="connection">เชื่อมต่อฐานข้อมูลแล้ว</span><?php endif; ?>
                <div class="header-actions">
                    <button class="header-action" id="print-overview" type="button" title="พิมพ์ภาพรวม"><span aria-hidden="true">🖨</span> พิมพ์ภาพรวม</button>
                    <button class="header-action" id="share-overview" type="button" title="แชร์สรุปภาพรวม"><span aria-hidden="true">↗</span> แชร์ภาพรวม</button>
                </div>
                <p class="action-status" id="overview-action-status" role="status" aria-live="polite"></p>
            </div>
        </div>

        <?php if ($dashboardError !== ''): ?><p class="error" role="alert"><?= escape($dashboardError) ?></p><?php endif; ?>

        <section class="stats" aria-label="สรุปจำนวนข้อมูล">
            <a class="stat" href="Management%20Modules/news_list.php"><span class="stat-label">ข่าวสารและกิจกรรม</span><span class="stat-value"><?= escape((string) $counts['content']) ?></span><span class="stat-detail">เผยแพร่ <?= escape((string) $counts['published']) ?> · ฉบับร่าง <?= escape((string) $counts['draft']) ?></span></a>
            <a class="stat" href="Management%20Modules/courses_list.php"><span class="stat-label">หลักสูตร</span><span class="stat-value"><?= escape((string) $counts['courses']) ?></span><span class="stat-detail">รายการในระบบ</span></a>
            <a class="stat" href="Management%20Modules/personnel_directory.php"><span class="stat-label">บุคลากรบนเว็บไซต์</span><span class="stat-value"><?= escape((string) $counts['staff_profiles']) ?></span><span class="stat-detail">โปรไฟล์ที่แสดง/ซ่อนบนเว็บ</span></a>
            <a class="stat" href="members_list.php?type=personnel"><span class="stat-label">บัญชีบุคลากร</span><span class="stat-value"><?= escape((string) $counts['personnel']) ?></span><span class="stat-detail">บัญชีเข้าสู่ระบบ</span></a>
            <a class="stat" href="members_list.php?type=students"><span class="stat-label">บัญชีนักศึกษา</span><span class="stat-value"><?= escape((string) $counts['students']) ?></span><span class="stat-detail">เปิดรายชื่อนักศึกษา</span></a>
            <a class="stat" href="members_list.php?type=alumni"><span class="stat-label">บัญชีศิษย์เก่า</span><span class="stat-value"><?= escape((string) $counts['alumni']) ?></span><span class="stat-detail">เปิดรายชื่อศิษย์เก่า</span></a>
            <a class="stat" href="members_list.php"><span class="stat-label">สมาชิกทั้งหมด</span><span class="stat-value"><?= escape((string) ($counts['personnel'] + $counts['students'] + $counts['alumni'])) ?></span><span class="stat-detail">รวมสมาชิกทุกประเภท</span></a>
        </section>

        <section class="panel chart-panel" aria-labelledby="records-chart-title">
            <div class="chart-heading">
                <div><h2 id="records-chart-title">สถิติการบันทึกข้อมูลรายปี</h2><p>นับรายการจากวันที่สร้างข้อมูลในระบบ · กราฟหุ้นคำนวณ OHLC จากจำนวนรายการรายวัน ไม่ใช่ราคาหุ้น</p></div>
                <strong><?= escape((string) $chartTotal) ?> รายการในปี <?= escape((string) ($selectedYear + 543)) ?></strong>
            </div>
            <div class="chart-controls">
                <form method="get" aria-label="เลือกปีที่แสดง">
                    <label for="chart-year">ปีที่บันทึก
                        <select id="chart-year" name="year">
                            <?php foreach ($availableYears as $year): ?>
                                <option value="<?= (int) $year ?>" <?= $selectedYear === $year ? 'selected' : '' ?>>พ.ศ. <?= escape((string) ($year + 543)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </form>
                <label for="chart-source">ชุดข้อมูล
                    <select id="chart-source">
                        <option value="all">ข้อมูลทั้งหมด</option>
                        <?php foreach ($chartSources as $key => $source): ?>
                            <option value="<?= escape($key) ?>"><?= escape($source['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div class="chart-modes" role="group" aria-label="ชนิดกราฟ">
                    <button class="chart-mode" type="button" data-chart-type="bar" aria-pressed="true">แท่ง</button>
                    <button class="chart-mode" type="button" data-chart-type="line" aria-pressed="false">เส้นหลายชุด</button>
                    <button class="chart-mode" type="button" data-chart-type="area" aria-pressed="false">พื้นที่</button>
                    <button class="chart-mode" type="button" data-chart-type="pie" aria-pressed="false">วงกลม</button>
                    <button class="chart-mode" type="button" data-chart-type="doughnut" aria-pressed="false">โดนัท</button>
                    <button class="chart-mode" type="button" data-chart-type="polar-area" aria-pressed="false">Polar Area</button>
                    <button class="chart-mode" type="button" data-chart-type="scatter-trend" aria-pressed="false">กระจาย + แนวโน้ม</button>
                    <button class="chart-mode" type="button" data-chart-type="histogram" aria-pressed="false">ฮิสโตแกรมรายเดือน</button>
                    <button class="chart-mode" type="button" data-chart-type="radar" aria-pressed="false">เรดาร์</button>
                    <button class="chart-mode" type="button" data-chart-type="stacked" aria-pressed="false">แท่งซ้อน</button>
                    <button class="chart-mode" type="button" data-chart-type="combo" aria-pressed="false">แท่ง + เส้น</button>
                    <button class="chart-mode" type="button" data-chart-type="candlestick" aria-pressed="false">หุ้น · แท่งเทียน</button>
                    <button class="chart-mode" type="button" data-chart-type="ohlc" aria-pressed="false">หุ้น · OHLC</button>
                </div>
                <details class="chart-unavailable">
                    <summary>กราฟขั้นสูงที่ต้องมีข้อมูลเพิ่ม</summary>
                    <div class="chart-modes" role="group" aria-label="กราฟที่รอชุดข้อมูล">
                        <button class="chart-mode" type="button" disabled title="ต้องมีค่าตัวอย่างดิบแยกตามกลุ่ม">Box Plot</button>
                        <button class="chart-mode" type="button" disabled title="ต้องมีค่าตัวอย่างดิบเพื่อคำนวณ SD/SE">Error Bar</button>
                        <button class="chart-mode" type="button" disabled title="ต้องมีวันเริ่มและวันสิ้นสุดของแต่ละงาน">Gantt</button>
                        <button class="chart-mode" type="button" disabled title="ต้องมีข้อมูลรายรับและรายจ่าย">Waterfall</button>
                        <button class="chart-mode" type="button" disabled title="ต้องมีข้อมูลลำดับชั้นและสัดส่วนของหมวด">Treemap</button>
                        <button class="chart-mode" type="button" disabled title="ต้องมีข้อมูลพื้นที่หรือรหัสจังหวัด">Choropleth</button>
                    </div>
                    <p>ยังไม่เปิดกราฟเหล่านี้ เพราะฐานข้อมูลปัจจุบันไม่มีข้อมูลต้นทางที่จำเป็น</p>
                </details>
            </div>
            <div class="chart-area"><canvas id="records-chart" role="img" aria-label="จำนวนรายการที่บันทึก แยกตามเดือน"></canvas><p class="chart-fallback" id="chart-fallback" hidden>โหลดกราฟไม่สำเร็จ กรุณาตรวจสอบการเชื่อมต่ออินเทอร์เน็ต</p></div>
            <p class="chart-status" id="chart-status" aria-live="polite"></p>
        </section>

        <div class="content-grid">
            <section class="panel">
                <div class="panel-heading"><h2>เนื้อหาล่าสุด</h2><a class="button" href="Management%20Modules/news_list.php">จัดการข่าวสาร</a></div>
                <?php if (!$recentContent): ?><p class="empty">ยังไม่มีข่าวสารหรือกิจกรรม</p><?php endif; ?>
                <?php foreach ($recentContent as $item): ?>
                    <div class="row">
                        <div class="row-main"><span class="row-title"><?= escape($item['title']) ?></span><span class="row-meta"><?= escape($typeLabels[$item['content_type']] ?? 'ข่าวสาร') ?> · <?= $item['status'] === 'published' ? 'เผยแพร่แล้ว' : 'ฉบับร่าง' ?> · <?= escape($item['created_at']) ?></span></div>
                        <a class="row-link" href="Management%20Modules/news_edit.php?id=<?= (int) $item['id'] ?>">แก้ไข</a>
                    </div>
                <?php endforeach; ?>
            </section>

            <section class="panel">
                <div class="panel-heading"><h2>สมาชิกที่เพิ่มล่าสุด</h2><a class="button" href="members_list.php">ดูสมาชิก</a></div>
                <?php if (!$recentMembers): ?><p class="empty">ยังไม่มีสมาชิก</p><?php endif; ?>
                <?php foreach ($recentMembers as $member): ?>
                    <div class="row">
                        <div class="row-main"><span class="row-title"><?= escape($member['full_name']) ?></span><span class="row-meta"><?= escape($member['member_type']) ?> · <?= escape($member['member_id']) ?> · <?= escape($member['created_at']) ?></span></div>
                        <a class="row-link" href="members_list.php?type=<?= $member['member_type'] === 'นักศึกษา' ? 'students' : ($member['member_type'] === 'บุคลากร' ? 'personnel' : 'alumni') ?>">เปิดรายการ</a>
                    </div>
                <?php endforeach; ?>
            </section>
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script>
        const overviewShareSummary = <?= json_encode($shareSummary, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        const overviewActionStatus = document.getElementById('overview-action-status');
        document.getElementById('print-overview').addEventListener('click', () => window.print());
        document.getElementById('share-overview').addEventListener('click', async () => {
            const shareText = [
                `${overviewShareSummary.title} ประจำปี พ.ศ. ${overviewShareSummary.year}`,
                `ข่าวสารและกิจกรรม: ${overviewShareSummary.content} (เผยแพร่ ${overviewShareSummary.published}, ฉบับร่าง ${overviewShareSummary.draft})`,
                `หลักสูตร: ${overviewShareSummary.courses}`,
                `บุคลากรบนเว็บไซต์: ${overviewShareSummary.staff_profiles}`,
                `บัญชีสมาชิก: ${overviewShareSummary.total} (บุคลากร ${overviewShareSummary.personnel_accounts}, นักศึกษา ${overviewShareSummary.students}, ศิษย์เก่า ${overviewShareSummary.alumni})`,
            ].join('\n');
            const shareData = { title: overviewShareSummary.title, text: shareText, url: window.location.href };
            try {
                if (navigator.share) {
                    await navigator.share(shareData);
                    overviewActionStatus.textContent = 'แชร์ภาพรวมแล้ว';
                } else if (navigator.clipboard?.writeText) {
                    await navigator.clipboard.writeText(`${shareText}\n${shareData.url}`);
                    overviewActionStatus.textContent = 'คัดลอกสรุปภาพรวมแล้ว';
                } else {
                    const shareTextArea = document.createElement('textarea');
                    shareTextArea.value = `${shareText}\n${shareData.url}`;
                    shareTextArea.setAttribute('readonly', '');
                    shareTextArea.style.position = 'fixed';
                    shareTextArea.style.opacity = '0';
                    document.body.append(shareTextArea);
                    shareTextArea.select();
                    const copied = document.execCommand('copy');
                    shareTextArea.remove();
                    overviewActionStatus.textContent = copied ? 'คัดลอกสรุปภาพรวมแล้ว' : 'อุปกรณ์นี้ไม่รองรับการแชร์';
                }
            } catch (error) {
                overviewActionStatus.textContent = error.name === 'AbortError' ? '' : 'แชร์ไม่สำเร็จ กรุณาลองอีกครั้ง';
            }
        });

        const recordChartData = <?= json_encode($monthlyChartData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        const dailyRecordData = <?= json_encode($dailyChartData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        const recordChartLabels = <?= json_encode(array_map(static fn(array $source): string => $source['label'], $chartSources), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        const monthLabels = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
        const chartColors = ['#167d78', '#3769a8', '#dd8737', '#7b65a8', '#c65050', '#78a641'];
        const chartYear = <?= (int) $selectedYear ?>;
        const chartSourceSelect = document.getElementById('chart-source');
        const chartStatus = document.getElementById('chart-status');
        const chartFallback = document.getElementById('chart-fallback');
        const chartCanvas = document.getElementById('records-chart');
        let selectedChartType = 'bar';
        let recordsChart = null;

        const calculateTrendline = (values) => {
            const points = values.map((value, index) => ({ x: index + 1, y: value }));
            const meanX = points.reduce((sum, point) => sum + point.x, 0) / points.length;
            const meanY = points.reduce((sum, point) => sum + point.y, 0) / points.length;
            const numerator = points.reduce((sum, point) => sum + (point.x - meanX) * (point.y - meanY), 0);
            const denominator = points.reduce((sum, point) => sum + (point.x - meanX) ** 2, 0);
            const slope = denominator === 0 ? 0 : numerator / denominator;
            const intercept = meanY - slope * meanX;
            return points.map((point) => ({ x: point.x, y: intercept + slope * point.x }));
        };

        const getMonthlyOhlc = (sourceKeys) => monthLabels.map((_, monthIndex) => {
            const daysInMonth = new Date(chartYear, monthIndex + 1, 0).getDate();
            const dailyCounts = Array.from({ length: daysInMonth }, (_, dayIndex) => sourceKeys.reduce(
                (total, key) => total + (dailyRecordData[key][monthIndex][dayIndex + 1] || 0),
                0
            ));
            return {
                open: dailyCounts[0],
                close: dailyCounts[dailyCounts.length - 1],
                high: Math.max(...dailyCounts),
                low: Math.min(...dailyCounts),
            };
        });

        const stockChartPlugin = {
            id: 'record-stock-chart',
            afterDatasetsDraw(chart) {
                if (!['candlestick', 'ohlc'].includes(selectedChartType)) return;
                const { ctx, chartArea, scales: { x, y } } = chart;
                const sourceKeys = chartSourceSelect.value === 'all'
                    ? Object.keys(dailyRecordData)
                    : [chartSourceSelect.value];
                const values = getMonthlyOhlc(sourceKeys);
                const slotWidth = (chartArea.right - chartArea.left) / monthLabels.length;
                const bodyWidth = Math.max(4, Math.min(18, slotWidth * .5));
                ctx.save();
                values.forEach((value, index) => {
                    const center = x.getPixelForValue(index);
                    const openY = y.getPixelForValue(value.open);
                    const closeY = y.getPixelForValue(value.close);
                    const highY = y.getPixelForValue(value.high);
                    const lowY = y.getPixelForValue(value.low);
                    const color = value.close > value.open ? '#16834a' : value.close < value.open ? '#c33b3b' : '#64748b';
                    ctx.strokeStyle = color;
                    ctx.fillStyle = color;
                    ctx.lineWidth = 2;
                    ctx.beginPath();
                    ctx.moveTo(center, highY);
                    ctx.lineTo(center, lowY);
                    ctx.stroke();

                    if (selectedChartType === 'ohlc') {
                        ctx.beginPath();
                        ctx.moveTo(center - bodyWidth / 2, openY);
                        ctx.lineTo(center, openY);
                        ctx.moveTo(center, closeY);
                        ctx.lineTo(center + bodyWidth / 2, closeY);
                        ctx.stroke();
                    } else {
                        const bodyTop = Math.min(openY, closeY);
                        const bodyHeight = Math.max(2, Math.abs(closeY - openY));
                        ctx.fillRect(center - bodyWidth / 2, bodyTop, bodyWidth, bodyHeight);
                    }
                });
                ctx.restore();
            },
        };

        document.getElementById('chart-year').addEventListener('change', (event) => event.currentTarget.form.requestSubmit());

        const renderRecordsChart = () => {
            if (typeof Chart === 'undefined') {
                chartCanvas.hidden = true;
                chartFallback.hidden = false;
                return;
            }

            const selectedSource = chartSourceSelect.value;
            const sourceKeys = selectedSource === 'all' ? Object.keys(recordChartData) : [selectedSource];
            const stockValues = ['candlestick', 'ohlc'].includes(selectedChartType) ? getMonthlyOhlc(sourceKeys) : [];
            const selectedTotal = sourceKeys.reduce((total, key) => total + recordChartData[key].reduce((sum, count) => sum + count, 0), 0);
            chartStatus.textContent = selectedTotal === 0
                ? 'ไม่มีรายการในชุดข้อมูลและปีที่เลือก'
                : `ปี <?= escape((string) ($selectedYear + 543)) ?> · ${selectedSource === 'all' ? 'ทุกชุดข้อมูล' : recordChartLabels[selectedSource]} · ${selectedTotal} รายการ`;
            if (selectedTotal > 0 && ['candlestick', 'ohlc'].includes(selectedChartType)) {
                chartStatus.textContent += ' · OHLC แสดงจำนวนรายการรายวัน ไม่ใช่ราคาหุ้น';
            }

            let chartType = selectedChartType === 'scatter-trend' ? 'scatter' : selectedChartType;
            if (['histogram', 'stacked', 'combo', 'candlestick', 'ohlc'].includes(selectedChartType)) chartType = 'bar';
            if (selectedChartType === 'area') chartType = 'line';
            if (selectedChartType === 'doughnut') chartType = 'doughnut';
            if (selectedChartType === 'polar-area') chartType = 'polarArea';
            let labels = monthLabels;
            let datasets;
            if (['candlestick', 'ohlc'].includes(selectedChartType)) {
                datasets = [{
                    label: 'จำนวนรายการต่อวัน (เปิด-สูงสุด-ต่ำสุด-ปิด)',
                    data: stockValues.map((value) => value.close),
                    backgroundColor: 'transparent',
                    borderColor: 'transparent',
                    borderWidth: 0,
                    pointRadius: 0,
                }];
            } else if (['pie', 'doughnut', 'polar-area'].includes(selectedChartType)) {
                if (selectedSource === 'all') {
                    labels = sourceKeys.map((key) => recordChartLabels[key]);
                    datasets = [{
                        data: sourceKeys.map((key) => recordChartData[key].reduce((sum, count) => sum + count, 0)),
                        backgroundColor: sourceKeys.map((key) => chartColors[Object.keys(recordChartData).indexOf(key)]),
                        borderWidth: 1,
                    }];
                } else {
                    datasets = [{
                        label: 'จำนวนรายการ',
                        data: recordChartData[selectedSource],
                        backgroundColor: monthLabels.map((_, index) => chartColors[index % chartColors.length]),
                        borderWidth: 1,
                    }];
                }
            } else if (selectedChartType === 'combo') {
                const monthlyTotals = monthLabels.map((_, monthIndex) => sourceKeys.reduce((sum, key) => sum + recordChartData[key][monthIndex], 0));
                const yearlyTotal = monthlyTotals.reduce((sum, count) => sum + count, 0);
                let cumulative = 0;
                const cumulativeShare = monthlyTotals.map((count) => {
                    cumulative += count;
                    return yearlyTotal === 0 ? 0 : (cumulative / yearlyTotal) * 100;
                });
                datasets = [
                    { type: 'bar', label: 'รายการที่บันทึกต่อเดือน', data: monthlyTotals, backgroundColor: '#167d78', yAxisID: 'y' },
                    { type: 'line', label: 'สัดส่วนสะสม (%)', data: cumulativeShare, borderColor: '#dd8737', backgroundColor: 'transparent', pointBackgroundColor: '#dd8737', borderWidth: 2, tension: .3, yAxisID: 'y1' },
                ];
            } else {
                datasets = sourceKeys.map((key) => ({
                    label: recordChartLabels[key],
                    data: selectedChartType === 'scatter-trend'
                        ? recordChartData[key].map((value, index) => ({ x: index + 1, y: value }))
                        : recordChartData[key],
                    borderColor: chartColors[Object.keys(recordChartData).indexOf(key)],
                    backgroundColor: ['line', 'area', 'scatter-trend', 'radar'].includes(selectedChartType)
                        ? `${chartColors[Object.keys(recordChartData).indexOf(key)]}33`
                        : chartColors[Object.keys(recordChartData).indexOf(key)],
                    pointBackgroundColor: chartColors[Object.keys(recordChartData).indexOf(key)],
                    borderWidth: ['line', 'scatter-trend', 'radar'].includes(selectedChartType) ? 2 : 1,
                    tension: .3,
                    fill: ['area', 'radar'].includes(selectedChartType),
                    pointRadius: selectedChartType === 'scatter-trend' ? 4 : 2,
                    showLine: selectedChartType !== 'scatter-trend',
                    stack: selectedChartType === 'stacked' ? 'monthly-records' : undefined,
                }));
                if (selectedChartType === 'scatter-trend') {
                    datasets.push(...sourceKeys.map((key) => ({
                        type: 'line',
                        label: `${recordChartLabels[key]} (แนวโน้ม)`,
                        data: calculateTrendline(recordChartData[key]),
                        borderColor: chartColors[Object.keys(recordChartData).indexOf(key)],
                        borderDash: [5, 4],
                        borderWidth: 2,
                        pointRadius: 0,
                        showLine: true,
                    })));
                }
            }

            const options = {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: ['pie', 'doughnut', 'polar-area', 'radar', 'stacked', 'combo', 'scatter-trend', 'candlestick', 'ohlc'].includes(selectedChartType) || sourceKeys.length > 1, position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label(context) {
                                if (['candlestick', 'ohlc'].includes(selectedChartType)) {
                                    const value = stockValues[context.dataIndex];
                                    return ` เปิด ${value.open} · สูง ${value.high} · ต่ำ ${value.low} · ปิด ${value.close} รายการ`;
                                }
                                return `${context.dataset.label || ''}: ${context.formattedValue}`;
                            },
                        },
                    },
                },
            };
            if (['candlestick', 'ohlc'].includes(selectedChartType)) {
                const low = Math.min(...stockValues.map((value) => value.low));
                const high = Math.max(...stockValues.map((value) => value.high));
                options.scales = {
                    y: { min: Math.min(0, low), max: high === low ? high + 1 : high, beginAtZero: true, ticks: { precision: 0 }, title: { display: true, text: 'จำนวนรายการต่อวัน' } },
                    x: { offset: true, ticks: { maxRotation: 0, autoSkip: false } },
                };
            } else if (['radar', 'polar-area'].includes(selectedChartType)) {
                options.scales = { r: { beginAtZero: true, ticks: { precision: 0 } } };
            } else if (selectedChartType === 'combo') {
                options.scales = {
                    y: { beginAtZero: true, ticks: { precision: 0 }, title: { display: true, text: 'จำนวนรายการ' } },
                    y1: { beginAtZero: true, max: 100, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'สัดส่วนสะสม (%)' } },
                };
            } else if (!['pie', 'doughnut'].includes(selectedChartType)) {
                options.scales = {
                    y: { beginAtZero: true, stacked: selectedChartType === 'stacked', ticks: { precision: 0 } },
                    x: selectedChartType === 'scatter-trend'
                        ? { type: 'linear', min: 1, max: 12, ticks: { stepSize: 1, callback: (value) => monthLabels[value - 1] ?? '' } }
                        : { offset: selectedChartType !== 'histogram', stacked: selectedChartType === 'stacked', ticks: { maxRotation: 0, autoSkip: false } },
                };
                if (selectedChartType === 'histogram') {
                    datasets.forEach((dataset) => { dataset.barPercentage = 1; dataset.categoryPercentage = .95; });
                }
            }
            if (recordsChart) recordsChart.destroy();
            recordsChart = new Chart(chartCanvas, {
                type: chartType,
                data: { labels, datasets },
                options,
                plugins: [stockChartPlugin],
            });
        };

        chartSourceSelect.addEventListener('change', renderRecordsChart);
        document.querySelectorAll('[data-chart-type]').forEach((button) => {
            button.addEventListener('click', () => {
                selectedChartType = button.dataset.chartType;
                document.querySelectorAll('[data-chart-type]').forEach((modeButton) => {
                    modeButton.setAttribute('aria-pressed', String(modeButton === button));
                });
                renderRecordsChart();
            });
        });
        renderRecordsChart();
    </script>
</body>
</html>