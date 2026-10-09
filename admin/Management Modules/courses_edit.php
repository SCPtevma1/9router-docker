<?php
declare(strict_types=1);
require_once __DIR__ . '/../check_session.php';
require_once __DIR__ . '/../admin_nav.php';

$facultyOptions = require __DIR__ . '/../course_faculties.php';
$departmentCatalog = require __DIR__ . '/../course_departments.php';
$programCatalog = require __DIR__ . '/../course_program_catalog.php';
$courseOptions = [];
foreach ($programCatalog as $level => $awards) {
    foreach ($awards as $award => $courses) {
        $degree = $level . ' - ' . $award;
        foreach ($courses as $courseName) {
            $courseOptions[$degree . '|' . $courseName] = [
                'course_name' => $courseName,
                'degree' => $degree,
            ];
        }
    }
}
$id = filter_var($_GET['id'] ?? $_POST['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id) {
    header('Location: courses_list.php');
    exit;
}

$pdo = db();
$statement = $pdo->prepare('SELECT * FROM courses WHERE id = :id');
$statement->execute(['id' => $id]);
$course = $statement->fetch();

if (!$course) {
    http_response_code(404);
    exit('ไม่พบข้อมูลหลักสูตร');
}

$error = '';
$form = [
    'course_code' => (string) ($course['course_code'] ?? ''),
    'course_selection' => '',
    'course_name' => (string) ($course['course_name'] ?? ''),
    'faculty' => (string) ($course['faculty'] ?? ''),
    'degree' => (string) ($course['degree'] ?? ''),
    'department' => (string) ($course['department'] ?? ''),
    'description' => (string) ($course['description'] ?? ''),
    'credits' => (string) ($course['credits'] ?? ''),
    'status' => (string) ($course['status'] ?? 'active'),
];
foreach ($courseOptions as $selection => $program) {
    if ($program['course_name'] === $form['course_name'] && $program['degree'] === $form['degree']) {
        $form['course_selection'] = $selection;
        break;
    }
}
if ($form['course_selection'] === '') {
    $form['course_selection'] = '__legacy__';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $form = [
        'course_code' => trim((string) ($_POST['course_code'] ?? '')),
        'course_selection' => trim((string) ($_POST['course_selection'] ?? '')),
        'course_name' => '',
        'degree' => '',
        'faculty' => trim((string) ($_POST['faculty'] ?? '')),
        'department' => trim((string) ($_POST['department'] ?? '')),
        'description' => trim((string) ($_POST['description'] ?? '')),
        'credits' => trim((string) ($_POST['credits'] ?? '')),
        'status' => ($_POST['status'] ?? '') === 'inactive' ? 'inactive' : 'active',
    ];
    $selectedCourse = $courseOptions[$form['course_selection']] ?? null;
    if ($selectedCourse) {
        $form['course_name'] = $selectedCourse['course_name'];
        $form['degree'] = $selectedCourse['degree'];
    } elseif ($form['course_selection'] === '__legacy__') {
        $form['course_name'] = (string) $course['course_name'];
        $form['degree'] = (string) $course['degree'];
    }
    $credits = $form['credits'] === ''
        ? null
        : filter_var($form['credits'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    $allowedDepartments = $departmentCatalog[$form['faculty']] ?? [];
    $isUnchangedDepartment = $form['faculty'] === (string) ($course['faculty'] ?? '')
        && $form['department'] === (string) ($course['department'] ?? '');
    $invalidDepartment = $allowedDepartments
        ? ($form['department'] === '' || (!in_array($form['department'], $allowedDepartments, true) && !$isUnchangedDepartment))
        : ($form['department'] !== '' && !$isUnchangedDepartment);

    if ($form['degree'] === '') {
        $error = 'กรุณาเลือกชื่อหลักสูตรจากรายการ';
    } elseif (!in_array($form['faculty'], $facultyOptions, true) && $form['faculty'] !== (string) ($course['faculty'] ?? '')) {
        $error = 'กรุณาเลือกคณะ / วิทยาลัยจากรายการ';
    } elseif ($invalidDepartment) {
        $error = 'กรุณาเลือกสาขาจากรายการของคณะ';
    } elseif ($credits === false) {
        $error = 'หน่วยกิตต้องเป็นจำนวนเต็มตั้งแต่ 0 ขึ้นไป';
    } else {
        try {
            $statement = $pdo->prepare(
                'UPDATE courses
                SET course_code = :course_code,
                    course_name = :course_name,
                    faculty = :faculty,
                    degree = :degree,
                    department = :department,
                    description = :description,
                    credits = :credits,
                    status = :status
                WHERE id = :id'
            );
            $statement->execute([
                'course_code' => $form['course_code'] ?: null,
                'course_name' => $form['course_name'],
                'faculty' => $form['faculty'],
                'degree' => $form['degree'] ?: null,
                'department' => $form['department'] ?: null,
                'description' => $form['description'] ?: null,
                'credits' => $credits,
                'status' => $form['status'],
                'id' => $id,
            ]);

            header('Location: courses_list.php');
            exit;
        } catch (Throwable $exception) {
            error_log('Course update failed: ' . $exception->getMessage());
            $error = 'บันทึกการแก้ไขไม่สำเร็จ กรุณาลองอีกครั้ง';
        }
    }
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>แก้ไขหลักสูตร</title>
    <style>
        * { box-sizing: border-box; }
        body { min-height: 100vh; margin: 0; display: grid; grid-template-columns: 250px minmax(0, 1fr); background: #f4f7fa; color: #17324d; font-family: Arial, sans-serif; }
        .top { position: sticky; top: 0; height: 100vh; padding: 1.5rem 0; background: #17324d; color: #fff; }
        .top .wrap { display: flex; width: 100%; height: 100%; padding: 0 1.25rem; flex-direction: column; align-items: stretch; gap: 2rem; }
        .brand { display: flex; align-items: center; gap: .75rem; color: #fff; font-weight: 700; line-height: 1.35; text-decoration: none; }
        .brand-mark { width: 38px; height: 38px; display: grid; flex: 0 0 auto; place-items: center; border: 1px solid rgba(255, 255, 255, .45); border-left: 3px solid #c8a35c; font-family: Georgia, serif; font-size: 1.35rem; }
        .top nav { display: grid; gap: .35rem; }
        .top nav a { display: block; padding: .65rem .75rem; border-radius: 5px; color: rgba(255, 255, 255, .82); text-decoration: none; }
        .top nav a:hover, .top nav a:focus-visible, .top nav a[aria-current="page"] { background: rgba(255, 255, 255, .12); color: #fff; }
        .top nav a:focus-visible { outline: 2px solid #c8a35c; outline-offset: 2px; }
        .nav-user { display: grid; gap: .25rem; margin-top: auto; padding-top: 1rem; border-top: 1px solid rgba(255, 255, 255, .2); overflow-wrap: anywhere; }
        .nav-user-label { color: rgba(255, 255, 255, .65); font-size: .85rem; }
        .nav-user a { margin-top: .35rem; color: #fff; }
        .box { width: min(650px, calc(100% - 4rem)); margin: 2rem auto; padding: 1.5rem; border: 1px solid #d7e0ea; border-radius: 8px; background: #fff; }
        h1 { margin-top: 0; font-size: 1.4rem; }
        label { display: block; margin: .8rem 0 .3rem; font-weight: 700; }
        input, textarea, select { width: 100%; padding: .65rem; border: 1px solid #bdc9d6; border-radius: 5px; font: inherit; }
        textarea { min-height: 110px; resize: vertical; }
        input:focus-visible, textarea:focus-visible, select:focus-visible, a:focus-visible, button:focus-visible { outline: 3px solid #6c9dce; outline-offset: 2px; }
        button, .button { display: inline-block; margin-top: 1rem; padding: .65rem .9rem; border: 0; border-radius: 5px; background: #1e4a7a; color: #fff; text-decoration: none; cursor: pointer; font: inherit; }
        .secondary { border: 1px solid #bdc9d6; background: #fff; color: #1e4a7a; }
        .error { padding: .7rem; border-radius: 5px; background: #fff0f0; color: #a42121; }
        @media (max-width: 760px) {
            body { grid-template-columns: 1fr; }
            .top { position: static; height: auto; padding: .9rem 0; }
            .top .wrap { width: min(1100px, 92vw); height: auto; gap: .9rem; }
            .top nav { display: flex; flex-wrap: wrap; gap: .25rem; }
            .top nav a { padding: .5rem .65rem; }
            .nav-user { width: 100%; display: flex; align-items: center; flex-wrap: wrap; gap: .4rem .75rem; margin: 0; padding-top: .75rem; }
            .nav-user a { margin: 0 0 0 auto; }
        }
    </style>
    <link rel="stylesheet" href="../admin.css">
</head>
<body>
    <header class="top">
        <div class="wrap">
            <a class="brand" href="../../index.html">
                <span class="brand-mark" aria-hidden="true"><img src="../../img/images.jpg" alt=""></span>
                <span>ระบบจัดการเว็บไซต์</span>
            </a>
            <?php render_admin_nav('courses', '../'); ?>
            <div class="nav-user">
                <span class="nav-user-label">ผู้ดูแลระบบ</span>
                <strong><?= escape((string) $_SESSION['admin_username']) ?></strong>
                <a href="../logout.php">ออกจากระบบ</a>
            </div>
        </div>
    </header>
    <main class="box">
        <h1>แก้ไขข้อมูลหลักสูตร</h1>

        <?php if ($error !== ''): ?>
            <p class="error" role="alert"><?= escape($error) ?></p>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= (int) $id ?>">

            <label for="course_code">รหัสหลักสูตร</label>
            <input id="course_code" name="course_code" maxlength="50" value="<?= escape($form['course_code']) ?>">

            <label for="course_selection">ชื่อหลักสูตร / สาขาวิชา</label>
            <select id="course_selection" name="course_selection" required>
                <?php if ($form['course_selection'] === '__legacy__'): ?>
                    <optgroup label="ข้อมูลหลักสูตรเดิม">
                        <option value="__legacy__" selected><?= escape($form['course_name']) ?></option>
                    </optgroup>
                <?php endif; ?>
                <?php foreach ($programCatalog as $level => $awards): ?>
                    <?php foreach ($awards as $award => $courses): ?>
                        <optgroup label="<?= escape($level . ' - ' . $award) ?>">
                            <?php foreach ($courses as $courseName): ?>
                                <?php $selection = $level . ' - ' . $award . '|' . $courseName; ?>
                                <option value="<?= escape($selection) ?>" <?= $form['course_selection'] === $selection ? 'selected' : '' ?>><?= escape($courseName) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </select>
            <small>ระบบกำหนดระดับการศึกษาและชื่อปริญญาตามรายการที่เลือก</small>

            <label for="faculty">คณะ / วิทยาลัย</label>
            <select id="faculty" name="faculty" required>
                <?php if ($form['faculty'] !== '' && !in_array($form['faculty'], $facultyOptions, true)): ?>
                    <option value="<?= escape($form['faculty']) ?>" selected><?= escape($form['faculty']) ?> (ข้อมูลเดิม)</option>
                <?php endif; ?>
                <option value="" disabled <?= $form['faculty'] === '' ? 'selected' : '' ?>>เลือกคณะ / วิทยาลัย</option>
                <?php foreach ($facultyOptions as $faculty): ?>
                    <option value="<?= escape($faculty) ?>" <?= $form['faculty'] === $faculty ? 'selected' : '' ?>><?= escape($faculty) ?></option>
                <?php endforeach; ?>
            </select>

            <label for="department">สาขา</label>
            <select id="department" name="department">
                <option value="" <?= $form['department'] === '' ? 'selected' : '' ?>>เลือกคณะก่อน</option>
                <?php if ($form['department'] !== '' && !in_array($form['department'], $departmentCatalog[$form['faculty']] ?? [], true)): ?>
                    <optgroup label="สาขาเดิม" data-faculty="<?= escape($form['faculty']) ?>">
                        <option value="<?= escape($form['department']) ?>" selected><?= escape($form['department']) ?> (ข้อมูลเดิม)</option>
                    </optgroup>
                <?php endif; ?>
                <?php foreach ($departmentCatalog as $faculty => $departments): ?>
                    <optgroup label="<?= escape($faculty) ?>" data-faculty="<?= escape($faculty) ?>" <?= $form['faculty'] !== $faculty ? 'hidden' : '' ?>>
                        <?php foreach ($departments as $department): ?>
                            <option value="<?= escape($department) ?>" <?= $form['department'] === $department ? 'selected' : '' ?>><?= escape($department) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>

            <label for="credits">หน่วยกิต</label>
            <input id="credits" name="credits" type="number" min="0" step="1" value="<?= escape($form['credits']) ?>">

            <label for="description">รายละเอียดหลักสูตร</label>
            <textarea id="description" name="description"><?= escape($form['description']) ?></textarea>

            <label for="status">สถานะ</label>
            <select id="status" name="status">
                <option value="active" <?= $form['status'] === 'active' ? 'selected' : '' ?>>เปิดสอน</option>
                <option value="inactive" <?= $form['status'] === 'inactive' ? 'selected' : '' ?>>ปิดการใช้งาน</option>
            </select>

            <button type="submit">บันทึกการแก้ไข</button>
            <a class="button secondary" href="courses_list.php">ยกเลิก</a>
        </form>
    </main>
<script>
const facultySelect=document.getElementById("faculty");
const departmentSelect=document.getElementById("department");
const updateDepartments=(reset=false)=>{
    let hasDepartments=false;
    departmentSelect.querySelectorAll("optgroup[data-faculty]").forEach(group=>{
        const visible=group.dataset.faculty===facultySelect.value;
        group.hidden=!visible;
        if(visible && group.querySelector("option")) hasDepartments=true;
    });
    if(reset) departmentSelect.value="";
    departmentSelect.disabled=!hasDepartments;
    departmentSelect.required=hasDepartments;
    departmentSelect.options[0].textContent=facultySelect.value
        ? (hasDepartments ? "เลือกสาขา" : "คณะนี้ยังไม่มีรายการสาขา")
        : "เลือกคณะก่อน";
    if(!hasDepartments) departmentSelect.value="";
};
facultySelect.addEventListener("change",()=>updateDepartments(true));
updateDepartments();
</script>
</body>
</html>