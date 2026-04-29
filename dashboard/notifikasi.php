<?php
declare(strict_types=1);

// ================== CONFIG ==================
$judul_halaman_rayhanrp  = 'Riwayat Notifikasi';
$menu_aktif_rayhanrp = 'notifikasi';

require_once __DIR__ . '/_layout.php';

if (!can('view_notifikasi', $data_admin_rayhanrp)) {
    header('Location: dashboard.php?err=akses');
    exit;
}

$database_rayhanrp = sirey_getDatabase();


// ================== QUERY ==================

// Log broadcast Telegram
$daftar_broadcast_rayhanrp = sirey_fetchAll(sirey_query(
    'SELECT n.notifikasi_id, n.tipe, g.nama_grup,
            n.pesan, n.jumlah_terkirim, n.waktu_kirim
     FROM notifikasi_rayhanRP n
     LEFT JOIN grup_rayhanRP g ON n.grup_id = g.grup_id
     ORDER BY n.waktu_kirim DESC
     LIMIT 100'
));

// Statistik ringkas
$total_broadcast_rayhanrp = count($daftar_broadcast_rayhanrp);
?>


<div class="page-header">
  <h2>📢 Riwayat Notifikasi</h2>
  <p>Log semua notifikasi yang telah dikirim via Telegram maupun in-app.</p>
</div>


<!-- ================== STATISTIK ================== -->
<div class="stat-grid" style="margin-bottom:24px;">
  <div class="stat-card">
    <div class="stat-icon blue">📡</div>
    <div>
      <div class="stat-value"><?php echo $total_broadcast_rayhanrp; ?></div>
      <div class="stat-label">Broadcast Telegram</div>
    </div>
  </div>

</div>

<!-- ================== BROADCAST LOG ================== -->
<div>
  <div class="card">
    <div class="card-header">
      <h3>Log Broadcast Telegram</h3>
      <a href="pengumuman.php" class="btn btn-primary btn-sm">+ Kirim Baru</a>
    </div>

    <?php if (empty($daftar_broadcast_rayhanrp)): ?>
      <div class="empty-state">
        <div class="empty-icon">📭</div>
        <p>Belum ada broadcast yang dikirim.</p>
      </div>

    <?php else: ?>
      <table class="data-table">
        <thead>
          <tr>
            <th>Tipe</th>
            <th>Target Kelas</th>
            <th>Pesan</th>
            <th style="text-align:center;">Terkirim ke</th>
            <th>Waktu</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($daftar_broadcast_rayhanrp as $notif_rayhanrp): ?>
            <tr>
              <td>
                <span class="badge badge-default">
                  <?php echo ucfirst(htmlspecialchars($notif_rayhanrp['tipe'])); ?>
                </span>
              </td>
              <td style="font-size:13px;">
                <?php echo htmlspecialchars($notif_rayhanrp['nama_grup'] ?? 'Semua'); ?>
              </td>
              <td style="max-width:340px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                <?php echo htmlspecialchars(mb_substr((string)$notif_rayhanrp['pesan'], 0, 100)); ?>
              </td>
              <td style="text-align:center; font-weight:bold;">
                <?php echo (int)$notif_rayhanrp['jumlah_terkirim']; ?>
                <small style="font-weight:normal; color:var(--clr-muted);">orang</small>
              </td>
              <td style="white-space:nowrap; color:var(--clr-muted); font-size:13px;">
                <?php echo formatDatetime($notif_rayhanrp['waktu_kirim']); ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<?php layoutEnd(); ?>
