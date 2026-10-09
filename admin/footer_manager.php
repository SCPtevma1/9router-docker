<?php
declare(strict_types=1);

require_once __DIR__ . '/check_session.php';
require_once __DIR__ . '/admin_nav.php';
require_once __DIR__ . '/footer_common.php';
require_once __DIR__ . '/image_uploads.php';

$pdo = db();
ensure_footer_schema($pdo);

$notice = '';
$error = '';

$defaultSections = [
    ['title' => 'เกี่ยวกับมหาวิทยาลัย', 'title_en' => 'About the University', 'links' => [
        ['label' => 'ประวัติ', 'label_en' => 'History', 'url' => '../about.html'],
        ['label' => 'วิสัยทัศน์', 'label_en' => 'Vision', 'url' => '../about.html#vision'],
        ['label' => 'โครงสร้าง', 'label_en' => 'Organization', 'url' => '../about.html#structure'],
        ['label' => 'ผู้บริหาร', 'label_en' => 'Leadership', 'url' => '../about.html#leaders'],
        ['label' => 'แผนที่', 'label_en' => 'Campus map', 'url' => '../about.html#map'],
    ]],
    ['title' => 'หลักสูตร/การศึกษา', 'title_en' => 'Programs & Study', 'links' => [
        ['label' => 'ปริญญาตรี', 'label_en' => "Bachelor's degree", 'url' => '../programs.html'],
        ['label' => 'โท-เอก', 'label_en' => 'Graduate programs', 'url' => '../programs.html#graduate'],
        ['label' => 'ระยะสั้น', 'label_en' => 'Short courses', 'url' => '../programs.html#short'],
        ['label' => 'คณะ', 'label_en' => 'Faculties', 'url' => '../programs.html#faculties'],
        ['label' => 'ปฏิทิน', 'label_en' => 'Academic calendar', 'url' => '../programs.html#calendar'],
    ]],
    ['title' => 'การรับสมัคร', 'title_en' => 'Admissions', 'links' => [
        ['label' => 'TCAS', 'label_en' => 'TCAS', 'url' => '../admission.html'],
        ['label' => 'โควตา', 'label_en' => 'Quota', 'url' => '../admission.html#quota'],
        ['label' => 'ทุน', 'label_en' => 'Scholarships', 'url' => '../admission.html#scholar'],
        ['label' => 'คู่มือ', 'label_en' => 'Application guide', 'url' => '../admission.html#guide'],
    ]],
    ['title' => 'ข่าวสารและกิจกรรม', 'title_en' => 'News & Events', 'links' => [
        ['label' => 'ข่าวมหาวิทยาลัย', 'label_en' => 'University news', 'url' => '../news.html'],
        ['label' => 'ข่าวคณะ', 'label_en' => 'Faculty news', 'url' => '../news.html#faculty'],
        ['label' => 'กิจกรรม', 'label_en' => 'Events', 'url' => '../news.html#events'],
        ['label' => 'ประกาศ', 'label_en' => 'Announcements', 'url' => '../news.html#announce'],
    ]],
    ['title' => 'ระบบสารสนเทศ', 'title_en' => 'Information Systems', 'links' => [
        ['label' => 'ทะเบียน', 'label_en' => 'Registration', 'url' => '../intranet.html'],
        ['label' => 'LMS', 'label_en' => 'LMS', 'url' => '../intranet.html'],
        ['label' => 'E-Office', 'label_en' => 'E-Office', 'url' => '../intranet.html'],
        ['label' => 'เข้าสู่ระบบ', 'label_en' => 'Sign in', 'url' => '../login.html'],
    ]],
    ['title' => 'ติดต่อเรา', 'title_en' => 'Contact', 'links' => [
        ['label' => 'ติดต่อเจ้าหน้าที่', 'label_en' => 'Contact staff', 'url' => '../contact.html'],
        ['label' => 'แผนผังเว็บไซต์', 'label_en' => 'Sitemap', 'url' => '../sitemap.html'],
    ]],
];

$slugify = static function (string $value): string {
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    $value = strtolower($value);
    $value = preg_replace('/[\s_]+/', '-', $value);
    $value = preg_replace('/[^a-z0-9\-]/u', '', $value);
    $value = trim((string) $value, '-');
    return $value !== '' ? $value : 'section';
};

$normalizeUrl = static function (string $url): string {
    $trimmed = trim($url);
    if ($trimmed === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $trimmed) || preg_match('#^mailto:#i', $trimmed) || preg_match('#^/#', $trimmed) || preg_match('#^\.\.?/#', $trimmed)) {
        return $trimmed;
    }
    return '/' . ltrim($trimmed, '/');
};

$uploadedLogoPath = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'create_section') {
            $title = trim((string) ($_POST['title'] ?? ''));
            $titleEn = trim((string) ($_POST['title_en'] ?? ''));
            if ($title === '' || mb_strlen($title, 'UTF-8') > 120 || mb_strlen($titleEn, 'UTF-8') > 120) {
                $error = 'กรุณากรอกชื่อหัวข้อภาษาไทย และกรอกแต่ละภาษาไม่เกิน 120 ตัวอักษร';
            } else {
                $slug = $slugify($title);
                $exists = $pdo->prepare('SELECT id FROM footer_sections WHERE slug = :slug LIMIT 1');
                $exists->execute(['slug' => $slug]);
                if ($exists->fetchColumn() !== false) {
                    $base = $slug;
                    $counter = 1;
                    do {
                        $candidate = $base . '-' . $counter;
                        $check = $pdo->prepare('SELECT id FROM footer_sections WHERE slug = :slug LIMIT 1');
                        $check->execute(['slug' => $candidate]);
                        $counter++;
                    } while ($check->fetchColumn() !== false);
                    $slug = $candidate;
                }

                $insert = $pdo->prepare(
                    'INSERT INTO footer_sections (title, title_en, slug, sort_order)
                     VALUES (:title, :title_en, :slug, :sort_order)'
                );
                $insert->execute([
                    'title' => $title,
                    'title_en' => $titleEn,
                    'slug' => $slug,
                    'sort_order' => max(0, (int) ($_POST['sort_order'] ?? 0)),
                ]);
                $notice = 'สร้างหัวข้อ footer เรียบร้อยแล้ว';
            }
        } elseif ($action === 'create_link') {
            $sectionId = (int) ($_POST['section_id'] ?? 0);
            $label = trim((string) ($_POST['label'] ?? ''));
            $labelEn = trim((string) ($_POST['label_en'] ?? ''));
            $url = $normalizeUrl((string) ($_POST['url'] ?? ''));
            if ($sectionId <= 0 || $label === '' || $url === ''
                || mb_strlen($label, 'UTF-8') > 120 || mb_strlen($labelEn, 'UTF-8') > 120
                || strlen($url) > 255) {
                $error = 'กรุณากรอกหัวข้อภาษาไทยและ URL ให้ถูกต้อง (ข้อความไม่เกิน 120 ตัวอักษร)';
            } else {
                $sectionCheck = $pdo->prepare('SELECT id FROM footer_sections WHERE id = :id LIMIT 1');
                $sectionCheck->execute(['id' => $sectionId]);
                if ($sectionCheck->fetchColumn() === false) {
                    $error = 'ไม่พบหัวข้อที่เลือก';
                } else {
                    $insert = $pdo->prepare(
                        'INSERT INTO footer_links (section_id, label, label_en, url, sort_order)
                         VALUES (:section_id, :label, :label_en, :url, :sort_order)'
                    );
                    $insert->execute([
                        'section_id' => $sectionId,
                        'label' => $label,
                        'label_en' => $labelEn,
                        'url' => $url,
                        'sort_order' => max(0, (int) ($_POST['link_sort_order'] ?? 0)),
                    ]);
                    $notice = 'เพิ่มหัวข้อย่อยสำเร็จ';
                }
            }
        } elseif ($action === 'update_section') {
            $sectionId = (int) ($_POST['section_id'] ?? 0);
            $title = trim((string) ($_POST['title'] ?? ''));
            $titleEn = trim((string) ($_POST['title_en'] ?? ''));
            if ($sectionId <= 0 || $title === '' || mb_strlen($title, 'UTF-8') > 120
                || mb_strlen($titleEn, 'UTF-8') > 120) {
                $error = 'กรุณากรอกชื่อหัวข้อภาษาไทย และกรอกแต่ละภาษาไม่เกิน 120 ตัวอักษร';
            } else {
                $update = $pdo->prepare(
                    'UPDATE footer_sections SET title = :title, title_en = :title_en, sort_order = :sort_order
                     WHERE id = :id'
                );
                $update->execute([
                    'title' => $title,
                    'title_en' => $titleEn,
                    'sort_order' => max(0, (int) ($_POST['sort_order'] ?? 0)),
                    'id' => $sectionId,
                ]);
                $notice = 'บันทึกหัวข้อ footer เรียบร้อยแล้ว';
            }
        } elseif ($action === 'update_link') {
            $linkId = (int) ($_POST['link_id'] ?? 0);
            $label = trim((string) ($_POST['label'] ?? ''));
            $labelEn = trim((string) ($_POST['label_en'] ?? ''));
            $url = $normalizeUrl((string) ($_POST['url'] ?? ''));
            if ($linkId <= 0 || $label === '' || $url === ''
                || mb_strlen($label, 'UTF-8') > 120 || mb_strlen($labelEn, 'UTF-8') > 120
                || strlen($url) > 255) {
                $error = 'กรุณากรอกหัวข้อภาษาไทยและ URL ให้ถูกต้อง (ข้อความไม่เกิน 120 ตัวอักษร)';
            } else {
                $update = $pdo->prepare(
                    'UPDATE footer_links SET label = :label, label_en = :label_en, url = :url, sort_order = :sort_order
                     WHERE id = :id'
                );
                $update->execute([
                    'label' => $label,
                    'label_en' => $labelEn,
                    'url' => $url,
                    'sort_order' => max(0, (int) ($_POST['link_sort_order'] ?? 0)),
                    'id' => $linkId,
                ]);
                $notice = 'บันทึกหัวข้อย่อยเรียบร้อยแล้ว';
            }
        } elseif ($action === 'save_logo') {
            if (!isset($_FILES['logo']) || !is_array($_FILES['logo'])) {
                throw new ImageUploadException('กรุณาเลือกรูปโลโก้');
            }
            $upload = save_uploaded_images(
                array_map(static fn (mixed $value): array => [$value], $_FILES['logo']),
                dirname(__DIR__) . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'footer',
                'img/footer',
                'footer-logo-'
            );
            if ($upload === []) {
                throw new ImageUploadException('กรุณาเลือกรูปโลโก้');
            }
            $uploadedLogoPath = $upload[0]['path'];
            $oldLogoPath = (string) $pdo->query(
                'SELECT logo_path FROM footer_settings WHERE id = 1'
            )->fetchColumn();
            $update = $pdo->prepare('UPDATE footer_settings SET logo_path = :logo_path WHERE id = 1');
            $update->execute(['logo_path' => $uploadedLogoPath]);
            remove_managed_image_file($oldLogoPath, 'img/footer', 'footer-logo-');
            $uploadedLogoPath = null;
            $notice = 'อัปเดตโลโก้ Footer เรียบร้อยแล้ว';
        } elseif ($action === 'remove_logo') {
            $oldLogoPath = (string) $pdo->query(
                'SELECT logo_path FROM footer_settings WHERE id = 1'
            )->fetchColumn();
            $update = $pdo->prepare('UPDATE footer_settings SET logo_path = :logo_path WHERE id = 1');
            $update->execute(['logo_path' => 'img/images.png']);
            remove_managed_image_file($oldLogoPath, 'img/footer', 'footer-logo-');
            $notice = 'คืนค่าโลโก้เริ่มต้นเรียบร้อยแล้ว';
        } elseif ($action === 'delete_section') {
            $sectionId = (int) ($_POST['section_id'] ?? 0);
            if ($sectionId <= 0) {
                $error = 'ไม่พบหัวข้อที่ต้องการลบ';
            } else {
                $delete = $pdo->prepare('DELETE FROM footer_sections WHERE id = :id');
                $delete->execute(['id' => $sectionId]);
                $notice = 'ลบหัวข้อ footer เรียบร้อยแล้ว';
            }
        } elseif ($action === 'delete_link') {
            $linkId = (int) ($_POST['link_id'] ?? 0);
            if ($linkId <= 0) {
                $error = 'ไม่พบหัวข้อย่อยที่ต้องการลบ';
            } else {
                $delete = $pdo->prepare('DELETE FROM footer_links WHERE id = :id');
                $delete->execute(['id' => $linkId]);
                $notice = 'ลบหัวข้อย่อยเรียบร้อยแล้ว';
            }
        } elseif ($action === 'load_default_sections') {
            $count = $pdo->query('SELECT COUNT(*) FROM footer_sections')->fetchColumn();
            if ((int) $count > 0) {
                $notice = 'มีหัวข้อ footer อยู่แล้ว ไม่ต้องสร้างซ้ำ';
            } else {
                foreach ($defaultSections as $order => $section) {
                    $sectionStmt = $pdo->prepare(
                        'INSERT INTO footer_sections (title, title_en, slug, sort_order)
                         VALUES (:title, :title_en, :slug, :sort_order)'
                    );
                    $slug = $slugify($section['title']);
                    $sectionStmt->execute([
                        'title' => $section['title'],
                        'title_en' => $section['title_en'],
                        'slug' => $slug,
                        'sort_order' => $order,
                    ]);
                    $sectionId = (int) $pdo->lastInsertId();
                    foreach ($section['links'] as $linkOrder => $link) {
                        $linkStmt = $pdo->prepare(
                            'INSERT INTO footer_links (section_id, label, label_en, url, sort_order)
                             VALUES (:section_id, :label, :label_en, :url, :sort_order)'
                        );
                        $linkStmt->execute([
                            'section_id' => $sectionId,
                            'label' => $link['label'],
                            'label_en' => $link['label_en'],
                            'url' => $normalizeUrl($link['url']),
                            'sort_order' => $linkOrder,
                        ]);
                    }
                }
                $notice = 'สร้าง footer เริ่มต้นเรียบร้อยแล้ว';
            }
        }
    } catch (Throwable $exception) {
        if ($uploadedLogoPath !== null) {
            remove_managed_image_file($uploadedLogoPath, 'img/footer', 'footer-logo-');
        }
        error_log('Footer manager failed: ' . $exception->getMessage());
        $error = $exception instanceof ImageUploadException
            ? $exception->getMessage()
            : 'จัดการ footer ไม่สำเร็จ กรุณาตรวจสอบข้อมูลและลองใหม่อีกครั้ง';
    }
}

$sections = $pdo->query(
    'SELECT id, title, title_en, slug, sort_order, is_active FROM footer_sections ORDER BY sort_order ASC, id ASC'
)->fetchAll();

$sectionCounts = [];
foreach ($sections as $section) {
    $count = $pdo->prepare('SELECT COUNT(*) FROM footer_links WHERE section_id = :section_id');
    $count->execute(['section_id' => $section['id']]);
    $sectionCounts[(int) $section['id']] = (int) $count->fetchColumn();
}

$links = [];
foreach ($sections as $section) {
    $items = $pdo->prepare(
        'SELECT id, label, label_en, url, sort_order, is_active FROM footer_links
         WHERE section_id = :section_id ORDER BY sort_order ASC, id ASC'
    );
    $items->execute(['section_id' => $section['id']]);
    $links[(int) $section['id']] = $items->fetchAll();
}
$sectionCount = count($sections);
$linkCount = $pdo->query('SELECT COUNT(*) FROM footer_links')->fetchColumn();
$logoPath = (string) $pdo->query('SELECT logo_path FROM footer_settings WHERE id = 1')->fetchColumn();
$csrfToken = csrf_token();
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>จัดการ Footer | Admin</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; grid-template-columns: 250px minmax(0, 1fr); background: #f4f7fa; color: #17324d; font-family: Arial, sans-serif; }
        .side { position: sticky; top: 0; height: 100vh; padding: 1.5rem 1.25rem; background: #17324d; color: #fff; }
        .brand { display: flex; align-items: center; gap: .75rem; text-decoration: none; color: #fff; font-weight: 700; }
        .brand-mark { width: 38px; height: 38px; display: grid; place-items: center; border: 1px solid rgba(255,255,255,.45); border-left: 3px solid #c8a35c; }
        nav { display: grid; gap: .35rem; margin-top: 2rem; }
        nav a { display: block; padding: .65rem .75rem; border-radius: 4px; color: rgba(255,255,255,.82); text-decoration: none; }
        nav a:hover, nav a[aria-current="page"] { background: rgba(255,255,255,.12); color: #fff; }
        .user { position: absolute; right: 1.25rem; bottom: 1.5rem; left: 1.25rem; display: grid; gap: .25rem; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,.2); }
        .user small { color: rgba(255,255,255,.66); }
        .user a { color: #fff; }
        main { width: min(1100px, calc(100% - 4rem)); margin: 0 auto; padding: 2rem 0; }
        h1 { margin: 0 0 .25rem; font-size: 1.8rem; }
        .sub { margin: 0 0 1.25rem; color: #66788a; }
        .notice { margin: 0 0 1rem; padding: .8rem 1rem; border-left: 4px solid #1e4a7a; background: #fff; color: #17324d; }
        .notice.error { border-color: #b44d4d; color: #7a2c2c; }
        .notice.success { border-color: #2f8d59; color: #1c6332; }
        .stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1rem; margin-bottom: 1rem; }
        .stat { background: #fff; border: 1px solid #dfe7f1; border-radius: 12px; padding: 1rem; text-align: center; }
        .stat strong { display: block; font-size: 1.5rem; }
        .grid { display: grid; grid-template-columns: 1fr 1.2fr; gap: 1rem; }
        .panel { background: #fff; border: 1px solid #dfe7f1; border-radius: 12px; padding: 1.25rem; }
        .panel h2 { margin: 0 0 1rem; font-size: 1.1rem; }
        .field { margin-bottom: .9rem; }
        label { display: block; margin-bottom: .35rem; font-weight: 700; }
        input, select, button { font: inherit; }
        input, select { width: 100%; min-height: 42px; padding: .55rem .7rem; border: 1px solid #bccad8; border-radius: 8px; }
        button { min-height: 42px; padding: .55rem 1rem; border: 1px solid #1e4a7a; border-radius: 8px; background: #1e4a7a; color: #fff; cursor: pointer; font-weight: 700; }
        button.danger { background: #c94c4c; border-color: #c94c4c; }
        .row { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem; }
        .section-list { display: grid; gap: .85rem; }
        .section-box { border: 1px solid #e1e8f0; border-radius: 10px; padding: .9rem; background: #fafbff; }
        .section-head { display: flex; justify-content: space-between; align-items: center; gap: .75rem; margin-bottom: .6rem; }
        .pill { display: inline-block; background: #e8f2ff; color: #0d5db1; padding: .2rem .5rem; border-radius: 999px; font-size: .76rem; font-weight: 700; }
        .link-list { display: grid; gap: .55rem; }
        .link-item { display: flex; justify-content: space-between; gap: .75rem; align-items: center; padding: .45rem .6rem; background: #fff; border: 1px solid #e8edf6; border-radius: 8px; }
        .link-item a { color: #17324d; text-decoration: none; }
        .link-item a:hover { text-decoration: underline; }
        .empty { padding: 1rem; border: 1px dashed #d2dceb; border-radius: 8px; background: #f8fbff; color: #627286; }
        .actions-inline { display: flex; gap: .5rem; }
        .logo-preview { width: 72px; height: 72px; object-fit: contain; padding: .35rem; border: 1px solid #dfe7f1; border-radius: 8px; background: #fff; }
        .english-field { margin-top: .45rem; }
        form.inline { display: inline; }
        @media (max-width: 920px) { body { grid-template-columns: 1fr; } .side { position: static; height: auto; } main { width: min(100% - 2rem, 1100px); padding: 1.25rem 0; } .grid { grid-template-columns: 1fr; } .stats { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <aside class="side">
        <a class="brand" href="../index.html"><span class="brand-mark" aria-hidden="true">U</span><span>ระบบจัดการเว็บไซต์</span></a>
        <?php render_admin_nav('footer'); ?>
        <div class="user"><small>ผู้ดูแลระบบ</small><strong><?= escape((string) ($_SESSION['admin_username'] ?? '')) ?></strong><a href="logout.php">ออกจากระบบ</a></div>
    </aside>
    <main>
        <h1>จัดการ Footer</h1>
        <p class="sub">จัดการโลโก้ หัวข้อ และลิงก์ Footer ได้จากที่นี่ หน้าเว็บไซต์จะแสดงข้อมูลล่าสุดโดยอัตโนมัติ</p>

        <?php if ($notice !== ''): ?><div class="notice success"><?= escape($notice) ?></div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="notice error"><?= escape($error) ?></div><?php endif; ?>

        <div class="stats">
            <div class="stat"><strong><?= (int) $sectionCount ?></strong><span>หัวข้อหลัก</span></div>
            <div class="stat"><strong><?= (int) $linkCount ?></strong><span>หัวข้อย่อย</span></div>
            <div class="stat"><strong><?= $sectionCount === 0 ? 'ยังไม่มี' : 'พร้อมใช้งาน' ?></strong><span>สถานะ footer</span></div>
        </div>

        <div class="panel" style="margin-bottom:1rem;">
            <h2>โลโก้ Footer</h2>
            <div class="row" style="align-items:center;">
                <img class="logo-preview" src="../<?= escape($logoPath) ?>" alt="โลโก้ Footer ปัจจุบัน">
                <div>
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
                        <input type="hidden" name="action" value="save_logo">
                        <div class="field">
                            <label for="logo">เลือกรูปโลโก้ (JPG, PNG, GIF หรือ WebP ไม่เกิน 5 MB)</label>
                            <input id="logo" name="logo" type="file" accept="image/jpeg,image/png,image/gif,image/webp" required>
                        </div>
                        <button type="submit">อัปเดตโลโก้</button>
                    </form>
                    <form method="post" style="margin-top:.5rem;">
                        <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
                        <input type="hidden" name="action" value="remove_logo">
                        <button type="submit">ใช้โลโก้เริ่มต้น</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="grid">
            <div class="panel">
                <h2>สร้างหัวข้อหลัก</h2>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
                    <input type="hidden" name="action" value="create_section">
                    <div class="field">
                        <label for="title">ชื่อหัวข้อ (ภาษาไทย)</label>
                        <input id="title" name="title" type="text" placeholder="เช่น เกี่ยวกับมหาวิทยาลัย" required>
                    </div>
                    <div class="field english-field">
                        <label for="title_en">ชื่อหัวข้อ (English)</label>
                        <input id="title_en" name="title_en" type="text" maxlength="120" placeholder="e.g. About the University">
                    </div>
                    <div class="field">
                        <label for="sort_order">ลำดับ</label>
                        <input id="sort_order" name="sort_order" type="number" min="0" value="0">
                    </div>
                    <button type="submit">เพิ่มหัวข้อหลัก</button>
                </form>
                <div style="margin-top: 1rem;">
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
                        <input type="hidden" name="action" value="load_default_sections">
                        <button type="submit">สร้าง footer เริ่มต้น</button>
                    </form>
                </div>
            </div>

            <div class="panel">
                <h2>เพิ่มหัวข้อย่อย</h2>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
                    <input type="hidden" name="action" value="create_link">
                    <div class="field">
                        <label for="section_id">เลือกหัวข้อหลัก</label>
                        <select id="section_id" name="section_id" required>
                            <option value="">-- เลือกหัวข้อหลัก --</option>
                            <?php foreach ($sections as $section): ?>
                                <option value="<?= (int) $section['id'] ?>"><?= escape((string) $section['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="field">
                            <label for="label">ชื่อหัวข้อย่อย (ภาษาไทย)</label>
                            <input id="label" name="label" type="text" placeholder="เช่น ประวัติ" required>
                        </div>
                        <div class="field english-field">
                            <label for="label_en">ชื่อหัวข้อย่อย (English)</label>
                            <input id="label_en" name="label_en" type="text" maxlength="120" placeholder="e.g. History">
                        </div>
                        <div class="field">
                            <label for="link_sort_order">ลำดับ</label>
                            <input id="link_sort_order" name="link_sort_order" type="number" min="0" value="0">
                        </div>
                    </div>
                    <div class="field">
                        <label for="url">URL</label>
                        <input id="url" name="url" type="text" placeholder="../about.html หรือ https://example.com" required>
                    </div>
                    <button type="submit">เพิ่มหัวข้อย่อย</button>
                </form>
            </div>
        </div>

        <div class="panel" style="margin-top:1rem;">
            <h2>รายการ footer ที่มีอยู่</h2>
            <?php if ($sections === []): ?>
                <div class="empty">ยังไม่มีหัวข้อ footer ในระบบ กรุณาสร้างหัวข้อหลักก่อน</div>
            <?php else: ?>
                <div class="section-list">
                    <?php foreach ($sections as $section): ?>
                        <div class="section-box">
                            <div class="section-head">
                                <div>
                                    <strong><?= escape((string) $section['title']) ?></strong>
                                    <?php if ((string) $section['title_en'] !== ''): ?><span class="pill"><?= escape((string) $section['title_en']) ?></span><?php endif; ?>
                                    <span class="pill">slug: <?= escape((string) $section['slug']) ?></span>
                                </div>
                                <div class="actions-inline">
                                    <form class="inline" method="post" onsubmit="return confirm('ลบหัวข้อหลักนี้และหัวข้อย่อยทั้งหมดใช่หรือไม่?');">
                                        <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
                                        <input type="hidden" name="action" value="delete_section">
                                        <input type="hidden" name="section_id" value="<?= (int) $section['id'] ?>">
                                        <button type="submit" class="danger">ลบหัวข้อ</button>
                                    </form>
                                </div>
                            </div>

                            <form method="post" class="row" style="margin:.75rem 0;">
                                <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
                                <input type="hidden" name="action" value="update_section">
                                <input type="hidden" name="section_id" value="<?= (int) $section['id'] ?>">
                                <div class="field">
                                    <label>ชื่อหัวข้อ (ภาษาไทย)</label>
                                    <input name="title" type="text" maxlength="120" value="<?= escape((string) $section['title']) ?>" required>
                                </div>
                                <div class="field">
                                    <label>ชื่อหัวข้อ (English)</label>
                                    <input name="title_en" type="text" maxlength="120" value="<?= escape((string) $section['title_en']) ?>">
                                </div>
                                <div class="field">
                                    <label>ลำดับ</label>
                                    <input name="sort_order" type="number" min="0" value="<?= (int) $section['sort_order'] ?>">
                                </div>
                                <div class="field" style="align-self:end;">
                                    <button type="submit">บันทึกหัวข้อ</button>
                                </div>
                            </form>

                            <?php if (empty($links[(int) $section['id']])): ?>
                                <div class="empty">ยังไม่มีหัวข้อย่อยในหมวดนี้</div>
                            <?php else: ?>
                                <div class="link-list">
                                    <?php foreach ($links[(int) $section['id']] as $link): ?>
                                        <div class="link-item">
                                            <a href="<?= escape((string) $link['url']) ?>" target="_blank" rel="noopener noreferrer"><?= escape((string) $link['label']) ?><?= (string) $link['label_en'] !== '' ? ' / ' . escape((string) $link['label_en']) : '' ?></a>
                                            <div class="actions-inline">
                                                <span class="pill">#<?= (int) $link['sort_order'] ?></span>
                                                <form class="inline" method="post" onsubmit="return confirm('ลบหัวข้อย่อยนี้ใช่หรือไม่?');">
                                                    <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
                                                    <input type="hidden" name="action" value="delete_link">
                                                    <input type="hidden" name="link_id" value="<?= (int) $link['id'] ?>">
                                                    <button type="submit" class="danger">ลบ</button>
                                                </form>
                                            </div>
                                        </div>
                                        <form method="post" class="row" style="margin:.4rem 0 .8rem;">
                                            <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
                                            <input type="hidden" name="action" value="update_link">
                                            <input type="hidden" name="link_id" value="<?= (int) $link['id'] ?>">
                                            <div class="field">
                                                <label>หัวข้อย่อย (ภาษาไทย)</label>
                                                <input name="label" type="text" maxlength="120" value="<?= escape((string) $link['label']) ?>" required>
                                            </div>
                                            <div class="field">
                                                <label>หัวข้อย่อย (English)</label>
                                                <input name="label_en" type="text" maxlength="120" value="<?= escape((string) $link['label_en']) ?>">
                                            </div>
                                            <div class="field">
                                                <label>URL</label>
                                                <input name="url" type="text" maxlength="255" value="<?= escape((string) $link['url']) ?>" required>
                                            </div>
                                            <div class="field">
                                                <label>ลำดับ</label>
                                                <input name="link_sort_order" type="number" min="0" value="<?= (int) $link['sort_order'] ?>">
                                            </div>
                                            <div class="field" style="align-self:end;">
                                                <button type="submit">บันทึกหัวข้อย่อย</button>
                                            </div>
                                        </form>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
