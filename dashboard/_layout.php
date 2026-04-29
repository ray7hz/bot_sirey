<?php
declare(strict_types=1);

require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

startSession();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$data_admin_rayhanrp = requireAdminSession('login.php');

$judul_halaman_rayhanrp ??= 'Dashboard';
$menu_aktif_rayhanrp ??= 'dashboard';

const MENU_ITEMS = [
    'dashboard'            => ['icon' => '📊', 'label' => 'Dashboard',      'href' => 'dashboard.php',            'permission' => 'view_dashboard'],
    'users'                => ['icon' => '👥', 'label' => 'Pengguna',       'href' => 'users.php',                'permission' => 'view_users'],
    'grup'                 => ['icon' => '🎓', 'label' => 'Grup/Kelas',     'href' => 'grup.php',                 'permission' => 'view_grup'],
    'mata_pelajaran'       => ['icon' => '📚', 'label' => 'Mata Pelajaran', 'href' => 'mata_pelajaran.php',       'permission' => 'view_mata_pelajaran'],
    'manage_guru_mengajar' => ['icon' => '🏫', 'label' => 'Guru Mengajar',  'href' => 'manage_guru_mengajar.php', 'permission' => 'manage_guru_mengajar'],
    'jadwal'               => ['icon' => '📅', 'label' => 'Jadwal',         'href' => 'jadwal.php',               'permission' => 'view_jadwal'],
    'tugas'                => ['icon' => '📝', 'label' => 'Tugas',          'href' => 'tugas.php',                'permission' => 'view_tugas'],
    'penilaian'            => ['icon' => '⭐', 'label' => 'Penilaian',      'href' => 'penilaian.php',            'permission' => 'view_penilaian'],
    'notifikasi'           => ['icon' => '📢', 'label' => 'Notifikasi',     'href' => 'notifikasi.php',           'permission' => 'view_notifikasi'],
    'pengumuman'           => ['icon' => '📣', 'label' => 'Pengumuman',     'href' => 'pengumuman.php',           'permission' => 'view_pengumuman'],
    'audit_log'            => ['icon' => '🧾', 'label' => 'Audit Log',      'href' => 'audit_log.php',            'permission' => 'view_audit_log'],
];

function roleBadgeClass(string $role): string
{
    return match ($role) {
        'admin' => 'badge-admin',
        'guru' => 'badge-guru',
        'siswa' => 'badge-siswa',
        'kurikulum' => 'badge-kurikulum',
        'kepala_sekolah' => 'badge-kepsek',
        default => 'badge-default',
    };
}

function statusBadgeClass(string $status): string
{
    return 'badge-status-' . $status;
}

function menuVisible(array $item, array $data_admin_rayhanrp): bool
{
    $permission = (string)($item['permission'] ?? '');
    return $permission === '' ? true : can($permission, $data_admin_rayhanrp);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($judul_halaman_rayhanrp); ?> - Bot SiRey</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<nav class="navbar">
  <div class="navbar-brand">
    Bot SiRey
    <span class="brand-dot"></span>
  </div>
  <div class="navbar-user">
    <span><?php echo htmlspecialchars($data_admin_rayhanrp['name']); ?></span>
    <span class="role-badge"><?php echo strtoupper(str_replace('_', ' ', $data_admin_rayhanrp['role'])); ?></span>
    <form method="POST" style="margin:0">
      <button type="submit" name="logout" value="1" class="btn-logout">Keluar</button>
    </form>
  </div>
</nav>

<div class="layout">
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-section">
      <div class="sidebar-label">Navigasi</div>
      <?php foreach (MENU_ITEMS as $key => $item): ?>
        <?php if (!menuVisible($item, $data_admin_rayhanrp)) continue; ?>
        <a href="<?php echo $item['href']; ?>"
           class="sidebar-link <?php echo $menu_aktif_rayhanrp === $key ? 'active' : ''; ?>">
          <span class="icon"><?php echo $item['icon']; ?></span>
          <?php echo htmlspecialchars($item['label']); ?>
        </a>
      <?php endforeach; ?>
    </div>
  </aside>

  <main class="main">
<?php

function layoutEnd(): void
{
    echo "\n  </main>\n</div>\n</body>\n</html>\n";
}
