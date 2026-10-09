<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true,
    ]);
}
require_once __DIR__ . '/admin/db.php';

$roles = [
    'student' => [
        'table' => 'students',
        'id' => 'student_id',
        'label' => 'นักศึกษา',
        'fields' => ['email' => 'อีเมล', 'faculty' => 'คณะ', 'major' => 'สาขาวิชา', 'class_year' => 'ชั้นปี'],
    ],
    'personnel' => [
        'table' => 'personnel',
        'id' => 'personnel_id',
        'label' => 'บุคลากร',
        'fields' => ['email' => 'อีเมล', 'faculty' => 'คณะ', 'department' => 'หน่วยงาน', 'position' => 'ตำแหน่ง'],
    ],
    'alumni' => [
        'table' => 'alumni',
        'id' => 'alumni_id',
        'label' => 'ศิษย์เก่า',
        'fields' => ['email' => 'อีเมล', 'phone' => 'เบอร์โทรศัพท์', 'faculty' => 'คณะ', 'major' => 'สาขาวิชา', 'graduation_year' => 'ปีที่สำเร็จการศึกษา', 'current_job' => 'อาชีพ / สถานที่ทำงาน'],
    ],
];
$fieldLabels = [
    'prefix' => 'คำนำหน้า',
    'firstname' => 'ชื่อ',
    'lastname' => 'นามสกุล',
    'email' => 'อีเมล',
    'faculty' => 'คณะ',
    'major' => 'สาขาวิชา',
    'class_year' => 'ชั้นปี',
    'department' => 'หน่วยงาน',
    'position' => 'ตำแหน่ง',
    'phone' => 'เบอร์โทรศัพท์',
    'graduation_year' => 'ปีที่สำเร็จการศึกษา (พ.ศ.)',
    'current_job' => 'อาชีพ / สถานที่ทำงาน',
];
$fieldLimits = [
    'prefix' => 20,
    'firstname' => 100,
    'lastname' => 100,
    'email' => 100,
    'faculty' => 100,
    'major' => 100,
    'class_year' => 1,
    'department' => 100,
    'position' => 100,
    'phone' => 15,
    'graduation_year' => 4,
    'current_job' => 150,
];
$personnelOptions = [
    'faculty' => require __DIR__ . '/admin/course_faculties.php',
    'department' => ['คณะครุศาสตร์', 'คณะวิทยาศาสตร์', 'คณะมนุษยศาสตร์', 'คณะบริหารธุรกิจ', 'ฝ่ายวิชาการ', 'ฝ่ายบุคคล', 'งานทะเบียน', 'งานเทคโนโลยีสารสนเทศ'],
    'position' => ['อาจารย์', 'ครู', 'ผู้ช่วยศาสตราจารย์', 'รองศาสตราจารย์', 'เจ้าหน้าที่', 'หัวหน้าภาควิชา'],
];
$roleKey = $_SESSION['member_role'] ?? '';
$memberId = $_SESSION['member_id'] ?? '';

if (!is_string($roleKey) || !isset($roles[$roleKey]) || !is_string($memberId) || $memberId === '') {
    header('Location: login.html');
    exit;
}

$role = $roles[$roleKey];
$editableFields = array_merge(
    ['prefix', 'firstname', 'lastname', 'email'],
    array_values(array_diff(array_keys($role['fields']), ['email']))
);
$editValues = [];
$error = '';
$isEditing = ($_GET['edit'] ?? '') === '1';
$saved = ($_GET['saved'] ?? '') === '1';

try {
    $columns = array_values(array_unique(array_merge(
        [$role['id']],
        $editableFields
    )));
    $selectColumns = array_map(static fn(string $column): string => '`' . $column . '`', $columns);
    $statement = db()->prepare(
        'SELECT ' . implode(', ', $selectColumns)
        . ' FROM `' . $role['table'] . '` WHERE `' . $role['id'] . '` = :member_id LIMIT 1'
    );
    $statement->execute(['member_id' => $memberId]);
    $member = $statement->fetch();
    if (!$member) {
        unset($_SESSION['member_role'], $_SESSION['member_id']);
        header('Location: login.html?error=invalid');
        exit;
    }

    foreach ($editableFields as $field) {
        $editValues[$field] = (string) ($member[$field] ?? '');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $isEditing = true;
        foreach ($editableFields as $field) {
            $value = $_POST[$field] ?? '';
            if (!is_string($value)) {
                $error = 'ข้อมูลที่ส่งมาไม่ถูกต้อง กรุณาตรวจสอบอีกครั้ง';
                break;
            }
            $editValues[$field] = trim($value);
        }

        if (($_POST['action'] ?? '') !== 'update_profile') {
            $error = 'คำขอไม่ถูกต้อง กรุณาลองใหม่';
        }
        if ($error === '' && ($editValues['firstname'] === '' || $editValues['lastname'] === '')) {
            $error = 'กรุณากรอกชื่อและนามสกุลให้ครบถ้วน';
        }
        if ($error === '' && $editValues['email'] !== '' && !filter_var($editValues['email'], FILTER_VALIDATE_EMAIL)) {
            $error = 'รูปแบบอีเมลไม่ถูกต้อง';
        }
        foreach ($editableFields as $field) {
            if ($error !== '') {
                break;
            }
            if (strlen($editValues[$field]) > $fieldLimits[$field]) {
                $error = $fieldLabels[$field] . 'ยาวเกินกำหนด';
            }
        }
        if ($error === '' && $roleKey === 'student' && $editValues['class_year'] !== ''
            && filter_var($editValues['class_year'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 9]]) === false) {
            $error = 'ชั้นปีต้องเป็นจำนวนเต็มตั้งแต่ 1 ถึง 9';
        }
        if ($error === '' && $roleKey === 'alumni' && $editValues['graduation_year'] !== ''
            && filter_var($editValues['graduation_year'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 2400, 'max_range' => 3000]]) === false) {
            $error = 'ปีที่สำเร็จการศึกษาต้องเป็น พ.ศ. ที่ถูกต้อง';
        }
        if ($error === '' && $roleKey === 'personnel') {
            foreach ($personnelOptions as $field => $options) {
                if ($editValues[$field] !== '' && !in_array($editValues[$field], $options, true)
                    && $editValues[$field] !== (string) ($member[$field] ?? '')) {
                    $error = 'กรุณาเลือก' . $fieldLabels[$field] . 'จากรายการ';
                    break;
                }
            }
        }

        if ($error === '') {
            try {
                $updates = array_map(static fn(string $field): string => '`' . $field . '` = :profile_' . $field, $editableFields);
                $parameters = [];
                foreach ($editableFields as $field) {
                    $parameters['profile_' . $field] = $editValues[$field] === '' ? null : $editValues[$field];
                }
                $parameters['member_id'] = $memberId;
                $statement = db()->prepare(
                    'UPDATE `' . $role['table'] . '` SET ' . implode(', ', $updates)
                    . ' WHERE `' . $role['id'] . '` = :member_id'
                );
                $statement->execute($parameters);
                header('Location: intranet.php?saved=1');
                exit;
            } catch (Throwable $exception) {
                error_log('Member profile update failed: ' . $exception->getMessage());
                $error = 'บันทึกข้อมูลไม่สำเร็จ กรุณาลองใหม่ภายหลัง';
            }
        }
    }
} catch (Throwable $exception) {
    error_log('Member profile load failed: ' . $exception->getMessage());
    http_response_code(503);
    exit('ไม่สามารถโหลดข้อมูลสมาชิกได้ กรุณาลองใหม่ภายหลัง');
}

$fullName = trim(implode(' ', array_filter([
    (string) ($member['prefix'] ?? ''),
    (string) ($member['firstname'] ?? ''),
    (string) ($member['lastname'] ?? ''),
])));
$displayFields = array_merge(
    ['email' => $fieldLabels['email']],
    array_diff_key($role['fields'], ['email' => true])
);
$portalMenus = [
    'personnel' => [
        ['title' => 'ข้อมูลบุคลากร', 'description' => 'ตรวจสอบและแก้ไขข้อมูลส่วนตัวกับหน่วยงาน', 'href' => 'intranet.php?edit=1#edit', 'available' => true],
        ['title' => 'แค็ตตาล็อกรายวิชา', 'description' => 'ดูรายวิชาที่เปิดให้บริการในระบบ', 'href' => '#courses', 'available' => true],
        ['title' => 'ตารางปฏิบัติงาน', 'description' => 'ตารางเวรและกำหนดการทำงาน', 'href' => '', 'available' => false],
        ['title' => 'ตารางสอน', 'description' => 'รายวิชา ห้องเรียน และเวลาเรียนที่รับผิดชอบ', 'href' => '', 'available' => false],
        ['title' => 'ผลการเรียนของนักศึกษา', 'description' => 'ตรวจสอบผลการเรียนรายวิชาที่สอน', 'href' => '', 'available' => false],
    ],
    'student' => [
        ['title' => 'ข้อมูลนักศึกษา', 'description' => 'ตรวจสอบและแก้ไขข้อมูลส่วนตัวกับหลักสูตร', 'href' => 'intranet.php?edit=1#edit', 'available' => true],
        ['title' => 'แค็ตตาล็อกรายวิชา', 'description' => 'ค้นหารายวิชาและรายละเอียดหลักสูตร', 'href' => '#courses', 'available' => true],
        ['title' => 'ตารางเรียนและลงทะเบียน', 'description' => 'ตารางเรียน การเพิ่มถอน และสถานะลงทะเบียน', 'href' => '', 'available' => false],
        ['title' => 'ผลการเรียน', 'description' => 'เกรดเฉลี่ยและผลการเรียนรายภาค', 'href' => '', 'available' => false],
        ['title' => 'ข่าวและประกาศ', 'description' => 'ติดตามข่าวสารและประกาศของมหาวิทยาลัย', 'href' => 'news.html', 'available' => true],
    ],
    'alumni' => [
        ['title' => 'ข้อมูลศิษย์เก่า', 'description' => 'แก้ไขข้อมูลติดต่อและประวัติการทำงาน', 'href' => 'intranet.php?edit=1#edit', 'available' => true],
        ['title' => 'เครือข่ายและกิจกรรม', 'description' => 'ดูข่าวและกิจกรรมสำหรับศิษย์เก่า', 'href' => 'alumni.html', 'available' => true],
        ['title' => 'ข่าวมหาวิทยาลัย', 'description' => 'ติดตามข่าวสารและประกาศล่าสุด', 'href' => 'news.html', 'available' => true],
        ['title' => 'ขอ Transcript / ใบรับรอง', 'description' => 'ส่งคำขอเอกสารและติดตามสถานะ', 'href' => '', 'available' => false],
        ['title' => 'ประวัติการศึกษา', 'description' => 'ตรวจสอบเอกสารและผลการศึกษาย้อนหลัง', 'href' => '', 'available' => false],
    ],
];
$services = $portalMenus[$roleKey];
$courses = [];
if (in_array($roleKey, ['student', 'personnel'], true)) {
    try {
        $courses = db()->query(
            "SELECT course_code, course_name, department, credits FROM courses WHERE status = 'active' ORDER BY department, course_name"
        )->fetchAll();
    } catch (Throwable $exception) {
        error_log('Member course catalog load failed: ' . $exception->getMessage());
    }
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#174b36">
    <title>โปรไฟล์สมาชิก | มหาวิทยาลัยตัวอย่าง</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { color-scheme: light; --green: #174b36; --green-dark: #103a2a; --green-soft: #e9f2ec; --ink: #24342c; --muted: #718078; --line: #d8e1db; --paper: #fff; --canvas: #f3f6f3; --danger: #a53c35; }
        * { box-sizing: border-box; }
        body { min-height: 100vh; margin: 0; background: linear-gradient(115deg, rgba(23,75,54,.035), transparent 42%), var(--canvas); color: var(--ink); font-family: 'Sarabun', 'Leelawadee UI', Tahoma, sans-serif; line-height: 1.55; }
        a { color: inherit; }
        .topbar { min-height: 66px; padding: 0 28px; display: flex; align-items: center; justify-content: space-between; gap: 1rem; border-bottom: 1px solid var(--line); background: var(--paper); }
        .brand { color: var(--green); font-weight: 700; text-decoration: none; }
        .layout { width: min(1180px, calc(100% - 40px)); min-height: calc(100vh - 66px); margin: 0 auto; display: grid; grid-template-columns: 220px minmax(0, 1fr); gap: 38px; }
        .sidebar { padding: 32px 0; display: flex; flex-direction: column; }
        .nav-label, .eyebrow { margin: 0 0 9px; color: var(--muted); font-size: .76rem; font-weight: 700; }
        .profile-nav { display: grid; gap: 4px; }
        .profile-nav a { padding: 10px 12px; border-left: 3px solid transparent; color: #42554a; text-decoration: none; }
        .profile-nav a:hover, .profile-nav a[aria-current="page"] { border-left-color: var(--green); background: var(--green-soft); color: var(--green-dark); }
        .account-type { margin-top: auto; padding: 16px 12px 0; border-top: 1px solid var(--line); }
        .account-type span { display: block; color: var(--muted); font-size: .75rem; }
        .content { min-width: 0; padding: 38px 0 56px; }
        .page-title { margin-bottom: 26px; }
        .page-title h1 { margin: 0; font-size: 1.5rem; }
        .page-title p { margin: 4px 0 0; color: var(--muted); }
        .profile-head { padding: 24px 0; display: flex; align-items: center; justify-content: space-between; gap: 20px; border-top: 1px solid var(--line); border-bottom: 1px solid var(--line); }
        .profile-head h2 { margin: 0; font-size: 1.65rem; overflow-wrap: anywhere; }
        .profile-meta { margin: 5px 0 0; color: var(--muted); }
        .role-chip { display: inline-block; margin-bottom: 6px; padding: 3px 9px; border-radius: 3px; background: var(--green-soft); color: var(--green-dark); font-size: .82rem; font-weight: 700; }
        .button { min-height: 42px; padding: 8px 14px; display: inline-flex; align-items: center; justify-content: center; border: 1px solid var(--line); border-radius: 4px; background: var(--paper); color: var(--ink); font: inherit; font-weight: 600; text-decoration: none; cursor: pointer; }
        .button:hover { border-color: var(--green); color: var(--green); }
        .button-primary { border-color: var(--green); background: var(--green); color: #fff; }
        .button-primary:hover { background: var(--green-dark); color: #fff; }
        .section { padding: 26px 0; border-bottom: 1px solid var(--line); }
        .section-heading { margin-bottom: 16px; }
        .section-heading h2 { margin: 0; font-size: 1.12rem; }
        .section-heading p { margin: 3px 0 0; color: var(--muted); font-size: .9rem; }
        .service-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
        .service-item { min-width: 0; padding: 15px; border: 1px solid var(--line); border-radius: 4px; background: var(--paper); }
        .service-item h3 { margin: 0; font-size: 1rem; }
        .service-item p { min-height: 44px; margin: 5px 0 12px; color: var(--muted); font-size: .88rem; }
        .service-footer { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
        .service-state { color: var(--muted); font-size: .78rem; }
        .service-state.is-ready { color: var(--green); font-weight: 700; }
        .course-table-wrap { overflow-x: auto; border: 1px solid var(--line); background: var(--paper); }
        .course-table { width: 100%; border-collapse: collapse; text-align: left; }
        .course-table th, .course-table td { padding: 10px 12px; border-bottom: 1px solid var(--line); vertical-align: top; }
        .course-table th { background: var(--green-soft); color: var(--green-dark); font-size: .84rem; }
        .course-table td { font-size: .9rem; }
        .course-table tr:last-child td { border-bottom: 0; }
        .details { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); margin: 0; border-top: 1px solid var(--line); border-left: 1px solid var(--line); background: var(--paper); }
        .detail { min-width: 0; min-height: 78px; padding: 13px 15px; border-right: 1px solid var(--line); border-bottom: 1px solid var(--line); }
        dt { margin-bottom: 3px; color: var(--muted); font-size: .84rem; }
        dd { margin: 0; font-weight: 600; overflow-wrap: anywhere; }
        .action-list { margin: 0; padding: 0; list-style: none; }
        .action-row { min-height: 66px; display: flex; align-items: center; justify-content: space-between; gap: 16px; border-top: 1px solid var(--line); }
        .action-row:last-child { border-bottom: 1px solid var(--line); }
        .action-row p { margin: 0; font-weight: 600; }
        .action-row small { display: block; color: var(--muted); font-weight: 400; }
        .notice { margin: 0 0 18px; padding: 11px 14px; border-left: 3px solid var(--green); background: var(--green-soft); color: var(--green-dark); }
        .notice-error { border-left-color: var(--danger); background: #fff4f2; color: #812f29; }
        .edit-form { max-width: 760px; }
        .form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 15px 18px; }
        .field { min-width: 0; }
        .field-wide { grid-column: 1 / -1; }
        label { display: block; margin-bottom: 5px; font-weight: 600; }
        input, select { width: 100%; min-height: 44px; padding: 8px 11px; border: 1px solid #cbd7ce; border-radius: 4px; background: var(--paper); color: var(--ink); font: inherit; }
        input:focus-visible, select:focus-visible, a:focus-visible, button:focus-visible { outline: 3px solid rgba(38,118,80,.22); outline-offset: 2px; }
        .form-actions { display: flex; gap: 9px; margin-top: 22px; }
        .help-note { margin: 16px 0 0; color: var(--muted); font-size: .86rem; }
        @media (max-width: 760px) {
            .topbar { padding: 0 16px; }
            .layout { width: min(100% - 28px, 680px); grid-template-columns: 1fr; gap: 0; }
            .sidebar { padding: 13px 0 0; }
            .nav-label, .account-type { display: none; }
            .profile-nav { display: flex; gap: 4px; overflow-x: auto; border-bottom: 1px solid var(--line); }
            .profile-nav a { flex: 0 0 auto; padding: 9px 11px; border-left: 0; border-bottom: 3px solid transparent; }
            .profile-nav a:hover, .profile-nav a[aria-current="page"] { border-left: 0; border-bottom-color: var(--green); }
            .content { padding: 25px 0 40px; }
        }
        @media (max-width: 520px) {
            .topbar { min-height: 58px; }
            .layout { min-height: calc(100vh - 58px); }
            .profile-head { align-items: flex-start; flex-direction: column; }
            .details, .form-grid { grid-template-columns: 1fr; }
            .service-grid { grid-template-columns: 1fr; }
            .field-wide { grid-column: auto; }
            .action-row { align-items: flex-start; padding: 12px 0; }
            .form-actions { flex-direction: column; }
        }
    </style>
</head>
<body>
<header class="topbar">
    <a class="brand" href="index.html">มหาวิทยาลัยตัวอย่าง</a>
    <form method="post" action="logout.php">
        <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
        <button class="button" type="submit">ออกจากระบบ</button>
    </form>
</header>
<div class="layout">
    <aside class="sidebar" aria-label="เมนูโปรไฟล์">
        <p class="nav-label">เมนูสมาชิก</p>
        <nav class="profile-nav">
            <a href="intranet.php#overview" <?= !$isEditing ? 'aria-current="page"' : '' ?>>ภาพรวม</a>
            <a href="intranet.php#services">เมนู <?= escape($role['label']) ?></a>
            <a href="intranet.php#details">ข้อมูลโปรไฟล์</a>
            <a href="intranet.php?edit=1#edit" <?= $isEditing ? 'aria-current="page"' : '' ?>>แก้ไขข้อมูล</a>
        </nav>
        <div class="account-type"><span>ประเภทบัญชี</span><strong><?= escape($role['label']) ?></strong></div>
    </aside>
    <main class="content">
        <div class="page-title"><h1>พื้นที่สมาชิก</h1><p>ตรวจสอบและจัดการข้อมูลโปรไฟล์ของคุณ</p></div>
        <?php if ($saved): ?><p class="notice" role="status">บันทึกข้อมูลโปรไฟล์เรียบร้อยแล้ว</p><?php endif; ?>
        <?php if ($error !== ''): ?><p class="notice notice-error" role="alert"><?= escape($error) ?></p><?php endif; ?>
        <section class="profile-head" id="overview" aria-labelledby="profile-name">
            <div>
                <span class="role-chip"><?= escape($role['label']) ?></span>
                <h2 id="profile-name"><?= escape($fullName !== '' ? $fullName : $memberId) ?></h2>
                <p class="profile-meta">รหัสสมาชิก <?= escape($memberId) ?></p>
            </div>
            <?php if (!$isEditing): ?><a class="button button-primary" href="intranet.php?edit=1#edit">แก้ไขโปรไฟล์</a><?php endif; ?>
        </section>

        <?php if (!$isEditing): ?>
            <section class="section" id="services" aria-labelledby="services-title">
                <div class="section-heading"><h2 id="services-title">เมนูสำหรับ<?= escape($role['label']) ?></h2><p>บริการที่เกี่ยวข้องกับบทบาทและบัญชีของคุณ</p></div>
                <div class="service-grid">
                    <?php foreach ($services as $service): ?>
                        <article class="service-item">
                            <h3><?= escape($service['title']) ?></h3>
                            <p><?= escape($service['description']) ?></p>
                            <div class="service-footer">
                                <?php if ($service['available']): ?>
                                    <span class="service-state is-ready">พร้อมใช้งาน</span>
                                    <a class="button" href="<?= escape($service['href']) ?>">เปิด</a>
                                <?php else: ?>
                                    <span class="service-state">ยังไม่เชื่อมข้อมูล</span>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <?php if (in_array($roleKey, ['student', 'personnel'], true)): ?>
                <section class="section" id="courses" aria-labelledby="courses-title">
                    <div class="section-heading"><h2 id="courses-title">แค็ตตาล็อกรายวิชา</h2><p>ข้อมูลรายวิชาที่เปิดให้ดูในปัจจุบัน</p></div>
                    <?php if ($courses): ?>
                        <div class="course-table-wrap">
                            <table class="course-table">
                                <thead><tr><th scope="col">รหัสวิชา</th><th scope="col">ชื่อวิชา</th><th scope="col">หน่วยกิต</th><th scope="col">ภาควิชา</th></tr></thead>
                                <tbody>
                                    <?php foreach ($courses as $course): ?>
                                        <tr>
                                            <td><?= escape((string) $course['course_code']) ?></td>
                                            <td><?= escape((string) $course['course_name']) ?></td>
                                            <td><?= escape((string) $course['credits']) ?></td>
                                            <td><?= escape((string) ($course['department'] ?? '—')) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="notice">ยังไม่มีรายวิชาที่เปิดให้แสดง</p>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($isEditing): ?>
            <section class="section" id="edit" aria-labelledby="edit-title">
                <div class="section-heading"><h2 id="edit-title">แก้ไขข้อมูลโปรไฟล์</h2><p>รหัสสมาชิกและประเภทบัญชีไม่สามารถแก้ไขได้</p></div>
                <form class="edit-form" method="post" action="intranet.php?edit=1#edit">
                    <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="form-grid">
                        <?php foreach ($editableFields as $field): ?>
                            <?php
                            $label = $fieldLabels[$field];
                            $value = $editValues[$field] ?? '';
                            $required = in_array($field, ['firstname', 'lastname'], true);
                            $wide = in_array($field, ['email', 'faculty', 'major', 'current_job'], true);
                            ?>
                            <div class="field <?= $wide ? 'field-wide' : '' ?>">
                                <label for="field-<?= escape($field) ?>"><?= escape($label) ?></label>
                                <?php if ($roleKey === 'personnel' && isset($personnelOptions[$field])): ?>
                                    <select id="field-<?= escape($field) ?>" name="<?= escape($field) ?>">
                                        <option value="">เลือก<?= escape($label) ?></option>
                                        <?php if ($value !== '' && !in_array($value, $personnelOptions[$field], true)): ?>
                                            <option value="<?= escape($value) ?>" selected><?= escape($value) ?></option>
                                        <?php endif; ?>
                                        <?php foreach ($personnelOptions[$field] as $option): ?>
                                            <option value="<?= escape($option) ?>" <?= $value === $option ? 'selected' : '' ?>><?= escape($option) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php elseif ($field === 'class_year' || $field === 'graduation_year'): ?>
                                    <input id="field-<?= escape($field) ?>" name="<?= escape($field) ?>" type="number" min="<?= $field === 'class_year' ? '1' : '2400' ?>" max="<?= $field === 'class_year' ? '9' : '3000' ?>" value="<?= escape($value) ?>">
                                <?php else: ?>
                                    <input id="field-<?= escape($field) ?>" name="<?= escape($field) ?>" type="<?= $field === 'email' ? 'email' : 'text' ?>" maxlength="<?= $fieldLimits[$field] ?>" value="<?= escape($value) ?>" <?= $required ? 'required' : '' ?>>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="help-note">แก้ไขได้เฉพาะข้อมูลของบัญชี <?= escape($role['label']) ?> นี้</p>
                    <div class="form-actions">
                        <button class="button button-primary" type="submit">บันทึกข้อมูล</button>
                        <a class="button" href="intranet.php#details">ยกเลิก</a>
                    </div>
                </form>
            </section>
        <?php else: ?>
            <section class="section" id="details" aria-labelledby="details-title">
                <div class="section-heading"><h2 id="details-title">ข้อมูลโปรไฟล์</h2><p>ข้อมูลที่ใช้ระบุตัวตนและรายละเอียดสมาชิก</p></div>
                <dl class="details">
                    <div class="detail"><dt>ประเภทสมาชิก</dt><dd><?= escape($role['label']) ?></dd></div>
                    <div class="detail"><dt>รหัสสมาชิก</dt><dd><?= escape($memberId) ?></dd></div>
                    <?php foreach ($displayFields as $field => $label): ?>
                        <div class="detail"><dt><?= escape($label) ?></dt><dd><?= escape((string) (($member[$field] ?? '') !== '' ? $member[$field] : '—')) ?></dd></div>
                    <?php endforeach; ?>
                </dl>
            </section>
            <section class="section" id="actions" aria-labelledby="actions-title">
                <div class="section-heading"><h2 id="actions-title">เมนูสมาชิก</h2><p>จัดการข้อมูลบัญชีที่เปิดใช้งานอยู่</p></div>
                <ul class="action-list">
                    <li class="action-row"><p>ดูข้อมูลโปรไฟล์<small>ตรวจสอบรายละเอียดสมาชิกและข้อมูลที่บันทึกไว้</small></p><a class="button" href="#details">ดูข้อมูล</a></li>
                    <li class="action-row"><p>แก้ไขข้อมูลส่วนตัว<small>ปรับชื่อ อีเมล และข้อมูลสมาชิกตามประเภทบัญชี</small></p><a class="button" href="intranet.php?edit=1#edit">แก้ไข</a></li>
                    <li class="action-row"><p>ออกจากระบบ<small>สิ้นสุดการใช้งานบัญชีนี้บนอุปกรณ์ปัจจุบัน</small></p><form method="post" action="logout.php"><input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>"><button class="button" type="submit">ออกจากระบบ</button></form></li>
                </ul>
            </section>
        <?php endif; ?>
    </main>
</div>
</body>
</html>