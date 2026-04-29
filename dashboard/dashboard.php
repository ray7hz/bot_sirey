<?php
declare(strict_types=1);

$pageTitle  = 'Dashboard';
$activeMenu = 'dashboard';
require_once __DIR__ . '/_layout.php';

if (!can('view_dashboard', $data_admin_rayhanrp)) {
    session_destroy();
    header('Location: login.php?err=noperm');
    exit;
}

$db = sirey_getDatabase();

// Ambil jumlah data satu per satu supaya alurnya mudah diikuti pemula.
$queryAkun = sirey_query('SELECT COUNT(*) AS total FROM akun_rayhanRP');
$rowAkun   = $queryAkun ? mysqli_fetch_assoc(mysqli_stmt_get_result($queryAkun)) : ['total' => 0];
$totalAkun = (int) ($rowAkun['total'] ?? 0);

$queryGrup = sirey_query('SELECT COUNT(*) AS total FROM grup_rayhanRP');
$rowGrup   = $queryGrup ? mysqli_fetch_assoc(mysqli_stmt_get_result($queryGrup)) : ['total' => 0];
$totalGrup = (int) ($rowGrup['total'] ?? 0);

// Count jadwal dari guru_mengajar (jadwal aktif saja)
// Jika guru: hanya jadwal mereka sendiri
// Jika selain guru (admin/kurikulum/kepala_sekolah): semua jadwal aktif
if ($data_admin_rayhanrp['role'] === 'guru') {
    $queryJadwal = sirey_query(
        'SELECT COUNT(*) AS total FROM guru_mengajar_rayhanRP WHERE akun_id = ? AND aktif = 1',
        'i',
        $data_admin_rayhanrp['id']
    );
} else {
    $queryJadwal = sirey_query('SELECT COUNT(*) AS total FROM guru_mengajar_rayhanRP WHERE aktif = 1');
}
$rowJadwal   = $queryJadwal ? mysqli_fetch_assoc(mysqli_stmt_get_result($queryJadwal)) : ['total' => 0];
$totalJadwal = (int) ($rowJadwal['total'] ?? 0);

// Count tugas
// Jika guru: hanya tugas dari grup yang mereka ajar
// Jika selain guru (admin/kurikulum/kepala_sekolah): semua tugas
if ($data_admin_rayhanrp['role'] === 'guru') {
    $queryTugas = sirey_query(
        'SELECT COUNT(*) AS total FROM tugas_rayhanRP t
         INNER JOIN guru_mengajar_rayhanRP gm ON t.grup_id = gm.grup_id
         WHERE gm.akun_id = ? AND gm.aktif = 1',
        'i',
        $data_admin_rayhanrp['id']
    );
} else {
    $queryTugas = sirey_query('SELECT COUNT(*) AS total FROM tugas_rayhanRP');
}
$rowTugas   = $queryTugas ? mysqli_fetch_assoc(mysqli_stmt_get_result($queryTugas)) : ['total' => 0];
$totalTugas = (int) ($rowTugas['total'] ?? 0);

// Ambil 6 notifikasi terbaru.
$recentNotifs = [];
$queryNotifikasi = sirey_query('SELECT pesan, waktu_kirim, tipe FROM notifikasi_rayhanRP ORDER BY waktu_kirim DESC LIMIT 6');
if ($queryNotifikasi) {
    $resultNotifikasi = mysqli_stmt_get_result($queryNotifikasi);
    while ($row = mysqli_fetch_assoc($resultNotifikasi)) {
        $recentNotifs[] = $row;
    }
}
?>

<div class="page-header">
    <h2>Dashboard</h2>
    <p>Selamat datang kembali, <?php echo htmlspecialchars($data_admin_rayhanrp['name']); ?> 👋</p>
</div>

<div class="stat-grid">
    <!-- Total Akun: Hanya untuk admin dan kepala_sekolah -->
    <?php if (!in_array($data_admin_rayhanrp['role'], ['guru', 'kurikulum'], true)): ?>
    <div class="stat-card">
        <div class="stat-icon blue">👤</div>
        <div>
            <div class="stat-value"><?php echo number_format($totalAkun); ?></div>
            <div class="stat-label">Total Akun</div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Grup/Kelas: Hanya untuk admin, kurikulum, dan kepala_sekolah (tidak untuk guru) -->
    <?php if ($data_admin_rayhanrp['role'] !== 'guru'): ?>
    <div class="stat-card">
        <div class="stat-icon green">🎓</div>
        <div>
            <div class="stat-value"><?php echo number_format($totalGrup); ?></div>
            <div class="stat-label">Grup/Kelas</div>
        </div>
    </div>
    <?php endif; ?>

    <div class="stat-card">
        <div class="stat-icon amber">📅</div>
        <div>
            <div class="stat-value"><?php echo number_format($totalJadwal); ?></div>
            <div class="stat-label">Jadwal</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon purple">📝</div>
        <div>
            <div class="stat-value"><?php echo number_format($totalTugas); ?></div>
            <div class="stat-label">Tugas</div>
        </div>
    </div>
</div>

<!-- Notifikasi Terbaru: Tidak untuk guru -->
<?php if ($data_admin_rayhanrp['role'] !== 'guru'): ?>
<div class="card">
    <div class="card-header">
        <h3>📢 Notifikasi Terbaru</h3>
        <a href="notifikasi.php" class="btn btn-secondary btn-sm">Lihat Semua</a>
    </div>

    <?php if (empty($recentNotifs)) : ?>
        <div class="empty-state">
            <div class="empty-icon">📭</div>
            <p>Belum ada notifikasi yang terkirim.</p>
        </div>
    <?php else : ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Pesan</th>
                    <th>Tipe</th>
                    <th>Waktu Kirim</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentNotifs as $notif) : ?>
                    <tr>
                        <td><?php echo htmlspecialchars(mb_substr($notif['pesan'], 0, 90)); ?>…</td>
                        <td>
                            <span class="badge badge-default">
                                <?php echo htmlspecialchars(ucfirst($notif['tipe'])); ?>
                            </span>
                        </td>
                        <td style="white-space: nowrap;">
                            <?php echo htmlspecialchars($notif['waktu_kirim']); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php layoutEnd(); ?>
