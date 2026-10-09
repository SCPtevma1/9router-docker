<?php
declare(strict_types=1);
require_once __DIR__ . '/../check_session.php';
require_once __DIR__ . '/../admin_nav.php';
require_once __DIR__ . '/../image_uploads.php';

$pdo = db();
$idValue = $_GET['id'] ?? $_POST['id'] ?? null;
$id = $idValue === null || $idValue === '' ? null : filter_var($idValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($idValue !== null && $id === false) {
    http_response_code(400);
    exit('รหัสบุคลากรไม่ถูกต้อง');
}
$existing = null;
$existingImages = [];
if ($id !== null) {
    $statement = $pdo->prepare('SELECT * FROM personnel_profiles WHERE id = :id');
    $statement->execute(['id' => $id]);
    $existing = $statement->fetch() ?: null;
    if (!$existing) {
        http_response_code(404);
        exit('ไม่พบข้อมูลบุคลากร');
    }
    $imageStatement = $pdo->prepare(
        'SELECT id, image_path, display_order FROM personnel_profile_images
        WHERE personnel_profile_id = :id ORDER BY display_order ASC, id ASC'
    );
    $imageStatement->execute(['id' => $id]);
    $existingImages = $imageStatement->fetchAll();
}
$form = [
    'prefix' => (string) ($existing['prefix'] ?? ''),
    'fullname' => (string) ($existing['fullname'] ?? ''),
    'position' => (string) ($existing['position'] ?? ''),
    'department' => (string) ($existing['department'] ?? ''),
    'email' => (string) ($existing['email'] ?? ''),
    'phone' => (string) ($existing['phone'] ?? ''),
    'display_order' => (string) ($existing['display_order'] ?? '0'),
    'status' => (string) ($existing['status'] ?? 'active'),
];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach (array_keys($form) as $field) {
        $form[$field] = trim((string) ($_POST[$field] ?? ''));
    }
    $removeImage = ($_POST['remove_image'] ?? '') === '1';
    $selectedImageIds = $_POST['remove_images'] ?? [];
    $selectedImageIds = is_array($selectedImageIds)
        ? array_values(array_unique(array_filter(array_map(
            static fn(mixed $imageId): int => filter_var($imageId, FILTER_VALIDATE_INT) ?: 0,
            $selectedImageIds
        ))))
        : [];
    $existingImageIds = array_map(static fn(array $image): int => (int) $image['id'], $existingImages);
    $removeImageIds = array_values(array_intersect($selectedImageIds, $existingImageIds));

    $order = $form['display_order'] === '' ? 0 : filter_var($form['display_order'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1000000]]);
    if ($form['fullname'] === '' || mb_strlen($form['fullname'], 'UTF-8') > 255) {
        $error = 'กรุณากรอกชื่อ-นามสกุลไม่เกิน 255 ตัวอักษร';
    } elseif ($form['prefix'] !== '' && mb_strlen($form['prefix'], 'UTF-8') > 50) {
        $error = 'คำนำหน้าต้องไม่เกิน 50 ตัวอักษร';
    } elseif ($form['position'] === '' || mb_strlen($form['position'], 'UTF-8') > 150) {
        $error = 'กรุณากรอกตำแหน่งไม่เกิน 150 ตัวอักษร';
    } elseif ($form['department'] === '' || mb_strlen($form['department'], 'UTF-8') > 150) {
        $error = 'กรุณากรอกหน่วยงานไม่เกิน 150 ตัวอักษร';
    } elseif ($form['email'] !== '' && (!filter_var($form['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($form['email'], 'UTF-8') > 190)) {
        $error = 'กรุณาตรวจสอบรูปแบบอีเมล (ไม่เกิน 190 ตัวอักษร)';
    } elseif (mb_strlen($form['phone'], 'UTF-8') > 30) {
        $error = 'เบอร์โทรศัพท์ต้องไม่เกิน 30 ตัวอักษร';
    } elseif ($order === false) {
        $error = 'ลำดับการแสดงต้องเป็นจำนวนเต็มตั้งแต่ 0 ถึง 1000000';
    } elseif (!in_array($form['status'], ['active', 'inactive'], true)) {
        $error = 'สถานะไม่ถูกต้อง';
    } else {
        $directory = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'personnel';
        $imagePath = (string) ($existing['image'] ?? '');
        $newFiles = [];
        if ($removeImage) {
            $imagePath = '';
        }
        try {
            $newFiles = save_uploaded_images(
                $_FILES['image_upload'] ?? [],
                $directory,
                'img/personnel',
                'personnel_'
            );

            $values = [
                'prefix' => $form['prefix'],
                'fullname' => $form['fullname'],
                'position' => $form['position'],
                'department' => $form['department'],
                'email' => $form['email'] ?: null,
                'phone' => $form['phone'] ?: null,
                'image' => $imagePath,
                'display_order' => (int) $order,
                'status' => $form['status'],
            ];
            $pdo->beginTransaction();
            $profileId = $id === null ? 0 : (int) $id;
            if ($id !== null) {
                $statement = $pdo->prepare(
                    'UPDATE personnel_profiles SET prefix = :prefix, fullname = :fullname,
                        position = :position, department = :department, email = :email,
                        phone = :phone, image = :image, display_order = :display_order,
                        status = :status WHERE id = :id'
                );
                $statement->execute($values + ['id' => $id]);
            } else {
                $statement = $pdo->prepare(
                    'INSERT INTO personnel_profiles
                        (prefix, fullname, position, department, email, phone, image, display_order, status)
                    VALUES (:prefix, :fullname, :position, :department, :email, :phone, :image, :display_order, :status)'
                );
                $statement->execute($values);
            }

            if ($id === null) {
                $profileId = (int) $pdo->lastInsertId();
            }
            if ($removeImageIds) {
                $deleteImage = $pdo->prepare(
                    'DELETE FROM personnel_profile_images
                    WHERE id = :image_id AND personnel_profile_id = :profile_id'
                );
                foreach ($removeImageIds as $imageId) {
                    $deleteImage->execute(['image_id' => $imageId, 'profile_id' => $profileId]);
                }
            }
            if ($newFiles) {
                $insertImage = $pdo->prepare(
                    'INSERT INTO personnel_profile_images (personnel_profile_id, image_path, display_order)
                    VALUES (:profile_id, :image_path, :display_order)'
                );
                $nextImageOrder = $existingImages
                    ? max(array_map(static fn(array $image): int => (int) $image['display_order'], $existingImages)) + 1
                    : 0;
                foreach ($newFiles as $index => $newFile) {
                    $insertImage->execute([
                        'profile_id' => $profileId,
                        'image_path' => $newFile['path'],
                        'display_order' => $nextImageOrder + $index,
                    ]);
                }
            }
            $pdo->commit();

            foreach ($existingImages as $oldImage) {
                if (in_array((int) $oldImage['id'], $removeImageIds, true)) {
                    remove_managed_image_file((string) $oldImage['image_path'], 'img/personnel', 'personnel_');
                }
            }
            $oldImage = (string) ($existing['image'] ?? '');
            if ($removeImage && $oldImage !== '') {
                $legacyPrefix = str_starts_with(basename($oldImage), 'personnel_') ? 'personnel_' : '';
                remove_managed_image_file($oldImage, 'img/personnel', $legacyPrefix);
            }
            $_SESSION['personnel_directory_message'] = $id === null
                ? 'เพิ่มข้อมูลบุคลากรเรียบร้อยแล้ว'
                : 'อัปเดตข้อมูลบุคลากรเรียบร้อยแล้ว';
            header('Location: personnel_directory.php');
            exit;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            foreach ($newFiles as $newFile) {
                if (is_file($newFile['absolute_path']) && !@unlink($newFile['absolute_path'])) {
                    error_log('Unable to clean up failed profile image upload: ' . $newFile['absolute_path']);
                }
            }
            error_log('Personnel profile save failed: ' . $exception->getMessage());
            $error = $exception instanceof ImageUploadException
                ? $exception->getMessage()
                : 'บันทึกข้อมูลไม่สำเร็จ กรุณาตรวจสอบข้อมูลแล้วลองใหม่';
        }
    }
}

$isEditing = $id !== null;
$departmentTags = [
    'คณะ / วิทยาลัย' => [
        ['🎓', 'คณะครุศาสตร์'],
        ['🔬', 'คณะวิทยาศาสตร์และเทคโนโลยี'],
        ['🌏', 'คณะมนุษยศาสตร์และสังคมศาสตร์'],
        ['📊', 'คณะบริหารธุรกิจและการบัญชี'],
        ['⚖️', 'คณะนิติรัฐศาสตร์'],
        ['🩺', 'คณะพยาบาลศาสตร์'],
        ['💻', 'คณะเทคโนโลยีสารสนเทศ'],
    ],
    'สำนักงาน / สำนัก / กอง' => [
        ['🏛️', 'สำนักงานอธิการบดี'],
        ['📋', 'กองกลาง'],
        ['👥', 'กองบริหารงานบุคคล'],
        ['💰', 'กองคลัง'],
        ['🧭', 'กองนโยบายและแผน'],
        ['📚', 'สำนักส่งเสริมวิชาการและงานทะเบียน'],
        ['🖥️', 'สำนักวิทยบริการและเทคโนโลยีสารสนเทศ'],
        ['🧪', 'สถาบันวิจัยและพัฒนา'],
    ],
    'ศูนย์ / หน่วยงานอื่น' => [
        ['📍', 'ศูนย์ / โครงการ'],
        ['🏢', 'หน่วยงานอื่น'],
    ],
];
$positionTags = [
    'ผู้บริหาร' => [
        ['👑', 'อธิการบดี'],
        ['🏛️', 'รองอธิการบดี'],
        ['⭐', 'ผู้ช่วยอธิการบดี'],
        ['🎓', 'คณบดี'],
        ['📘', 'รองคณบดี'],
        ['🏢', 'ผู้อำนวยการ'],
        ['📋', 'รองผู้อำนวยการ'],
        ['🗂️', 'ผู้อำนวยการกอง'],
    ],
    'สายวิชาการ' => [
        ['🏅', 'ศาสตราจารย์'],
        ['🥈', 'รองศาสตราจารย์'],
        ['🥉', 'ผู้ช่วยศาสตราจารย์'],
        ['👩‍🏫', 'อาจารย์'],
        ['🧑‍🏫', 'อาจารย์พิเศษ'],
        ['🏫', 'ครู / อาจารย์โรงเรียนสาธิต'],
    ],
    'สายสนับสนุน' => [
        ['📚', 'นักวิชาการศึกษา'],
        ['💻', 'นักวิชาการคอมพิวเตอร์'],
        ['💵', 'นักวิชาการเงินและบัญชี'],
        ['🗃️', 'เจ้าหน้าที่บริหารงานทั่วไป'],
        ['🧑‍💼', 'นักทรัพยากรบุคคล'],
        ['🔧', 'เจ้าหน้าที่'],
        ['🏢', 'พนักงานมหาวิทยาลัย'],
        ['📝', 'พนักงานราชการ'],
    ],
];
$rankOptions = [
    ['abbr' => 'อ.', 'title' => 'อาจารย์', 'level' => 'Entry-Level / Field Officer · GS-9 ถึง GS-11', 'example' => 'เจ้าหน้าที่บรรจุใหม่ / นักวิเคราะห์ฝึกหัด'],
    ['abbr' => 'ผศ.', 'title' => 'ผู้ช่วยศาสตราจารย์', 'level' => 'Mid-Level Management · GS-12 ถึง GS-13', 'example' => 'Senior Agent / Senior Analyst'],
    ['abbr' => 'รศ.', 'title' => 'รองศาสตราจารย์', 'level' => 'Senior Specialist / Management · GS-14 ถึง GS-15', 'example' => 'Chief of Station / Chief Analyst'],
    ['abbr' => 'ศ.', 'title' => 'ศาสตราจารย์', 'level' => 'Executive / Senior Intelligence Service', 'example' => 'Director / Deputy Director'],
];
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $isEditing ? 'แก้ไขข้อมูลบุคลากร' : 'เพิ่มบุคลากร' ?></title>
    <style>
        * { box-sizing: border-box; }
        body { min-height: 100vh; margin: 0; display: grid; grid-template-columns: 250px minmax(0, 1fr); background: #f4f7fa; color: #17324d; font-family: Arial, sans-serif; }
        .side { position: sticky; top: 0; height: 100vh; padding: 1.5rem 1.25rem; background: #17324d; color: #fff; }
        .brand { display: flex; align-items: center; gap: .75rem; color: #fff; font-weight: 700; text-decoration: none; }
        .brand-mark { width: 38px; height: 38px; display: grid; place-items: center; border: 1px solid rgba(255,255,255,.45); border-left: 3px solid #c8a35c; font-family: Georgia,serif; font-size: 1.35rem; }
        nav { display: grid; gap: .35rem; margin-top: 2rem; }
        nav a { padding: .65rem .75rem; border-radius: 4px; color: rgba(255,255,255,.82); text-decoration: none; }
        nav a:hover, nav a[aria-current="page"] { background: rgba(255,255,255,.12); color: #fff; }
        .user { position: absolute; right: 1.25rem; bottom: 1.5rem; left: 1.25rem; display: grid; gap: .25rem; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,.2); overflow-wrap: anywhere; }
        .user small { color: rgba(255,255,255,.65); }
        .user a { margin-top: .35rem; color: #fff; }
        main { width: min(850px, calc(100% - 4rem)); min-width: 0; margin: 0 auto; padding: 2rem 0; }
        .panel { padding: 1.25rem; border: 1px solid #d7e0ea; border-radius: 6px; background: #fff; }
        h1 { margin: 0; font-size: 1.5rem; }
        label { display: block; margin: .8rem 0 .3rem; font-weight: 700; }
        input, select { width: 100%; min-height: 42px; padding: .6rem; border: 1px solid #bdc9d6; border-radius: 4px; font: inherit; }
        input:focus-visible, select:focus-visible, button:focus-visible, a:focus-visible { outline: 3px solid #6c9dce; outline-offset: 2px; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: 0 1rem; }
        .wide { grid-column: 1 / -1; }
        .hint, .muted { color: #66788a; font-size: .88rem; }
        .image-previews { display: flex; flex-wrap: wrap; gap: .75rem; margin-top: .75rem; }
        .image-preview { width: 125px; display: grid; gap: .35rem; font-size: .82rem; }
        .image-preview img { width: 125px; height: 90px; border-radius: 4px; object-fit: cover; }
        .image-preview label { display: flex; align-items: center; gap: .3rem; margin: 0; font-weight: 400; }
        .tag-picker { margin-top: .55rem; }
        .tag-picker summary { width: fit-content; padding: .35rem .55rem; border: 1px solid #d7e0ea; border-radius: 4px; color: #1e4a7a; cursor: pointer; font-size: .85rem; font-weight: 600; }
        .tag-picker summary:hover, .tag-picker[open] summary { background: #edf4fa; }
        .tag-picker[open] summary { margin-bottom: .6rem; }
        .tag-groups { display: grid; gap: .65rem; padding: .75rem; border: 1px solid #d7e0ea; border-radius: 4px; background: #fbfcfe; }
        .tag-group-title { margin: 0 0 .4rem; color: #66788a; font-size: .8rem; font-weight: 700; }
        .tag-options { display: flex; flex-wrap: wrap; gap: .4rem; }
        .tag-option { display: inline-flex; align-items: center; gap: .35rem; min-height: 34px; padding: .35rem .55rem; border: 1px solid #d7e0ea; border-radius: 4px; background: #fff; color: #29445c; font: inherit; font-size: .84rem; cursor: pointer; }
        .tag-option:hover { border-color: #86a8c7; background: #edf4fa; }
        .tag-option:active { background: #dfeaf4; }
        .rank-picker { margin-top: .65rem; }
        .rank-picker summary { width: fit-content; padding: .4rem .65rem; border: 1px solid #bdc9d6; border-radius: 4px; background: #fff; color: #1e4a7a; cursor: pointer; font-size: .85rem; font-weight: 700; }
        .rank-picker summary:hover, .rank-picker[open] summary { background: #edf4fa; }
        .rank-controls { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: .5rem; margin-top: .6rem; }
        .rank-controls select { min-width: 0; }
        .rank-add { display: inline-flex; align-items: center; justify-content: center; gap: .4rem; margin: 0; border-color: #c8a35c; background: #fff8e6; color: #715411; white-space: nowrap; }
        .rank-add:hover { border-color: #9a7420; background: #fff1c9; }
        .rank-note { margin: .55rem 0 0; color: #66788a; font-size: .8rem; }
        .preview { display: flex; align-items: center; gap: 1rem; margin-top: .75rem; }
        .preview img { width: 84px; height: 84px; border-radius: 50%; object-fit: cover; }
        .error { margin: 1rem 0; padding: .75rem; border-left: 3px solid #a42121; background: #fff0f0; color: #a42121; }
        .actions { display: flex; align-items: center; gap: .6rem; margin-top: 1.2rem; }
        button, .button { display: inline-block; min-height: 40px; padding: .55rem .8rem; border: 1px solid #1e4a7a; border-radius: 4px; background: #1e4a7a; color: #fff; font: inherit; text-decoration: none; cursor: pointer; }
        .secondary { background: #fff; color: #1e4a7a; }
        .brand-mark { overflow: hidden; padding: 2px; background: #fff; }
        .brand-mark img { width: 100%; height: 100%; object-fit: contain; }
        main { width: min(1040px, calc(100% - 4rem)); }
        .document.panel { padding: 0; overflow: hidden; border-radius: 8px; box-shadow: 0 12px 32px rgba(23, 50, 77, .08); }
        .document-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 1.5rem; padding: 1.5rem 1.75rem; border-bottom: 1px solid #d7e0ea; background: #fbfcfe; }
        .document-kicker, .section-index { color: #66788a; font-family: ui-monospace, Consolas, monospace; font-size: .76rem; font-weight: 700; letter-spacing: .04em; }
        .document-header h1 { margin-top: .35rem; }
        .document-intro { margin: .4rem 0 0; color: #66788a; }
        .document-number { flex: 0 0 auto; padding: .65rem .8rem; border: 1px solid #d7e0ea; border-radius: 4px; background: #fff; text-align: right; }
        .document-number span, .document-number strong { display: block; }
        .document-number span { color: #66788a; font-size: .75rem; }
        .document-number strong { margin-top: .15rem; color: #1e4a7a; font-family: ui-monospace, Consolas, monospace; }
        .metadata-section, .form-section { padding: 1.35rem 1.75rem; border-bottom: 1px solid #e4eaf0; }
        .document-section-heading { display: flex; align-items: flex-start; gap: .8rem; margin-bottom: 1rem; }
        .section-index { padding: .22rem .35rem; border-radius: 3px; background: #edf4fa; color: #1e4a7a; }
        .document-section-heading h2 { margin: 0; font-size: 1.05rem; }
        .document-section-heading p { margin: .2rem 0 0; color: #66788a; font-size: .88rem; }
        .metadata-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .75rem; }
        .metadata-card { min-width: 0; padding: .7rem .75rem; border: 1px solid #d7e0ea; border-radius: 4px; background: #fbfcfe; }
        .metadata-card span, .metadata-card strong { display: block; overflow-wrap: anywhere; }
        .metadata-card span { color: #66788a; font-size: .78rem; }
        .metadata-card strong { margin-top: .3rem; font-size: .9rem; }
        .metadata-field label { margin-top: 0; font-size: .85rem; }
        .document-footer { display: flex; align-items: flex-end; justify-content: space-between; gap: 1rem; padding: 1.25rem 1.75rem; background: #fbfcfe; }
        .signoff { display: grid; gap: .2rem; min-width: 0; }
        .signoff span, .signoff small { color: #66788a; font-size: .8rem; }
        .signoff strong { overflow-wrap: anywhere; }
        .document-footer .actions { flex-wrap: wrap; justify-content: flex-end; margin: 0; }
        body { background: #f1f3ef; color: #202a26; font-family: "Segoe UI", Tahoma, sans-serif; }
        .side { background: #1d2925; border-right: 3px solid #a5323d; }
        .brand-mark { width: 42px; height: 42px; border-left-color: #bd4650; }
        .document.panel { border-color: #d5ddd7; border-top: 5px solid #a5323d; box-shadow: 0 16px 42px rgba(22, 35, 29, .1); }
        .document-header { background: #fff; }
        .document-kicker { color: #a5323d; }
        .document-number { border-color: #d5ddd7; background: #f7f8f5; }
        .document-number strong { color: #a5323d; }
        .metadata-section { background: #f7f8f5; }
        .section-index { background: #f7e9e9; color: #8c2934; }
        .metadata-card { border-color: #d5ddd7; background: #fff; }
        .metadata-card strong, .document-section-heading h2 { color: #202a26; }
        input:focus-visible, select:focus-visible { border-color: #a5323d; outline-color: #bd4650; }
        .tag-picker summary, .rank-picker summary { border-color: #d5ddd7; color: #49574f; }
        .tag-option { border-color: #d5ddd7; color: #33443a; }
        .tag-option:hover, .tag-option:active, .tag-picker[open] summary, .rank-picker[open] summary { border-color: #a5323d; background: #f7e9e9; }
        .rank-add { border-color: #c69b5a; background: #fbf4e7; color: #715411; }
        .document-footer { border-top: 1px solid #d5ddd7; background: #f7f8f5; }
        .signoff { padding-left: .75rem; border-left: 2px solid #a5323d; }
        .document-footer button, .document-footer .button:not(.secondary) { border-color: #a5323d; background: #a5323d; }
        .document-footer button:hover, .document-footer .button:not(.secondary):hover { background: #842933; }
        .document-footer .secondary { border-color: #c9d2cb; background: #fff; color: #33443a; }
        @media (max-width: 760px) { body { grid-template-columns: 1fr; } .side { position: static; height: auto; padding: 1rem; } nav { display: flex; flex-wrap: wrap; margin-top: .8rem; } .user { position: static; margin-top: .8rem; padding-top: .7rem; } main { width: min(100% - 2rem, 850px); padding: 1.2rem 0; } }
        @media (max-width: 760px) { .document-header, .metadata-section, .form-section, .document-footer { padding-right: 1rem; padding-left: 1rem; } .document-header { flex-direction: column; } .document-number { text-align: left; } .metadata-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .document-footer { align-items: stretch; flex-direction: column; } .document-footer .actions { justify-content: flex-start; } }
        @media (max-width: 520px) { .grid, .metadata-grid { grid-template-columns: 1fr; } .wide { grid-column: auto; } .rank-controls { grid-template-columns: 1fr; } }
    </style>
    <link rel="stylesheet" href="../admin.css">
</head>
<body>
<aside class="side">
    <a class="brand" href="../../index.html"><span class="brand-mark" aria-hidden="true"><img src="../../img/images.jpg" alt=""></span><span>ระบบจัดการเว็บไซต์</span></a>
    <?php render_admin_nav('personnel', '../'); ?>
    <div class="user"><small>ผู้ดูแลระบบ</small><strong><?= escape((string) $_SESSION['admin_username']) ?></strong><a href="../logout.php">ออกจากระบบ</a></div>
</aside>
<main>
    <section class="panel document">
        <header class="document-header">
            <div>
                <span class="document-kicker">HUMAN RESOURCES / PERSONNEL RECORD</span>
                <h1><?= $isEditing ? 'แก้ไขข้อมูลบุคลากร' : 'เพิ่มบุคลากร' ?></h1>
                <p class="document-intro">ทะเบียนประวัติบุคลากร · เอกสารสำหรับผู้ดูแลระบบ</p>
            </div>
            <div class="document-number"><span>รหัสระเบียน</span><strong><?= $id !== null ? 'PF-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT) : 'PF-NEW' ?></strong></div>
        </header>
        <?php if ($error !== ''): ?><p class="error" role="alert"><?= escape($error) ?></p><?php endif; ?>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
            <?php if ($isEditing): ?><input type="hidden" name="id" value="<?= (int) $id ?>"><?php endif; ?>

            <section class="metadata-section" aria-labelledby="metadata-heading">
                <div class="document-section-heading">
                    <span class="section-index">01 / META</span>
                    <div><h2 id="metadata-heading">ข้อมูลกำกับเอกสาร</h2><p>สถานะการเผยแพร่และลำดับการแสดงผล</p></div>
                </div>
                <div class="metadata-grid">
                    <div class="metadata-card"><span>วันที่จัดทำ</span><strong><?= !empty($existing['created_at']) ? escape(date('d/m/Y H:i', strtotime((string) $existing['created_at']))) : 'กำหนดเมื่อบันทึก' ?></strong></div>
                    <div class="metadata-card"><span>ปรับปรุงล่าสุด</span><strong><?= !empty($existing['updated_at']) ? escape(date('d/m/Y H:i', strtotime((string) $existing['updated_at']))) : 'ยังไม่มีประวัติ' ?></strong></div>
                    <div class="metadata-field"><label for="display_order">ลำดับแสดงผล</label><input id="display_order" name="display_order" type="number" min="0" max="1000000" value="<?= escape($form['display_order']) ?>"></div>
                    <div class="metadata-field"><label for="status">สถานะ</label><select id="status" name="status"><option value="active" <?= $form['status'] === 'active' ? 'selected' : '' ?>>แสดงบนเว็บไซต์</option><option value="inactive" <?= $form['status'] === 'inactive' ? 'selected' : '' ?>>ซ่อนจากเว็บไซต์</option></select></div>
                </div>
            </section>

            <section class="form-section" aria-labelledby="profile-heading">
                <div class="document-section-heading">
                    <span class="section-index">02 / PROFILE</span>
                    <div><h2 id="profile-heading">ประวัติและการปฏิบัติงาน</h2><p>ข้อมูลระบุตัวบุคคล ตำแหน่ง และหน่วยงาน</p></div>
                </div>
            <div class="grid">
                <div><label for="prefix">คำนำหน้า</label><input id="prefix" name="prefix" maxlength="50" value="<?= escape($form['prefix']) ?>"></div>
                <div><label for="fullname">ชื่อ-นามสกุล</label><input id="fullname" name="fullname" maxlength="255" required value="<?= escape($form['fullname']) ?>"></div>
                <div>
                    <label for="position">ตำแหน่ง</label>
                    <input id="position" name="position" maxlength="150" required value="<?= escape($form['position']) ?>">
                    <details class="tag-picker" data-tag-picker data-target="position">
                        <summary>เลือกแท็กตำแหน่ง</summary>
                        <div class="tag-groups">
                            <?php foreach ($positionTags as $group => $tags): ?>
                                <section><h2 class="tag-group-title"><?= escape($group) ?></h2><div class="tag-options">
                                    <?php foreach ($tags as [$icon, $label]): ?>
                                        <button class="tag-option" type="button" data-tag-value="<?= escape($label) ?>"><span aria-hidden="true"><?= escape($icon) ?></span><?= escape($label) ?></button>
                                    <?php endforeach; ?>
                                </div></section>
                            <?php endforeach; ?>
                        </div>
                    </details>
                    <details class="rank-picker" data-rank-picker data-target="position">
                        <summary>ระดับยศเทียบเคียง</summary>
                        <div class="rank-controls">
                            <select id="rank-select" aria-label="เลือกระดับยศเทียบเคียง">
                                <option value="">เลือกระดับยศ</option>
                            <?php foreach ($rankOptions as $rank): ?>
                                <option value="<?= escape($rank['title']) ?>" data-level="<?= escape($rank['level']) ?>" data-example="<?= escape($rank['example']) ?>"><?= escape($rank['abbr'] . ' ' . $rank['title'] . ' — ' . $rank['level']) ?></option>
                            <?php endforeach; ?>
                            </select>
                            <button class="rank-add" type="button" id="add-rank"><span aria-hidden="true">★</span>เพิ่มยศ</button>
                        </div>
                        <p class="rank-note" id="rank-description" aria-live="polite">เลือกระดับเพื่อดูคำอธิบายเทียบเคียง</p>
                        <p class="rank-note">การเทียบ GS/CIA เป็นตัวอย่างประกอบเท่านั้น ไม่ใช่ยศจริงหรือการจัดระดับอย่างเป็นทางการของ CIA ซึ่งระดับ GS ขึ้นกับสายงานและหน้าที่</p>
                    </details>
                </div>
                <div>
                    <label for="department">หน่วยงาน / คณะ</label>
                    <input id="department" name="department" maxlength="150" required value="<?= escape($form['department']) ?>">
                    <details class="tag-picker" data-tag-picker data-target="department">
                        <summary>เลือกแท็กหน่วยงาน / คณะ</summary>
                        <div class="tag-groups">
                            <?php foreach ($departmentTags as $group => $tags): ?>
                                <section><h2 class="tag-group-title"><?= escape($group) ?></h2><div class="tag-options">
                                    <?php foreach ($tags as [$icon, $label]): ?>
                                        <button class="tag-option" type="button" data-tag-value="<?= escape($label) ?>"><span aria-hidden="true"><?= escape($icon) ?></span><?= escape($label) ?></button>
                                    <?php endforeach; ?>
                                </div></section>
                            <?php endforeach; ?>
                        </div>
                    </details>
                    <p class="hint">เลือกแท็กแนะนำหรือพิมพ์หน่วยงานอื่นเองได้</p>
                </div>
                <div><label for="email">อีเมล</label><input id="email" name="email" type="email" maxlength="190" value="<?= escape($form['email']) ?>"></div>
                <div><label for="phone">เบอร์โทรศัพท์</label><input id="phone" name="phone" maxlength="30" value="<?= escape($form['phone']) ?>"></div>
                <div class="wide"><label for="image_upload">รูปบุคลากร</label><input id="image_upload" name="image_upload[]" type="file" accept="image/jpeg,image/png,image/gif,image/webp" multiple><p class="hint">เลือกเพิ่มได้หลายรูป รองรับ JPG, PNG, GIF, WebP ขนาดไม่เกิน 5 MB ต่อรูป และไม่เกิน 20 รูปต่อครั้ง</p>
                    <?php if (($existing['image'] ?? '') !== '' || $existingImages): ?><div class="image-previews">
                        <?php if (($existing['image'] ?? '') !== ''): ?><div class="image-preview"><img src="../../<?= escape($existing['image']) ?>" alt="รูปบุคลากรเดิม"><label><input type="checkbox" name="remove_image" value="1" style="width:auto;min-height:0"> ลบรูปนี้</label></div><?php endif; ?>
                        <?php foreach ($existingImages as $existingImage): ?><div class="image-preview"><img src="../../<?= escape($existingImage['image_path']) ?>" alt="รูปบุคลากรเดิม"><label><input type="checkbox" name="remove_images[]" value="<?= (int) $existingImage['id'] ?>" style="width:auto;min-height:0"> ลบรูปนี้</label></div><?php endforeach; ?>
                    </div><?php endif; ?>
                </div>
            </div>
            </section>

            <footer class="document-footer">
                <div class="signoff"><span>ผู้จัดทำรายการ</span><strong><?= escape((string) $_SESSION['admin_username']) ?></strong><small>เจ้าหน้าที่ผู้ดูแลข้อมูลบุคลากร</small></div>
                <div class="actions"><button type="submit"><?= $isEditing ? 'บันทึกการแก้ไข' : 'เพิ่มบุคลากร' ?></button><a class="button secondary" href="personnel_directory.php">ยกเลิก</a></div>
            </footer>
        </form>
    </section>
</main>
<script>
document.querySelectorAll('[data-rank-picker]').forEach((picker) => {
    const input = document.getElementById(picker.dataset.target);
    const rankSelect = picker.querySelector('#rank-select');
    const rankDescription = picker.querySelector('#rank-description');
    rankSelect.addEventListener('change', () => {
        const option = rankSelect.selectedOptions[0];
        rankDescription.textContent = option.value
            ? `${option.dataset.level}: ${option.dataset.example}`
            : 'เลือกระดับเพื่อดูคำอธิบายเทียบเคียง';
    });
    picker.querySelector('#add-rank').addEventListener('click', () => {
        if (!rankSelect.value) {
            rankDescription.textContent = 'กรุณาเลือกระดับยศก่อน';
            rankSelect.focus();
            return;
        }
        input.value = rankSelect.value;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        picker.open = false;
        input.focus();
    });
});
document.querySelectorAll('[data-tag-picker]').forEach((picker) => {
    const input = document.getElementById(picker.dataset.target);
    picker.querySelectorAll('[data-tag-value]').forEach((button) => {
        button.addEventListener('click', () => {
            input.value = button.dataset.tagValue;
            input.dispatchEvent(new Event('input', { bubbles: true }));
            picker.open = false;
            input.focus();
        });
    });
});
</script>
</body>
</html>
