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
require_once __DIR__ . '/admin/site_settings.php';

$settingsAvailable = true;
try {
    $siteSettings = site_settings();
} catch (Throwable $exception) {
    error_log('Registration settings load failed: ' . $exception->getMessage());
    $siteSettings = site_settings_defaults();
    $settingsAvailable = false;
}

$allRoles = [
    'student' => ['table' => 'students', 'id' => 'student_id', 'label' => 'นักศึกษา', 'fields' => ['faculty', 'major', 'class_year']],
    'personnel' => ['table' => 'personnel', 'id' => 'personnel_id', 'label' => 'บุคลากร', 'fields' => ['faculty', 'department', 'position']],
    'alumni' => ['table' => 'alumni', 'id' => 'alumni_id', 'label' => 'ศิษย์เก่า', 'fields' => ['faculty', 'major', 'phone', 'graduation_year', 'current_job']],
];
$roles = array_filter(
    $allRoles,
    static fn(array $role, string $key): bool => ($siteSettings['registration_' . $key] ?? '1') === '1',
    ARRAY_FILTER_USE_BOTH
);
$fieldLabels = [
    'faculty' => 'คณะ',
    'major' => 'สาขาวิชา',
    'class_year' => 'ชั้นปี',
    'department' => 'หน่วยงาน',
    'position' => 'ตำแหน่ง',
    'phone' => 'เบอร์โทรศัพท์',
    'graduation_year' => 'ปีที่สำเร็จการศึกษา (พ.ศ.)',
    'current_job' => 'อาชีพ / สถานที่ทำงาน',
];
$facultyOptions = require __DIR__ . '/admin/course_faculties.php';
$personnelDepartmentOptions = [
    'คณะครุศาสตร์',
    'คณะวิทยาศาสตร์',
    'คณะมนุษยศาสตร์',
    'คณะบริหารธุรกิจ',
    'ฝ่ายวิชาการ',
    'ฝ่ายบุคคล',
    'งานทะเบียน',
    'งานเทคโนโลยีสารสนเทศ',
];
$personnelPositionOptions = [
    'อาจารย์',
    'ครู',
    'ผู้ช่วยศาสตราจารย์',
    'รองศาสตราจารย์',
    'เจ้าหน้าที่',
    'หัวหน้าภาควิชา',
];
$departmentCatalog = require __DIR__ . '/admin/course_departments.php';
$alumniJobOptions = [
    'การศึกษา' => [
        ['🏫', 'ครู / อาจารย์'],
        ['🎓', 'อาจารย์มหาวิทยาลัย'],
        ['📚', 'บุคลากรทางการศึกษา'],
        ['🧑‍🏫', 'ติวเตอร์ / วิทยากร'],
    ],
    'ภาครัฐและสาธารณสุข' => [
        ['🏛️', 'ข้าราชการ'],
        ['🗂️', 'พนักงานราชการ'],
        ['🏥', 'บุคลากรทางการแพทย์'],
        ['🚓', 'ทหาร / ตำรวจ'],
        ['🏘️', 'องค์กรปกครองส่วนท้องถิ่น'],
    ],
    'ธุรกิจและวิชาชีพ' => [
        ['🏢', 'พนักงานบริษัทเอกชน'],
        ['💼', 'เจ้าของกิจการ / ธุรกิจส่วนตัว'],
        ['📊', 'บัญชี / การเงิน'],
        ['💻', 'เทคโนโลยีสารสนเทศ'],
        ['🛍️', 'การตลาด / การขาย'],
        ['🌾', 'เกษตรกรรม'],
        ['🧑‍💻', 'ฟรีแลนซ์'],
    ],
    'สถานะการทำงาน' => [
        ['🔎', 'กำลังหางาน'],
        ['🏡', 'ประกอบอาชีพอิสระ'],
        ['🌱', 'กำลังศึกษาต่อ'],
        ['🌤️', 'เกษียณอายุ'],
    ],
];
$alumniTagGroups = [
    'faculty' => ['คณะ / วิทยาลัย' => array_map(static fn(string $faculty): array => ['🏛️', $faculty], $facultyOptions)],
    'major' => $departmentCatalog,
    'current_job' => $alumniJobOptions,
];
$form = array_fill_keys(['role', 'member_id', 'prefix', 'firstname', 'lastname', 'email', 'faculty', 'major', 'class_year', 'department', 'position', 'phone', 'graduation_year', 'current_job'], '');
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach ($form as $key => $_value) {
        $form[$key] = trim((string) ($_POST[$key] ?? ''));
    }
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');

    if (!$settingsAvailable) {
        $error = 'ระบบไม่สามารถโหลดการตั้งค่าได้ กรุณาลองใหม่ภายหลัง';
    } elseif (!isset($allRoles[$form['role']])) {
        $error = 'กรุณาเลือกประเภทสมาชิก';
    } elseif (!isset($roles[$form['role']])) {
        $error = 'ขณะนี้ปิดรับสมัครสมาชิกประเภทนี้';
    } elseif ($form['member_id'] === '' || strlen($form['member_id']) > 15) {
        $error = 'กรุณากรอกรหัสสมาชิกไม่เกิน 15 ตัวอักษร';
    } elseif ($form['firstname'] === '' || strlen($form['firstname']) > 100 || $form['lastname'] === '' || strlen($form['lastname']) > 100) {
        $error = 'กรุณากรอกชื่อและนามสกุลให้ครบถ้วน (ไม่เกิน 100 ตัวอักษรต่อช่อง)';
    } elseif ($form['prefix'] !== '' && strlen($form['prefix']) > 20) {
        $error = 'คำนำหน้าต้องไม่เกิน 20 ตัวอักษร';
    } elseif ($form['email'] !== '' && (!filter_var($form['email'], FILTER_VALIDATE_EMAIL) || strlen($form['email']) > 100)) {
        $error = 'กรุณาตรวจสอบอีเมล (ไม่เกิน 100 ตัวอักษร)';
    } elseif (strlen($password) < 8 || strlen($password) > 72) {
        $error = 'รหัสผ่านต้องมีความยาว 8 ถึง 72 ตัวอักษร';
    } elseif (!hash_equals($password, $passwordConfirmation)) {
        $error = 'ยืนยันรหัสผ่านไม่ตรงกัน';
    } else {
        $limits = [
            'faculty' => 100,
            'major' => 100,
            'class_year' => 1,
            'department' => 100,
            'position' => 100,
            'phone' => 15,
            'graduation_year' => 4,
            'current_job' => 150,
        ];
        foreach ($roles[$form['role']]['fields'] as $field) {
            if (strlen($form[$field]) > $limits[$field]) {
                $error = $fieldLabels[$field] . 'ยาวเกินกำหนด';
                break;
            }
        }
        if ($error === '' && $form['role'] === 'personnel') {
            $personnelChoices = [
                'faculty' => $facultyOptions,
                'department' => $personnelDepartmentOptions,
                'position' => $personnelPositionOptions,
            ];
            foreach ($personnelChoices as $field => $options) {
                if ($form[$field] !== '' && !in_array($form[$field], $options, true)) {
                    $error = 'กรุณาเลือก' . $fieldLabels[$field] . 'จากรายการ';
                    break;
                }
            }
        }
        if ($error === '' && $form['role'] === 'student' && $form['class_year'] !== ''
            && filter_var($form['class_year'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 9]]) === false) {
            $error = 'ชั้นปีต้องเป็นจำนวนเต็มตั้งแต่ 1 ถึง 9';
        }
        if ($error === '' && $form['role'] === 'alumni' && $form['graduation_year'] !== ''
            && filter_var($form['graduation_year'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 2400, 'max_range' => 3000]]) === false) {
            $error = 'ปีที่สำเร็จการศึกษาต้องเป็น พ.ศ. ที่ถูกต้อง';
        }

        if ($error === '') {
            $role = $roles[$form['role']];
            $columns = [$role['id'], 'password', 'prefix', 'firstname', 'lastname', 'email'];
            $values = [
                $form['member_id'],
                password_hash($password, PASSWORD_DEFAULT),
                $form['prefix'] ?: null,
                $form['firstname'],
                $form['lastname'],
                $form['email'] ?: null,
            ];
            foreach ($role['fields'] as $field) {
                $columns[] = $field;
                $values[] = $form[$field] === '' ? null : $form[$field];
            }
            $quotedColumns = '`' . implode('`, `', $columns) . '`';
            $placeholders = implode(', ', array_fill(0, count($columns), '?'));

            try {
                $statement = db()->prepare('INSERT INTO `' . $role['table'] . '` (' . $quotedColumns . ') VALUES (' . $placeholders . ')');
                $statement->execute($values);
                $success = true;
                $form['member_id'] = '';
                $form['firstname'] = '';
                $form['lastname'] = '';
                $form['email'] = '';
            } catch (PDOException $exception) {
                if ($exception->getCode() === '23000') {
                    $error = 'รหัสสมาชิกนี้มีในระบบแล้ว';
                } else {
                    error_log('Member registration failed: ' . $exception->getMessage());
                    $error = 'สมัครสมาชิกไม่สำเร็จ กรุณาลองใหม่';
                }
            } catch (Throwable $exception) {
                error_log('Member registration failed: ' . $exception->getMessage());
                $error = 'ระบบไม่สามารถเชื่อมต่อฐานข้อมูลได้ กรุณาลองใหม่';
            }
        }
    }
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#174b36">
    <title>สมัครสมาชิก | <?= escape($siteSettings['site_name']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { color-scheme: light; --green: #174b36; --green-dark: #103a2a; --ink: #24342c; --muted: #718078; --line: #d8e1db; --paper: #fff; --canvas: #edf2ed; --danger: #a53c35; }
        * { box-sizing: border-box; }
        body { min-height: 100vh; margin: 0; padding: 32px 18px; background: repeating-linear-gradient(0deg, transparent 0 31px, rgba(23,75,54,.035) 31px 32px), repeating-linear-gradient(90deg, transparent 0 31px, rgba(23,75,54,.035) 31px 32px), var(--canvas); color: var(--ink); font-family: 'Sarabun', 'Leelawadee UI', Tahoma, sans-serif; line-height: 1.55; }
        main { width: min(760px, 100%); margin: 0 auto; padding: 30px; border: 1px solid rgba(16,58,42,.12); border-radius: 8px; background: var(--paper); box-shadow: 0 18px 55px rgba(25,55,39,.1); }
        .brand { margin: 0 0 6px; color: var(--green); font-weight: 700; }
        h1 { margin: 0; font-size: 1.8rem; }
        .intro { margin: 4px 0 24px; color: var(--muted); }
        .message { margin: 0 0 18px; padding: 11px 14px; border: 1px solid #c8e3d1; background: #f1faf3; color: #245a35; }
        .error { border-color: #edc5c1; background: #fff5f3; color: #812f29; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0 16px; }
        .field { min-width: 0; margin-bottom: 13px; }
        .wide { grid-column: 1 / -1; }
        label { display: block; margin-bottom: 5px; font-weight: 600; }
        input, select { width: 100%; min-height: 44px; padding: 9px 11px; border: 1px solid var(--line); border-radius: 4px; background: #fff; color: var(--ink); font: inherit; }
        input:focus-visible, select:focus-visible, button:focus-visible, a:focus-visible { outline: 3px solid rgba(38,118,80,.25); outline-offset: 2px; }
        fieldset { margin: 12px 0 18px; padding: 14px; border: 1px solid var(--line); }
        legend { padding: 0 6px; font-weight: 700; }
        .tag-picker { margin-top: .55rem; }
        .tag-picker summary { width: fit-content; padding: .35rem .55rem; border: 1px solid var(--line); border-radius: 4px; color: var(--green); cursor: pointer; font-size: .85rem; font-weight: 600; }
        .tag-picker summary:hover, .tag-picker[open] summary { background: #f1f7f2; }
        .tag-picker[open] summary { margin-bottom: .6rem; }
        .tag-groups { display: grid; gap: .65rem; padding: .75rem; border: 1px solid var(--line); border-radius: 4px; background: #fbfcfb; }
        .tag-group-title { margin: 0 0 .4rem; color: var(--muted); font-size: .82rem; font-weight: 700; }
        .tag-options { display: flex; flex-wrap: wrap; gap: .4rem; }
        .tag-option { display: inline-flex; align-items: center; gap: .35rem; min-height: 34px; padding: .35rem .55rem; border: 1px solid var(--line); border-radius: 4px; background: #fff; color: var(--ink); font: inherit; font-size: .84rem; cursor: pointer; }
        .tag-option:hover { border-color: #91b8a0; background: #f1f7f2; }
        .actions { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
        button { min-height: 44px; padding: 9px 18px; border: 1px solid var(--green); border-radius: 4px; background: var(--green); color: #fff; font: inherit; font-weight: 700; cursor: pointer; }
        a { color: var(--green); }
        @media (max-width: 600px) { body { padding: 14px; } main { padding: 20px 16px; } .grid { grid-template-columns: 1fr; } .wide { grid-column: auto; } }
    </style>
</head>
<body>
<main>
    <p class="brand"><?= escape($siteSettings['site_name']) ?></p>
    <h1>สมัครสมาชิก</h1>
    <p class="intro">เลือกประเภทสมาชิกและกรอกข้อมูลสำหรับลงทะเบียน</p>
    <?php if ($siteSettings['contact_email'] !== '' || $siteSettings['contact_phone'] !== ''): ?>
        <p class="intro">ติดต่อสอบถาม<?= $siteSettings['contact_phone'] !== '' ? ' โทร. ' . escape($siteSettings['contact_phone']) : '' ?><?= $siteSettings['contact_phone'] !== '' && $siteSettings['contact_email'] !== '' ? ' · ' : '' ?><?= $siteSettings['contact_email'] !== '' ? escape($siteSettings['contact_email']) : '' ?></p>
    <?php endif; ?>

    <?php if ($success): ?><p class="message" role="status">สมัครสมาชิกเรียบร้อยแล้ว ข้อมูลถูกบันทึกลงฐานข้อมูล</p><?php endif; ?>
    <?php if ($error !== ''): ?><p class="message error" role="alert"><?= escape($error) ?></p><?php endif; ?>

    <?php if (!$settingsAvailable): ?>
        <p class="message error" role="alert">ขณะนี้ไม่สามารถตรวจสอบสถานะการสมัครสมาชิกได้ กรุณาลองใหม่ภายหลัง</p>
    <?php elseif (!$roles): ?>
        <p class="message" role="status">ขณะนี้ปิดรับสมัครสมาชิกทุกประเภท</p>
    <?php else: ?>
    <form method="post" autocomplete="on">
        <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
        <div class="grid">
            <div class="field wide">
                <label for="role">ประเภทสมาชิก</label>
                <select id="role" name="role" required>
                    <option value="">เลือกประเภทสมาชิก</option>
                    <?php foreach ($roles as $value => $role): ?>
                        <option value="<?= escape($value) ?>" <?= $form['role'] === $value ? 'selected' : '' ?>><?= escape($role['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field wide">
                <label for="member_id">รหัสสมาชิก</label>
                <input id="member_id" name="member_id" maxlength="15" required autocomplete="username" value="<?= escape($form['member_id']) ?>">
            </div>
            <div class="field">
                <label for="prefix">คำนำหน้า</label>
                <input id="prefix" name="prefix" maxlength="20" value="<?= escape($form['prefix']) ?>">
            </div>
            <div class="field"><label for="firstname">ชื่อ</label><input id="firstname" name="firstname" maxlength="100" required autocomplete="given-name" value="<?= escape($form['firstname']) ?>"></div>
            <div class="field"><label for="lastname">นามสกุล</label><input id="lastname" name="lastname" maxlength="100" required autocomplete="family-name" value="<?= escape($form['lastname']) ?>"></div>
            <div class="field"><label for="email">อีเมล</label><input id="email" name="email" type="email" maxlength="100" autocomplete="email" value="<?= escape($form['email']) ?>"></div>
        </div>

        <?php foreach ($roles as $roleKey => $role): ?>
            <fieldset data-role-fields="<?= escape($roleKey) ?>" <?= $form['role'] !== $roleKey ? 'hidden disabled' : '' ?>>
                <legend>ข้อมูล<?= escape($role['label']) ?></legend>
                <div class="grid">
                    <?php foreach ($role['fields'] as $field): ?>
                        <div class="field <?= in_array($field, ['faculty', 'major', 'current_job'], true) ? 'wide' : '' ?>">
                            <label for="<?= escape($roleKey . '_' . $field) ?>"><?= escape($fieldLabels[$field]) ?></label>
                            <?php if ($roleKey === 'personnel' && in_array($field, ['faculty', 'department', 'position'], true)): ?>
                                <?php
                                $options = match ($field) {
                                    'faculty' => $facultyOptions,
                                    'department' => $personnelDepartmentOptions,
                                    'position' => $personnelPositionOptions,
                                };
                                ?>
                                <select id="<?= escape($roleKey . '_' . $field) ?>" name="<?= escape($field) ?>">
                                    <option value="">เลือก<?= escape($fieldLabels[$field]) ?></option>
                                    <?php foreach ($options as $option): ?>
                                        <option value="<?= escape($option) ?>" <?= $form[$field] === $option ? 'selected' : '' ?>><?= escape($option) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif ($field === 'class_year' || $field === 'graduation_year'): ?>
                                <input id="<?= escape($roleKey . '_' . $field) ?>" name="<?= escape($field) ?>" type="number" min="<?= $field === 'class_year' ? '1' : '2400' ?>" max="<?= $field === 'class_year' ? '9' : '3000' ?>" value="<?= escape($form[$field]) ?>">
                            <?php else: ?>
                                <input id="<?= escape($roleKey . '_' . $field) ?>" name="<?= escape($field) ?>" maxlength="<?= $field === 'current_job' ? '150' : ($field === 'phone' ? '15' : '100') ?>" value="<?= escape($form[$field]) ?>">
                                <?php if ($roleKey === 'alumni' && isset($alumniTagGroups[$field])): ?>
                                    <details class="tag-picker" data-tag-picker data-target="<?= escape($roleKey . '_' . $field) ?>" data-field="<?= escape($field) ?>">
                                        <summary>เลือกตัวเลือก<?= escape($fieldLabels[$field]) ?></summary>
                                        <div class="tag-groups">
                                            <?php foreach ($alumniTagGroups[$field] as $group => $options): ?>
                                                <section>
                                                    <h2 class="tag-group-title"><?= escape($group) ?></h2>
                                                    <div class="tag-options">
                                                        <?php foreach ($options as $option): ?>
                                                            <?php [$icon, $value] = is_array($option) ? $option : ['📚', $option]; ?>
                                                            <button class="tag-option" type="button" data-tag-value="<?= escape($value) ?>" <?= $field === 'major' ? 'data-faculty="' . escape($group) . '"' : '' ?>><span aria-hidden="true"><?= escape($icon) ?></span><?= escape($value) ?></button>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </section>
                                            <?php endforeach; ?>
                                        </div>
                                    </details>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </fieldset>
        <?php endforeach; ?>

        <div class="grid">
            <div class="field"><label for="password">รหัสผ่าน (8-72 ตัวอักษร)</label><input id="password" name="password" type="password" minlength="8" maxlength="72" required autocomplete="new-password"></div>
            <div class="field"><label for="password_confirmation">ยืนยันรหัสผ่าน</label><input id="password_confirmation" name="password_confirmation" type="password" minlength="8" maxlength="72" required autocomplete="new-password"></div>
        </div>
        <div class="actions"><button type="submit">สมัครสมาชิก</button><a href="login.html">กลับไปหน้าเข้าสู่ระบบ</a></div>
    </form>
    <?php endif; ?>
</main>
<script>
const roleSelect = document.getElementById('role');
const roleFieldsets = document.querySelectorAll('[data-role-fields]');
const updateRoleFields = () => {
    roleFieldsets.forEach((fieldset) => {
        const active = fieldset.dataset.roleFields === roleSelect.value;
        fieldset.hidden = !active;
        fieldset.disabled = !active;
    });
};
roleSelect.addEventListener('change', updateRoleFields);
updateRoleFields();
document.querySelectorAll('[data-tag-picker]').forEach((picker) => {
    const input = document.getElementById(picker.dataset.target);
    picker.querySelectorAll('[data-tag-value]').forEach((button) => {
        button.addEventListener('click', () => {
            input.value = button.dataset.tagValue;
            input.dispatchEvent(new Event('input', { bubbles: true }));
            if (picker.dataset.field === 'major' && button.dataset.faculty) {
                document.getElementById('alumni_faculty').value = button.dataset.faculty;
            }
            picker.open = false;
            input.focus();
        });
    });
});
</script>
</body>
</html>
