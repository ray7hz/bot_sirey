<?php
declare(strict_types=1);

require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

startSession();

if (!empty($_SESSION['admin_id'])) {
    redirectTo('dashboard.php');
}

$error = '';
$nisValue = '';

// Check for error parameter from redirects
if (!empty($_GET['err'])) {
    $err_code = (string)$_GET['err'];
    if ($err_code === 'noperm') {
        $error = '⚠️ Anda tidak memiliki akses ke halaman yang diminta. Silakan login dengan akun yang sesuai.';
    } elseif ($err_code === 'readonly') {
        $error = '⚠️ Akun kepala sekolah hanya memiliki akses baca (read-only).';
    }
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
        } elseif (!in_array($account['role'], ['admin', 'guru', 'kurikulum', 'kepala_sekolah'], true)) {
            $error = 'Akun ini tidak memiliki akses ke panel web.';
        } elseif (!verifyAccountPassword($account, $password)) {
            $error = 'Password tidak sesuai.';
            auditLog(null, 'login_gagal', 'akun', null, ['nis_nip' => $nisValue], 'gagal');
        } else {
            setAdminSession(
                $account['akun_id'],
                $account['nis_nip'],
                $account['role'],
                $account['nama_lengkap']
            );

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
  <title>Login - Bot SiRey</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<div class="login-page">
  <div class="login-box">
    <div class="login-head">
      <h1>Bot SiRey</h1>
      <p>Panel Internal Sekolah</p>
    </div>

    <div class="login-body">
      <?php if ($error !== ''): ?>
        <div class="alert alert-error">
          <?php echo htmlspecialchars($error); ?>
        </div>
      <?php endif; ?>

      <form method="POST" novalidate>
        <div class="form-group">
          <label class="form-label" for="nis">NIS / NIP</label>
          <input
            id="nis"
            name="nis"
            type="text"
            class="form-control"
            required
            autofocus
            autocomplete="username"
            placeholder="Masukkan NIS atau NIP Anda"
            value="<?php echo htmlspecialchars($nisValue); ?>"
          >
        </div>

        <div class="form-group">
          <label class="form-label" for="password">Password</label>
          <input
            id="password"
            name="password"
            type="password"
            class="form-control"
            required
            autocomplete="current-password"
            placeholder="Masukkan password Anda"
          >
        </div>

        <button type="submit" class="login-submit">Masuk ke Panel</button>
      </form>

      <p style="margin-top:20px; font-size:12px; color:var(--clr-muted); line-height:1.7;">
        Role yang dapat masuk: <strong>Admin</strong>, <strong>Guru</strong>,
        <strong>Kurikulum</strong>, dan <strong>Kepala Sekolah</strong>.
      </p>
    </div>
  </div>
</div>

</body>
</html>
