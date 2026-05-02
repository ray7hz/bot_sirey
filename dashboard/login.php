<?php
declare(strict_types=1);

require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

startSession();

if (!empty($_SESSION['admin_id'])) {
    redirectTo('dashboard.php');
}

$error    = '';
$nisValue = '';

if (!empty($_GET['err'])) {
    $error = match((string)$_GET['err']) {
        'noperm'   => 'Anda tidak memiliki akses ke halaman yang diminta.',
        'readonly' => 'Akun kepala sekolah hanya memiliki akses baca (read-only).',
        default    => '',
    };
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nisValue = trim((string)($_POST['nis'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($nisValue === '' || $password === '') {
        $error = 'NIS/NIP dan password wajib diisi.';
    } else {
        $account = fetchAccountByNis($nisValue);

        if (!$account) {
            $error = 'Akun tidak ditemukan.';
        } elseif (!in_array($account['role'], ['admin','guru','kurikulum','kepala_sekolah'], true)) {
            $error = 'Akun ini tidak memiliki akses ke panel web.';
        } elseif (!verifyAccountPassword($account, $password)) {
            $error = 'Password tidak sesuai.';
            auditLog(null, 'login_gagal', 'akun', null, ['nis_nip' => $nisValue], 'gagal');
        } else {
            setAdminSession($account['akun_id'], $account['nis_nip'], $account['role'], $account['nama_lengkap']);
            auditLog($account['akun_id'], 'login', 'akun', $account['akun_id'], ['via' => 'web']);
            redirectTo('dashboard.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — SKADACI</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
  <style>
    :root {
      --primary: #E67E22;
      --primary-dark: #D35400;
      --accent: #16A085;
      --secondary: #27AE60;
    }
    * { box-sizing: border-box; }
    body {
      font-family: 'DM Sans', sans-serif;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: url('assets/background2.png') center/cover no-repeat fixed;
      overflow: hidden;
      position: relative;
    }

    /* Animated background - Background 2 */
    .bg-shapes {
      position: fixed; inset: 0; z-index: 0; overflow: hidden;
      background: radial-gradient(ellipse at center, rgba(0,0,0,.3) 0%, rgba(0,0,0,.5) 100%);
    }
    
    /* Dark overlay for readability */
    .bg-shapes::before {
      content: '';
      position: absolute; inset: 0;
      background: rgba(0,0,0,.35);
      pointer-events: none;
    }
    
    /* Floating cards effect - hidden for background image */
    .shape {
      position: absolute;
      border-radius: 20px;
      border: 1px solid rgba(255,255,255,.05);
      backdrop-filter: blur(10px);
      animation: float-card 6s ease-in-out infinite;
      opacity: 0;
      display: none;
    }
    .shape-1 {
      width: 300px; height: 300px;
      background: #FF6B4A;
      top: -80px; left: -100px;
      animation-delay: 0s;
    }
    .shape-2 {
      width: 250px; height: 250px;
      background: #5B9BD5;
      bottom: -100px; right: -50px;
      animation-delay: -2s;
    }
    .shape-3 {
      width: 280px; height: 280px;
      background: #2D7A3F;
      top: 40%; left: 5%;
      animation-delay: -4s;
    }
    @keyframes float-card {
      0%, 100% { transform: translateY(0) rotate(0deg); }
      50%       { transform: translateY(-20px) rotate(2deg); }
    }

    /* Animated dots grid - hidden for background image */
    .bg-shapes::after {
      content: '';
      position: absolute; inset: 0;
      background-image: 
        radial-gradient(circle, rgba(255,255,255,.08) 1px, transparent 1px);
      background-size: 60px 60px;
      background-position: 0 0;
      pointer-events: none;
      z-index: 1;
      opacity: 0;
    }

    /* Login card */
    .login-wrapper {
      position: relative; z-index: 1;
      width: 100%; max-width: 420px;
      padding: 20px;
    }

    .login-card {
      background: rgba(20,30,50,.75);
      backdrop-filter: blur(30px);
      -webkit-backdrop-filter: blur(30px);
      border: 1px solid rgba(255,255,255,.15);
      border-radius: 20px;
      padding: 0;
      overflow: hidden;
      box-shadow: 0 25px 60px rgba(0,0,0,.7), 0 0 0 1px rgba(255,255,255,.1);
      animation: slideUp .5s cubic-bezier(.34,1.56,.64,1);
    }

    @keyframes slideUp {
      from { opacity: 0; transform: translateY(30px) scale(.97); }
      to   { opacity: 1; transform: translateY(0) scale(1); }
    }

    .login-head {
      background: linear-gradient(135deg, #E67E22 0%, #F39C12 40%, #16A085 70%, #27AE60 100%);
      padding: 36px 32px 28px;
      text-align: center;
      position: relative;
      overflow: hidden;
    }
    .login-head::before {
      content: '';
      position: absolute;
      width: 200px; height: 200px;
      background: rgba(255,255,255,.07);
      border-radius: 50%;
      top: -60px; right: -60px;
    }
    .login-head::after {
      content: '';
      position: absolute;
      width: 120px; height: 120px;
      background: rgba(255,255,255,.05);
      border-radius: 50%;
      bottom: -30px; left: -20px;
    }

    .logo-icon {
      width: 70px; height: 70px;
      background: transparent;
      border: none;
      border-radius: 16px;
      display: inline-flex; align-items: center; justify-content: center;
      font-size: 26px; color: #fff;
      font-weight: 800;
      font-family: 'Plus Jakarta Sans', sans-serif;
      margin-bottom: 14px;
      position: relative; z-index: 1;
      backdrop-filter: none;
      overflow: hidden;
    }
    
    .logo-icon img {
      width: 100%;
      height: 100%;
      object-fit: contain;
      border-radius: 8px;
    }

    .login-title {
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 22px;
      font-weight: 800;
      color: #fff;
      margin-bottom: 4px;
      position: relative; z-index: 1;
    }
    .login-subtitle {
      font-size: 13px;
      color: rgba(255,255,255,.65);
      position: relative; z-index: 1;
    }

    .login-body {
      padding: 28px 32px 32px;
      background: #212D3E;
    }

    .form-label {
      font-size: 12.5px;
      font-weight: 600;
      color: rgba(255,255,255,.7);
      margin-bottom: 6px;
    }

    .input-group-text {
      background: rgba(255,255,255,.06);
      border: 1.5px solid rgba(255,255,255,.1);
      border-right: none;
      color: rgba(255,255,255,.4);
      border-radius: 10px 0 0 10px;
    } 

    .form-control {
      background: rgba(255,255,255,.06);
      border: 1.5px solid rgba(255,255,255,.1);
      border-left: none;
      color: #f1f5f9;
      border-radius: 0 10px 10px 0;
      font-size: 14px;
      padding: 11px 14px;
    }
    .form-control::placeholder { color: rgba(255,255,255,.25); }
    .form-control:focus {
      background: rgba(255,255,255,.09);
      border-color: rgba(230,126,34,.6);
      box-shadow: 0 0 0 3px rgba(230,126,34,.2);
      color: #f1f5f9;
      outline: none;
    }
    .input-group:focus-within .input-group-text {
      border-color: rgba(230,126,34,.6);
      color: rgba(255,255,255,.7);
    }

    .btn-toggle-pw {
      background: rgba(255,255,255,.06);
      border: 1.5px solid rgba(255,255,255,.1);
      border-left: none;
      border-radius: 0 10px 10px 0;
      color: rgba(255,255,255,.4);
      padding: 0 14px;
      cursor: pointer;
      transition: color .2s;
    }
    .btn-toggle-pw:hover { color: rgba(255,255,255,.8); }
    .input-group:focus-within .btn-toggle-pw { border-color: rgba(37,99,235,.6); }

    /* Separate form-control that is in a group with pw toggle */
    .pw-control {
      border-radius: 0 !important;
      border-right: none !important;
    }

    .btn-login {
      width: 100%;
      padding: 13px;
      background: var(--primary);
      border: none;
      border-radius: 10px;
      color: #fff;
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 14px;
      font-weight: 700;
      cursor: pointer;
      transition: all .2s;
      letter-spacing: .3px;
      display: flex; align-items: center; justify-content: center; gap: 8px;
      margin-top: 8px;
    }
    .btn-login:hover {
      background: var(--primary-dark);
      transform: translateY(-1px);
      box-shadow: 0 8px 24px rgba(230,126,34,.4);
    }
    .btn-login:active { transform: translateY(0); }

    .alert-login {
      background: rgba(220,38,38,.15);
      border: 1px solid rgba(220,38,38,.3);
      border-radius: 10px;
      color: #fca5a5;
      font-size: 13px;
      padding: 12px 14px;
      margin-bottom: 18px;
      display: flex; align-items: center; gap: 8px;
    }

    .role-info {
      margin-top: 20px;
      padding: 12px 14px;
      background: rgba(255,255,255,.04);
      border: 1px solid rgba(255,255,255,.07);
      border-radius: 10px;
      font-size: 12px;
      color: rgba(255,255,255,.4);
      text-align: center;
      line-height: 1.6;
    }
    .role-info strong { color: rgba(255,255,255,.6); }
  </style>
</head>
<body>

<div class="bg-shapes">
  <div class="shape shape-1"></div>
  <div class="shape shape-2"></div>
  <div class="shape shape-3"></div>
</div>

<div class="login-wrapper">
  <div class="login-card">

    <!-- HEAD -->
    <div class="login-head">
      <div class="logo-icon">
        <img src="assets/logo-smkn2.png" alt="Logo SMK Negeri 2 Cimahi">
      </div>
      <div class="login-title">SKADACI</div>
      <div class="login-subtitle">Panel Manajemen Internal Sekolah</div>
    </div>

    <!-- BODY -->
    <div class="login-body">

      <?php if ($error !== ''): ?>
        <div class="alert-login">
          <i class="bi bi-exclamation-triangle-fill"></i>
          <?php echo htmlspecialchars($error); ?>
        </div>
      <?php endif; ?>

      <form method="POST" novalidate>

        <div class="mb-3">
          <label class="form-label" for="nis">NIS / NIP</label>
          <div class="input-group">
            <span class="input-group-text">
              <i class="bi bi-person"></i>
            </span>
            <input
              id="nis" name="nis" type="text"
              class="form-control"
              placeholder="Masukkan NIS atau NIP"
              value="<?php echo htmlspecialchars($nisValue); ?>"
              autocomplete="username"
              autofocus required>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label" for="password">Password</label>
          <div class="input-group">
            <span class="input-group-text">
              <i class="bi bi-lock"></i>
            </span>
            <input
              id="password" name="password" type="password"
              class="form-control pw-control"
              placeholder="Masukkan password"
              autocomplete="current-password" required>
            <button type="button" class="btn-toggle-pw" id="togglePw" title="Tampilkan password">
              <i class="bi bi-eye" id="pwIcon"></i>
            </button>
          </div>
        </div>

        <button type="submit" class="btn-login">
          <i class="bi bi-box-arrow-in-right"></i>
          Masuk ke Panel
        </button>

      </form>

      <div class="role-info">
        Role yang dapat masuk:<br>
        <strong>Admin</strong> · <strong>Guru</strong> · <strong>Kurikulum</strong> · <strong>Kepala Sekolah</strong>
      </div>

    </div><!-- .login-body -->
  </div><!-- .login-card -->
</div>

<script>
  const togglePw = document.getElementById('togglePw');
  const pwInput  = document.getElementById('password');
  const pwIcon   = document.getElementById('pwIcon');

  togglePw.addEventListener('click', () => {
    const isText = pwInput.type === 'text';
    pwInput.type   = isText ? 'password' : 'text';
    pwIcon.className = isText ? 'bi bi-eye' : 'bi bi-eye-slash';
  });
</script>

</body>
</html>