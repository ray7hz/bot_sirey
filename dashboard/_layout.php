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
    'dashboard'            => ['icon' => 'bi-speedometer2',     'label' => 'Dashboard',      'href' => 'dashboard.php',            'permission' => 'view_dashboard'],
    'users'                => ['icon' => 'bi-people-fill',      'label' => 'Pengguna',       'href' => 'users.php',                'permission' => 'view_users'],
    'grup'                 => ['icon' => 'bi-mortarboard-fill', 'label' => 'Kelas',   'href' => 'grup.php',                 'permission' => 'view_grup'],
    'kelas_saya'           => ['icon' => 'bi-mortarboard',      'label' => 'Kelas Saya',     'href' => 'kelas_saya.php',           'permission' => 'view_kelas_saya'],
    'mata_pelajaran'       => ['icon' => 'bi-book-fill',        'label' => 'Mata Pelajaran', 'href' => 'mata_pelajaran.php',       'permission' => 'view_mata_pelajaran'],
    'manage_guru_mengajar' => ['icon' => 'bi-building-fill',    'label' => 'Manage Guru',  'href' => 'manage_guru_mengajar.php', 'permission' => 'manage_guru_mengajar'],
    'jadwal'               => ['icon' => 'bi-calendar3',        'label' => 'Jadwal',         'href' => 'jadwal.php',               'permission' => 'view_jadwal'],
    'tugas'                => ['icon' => 'bi-journal-text',     'label' => 'Tugas',          'href' => 'tugas.php',                'permission' => 'view_tugas'],
    'penilaian'            => ['icon' => 'bi-star-fill',        'label' => 'Penilaian',      'href' => 'penilaian.php',            'permission' => 'view_penilaian'],
    'notifikasi'           => ['icon' => 'bi-bell-fill',        'label' => 'Notifikasi',     'href' => 'notifikasi.php',           'permission' => 'view_notifikasi'],
    'pengumuman'           => ['icon' => 'bi-megaphone-fill',   'label' => 'Pengumuman',     'href' => 'pengumuman.php',           'permission' => 'view_pengumuman'],
    'audit_log'            => ['icon' => 'bi-shield-check',     'label' => 'Audit Log',      'href' => 'audit_log.php',            'permission' => 'view_audit_log'],
];

function roleBadgeClass(string $role): string {
    return match($role) {
        'admin'          => 'bg-danger',
        'guru'           => 'bg-success',
        'siswa'          => 'bg-warning text-dark',
        'kurikulum'      => 'bg-primary',
        'kepala_sekolah' => 'bg-info text-dark',
        default          => 'bg-secondary',
    };
}

function roleLabel(string $role): string {
    return match($role) {
        'admin'          => 'Admin',
        'guru'           => 'Guru',
        'siswa'          => 'Siswa',
        'kurikulum'      => 'Kurikulum',
        'kepala_sekolah' => 'Kepala Sekolah',
        default          => ucfirst($role),
    };
}

function menuVisible(array $item, array $data_admin_rayhanrp): bool {
    $permission = (string)($item['permission'] ?? '');
    return $permission === '' ? true : can($permission, $data_admin_rayhanrp);
}

// Hitung notifikasi belum dibaca (jumlah broadcast terbaru, max 99)
$notif_count = 0;
$stmt_notif = sirey_query('SELECT COUNT(*) AS total FROM notifikasi_rayhanRP WHERE waktu_kirim >= DATE_SUB(NOW(), INTERVAL 7 DAY)');
if ($stmt_notif) {
    $row_notif = sirey_fetch($stmt_notif);
    $notif_count = min((int)($row_notif['total'] ?? 0), 99);
}
?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($judul_halaman_rayhanrp); ?> — SKADACI BOT</title>

  <!-- Bootstrap 5.3 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <!-- DataTables -->
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,400&display=swap" rel="stylesheet">

  <style>
    :root {
      --sidebar-w: 260px;
      --navbar-h: 64px;
      --primary: #E67E22;
      --primary-dark: #D35400;
      --primary-light: #FEF5E7;
      --accent: #16A085;
      --secondary: #27AE60;
      --sidebar-bg: #212D3E;
      --sidebar-text: #94a3b8;
      --sidebar-active: #E67E22;
      --surface: #ffffff;
      --bg: #f1f5f9;
      --border: #e2e8f0;
      --text: #1e293b;
      --muted: #64748b;
      --success: #059669;
      --danger: #dc2626;
      --warning: #d97706;
      --radius: 12px;
      --shadow: 0 1px 3px rgba(0,0,0,.08), 0 4px 16px rgba(15,23,42,.06);
      --shadow-md: 0 4px 12px rgba(15,23,42,.12);
      --transition: all .2s ease;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--bg);
      color: var(--text);
      font-size: 14px;
      line-height: 1.6;
    }

    h1,h2,h3,h4,h5,h6 {
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-weight: 700;
    }

    /* ── SCROLLBAR ── */
    ::-webkit-scrollbar { width: 5px; height: 5px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 99px; }

    /* ── NAVBAR ── */
    .app-navbar {
      position: fixed; top: 0; left: 0; right: 0;
      height: var(--navbar-h);
      background: var(--sidebar-bg);
      display: flex; align-items: center; justify-content: space-between;
      padding: 0 20px 0 0;
      z-index: 1040;
      box-shadow: 0 2px 16px rgba(0,0,0,.25);
    }

    .navbar-brand-block {
      width: var(--sidebar-w);
      display: flex; align-items: center; gap: 12px;
      padding: 0 20px;
      flex-shrink: 0;
    }

    .navbar-logo {
      width: 42px; height: 42px;
      background: transparent;
      border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      font-size: 18px; color: #fff;
      font-weight: 800;
      font-family: 'Plus Jakarta Sans', sans-serif;
      flex-shrink: 0;
      overflow: hidden;
    }
    
    .navbar-logo img {
      width: 100%;
      height: 100%;
      object-fit: contain;
      border-radius: 8px;
    }

    .navbar-title {
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-weight: 800;
      font-size: 17px;
      color: #fff;
      letter-spacing: -.3px;
    }

    .navbar-title span {
      color: var(--accent);
    }

    .navbar-right {
      display: flex; align-items: center; gap: 8px;
    }

    .nav-icon-btn {
      width: 38px; height: 38px;
      border-radius: 10px;
      border: none;
      background: rgba(255,255,255,.08);
      color: #94a3b8;
      display: flex; align-items: center; justify-content: center;
      font-size: 17px;
      cursor: pointer;
      transition: var(--transition);
      position: relative;
      text-decoration: none;
    }
    .nav-icon-btn:hover { background: rgba(255,255,255,.14); color: #fff; }

    .badge-dot {
      position: absolute; top: 6px; right: 6px;
      width: 8px; height: 8px;
      background: var(--accent);
      border-radius: 50%;
      border: 2px solid var(--sidebar-bg);
    }

    .nav-divider {
      width: 1px; height: 24px;
      background: rgba(255,255,255,.1);
      margin: 0 4px;
    }

    .nav-user {
      display: flex; align-items: center; gap: 10px;
      padding: 6px 10px;
      border-radius: 10px;
      background: rgba(255,255,255,.06);
      cursor: pointer;
      transition: var(--transition);
      text-decoration: none;
    }
    .nav-user:hover { background: rgba(255,255,255,.12); }

    .nav-avatar {
      width: 32px; height: 32px;
      border-radius: 9px;
      background: var(--primary);
      color: #fff;
      font-weight: 700;
      font-size: 13px;
      display: flex; align-items: center; justify-content: center;
      font-family: 'Plus Jakarta Sans', sans-serif;
      flex-shrink: 0;
    }

    .nav-user-info { line-height: 1.2; }
    .nav-user-name { font-size: 13px; font-weight: 600; color: #f1f5f9; }
    .nav-user-role { font-size: 11px; color: #64748b; }

    /* ── SIDEBAR ── */
    .app-sidebar {
      position: fixed; top: var(--navbar-h); left: 0; bottom: 0;
      width: var(--sidebar-w);
      background: var(--sidebar-bg);
      overflow-y: auto;
      overflow-x: hidden;
      padding: 16px 12px 24px;
      z-index: 1030;
      transition: transform .3s ease;
    }

    .sidebar-section-label {
      font-size: 10px;
      font-weight: 700;
      letter-spacing: 1.2px;
      text-transform: uppercase;
      color: #5a6980;
      padding: 12px 10px 6px;
      margin-top: 4px;
    }

    .sidebar-link {
      display: flex; align-items: center; gap: 10px;
      padding: 10px 12px;
      border-radius: 9px;
      color: #94a3b8;
      text-decoration: none;
      font-size: 13.5px;
      font-weight: 500;
      transition: var(--transition);
      margin-bottom: 2px;
      position: relative;
    }
    .sidebar-link:hover {
      background: rgba(255,255,255,.07);
      color: #e2e8f0;
    }
    .sidebar-link.active {
      background: var(--primary);
      color: #fff;
      font-weight: 600;
      box-shadow: 0 4px 12px rgba(230,126,34,.35);
    }
    .sidebar-link i {
      font-size: 15px;
      flex-shrink: 0;
      width: 18px;
      text-align: center;
    }

    .sidebar-link .sidebar-badge {
      margin-left: auto;
      font-size: 10px;
      background: var(--accent);
      color: #fff;
      padding: 1px 6px;
      border-radius: 20px;
      font-weight: 700;
    }

    /* ── MAIN ── */
    .app-main {
      margin-left: var(--sidebar-w);
      padding-top: var(--navbar-h);
      min-height: 100vh;
    }

    .main-content {
      padding: 28px 28px 40px;
      max-width: 1400px;
    }

    /* ── PAGE HEADER ── */
    .page-header {
      margin-bottom: 24px;
    }
    .page-header h2 {
      font-size: 22px;
      color: #0f172a;
      margin-bottom: 4px;
      display: flex; align-items: center; gap: 8px;
    }
    .page-header p {
      color: var(--muted);
      font-size: 13.5px;
      margin: 0;
    }

    /* ── CARDS ── */
    .card {
      border: 1px solid var(--border);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      background: var(--surface);
    }
    .card-header {
      border-bottom: 1px solid var(--border);
      border-radius: var(--radius) var(--radius) 0 0 !important;
      background: #fafbfc;
      padding: 16px 20px;
    }
    .card-header h5, .card-header h6 {
      margin: 0;
      font-size: 15px;
      font-weight: 700;
      color: #0f172a;
    }
    .card-body { padding: 20px; }

    /* ── STAT CARDS ── */
    .stat-card {
      border-radius: var(--radius);
      padding: 22px 20px;
      display: flex; align-items: center; gap: 16px;
      box-shadow: var(--shadow);
      border: 1px solid var(--border);
      background: var(--surface);
      transition: var(--transition);
    }
    .stat-card:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow-md);
    }
    .stat-icon {
      width: 52px; height: 52px;
      border-radius: 14px;
      display: flex; align-items: center; justify-content: center;
      font-size: 22px;
      flex-shrink: 0;
    }
    .stat-value {
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 28px;
      font-weight: 800;
      color: #0f172a;
      line-height: 1;
    }
    .stat-label {
      font-size: 12px;
      color: var(--muted);
      margin-top: 3px;
      font-weight: 500;
    }
    .stat-trend {
      font-size: 11px;
      font-weight: 600;
      margin-top: 2px;
    }

    /* ── TABLES ── */
    .table { font-size: 13.5px; }
    .table thead th {
      background: #f8fafc;
      font-size: 11px;
      font-weight: 700;
      letter-spacing: .7px;
      text-transform: uppercase;
      color: var(--muted);
      border-bottom: 2px solid var(--border);
      padding: 10px 14px;
      white-space: nowrap;
    }
    .table tbody td { padding: 12px 14px; vertical-align: middle; }
    .table tbody tr { transition: background .12s; }
    .table tbody tr:hover td { background: #f8fafc; }

    /* ── BUTTONS ── */
    .btn {
      font-family: 'DM Sans', sans-serif;
      font-weight: 600;
      border-radius: 8px;
      font-size: 13px;
      transition: var(--transition);
    }
    .btn-primary {
      background: var(--primary);
      border-color: var(--primary);
    }
    .btn-primary:hover {
      background: var(--primary-dark);
      border-color: var(--primary-dark);
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(230,126,34,.3);
    }
    .btn-sm { font-size: 12px; padding: 4px 10px; border-radius: 6px; }

    /* ── BADGES ── */
    .badge { font-size: 11px; font-weight: 600; padding: 4px 9px; border-radius: 20px; }

    /* ── FORMS ── */
    .form-control, .form-select {
      border-radius: 8px;
      border: 1.5px solid var(--border);
      font-size: 13.5px;
      font-family: 'DM Sans', sans-serif;
      transition: border-color .15s, box-shadow .15s;
    }
    .form-control:focus, .form-select:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(230,126,34,.1);
    }
    .form-label { font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 5px; }

    /* ── ALERTS ── */
    .alert { border-radius: 10px; font-size: 13.5px; border: none; }
    .alert-success { background: #d1fae5; color: #065f46; }
    .alert-danger  { background: #fee2e2; color: #991b1b; }
    .alert-info    { background: #FEF5E7; color: #B8860B; }
    .alert-warning { background: #fef3c7; color: #92400e; }

    /* ── MODALS ── */
    .modal-content { border-radius: 14px; border: none; box-shadow: 0 20px 60px rgba(0,0,0,.2); }
    .modal-header { border-bottom: 1px solid var(--border); padding: 18px 22px; }
    .modal-footer { border-top: 1px solid var(--border); padding: 14px 22px; }
    .modal-title { font-size: 16px; font-weight: 700; }
    .modal-body  { padding: 22px; }

    /* ── DATATABLES OVERRIDES ── */
    div.dataTables_wrapper div.dataTables_filter input,
    div.dataTables_wrapper div.dataTables_length select {
      border-radius: 8px;
      border: 1.5px solid var(--border);
      font-size: 13px;
      padding: 5px 10px;
    }
    div.dataTables_wrapper div.dataTables_info { font-size: 12px; color: var(--muted); }
    div.dataTables_wrapper div.dataTables_paginate .paginate_button {
      border-radius: 6px !important;
      font-size: 12px;
    }
    div.dataTables_wrapper div.dataTables_paginate .paginate_button.current {
      background: var(--primary) !important;
      border-color: var(--primary) !important;
      color: #fff !important;
    }

    /* ── EMPTY STATE ── */
    .empty-state { text-align: center; padding: 60px 20px; color: var(--muted); }
    .empty-state i { font-size: 48px; opacity: .3; display: block; margin-bottom: 12px; }
    .empty-state p { font-size: 14px; }

    /* ── RESPONSIVE ── */
    @media (max-width: 991.98px) {
      .app-sidebar {
        transform: translateX(-100%);
      }
      .app-sidebar.show {
        transform: translateX(0);
        box-shadow: 4px 0 24px rgba(0,0,0,.3);
      }
      .app-main { margin-left: 0; }
      .navbar-brand-block { width: auto; }
      .main-content { padding: 20px 16px 32px; }
    }

    /* ── SIDEBAR TOGGLE BUTTON ── */
    #sidebarToggle {
      display: none;
    }
    @media (max-width: 991.98px) {
      #sidebarToggle { display: flex; }
    }

    /* ── OVERLAY ── */
    #sidebarOverlay {
      display: none;
      position: fixed; inset: 0;
      background: rgba(0,0,0,.4);
      z-index: 1029;
    }
    #sidebarOverlay.show { display: block; }

    /* ── LOADING BAR ── */
    #loadingBar {
      position: fixed; top: 0; left: 0; height: 3px;
      background: var(--primary);
      z-index: 9999;
      transition: width .3s ease;
      width: 0;
    }

    /* ── CUSTOM SCROLLBAR SIDEBAR ── */
    .app-sidebar::-webkit-scrollbar { width: 3px; }
    .app-sidebar::-webkit-scrollbar-thumb { background: #334155; }
  </style>
</head>
<body>

<div id="loadingBar"></div>
<div id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- ═══ NAVBAR ═══ -->
<nav class="app-navbar">
  <div class="navbar-brand-block">
    <button id="sidebarToggle" class="nav-icon-btn" onclick="toggleSidebar()">
      <i class="bi bi-list"></i>
    </button>
    <div class="navbar-logo">
      <img src="assets/logo-smkn2.png" alt="Logo SMK Negeri 2 Cimahi">
    </div>
    <div class="navbar-title">SKADACI</div>
  </div>

  <div class="navbar-right">
    <!-- Notifikasi (hidden untuk admin) -->
    <?php if ($data_admin_rayhanrp['role'] !== 'admin' && $data_admin_rayhanrp['role'] !== 'guru'): ?>
    <a href="notifikasi.php" class="nav-icon-btn" title="Notifikasi">
      <i class="bi bi-bell"></i>
      <?php if ($notif_count > 0): ?>
        <span class="badge-dot"></span>
      <?php endif; ?>
    </a>

    <div class="nav-divider"></div>
    <?php endif; ?>

    <!-- User Dropdown -->
    <div class="dropdown">
      <div class="nav-user" data-bs-toggle="dropdown" aria-expanded="false">
        <div class="nav-avatar">
          <?php echo strtoupper(substr($data_admin_rayhanrp['name'], 0, 1)); ?>
        </div>
        <div class="nav-user-info d-none d-sm-block">
          <div class="nav-user-name"><?php echo htmlspecialchars($data_admin_rayhanrp['name']); ?></div>
          <div class="nav-user-role"><?php echo roleLabel($data_admin_rayhanrp['role']); ?></div>
        </div>
        <i class="bi bi-chevron-down ms-1" style="font-size:11px; color:#64748b;"></i>
      </div>
      <ul class="dropdown-menu dropdown-menu-end mt-2 shadow-sm" style="border-radius:12px; min-width:180px; border:1px solid var(--border);">
        <li>
          <div class="px-3 py-2 border-bottom">
            <div style="font-size:13px; font-weight:700; color:#0f172a;">
              <?php echo htmlspecialchars($data_admin_rayhanrp['name']); ?>
            </div>
            <span class="badge <?php echo roleBadgeClass($data_admin_rayhanrp['role']); ?> mt-1" style="font-size:10px;">
              <?php echo roleLabel($data_admin_rayhanrp['role']); ?>
            </span>
          </div>
        </li>
        <li>
          <form method="POST" class="m-0">
            <button type="submit" name="logout" value="1" class="dropdown-item text-danger fw-semibold" style="font-size:13px; padding: 10px 16px;">
              <i class="bi bi-box-arrow-right me-2"></i>Keluar
            </button>
          </form>
        </li>
      </ul>
    </div>
  </div>
</nav>

<!-- ═══ SIDEBAR ═══ -->
<aside class="app-sidebar" id="appSidebar">

  <div class="sidebar-section-label">Navigasi</div>

  <?php foreach (MENU_ITEMS as $key => $item): ?>
    <?php if (!menuVisible($item, $data_admin_rayhanrp)) continue; ?> 
    <a href="<?php echo $item['href']; ?>"
       class="sidebar-link <?php echo $menu_aktif_rayhanrp === $key ? 'active' : ''; ?>">
      <i class="bi <?php echo $item['icon']; ?>"></i>
      <?php echo htmlspecialchars($item['label']); ?>
      <?php if ($key === 'notifikasi' && $notif_count > 0): ?>
        <span class="sidebar-badge"><?php echo $notif_count; ?></span>
      <?php endif; ?>
    </a>
  <?php endforeach; ?>

  <!-- Footer sidebar -->
  <div class="mt-auto pt-3" style="border-top: 1px solid #5a6980; margin-top: 24px !important;">
    <div style="font-size: 11px; color: #5a6980; text-align: center; padding: 8px 0;">
      Rayhan Rizky P. &copy; <?php echo date('Y'); ?>
    </div>
  </div>

</aside>

<!-- ═══ MAIN CONTENT ═══ -->
<main class="app-main">
  <div class="main-content">

<?php

function layoutEnd(): void {
    echo "\n  </div><!-- .main-content -->\n</main><!-- .app-main -->\n";
    ?>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- DataTables -->
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<script>
// ── Sidebar toggle (mobile) ──
function toggleSidebar() {
  const sidebar  = document.getElementById('appSidebar');
  const overlay  = document.getElementById('sidebarOverlay');
  sidebar.classList.toggle('show');
  overlay.classList.toggle('show');
}
function closeSidebar() {
  document.getElementById('appSidebar').classList.remove('show');
  document.getElementById('sidebarOverlay').classList.remove('show');
}

// ── Loading bar ──
window.addEventListener('beforeunload', () => {
  const bar = document.getElementById('loadingBar');
  bar.style.width = '70%';
});
window.addEventListener('load', () => {
  const bar = document.getElementById('loadingBar');
  bar.style.width = '100%';
  setTimeout(() => bar.style.opacity = '0', 400);
});

// ── SweetAlert confirm untuk form delete ──
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('form[data-confirm]').forEach(form => {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      const msg = this.dataset.confirm || 'Apakah Anda yakin?';
      Swal.fire({
        title: 'Konfirmasi',
        text: msg,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal',
        borderRadius: '12px',
      }).then(result => {
        if (result.isConfirmed) this.submit();
      });
    });
  });

  // Auto-dismiss flash alerts
  document.querySelectorAll('.alert-auto-dismiss').forEach(el => {
    setTimeout(() => {
      el.style.transition = 'opacity .5s';
      el.style.opacity = '0';
      setTimeout(() => el.remove(), 500);
    }, 4000);
  });
});

// ── Global DataTable defaults ──
if (typeof $.fn.DataTable !== 'undefined') {
  $.extend(true, $.fn.dataTable.defaults, {
    language: {
      search: '',
      searchPlaceholder: 'Cari...',
      lengthMenu: 'Tampilkan _MENU_ baris',
      info: 'Menampilkan _START_–_END_ dari _TOTAL_ data',
      infoEmpty: 'Tidak ada data',
      zeroRecords: 'Data tidak ditemukan',
      paginate: { previous: '‹', next: '›' }
    },
    responsive: true,
    pageLength: 25,
    dom: '<"row align-items-center mb-3"<"col-sm-6"l><"col-sm-6 text-end"f>>rt<"row align-items-center mt-3"<"col-sm-6"i><"col-sm-6"p>>',
  });
}
</script>

<?php
}