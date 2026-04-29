<?php
declare(strict_types=1);

$judul_halaman_rayhanrp = 'Audit Log';
$menu_aktif_rayhanrp = 'audit_log';

require_once __DIR__ . '/_layout.php';

if (!can('view_audit_log', $data_admin_rayhanrp)) {
    header('Location: dashboard.php?err=akses');
    exit;
}

$filter_aksi_rayhanrp = trim((string)($_GET['aksi'] ?? ''));
$filter_status_rayhanrp = trim((string)($_GET['status'] ?? ''));
$filter_nama_rayhanrp = trim((string)($_GET['nama'] ?? ''));

$pernyataan_sql_rayhanrp = 'SELECT al.id, al.aksi, al.objek_tipe, al.objek_id, al.status, al.detail,
               al.ip_address, al.waktu, a.nama_lengkap, a.role
        FROM audit_log_rayhanRP al
        LEFT JOIN akun_rayhanRP a ON al.akun_id = a.akun_id
        WHERE 1=1';
$tipe_rayhanrp = '';
$parameter_rayhanrp = [];

if ($filter_aksi_rayhanrp !== '') {
    $pernyataan_sql_rayhanrp .= ' AND al.aksi LIKE ?';
    $tipe_rayhanrp .= 's';
    $parameter_rayhanrp[] = '%' . $filter_aksi_rayhanrp . '%';
}

if ($filter_status_rayhanrp !== '') {
    $pernyataan_sql_rayhanrp .= ' AND al.status = ?';
    $tipe_rayhanrp .= 's';
    $parameter_rayhanrp[] = $filter_status_rayhanrp;
}

if ($filter_nama_rayhanrp !== '') {
    $pernyataan_sql_rayhanrp .= ' AND a.nama_lengkap LIKE ?';
    $tipe_rayhanrp .= 's';
    $parameter_rayhanrp[] = '%' . $filter_nama_rayhanrp . '%';
}

$pernyataan_sql_rayhanrp .= ' ORDER BY al.waktu DESC LIMIT 200';

$baris_rayhanrp = $tipe_rayhanrp !== ''
    ? sirey_fetchAll(sirey_query($pernyataan_sql_rayhanrp, $tipe_rayhanrp, ...$parameter_rayhanrp))
    : sirey_fetchAll(sirey_query($pernyataan_sql_rayhanrp));
?>

<div class="page-header">
  <h2>Audit Log</h2>
  <p>Riwayat aksi sensitif dan perubahan penting di sistem.</p>
</div>

<div class="card" style="margin-bottom:24px;">
  <div class="card-header">
    <h3>Filter</h3>
  </div>

  <form method="GET" style="display:grid; grid-template-columns:1fr 1fr 1fr auto auto; gap:12px; align-items:flex-end;">
    <div class="form-group" style="margin:0;">
      <label class="form-label">Aksi</label>
      <input type="text" name="aksi" class="form-control" value="<?php echo htmlspecialchars($filter_aksi_rayhanrp); ?>" placeholder="Contoh: reset_password">
    </div>
    <div class="form-group" style="margin:0;">
      <label class="form-label">Status</label>
      <select name="status" class="form-control">
        <option value="">Semua Status</option>
        <option value="sukses" <?php echo $filter_status_rayhanrp === 'sukses' ? 'selected' : ''; ?>>Sukses</option>
        <option value="gagal" <?php echo $filter_status_rayhanrp === 'gagal' ? 'selected' : ''; ?>>Gagal</option>
        <option value="ditolak" <?php echo $filter_status_rayhanrp === 'ditolak' ? 'selected' : ''; ?>>Ditolak</option>
      </select>
    </div>
    <div class="form-group" style="margin:0;">
      <label class="form-label">Pelaku</label>
      <input type="text" name="nama" class="form-control" value="<?php echo htmlspecialchars($filter_nama_rayhanrp); ?>" placeholder="Cari nama">
    </div>
    <button type="submit" class="btn btn-primary">Filter</button>
    <a href="audit_log.php" class="btn btn-secondary">Reset</a>
  </form>
</div>

<div class="card">
  <div class="card-header">
    <h3>Riwayat Aktivitas (<?php echo count($baris_rayhanrp); ?>)</h3>
  </div>

  <?php if (empty($baris_rayhanrp)): ?>
    <div class="empty-state">
      <div class="empty-icon">Log</div>
      <p>Belum ada data audit yang cocok.</p>
    </div>
  <?php else: ?>
    <table class="data-table">
      <thead>
        <tr>
          <th>Waktu</th>
          <th>Pelaku</th>
          <th>Aksi</th>
          <th>Objek</th>
          <th>Status</th>
          <th>Detail</th>
          <th>IP</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($baris_rayhanrp as $item_rayhanrp): ?>
          <?php
            $detail_rayhanrp = '-';
            if (!empty($item_rayhanrp['detail'])) {
                $decoded_rayhanrp = json_decode((string)$item_rayhanrp['detail'], true);
                $detail_rayhanrp = is_array($decoded_rayhanrp)
                    ? json_encode($decoded_rayhanrp, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    : (string)$item_rayhanrp['detail'];
            }
          ?>
          <tr>
            <td style="white-space:nowrap;"><?php echo formatDatetime($item_rayhanrp['waktu']); ?></td>
            <td>
              <?php echo htmlspecialchars($item_rayhanrp['nama_lengkap'] ?? 'Sistem'); ?>
              <?php if (!empty($item_rayhanrp['role'])): ?>
                <br><span class="badge <?php echo roleBadgeClass((string)$item_rayhanrp['role']); ?>"><?php echo htmlspecialchars((string)$item_rayhanrp['role']); ?></span>
              <?php endif; ?>
            </td>
            <td><strong><?php echo htmlspecialchars((string)$item_rayhanrp['aksi']); ?></strong></td>
            <td>
              <?php echo htmlspecialchars((string)($item_rayhanrp['objek_tipe'] ?? '-')); ?>
              <?php if (!empty($item_rayhanrp['objek_id'])): ?>
                <br><small>#<?php echo (int)$item_rayhanrp['objek_id']; ?></small>
              <?php endif; ?>
            </td>
            <td>
              <span class="badge badge-default"><?php echo htmlspecialchars((string)$item_rayhanrp['status']); ?></span>
            </td>
            <td style="max-width:360px; white-space:pre-wrap; word-break:break-word; font-size:12px;">
              <?php echo htmlspecialchars($detail_rayhanrp); ?>
            </td>
            <td style="white-space:nowrap;"><?php echo htmlspecialchars((string)($item_rayhanrp['ip_address'] ?? '-')); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php layoutEnd(); ?>
