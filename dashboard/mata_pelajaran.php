<?php
declare(strict_types=1);

$judul_halaman_rayhanrp  = 'Mata Pelajaran';
$menu_aktif_rayhanrp = 'mata_pelajaran';

require_once __DIR__ . '/_layout.php';

if (!can('view_mata_pelajaran', $data_admin_rayhanrp)) {
    header('Location: dashboard.php?err=akses');
    exit;
}

$bisa_tulis_rayhanrp = can('manage_mata_pelajaran', $data_admin_rayhanrp);
$database_rayhanrp  = sirey_getDatabase();
$pesan_rayhanrp = '';
$error_rayhanrp = '';


// ================== ACTION (POST) ==================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $bisa_tulis_rayhanrp) {
    requireNotReadonly($data_admin_rayhanrp, 'mata_pelajaran.php');

    $aksi_rayhanrp = (string)($_POST['act'] ?? '');

    if ($aksi_rayhanrp === 'create') {
        $kode_rayhanrp = strtoupper(trim((string)($_POST['kode'] ?? '')));
        $nama_rayhanrp = trim((string)($_POST['nama'] ?? ''));
        $kategori_rayhanrp = (string)($_POST['kategori'] ?? 'umum');

        if ($kode_rayhanrp === '' || $nama_rayhanrp === '') {
            $error_rayhanrp = 'Kode dan nama wajib diisi.';
        } elseif (!in_array($kategori_rayhanrp, ['umum', 'kejuruan', 'pilihan'], true)) {
            $error_rayhanrp = 'Kategori tidak valid.';
        } else {
            // Check if kategori column exists before inserting
            $has_kategori = false;
            $test_col = sirey_query("DESCRIBE mata_pelajaran_rayhanRP kategori");
            if ($test_col) {
                $col_info = sirey_fetch($test_col);
                $has_kategori = $col_info !== false && $col_info !== null;
            }
            
            if ($has_kategori) {
                $hasil_rayhanrp = sirey_execute(
                    'INSERT INTO mata_pelajaran_rayhanRP (kode, nama, kategori) VALUES (?, ?, ?)',
                    'sss', $kode_rayhanrp, $nama_rayhanrp, $kategori_rayhanrp
                );
            } else {
                $hasil_rayhanrp = sirey_execute(
                    'INSERT INTO mata_pelajaran_rayhanRP (kode, nama) VALUES (?, ?)',
                    'ss', $kode_rayhanrp, $nama_rayhanrp
                );
            }
            
            if ($hasil_rayhanrp >= 1) {
                auditLog($data_admin_rayhanrp['id'], 'create_matpel', 'mata_pelajaran', sirey_lastInsertId(),
                    ['kode' => $kode_rayhanrp, 'nama' => $nama_rayhanrp, 'kategori' => $kategori_rayhanrp]);
                $pesan_rayhanrp = 'Mata pelajaran berhasil ditambahkan.';
            } else {
                $error_rayhanrp = 'Gagal (kode mungkin sudah ada).';
            }
        }

    } elseif ($aksi_rayhanrp === 'update') {
        $id_rayhanrp   = (int)($_POST['id'] ?? 0);
        $kode_rayhanrp = strtoupper(trim((string)($_POST['kode'] ?? '')));
        $nama_rayhanrp = trim((string)($_POST['nama'] ?? ''));
        $kategori_rayhanrp = (string)($_POST['kategori'] ?? 'umum');

        if ($id_rayhanrp <= 0 || $kode_rayhanrp === '' || $nama_rayhanrp === '') {
            $error_rayhanrp = 'ID, kode, dan nama wajib diisi.';
        } elseif (!in_array($kategori_rayhanrp, ['umum', 'kejuruan', 'pilihan'], true)) {
            $error_rayhanrp = 'Kategori tidak valid.';
        } else {
            // Check if kategori column exists before updating
            $has_kategori = false;
            $test_col = sirey_query("DESCRIBE mata_pelajaran_rayhanRP kategori");
            if ($test_col) {
                $col_info = sirey_fetch($test_col);
                $has_kategori = $col_info !== false && $col_info !== null;
            }
            
            if ($has_kategori) {
                sirey_execute(
                    'UPDATE mata_pelajaran_rayhanRP SET kode = ?, nama = ?, kategori = ? WHERE matpel_id = ?',
                    'sssi', $kode_rayhanrp, $nama_rayhanrp, $kategori_rayhanrp, $id_rayhanrp
                );
            } else {
                sirey_execute(
                    'UPDATE mata_pelajaran_rayhanRP SET kode = ?, nama = ? WHERE matpel_id = ?',
                    'ssi', $kode_rayhanrp, $nama_rayhanrp, $id_rayhanrp
                );
            }
            
            auditLog($data_admin_rayhanrp['id'], 'update_matpel', 'mata_pelajaran', $id_rayhanrp, ['kode' => $kode_rayhanrp, 'nama' => $nama_rayhanrp, 'kategori' => $kategori_rayhanrp]);
            $pesan_rayhanrp = 'Mata pelajaran berhasil diperbarui.';
        }

    } elseif ($aksi_rayhanrp === 'toggle') {
        $id_rayhanrp    = (int)($_POST['id'] ?? 0);
        $aktif_rayhanrp = (int)($_POST['aktif'] ?? 1);
        if ($id_rayhanrp > 0 && $id_rayhanrp !== 1) { // Jangan toggle UMUM
            $aktif_baru_rayhanrp = $aktif_rayhanrp ? 0 : 1;
            sirey_execute('UPDATE mata_pelajaran_rayhanRP SET aktif = ? WHERE matpel_id = ?', 'ii', $aktif_baru_rayhanrp, $id_rayhanrp);
            auditLog($data_admin_rayhanrp['id'], 'toggle_matpel', 'mata_pelajaran', $id_rayhanrp, ['aktif' => $aktif_baru_rayhanrp]);
            $pesan_rayhanrp = $aktif_baru_rayhanrp ? 'Mata pelajaran diaktifkan.' : 'Mata pelajaran dinonaktifkan.';
        }

    } elseif ($aksi_rayhanrp === 'delete') {
        $id_rayhanrp = (int)($_POST['id'] ?? 0);
        if ($id_rayhanrp > 0 && $id_rayhanrp !== 1) { // Jangan hapus UMUM
            sirey_execute('DELETE FROM mata_pelajaran_rayhanRP WHERE matpel_id = ?', 'i', $id_rayhanrp);
            auditLog($data_admin_rayhanrp['id'], 'delete_matpel', 'mata_pelajaran', $id_rayhanrp);
            $pesan_rayhanrp = 'Mata pelajaran dihapus.';
        } else {
            $error_rayhanrp = 'Mata pelajaran UMUM tidak dapat dihapus.';
        }
    }
}


// ================== QUERY ==================
// Check if kategori column exists
$kategori_exists = false;
try {
    $test_result = sirey_query("DESCRIBE mata_pelajaran_rayhanRP kategori");
    if ($test_result) {
        $col_info = sirey_fetch($test_result);
        $kategori_exists = $col_info !== false && $col_info !== null;
    }
} catch (Exception $e) {
    // Column doesn't exist
}

// Build query based on column existence
$sql_query = $kategori_exists
    ? 'SELECT matpel_id, kode, nama, kategori, aktif, dibuat_pada FROM mata_pelajaran_rayhanRP ORDER BY nama ASC'
    : 'SELECT matpel_id, kode, nama, "umum" as kategori, aktif, dibuat_pada FROM mata_pelajaran_rayhanRP ORDER BY nama ASC';

$daftar_matpel_rayhanrp = sirey_fetchAll(sirey_query($sql_query));
?>

<div class="page-header">
  <h2>📚 Mata Pelajaran</h2>
  <p>Kelola daftar mata pelajaran yang dapat digunakan di jadwal, tugas, dan assignment guru.</p>
</div>

<?php if ($pesan_rayhanrp !== ''): ?>
  <div class="alert alert-success">✓ <?php echo htmlspecialchars($pesan_rayhanrp); ?></div>
<?php endif; ?>
<?php if ($error_rayhanrp !== ''): ?>
  <div class="alert alert-error">⚠️ <?php echo htmlspecialchars($error_rayhanrp); ?></div>
<?php endif; ?>


<!-- Form Tambah -->
<?php if ($bisa_tulis_rayhanrp): ?>
<div class="card" style="margin-bottom:24px;">
  <div class="card-header"><h3>+ Tambah Mata Pelajaran</h3></div>
  <form method="POST" style="padding:20px;">
    <input type="hidden" name="act" value="create">
    <div style="display:grid; grid-template-columns:150px 1fr 150px auto; gap:14px; align-items:flex-end;">
      <div class="form-group" style="margin:0;">
        <label class="form-label">Kode *</label>
        <input name="kode" type="text" class="form-control" required
               placeholder="Contoh: MTK" maxlength="20" style="text-transform:uppercase;">
      </div>
      <div class="form-group" style="margin:0;">
        <label class="form-label">Nama Mata Pelajaran *</label>
        <input name="nama" type="text" class="form-control" required
               placeholder="Contoh: Matematika">
      </div>
      <?php if ($kategori_exists): ?>
      <div class="form-group" style="margin:0;">
        <label class="form-label">Kategori *</label>
        <select name="kategori" class="form-control">
          <option value="umum">Umum</option>
          <option value="kejuruan">Kejuruan</option>
          <option value="pilihan">Pilihan</option>
        </select>
      </div>
      <?php endif; ?>
      <div>
        <button type="submit" class="btn btn-primary">+ Tambah</button>
      </div>
    </div>
  </form>
</div>
<?php endif; ?>


<!-- Tabel -->
<div class="card">
  <div class="card-header">
    <h3>Daftar Mata Pelajaran (<?php echo count($daftar_matpel_rayhanrp); ?>)</h3>
  </div>

  <?php if (empty($daftar_matpel_rayhanrp)): ?>
    <div class="empty-state">
      <div class="empty-icon">📚</div>
      <p>Belum ada mata pelajaran.</p>
    </div>
  <?php else: ?>
    <table class="data-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Kode</th>
          <th>Nama</th>
          <?php if ($kategori_exists): ?><th>Kategori</th><?php endif; ?>
          <th>Status</th>
          <th>Ditambah</th>
          <?php if ($bisa_tulis_rayhanrp): ?><th>Aksi</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($daftar_matpel_rayhanrp as $i_rayhanrp => $mp_rayhanrp): ?>
          <tr style="<?php echo !$mp_rayhanrp['aktif'] ? 'opacity:0.55;' : ''; ?>">
            <td style="color:var(--clr-muted); font-size:12px;"><?php echo $i_rayhanrp + 1; ?></td>
            <td>
              <span class="badge badge-default"><?php echo htmlspecialchars($mp_rayhanrp['kode']); ?></span>
            </td>
            <td>
              <?php echo htmlspecialchars($mp_rayhanrp['nama']); ?>
              <?php if ($mp_rayhanrp['matpel_id'] === 1): ?>
                <small style="color:var(--clr-muted);">(default)</small>
              <?php endif; ?>
            </td>
            <?php if ($kategori_exists): ?>
            <td>
              <?php
                $badge_color = match($mp_rayhanrp['kategori']) {
                  'kejuruan' => 'badge-warning',
                  'pilihan' => 'badge-info',
                  default => 'badge-secondary'
                };
                $kategori_label = match($mp_rayhanrp['kategori']) {
                  'kejuruan' => 'Kejuruan',
                  'pilihan' => 'Pilihan',
                  default => 'Umum'
                };
              ?>
              <span class="badge <?php echo $badge_color; ?>"><?php echo $kategori_label; ?></span>
            </td>
            <?php endif; ?>
            <td>
              <?php if ($mp_rayhanrp['aktif']): ?>
                <span style="color:#15803d; font-weight:bold;">✓ Aktif</span>
              <?php else: ?>
                <span style="color:#b91c1c;">✗ Nonaktif</span>
              <?php endif; ?>
            </td>
            <td style="color:var(--clr-muted); font-size:12px;">
              <?php echo formatDatetime($mp_rayhanrp['dibuat_pada'], 'd/m/Y'); ?>
            </td>
            <?php if ($bisa_tulis_rayhanrp): ?>
            <td style="white-space:nowrap;">
              <?php if ($mp_rayhanrp['matpel_id'] !== 1): ?>
                <button type="button" class="btn btn-primary btn-sm"
                        onclick="openEditModal(<?php echo $mp_rayhanrp['matpel_id']; ?>, '<?php echo htmlspecialchars($mp_rayhanrp['kode']); ?>', '<?php echo htmlspecialchars($mp_rayhanrp['nama']); ?>', '<?php echo htmlspecialchars($mp_rayhanrp['kategori']); ?>')">
                  ✏️ Edit
                </button>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="act" value="toggle">
                  <input type="hidden" name="id" value="<?php echo $mp_rayhanrp['matpel_id']; ?>">
                  <input type="hidden" name="aktif" value="<?php echo $mp_rayhanrp['aktif']; ?>">
                  <button type="submit" class="btn btn-secondary btn-sm">
                    <?php echo $mp_rayhanrp['aktif'] ? '⏸' : '▶'; ?>
                  </button>
                </form>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus mata pelajaran ini? Assignment guru yang terkait juga akan terhapus.')">
                  <input type="hidden" name="act" value="delete">
                  <input type="hidden" name="id" value="<?php echo $mp_rayhanrp['matpel_id']; ?>">
                  <button type="submit" class="btn btn-danger btn-sm">🗑️</button>
                </form>
              <?php else: ?>
                <span style="color:var(--clr-muted); font-size:12px;">Terlindungi</span>
              <?php endif; ?>
            </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>


<!-- Modal Edit -->
<?php if ($bisa_tulis_rayhanrp): ?>
<div id="editModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%;
     background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
  <div style="background:white; padding:30px; border-radius:8px; max-width:440px; width:90%;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
      <h3 style="margin:0;">✏️ Edit Mata Pelajaran</h3>
      <button onclick="closeEditModal()" style="background:none; border:none; font-size:24px; cursor:pointer;">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="act" value="update">
      <input type="hidden" name="id" id="edit_id">
      <div class="form-group" style="margin-bottom:14px;">
        <label class="form-label">Kode *</label>
        <input type="text" name="kode" id="edit_kode" class="form-control" required
               maxlength="20" style="text-transform:uppercase;">
      </div>
      <div class="form-group" style="margin-bottom:14px;">
        <label class="form-label">Nama *</label>
        <input type="text" name="nama" id="edit_nama" class="form-control" required>
      </div>
      <?php if ($kategori_exists): ?>
      <div class="form-group" style="margin-bottom:20px;">
        <label class="form-label">Kategori *</label>
        <select name="kategori" id="edit_kategori" class="form-control" required>
          <option value="umum">Umum</option>
          <option value="kejuruan">Kejuruan</option>
          <option value="pilihan">Pilihan</option>
        </select>
      </div>
      <?php else: ?>
      <input type="hidden" name="kategori" id="edit_kategori" value="umum">
      <?php endif; ?>
      <div style="display:flex; gap:8px;">
        <button type="submit" class="btn btn-primary" style="flex:1;">💾 Simpan</button>
        <button type="button" class="btn btn-secondary" onclick="closeEditModal()" style="flex:1;">Batal</button>
      </div>
    </form>
  </div>
</div>

<script>
  function openEditModal(id, kode, nama, kategori) {
    document.getElementById('edit_id').value   = id;
    document.getElementById('edit_kode').value = kode;
    document.getElementById('edit_nama').value = nama;
    <?php if ($kategori_exists): ?>
    document.getElementById('edit_kategori').value = kategori;
    <?php endif; ?>
    document.getElementById('editModal').style.display = 'flex';
  }
  function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
  }
  document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
  });
</script>
<?php endif; ?>

<?php layoutEnd(); ?>
