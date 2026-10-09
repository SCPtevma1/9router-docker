<?php
declare(strict_types=1);
require_once __DIR__ . '/../check_session.php';
header('Location: personnel_profile_form.php', true, 302);
exit;
$error = '';
$form = [
    'prefix' => '',
    'fullname' => '',
    'position' => '',
    'department' => '',
    'email' => '',
    'phone' => '',
    'image' => '',
    'status' => 'active',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $form = [
        'prefix' => trim((string) ($_POST['prefix'] ?? '')),
        'fullname' => trim((string) ($_POST['fullname'] ?? '')),
        'position' => trim((string) ($_POST['position'] ?? '')),
        'department' => trim((string) ($_POST['department'] ?? '')),
        'email' => trim((string) ($_POST['email'] ?? '')),
        'phone' => trim((string) ($_POST['phone'] ?? '')),
        'image' => trim((string) ($_POST['image'] ?? '')),
        'status' => ($_POST['status'] ?? '') === 'inactive' ? 'inactive' : 'active',
    ];

    $upload = $_FILES['image_upload'] ?? null;
    $hasImageUpload = is_array($upload)
        && (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    $uploadError = '';
    $uploadExtension = null;

    if ($hasImageUpload) {
        $uploadCode = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        $temporaryPath = (string) ($upload['tmp_name'] ?? '');
        $allowedImageTypes = [
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_GIF => 'gif',
            IMAGETYPE_WEBP => 'webp',
        ];

        if ($uploadCode !== UPLOAD_ERR_OK) {
            $uploadError = 'อัปโหลดรูปภาพไม่สำเร็จ กรุณาลองใหม่';
        } elseif (!is_uploaded_file($temporaryPath)) {
            $uploadError = 'ไฟล์รูปภาพไม่ถูกต้อง';
        } elseif ((int) ($upload['size'] ?? 0) > 5 * 1024 * 1024) {
            $uploadError = 'รูปภาพต้องมีขนาดไม่เกิน 5 MB';
        } else {
            $imageInfo = @getimagesize($temporaryPath);
            $uploadExtension = $imageInfo === false
                ? null
                : ($allowedImageTypes[$imageInfo[2]] ?? null);

            if ($uploadExtension === null) {
                $uploadError = 'ไฟล์ต้องเป็นรูป JPG, PNG, WebP หรือ GIF';
            }
        }
    }

    $isLocalImage = str_starts_with($form['image'], '/')
        && !str_starts_with($form['image'], '//');
    $isHttpsImage = filter_var($form['image'], FILTER_VALIDATE_URL) !== false
        && strtolower((string) parse_url($form['image'], PHP_URL_SCHEME)) === 'https';

    if ($form['fullname'] === '' || $form['position'] === '' || $form['department'] === '') {
        $error = 'กรุณากรอกชื่อ ตำแหน่ง และหน่วยงานให้ครบถ้วน';
    } elseif ($form['email'] !== '' && !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'รูปแบบอีเมลไม่ถูกต้อง';
    } elseif ($uploadError !== '') {
        $error = $uploadError;
    } elseif (!$hasImageUpload && $form['image'] !== '' && !$isLocalImage && !$isHttpsImage) {
        $error = 'รูปภาพต้องเป็น path ภายในเว็บไซต์หรือ URL ที่ขึ้นต้นด้วย HTTPS';
    } elseif (!$hasImageUpload && strlen($form['image']) > 255) {
        $error = 'ที่อยู่รูปภาพต้องมีความยาวไม่เกิน 255 ตัวอักษร';
    } else {
        $storedImagePath = null;

        try {
            if ($hasImageUpload) {
                $uploadDirectory = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'personnel';
                if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
                    throw new RuntimeException('Unable to create personnel image directory');
                }

                $fileName = bin2hex(random_bytes(16)) . '.' . $uploadExtension;
                $storedImagePath = $uploadDirectory . DIRECTORY_SEPARATOR . $fileName;
                if (!move_uploaded_file((string) $upload['tmp_name'], $storedImagePath)) {
                    throw new RuntimeException('Unable to store personnel image');
                }

                $webRoot = rtrim(dirname((string) ($_SERVER['SCRIPT_NAME'] ?? ''), 3), '/');
                $form['image'] = ($webRoot === '.' ? '' : $webRoot) . '/img/personnel/' . $fileName;
                if (strlen($form['image']) > 255) {
                    throw new RuntimeException('Personnel image path is too long');
                }
            }

            $statement = db()->prepare(
                'INSERT INTO personnel (
                    prefix, fullname, position, department, email, phone, image, status
                ) VALUES (
                    :prefix, :fullname, :position, :department, :email, :phone, :image, :status
                )'
            );
            $statement->execute([
                'prefix' => $form['prefix'] ?: null,
                'fullname' => $form['fullname'],
                'position' => $form['position'] ?: null,
                'department' => $form['department'] ?: null,
                'email' => $form['email'] ?: null,
                'phone' => $form['phone'] ?: null,
                'image' => $form['image'] ?: null,
                'status' => $form['status'],
            ]);

            header('Location: personnel_list.php');
            exit;
        } catch (Throwable $exception) {
            if ($storedImagePath !== null && is_file($storedImagePath)) {
                unlink($storedImagePath);
            }
            error_log('Personnel insert failed: ' . $exception->getMessage());
            $error = 'บันทึกข้อมูลไม่สำเร็จ กรุณาตรวจสอบฐานข้อมูลแล้วลองอีกครั้ง';
        }
    }
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>เพิ่มบุคลากร</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #f4f7fa; color: #17324d; font-family: Arial, sans-serif; }
        .top { padding: 1rem 0; background: #17324d; color: #fff; }
        .wrap { width: min(1100px, 92vw); margin: 0 auto; }
        .top .wrap, .top nav, .actions { display: flex; align-items: center; gap: .75rem; }
        .top .wrap { justify-content: space-between; }
        .top a { color: #fff; }
        .form-panel { width: min(760px, 100%); margin: 2rem auto; padding: 1.5rem; border: 1px solid #d7e0ea; border-radius: 8px; background: #fff; }
        h1 { margin-top: 0; font-size: 1.5rem; }
        .intro, .hint { color: #66788a; }
        .form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0 1rem; }
        .field { min-width: 0; }
        .field-wide { grid-column: 1 / -1; }
        .tag-picker { min-width: 0; margin: .65rem 0 0; padding: 0; border: 0; }
        .tag-picker legend { margin-bottom: .4rem; color: #66788a; font-size: .85rem; }
        .tag-options { display: flex; flex-wrap: wrap; gap: .4rem; }
        .tag-option { margin: 0; padding: .4rem .65rem; border: 1px solid #bdc9d6; border-radius: 999px; background: #fff; color: #1e4a7a; font-size: .85rem; cursor: pointer; }
        .tag-option:hover, .tag-option[aria-pressed="true"] { border-color: #1e4a7a; background: #e7eef6; }
        label { display: block; margin: .8rem 0 .3rem; font-weight: 700; }
        input, select { width: 100%; min-height: 44px; padding: .65rem; border: 1px solid #bdc9d6; border-radius: 5px; font: inherit; }
        input:focus-visible, select:focus-visible, a:focus-visible, button:focus-visible { outline: 3px solid #6c9dce; outline-offset: 2px; }
        .hint { margin: .35rem 0 0; font-size: .85rem; }
        .error { padding: .7rem; border-radius: 5px; background: #fff0f0; color: #a42121; }
        button, .button { display: inline-block; margin-top: 1rem; padding: .65rem .9rem; border: 0; border-radius: 5px; background: #1e4a7a; color: #fff; text-decoration: none; cursor: pointer; font: inherit; }
        .secondary { border: 1px solid #bdc9d6; background: #fff; color: #1e4a7a; }
        @media (max-width: 600px) {
            .top .wrap { align-items: flex-start; flex-direction: column; }
            .form-panel { margin: 1rem auto; padding: 1rem; }
            .form-grid { grid-template-columns: 1fr; }
            .field-wide { grid-column: auto; }
        }
    </style>
</head>
<body>
    <header class="top">
        <div class="wrap">
            <strong>จัดการบุคลากร</strong>
            <nav aria-label="เมนูบุคลากร">
                <a href="personnel_list.php">รายชื่อบุคลากร</a>
                <a href="../dashboard.php">แดชบอร์ด</a>
            </nav>
        </div>
    </header>

    <main class="wrap">
        <section class="form-panel">
            <h1>เพิ่มอาจารย์ / บุคลากร</h1>
            <p class="intro">กรอกข้อมูลผู้สอนหรือเจ้าหน้าที่เพื่อเพิ่มลงในรายชื่อบุคลากร</p>

            <?php if ($error !== ''): ?>
                <p class="error" role="alert"><?= escape($error) ?></p>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">

                <div class="form-grid">
                    <div class="field">
                        <label for="prefix">คำนำหน้า</label>
                        <input id="prefix" name="prefix" maxlength="50" placeholder="เช่น อาจารย์, ดร., ผศ.ดร." value="<?= escape($form['prefix']) ?>">
                    </div>
                    <div class="field">
                        <label for="fullname">ชื่อ-นามสกุล</label>
                        <input id="fullname" name="fullname" maxlength="255" required autocomplete="name" value="<?= escape($form['fullname']) ?>">
                    </div>
                    <div class="field">
                        <label for="position">ตำแหน่ง</label>
                        <input id="position" name="position" maxlength="255" required value="<?= escape($form['position']) ?>">
                        <fieldset class="tag-picker" data-tag-target="position">
                            <legend>ตำแหน่งที่ใช้บ่อย</legend>
                            <div class="tag-options">
                                <button class="tag-option" type="button" data-tag-value="อาจารย์" aria-pressed="false">อาจารย์</button>
                                <button class="tag-option" type="button" data-tag-value="ครู" aria-pressed="false">ครู</button>
                                <button class="tag-option" type="button" data-tag-value="ผู้ช่วยศาสตราจารย์" aria-pressed="false">ผู้ช่วยศาสตราจารย์</button>
                                <button class="tag-option" type="button" data-tag-value="รองศาสตราจารย์" aria-pressed="false">รองศาสตราจารย์</button>
                                <button class="tag-option" type="button" data-tag-value="เจ้าหน้าที่" aria-pressed="false">เจ้าหน้าที่</button>
                                <button class="tag-option" type="button" data-tag-value="หัวหน้าภาควิชา" aria-pressed="false">หัวหน้าภาควิชา</button>
                            </div>
                        </fieldset>
                    </div>
                    <div class="field">
                        <label for="department">คณะ / หน่วยงาน</label>
                        <input id="department" name="department" maxlength="255" required value="<?= escape($form['department']) ?>">
                        <fieldset class="tag-picker" data-tag-target="department">
                            <legend>คณะ / หน่วยงานที่ใช้บ่อย</legend>
                            <div class="tag-options">
                                <button class="tag-option" type="button" data-tag-value="คณะครุศาสตร์" aria-pressed="false">คณะครุศาสตร์</button>
                                <button class="tag-option" type="button" data-tag-value="คณะวิทยาศาสตร์" aria-pressed="false">คณะวิทยาศาสตร์</button>
                                <button class="tag-option" type="button" data-tag-value="คณะมนุษยศาสตร์" aria-pressed="false">คณะมนุษยศาสตร์</button>
                                <button class="tag-option" type="button" data-tag-value="คณะบริหารธุรกิจ" aria-pressed="false">คณะบริหารธุรกิจ</button>
                                <button class="tag-option" type="button" data-tag-value="ฝ่ายวิชาการ" aria-pressed="false">ฝ่ายวิชาการ</button>
                                <button class="tag-option" type="button" data-tag-value="ฝ่ายบุคคล" aria-pressed="false">ฝ่ายบุคคล</button>
                                <button class="tag-option" type="button" data-tag-value="งานทะเบียน" aria-pressed="false">งานทะเบียน</button>
                                <button class="tag-option" type="button" data-tag-value="งานเทคโนโลยีสารสนเทศ" aria-pressed="false">งานเทคโนโลยีสารสนเทศ</button>
                            </div>
                        </fieldset>
                    </div>
                    <div class="field">
                        <label for="email">อีเมล</label>
                        <input id="email" name="email" type="email" maxlength="100" autocomplete="email" value="<?= escape($form['email']) ?>">
                    </div>
                    <div class="field">
                        <label for="phone">โทรศัพท์</label>
                        <input id="phone" name="phone" type="tel" maxlength="50" autocomplete="tel" value="<?= escape($form['phone']) ?>">
                    </div>
                    <div class="field field-wide">
                        <label for="image">ที่อยู่รูปภาพ</label>
                        <input id="image" name="image" maxlength="255" placeholder="เช่น /9router-docker/img/personnel/teacher.jpg" value="<?= escape($form['image']) ?>">
                        <label for="image_upload">หรือเลือกรูปจากเครื่อง</label>
                        <input id="image_upload" name="image_upload" type="file" accept="image/jpeg,image/png,image/webp,image/gif">
                        <p class="hint">เลือกรูป JPG, PNG, WebP หรือ GIF ขนาดไม่เกิน 5 MB (ไม่บังคับ) หากเลือกไฟล์ ระบบจะใช้ไฟล์นี้แทน URL/path ด้านบน</p>
                    </div>
                    <div class="field">
                        <label for="status">สถานะ</label>
                        <select id="status" name="status">
                            <option value="active" <?= $form['status'] === 'active' ? 'selected' : '' ?>>ปฏิบัติงาน</option>
                            <option value="inactive" <?= $form['status'] === 'inactive' ? 'selected' : '' ?>>ไม่ปฏิบัติงาน</option>
                        </select>
                    </div>
                </div>

                <div class="actions">
                    <button type="submit">บันทึกบุคลากร</button>
                    <a class="button secondary" href="personnel_list.php">ยกเลิก</a>
                </div>
            </form>
        </section>
    </main>
    <script>
        document.querySelectorAll('.tag-picker').forEach((group) => {
            const input = document.getElementById(group.dataset.tagTarget);
            const options = group.querySelectorAll('.tag-option');

            const updateSelection = () => {
                options.forEach((option) => {
                    option.setAttribute('aria-pressed', String(option.dataset.tagValue === input.value));
                });
            };

            options.forEach((option) => {
                option.addEventListener('click', () => {
                    input.value = option.dataset.tagValue;
                    updateSelection();
                    input.focus();
                });
            });

            input.addEventListener('input', updateSelection);
            updateSelection();
        });
    </script>
</body>
</html>