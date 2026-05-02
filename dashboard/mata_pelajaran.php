<?php
declare(strict_types=1);

$judul_halaman_rayhanrp = 'Mata Pelajaran';
$menu_aktif_rayhanrp    = 'mata_pelajaran';
require_once __DIR__ . '/_layout.php';

if (!can('view_mata_pelajaran', $data_admin_rayhanrp)) {
    header('Location: dashboard.php?err=akses'); exit;
}

$bisa_tulis = can('manage_mata_pelajaran', $data_admin_rayhanrp);
$pesan = $error = '';

// Cek kolom kategori
$hasKategori = false;
$chk = sirey_query("DESCRIBE mata_pelajaran_rayhanRP kategori");
if ($chk) { $row = sirey_fetch($chk); $hasKategori = ($row !== null); }

// ── POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $bisa_tulis) {
    requireNotReadonly($data_admin_rayhanrp, 'mata_pelajaran.php');
    $aksi = (string)($_POST['act'] ?? '');

    if ($aksi === 'create') {
        $kode = strtoupper(trim((string)($_POST['kode'] ?? '')));
        $nama = trim((string)($_POST['nama'] ?? ''));
        $kat  = in_array($_POST['kategori'] ?? '', ['umum','kejuruan','pilihan'], true)
                ? (string)$_POST['kategori'] : 'umum';

        if ($kode === '' || $nama === '') { $error = 'Kode dan nama wajib diisi.'; }
        else {
            if ($hasKategori) {
                $h = sirey_execute('INSERT INTO mata_pelajaran_rayhanRP (kode,nama,kategori) VALUES (?,?,?)', 'sss',$kode,$nama,$kat);
            } else {
                $h = sirey_execute('INSERT INTO mata_pelajaran_rayhanRP (kode,nama) VALUES (?,?)', 'ss',$kode,$nama);
            }
            if ($h >= 1) {
                auditLog($data_admin_rayhanrp['id'],'create_matpel','mata_pelajaran',sirey_lastInsertId(),['kode'=>$kode,'nama'=>$nama]);
                $pesan = 'Mata pelajaran berhasil ditambahkan.';
            } else { $error = 'Gagal – kode mungkin sudah ada.'; }
        }

    } elseif ($aksi === 'update') {
        $id   = (int)($_POST['id'] ?? 0);
        $kode = strtoupper(trim((string)($_POST['kode'] ?? '')));
        $nama = trim((string)($_POST['nama'] ?? ''));
        $kat  = in_array($_POST['kategori'] ?? '', ['umum','kejuruan','pilihan'], true)
                ? (string)$_POST['kategori'] : 'umum';

        if ($id <= 0 || $kode === '' || $nama === '') { $error = 'Data tidak valid.'; }
        else {
            if ($hasKategori) {
                sirey_execute('UPDATE mata_pelajaran_rayhanRP SET kode=?,nama=?,kategori=? WHERE matpel_id=?','sssi',$kode,$nama,$kat,$id);
            } else {
                sirey_execute('UPDATE mata_pelajaran_rayhanRP SET kode=?,nama=? WHERE matpel_id=?','ssi',$kode,$nama,$id);
            }
            auditLog($data_admin_rayhanrp['id'],'update_matpel','mata_pelajaran',$id,['kode'=>$kode,'nama'=>$nama]);
            $pesan = 'Mata pelajaran berhasil diperbarui.';
        }

    } elseif ($aksi === 'toggle') {
        $id    = (int)($_POST['id'] ?? 0);
        $aktif = (int)($_POST['aktif'] ?? 1);
        if ($id > 0 && $id !== 1) {
            $baru = $aktif ? 0 : 1;
            sirey_execute('UPDATE mata_pelajaran_rayhanRP SET aktif=? WHERE matpel_id=?','ii',$baru,$id);
            auditLog($data_admin_rayhanrp['id'],'toggle_matpel','mata_pelajaran',$id,['aktif'=>$baru]);
            $pesan = $baru ? 'Mata pelajaran diaktifkan.' : 'Mata pelajaran dinonaktifkan.';
        }

    } elseif ($aksi === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === 1) { $error = 'Mata pelajaran default tidak dapat dihapus.'; }
        elseif ($id > 0) {
            sirey_execute('DELETE FROM mata_pelajaran_rayhanRP WHERE matpel_id=?','i',$id);
            auditLog($data_admin_rayhanrp['id'],'delete_matpel','mata_pelajaran',$id);
            $pesan = 'Mata pelajaran dihapus.';
        }
    }
}

// ── Query ──
$sqlKat = $hasKategori ? 'matpel_id,kode,nama,kategori,aktif,dibuat_pada'
                       : 'matpel_id,kode,nama,"umum" AS kategori,aktif,dibuat_pada';
$daftar = sirey_fetchAll(sirey_query("SELECT $sqlKat FROM mata_pelajaran_rayhanRP ORDER BY nama ASC"));
?>

<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2">
  <div>
    <h2><i class="bi bi-book-fill text-primary me-2"></i>Mata Pelajaran</h2>
    <p>Kelola daftar mata pelajaran untuk jadwal, tugas, dan assignment guru.</p>
  </div>
  <?php if ($bisa_tulis): ?>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambah">
      <i class="bi bi-plus-lg me-1"></i>Tambah Mapel
    </button>
  <?php endif; ?>
</div>

<?php if ($pesan !== ''): ?>
  <div class="alert alert-success alert-dismissible fade show alert-auto-dismiss mb-3">
    <i class="bi bi-check-circle-fill me-2"></i><?php echo htmlspecialchars($pesan); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>
<?php if ($error !== ''): ?>
  <div class="alert alert-danger alert-dismissible fade show mb-3">
    <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($error); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<div class="card">
  <div class="card-header">
    <h5><i class="bi bi-table me-2"></i>Daftar Mata Pelajaran
      <span class="badge bg-primary ms-1"><?php echo count($daftar); ?></span>
    </h5>
  </div>
  <div class="card-body p-0">
    <?php if (empty($daftar)): ?>
      <div class="empty-state"><i class="bi bi-book"></i><p>Belum ada mata pelajaran.</p></div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover mb-0" id="tblMapel">
          <thead>
            <tr>
              <th>#</th><th>Kode</th><th>Nama</th>
              <?php if ($hasKategori): ?><th>Kategori</th><?php endif; ?>
              <th>Status</th><th>Ditambah</th>
              <?php if ($bisa_tulis): ?><th class="text-center">Aksi</th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($daftar as $i => $mp): ?>
              <tr style="<?php echo !(int)$mp['aktif'] ? 'opacity:.55;' : ''; ?>">
                <td class="text-muted" style="font-size:12px;"><?php echo $i + 1; ?></td>
                <td><span class="badge bg-dark"><?php echo htmlspecialchars($mp['kode']); ?></span></td>
                <td>
                  <?php echo htmlspecialchars($mp['nama']); ?>
                  <?php if ((int)$mp['matpel_id'] === 1): ?><span class="badge bg-secondary ms-1" style="font-size:10px;">default</span><?php endif; ?>
                </td>
                <?php if ($hasKategori): ?>
                  <td>
                    <?php
                      $katBadge = match($mp['kategori']) {
                        'kejuruan' => ['bg-warning text-dark', 'Kejuruan'],
                        'pilihan'  => ['bg-info text-dark', 'Pilihan'],
                        default    => ['bg-secondary', 'Umum'],
                      };
                    ?>
                    <span class="badge <?php echo $katBadge[0]; ?>"><?php echo $katBadge[1]; ?></span>
                  </td>
                <?php endif; ?>
                <td>
                  <span class="badge <?php echo (int)$mp['aktif'] ? 'bg-success' : 'bg-secondary'; ?>">
                    <?php echo (int)$mp['aktif'] ? 'Aktif' : 'Nonaktif'; ?>
                  </span>
                </td>
                <td class="text-muted" style="font-size:12px;"><?php echo formatDatetime($mp['dibuat_pada'],'d/m/Y'); ?></td>
                <?php if ($bisa_tulis): ?>
                  <td class="text-center">
                    <?php if ((int)$mp['matpel_id'] !== 1): ?>
                      <div class="d-flex gap-1 justify-content-center">
                        <button class="btn btn-sm btn-outline-primary"
                                onclick='openEdit(<?php echo json_encode($mp, JSON_HEX_TAG|JSON_HEX_QUOT|JSON_HEX_AMP); ?>)'
                                title="Edit"><i class="bi bi-pencil"></i></button>
                        <form method="POST" class="m-0">
                          <input type="hidden" name="act" value="toggle">
                          <input type="hidden" name="id" value="<?php echo (int)$mp['matpel_id']; ?>">
                          <input type="hidden" name="aktif" value="<?php echo (int)$mp['aktif']; ?>">
                          <button type="submit" class="btn btn-sm btn-outline-secondary"
                                  title="<?php echo (int)$mp['aktif'] ? 'Nonaktifkan' : 'Aktifkan'; ?>">
                            <i class="bi <?php echo (int)$mp['aktif'] ? 'bi-pause' : 'bi-play'; ?>"></i>
                          </button>
                        </form>
                        <form method="POST" class="m-0" data-confirm="Hapus mapel ini? Assignment guru terkait juga terhapus.">
                          <input type="hidden" name="act" value="delete">
                          <input type="hidden" name="id" value="<?php echo (int)$mp['matpel_id']; ?>">
                          <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button>
                        </form>
                      </div>
                    <?php else: ?>
                      <span class="text-muted small">Terlindungi</span>
                    <?php endif; ?>
                  </td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php if ($bisa_tulis): ?>
<!-- MODAL TAMBAH -->
<div class="modal fade" id="modalTambah" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="act" value="create">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Tambah Mata Pelajaran</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-sm-4">
              <label class="form-label">Kode <span class="text-danger">*</span></label>
              <input type="text" name="kode" class="form-control" placeholder="MTK" maxlength="20"
                     style="text-transform:uppercase;" required>
            </div>
            <div class="col-sm-8">
              <label class="form-label">Nama Mata Pelajaran <span class="text-danger">*</span></label>
              <input type="text" name="nama" class="form-control" placeholder="Contoh: Matematika" required>
            </div>
            <?php if ($hasKategori): ?>
            <div class="col-sm-6">
              <label class="form-label">Kategori</label>
              <select name="kategori" class="form-select">
                <option value="umum">Umum</option>
                <option value="kejuruan">Kejuruan</option>
                <option value="pilihan">Pilihan</option>
              </select>
            </div>
            <?php else: ?>
              <input type="hidden" name="kategori" value="umum">
            <?php endif; ?>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- MODAL EDIT -->
<div class="modal fade" id="modalEdit" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="act" value="update">
        <input type="hidden" name="id" id="edit_id">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Mata Pelajaran</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-sm-4">
              <label class="form-label">Kode <span class="text-danger">*</span></label>
              <input type="text" name="kode" id="edit_kode" class="form-control" maxlength="20"
                     style="text-transform:uppercase;" required>
            </div>
            <div class="col-sm-8">
              <label class="form-label">Nama <span class="text-danger">*</span></label>
              <input type="text" name="nama" id="edit_nama" class="form-control" required>
            </div>
            <?php if ($hasKategori): ?>
            <div class="col-sm-6">
              <label class="form-label">Kategori</label>
              <select name="kategori" id="edit_kategori" class="form-select">
                <option value="umum">Umum</option>
                <option value="kejuruan">Kejuruan</option>
                <option value="pilihan">Pilihan</option>
              </select>
            </div>
            <?php else: ?>
              <input type="hidden" name="kategori" id="edit_kategori" value="umum">
            <?php endif; ?>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
$(document).ready(function () {
  $('#tblMapel').DataTable({ columnDefs:[{ orderable:false, targets:<?php echo $bisa_tulis ? '-1' : '99'; ?> }] });
});
function openEdit(mp) {
  document.getElementById('edit_id').value    = mp.matpel_id;
  document.getElementById('edit_kode').value  = mp.kode;
  document.getElementById('edit_nama').value  = mp.nama;
  const kat = document.getElementById('edit_kategori');
  if (kat) kat.value = mp.kategori || 'umum';
  new bootstrap.Modal(document.getElementById('modalEdit')).show();
}
</script>

<?php layoutEnd(); ?>