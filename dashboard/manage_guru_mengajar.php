<?php
declare(strict_types=1);

$judul_halaman_rayhanrp = 'Manajemen Guru Mengajar';
$menu_aktif_rayhanrp    = 'manage_guru_mengajar';
require_once __DIR__ . '/_layout.php';

if (!can('manage_guru_mengajar', $data_admin_rayhanrp)) {
    header('Location: dashboard.php?err=akses'); exit;
}

$pesan = $error = '';
$hari_list = ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'];

// ── POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireNotReadonly($data_admin_rayhanrp, 'manage_guru_mengajar.php');
    $aksi = (string)($_POST['act'] ?? '');

    if ($aksi === 'assign_wali') {
        $grupId = (int)($_POST['grup_id'] ?? 0);
        $waliId = (int)($_POST['wali_id'] ?? 0);

        if ($grupId <= 0) { $error = 'Kelas tidak valid.'; }
        else {
            if ($waliId > 0) {
                sirey_execute('UPDATE grup_rayhanRP SET wali_kelas_id=? WHERE grup_id=?','ii',$waliId,$grupId);
                auditLog($data_admin_rayhanrp['id'],'assign_wali_kelas','grup',$grupId,['wali_kelas_id'=>$waliId]);
                $pesan = 'Wali kelas berhasil ditugaskan.';
            } else {
                sirey_execute('UPDATE grup_rayhanRP SET wali_kelas_id=NULL WHERE grup_id=?','i',$grupId);
                auditLog($data_admin_rayhanrp['id'],'remove_wali_kelas','grup',$grupId);
                $pesan = 'Wali kelas berhasil dihapus.';
            }
        }

    } elseif ($aksi === 'create') {
        $akunId   = (int)($_POST['akun_id'] ?? 0);
        $grupId   = (int)($_POST['grup_id'] ?? 0);
        $matpelId = (int)($_POST['matpel_id'] ?? 0);
        $hari     = trim((string)($_POST['hari'] ?? ''));
        $mulai    = trim((string)($_POST['jam_mulai'] ?? ''));
        $selesai  = trim((string)($_POST['jam_selesai'] ?? ''));

        if ($akunId <= 0 || $grupId <= 0 || $matpelId <= 0) { $error = 'Guru, Kelas, dan Mapel wajib dipilih.'; }
        elseif (!in_array($hari, $hari_list, true) || $mulai === '' || $selesai === '') { $error = 'Hari dan jam wajib diisi.'; }
        elseif ($selesai <= $mulai) { $error = 'Jam selesai harus lebih besar dari jam mulai.'; }
        else {
            $h = sirey_execute(
                'INSERT IGNORE INTO guru_mengajar_rayhanRP (akun_id,grup_id,matpel_id,hari,jam_mulai,jam_selesai) VALUES (?,?,?,?,?,?)',
                'iiisss', $akunId, $grupId, $matpelId, $hari, $mulai, $selesai
            );
            if ($h >= 1) {
                auditLog($data_admin_rayhanrp['id'],'create_guru_mengajar','guru_mengajar',null,
                    ['akun_id'=>$akunId,'grup_id'=>$grupId,'matpel_id'=>$matpelId,'hari'=>$hari,'jam_mulai'=>$mulai,'jam_selesai'=>$selesai]);
                $pesan = 'Assignment berhasil ditambahkan.';
            } else { $error = 'Assignment sudah ada atau gagal disimpan.'; }
        }

    } elseif ($aksi === 'update') {
        $id      = (int)($_POST['id'] ?? 0);
        $hari    = trim((string)($_POST['hari'] ?? ''));
        $mulai   = trim((string)($_POST['jam_mulai'] ?? ''));
        $selesai = trim((string)($_POST['jam_selesai'] ?? ''));

        if ($id <= 0) { $error = 'ID tidak valid.'; }
        elseif (!in_array($hari, $hari_list, true) || $mulai === '' || $selesai === '') { $error = 'Hari dan jam wajib diisi.'; }
        elseif ($selesai <= $mulai) { $error = 'Jam selesai harus lebih besar dari jam mulai.'; }
        else {
            sirey_execute('UPDATE guru_mengajar_rayhanRP SET hari=?,jam_mulai=?,jam_selesai=? WHERE id=?',
                'sssi', $hari, $mulai, $selesai, $id);
            auditLog($data_admin_rayhanrp['id'],'update_guru_mengajar','guru_mengajar',$id,
                ['hari'=>$hari,'jam_mulai'=>$mulai,'jam_selesai'=>$selesai]);
            $pesan = 'Jadwal berhasil diperbarui.';
        }

    } elseif ($aksi === 'toggle') {
        $id    = (int)($_POST['id'] ?? 0);
        $aktif = (int)($_POST['aktif'] ?? 1);
        if ($id > 0) {
            $baru = $aktif ? 0 : 1;
            sirey_execute('UPDATE guru_mengajar_rayhanRP SET aktif=? WHERE id=?','ii',$baru,$id);
            auditLog($data_admin_rayhanrp['id'],'toggle_guru_mengajar','guru_mengajar',$id,['aktif'=>$baru]);
            $pesan = $baru ? 'Assignment diaktifkan.' : 'Assignment dinonaktifkan.';
        }

    } elseif ($aksi === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            sirey_execute('DELETE FROM guru_mengajar_rayhanRP WHERE id=?','i',$id);
            auditLog($data_admin_rayhanrp['id'],'delete_guru_mengajar','guru_mengajar',$id);
            $pesan = 'Assignment berhasil dihapus.';
        }
    }
}

// ── Query ──
$fGuru = (int)($_GET['filter_guru'] ?? 0);
$fGrup = (int)($_GET['filter_grup'] ?? 0);

$sql   = 'SELECT gm.id,gm.aktif,a.akun_id,a.nama_lengkap AS guru_nama,
                 g.grup_id,g.nama_grup,g.jurusan,
                 mp.matpel_id,mp.nama AS matpel_nama,mp.kode AS matpel_kode,
                 gm.hari,gm.jam_mulai,gm.jam_selesai,gm.dibuat_pada
          FROM guru_mengajar_rayhanRP gm
          INNER JOIN akun_rayhanRP a ON gm.akun_id=a.akun_id
          INNER JOIN grup_rayhanRP g ON gm.grup_id=g.grup_id
          INNER JOIN mata_pelajaran_rayhanRP mp ON gm.matpel_id=mp.matpel_id
          WHERE 1=1';
$types = ''; $params = [];
if ($fGuru > 0) { $sql .= ' AND gm.akun_id=?'; $types .= 'i'; $params[] = $fGuru; }
if ($fGrup > 0) { $sql .= ' AND gm.grup_id=?'; $types .= 'i'; $params[] = $fGrup; }
$sql .= ' ORDER BY a.nama_lengkap,g.nama_grup,mp.nama ASC';
$daftar = $types ? sirey_fetchAll(sirey_query($sql,$types,...$params)) : sirey_fetchAll(sirey_query($sql));

// Dropdown
$daftarGuru   = sirey_fetchAll(sirey_query('SELECT akun_id,nama_lengkap FROM akun_rayhanRP WHERE role="guru" ORDER BY nama_lengkap ASC'));
$daftarGrup   = sirey_fetchAll(sirey_query('SELECT grup_id,nama_grup,jurusan FROM grup_rayhanRP WHERE aktif=1 ORDER BY nama_grup ASC'));
$daftarMatpel = sirey_fetchAll(sirey_query('SELECT matpel_id,kode,nama FROM mata_pelajaran_rayhanRP WHERE aktif=1 ORDER BY nama ASC'));

?>

<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2">
  <div>
    <h2><i class="bi bi-building-fill text-primary me-2"></i>Manajemen Guru Mengajar</h2>
    <p>Tetapkan guru ke kelas dan mata pelajaran beserta jadwal mengajar.</p>
  </div>
  <div class="d-flex gap-2">
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambah">
      <i class="bi bi-plus-lg me-1"></i>Tambah Assignment
    </button>
    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalWaliKelas">
      <i class="bi bi-plus-lg me-1"></i>Wali Kelas
    </button>
  </div>
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

<!-- Filter -->
<div class="card mb-3">
  <div class="card-body py-3">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-sm-4">
        <label class="form-label">Filter Guru</label>
        <select name="filter_guru" class="form-select">
          <option value="">Semua Guru</option>
          <?php foreach ($daftarGuru as $g): ?>
            <option value="<?php echo $g['akun_id']; ?>" <?php echo $fGuru === (int)$g['akun_id'] ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($g['nama_lengkap']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-4">
        <label class="form-label">Filter Kelas</label>
        <select name="filter_grup" class="form-select">
          <option value="">Semua Kelas</option>
          <?php foreach ($daftarGrup as $g): ?>
            <option value="<?php echo $g['grup_id']; ?>" <?php echo $fGrup === (int)$g['grup_id'] ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($g['nama_grup']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Filter</button>
        <?php if ($fGuru || $fGrup): ?>
          <a href="manage_guru_mengajar.php" class="btn btn-outline-secondary">Reset</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<!-- Tabel -->
<div class="card">
  <div class="card-header">
    <h5><i class="bi bi-table me-2"></i>Daftar Assignment
      <span class="badge bg-primary ms-1"><?php echo count($daftar); ?></span>
    </h5>
  </div>
  <div class="card-body p-0">
    <?php if (empty($daftar)): ?>
      <div class="empty-state"><i class="bi bi-building"></i><p>Belum ada assignment guru.</p></div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover mb-0" id="tblGuru">
          <thead>
            <tr><th>#</th><th>Guru</th><th>Kelas</th><th>Mata Pelajaran</th><th>Hari</th><th>Waktu</th><th>Status</th><th class="text-center">Aksi</th></tr>
          </thead>
          <tbody>
            <?php foreach ($daftar as $i => $r): ?>
              <tr style="<?php echo !(int)$r['aktif'] ? 'opacity:.55;' : ''; ?>">
                <td class="text-muted" style="font-size:12px;"><?php echo $i + 1; ?></td>
                <td><strong><?php echo htmlspecialchars($r['guru_nama']); ?></strong></td>
                <td>
                  <?php echo htmlspecialchars($r['nama_grup']); ?>
                  <div class="text-muted" style="font-size:11px;"><?php echo htmlspecialchars($r['jurusan']); ?></div>
                </td>
                <td>
                  <span class="badge bg-dark me-1"><?php echo htmlspecialchars($r['matpel_kode']); ?></span>
                  <?php echo htmlspecialchars($r['matpel_nama']); ?>
                </td>
                <td>
                  <?php if ($r['hari']): ?>
                    <span class="badge bg-primary"><?php echo htmlspecialchars($r['hari']); ?></span>
                  <?php else: ?>
                    <span class="text-muted">—</span>
                  <?php endif; ?>
                </td>
                <td style="font-size:12px;">
                  <?php
                    $jm = $r['jam_mulai'] ? substr($r['jam_mulai'],0,5) : '—';
                    $js = $r['jam_selesai'] ? substr($r['jam_selesai'],0,5) : '—';
                    echo "$jm – $js";
                  ?>
                </td>
                <td>
                  <span class="badge <?php echo (int)$r['aktif'] ? 'bg-success' : 'bg-secondary'; ?>">
                    <?php echo (int)$r['aktif'] ? 'Aktif' : 'Nonaktif'; ?>
                  </span>
                </td>
                <td class="text-center">
                  <div class="d-flex gap-1 justify-content-center">
                    <button class="btn btn-sm btn-outline-primary"
                            onclick='openEdit(<?php echo json_encode($r, JSON_HEX_TAG|JSON_HEX_QUOT|JSON_HEX_AMP); ?>)'
                            title="Edit Jadwal"><i class="bi bi-pencil"></i></button>
                    <form method="POST" class="m-0">
                      <input type="hidden" name="act" value="toggle">
                      <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                      <input type="hidden" name="aktif" value="<?php echo (int)$r['aktif']; ?>">
                      <button type="submit" class="btn btn-sm btn-outline-secondary"
                              title="<?php echo (int)$r['aktif'] ? 'Nonaktifkan' : 'Aktifkan'; ?>">
                        <i class="bi <?php echo (int)$r['aktif'] ? 'bi-pause' : 'bi-play'; ?>"></i>
                      </button>
                    </form>
                    <form method="POST" class="m-0" data-confirm="Hapus assignment ini?">
                      <input type="hidden" name="act" value="delete">
                      <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- MODAL TAMBAH -->
<div class="modal fade" id="modalTambah" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="act" value="create">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Tambah Assignment Guru</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-sm-6">
              <label class="form-label">Guru <span class="text-danger">*</span></label>
              <select name="akun_id" class="form-select" required>
                <option value="">Pilih Guru</option>
                <?php foreach ($daftarGuru as $g): ?>
                  <option value="<?php echo $g['akun_id']; ?>"><?php echo htmlspecialchars($g['nama_lengkap']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-sm-6">
              <label class="form-label">Kelas <span class="text-danger">*</span></label>
              <select name="grup_id" class="form-select" required>
                <option value="">Pilih Kelas</option>
                <?php foreach ($daftarGrup as $g): ?>
                  <option value="<?php echo $g['grup_id']; ?>"><?php echo htmlspecialchars($g['nama_grup']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-sm-6">
              <label class="form-label">Mata Pelajaran <span class="text-danger">*</span></label>
              <select name="matpel_id" class="form-select" required>
                <option value="">Pilih Mapel</option>
                <?php foreach ($daftarMatpel as $mp): ?>
                  <option value="<?php echo $mp['matpel_id']; ?>"><?php echo htmlspecialchars($mp['kode'].' — '.$mp['nama']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-sm-6">
              <label class="form-label">Hari <span class="text-danger">*</span></label>
              <select name="hari" class="form-select" required>
                <option value="">Pilih Hari</option>
                <?php foreach ($hari_list as $h): ?>
                  <option value="<?php echo $h; ?>"><?php echo $h; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-sm-6">
              <label class="form-label">Jam Mulai <span class="text-danger">*</span></label>
              <input type="time" name="jam_mulai" class="form-control" required>
            </div>
            <div class="col-sm-6">
              <label class="form-label">Jam Selesai <span class="text-danger">*</span></label>
              <input type="time" name="jam_selesai" class="form-control" required>
            </div>
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

<!-- MODAL EDIT JADWAL -->
<div class="modal fade" id="modalEdit" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="act" value="update">
        <input type="hidden" name="id" id="edit_id">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Jadwal</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-secondary py-2 mb-3" id="edit_info" style="font-size:13px;"></div>
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Hari <span class="text-danger">*</span></label>
              <select name="hari" id="edit_hari" class="form-select" required>
                <option value="">Pilih Hari</option>
                <?php foreach ($hari_list as $h): ?>
                  <option value="<?php echo $h; ?>"><?php echo $h; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-sm-6">
              <label class="form-label">Jam Mulai <span class="text-danger">*</span></label>
              <input type="time" name="jam_mulai" id="edit_jam_mulai" class="form-control" required>
            </div>
            <div class="col-sm-6">
              <label class="form-label">Jam Selesai <span class="text-danger">*</span></label>
              <input type="time" name="jam_selesai" id="edit_jam_selesai" class="form-control" required>
            </div>
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

<!-- MODAL WALI KELAS -->
<div class="modal fade" id="modalWaliKelas" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="act" value="assign_wali">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-person-check me-2"></i>Atur Wali Kelas</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Kelas <span class="text-danger">*</span></label>
              <select name="grup_id" id="wali_grup_id" class="form-select" required>
                <option value="">Pilih Kelas</option>
                <?php foreach ($daftarGrup as $g): ?>
                  <option value="<?php echo $g['grup_id']; ?>">
                    <?php echo htmlspecialchars($g['nama_grup']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Wali Kelas <span class="text-danger">*</span></label>
              <select name="wali_id" id="wali_guru_id" class="form-select" required>
                <option value="">Pilih Guru</option>
                <?php foreach ($daftarGuru as $g): ?>
                  <option value="<?php echo $g['akun_id']; ?>"><?php echo htmlspecialchars($g['nama_lengkap']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="alert alert-info mt-3" style="font-size:13px;">
            <i class="bi bi-info-circle me-2"></i>Pilih guru untuk menjadi wali kelas.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-success"><i class="bi bi-save me-1"></i>Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
$(document).ready(function () {
  $('#tblGuru').DataTable({ columnDefs:[{orderable:false, targets:-1}] });
});

function openEdit(r) {
  document.getElementById('edit_id').value        = r.id;
  document.getElementById('edit_info').textContent = r.guru_nama + ' — ' + r.nama_grup + ' — ' + r.matpel_nama;
  document.getElementById('edit_hari').value       = r.hari || '';
  document.getElementById('edit_jam_mulai').value  = (r.jam_mulai || '').substring(0,5);
  document.getElementById('edit_jam_selesai').value= (r.jam_selesai || '').substring(0,5);
  new bootstrap.Modal(document.getElementById('modalEdit')).show();
}
</script>

<?php layoutEnd(); ?>