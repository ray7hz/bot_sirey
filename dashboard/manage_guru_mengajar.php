<?php
declare(strict_types=1);

$judul_halaman_rayhanrp  = 'Manajemen Guru Mengajar & Wali Kelas';
$menu_aktif_rayhanrp = 'manage_guru_mengajar';

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

startSession();
$data_admin_rayhanrp = requireAdminSession('login.php');
ensureSireySchema();

if (!can('manage_guru_mengajar', $data_admin_rayhanrp)) {
    header('Location: dashboard.php?err=akses');
    exit;
}

// Endpoint AJAX untuk mengambil Wali Kelas saat ini berdasarkan Kelas yang dipilih
if (($_GET['action'] ?? '') === 'get_wali_by_grup') {
    header('Content-Type: application/json; charset=utf-8');

    $id_grup_ajax_rayhanrp = (int)($_GET['grup_id'] ?? 0);
    $wali_rayhanrp = $id_grup_ajax_rayhanrp > 0
        ? sirey_fetch(sirey_query(
            'SELECT g.wali_kelas_id, a.nama_lengkap AS wali_kelas
             FROM grup_rayhanRP g
             LEFT JOIN akun_rayhanRP a ON a.akun_id = g.wali_kelas_id
             WHERE g.grup_id = ?
             LIMIT 1',
            'i',
            $id_grup_ajax_rayhanrp
        ))
        : null;

    echo json_encode([
        'success' => true,
        'wali_kelas_id' => (int)($wali_rayhanrp['wali_kelas_id'] ?? 0),
        'wali_kelas' => $wali_rayhanrp['wali_kelas'] ?? '-',
    ]);
    exit;
}

require_once __DIR__ . '/_layout.php';

$database_rayhanrp  = sirey_getDatabase();
$pesan_rayhanrp = '';
$error_rayhanrp = '';

// ================== ACTION (POST) ==================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireNotReadonly($data_admin_rayhanrp, 'manage_guru_mengajar.php');
    $aksi_rayhanrp = (string)($_POST['act'] ?? '');

    // Aksi 1: Menetapkan Wali Kelas
    if ($aksi_rayhanrp === 'set_wali') {
        $id_grup_rayhanrp = (int)($_POST['grup_id'] ?? 0);
        $id_guru_rayhanrp = (int)($_POST['guru_id'] ?? 0);

        if ($id_grup_rayhanrp <= 0) {
            $error_rayhanrp = 'Kelas wajib dipilih untuk menetapkan Wali Kelas.';
        } else {
            sirey_execute(
                'UPDATE grup_rayhanRP SET wali_kelas_id = ? WHERE grup_id = ?',
                'ii',
                $id_guru_rayhanrp > 0 ? $id_guru_rayhanrp : null,
                $id_grup_rayhanrp
            );
            auditLog($data_admin_rayhanrp['id'], 'set_wali_kelas', 'grup', $id_grup_rayhanrp, ['wali_kelas_id' => $id_guru_rayhanrp]);
            $pesan_rayhanrp = 'Wali Kelas berhasil diperbarui.';
        }
    } 
    // Aksi 2: Membuat Jadwal Mengajar (Assignment Guru)
    elseif ($aksi_rayhanrp === 'create') {
        $id_akun_rayhanrp   = (int)($_POST['akun_id'] ?? 0);
        $id_grup_rayhanrp   = (int)($_POST['grup_id'] ?? 0);
        $id_matpel_rayhanrp = (int)($_POST['matpel_id'] ?? 0);
        $hari_rayhanrp      = trim((string)($_POST['hari'] ?? ''));
        $jam_mulai_rayhanrp = trim((string)($_POST['jam_mulai'] ?? ''));
        $jam_selesai_rayhanrp = trim((string)($_POST['jam_selesai'] ?? ''));

        if ($id_akun_rayhanrp <= 0 || $id_grup_rayhanrp <= 0 || $id_matpel_rayhanrp <= 0) {
            $error_rayhanrp = 'Guru, Kelas, dan Mata Pelajaran wajib dipilih.';
        } elseif ($hari_rayhanrp === '' || $jam_mulai_rayhanrp === '' || $jam_selesai_rayhanrp === '') {
            $error_rayhanrp = 'Hari, jam mulai, dan jam selesai wajib diisi.';
        } elseif ($jam_selesai_rayhanrp <= $jam_mulai_rayhanrp) {
            $error_rayhanrp = 'Jam selesai harus lebih besar dari jam mulai.';
        } else {
            $hasil_rayhanrp = sirey_execute(
                'INSERT IGNORE INTO guru_mengajar_rayhanRP (akun_id, grup_id, matpel_id, hari, jam_mulai, jam_selesai) VALUES (?, ?, ?, ?, ?, ?)',
                'iiisss', $id_akun_rayhanrp, $id_grup_rayhanrp, $id_matpel_rayhanrp, $hari_rayhanrp, $jam_mulai_rayhanrp, $jam_selesai_rayhanrp
            );
            if ($hasil_rayhanrp >= 1) {
                auditLog($data_admin_rayhanrp['id'], 'create_guru_mengajar', 'guru_mengajar', null, [
                    'akun_id' => $id_akun_rayhanrp, 'grup_id' => $id_grup_rayhanrp, 'matpel_id' => $id_matpel_rayhanrp,
                    'hari' => $hari_rayhanrp, 'jam_mulai' => $jam_mulai_rayhanrp, 'jam_selesai' => $jam_selesai_rayhanrp
                ]);
                $pesan_rayhanrp = 'Jadwal mengajar guru berhasil ditambahkan.';
            } else {
                $error_rayhanrp = 'Jadwal sudah ada atau gagal disimpan.';
            }
        }
    } 
    // Aksi 3: Update Jadwal Mengajar
    elseif ($aksi_rayhanrp === 'update') {
        $id_rayhanrp = (int)($_POST['id'] ?? 0);
        $hari_rayhanrp = trim((string)($_POST['hari'] ?? ''));
        $jam_mulai_rayhanrp = trim((string)($_POST['jam_mulai'] ?? ''));
        $jam_selesai_rayhanrp = trim((string)($_POST['jam_selesai'] ?? ''));

        if ($id_rayhanrp <= 0) {
            $error_rayhanrp = 'ID tidak valid.';
        } elseif ($hari_rayhanrp === '' || $jam_mulai_rayhanrp === '' || $jam_selesai_rayhanrp === '') {
            $error_rayhanrp = 'Hari, jam mulai, dan jam selesai wajib diisi.';
        } elseif ($jam_selesai_rayhanrp <= $jam_mulai_rayhanrp) {
            $error_rayhanrp = 'Jam selesai harus lebih besar dari jam mulai.';
        } else {
            sirey_execute(
                'UPDATE guru_mengajar_rayhanRP SET hari = ?, jam_mulai = ?, jam_selesai = ? WHERE id = ?',
                'sssi', $hari_rayhanrp, $jam_mulai_rayhanrp, $jam_selesai_rayhanrp, $id_rayhanrp
            );
            auditLog($data_admin_rayhanrp['id'], 'update_guru_mengajar', 'guru_mengajar', $id_rayhanrp, [
                'hari' => $hari_rayhanrp, 'jam_mulai' => $jam_mulai_rayhanrp, 'jam_selesai' => $jam_selesai_rayhanrp
            ]);
            $pesan_rayhanrp = 'Jadwal berhasil diperbarui.';
        }
    } 
    // Aksi 4: Toggle Status Jadwal
    elseif ($aksi_rayhanrp === 'toggle') {
        $id_rayhanrp    = (int)($_POST['id'] ?? 0);
        $aktif_rayhanrp = (int)($_POST['aktif'] ?? 1);

        if ($id_rayhanrp > 0) {
            $aktif_baru_rayhanrp = $aktif_rayhanrp ? 0 : 1;
            sirey_execute('UPDATE guru_mengajar_rayhanRP SET aktif = ? WHERE id = ?', 'ii', $aktif_baru_rayhanrp, $id_rayhanrp);
            auditLog($data_admin_rayhanrp['id'], 'toggle_guru_mengajar', 'guru_mengajar', $id_rayhanrp, ['aktif' => $aktif_baru_rayhanrp]);
            $pesan_rayhanrp = $aktif_baru_rayhanrp ? 'Jadwal diaktifkan.' : 'Jadwal dinonaktifkan.';
        } else {
            $error_rayhanrp = 'ID tidak valid.';
        }
    } 
    // Aksi 5: Hapus Jadwal
    elseif ($aksi_rayhanrp === 'delete') {
        $id_rayhanrp = (int)($_POST['id'] ?? 0);
        if ($id_rayhanrp > 0) {
            sirey_execute('DELETE FROM guru_mengajar_rayhanRP WHERE id = ?', 'i', $id_rayhanrp);
            auditLog($data_admin_rayhanrp['id'], 'delete_guru_mengajar', 'guru_mengajar', $id_rayhanrp);
            $pesan_rayhanrp = 'Jadwal dihapus.';
        } else {
            $error_rayhanrp = 'ID tidak valid.';
        }
    }
}

// ================== QUERY ==================
$filter_guru_rayhanrp = (int)($_GET['filter_guru'] ?? 0);
$filter_grup_rayhanrp = (int)($_GET['filter_grup'] ?? 0);

$pernyataan_sql_rayhanrp    = 'SELECT gm.id, gm.aktif,
                  a.akun_id, a.nama_lengkap AS guru_nama,
                  g.grup_id, g.nama_grup, g.jurusan,
                  mp.matpel_id, mp.nama AS matpel_nama, mp.kode AS matpel_kode,
                  gm.hari, gm.jam_mulai, gm.jam_selesai,
                  gm.dibuat_pada
           FROM guru_mengajar_rayhanRP gm
           INNER JOIN akun_rayhanRP a              ON gm.akun_id   = a.akun_id
           INNER JOIN grup_rayhanRP g              ON gm.grup_id   = g.grup_id
           INNER JOIN mata_pelajaran_rayhanRP mp   ON gm.matpel_id = mp.matpel_id
           WHERE 1=1';
$parameter_rayhanrp = [];
$tipe_rayhanrp  = '';

if ($filter_guru_rayhanrp > 0) { $pernyataan_sql_rayhanrp .= ' AND gm.akun_id = ?'; $tipe_rayhanrp .= 'i'; $parameter_rayhanrp[] = $filter_guru_rayhanrp; }
if ($filter_grup_rayhanrp > 0) { $pernyataan_sql_rayhanrp .= ' AND gm.grup_id = ?'; $tipe_rayhanrp .= 'i'; $parameter_rayhanrp[] = $filter_grup_rayhanrp; }

$pernyataan_sql_rayhanrp .= ' ORDER BY a.nama_lengkap, g.nama_grup, mp.nama ASC';

$daftar_assignment_rayhanrp = sirey_fetchAll(sirey_query($pernyataan_sql_rayhanrp, $tipe_rayhanrp, ...$parameter_rayhanrp));

// Data untuk Dropdown
$daftar_guru_rayhanrp = sirey_fetchAll(sirey_query(
    'SELECT akun_id, nama_lengkap FROM akun_rayhanRP WHERE role = "guru" ORDER BY nama_lengkap ASC'
));
$daftar_grup_rayhanrp  = sirey_fetchAll(sirey_query(
    'SELECT grup_id, nama_grup, jurusan FROM grup_rayhanRP WHERE aktif = 1 ORDER BY nama_grup ASC'
));
$daftar_matpel_rayhanrp = sirey_fetchAll(sirey_query(
    'SELECT matpel_id, kode, nama FROM mata_pelajaran_rayhanRP WHERE aktif = 1 ORDER BY nama ASC'
));
?>

<div class="page-header">
  <h2>🏫 Manajemen Guru Mengajar & Wali Kelas</h2>
  <p>Tetapkan wali kelas dan atur jadwal guru mengajar mata pelajaran.</p>
</div>

<?php if ($pesan_rayhanrp !== ''): ?>
  <div class="alert alert-success">✓ <?php echo htmlspecialchars($pesan_rayhanrp); ?></div>
<?php endif; ?>
<?php if ($error_rayhanrp !== ''): ?>
  <div class="alert alert-error">⚠️ <?php echo htmlspecialchars($error_rayhanrp); ?></div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 2.5fr; gap: 24px; margin-bottom: 24px; align-items: start;">
  
  <!-- FORM 1: SET WALI KELAS -->
  <div class="card">
    <div class="card-header">
      <h3 style="color:#0369a1;">🎓 Set Wali Kelas</h3>
    </div>
    <form method="POST" style="padding:10px 20px 20px 20px;">
      <input type="hidden" name="act" value="set_wali">
      
      <div class="form-group">
        <label class="form-label">Pilih Kelas *</label>
        <select name="grup_id" id="wali_grup_id" class="form-control" required>
          <option value="">-- Pilih Kelas --</option>
          <?php foreach ($daftar_grup_rayhanrp as $item_grup_rayhanrp): ?>
            <option value="<?php echo $item_grup_rayhanrp['grup_id']; ?>"><?php echo htmlspecialchars($item_grup_rayhanrp['nama_grup']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Tugaskan Guru *</label>
        <select name="guru_id" id="wali_guru_id" class="form-control" required>
          <option value="">-- Belum Ada Wali Kelas --</option>
          <?php foreach ($daftar_guru_rayhanrp as $item_guru_rayhanrp): ?>
            <option value="<?php echo $item_guru_rayhanrp['akun_id']; ?>"><?php echo htmlspecialchars($item_guru_rayhanrp['nama_lengkap']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center;">Simpan Wali Kelas</button>
    </form>
  </div>

  <!-- FORM 2: TAMBAH JADWAL MENGAJAR -->
  <div class="card">
    <div class="card-header">
      <h3 style="color:#15803d;">+ Tambah Jadwal Mengajar Guru</h3>
    </div>
    <form method="POST" style="padding:10px 20px 20px 20px;">
      <input type="hidden" name="act" value="create">
      <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:14px; margin-bottom:14px;">
        <div class="form-group" style="margin:0;">
          <label class="form-label">Guru *</label>
          <select name="akun_id" class="form-control" required>
            <option value="">-- Pilih Guru --</option>
            <?php foreach ($daftar_guru_rayhanrp as $item_guru_rayhanrp): ?>
              <option value="<?php echo $item_guru_rayhanrp['akun_id']; ?>"><?php echo htmlspecialchars($item_guru_rayhanrp['nama_lengkap']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group" style="margin:0;">
          <label class="form-label">Kelas *</label>
          <select name="grup_id" class="form-control" required>
            <option value="">-- Pilih Kelas --</option>
            <?php foreach ($daftar_grup_rayhanrp as $item_grup_rayhanrp): ?>
              <option value="<?php echo $item_grup_rayhanrp['grup_id']; ?>"><?php echo htmlspecialchars($item_grup_rayhanrp['nama_grup']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group" style="margin:0;">
          <label class="form-label">Mata Pelajaran *</label>
          <select name="matpel_id" class="form-control" required>
            <option value="">-- Pilih Mapel --</option>
            <?php foreach ($daftar_matpel_rayhanrp as $item_matpel_rayhanrp): ?>
              <option value="<?php echo $item_matpel_rayhanrp['matpel_id']; ?>">
                <?php echo htmlspecialchars($item_matpel_rayhanrp['kode'] . ' — ' . $item_matpel_rayhanrp['nama']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div style="display:grid; grid-template-columns:repeat(3, 1fr) auto; gap:14px; align-items:flex-end;">
        <div class="form-group" style="margin:0;">
          <label class="form-label">Hari *</label>
          <select name="hari" class="form-control" required>
            <option value="">-- Pilih Hari --</option>
            <?php foreach (['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'] as $hari_loop_rayhanrp): ?>
              <option value="<?php echo $hari_loop_rayhanrp; ?>"><?php echo $hari_loop_rayhanrp; ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group" style="margin:0;">
          <label class="form-label">Mulai *</label>
          <input type="time" name="jam_mulai" class="form-control" required>
        </div>

        <div class="form-group" style="margin:0;">
          <label class="form-label">Selesai *</label>
          <input type="time" name="jam_selesai" class="form-control" required>
        </div>

        <div style="margin:0;">
          <button type="submit" class="btn btn-success">+ Tambah Jadwal</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Filter & Tabel -->
<div class="card">
  <div class="card-header">
    <h3>Daftar Jadwal Mengajar (<?php echo count($daftar_assignment_rayhanrp); ?>)</h3>
  </div>

  <!-- Filter -->
  <div style="padding:16px 20px; background:#f8f9fa; border-bottom:1px solid #dee2e6;">
    <form method="GET" style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">
      <div>
        <label class="form-label" style="font-size:13px;">Filter Guru</label>
        <select name="filter_guru" class="form-control" style="min-width:200px;">
          <option value="">Semua Guru</option>
          <?php foreach ($daftar_guru_rayhanrp as $item_guru_rayhanrp): ?>
            <option value="<?php echo $item_guru_rayhanrp['akun_id']; ?>"
                    <?php echo $filter_guru_rayhanrp === (int)$item_guru_rayhanrp['akun_id'] ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($item_guru_rayhanrp['nama_lengkap']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="form-label" style="font-size:13px;">Filter Kelas</label>
        <select name="filter_grup" class="form-control" style="min-width:180px;">
          <option value="">Semua Kelas</option>
          <?php foreach ($daftar_grup_rayhanrp as $item_grup_rayhanrp): ?>
            <option value="<?php echo $item_grup_rayhanrp['grup_id']; ?>"
                    <?php echo $filter_grup_rayhanrp === (int)$item_grup_rayhanrp['grup_id'] ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($item_grup_rayhanrp['nama_grup']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn btn-primary btn-sm">🔍 Filter</button>
      <?php if ($filter_guru_rayhanrp || $filter_grup_rayhanrp): ?>
        <a href="manage_guru_mengajar.php" class="btn btn-secondary btn-sm">✕ Reset</a>
      <?php endif; ?>
    </form>
  </div>

  <?php if (empty($daftar_assignment_rayhanrp)): ?>
    <div class="empty-state">
      <div class="empty-icon">🏫</div>
      <p>Belum ada jadwal mengajar guru.</p>
    </div>
  <?php else: ?>
    <table class="data-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Guru</th>
          <th>Kelas</th>
          <th>Mata Pelajaran</th>
          <th>Hari</th>
          <th>Waktu</th>
          <th>Status</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($daftar_assignment_rayhanrp as $indeks_rayhanrp => $baris_assignment_rayhanrp): ?>
          <tr style="<?php echo !$baris_assignment_rayhanrp['aktif'] ? 'opacity:0.55;' : ''; ?>">
            <td style="color:var(--clr-muted); font-size:12px;"><?php echo $indeks_rayhanrp + 1; ?></td>
            <td><strong><?php echo htmlspecialchars($baris_assignment_rayhanrp['guru_nama']); ?></strong></td>
            <td><?php echo htmlspecialchars($baris_assignment_rayhanrp['nama_grup']); ?></td>
            <td>
              <span class="badge badge-default">
                <?php echo htmlspecialchars($baris_assignment_rayhanrp['matpel_kode']); ?>
              </span>
              <?php echo htmlspecialchars($baris_assignment_rayhanrp['matpel_nama']); ?>
            </td>
            <td><?php echo htmlspecialchars($baris_assignment_rayhanrp['hari'] ?? '-'); ?></td>
            <td style="font-size:12px;">
              <?php 
                $jam_mulai = $baris_assignment_rayhanrp['jam_mulai'] ? substr($baris_assignment_rayhanrp['jam_mulai'], 0, 5) : '-';
                $jam_selesai = $baris_assignment_rayhanrp['jam_selesai'] ? substr($baris_assignment_rayhanrp['jam_selesai'], 0, 5) : '-';
                echo htmlspecialchars($jam_mulai . ' - ' . $jam_selesai);
              ?>
            </td>
            <td>
              <?php if ($baris_assignment_rayhanrp['aktif']): ?>
                <span style="color:#15803d; font-weight:bold;">✓ Aktif</span>
              <?php else: ?>
                <span style="color:#b91c1c;">✗ Nonaktif</span>
              <?php endif; ?>
            </td>
            <td style="white-space:nowrap;">
              <button type="button" class="btn btn-info btn-sm" onclick='openEditModal(<?php echo htmlspecialchars(json_encode($baris_assignment_rayhanrp, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)); ?>)'>Edit</button>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="act" value="toggle">
                <input type="hidden" name="id" value="<?php echo $baris_assignment_rayhanrp['id']; ?>">
                <input type="hidden" name="aktif" value="<?php echo $baris_assignment_rayhanrp['aktif']; ?>">
                <button type="submit" class="btn btn-secondary btn-sm">
                  <?php echo $baris_assignment_rayhanrp['aktif'] ? '⏸' : '▶'; ?>
                </button>
              </form>
              <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus jadwal ini?')">
                <input type="hidden" name="act" value="delete">
                <input type="hidden" name="id" value="<?php echo $baris_assignment_rayhanrp['id']; ?>">
                <button type="submit" class="btn btn-danger btn-sm">🗑</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<!-- Modal Edit Jadwal -->
<div id="editModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1000; align-items:center; justify-content:center;">
  <div style="background:#fff; padding:28px; border-radius:8px; width:min(520px, 92vw); max-height:90vh; overflow:auto;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px;">
      <h3 style="margin:0;">Edit Jadwal</h3>
      <button type="button" class="btn btn-secondary btn-sm" onclick="closeEditModal()">Tutup</button>
    </div>
    <form method="POST" style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
      <input type="hidden" name="act" value="update">
      <input type="hidden" name="id" id="edit_id">
      
      <div class="form-group" style="margin:0; grid-column:1/-1;">
        <label class="form-label">Info Assignment</label>
        <p id="edit_info" style="margin:0; font-size:13px; color:#666;"></p>
      </div>

      <div class="form-group" style="margin:0;">
        <label class="form-label">Hari *</label>
        <select name="hari" id="edit_hari" class="form-control" required>
          <option value="">-- Pilih --</option>
          <?php foreach (['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'] as $hari_loop): ?>
            <option value="<?php echo $hari_loop; ?>"><?php echo $hari_loop; ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group" style="margin:0;">
        <label class="form-label">Jam Mulai *</label>
        <input type="time" name="jam_mulai" id="edit_jam_mulai" class="form-control" required>
      </div>

      <div class="form-group" style="margin:0;">
        <label class="form-label">Jam Selesai *</label>
        <input type="time" name="jam_selesai" id="edit_jam_selesai" class="form-control" required>
      </div>

      <div style="grid-column:1/-1; display:flex; gap:10px;">
        <button type="submit" class="btn btn-primary">💾 Simpan</button>
        <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Batal</button>
      </div>
    </form>
  </div>
</div>

<script>
// Logic AJAX untuk mengambil nama Wali Kelas saat ini (di form Wali Kelas)
document.getElementById('wali_grup_id')?.addEventListener('change', async function () {
  const grupId = this.value;
  const selectGuru = document.getElementById('wali_guru_id');

  // Reset dropdown
  selectGuru.value = '';

  if (!grupId) return;

  try {
    const response = await fetch('manage_guru_mengajar.php?action=get_wali_by_grup&grup_id=' + encodeURIComponent(grupId));
    const data = await response.json();

    if (data.success && data.wali_kelas_id > 0) {
      // Set guru yang terpilih di dropdown
      selectGuru.value = data.wali_kelas_id;
    }
  } catch (error) {
    console.error('Gagal memuat wali kelas:', error);
  }
});

function openEditModal(data) {
  document.getElementById('edit_id').value = data.id || '';
  document.getElementById('edit_info').textContent = 
    (data.guru_nama || '-') + ' — ' + (data.nama_grup || '-') + ' — ' + (data.matpel_nama || '-');
  document.getElementById('edit_hari').value = data.hari || '';
  document.getElementById('edit_jam_mulai').value = (data.jam_mulai || '').substring(0, 5);
  document.getElementById('edit_jam_selesai').value = (data.jam_selesai || '').substring(0, 5);
  document.getElementById('editModal').style.display = 'flex';
}

function closeEditModal() {
  document.getElementById('editModal').style.display = 'none';
}

document.getElementById('editModal')?.addEventListener('click', function (event) {
  if (event.target === this) {
    closeEditModal();
  }
});
</script>

<?php layoutEnd(); ?>