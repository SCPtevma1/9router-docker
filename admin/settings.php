<?php
declare(strict_types=1);

require_once __DIR__ . '/check_session.php';
require_once __DIR__ . '/admin_nav.php';
require_once __DIR__ . '/site_settings.php';

$settings = site_settings_defaults();
$error = '';
$saved = isset($_GET['saved']) && $_GET['saved'] === '1';

try {
    $settings = site_settings();
} catch (Throwable $exception) {
    error_log('Admin settings load failed: ' . $exception->getMessage());
    $error = 'โหลดการตั้งค่าไม่สำเร็จ กรุณาตรวจสอบการเชื่อมต่อฐานข้อมูล';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $form = [
        'site_name' => trim((string) ($_POST['site_name'] ?? '')),
        'contact_email' => trim((string) ($_POST['contact_email'] ?? '')),
        'contact_phone' => trim((string) ($_POST['contact_phone'] ?? '')),
        'registration_student' => isset($_POST['registration_student']) ? '1' : '0',
        'registration_personnel' => isset($_POST['registration_personnel']) ? '1' : '0',
        'registration_alumni' => isset($_POST['registration_alumni']) ? '1' : '0',
    ];

    if ($form['site_name'] === '' || mb_strlen($form['site_name'], 'UTF-8') > 100) {
        $error = 'กรุณากรอกชื่อเว็บไซต์ไม่เกิน 100 ตัวอักษร';
    } elseif ($form['contact_email'] !== ''
        && (!filter_var($form['contact_email'], FILTER_VALIDATE_EMAIL) || strlen($form['contact_email']) > 100)) {
        $error = 'กรุณาตรวจสอบรูปแบบอีเมลติดต่อ';
    } elseif (strlen($form['contact_phone']) > 30) {
        $error = 'เบอร์โทรศัพท์ต้องไม่เกิน 30 ตัวอักษร';
    } else {
        try {
            save_site_settings($form);
            header('Location: settings.php?saved=1');
            exit;
        } catch (Throwable $exception) {
            error_log('Admin settings save failed: ' . $exception->getMessage());
            $error = 'บันทึกการตั้งค่าไม่สำเร็จ กรุณาลองใหม่';
            $settings = array_merge($settings, $form);
        }
    }
    if ($error !== '' && isset($form)) {
        $settings = array_merge($settings, $form);
    }
}

$registrationTypes = [
    'student' => ['key' => 'registration_student', 'label' => 'นักศึกษา', 'description' => 'บัญชีนักศึกษาและข้อมูลคณะ/สาขา'],
    'personnel' => ['key' => 'registration_personnel', 'label' => 'บุคลากร', 'description' => 'บัญชีบุคลากรและข้อมูลหน่วยงาน'],
    'alumni' => ['key' => 'registration_alumni', 'label' => 'ศิษย์เก่า', 'description' => 'บัญชีศิษย์เก่าและข้อมูลการศึกษา'],
];
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ตั้งค่าเว็บไซต์ | Admin</title>
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
        main { width: min(900px, calc(100% - 4rem)); min-width: 0; margin: 0 auto; padding: 2rem 0; }
        h1 { margin: 0; font-size: 1.6rem; }
        h2 { margin: 0; font-size: 1.08rem; }
        .sub { margin: .35rem 0 1.4rem; color: #66788a; }
        .panel { margin-bottom: 1rem; padding: 1.25rem; border: 1px solid #d7e0ea; border-radius: 5px; background: #fff; }
        .panel-heading { margin-bottom: 1rem; }
        .panel-heading p { margin: .3rem 0 0; color: #66788a; font-size: .92rem; }
        .form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; }
        .field { min-width: 0; }
        .field-wide { grid-column: 1 / -1; }
        label { display: block; margin-bottom: .35rem; font-weight: 600; }
        input[type="text"], input[type="email"], input[type="tel"] { width: 100%; min-height: 42px; padding: .55rem .7rem; border: 1px solid #bdc9d6; border-radius: 4px; color: inherit; font: inherit; }
        input:focus-visible, button:focus-visible { outline: 3px solid rgba(30,74,122,.2); outline-offset: 2px; }
        .toggle-list { display: grid; }
        .toggle-row { display: flex; align-items: flex-start; gap: .75rem; padding: .8rem 0; border-top: 1px solid #e4eaf0; cursor: pointer; }
        .toggle-row:first-child { border-top: 0; }
        .toggle-row input { width: 18px; height: 18px; flex: 0 0 auto; margin: .15rem 0 0; accent-color: #1e4a7a; }
        .toggle-copy { display: grid; gap: .15rem; }
        .toggle-copy small { color: #66788a; }
        .notice { margin: 0 0 1rem; padding: .8rem 1rem; border-left: 3px solid #a53c35; background: #fff; color: #812f29; }
        .success { border-color: #26834a; color: #17652a; }
        .actions { display: flex; justify-content: flex-end; }
        button { min-height: 42px; padding: .55rem 1rem; border: 1px solid #1e4a7a; border-radius: 4px; background: #1e4a7a; color: #fff; font: inherit; font-weight: 700; cursor: pointer; }
        @media (max-width: 760px) { body { grid-template-columns: 1fr; } .side { position: static; height: auto; padding: 1rem; } nav { display: flex; flex-wrap: wrap; margin-top: .8rem; } .user { position: static; margin-top: .8rem; padding-top: .7rem; } main { width: min(100% - 2rem, 900px); padding: 1.2rem 0; } }
        @media (max-width: 540px) { .form-grid { grid-template-columns: 1fr; } .field-wide { grid-column: auto; } .panel { padding: 1rem; } }
    </style>
    <link rel="stylesheet" href="admin.css">
</head>
<body>
<aside class="side">
    <a class="brand" href="../index.html"><span class="brand-mark" aria-hidden="true"><img src="../img/images.jpg" alt=""></span><span>ระบบจัดการเว็บไซต์</span></a>
    <?php render_admin_nav('settings'); ?>
    <div class="user"><small>ผู้ดูแลระบบ</small><strong><?= escape((string) ($_SESSION['admin_username'] ?? '')) ?></strong><a href="logout.php">ออกจากระบบ</a></div>
</aside>
<main>
    <h1>ตั้งค่าเว็บไซต์</h1>
    <p class="sub">จัดการข้อมูลที่แสดงในหน้าสมัครสมาชิกและประเภทบัญชีที่เปิดรับ</p>

    <?php if ($saved): ?><p class="notice success" role="status">บันทึกการตั้งค่าเรียบร้อยแล้ว</p><?php endif; ?>
    <?php if ($error !== ''): ?><p class="notice" role="alert"><?= escape($error) ?></p><?php endif; ?>

    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
        <section class="panel" aria-labelledby="identity-heading">
            <div class="panel-heading"><h2 id="identity-heading">ข้อมูลเว็บไซต์</h2><p>แสดงบนหน้าสมัครสมาชิก</p></div>
            <div class="form-grid">
                <div class="field field-wide"><label for="site_name">ชื่อเว็บไซต์</label><input id="site_name" name="site_name" type="text" maxlength="100" required value="<?= escape($settings['site_name']) ?>"></div>
                <div class="field"><label for="contact_email">อีเมลติดต่อ</label><input id="contact_email" name="contact_email" type="email" maxlength="100" value="<?= escape($settings['contact_email']) ?>"></div>
                <div class="field"><label for="contact_phone">โทรศัพท์ติดต่อ</label><input id="contact_phone" name="contact_phone" type="tel" maxlength="30" value="<?= escape($settings['contact_phone']) ?>"></div>
            </div>
        </section>

        <section class="panel" aria-labelledby="registration-heading">
            <div class="panel-heading"><h2 id="registration-heading">การสมัครสมาชิก</h2><p>ปิดเฉพาะประเภทที่ไม่ต้องการรับสมัครได้ โดยไม่กระทบข้อมูลสมาชิกเดิม</p></div>
            <div class="toggle-list">
                <?php foreach ($registrationTypes as $type): ?>
                    <label class="toggle-row" for="<?= escape($type['key']) ?>">
                        <input id="<?= escape($type['key']) ?>" name="<?= escape($type['key']) ?>" type="checkbox" value="1" <?= $settings[$type['key']] === '1' ? 'checked' : '' ?>>
                        <span class="toggle-copy"><strong>เปิดรับสมัคร<?= escape($type['label']) ?></strong><small><?= escape($type['description']) ?></small></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </section>

        <div class="actions"><button type="submit">บันทึกการตั้งค่า</button></div>
    </form>
</main>
</body>
</html>