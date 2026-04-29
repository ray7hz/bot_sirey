<?php
declare(strict_types=1);

$judul_halaman_rayhanrp = 'Jadwal Pelajaran';
$menu_aktif_rayhanrp = 'jadwal';

require_once __DIR__ . '/_layout.php';

if (!can('view_jadwal', $data_admin_rayhanrp)) {
    header('Location: dashboard.php?err=akses');
    exit;
}

$database_rayhanrp = sirey_getDatabase();
$pesan_rayhanrp = '';
$error_rayhanrp = '';

// Filter parameters
$filter_id_grup_rayhanrp = (int)($_GET['filter_grup'] ?? 0);
$filter_hari_rayhanrp = trim((string)($_GET['filter_hari'] ?? ''));
$filter_id_guru_rayhanrp = (int)($_GET['filter_guru'] ?? 0);

// Query jadwal dari guru_mengajar
$pernyataan_sql_rayhanrp = 'SELECT gm.id AS jadwal_id,
               gm.akun_id AS guru_id,
               guru.nama_lengkap AS guru_nama,
               gm.grup_id,
               g.nama_grup,
               g.jurusan,
               gm.matpel_id,
               mp.nama AS matpel_nama,
               mp.kode AS matpel_kode,
               gm.hari,
               gm.jam_mulai,
               gm.jam_selesai,
               gm.dibuat_pada
        FROM guru_mengajar_rayhanRP gm
        INNER JOIN akun_rayhanRP guru ON gm.akun_id = guru.akun_id
        INNER JOIN grup_rayhanRP g ON gm.grup_id = g.grup_id
        INNER JOIN mata_pelajaran_rayhanRP mp ON gm.matpel_id = mp.matpel_id
        WHERE gm.aktif = 1';

$tipe_rayhanrp = '';
$param_rayhanrp = [];

if ($data_admin_rayhanrp['role'] === 'guru') {
    $pernyataan_sql_rayhanrp .= ' AND gm.akun_id = ?';
    $tipe_rayhanrp .= 'i';
    $param_rayhanrp[] = $data_admin_rayhanrp['id'];
}

if ($filter_id_grup_rayhanrp > 0) {
    $pernyataan_sql_rayhanrp .= ' AND gm.grup_id = ?';
    $tipe_rayhanrp .= 'i';
    $param_rayhanrp[] = $filter_id_grup_rayhanrp;
}

if ($filter_hari_rayhanrp !== '') {
    $pernyataan_sql_rayhanrp .= ' AND gm.hari = ?';
    $tipe_rayhanrp .= 's';
    $param_rayhanrp[] = $filter_hari_rayhanrp;
}

if ($filter_id_guru_rayhanrp > 0) {
    $pernyataan_sql_rayhanrp .= ' AND gm.akun_id = ?';
    $tipe_rayhanrp .= 'i';
    $param_rayhanrp[] = $filter_id_guru_rayhanrp;
}

$pernyataan_sql_rayhanrp .= ' ORDER BY FIELD(gm.hari, "Senin","Selasa","Rabu","Kamis","Jumat","Sabtu","Minggu"), gm.jam_mulai ASC';

$daftar_jadwal_rayhanrp = $tipe_rayhanrp !== ''
    ? sirey_fetchAll(sirey_query($pernyataan_sql_rayhanrp, $tipe_rayhanrp, ...$param_rayhanrp))
    : sirey_fetchAll(sirey_query($pernyataan_sql_rayhanrp));

// Dropdown data for filters
$daftar_grup_rayhanrp = $data_admin_rayhanrp['role'] === 'guru'
    ? getGrupDiajarGuru($data_admin_rayhanrp['id'])
    : sirey_fetchAll(sirey_query('SELECT grup_id, nama_grup, jurusan FROM grup_rayhanRP WHERE aktif = 1 ORDER BY nama_grup ASC'));

$daftar_guru_rayhanrp = sirey_fetchAll(sirey_query('SELECT akun_id, nama_lengkap FROM akun_rayhanRP WHERE role = "guru" ORDER BY nama_lengkap ASC'));
?>

<div class="page-header">
  <h2>📅 Jadwal Pelajaran</h2>
  <p>Jadwal pelajaran dibuat dan dikelola melalui Manajemen Guru Mengajar.</p>
</div>

<?php if ($pesan_rayhanrp !== ''): ?>
  <div class="alert alert-success">✓ <?php echo htmlspecialchars($pesan_rayhanrp); ?></div>
<?php endif; ?>
<?php if ($error_rayhanrp !== ''): ?>
  <div class="alert alert-error">⚠️ <?php echo htmlspecialchars($error_rayhanrp); ?></div>
<?php endif; ?>

<!-- Filter -->
<div class="card" style="margin-bottom:24px;">
  <div class="card-header"><h3>Filter Jadwal</h3></div>
  <form method="GET" style="display:grid; grid-template-columns:1fr 1fr 1fr auto auto; gap:12px; align-items:flex-end; padding:16px 20px;">
    <div class="form-group" style="margin:0;">
      <label class="form-label">Kelas</label>
      <select name="filter_grup" class="form-control">
        <option value="">Semua kelas</option>
        <?php foreach ($daftar_grup_rayhanrp as $group_rayhanrp): ?>
          <option value="<?php echo (int)$group_rayhanrp['grup_id']; ?>" <?php echo $filter_id_grup_rayhanrp === (int)$group_rayhanrp['grup_id'] ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars((string)$group_rayhanrp['nama_grup']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin:0;">
      <label class="form-label">Hari</label>
      <select name="filter_hari" class="form-control">
        <option value="">Semua hari</option>
        <?php foreach (['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'] as $hari_rayhanrp): ?>
          <option value="<?php echo $hari_rayhanrp; ?>" <?php echo $filter_hari_rayhanrp === $hari_rayhanrp ? 'selected' : ''; ?>><?php echo $hari_rayhanrp; ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php if ($data_admin_rayhanrp['role'] !== 'guru'): ?>
    <div class="form-group" style="margin:0;">
      <label class="form-label">Guru</label>
      <select name="filter_guru" class="form-control">
        <option value="">Semua guru</option>
        <?php foreach ($daftar_guru_rayhanrp as $teacher_rayhanrp): ?>
          <option value="<?php echo (int)$teacher_rayhanrp['akun_id']; ?>" <?php echo $filter_id_guru_rayhanrp === (int)$teacher_rayhanrp['akun_id'] ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars((string)$teacher_rayhanrp['nama_lengkap']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <button type="submit" class="btn btn-primary">🔍 Filter</button>
    <a href="jadwal.php" class="btn btn-secondary">Reset</a>
  </form>
</div>

<!-- Jadwal Table -->
<div class="card">
  <div class="card-header">
    <h3>Daftar Jadwal (<?php echo count($daftar_jadwal_rayhanrp); ?>)</h3>
  </div>

  <?php if (empty($daftar_jadwal_rayhanrp)): ?>
    <div class="empty-state">
      <div class="empty-icon">📅</div>
      <p>Tidak ada jadwal yang cocok.</p>
    </div>
  <?php else: ?>
    <table class="data-table">
      <thead>
        <tr>
          <th>Kelas</th>
          <th>Mapel</th>
          <th>Guru</th>
          <th>Hari</th>
          <th>Waktu</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($daftar_jadwal_rayhanrp as $jadwal_rayhanrp): ?>
          <tr>
            <td><strong><?php echo htmlspecialchars((string)($jadwal_rayhanrp['nama_grup'] ?? '-')); ?></strong></td>
            <td>
              <span class="badge badge-default"><?php echo htmlspecialchars((string)$jadwal_rayhanrp['matpel_kode']); ?></span>
              <?php echo htmlspecialchars((string)$jadwal_rayhanrp['matpel_nama']); ?>
            </td>
            <td><?php echo htmlspecialchars((string)($jadwal_rayhanrp['guru_nama'] ?? '-')); ?></td>
            <td><?php echo htmlspecialchars((string)$jadwal_rayhanrp['hari']); ?></td>
            <td><?php echo htmlspecialchars(substr((string)$jadwal_rayhanrp['jam_mulai'], 0, 5) . ' - ' . substr((string)$jadwal_rayhanrp['jam_selesai'], 0, 5)); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php layoutEnd(); ?>

<?php layoutEnd(); ?>
