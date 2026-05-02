<?php
declare(strict_types=1);

$judul_halaman_rayhanrp = 'Dashboard';
$menu_aktif_rayhanrp    = 'dashboard';
require_once __DIR__ . '/_layout.php';

if (!can('view_dashboard', $data_admin_rayhanrp)) {
    session_destroy();
    header('Location: login.php?err=noperm');
    exit;
}

// ── Statistik ──
$totalAkun   = 0;
$totalGrup   = 0;
$totalJadwal = 0;
$totalTugas  = 0;

$r = sirey_fetch(sirey_query('SELECT COUNT(*) AS t FROM akun_rayhanRP'));
$totalAkun = (int)($r['t'] ?? 0);

$r = sirey_fetch(sirey_query('SELECT COUNT(*) AS t FROM grup_rayhanRP'));
$totalGrup = (int)($r['t'] ?? 0);

if ($data_admin_rayhanrp['role'] === 'guru') {
    $r = sirey_fetch(sirey_query('SELECT COUNT(*) AS t FROM guru_mengajar_rayhanRP WHERE akun_id=? AND aktif=1','i',$data_admin_rayhanrp['id']));
} else {
    $r = sirey_fetch(sirey_query('SELECT COUNT(*) AS t FROM guru_mengajar_rayhanRP WHERE aktif=1'));
}
$totalJadwal = (int)($r['t'] ?? 0);

if ($data_admin_rayhanrp['role'] === 'guru') {
    $r = sirey_fetch(sirey_query(
        'SELECT COUNT(*) AS t FROM tugas_rayhanRP t
         INNER JOIN guru_mengajar_rayhanRP gm ON t.grup_id=gm.grup_id
         WHERE gm.akun_id=? AND gm.aktif=1','i',$data_admin_rayhanrp['id']));
} else {
    $r = sirey_fetch(sirey_query('SELECT COUNT(*) AS t FROM tugas_rayhanRP'));
}
$totalTugas = (int)($r['t'] ?? 0);

// Tugas aktif (belum lewat deadline)
$rAktif = sirey_fetch(sirey_query("SELECT COUNT(*) AS t FROM tugas_rayhanRP WHERE status='active' AND tenggat >= NOW()"));
$tugasAktif = (int)($rAktif['t'] ?? 0);

// Pengumpulan hari ini
$rKumpul = sirey_fetch(sirey_query("SELECT COUNT(*) AS t FROM pengumpulan_rayhanRP WHERE DATE(waktu_kumpul)=CURDATE()"));
$kumpulHariIni = (int)($rKumpul['t'] ?? 0);

// Belum dinilai
$rBelum = sirey_fetch(sirey_query(
    "SELECT COUNT(*) AS t FROM pengumpulan_rayhanRP p
     LEFT JOIN penilaian_rayhanRP pn ON pn.pengumpulan_id=p.pengumpulan_id
     WHERE pn.penilaian_id IS NULL"
));
$belumDinilai = (int)($rBelum['t'] ?? 0);

// Notifikasi terbaru
$recentNotifs = sirey_fetchAll(sirey_query(
    'SELECT pesan, waktu_kirim, tipe FROM notifikasi_rayhanRP ORDER BY waktu_kirim DESC LIMIT 5'
));

// Tugas mendekati deadline (5 hari ke depan)
$deadlineTugas = sirey_fetchAll(sirey_query(
    "SELECT t.judul, t.tenggat, g.nama_grup, mp.nama AS matpel_nama,
            (SELECT COUNT(*) FROM pengumpulan_rayhanRP p WHERE p.tugas_id=t.tugas_id) AS jml_kumpul
     FROM tugas_rayhanRP t
     LEFT JOIN grup_rayhanRP g ON t.grup_id=g.grup_id
     LEFT JOIN mata_pelajaran_rayhanRP mp ON t.matpel_id=mp.matpel_id
     WHERE t.status='active' AND t.tenggat BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 5 DAY)
     ORDER BY t.tenggat ASC LIMIT 5"
));

// Aktivitas terbaru dari audit log
$recentActivity = sirey_fetchAll(sirey_query(
    "SELECT al.aksi, al.status, al.waktu, a.nama_lengkap, a.role
     FROM audit_log_rayhanRP al
     LEFT JOIN akun_rayhanRP a ON al.akun_id=a.akun_id
     ORDER BY al.waktu DESC LIMIT 8"
));
?>

<!-- Page Header -->
<div class="page-header d-flex align-items-start justify-content-between flex-wrap gap-2">
  <div>
    <h2><i class="bi bi-speedometer2 text-primary me-2"></i>Dashboard</h2>
    <p>Selamat datang kembali, <strong><?php echo htmlspecialchars($data_admin_rayhanrp['name']); ?></strong> 👋
      — <?php echo date('l, d F Y'); ?></p>
  </div>
  <span class="badge <?php echo roleBadgeClass($data_admin_rayhanrp['role']); ?> fs-6 px-3 py-2">
    <?php echo roleLabel($data_admin_rayhanrp['role']); ?>
  </span>
</div>

<!-- ── Stat Cards ── -->
<div class="row g-3 mb-4">

  <?php if (!in_array($data_admin_rayhanrp['role'], ['guru','kurikulum'], true)): ?>
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#dbeafe;">
        <i class="bi bi-people-fill" style="color:#2563eb;"></i>
      </div>
      <div>
        <div class="stat-value"><?php echo number_format($totalAkun); ?></div>
        <div class="stat-label">Total Akun</div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($data_admin_rayhanrp['role'] !== 'guru'): ?>
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#d1fae5;">
        <i class="bi bi-mortarboard-fill" style="color:#059669;"></i>
      </div>
      <div>
        <div class="stat-value"><?php echo number_format($totalGrup); ?></div>
        <div class="stat-label">Grup / Kelas</div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#fef3c7;">
        <i class="bi bi-calendar3" style="color:#d97706;"></i>
      </div>
      <div>
        <div class="stat-value"><?php echo number_format($totalJadwal); ?></div>
        <div class="stat-label">Jadwal Aktif</div>
      </div>
    </div>
  </div>

  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#ede9fe;">
        <i class="bi bi-journal-text" style="color:#7c3aed;"></i>
      </div>
      <div>
        <div class="stat-value"><?php echo number_format($totalTugas); ?></div>
        <div class="stat-label">Total Tugas</div>
      </div>
    </div>
  </div>

  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#d1fae5;">
        <i class="bi bi-check-circle-fill" style="color:#059669;"></i>
      </div>
      <div>
        <div class="stat-value"><?php echo $tugasAktif; ?></div>
        <div class="stat-label">Tugas Berlangsung</div>
      </div>
    </div>
  </div>

  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#dbeafe;">
        <i class="bi bi-inbox-fill" style="color:#2563eb;"></i>
      </div>
      <div>
        <div class="stat-value"><?php echo $kumpulHariIni; ?></div>
        <div class="stat-label">Kumpul Hari Ini</div>
      </div>
    </div>
  </div>

  <?php if (in_array($data_admin_rayhanrp['role'], ['guru','admin','kurikulum'], true)): ?>
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#fee2e2;">
        <i class="bi bi-hourglass-split" style="color:#dc2626;"></i>
      </div>
      <div>
        <div class="stat-value"><?php echo $belumDinilai; ?></div>
        <div class="stat-label">Belum Dinilai</div>
      </div>
    </div>
  </div>
  <?php endif; ?>

</div>

<!-- ── Row: Deadline + Aktivitas ── -->
<div class="row g-3 mb-4">

  <!-- Deadline tugas -->
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center justify-content-between">
        <h5><i class="bi bi-alarm text-warning me-2"></i>Deadline Mendekat</h5>
        <a href="tugas.php" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
      </div>
      <div class="card-body p-0">
        <?php if (empty($deadlineTugas)): ?>
          <div class="empty-state">
            <i class="bi bi-calendar-check"></i>
            <p>Tidak ada tugas yang mendekati deadline.</p>
          </div>
        <?php else: ?>
          <ul class="list-group list-group-flush">
            <?php foreach ($deadlineTugas as $dt): ?>
              <?php
                $hariSisa  = (int)ceil((strtotime($dt['tenggat']) - time()) / 86400);
                $badgeColor = $hariSisa <= 1 ? 'danger' : ($hariSisa <= 3 ? 'warning' : 'info');
              ?>
              <li class="list-group-item px-4 py-3">
                <div class="d-flex align-items-center justify-content-between">
                  <div>
                    <div class="fw-600" style="font-size:14px;"><?php echo htmlspecialchars($dt['judul']); ?></div>
                    <div class="text-muted" style="font-size:12px;">
                      <?php echo htmlspecialchars($dt['nama_grup'] ?? '-'); ?>
                      <?php if (!empty($dt['matpel_nama'])): ?>
                        · <?php echo htmlspecialchars($dt['matpel_nama']); ?>
                      <?php endif; ?>
                      · <?php echo (int)$dt['jml_kumpul']; ?> pengumpulan
                    </div>
                  </div>
                  <div class="text-end">
                    <span class="badge bg-<?php echo $badgeColor; ?> mb-1 d-block">
                      <?php echo $hariSisa <= 0 ? 'Hari ini!' : $hariSisa . ' hari'; ?>
                    </span>
                    <div class="text-muted" style="font-size:11px;">
                      <?php echo date('d/m H:i', strtotime($dt['tenggat'])); ?>
                    </div>
                  </div>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Notifikasi terbaru -->
  <?php if ($data_admin_rayhanrp['role'] !== 'guru'): ?>
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center justify-content-between">
        <h5><i class="bi bi-bell-fill text-primary me-2"></i>Notifikasi Terbaru</h5>
        <a href="notifikasi.php" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
      </div>
      <div class="card-body p-0">
        <?php if (empty($recentNotifs)): ?>
          <div class="empty-state">
            <i class="bi bi-bell-slash"></i>
            <p>Belum ada notifikasi.</p>
          </div>
        <?php else: ?>
          <ul class="list-group list-group-flush">
            <?php foreach ($recentNotifs as $n): ?>
              <li class="list-group-item px-4 py-3">
                <div class="d-flex align-items-start gap-3">
                  <div class="stat-icon flex-shrink-0" style="width:36px;height:36px;border-radius:9px;
                    background:<?php echo match($n['tipe']) {
                      'tugas' => '#ede9fe', 'jadwal' => '#fef3c7',
                      default => '#dbeafe'
                    }; ?>; font-size:15px;">
                    <i class="bi <?php echo match($n['tipe']) {
                      'tugas'      => 'bi-journal-text',
                      'jadwal'     => 'bi-calendar3',
                      'pengumuman' => 'bi-megaphone',
                      default      => 'bi-bell'
                    }; ?>" style="color:<?php echo match($n['tipe']) {
                      'tugas' => '#7c3aed', 'jadwal' => '#d97706', default => '#2563eb'
                    }; ?>;"></i>
                  </div>
                  <div class="flex-grow-1">
                    <div style="font-size:13px; line-height:1.4;">
                      <?php echo htmlspecialchars(mb_substr($n['pesan'], 0, 80)); ?>…
                    </div>
                    <div class="text-muted" style="font-size:11px; margin-top:2px;">
                      <span class="badge bg-light text-dark border me-1"><?php echo ucfirst($n['tipe']); ?></span>
                      <?php echo formatDatetime($n['waktu_kirim']); ?>
                    </div>
                  </div>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

</div>

<!-- Aktivitas terbaru (audit log) – hanya kepala sekolah & admin -->
<?php if (in_array($data_admin_rayhanrp['role'], ['admin','kepala_sekolah'], true) && !empty($recentActivity)): ?>
<div class="card">
  <div class="card-header d-flex align-items-center justify-content-between">
    <h5><i class="bi bi-shield-check text-success me-2"></i>Aktivitas Terbaru</h5>
    <a href="audit_log.php" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>Pelaku</th>
            <th>Aksi</th>
            <th>Status</th>
            <th>Waktu</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentActivity as $act): ?>
            <tr>
              <td>
                <div class="fw-600" style="font-size:13px;"><?php echo htmlspecialchars($act['nama_lengkap'] ?? 'Sistem'); ?></div>
                <?php if (!empty($act['role'])): ?>
                  <span class="badge <?php echo roleBadgeClass($act['role']); ?>" style="font-size:10px;"><?php echo roleLabel($act['role']); ?></span>
                <?php endif; ?>
              </td>
              <td><code style="font-size:12px;"><?php echo htmlspecialchars($act['aksi']); ?></code></td>
              <td>
                <span class="badge <?php echo $act['status'] === 'sukses' ? 'bg-success' : ($act['status'] === 'gagal' ? 'bg-danger' : 'bg-warning text-dark'); ?>">
                  <?php echo htmlspecialchars($act['status']); ?>
                </span>
              </td>
              <td class="text-muted" style="font-size:12px;"><?php echo formatDatetime($act['waktu']); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<?php layoutEnd(); ?>