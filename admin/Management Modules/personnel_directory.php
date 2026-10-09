<?php
declare(strict_types=1);
require_once __DIR__ . '/../check_session.php';
require_once __DIR__ . '/../admin_nav.php';
require_once __DIR__ . '/../image_uploads.php';

$pdo = db();
$message = (string) ($_SESSION['personnel_directory_message'] ?? '');
unset($_SESSION['personnel_directory_message']);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    if (($_POST['action'] ?? '') !== 'delete' || !$id) {
        $error = 'คำขอลบข้อมูลบุคลากรไม่ถูกต้อง';
    } else {
        try {
            $statement = $pdo->prepare('SELECT image FROM personnel_profiles WHERE id = :id');
            $statement->execute(['id' => $id]);
            $profile = $statement->fetch();
            if (!$profile) {
                $error = 'ไม่พบข้อมูลบุคลากรที่ต้องการลบ';
            } else {
                $imageStatement = $pdo->prepare(
                    'SELECT image_path FROM personnel_profile_images WHERE personnel_profile_id = :id'
                );
                $imageStatement->execute(['id' => $id]);
                $profileImages = $imageStatement->fetchAll();
                $pdo->beginTransaction();
                $pdo->prepare('DELETE FROM personnel_profile_images WHERE personnel_profile_id = :id')->execute(['id' => $id]);
                $statement = $pdo->prepare('DELETE FROM personnel_profiles WHERE id = :id');
                $statement->execute(['id' => $id]);
                if ($statement->rowCount() !== 1) {
                    throw new RuntimeException('Personnel profile disappeared during deletion');
                }
                $pdo->commit();
                $primaryImage = (string) $profile['image'];
                if ($primaryImage !== '') {
                    $prefix = str_starts_with(basename($primaryImage), 'personnel_') ? 'personnel_' : '';
                    remove_managed_image_file($primaryImage, 'img/personnel', $prefix);
                }
                foreach ($profileImages as $profileImage) {
                    remove_managed_image_file((string) $profileImage['image_path'], 'img/personnel', 'personnel_');
                }
                $_SESSION['personnel_directory_message'] = 'ลบข้อมูลบุคลากรเรียบร้อยแล้ว';
                header('Location: personnel_directory.php');
                exit;
            }
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Personnel profile deletion failed: ' . $exception->getMessage());
            $error = 'ลบข้อมูลบุคลากรไม่สำเร็จ กรุณาลองใหม่';
        }
    }
}

$searchValue = $_GET['q'] ?? '';
$search = is_string($searchValue) ? mb_substr(trim($searchValue), 0, 100, 'UTF-8') : '';
$sql = 'SELECT id, prefix, fullname, position, department, email, image, display_order, status
    FROM personnel_profiles';
$parameters = [];
if ($search !== '') {
    $sql .= ' WHERE fullname LIKE :search OR position LIKE :search OR department LIKE :search OR email LIKE :search';
    $parameters['search'] = '%' . $search . '%';
}
$sql .= ' ORDER BY display_order ASC, fullname ASC';
$statement = $pdo->prepare($sql);
$statement->execute($parameters);
$items = $statement->fetchAll();
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>จัดการบุคลากร</title>
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
        main { width: min(1250px, calc(100% - 4rem)); min-width: 0; margin: 0 auto; padding: 2rem 0; }
        .heading { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; }
        h1 { margin: 0; font-size: 1.6rem; }
        .muted { margin: .35rem 0 1.2rem; color: #66788a; }
        .button, button { min-height: 40px; padding: .55rem .8rem; border: 1px solid #1e4a7a; border-radius: 4px; background: #1e4a7a; color: #fff; font: inherit; text-decoration: none; cursor: pointer; }
        .secondary { background: #fff; color: #1e4a7a; }
        .danger { border-color: #a42121; background: #a42121; }
        .notice, .error, .empty { margin: 1rem 0; padding: .8rem; border-radius: 4px; background: #fff; }
        .notice { border-left: 3px solid #24733a; color: #17652a; }
        .error { border-left: 3px solid #a42121; background: #fff0f0; color: #a42121; }
        .toolbar { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin: 1rem 0; }
        .toolbar form { display: flex; width: min(100%, 420px); gap: .5rem; }
        input { min-width: 0; min-height: 40px; flex: 1; padding: .5rem .7rem; border: 1px solid #bdc9d6; border-radius: 4px; font: inherit; }
        .table-wrap { overflow-x: auto; border: 1px solid #d7e0ea; background: #fff; }
        table { width: 100%; border-collapse: collapse; white-space: nowrap; }
        th, td { padding: .65rem .75rem; border-bottom: 1px solid #e4eaf0; text-align: left; }
        th { background: #edf2f7; color: #425a70; font-size: .85rem; }
        td { font-size: .9rem; }
        .person { display: flex; align-items: center; gap: .65rem; }
        .person img, .placeholder { width: 42px; height: 42px; flex: 0 0 auto; border-radius: 50%; object-fit: cover; background: #e7eef6; }
        .placeholder { display: grid; place-items: center; color: #1e4a7a; font-weight: 700; }
        .status { display: inline-block; padding: .2rem .45rem; border-radius: 3px; background: #eaf7ed; color: #17652a; font-size: .8rem; }
        .status.inactive { background: #f1f2f4; color: #66788a; }
        .actions { display: flex; gap: .4rem; }
        .actions form { margin: 0; }
        .actions .button, .actions button { min-height: 34px; padding: .35rem .55rem; font-size: .84rem; }
        @media (max-width: 760px) { body { grid-template-columns: 1fr; } .side { position: static; height: auto; padding: 1rem; } nav { display: flex; flex-wrap: wrap; margin-top: .8rem; } .user { position: static; margin-top: .8rem; padding-top: .7rem; } main { width: min(100% - 2rem, 1250px); padding: 1.2rem 0; } .toolbar { align-items: flex-start; flex-direction: column; } }
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
    <div class="heading"><div><h1>บุคลากร</h1><p class="muted">ข้อมูลที่แสดงในหน้าเว็บไซต์</p></div><a class="button" href="personnel_profile_form.php">เพิ่มบุคลากร</a></div>
    <?php if ($message !== ''): ?><p class="notice" role="status"><?= escape($message) ?></p><?php endif; ?>
    <?php if ($error !== ''): ?><p class="error" role="alert"><?= escape($error) ?></p><?php endif; ?>
    <div class="toolbar"><strong><?= count($items) ?> รายชื่อ<?= $search !== '' ? ' ที่ค้นพบ' : '' ?></strong><form method="get" role="search"><input type="search" name="q" value="<?= escape($search) ?>" placeholder="ค้นหาชื่อ ตำแหน่ง หรือหน่วยงาน" aria-label="ค้นหาบุคลากร"><button type="submit">ค้นหา</button></form></div>
    <?php if (!$items): ?><p class="empty">ยังไม่มีข้อมูลบุคลากร</p><?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th scope="col">บุคลากร</th><th scope="col">ตำแหน่ง</th><th scope="col">หน่วยงาน</th><th scope="col">ลำดับ</th><th scope="col">สถานะ</th><th scope="col">จัดการ</th></tr></thead>
            <tbody><?php foreach ($items as $item): ?>
                <tr>
                    <td><div class="person"><?php if ($item['image'] !== ''): ?><img src="../../<?= escape($item['image']) ?>" alt=""><?php else: ?><span class="placeholder" aria-hidden="true"><?= escape(mb_substr($item['fullname'], 0, 1, 'UTF-8')) ?></span><?php endif; ?><span><?= escape(trim($item['prefix'] . ' ' . $item['fullname'])) ?><br><small><?= escape((string) ($item['email'] ?? '')) ?></small></span></div></td>
                    <td><?= escape($item['position']) ?></td><td><?= escape($item['department']) ?></td><td><?= escape((string) $item['display_order']) ?></td>
                    <td><span class="status <?= $item['status'] === 'active' ? '' : 'inactive' ?>"><?= $item['status'] === 'active' ? 'แสดงบนเว็บ' : 'ซ่อนจากเว็บ' ?></span></td>
                    <td><div class="actions"><a class="button secondary" href="personnel_profile_form.php?id=<?= (int) $item['id'] ?>">แก้ไข</a><form method="post" onsubmit="return confirm('ยืนยันลบข้อมูลบุคลากรนี้หรือไม่?')"><input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $item['id'] ?>"><button class="danger" type="submit">ลบ</button></form></div></td>
                </tr>
            <?php endforeach; ?></tbody>
        </table></div>
    <?php endif; ?>
</main>
</body>
</html>
