<?php
declare(strict_types=1);

require_once __DIR__ . '/check_session.php';
require_once __DIR__ . '/admin_nav.php';

$roles = [
    'students' => [
        'table' => 'students',
        'id' => 'student_id',
        'label' => 'นักศึกษา',
        'fields' => [
            'prefix' => 'คำนำหน้า',
            'firstname' => 'ชื่อ',
            'lastname' => 'นามสกุล',
            'email' => 'อีเมล',
            'faculty' => 'คณะ',
            'major' => 'สาขาวิชา',
            'class_year' => 'ชั้นปี',
            'status' => 'สถานะ',
        ],
    ],
    'personnel' => [
        'table' => 'personnel',
        'id' => 'personnel_id',
        'label' => 'บุคลากร',
        'fields' => [
            'prefix' => 'คำนำหน้า',
            'firstname' => 'ชื่อ',
            'lastname' => 'นามสกุล',
            'email' => 'อีเมล',
            'faculty' => 'คณะ',
            'department' => 'หน่วยงาน',
            'position' => 'ตำแหน่ง',
        ],
    ],
    'alumni' => [
        'table' => 'alumni',
        'id' => 'alumni_id',
        'label' => 'ศิษย์เก่า',
        'fields' => [
            'prefix' => 'คำนำหน้า',
            'firstname' => 'ชื่อ',
            'lastname' => 'นามสกุล',
            'email' => 'อีเมล',
            'phone' => 'เบอร์โทรศัพท์',
            'faculty' => 'คณะที่สำเร็จการศึกษา',
            'major' => 'สาขาวิชา',
            'graduation_year' => 'ปีที่สำเร็จการศึกษา (พ.ศ.)',
            'current_job' => 'อาชีพ / สถานที่ทำงาน',
        ],
    ],
];
$requestedType = $_GET['type'] ?? '';
$memberType = is_string($requestedType) ? $requestedType : '';
$requestedId = $_GET['id'] ?? '';
$memberId = is_string($requestedId) ? trim($requestedId) : '';
$member = null;
$error = '';

$returnTypeValue = $_GET['return_type'] ?? $memberType;
$returnType = is_string($returnTypeValue) && ($returnTypeValue === 'all' || isset($roles[$returnTypeValue]))
    ? $returnTypeValue
    : 'all';
$returnSearchValue = $_GET['return_search'] ?? '';
$returnSearch = is_string($returnSearchValue) ? mb_substr(trim($returnSearchValue), 0, 100, 'UTF-8') : '';
$returnPage = max(1, filter_var($_GET['return_page'] ?? 1, FILTER_VALIDATE_INT) ?: 1);
$returnUrl = 'members_list.php?' . http_build_query([
    'type' => $returnType,
    'q' => $returnSearch,
    'page' => $returnPage,
]);

if (!isset($roles[$memberType]) || $memberId === '' || strlen($memberId) > 15) {
    http_response_code(400);
    $error = 'ข้อมูลสมาชิกที่ร้องขอไม่ถูกต้อง';
} else {
    try {
        $role = $roles[$memberType];
        $columns = array_merge([$role['id']], array_keys($role['fields']));
        $selectColumns = array_map(static fn(string $column): string => '`' . $column . '`', $columns);
        $selectColumns[] = "CASE WHEN `password` IS NOT NULL AND `password` <> '' THEN 'ตั้งรหัสผ่านแล้ว' ELSE 'ยังไม่ได้ตั้งรหัสผ่าน' END AS `password_status`";
        $selectColumns[] = '`created_at`';
        $statement = db()->prepare(
            'SELECT ' . implode(', ', $selectColumns)
            . ' FROM `' . $role['table'] . '` WHERE `' . $role['id'] . '` = :member_id LIMIT 1'
        );
        $statement->execute(['member_id' => $memberId]);
        $member = $statement->fetch() ?: null;
        if (!$member) {
            http_response_code(404);
            $error = 'ไม่พบข้อมูลสมาชิกที่ต้องการ';
        } elseif ($memberType === 'personnel' && !empty($member['email'])) {
            $profileStatement = db()->prepare(
                'SELECT image FROM personnel_profiles
                WHERE email = :email AND image <> ""
                ORDER BY id DESC LIMIT 1'
            );
            $profileStatement->execute(['email' => $member['email']]);
            $member['profile_image'] = (string) ($profileStatement->fetchColumn() ?: '');
        }
    } catch (Throwable $exception) {
        error_log('Member detail query failed: ' . $exception->getMessage());
        http_response_code(500);
        $error = 'โหลดข้อมูลสมาชิกไม่สำเร็จ กรุณาลองใหม่';
    }
}

$pageTitle = $member ? 'ข้อมูลสมาชิก' . $roles[$memberType]['label'] : 'ไม่พบข้อมูลสมาชิก';
$personnelName = '';
$personnelRecordCode = '';
$personnelImage = '';
if ($member && $memberType === 'personnel') {
    $personnelName = trim(implode(' ', array_filter([
        (string) ($member['prefix'] ?? ''),
        (string) ($member['firstname'] ?? ''),
        (string) ($member['lastname'] ?? ''),
    ], static fn(string $part): bool => $part !== '')));
    $personnelRecordCode = 'UMB-' . str_pad((string) $member['personnel_id'], 5, '0', STR_PAD_LEFT);
    $storedImage = trim((string) ($member['profile_image'] ?? ''));
    if ($storedImage !== '') {
        $personnelImage = preg_match('~^https://~i', $storedImage)
            ? $storedImage
            : '../' . ltrim($storedImage, '/');
    }
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= escape($pageTitle) ?></title>
    <style>
        * { box-sizing: border-box; }
        body { min-height: 100vh; margin: 0; display: grid; grid-template-columns: 250px minmax(0, 1fr); background: #f4f7fa; color: #17324d; font-family: Arial, sans-serif; }
        .side { position: sticky; top: 0; height: 100vh; padding: 1.5rem 1.25rem; background: #17324d; color: #fff; }
        .brand { display: flex; align-items: center; gap: .75rem; color: #fff; font-weight: 700; text-decoration: none; }
        .brand-mark { width: 38px; height: 38px; display: grid; place-items: center; border: 1px solid rgba(255,255,255,.45); border-left: 3px solid #c8a35c; }
        nav { display: grid; gap: .35rem; margin-top: 2rem; }
        nav a { padding: .65rem .75rem; border-radius: 4px; color: rgba(255,255,255,.82); text-decoration: none; }
        nav a:hover, nav a[aria-current="page"] { background: rgba(255,255,255,.12); color: #fff; }
        .user { position: absolute; right: 1.25rem; bottom: 1.5rem; left: 1.25rem; display: grid; gap: .25rem; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,.2); overflow-wrap: anywhere; }
        .user small { color: rgba(255,255,255,.65); }
        .user a { margin-top: .35rem; color: #fff; }
        main { width: min(950px, calc(100% - 4rem)); min-width: 0; margin: 0 auto; padding: 2rem 0; }
        .heading { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.25rem; }
        h1 { margin: 0; font-size: 1.6rem; }
        .sub { margin: .35rem 0 0; color: #66788a; }
        .back, .notice { display: inline-flex; align-items: center; min-height: 40px; padding: .5rem .75rem; border: 1px solid #bdc9d6; border-radius: 4px; background: #fff; color: #1e4a7a; text-decoration: none; }
        .back:hover { background: #edf2f7; }
        .details { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); border: 1px solid #d7e0ea; background: #fff; }
        .detail { min-width: 0; padding: .85rem 1rem; border-bottom: 1px solid #e4eaf0; }
        .detail:nth-child(odd) { border-right: 1px solid #e4eaf0; }
        dt { margin-bottom: .25rem; color: #66788a; font-size: .84rem; }
        dd { margin: 0; overflow-wrap: anywhere; font-weight: 600; }
        .password-note { margin: 1rem 0 0; color: #66788a; font-size: .88rem; }
        .notice { display: block; border-left: 3px solid #a53c35; color: #812f29; }
        .personnel-dossier { width: min(1120px, 100%); margin: 0 auto; overflow: hidden; border: 1px solid #d9dfda; border-top: 5px solid #a5323d; border-radius: 6px; background: #fff; box-shadow: 0 12px 32px rgba(22,35,29,.08); }
        .dossier-masthead { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1rem 1.25rem; background: #1d2925; color: #fff; }
        .dossier-brand { display: flex; align-items: center; gap: .75rem; min-width: 0; }
        .dossier-brand img { width: 42px; height: 42px; flex: 0 0 auto; border: 1px solid rgba(255,255,255,.55); border-radius: 3px; background: #fff; object-fit: contain; }
        .dossier-brand span, .dossier-brand strong { display: block; }
        .dossier-brand span { color: rgba(255,255,255,.7); font-family: ui-monospace, Consolas, monospace; font-size: .7rem; letter-spacing: .04em; }
        .dossier-brand strong { margin-top: .15rem; font-size: .94rem; }
        .classification { flex: 0 0 auto; padding: .4rem .6rem; border: 1px solid #e5b8b9; border-radius: 3px; background: #f7e9e9; color: #842933; font-family: ui-monospace, Consolas, monospace; font-size: .75rem; font-weight: 800; text-align: center; }
        .classification small { display: block; margin-top: .12rem; font-family: "Segoe UI", Tahoma, sans-serif; font-size: .7rem; font-weight: 600; }
        .dossier-heading { display: flex; align-items: flex-end; justify-content: space-between; gap: 1rem; padding: 1.15rem 1.25rem; border-bottom: 1px solid #d9dfda; }
        .dossier-heading h1 { margin: .2rem 0 0; font-size: 1.5rem; }
        .dossier-kicker { color: #a5323d; font-family: ui-monospace, Consolas, monospace; font-size: .74rem; font-weight: 800; letter-spacing: .05em; }
        .dossier-heading .sub { margin: .25rem 0 0; }
        .employee-id { padding: .55rem .7rem; border: 1px solid #d9dfda; border-radius: 4px; background: #f7f8f5; text-align: right; }
        .employee-id span, .employee-id strong { display: block; }
        .employee-id span { color: #68736d; font-size: .72rem; }
        .employee-id strong { margin-top: .1rem; color: #842933; font-family: ui-monospace, Consolas, monospace; font-size: 1rem; }
        .dossier-profile { display: grid; grid-template-columns: 160px minmax(0,1fr); gap: 1.2rem; padding: 1.25rem; border-bottom: 1px solid #e4e9e4; }
        .dossier-photo { width: 160px; aspect-ratio: 4 / 5; display: grid; place-items: center; overflow: hidden; border: 1px solid #d9dfda; border-radius: 4px; background: #eff2ee; color: #68736d; }
        .dossier-photo img { width: 100%; height: 100%; object-fit: cover; }
        .dossier-photo-placeholder { color: #a5323d; font-family: Georgia, serif; font-size: 3rem; font-weight: 700; }
        .dossier-identity { min-width: 0; align-self: center; }
        .dossier-identity h2 { margin: .2rem 0 .65rem; font-size: 1.55rem; }
        .dossier-identity-line { display: flex; flex-wrap: wrap; gap: .45rem; margin-bottom: .8rem; }
        .dossier-chip { padding: .25rem .5rem; border: 1px solid #d9dfda; border-radius: 3px; background: #f7f8f5; color: #425148; font-size: .78rem; }
        .dossier-chip.is-active { border-color: #b9d8c8; background: #edf7f1; color: #176b50; }
        .dossier-identity p { margin: .25rem 0; color: #425148; overflow-wrap: anywhere; }
        .dossier-identity a { color: #842933; }
        .dossier-grid { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: .85rem; padding: 1rem 1.25rem; }
        .dossier-section { min-width: 0; padding: .9rem; border: 1px solid #d9dfda; border-radius: 4px; background: #fff; }
        .dossier-section.wide { grid-column: 1 / -1; }
        .dossier-section h2 { display: flex; align-items: baseline; gap: .55rem; margin: 0 0 .7rem; font-size: .98rem; }
        .dossier-section h2 span { color: #a5323d; font-family: ui-monospace, Consolas, monospace; font-size: .72rem; }
        .dossier-fields { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: .6rem; }
        .dossier-field { min-width: 0; padding: .55rem .6rem; border-left: 2px solid #d9dfda; background: #f7f8f5; }
        .dossier-field dt { margin: 0 0 .2rem; color: #68736d; font-size: .76rem; }
        .dossier-field dd { margin: 0; color: #202a26; font-size: .88rem; font-weight: 650; overflow-wrap: anywhere; }
        .dossier-field .not-recorded { color: #68736d; font-weight: 400; }
        .disposition { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .8rem; border: 1px solid #e5b8b9; background: #fbf3f2; }
        .disposition-stamp { padding: .35rem .6rem; border: 2px solid #a5323d; color: #a5323d; font-family: ui-monospace, Consolas, monospace; font-size: .86rem; font-weight: 900; transform: rotate(-2deg); }
        .dossier-footer { display: flex; align-items: flex-end; justify-content: space-between; gap: 1rem; padding: 1rem 1.25rem; border-top: 1px solid #d9dfda; background: #f7f8f5; }
        .dossier-footer p { margin: 0; color: #68736d; font-size: .78rem; }
        .dossier-footer .back { flex: 0 0 auto; }
        @media (max-width: 760px) { body { grid-template-columns: 1fr; } .side { position: static; height: auto; padding: 1rem; } nav { display: flex; flex-wrap: wrap; margin-top: .8rem; } .user { position: static; margin-top: .8rem; padding-top: .7rem; } main { width: min(100% - 2rem, 950px); padding: 1.2rem 0; } }
        @media (max-width: 760px) { .dossier-profile { grid-template-columns: 110px minmax(0,1fr); gap: .8rem; padding: 1rem; } .dossier-photo { width: 110px; } .dossier-grid { padding: .75rem; } .dossier-masthead, .dossier-heading { align-items: flex-start; flex-direction: column; } .classification, .employee-id { text-align: left; } }
        @media (max-width: 540px) { .details { grid-template-columns: 1fr; } .detail:nth-child(odd) { border-right: 0; } .dossier-grid, .dossier-fields { grid-template-columns: 1fr; } .dossier-section.wide { grid-column: auto; } .dossier-profile { grid-template-columns: 1fr; } .dossier-photo { width: 120px; } .dossier-footer, .disposition { align-items: flex-start; flex-direction: column; } }
    </style>
    <link rel="stylesheet" href="admin.css">
</head>
<body>
<aside class="side">
    <a class="brand" href="../index.html"><span class="brand-mark" aria-hidden="true"><img src="../img/images.jpg" alt=""></span><span>ระบบจัดการเว็บไซต์</span></a>
    <?php render_admin_nav('members'); ?>
    <div class="user"><small>ผู้ดูแลระบบ</small><strong><?= escape((string) ($_SESSION['admin_username'] ?? '')) ?></strong><a href="logout.php">ออกจากระบบ</a></div>
</aside>
<main>
    <?php if ($error !== ''): ?>
        <div class="heading">
            <div><h1><?= escape($pageTitle) ?></h1><p class="sub">ไม่สามารถเปิดแฟ้มข้อมูลได้</p></div>
            <a class="back" href="<?= escape($returnUrl) ?>">กลับไปรายชื่อสมาชิก</a>
        </div>
        <p class="notice" role="alert"><?= escape($error) ?></p>
    <?php elseif ($memberType === 'personnel'): ?>
        <article class="personnel-dossier">
            <header class="dossier-masthead">
                <div class="dossier-brand"><img src="../img/images.jpg" alt=""><div><span>HUMAN RESOURCES DIVISION</span><strong>UMBRELLA SECURITY SERVICE · U.S.S.</strong></div></div>
                <div class="classification">RESTRICTED ACCESS<small>สำหรับผู้ดูแลที่ผ่านการเข้าสู่ระบบ</small></div>
            </header>
            <div class="dossier-heading">
                <div><span class="dossier-kicker">EMPLOYEE PERSONNEL RECORD</span><h1>ข้อมูลสมาชิกบุคลากร</h1><p class="sub">ประวัติบุคลากร · <?= escape((string) $member['created_at']) ?></p></div>
                <div class="employee-id"><span>เลขแฟ้ม · อ้างอิงรหัสบัญชี</span><strong><?= escape($personnelRecordCode) ?></strong></div>
            </div>
            <section class="dossier-profile" aria-label="ข้อมูลประจำตัว">
                <div class="dossier-photo">
                    <?php if ($personnelImage !== ''): ?>
                        <img src="<?= escape($personnelImage) ?>" alt="รูปประจำตัว <?= escape($personnelName) ?>">
                    <?php else: ?>
                        <span class="dossier-photo-placeholder" aria-label="ไม่มีรูปประจำตัวในระบบ"><?= escape(mb_substr((string) ($member['firstname'] ?? 'บ'), 0, 1, 'UTF-8')) ?></span>
                    <?php endif; ?>
                </div>
                <div class="dossier-identity">
                    <div class="dossier-identity-line"><span class="dossier-chip">บุคลากร</span><span class="dossier-chip <?= $member['password_status'] === 'ตั้งรหัสผ่านแล้ว' ? 'is-active' : 'is-pending' ?>"><?= escape($member['password_status']) ?></span></div>
                    <h2><?= escape($personnelName) ?></h2>
                    <?php if (!empty($member['position'])): ?><p><strong>ตำแหน่ง:</strong> <?= escape((string) $member['position']) ?></p><?php endif; ?>
                    <?php if (!empty($member['faculty'])): ?><p><strong>คณะ:</strong> <?= escape((string) $member['faculty']) ?></p><?php endif; ?>
                    <?php if (!empty($member['department'])): ?><p><strong>หน่วยงาน:</strong> <?= escape((string) $member['department']) ?></p><?php endif; ?>
                    <?php if (!empty($member['email'])): ?><p><strong>อีเมล:</strong> <a href="mailto:<?= escape((string) $member['email']) ?>"><?= escape((string) $member['email']) ?></a></p><?php endif; ?>
                </div>
            </section>
            <div class="dossier-grid">
                <section class="dossier-section" aria-labelledby="physical-heading">
                    <h2 id="physical-heading"><span>01</span> ข้อมูลพื้นฐานและกายภาพ</h2>
                    <dl class="dossier-fields">
                        <div class="dossier-field"><dt>วันเกิด / อายุ</dt><dd class="not-recorded">ไม่ได้บันทึกในระบบ</dd></div>
                        <div class="dossier-field"><dt>สัญชาติ</dt><dd class="not-recorded">ไม่ได้บันทึกในระบบ</dd></div>
                        <div class="dossier-field"><dt>ที่อยู่ปัจจุบัน</dt><dd class="not-recorded">ไม่ได้บันทึกในระบบ</dd></div>
                        <div class="dossier-field"><dt>ส่วนสูง / น้ำหนัก</dt><dd class="not-recorded">ไม่ได้บันทึกในระบบ</dd></div>
                        <div class="dossier-field"><dt>กรุ๊ปเลือด</dt><dd class="not-recorded">ไม่ได้บันทึกในระบบ</dd></div>
                        <div class="dossier-field"><dt>ตำหนิ / แผลเป็น</dt><dd class="not-recorded">ไม่ได้บันทึกในระบบ</dd></div>
                    </dl>
                </section>
                <section class="dossier-section" aria-labelledby="professional-heading">
                    <h2 id="professional-heading"><span>02</span> ประวัติการทำงานและความเชี่ยวชาญ</h2>
                    <dl class="dossier-fields">
                        <div class="dossier-field"><dt>ตำแหน่ง</dt><dd><?= escape((string) (($member['position'] ?? '') ?: '—')) ?></dd></div>
                        <div class="dossier-field"><dt>คณะ / หน่วยงาน</dt><dd><?= escape(implode(' · ', array_filter([(string) ($member['faculty'] ?? ''), (string) ($member['department'] ?? '')]))) ?: '—' ?></dd></div>
                        <div class="dossier-field"><dt>การศึกษา</dt><dd class="not-recorded">ไม่ได้บันทึกในระบบ</dd></div>
                        <div class="dossier-field"><dt>ความเชี่ยวชาญ</dt><dd class="not-recorded">ไม่ได้บันทึกในระบบ</dd></div>
                        <div class="dossier-field"><dt>โครงการที่รับผิดชอบ</dt><dd class="not-recorded">ไม่ได้บันทึกในระบบ</dd></div>
                        <div class="dossier-field"><dt>วันที่สมัคร</dt><dd><?= escape((string) ($member['created_at'] ?? '—')) ?></dd></div>
                    </dl>
                </section>
                <section class="dossier-section" aria-labelledby="psych-heading">
                    <h2 id="psych-heading"><span>03</span> การประเมินทางจิตวิทยา</h2>
                    <dl class="dossier-fields">
                        <div class="dossier-field"><dt>สภาพจิตใจ</dt><dd class="not-recorded">ไม่มีข้อมูลการประเมิน</dd></div>
                        <div class="dossier-field"><dt>คะแนนความภักดี</dt><dd class="not-recorded">ไม่ได้ประเมิน</dd></div>
                    </dl>
                </section>
                <section class="dossier-section" aria-labelledby="disposition-heading">
                    <h2 id="disposition-heading"><span>04</span> สถานะและการดำเนินการ</h2>
                    <div class="disposition"><div><strong>สถานะการจ้างงาน</strong><p class="sub">ฐานข้อมูลบัญชีบุคลากรไม่มีฟิลด์สถานะการจ้าง</p></div><span class="disposition-stamp">NOT RECORDED</span></div>
                </section>
            </div>
            <footer class="dossier-footer"><p>RESTRICTED · เปิดดูโดย <?= escape((string) ($_SESSION['admin_username'] ?? 'ผู้ดูแลระบบ')) ?> · ไม่แสดงรหัสผ่านจริงเพื่อความปลอดภัย</p><a class="back" href="<?= escape($returnUrl) ?>">กลับไปรายชื่อสมาชิก</a></footer>
        </article>
    <?php else: ?>
        <div class="heading">
            <div><h1><?= escape($pageTitle) ?></h1><p class="sub"><?= escape($roles[$memberType]['label']) ?> · <?= escape((string) $member[$roles[$memberType]['id']]) ?></p></div>
            <a class="back" href="<?= escape($returnUrl) ?>">กลับไปรายชื่อสมาชิก</a>
        </div>
        <dl class="details">
            <div class="detail"><dt>ประเภทสมาชิก</dt><dd><?= escape($roles[$memberType]['label']) ?></dd></div>
            <div class="detail"><dt><?= escape($roles[$memberType]['label']) === 'นักศึกษา' ? 'รหัสนักศึกษา' : ($memberType === 'personnel' ? 'รหัสบุคลากร' : 'รหัสศิษย์เก่า') ?></dt><dd><?= escape((string) $member[$roles[$memberType]['id']]) ?></dd></div>
            <?php foreach ($roles[$memberType]['fields'] as $field => $label): ?>
                <div class="detail"><dt><?= escape($label) ?></dt><dd><?= escape((string) (($member[$field] ?? '') !== '' ? $member[$field] : '—')) ?></dd></div>
            <?php endforeach; ?>
            <div class="detail"><dt>รหัสผ่าน</dt><dd><?= escape($member['password_status']) ?></dd></div>
            <div class="detail"><dt>วันที่สมัคร</dt><dd><?= escape((string) ($member['created_at'] ?? '—')) ?></dd></div>
        </dl>
        <p class="password-note">ระบบไม่แสดงรหัสผ่านเดิมเพื่อความปลอดภัย และจัดเก็บรหัสผ่านในรูปแบบที่ไม่สามารถย้อนกลับได้</p>
    <?php endif; ?>
</main>
</body>
</html>