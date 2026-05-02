<?php
declare(strict_types=1);

$judul_halaman_rayhanrp = 'Jadwal Pelajaran';
$menu_aktif_rayhanrp    = 'jadwal';
require_once __DIR__ . '/_layout.php';

if (!can('view_jadwal', $data_admin_rayhanrp)) {
    header('Location: dashboard.php?err=akses');
    exit;
}

// ── Filter params ──
$fGrup  = (int)($_GET['filter_grup'] ?? 0);
$fHari  = trim((string)($_GET['filter_hari'] ?? ''));
$fGuru  = (int)($_GET['filter_guru'] ?? 0);
$fMode  = trim((string)($_GET['mode'] ?? 'tabel')); // tabel | grid

$hari_list = ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'];

// ── Query jadwal ──
$sql = 'SELECT gm.id AS jadwal_id,
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
               gm.aktif
        FROM guru_mengajar_rayhanRP gm
        INNER JOIN akun_rayhanRP guru ON gm.akun_id = guru.akun_id
        INNER JOIN grup_rayhanRP g    ON gm.grup_id = g.grup_id
        INNER JOIN mata_pelajaran_rayhanRP mp ON gm.matpel_id = mp.matpel_id
        WHERE gm.hari IS NOT NULL';

$types = ''; $params = [];

if ($data_admin_rayhanrp['role'] === 'guru') {
    $sql .= ' AND gm.akun_id = ?';
    $types .= 'i'; $params[] = $data_admin_rayhanrp['id'];
}
if ($fGrup > 0) {
    $sql .= ' AND gm.grup_id = ?';
    $types .= 'i'; $params[] = $fGrup;
}
if ($fHari !== '') {
    $sql .= ' AND gm.hari = ?';
    $types .= 's'; $params[] = $fHari;
}
if ($fGuru > 0 && $data_admin_rayhanrp['role'] !== 'guru') {
    $sql .= ' AND gm.akun_id = ?';
    $types .= 'i'; $params[] = $fGuru;
}

$sql .= ' ORDER BY FIELD(gm.hari,"Senin","Selasa","Rabu","Kamis","Jumat","Sabtu","Minggu"), gm.jam_mulai ASC';

$daftar = $types
    ? sirey_fetchAll(sirey_query($sql, $types, ...$params))
    : sirey_fetchAll(sirey_query($sql));

// ── Dropdown filter ──
$daftarGrup = $data_admin_rayhanrp['role'] === 'guru'
    ? getGrupDiajarGuru($data_admin_rayhanrp['id'])
    : sirey_fetchAll(sirey_query('SELECT grup_id,nama_grup FROM grup_rayhanRP WHERE aktif=1 ORDER BY nama_grup ASC'));

$daftarGuru = sirey_fetchAll(sirey_query(
    'SELECT akun_id,nama_lengkap FROM akun_rayhanRP WHERE role="guru" ORDER BY nama_lengkap ASC'
));

// ── Kelompokkan per hari untuk tampilan grid ──
$jadwalPerHari = [];
foreach ($hari_list as $h) $jadwalPerHari[$h] = [];
foreach ($daftar as $row) {
    $jadwalPerHari[$row['hari']][] = $row;
}

// Warna per mapel (konsisten berdasarkan hash nama)
function mapelColor(string $nama): array {
    $colors = [
        ['#dbeafe','#1d4ed8'], ['#d1fae5','#065f46'], ['#fef3c7','#92400e'],
        ['#ede9fe','#5b21b6'], ['#fee2e2','#991b1b'], ['#e0f2fe','#0c4a6e'],
        ['#f0fdf4','#14532d'], ['#fdf4ff','#86198f'], ['#fff7ed','#9a3412'],
        ['#f0f9ff','#0369a1'],
    ];
    $idx = abs(crc32($nama)) % count($colors);
    return $colors[$idx];
}
?>

<!-- Page Header -->
<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2">
  <div>
    <h2><i class="bi bi-calendar3 text-primary me-2"></i>Jadwal Pelajaran</h2>
    <p>Jadwal dikelola melalui menu <strong>Guru Mengajar</strong>. Halaman ini untuk melihat jadwal.</p>
  </div>
  <div class="d-flex gap-2">
    <!-- Mode toggle -->
    <div class="btn-group" role="group">
      <a href="?<?php echo http_build_query(array_merge($_GET, ['mode'=>'tabel'])); ?>"
         class="btn btn-sm <?php echo $fMode === 'tabel' ? 'btn-primary' : 'btn-outline-primary'; ?>">
        <i class="bi bi-table me-1"></i>Tabel
      </a>
      <a href="?<?php echo http_build_query(array_merge($_GET, ['mode'=>'grid'])); ?>"
         class="btn btn-sm <?php echo $fMode === 'grid' ? 'btn-primary' : 'btn-outline-primary'; ?>">
        <i class="bi bi-grid-3x3 me-1"></i>Grid Mingguan
      </a>
    </div>
    <?php if (can('manage_guru_mengajar', $data_admin_rayhanrp)): ?>
      <a href="manage_guru_mengajar.php" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-pencil-square me-1"></i>Kelola Jadwal
      </a>
    <?php endif; ?>
  </div>
</div>

<!-- Filter -->
<div class="card mb-3">
  <div class="card-body py-3">
    <form method="GET" class="row g-2 align-items-end">
      <input type="hidden" name="mode" value="<?php echo htmlspecialchars($fMode); ?>">

      <div class="col-sm-3">
        <label class="form-label">Kelas</label>
        <select name="filter_grup" class="form-select form-select-sm">
          <option value="">Semua Kelas</option>
          <?php foreach ($daftarGrup as $g): ?>
            <option value="<?php echo (int)$g['grup_id']; ?>"
                    <?php echo $fGrup === (int)$g['grup_id'] ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($g['nama_grup']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-sm-2">
        <label class="form-label">Hari</label>
        <select name="filter_hari" class="form-select form-select-sm">
          <option value="">Semua Hari</option>
          <?php foreach ($hari_list as $h): ?>
            <option value="<?php echo $h; ?>" <?php echo $fHari === $h ? 'selected' : ''; ?>><?php echo $h; ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <?php if ($data_admin_rayhanrp['role'] !== 'guru'): ?>
      <div class="col-sm-3">
        <label class="form-label">Guru</label>
        <select name="filter_guru" class="form-select form-select-sm">
          <option value="">Semua Guru</option>
          <?php foreach ($daftarGuru as $g): ?>
            <option value="<?php echo (int)$g['akun_id']; ?>"
                    <?php echo $fGuru === (int)$g['akun_id'] ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($g['nama_lengkap']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>

      <div class="col-auto d-flex gap-2">
        <button type="submit" class="btn btn-sm btn-primary">
          <i class="bi bi-funnel me-1"></i>Filter
        </button>
        <a href="jadwal.php?mode=<?php echo htmlspecialchars($fMode); ?>" class="btn btn-sm btn-outline-secondary">
          Reset
        </a>
      </div>

      <!-- Info total -->
      <div class="col-auto ms-auto d-flex align-items-end">
        <span class="badge bg-primary fs-6 px-3 py-2">
          <?php echo count($daftar); ?> sesi ditemukan
        </span>
      </div>
    </form>
  </div>
</div>

<?php if ($fMode === 'grid'): ?>
<!-- ══════════════════════════════════════════════════
     TAMPILAN GRID MINGGUAN
══════════════════════════════════════════════════ -->
<div class="card">
  <div class="card-header">
    <h5><i class="bi bi-grid-3x3 me-2"></i>Jadwal Mingguan</h5>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-bordered mb-0" style="min-width:900px;">
        <thead>
          <tr>
            <?php
            $hariTampil = $fHari !== '' ? [$fHari] : $hari_list;
            foreach ($hariTampil as $h):
              $ada = count($jadwalPerHari[$h] ?? []);
            ?>
              <th class="text-center" style="background:#f8fafc; min-width:140px;">
                <div class="fw-700"><?php echo $h; ?></div>
                <div class="text-muted" style="font-size:11px;"><?php echo $ada; ?> sesi</div>
              </th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <tr>
            <?php foreach ($hariTampil as $h): ?>
              <td class="align-top p-2" style="background:#fafbfc;">
                <?php if (empty($jadwalPerHari[$h])): ?>
                  <div class="text-center text-muted py-3" style="font-size:12px;">
                    <i class="bi bi-calendar-x d-block mb-1" style="font-size:22px; opacity:.3;"></i>
                    Tidak ada jadwal
                  </div>
                <?php else: ?>
                  <?php foreach ($jadwalPerHari[$h] as $j): ?>
                    <?php [$bg, $col] = mapelColor($j['matpel_nama']); ?>
                    <div class="rounded mb-2 p-2" style="background:<?php echo $bg; ?>; border-left:3px solid <?php echo $col; ?>;">
                      <div style="font-size:11px; font-weight:700; color:<?php echo $col; ?>;">
                        <?php echo substr($j['jam_mulai'],0,5); ?> – <?php echo substr($j['jam_selesai'],0,5); ?>
                      </div>
                      <div style="font-size:12px; font-weight:600; color:#1e293b; margin-top:2px;">
                        <?php echo htmlspecialchars($j['matpel_kode']); ?> — <?php echo htmlspecialchars($j['matpel_nama']); ?>
                      </div>
                      <div style="font-size:11px; color:#475569; margin-top:2px;">
                        <i class="bi bi-mortarboard me-1"></i><?php echo htmlspecialchars($j['nama_grup']); ?>
                      </div>
                      <div style="font-size:11px; color:#64748b;">
                        <i class="bi bi-person me-1"></i><?php echo htmlspecialchars($j['guru_nama']); ?>
                      </div>
                      <?php if (!(int)$j['aktif']): ?>
                        <span class="badge bg-secondary mt-1" style="font-size:9px;">Nonaktif</span>
                      <?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </td>
            <?php endforeach; ?>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php else: ?>
<!-- ══════════════════════════════════════════════════
     TAMPILAN TABEL
══════════════════════════════════════════════════ -->
<div class="card">
  <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
    <h5><i class="bi bi-table me-2"></i>Daftar Jadwal</h5>
    <?php if (!empty($daftar)): ?>
      <!-- Export CSV sederhana via JS -->
      <button class="btn btn-sm btn-outline-success" onclick="exportCSV()">
        <i class="bi bi-download me-1"></i>Export CSV
      </button>
    <?php endif; ?>
  </div>
  <div class="card-body p-0">
    <?php if (empty($daftar)): ?>
      <div class="empty-state">
        <i class="bi bi-calendar-x"></i>
        <p>Tidak ada jadwal yang cocok dengan filter yang dipilih.</p>
        <?php if ($fGrup || $fHari || $fGuru): ?>
          <a href="jadwal.php" class="btn btn-sm btn-outline-primary mt-2">Reset Filter</a>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover mb-0" id="tblJadwal">
          <thead>
            <tr>
              <th>#</th>
              <th>Hari</th>
              <th>Waktu</th>
              <th>Kelas</th>
              <th>Mata Pelajaran</th>
              <th>Guru</th>
              <th class="text-center">Status</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $no      = 0;
            $hariNow = '';
            foreach ($daftar as $j):
                $no++;
                $isNewHari = $j['hari'] !== $hariNow;
                $hariNow   = $j['hari'];
                [$bg, $col] = mapelColor($j['matpel_nama']);
            ?>
              <?php if ($isNewHari && $fHari === ''): ?>
                <tr>
                  <td colspan="7" style="background:#f1f5f9; padding:8px 14px;">
                    <span class="badge bg-primary" style="font-size:12px;">
                      <i class="bi bi-calendar2-week me-1"></i><?php echo $j['hari']; ?>
                    </span>
                    <span class="text-muted ms-2" style="font-size:12px;">
                      <?php echo count(array_filter($daftar, fn($x) => $x['hari'] === $j['hari'])); ?> sesi
                    </span>
                  </td>
                </tr>
              <?php endif; ?>
              <tr>
                <td class="text-muted" style="font-size:12px;"><?php echo $no; ?></td>
                <td>
                  <span class="badge" style="background:<?php echo $bg; ?>; color:<?php echo $col; ?>; border:1px solid <?php echo $col; ?>40;">
                    <?php echo htmlspecialchars($j['hari']); ?>
                  </span>
                </td>
                <td>
                  <span class="fw-600" style="font-size:13px;">
                    <?php echo substr($j['jam_mulai'],0,5); ?> – <?php echo substr($j['jam_selesai'],0,5); ?>
                  </span>
                  <?php
                    $mulaiSec   = strtotime(date('Y-m-d').' '.$j['jam_mulai']);
                    $selesaiSec = strtotime(date('Y-m-d').' '.$j['jam_selesai']);
                    $durasi     = round(($selesaiSec - $mulaiSec) / 60);
                  ?>
                  <div class="text-muted" style="font-size:11px;"><?php echo $durasi; ?> menit</div>
                </td>
                <td>
                  <div class="fw-600"><?php echo htmlspecialchars($j['nama_grup']); ?></div>
                  <div class="text-muted" style="font-size:11px;"><?php echo htmlspecialchars($j['jurusan']); ?></div>
                </td>
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <div class="rounded-2 d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width:28px;height:28px;background:<?php echo $bg; ?>;font-size:10px;font-weight:700;color:<?php echo $col; ?>;">
                      <?php echo htmlspecialchars($j['matpel_kode']); ?>
                    </div>
                    <div><?php echo htmlspecialchars($j['matpel_nama']); ?></div>
                  </div>
                </td>
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width:28px;height:28px;font-size:11px;color:#fff;font-weight:700;">
                      <?php echo strtoupper(substr($j['guru_nama'],0,1)); ?>
                    </div>
                    <div style="font-size:13px;"><?php echo htmlspecialchars($j['guru_nama']); ?></div>
                  </div>
                </td>
                <td class="text-center">
                  <span class="badge <?php echo (int)$j['aktif'] ? 'bg-success' : 'bg-secondary'; ?>">
                    <?php echo (int)$j['aktif'] ? 'Aktif' : 'Nonaktif'; ?>
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<style>
.fw-600 { font-weight: 600; }
.fw-700 { font-weight: 700; }
</style>

<script>
$(document).ready(function () {
  if ($('#tblJadwal').length) {
    $('#tblJadwal').DataTable({
      pageLength: 50,
      order: [],
      columnDefs: [{ orderable: false, targets: 0 }],
    });
  }
});

function exportCSV() {
  const table  = document.getElementById('tblJadwal');
  if (!table) return;
  const rows   = table.querySelectorAll('thead tr, tbody tr');
  const lines  = [];
  rows.forEach(row => {
    const cells = [...row.querySelectorAll('th,td')];
    if (cells.length < 2) return; // baris separator hari
    const vals = cells.map(td => '"' + td.innerText.replace(/\n/g,' ').replace(/"/g,'""').trim() + '"');
    lines.push(vals.join(','));
  });
  const blob = new Blob(['\uFEFF' + lines.join('\n')], { type:'text/csv;charset=utf-8;' });
  const url  = URL.createObjectURL(blob);
  const a    = document.createElement('a');
  a.href = url; a.download = 'jadwal_pelajaran.csv'; a.click();
  URL.revokeObjectURL(url);
}
</script>

<?php layoutEnd(); ?>