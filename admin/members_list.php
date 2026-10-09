<?php
declare(strict_types=1);
require_once __DIR__ . '/check_session.php';
require_once __DIR__ . '/admin_nav.php';

$roles = [
    'students' => [
        'label' => 'นักศึกษา',
        'id' => 'student_id',
        'select' => "student_id AS member_id, CONCAT_WS(' ', NULLIF(prefix, ''), firstname, lastname) AS full_name, email, faculty, major AS detail_one, CONCAT('ชั้นปี ', COALESCE(class_year, '-')) AS detail_two, status AS detail_three, created_at",
        'search' => ['student_id', 'firstname', 'lastname', 'email', 'faculty', 'major'],
        'columns' => ['member_id' => 'รหัสนักศึกษา', 'full_name' => 'ชื่อ-นามสกุล', 'email' => 'อีเมล', 'faculty' => 'คณะ', 'detail_one' => 'สาขา', 'detail_two' => 'ชั้นปี', 'detail_three' => 'สถานะ', 'created_at' => 'วันที่สมัคร'],
    ],
    'personnel' => [
        'label' => 'บุคลากร',
        'id' => 'personnel_id',
        'select' => "personnel_id AS member_id, CONCAT_WS(' ', NULLIF(prefix, ''), firstname, lastname) AS full_name, email, faculty, department AS detail_one, position AS detail_two, '' AS detail_three, created_at",
        'search' => ['personnel_id', 'firstname', 'lastname', 'email', 'faculty', 'department', 'position'],
        'columns' => ['member_id' => 'รหัสบุคลากร', 'full_name' => 'ชื่อ-นามสกุล', 'email' => 'อีเมล', 'faculty' => 'คณะ', 'detail_one' => 'หน่วยงาน', 'detail_two' => 'ตำแหน่ง', 'created_at' => 'วันที่สมัคร'],
    ],
    'alumni' => [
        'label' => 'ศิษย์เก่า',
        'id' => 'alumni_id',
        'select' => "alumni_id AS member_id, CONCAT_WS(' ', NULLIF(prefix, ''), firstname, lastname) AS full_name, email, faculty, major AS detail_one, CONCAT('จบปี ', COALESCE(graduation_year, '-')) AS detail_two, phone AS detail_three, created_at",
        'search' => ['alumni_id', 'firstname', 'lastname', 'email', 'faculty', 'major', 'phone'],
        'columns' => ['member_id' => 'รหัสศิษย์เก่า', 'full_name' => 'ชื่อ-นามสกุล', 'email' => 'อีเมล', 'faculty' => 'คณะที่จบ', 'detail_one' => 'สาขา', 'detail_two' => 'ปีที่จบ', 'detail_three' => 'โทรศัพท์', 'created_at' => 'วันที่สมัคร'],
    ],
];
$requestedType = $_GET['type'] ?? 'all';
$type = is_string($requestedType) ? $requestedType : 'all';
if ($type !== 'all' && !isset($roles[$type])) {
    $type = 'all';
}
$requestedSearch = $_GET['q'] ?? '';
$search = is_string($requestedSearch) ? trim($requestedSearch) : '';
$search = mb_substr($search, 0, 100, 'UTF-8');
$page = max(1, filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT) ?: 1);
$perPage = 25;
$items = [];
$counts = array_fill_keys(array_keys($roles), 0);
$total = 0;
$error = '';
$deleteError = '';
$deleteMessage = (string) ($_SESSION['member_list_message'] ?? '');
unset($_SESSION['member_list_message']);
$pageTitle = $type === 'personnel'
    ? 'บัญชีบุคลากร'
    : ($type === 'all' ? 'รายชื่อสมาชิกทั้งหมด' : 'รายชื่อ' . $roles[$type]['label']);
$activeMenu = 'members';
$allMembersQuery = "
    SELECT 'นักศึกษา' AS member_type, 'students' AS member_type_key, student_id AS member_id,
        CONCAT_WS(' ', NULLIF(prefix, ''), firstname, lastname) AS full_name,
        email, faculty, major AS detail_one,
        CONCAT('ชั้นปี ', COALESCE(class_year, '-')) AS detail_two,
        status AS detail_three, created_at
    FROM students
    UNION ALL
    SELECT 'บุคลากร', 'personnel', personnel_id,
        CONCAT_WS(' ', NULLIF(prefix, ''), firstname, lastname),
        email, faculty, department, position, '', created_at
    FROM personnel
    UNION ALL
    SELECT 'ศิษย์เก่า', 'alumni', alumni_id,
        CONCAT_WS(' ', NULLIF(prefix, ''), firstname, lastname),
        email, faculty, major, CONCAT('จบปี ', COALESCE(graduation_year, '-')), phone, created_at
    FROM alumni";
$allColumns = [
    'member_type' => 'ประเภท',
    'member_id' => 'รหัสสมาชิก',
    'full_name' => 'ชื่อ-นามสกุล',
    'email' => 'อีเมล',
    'faculty' => 'คณะ',
    'detail_one' => 'สาขา / หน่วยงาน',
    'detail_two' => 'ข้อมูลเพิ่มเติม',
    'detail_three' => 'สถานะ / โทรศัพท์',
    'created_at' => 'วันที่สมัคร',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $memberType = (string) ($_POST['member_type'] ?? '');
    $memberId = trim((string) ($_POST['member_id'] ?? ''));
    $returnType = (string) ($_POST['return_type'] ?? 'all');
    $returnType = $returnType === 'all' || isset($roles[$returnType]) ? $returnType : 'all';
    $returnSearch = mb_substr(trim((string) ($_POST['return_search'] ?? '')), 0, 100, 'UTF-8');
    $returnPage = max(1, filter_var($_POST['return_page'] ?? 1, FILTER_VALIDATE_INT) ?: 1);

    if (($_POST['action'] ?? '') !== 'delete' || !isset($roles[$memberType]) || $memberId === '' || strlen($memberId) > 15) {
        $deleteError = 'คำขอลบสมาชิกไม่ถูกต้อง';
    } else {
        try {
            $statement = db()->prepare(
                'DELETE FROM `' . $memberType . '` WHERE `' . $roles[$memberType]['id'] . '` = :member_id'
            );
            $statement->execute(['member_id' => $memberId]);
            if ($statement->rowCount() !== 1) {
                $deleteError = 'ไม่พบสมาชิกที่ต้องการลบ';
            } else {
                $_SESSION['member_list_message'] = 'ลบข้อมูลสมาชิกเรียบร้อยแล้ว';
                header('Location: members_list.php?' . http_build_query([
                    'type' => $returnType,
                    'q' => $returnSearch,
                    'page' => $returnPage,
                ]));
                exit;
            }
        } catch (Throwable $exception) {
            error_log('Member deletion failed: ' . $exception->getMessage());
            $deleteError = 'ลบข้อมูลสมาชิกไม่สำเร็จ กรุณาลองใหม่';
        }
    }
}

try {
    $pdo = db();
    foreach ($roles as $table => $_role) {
        $counts[$table] = (int) $pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
    }

    $conditions = [];
    $parameters = [];
    if ($search !== '') {
        $searchColumns = $type === 'all'
            ? ['member_type', 'member_id', 'full_name', 'email', 'faculty', 'detail_one', 'detail_two', 'detail_three']
            : $roles[$type]['search'];
        $conditions = array_map(static fn(string $column): string => '`' . $column . '` LIKE :search', $searchColumns);
        $parameters['search'] = '%' . $search . '%';
    }
    $where = $conditions ? ' WHERE (' . implode(' OR ', $conditions) . ')' : '';
    $source = $type === 'all' ? '(' . $allMembersQuery . ') AS members' : '`' . $type . '`';
    $countStatement = $pdo->prepare('SELECT COUNT(*) FROM ' . $source . $where);
    $countStatement->execute($parameters);
    $total = (int) $countStatement->fetchColumn();
    $totalPages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;
    $select = $type === 'all' ? '*' : $roles[$type]['select'];
    $statement = $pdo->prepare('SELECT ' . $select . ' FROM ' . $source . $where . ' ORDER BY created_at DESC LIMIT :limit OFFSET :offset');
    foreach ($parameters as $name => $value) {
        $statement->bindValue(':' . $name, $value, PDO::PARAM_STR);
    }
    $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
    $statement->execute();
    $items = $statement->fetchAll();
} catch (Throwable $exception) {
    error_log('Member list query failed: ' . $exception->getMessage());
    $error = 'โหลดรายชื่อสมาชิกไม่สำเร็จ กรุณาตรวจสอบการเชื่อมต่อฐานข้อมูล';
    $totalPages = 1;
}

$query = static function (int $targetPage) use ($type, $search): string {
    return '?' . http_build_query(['type' => $type, 'q' => $search, 'page' => $targetPage]);
};
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
        .brand-mark { width: 38px; height: 38px; display: grid; place-items: center; border: 1px solid rgba(255,255,255,.45); border-left: 3px solid #c8a35c; font-family: Georgia,serif; font-size: 1.35rem; }
        nav { display: grid; gap: .35rem; margin-top: 2rem; }
        nav a { padding: .65rem .75rem; border-radius: 4px; color: rgba(255,255,255,.82); text-decoration: none; }
        nav a:hover, nav a[aria-current="page"] { background: rgba(255,255,255,.12); color: #fff; }
        .user { position: absolute; right: 1.25rem; bottom: 1.5rem; left: 1.25rem; display: grid; gap: .25rem; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,.2); overflow-wrap: anywhere; }
        .user small { color: rgba(255,255,255,.65); }
        .user a { margin-top: .35rem; color: #fff; }
        main { width: min(1300px, calc(100% - 4rem)); min-width: 0; margin: 0 auto; padding: 2rem 0; }
        h1 { margin: 0; font-size: 1.6rem; }
        .sub { margin: .35rem 0 1.4rem; color: #66788a; }
        .tabs { display: flex; gap: .5rem; flex-wrap: wrap; margin-bottom: 1rem; border-bottom: 1px solid #d7e0ea; }
        .tabs a { padding: .65rem .85rem; color: #425a70; text-decoration: none; border-bottom: 2px solid transparent; }
        .tabs a[aria-current="page"] { color: #1e4a7a; border-color: #1e4a7a; font-weight: 700; }
        .count { display: inline-grid; min-width: 1.5rem; height: 1.5rem; margin-left: .3rem; padding: 0 .35rem; place-items: center; border-radius: 2rem; background: #e7eef6; font-size: .78rem; }
        .toolbar { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin: 1rem 0; }
        .toolbar form { display: flex; gap: .5rem; width: min(100%, 440px); }
        input, button { min-height: 40px; padding: .5rem .7rem; border: 1px solid #bdc9d6; border-radius: 4px; background: #fff; color: inherit; font: inherit; }
        input { flex: 1; min-width: 0; }
        button { border-color: #1e4a7a; background: #1e4a7a; color: #fff; cursor: pointer; }
        .table-wrap { overflow-x: auto; border: 1px solid #d7e0ea; background: #fff; }
        table { width: 100%; border-collapse: collapse; white-space: nowrap; }
        th, td { padding: .7rem .8rem; border-bottom: 1px solid #e4eaf0; text-align: left; }
        th { background: #edf2f7; color: #425a70; font-size: .85rem; }
        td { font-size: .92rem; }
        .empty, .notice { padding: 1rem; background: #fff; }
        .notice { border-left: 3px solid #a53c35; color: #812f29; }
            .success { border-left-color: #24733a; color: #17652a; }
            .actions { display: flex; justify-content: center; }
            .actions form { margin: 0; }
            .view-button { display: inline-flex; align-items: center; min-height: 34px; padding: .35rem .55rem; border: 1px solid #1e4a7a; border-radius: 4px; background: #fff; color: #1e4a7a; font-size: .84rem; text-decoration: none; }
            .view-button:hover { background: #edf2f7; }
            .danger { border-color: #a42121; background: #a42121; color: #fff; cursor: pointer; }
            .danger:hover { border-color: #841919; background: #841919; }
        .pagination { display: flex; justify-content: flex-end; align-items: center; gap: .75rem; margin-top: 1rem; }
        .pagination a { color: #1e4a7a; }
        @media (max-width: 760px) { body { grid-template-columns: 1fr; } .side { position: static; height: auto; padding: 1rem; } nav { display: flex; flex-wrap: wrap; margin-top: .8rem; } .user { position: static; margin-top: .8rem; padding-top: .7rem; } main { width: min(100% - 2rem, 1300px); padding: 1.2rem 0; } .toolbar { align-items: flex-start; flex-direction: column; } }
    </style>
    <link rel="stylesheet" href="admin.css">
</head>
<body>
<aside class="side">
    <a class="brand" href="../index.html"><span class="brand-mark" aria-hidden="true"><img src="../img/images.jpg" alt=""></span><span>ระบบจัดการเว็บไซต์</span></a>
    <?php render_admin_nav($activeMenu); ?>
    <div class="user"><small>ผู้ดูแลระบบ</small><strong><?= escape((string) $_SESSION['admin_username']) ?></strong><a href="logout.php">ออกจากระบบ</a></div>
</aside>
<main>
    <h1><?= escape($pageTitle) ?></h1>
    <p class="sub">ข้อมูลจากฐานข้อมูลสมาชิก แยกตามประเภทบัญชี</p>

    <div class="tabs" aria-label="ประเภทสมาชิก">
        <a href="?type=all" <?= $type === 'all' ? 'aria-current="page"' : '' ?>>ทั้งหมด<span class="count"><?= escape((string) array_sum($counts)) ?></span></a>
        <?php foreach ($roles as $key => $roleTab): ?>
            <a href="?type=<?= escape($key) ?>" <?= $type === $key ? 'aria-current="page"' : '' ?>><?= escape($roleTab['label']) ?><span class="count"><?= escape((string) $counts[$key]) ?></span></a>
        <?php endforeach; ?>
    </div>

    <?php if ($error !== ''): ?><p class="notice" role="alert"><?= escape($error) ?></p><?php else: ?>
        <?php if ($deleteMessage !== ''): ?><p class="notice success" role="status"><?= escape($deleteMessage) ?></p><?php endif; ?>
        <?php if ($deleteError !== ''): ?><p class="notice" role="alert"><?= escape($deleteError) ?></p><?php endif; ?>
        <div class="toolbar">
            <strong><?= escape($type === 'all' ? 'สมาชิก' : $roles[$type]['label']) ?>ทั้งหมด <?= escape((string) $total) ?> รายชื่อ</strong>
            <form method="get" role="search">
                <input type="hidden" name="type" value="<?= escape($type) ?>">
                <input name="q" type="search" value="<?= escape($search) ?>" placeholder="ค้นหารหัส ชื่อ อีเมล หรือคณะ" aria-label="ค้นหาสมาชิก">
                <button type="submit">ค้นหา</button>
            </form>
        </div>
        <?php if (!$items): ?>
            <p class="empty">ยังไม่มีรายชื่อ<?= escape($type === 'all' ? 'สมาชิก' : $roles[$type]['label']) ?><?= $search !== '' ? ' ที่ตรงกับคำค้นหา' : '' ?></p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <?php $visibleColumns = $type === 'all' ? $allColumns : $roles[$type]['columns']; ?>
                    <thead><tr><?php foreach ($visibleColumns as $label): ?><th scope="col"><?= escape($label) ?></th><?php endforeach; ?><th scope="col">จัดการ</th></tr></thead>
                    <tbody>
                    <?php foreach ($items as $item): ?>
                        <?php $memberTypeKey = $type === 'all' ? $item['member_type_key'] : $type; ?>
                        <tr>
                            <?php foreach ($visibleColumns as $column => $_label): ?>
                                <?php $value = $column === 'member_type' ? ($roles[$memberTypeKey]['label'] ?? '') : ($item[$column] ?? ''); ?>
                                <td><?= escape((string) ($value !== '' ? $value : '—')) ?></td>
                            <?php endforeach; ?>
                            <td><div class="actions">
                                <a class="view-button" href="member_view.php?<?= escape(http_build_query([
                                    'type' => $memberTypeKey,
                                    'id' => (string) $item['member_id'],
                                    'return_type' => $type,
                                    'return_search' => $search,
                                    'return_page' => $page,
                                ])) ?>" aria-label="ดูรายละเอียดสมาชิก<?= escape($roles[$memberTypeKey]['label']) ?> รหัส <?= escape((string) $item['member_id']) ?>">ดูข้อมูล</a>
                                <form method="post" data-delete-confirm="ยืนยันลบสมาชิก<?= escape($roles[$memberTypeKey]['label']) ?> รหัส <?= escape((string) $item['member_id']) ?> หรือไม่?">
                                <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="member_type" value="<?= escape($memberTypeKey) ?>">
                                <input type="hidden" name="member_id" value="<?= escape((string) $item['member_id']) ?>">
                                <input type="hidden" name="return_type" value="<?= escape($type) ?>">
                                <input type="hidden" name="return_search" value="<?= escape($search) ?>">
                                <input type="hidden" name="return_page" value="<?= escape((string) $page) ?>">
                                <button class="danger" type="submit" aria-label="ลบสมาชิก<?= escape($roles[$memberTypeKey]['label']) ?> รหัส <?= escape((string) $item['member_id']) ?>">ลบ</button>
                            </form></div></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="pagination">
                <?php if ($page > 1): ?><a href="<?= escape($query($page - 1)) ?>">ก่อนหน้า</a><?php endif; ?>
                <span>หน้า <?= escape((string) $page) ?> / <?= escape((string) $totalPages) ?></span>
                <?php if ($page < $totalPages): ?><a href="<?= escape($query($page + 1)) ?>">ถัดไป</a><?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</main>
<script>
document.querySelectorAll('[data-delete-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.deleteConfirm)) {
            event.preventDefault();
        }
    });
});
</script>
</body>
</html>
