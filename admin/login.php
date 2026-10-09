<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true,
    ]);
}
require_once __DIR__ . '/db.php';

if (!empty($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$username = '';
$redirect = $_GET['redirect'] ?? 'dashboard.php';
$validRedirect = static fn(mixed $value): bool => is_string($value)
  && (preg_match('/^[a-zA-Z0-9_.-]+\.php$/', $value)
    || preg_match('/^Management%20Modules\/[a-zA-Z0-9_.-]+\.php$/', $value));
if (!$validRedirect($redirect)) {
    $redirect = 'dashboard.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verify_csrf();
  $postedRedirect = $_POST['redirect'] ?? $redirect;
  if ($validRedirect($postedRedirect)) {
    $redirect = $postedRedirect;
    }

  $username = trim((string) ($_POST['username'] ?? ''));
  $password = (string) ($_POST['password'] ?? '');

  if ($username === '' || $password === '') {
    $error = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน';
  } else {
    try {
      $statement = db()->prepare('SELECT id, username, password_hash FROM admins WHERE username = :username LIMIT 1');
      $statement->execute(['username' => $username]);
      $admin = $statement->fetch();

      if ($admin && password_verify($password, (string) $admin['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int) $admin['id'];
        $_SESSION['admin_username'] = (string) $admin['username'];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        header('Location: ' . $redirect);
        exit;
      }

      $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
    } catch (Throwable $exception) {
      error_log('Admin login failed: ' . $exception->getMessage());
      $error = 'ระบบไม่สามารถเชื่อมต่อฐานข้อมูลได้ กรุณาลองใหม่อีกครั้ง';
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
  <title>เข้าสู่ระบบผู้ดูแล</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root{color-scheme:light;--green:#174b36;--green-dark:#103a2a;--green-bright:#267650;--ink:#24342c;--muted:#718078;--line:#d8e1db;--paper:#fff;--canvas:#edf2ed;--gold:#c8a35c;--danger:#a53c35}
    *{box-sizing:border-box}
    body{margin:0;min-height:100vh;min-height:100svh;padding:32px;display:grid;place-items:center;background-color:var(--canvas);background-image:linear-gradient(rgba(23,75,54,.035) 1px,transparent 1px),linear-gradient(90deg,rgba(23,75,54,.035) 1px,transparent 1px);background-size:32px 32px;color:var(--ink);font-family:'Sarabun','Leelawadee UI',Tahoma,sans-serif;line-height:1.55}
    .login-shell{width:min(100%,1040px);min-height:min(680px,calc(100svh - 64px));display:grid;grid-template-columns:.88fr 1.12fr;background:var(--paper);border:1px solid rgba(16,58,42,.12);border-radius:10px;overflow:hidden;box-shadow:0 24px 70px rgba(25,55,39,.14);animation:arrive .45s ease-out both}
    .brand-panel{position:relative;isolation:isolate;display:flex;flex-direction:column;justify-content:space-between;overflow:hidden;padding:48px 46px;background:var(--green);color:#fff}
    .brand-panel::before{position:absolute;z-index:-1;inset:0;content:"";background:linear-gradient(135deg,rgba(16,58,42,.88),rgba(16,58,42,.72)),url("../img/images.jpg") center/cover no-repeat}
    .brand-panel::after{position:absolute;z-index:-1;right:-130px;bottom:-210px;width:410px;height:410px;border:1px solid rgba(200,163,92,.3);content:"";transform:rotate(35deg)}
    .brand-top{display:flex;align-items:center;gap:13px;font-size:.92rem;font-weight:600}
    .brand-mark{width:42px;height:42px;display:grid;place-items:center;border:1px solid rgba(255,255,255,.42);border-left:3px solid var(--gold);font-family:Georgia,serif;font-size:1.55rem;line-height:1}
    .brand-mark img{width:100%;height:100%;object-fit:contain}
    .brand-copy{position:relative;margin:76px 0 54px}
    .brand-kicker{margin:0 0 12px;color:#d6c394;font-size:.78rem;font-weight:700;letter-spacing:.08em}
    .brand-copy h2{max-width:360px;margin:0;font-size:2.15rem;font-weight:600;line-height:1.32}
    .brand-copy p{max-width:330px;margin:16px 0 0;color:rgba(255,255,255,.76);font-size:1rem}
    .brand-footer{display:flex;align-items:center;gap:10px;color:rgba(255,255,255,.66);font-size:.84rem}
    .brand-footer::before{width:26px;height:1px;background:var(--gold);content:""}
    .form-panel{display:grid;place-items:center;padding:56px 64px;background:var(--paper)}
    .form-content{width:min(100%,370px)}
    .eyebrow{margin:0 0 10px;color:var(--green-bright);font-size:.78rem;font-weight:700;letter-spacing:.08em}
    h1{margin:0;color:var(--ink);font-size:2rem;font-weight:700;line-height:1.3}
    .intro{margin:9px 0 30px;color:var(--muted);font-size:.98rem}
    .error{margin:0 0 20px;padding:12px 14px;border:1px solid #edc5c1;border-left:3px solid var(--danger);border-radius:5px;background:#fff5f3;color:#812f29;font-size:.92rem}
    .field{margin-top:19px}
    label{display:block;margin-bottom:7px;color:#34463c;font-size:.91rem;font-weight:600}
    input{width:100%;height:50px;padding:0 14px;border:1px solid var(--line);border-radius:5px;background:#fff;color:var(--ink);font:inherit;font-size:1rem;transition:border-color .18s,box-shadow .18s}
    input::placeholder{color:#a1aca5}
    input:hover{border-color:#a8b8ad}
    input:focus-visible{border-color:var(--green-bright);outline:0;box-shadow:0 0 0 3px rgba(38,118,80,.15)}
    .password-wrap{position:relative}
    .password-wrap input{padding-right:72px}
    .password-toggle{position:absolute;top:50%;right:8px;min-width:52px;padding:6px 8px;transform:translateY(-50%);border:0;background:transparent;color:var(--green-bright);font:inherit;font-size:.84rem;font-weight:700;cursor:pointer}
    .password-toggle:hover{text-decoration:underline}
    .password-toggle:focus-visible,.submit-button:focus-visible,.back-link:focus-visible{outline:3px solid rgba(38,118,80,.32);outline-offset:3px}
    .submit-button{width:100%;min-height:50px;margin-top:27px;border:1px solid var(--green);border-radius:5px;background:var(--green);color:#fff;font:inherit;font-size:1rem;font-weight:700;cursor:pointer;transition:background .18s,border-color .18s,transform .18s}
    .submit-button:hover{transform:translateY(-1px);border-color:var(--green-dark);background:var(--green-dark)}
    .back-link{display:inline-block;margin-top:24px;color:var(--muted);font-size:.9rem;text-decoration:underline;text-decoration-color:#bec9c1;text-underline-offset:4px}
    .back-link:hover{color:var(--green)}
    @keyframes arrive{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
    @media(max-width:760px){body{padding:18px}.login-shell{min-height:0;grid-template-columns:1fr}.brand-panel{min-height:205px;padding:24px 28px}.brand-copy{margin:25px 0 16px}.brand-copy h2{font-size:1.55rem}.brand-copy p{margin-top:7px;font-size:.9rem}.brand-footer{font-size:.76rem}.form-panel{padding:38px 28px 34px}.intro{margin-bottom:23px}}
    @media(max-width:420px){body{padding:0}.login-shell{min-height:100svh;border:0;border-radius:0}.brand-panel{min-height:190px;padding:20px 22px}.brand-copy{margin:19px 0 12px}.brand-copy h2{font-size:1.4rem}.brand-copy p{font-size:.86rem}.brand-mark{width:36px;height:36px}.form-panel{padding:32px 24px}.brand-footer{font-size:.72rem}}
    @media(prefers-reduced-motion:reduce){*,*::before,*::after{scroll-behavior:auto!important;animation-duration:.01ms!important;animation-iteration-count:1!important;transition-duration:.01ms!important}}
  </style>
</head>
<body>
  <main class="login-shell">
    <section class="brand-panel" aria-label="ระบบจัดการเว็บไซต์">
      <div class="brand-top"><span class="brand-mark" aria-hidden="true"><img src="../img/images.jpg" alt=""></span><span>ระบบจัดการเว็บไซต์</span></div>
      <div class="brand-copy">
        <p class="brand-kicker">ADMINISTRATION</p>
        <h2>จัดการเว็บไซต์<br>ได้ในที่เดียว</h2>
        <p>พื้นที่สำหรับผู้ดูแลเนื้อหาและข้อมูลของเว็บไซต์</p>
      </div>
      <div class="brand-footer">สำหรับผู้ดูแลระบบ</div>
    </section>
    <section class="form-panel" aria-labelledby="login-title">
      <div class="form-content">
        <p class="eyebrow">ยินดีต้อนรับกลับ</p>
        <h1 id="login-title">เข้าสู่ระบบ</h1>
        <p class="intro">กรอกข้อมูลบัญชีผู้ดูแลเพื่อดำเนินการต่อ</p>
        <?php if ($error): ?><p class="error" role="alert" aria-live="polite"><?= escape($error) ?></p><?php endif; ?>
        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
          <input type="hidden" name="redirect" value="<?= escape($redirect) ?>">
          <div class="field">
            <label for="username">ชื่อผู้ใช้</label>
            <input id="username" name="username" type="text" value="<?= escape($username) ?>" required autocomplete="username" autofocus>
          </div>
          <div class="field">
            <label for="password">รหัสผ่าน</label>
            <div class="password-wrap">
              <input id="password" name="password" type="password" required autocomplete="current-password">
              <button class="password-toggle" type="button" aria-controls="password" aria-pressed="false">แสดง</button>
            </div>
          </div>
          <button class="submit-button" type="submit">เข้าสู่ระบบ</button>
        </form>
        <a class="back-link" href="../index.html">กลับไปยังเว็บไซต์หลัก</a>
      </div>
    </section>
  </main>
  <script>
    const passwordInput = document.getElementById('password');
    const passwordToggle = document.querySelector('.password-toggle');
    passwordToggle.addEventListener('click', () => {
      const isVisible = passwordInput.type === 'text';
      passwordInput.type = isVisible ? 'password' : 'text';
      passwordToggle.textContent = isVisible ? 'แสดง' : 'ซ่อน';
      passwordToggle.setAttribute('aria-pressed', String(!isVisible));
    });
  </script>
</body></html>