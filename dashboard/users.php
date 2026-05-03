<?php
declare(strict_types=1);

$judul_halaman_rayhanrp = 'Manajemen Pengguna';
$menu_aktif_rayhanrp    = 'users';
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../includes/import_functions.php';

if (!can('view_users', $data_admin_rayhanrp)) {
    header('Location: dashboard.php?err=akses'); exit;
}

$bisa_tulis = can('create_user', $data_admin_rayhanrp);
$db         = sirey_getDatabase();
$pesan      = '';
$error      = '';
$role_izin  = ['admin','guru','siswa','kepala_sekolah','kurikulum'];

// ── POST Actions ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = (string)($_POST['act'] ?? '');
    if (!$bisa_tulis) {
        $error = 'Halaman ini dalam mode baca saja.';
    } else {
        requireNotReadonly($data_admin_rayhanrp, 'users.php');

        if ($aksi === 'create') {
            $nis  = preg_replace('/\s+/','',trim((string)($_POST['nis_nip'] ?? '')));
            $nama = trim((string)($_POST['nama_lengkap'] ?? ''));
            $role = (string)($_POST['role'] ?? 'siswa');
            $jk   = trim((string)($_POST['jenis_kelamin'] ?? ''));

            if ($nis === '' || $nama === '') {
                $error = 'NIS/NIP dan nama wajib diisi.';
            } elseif (!in_array($role, $role_izin, true)) {
                $error = 'Role tidak valid.';
            } elseif (fetchAccountByNis($nis) !== null) {
                $error = 'NIS/NIP sudah terdaftar.';
            } else {
                $hasil = sirey_execute(
                    'INSERT INTO akun_rayhanRP (nis_nip,password,role,nama_lengkap,jenis_kelamin) VALUES (?,?,?,?,?)',
                    'sssss', $nis, hashPassword($nis), $role, $nama, $jk !== '' ? $jk : null
                );
                if ($hasil >= 1) {
                    $idBaru = sirey_lastInsertId();
                    auditLog($data_admin_rayhanrp['id'], 'create_user', 'akun', $idBaru, ['nis_nip'=>$nis,'role'=>$role]);
                    $pesan = "Pengguna berhasil ditambahkan. Password default: <strong>$nis</strong>";
                } else {
                    $error = sirey_lastDbErrno() === 1062 ? 'NIS/NIP sudah terdaftar.' : 'Gagal menambah pengguna.';
                }
            }

        } elseif ($aksi === 'update') {
            $id   = (int)($_POST['id'] ?? 0);
            $nama = trim((string)($_POST['nama_lengkap'] ?? ''));
            $role = (string)($_POST['role'] ?? 'siswa');
            $jk   = trim((string)($_POST['jenis_kelamin'] ?? ''));

            if ($id <= 0 || $nama === '' || !in_array($role, $role_izin, true)) {
                $error = 'Data tidak lengkap atau tidak valid.';
            } else {
                sirey_execute('UPDATE akun_rayhanRP SET nama_lengkap=?,role=?,jenis_kelamin=? WHERE akun_id=?',
                    'sssi', $nama, $role, $jk !== '' ? $jk : null, $id);
                auditLog($data_admin_rayhanrp['id'], 'update_user', 'akun', $id, ['role'=>$role]);
                $pesan = 'Pengguna berhasil diperbarui.';
            }

        } elseif ($aksi === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id === (int)$data_admin_rayhanrp['id']) {
                $error = 'Tidak dapat menghapus akun sendiri.';
            } elseif ($id > 0) {
                sirey_execute('DELETE FROM grup_anggota_rayhanRP WHERE akun_id=?','i',$id);
                sirey_execute('DELETE FROM akun_rayhanRP WHERE akun_id=?','i',$id);
                auditLog($data_admin_rayhanrp['id'],'delete_user','akun',$id);
                $pesan = 'Pengguna berhasil dihapus.';
            }

        } elseif ($aksi === 'delete_multiple') {
            $ids = array_filter(array_map('intval',(array)($_POST['selected_ids'] ?? [])));
            $n = 0;
            foreach ($ids as $id) {
                if ($id === (int)$data_admin_rayhanrp['id']) continue;
                sirey_execute('DELETE FROM grup_anggota_rayhanRP WHERE akun_id=?','i',$id);
                if (sirey_execute('DELETE FROM akun_rayhanRP WHERE akun_id=?','i',$id) >= 1) $n++;
            }
            auditLog($data_admin_rayhanrp['id'],'bulk_delete_user','akun',null,['ids'=>$ids]);
            $pesan = "$n pengguna berhasil dihapus.";

        } elseif ($aksi === 'reset_password') {
            $targetId  = (int)($_POST['target_id'] ?? 0);
            $newPass   = trim((string)($_POST['new_password'] ?? ''));
            $alasan    = trim((string)($_POST['alasan'] ?? ''));

            if (!can('reset_password', $data_admin_rayhanrp)) {
                $error = 'Tidak memiliki izin reset password.';
            } elseif ($targetId === (int)$data_admin_rayhanrp['id']) {
                $error = 'Gunakan halaman profil untuk mengganti password sendiri.';
            } elseif ($targetId <= 0 || strlen($newPass) < 6) {
                $error = 'Target harus valid dan password minimal 6 karakter.';
            } else {
                $target = sirey_fetch(sirey_query('SELECT akun_id,nama_lengkap,role FROM akun_rayhanRP WHERE akun_id=?','i',$targetId));
                if (!$target) {
                    $error = 'Akun target tidak ditemukan.';
                } elseif ($target['role'] === 'admin') {
                    $error = 'Password admin lain tidak dapat direset.';
                    auditLog($data_admin_rayhanrp['id'],'reset_password','akun',$targetId,['alasan'=>'target admin'],'ditolak');
                } else {
                    $ok = sirey_execute('UPDATE akun_rayhanRP SET password=? WHERE akun_id=?','si',hashPassword($newPass),$targetId);
                    sirey_execute(
                        'INSERT INTO reset_password_log_rayhanRP (target_akun_id,direset_oleh,alasan,status,sumber,ip_address) VALUES (?,?,?,?,?,?)',
                        'iissss', $targetId, $data_admin_rayhanrp['id'], $alasan ?: null,
                        $ok >= 1 ? 'berhasil' : 'gagal', 'web', (string)($_SERVER['REMOTE_ADDR'] ?? '')
                    );
                    if ($ok >= 1) {
                        auditLog($data_admin_rayhanrp['id'],'reset_password','akun',$targetId,['alasan'=>$alasan ?: null]);
                        $pesan = 'Password untuk '.$target['nama_lengkap'].' berhasil direset.';
                    } else {
                        $error = 'Gagal mereset password.';
                    }
                }
            }

        } elseif ($aksi === 'import_excel' && isset($_FILES['excel_file'])) {
            $file = $_FILES['excel_file'];
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $error = 'Upload file gagal. Error code: ' . $file['error'];
            } else {
                $hasil = importUsersFromExcel($db, (string)$file['tmp_name']);
                if (!empty($hasil['success'])) {
                    $pesan = 'Import selesai: '.((int)($hasil['imported'] ?? 0)).' berhasil, '.((int)($hasil['failed'] ?? 0)).' gagal.';
                    if (!empty($hasil['errors'])) {
                        $pesan .= '<br><small class="text-muted">Errors: ' . htmlspecialchars(implode('; ', array_slice($hasil['errors'], 0, 5))) . '</small>';
                    }
                    auditLog($data_admin_rayhanrp['id'],'import_user_excel','akun',null,['imported'=>$hasil['imported'],'failed'=>$hasil['failed']]);
                } else {
                    $error = (string)($hasil['error'] ?? 'Import gagal.');
                    if (!empty($hasil['errors'])) {
                        $error .= '<br><small>' . htmlspecialchars(implode('<br>', array_slice($hasil['errors'], 0, 10))) . '</small>';
                    }
                }
            }
        }
    }
}

// ── Query ──────────────────────────────────────────────────────────
$cari       = trim((string)($_GET['search'] ?? ''));
$filterRole = trim((string)($_GET['filter_role'] ?? ''));

$sql    = 'SELECT akun_id,nis_nip,role,nama_lengkap,jenis_kelamin,aktif,dibuat_pada FROM akun_rayhanRP WHERE 1=1';
$types  = '';
$params = [];

if ($cari !== '') {
    $sql   .= ' AND (nis_nip LIKE ? OR nama_lengkap LIKE ?)';
    $types .= 'ss'; $params[] = "%$cari%"; $params[] = "%$cari%";
}
if ($filterRole !== '') {
    $sql   .= ' AND role=?';
    $types .= 's'; $params[] = $filterRole;
}
$sql .= ' ORDER BY akun_id DESC';

$daftarUser = $types !== ''
    ? sirey_fetchAll(sirey_query($sql, $types, ...$params))
    : sirey_fetchAll(sirey_query($sql));

// Reset password log
$resetLogs = sirey_fetchAll(sirey_query(
    'SELECT r.log_id,r.alasan,r.status,r.waktu,
            target.nama_lengkap AS target_nama,
            actor.nama_lengkap AS actor_nama
     FROM reset_password_log_rayhanRP r
     INNER JOIN akun_rayhanRP target ON r.target_akun_id=target.akun_id
     INNER JOIN akun_rayhanRP actor  ON r.direset_oleh=actor.akun_id
     ORDER BY r.waktu DESC LIMIT 30'
));
?>

<!-- Page Header -->
<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2">
  <div>
    <h2><i class="bi bi-people-fill text-primary me-2"></i>Manajemen Pengguna</h2>
    <p><?php echo $bisa_tulis ? 'Kelola akun, grup utama, dan reset password.' : 'Mode baca saja.'; ?></p>
  </div>
  <?php if ($bisa_tulis): ?>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambah">
      <i class="bi bi-plus-lg me-1"></i>Tambah Pengguna
    </button>
  <?php endif; ?>
</div>

<!-- Flash Messages -->
<?php if ($pesan !== ''): ?>
  <div class="alert alert-success alert-dismissible fade show alert-auto-dismiss mb-3">
    <i class="bi bi-check-circle-fill me-2"></i><?php echo $pesan; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>
<?php if ($error !== ''): ?>
  <div class="alert alert-danger alert-dismissible fade show mb-3">
    <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<!-- Filter Bar -->
<div class="card mb-3">
  <div class="card-body py-3">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-sm-5">
        <label class="form-label">Cari</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-search"></i></span>
          <input type="text" name="search" class="form-control" placeholder="NIS/NIP atau nama..."
                 value="<?php echo htmlspecialchars($cari); ?>">
        </div>
      </div>
      <div class="col-sm-3">
        <label class="form-label">Role</label>
        <select name="filter_role" class="form-select">
          <option value="">Semua Role</option>
          <?php foreach (['siswa','guru','admin','kurikulum','kepala_sekolah'] as $r): ?>
            <option value="<?php echo $r; ?>" <?php echo $filterRole === $r ? 'selected' : ''; ?>><?php echo ucfirst(str_replace('_',' ',$r)); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Filter</button>
        <a href="users.php" class="btn btn-outline-secondary">Reset</a>
      </div>
    </form>
  </div>
</div>

<!-- Tabel Pengguna -->
<div class="card mb-4">
  <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
    <h5><i class="bi bi-table me-2"></i>Daftar Pengguna <span class="badge bg-primary ms-1"><?php echo count($daftarUser); ?></span></h5>
    <div class="d-flex gap-2 flex-wrap">
      <?php if ($bisa_tulis): ?>
        <button id="btnBulkDelete" class="btn btn-sm btn-danger d-none" onclick="submitBulkDelete()">
          <i class="bi bi-trash me-1"></i>Hapus Terpilih (<span id="selectedCount">0</span>)
        </button>
        <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#modalImport">
          <i class="bi bi-file-earmark-excel me-1"></i>Import Excel
        </button>
        <a href="download_template.php" class="btn btn-sm btn-outline-secondary">
          <i class="bi bi-download me-1"></i>Template XLSX
        </a>
      <?php endif; ?>
    </div>
  </div>
  <div class="card-body p-0">
    <?php if (empty($daftarUser)): ?>
      <div class="empty-state"><i class="bi bi-people"></i><p>Tidak ada pengguna yang cocok.</p></div>
    <?php else: ?>
      <div class="table-responsive">
        <table id="tblUser" class="table table-hover mb-0">
          <thead>
            <tr>
              <?php if ($bisa_tulis): ?><th style="width:40px;"><input type="checkbox" id="checkAll" class="form-check-input"></th><?php endif; ?>
              <th>NIS/NIP</th><th>Nama</th><th>JK</th><th>Role</th><th>Dibuat</th>
              <?php if ($bisa_tulis): ?><th class="text-center">Aksi</th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($daftarUser as $u): ?>
              <tr>
                <?php if ($bisa_tulis): ?>
                  <td>
                    <?php if ((int)$u['akun_id'] !== (int)$data_admin_rayhanrp['id']): ?>
                      <input type="checkbox" class="form-check-input userCheck" value="<?php echo (int)$u['akun_id']; ?>" onchange="updateBulk()">
                    <?php endif; ?>
                  </td>
                <?php endif; ?>
                <td><code style="font-size:12px;"><?php echo htmlspecialchars($u['nis_nip']); ?></code></td>
                <td>
                  <div class="fw-600"><?php echo htmlspecialchars($u['nama_lengkap']); ?></div>
                  <?php if (!(int)$u['aktif']): ?><span class="badge bg-secondary" style="font-size:10px;">Nonaktif</span><?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars($u['jenis_kelamin'] ?? '-'); ?></td>
                <td><span class="badge <?php echo roleBadgeClass($u['role']); ?>"><?php echo roleLabel($u['role']); ?></span></td>
                <td class="text-muted" style="font-size:12px;"><?php echo formatDatetime($u['dibuat_pada'],'d/m/Y'); ?></td>
                <?php if ($bisa_tulis): ?>
                  <td class="text-center">
                    <div class="d-flex gap-1 justify-content-center">
                      <button class="btn btn-sm btn-outline-primary"
                              onclick='openEdit(<?php echo json_encode($u, JSON_HEX_TAG|JSON_HEX_QUOT|JSON_HEX_AMP); ?>)'
                              title="Edit"><i class="bi bi-pencil"></i></button>
                      <?php if ((int)$u['akun_id'] !== (int)$data_admin_rayhanrp['id']): ?>
                        <button class="btn btn-sm btn-outline-warning"
                                onclick='openReset(<?php echo json_encode($u, JSON_HEX_TAG|JSON_HEX_QUOT|JSON_HEX_AMP); ?>)'
                                title="Reset Password"><i class="bi bi-key"></i></button>
                        <form method="POST" class="m-0" data-confirm="Hapus pengguna '<?php echo htmlspecialchars($u['nama_lengkap']); ?>'?">
                          <input type="hidden" name="act" value="delete">
                          <input type="hidden" name="id" value="<?php echo (int)$u['akun_id']; ?>">
                          <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button>
                        </form>
                      <?php endif; ?>
                    </div>
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

<!-- ── MODAL TAMBAH ── -->
<div class="modal fade" id="modalTambah" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="act" value="create">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Tambah Pengguna</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-sm-6">
              <label class="form-label">NIS / NIP <span class="text-danger">*</span></label>
              <input type="text" name="nis_nip" class="form-control" placeholder="Contoh: 10243001" required>
              <div class="form-text text-muted">Password default = NIS/NIP</div>
            </div>
            <div class="col-sm-6">
              <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
              <input type="text" name="nama_lengkap" class="form-control" placeholder="Nama lengkap" required>
            </div>
            <div class="col-sm-4">
              <label class="form-label">Role <span class="text-danger">*</span></label>
              <select name="role" class="form-select">
                <?php foreach ($role_izin as $r): ?>
                  <option value="<?php echo $r; ?>"><?php echo ucfirst(str_replace('_',' ',$r)); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-sm-4">
              <label class="form-label">Jenis Kelamin</label>
              <select name="jenis_kelamin" class="form-select">
                <option value="">— Pilih —</option>
                <option value="L">Laki-laki (L)</option>
                <option value="P">Perempuan (P)</option>
              </select>
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

<!-- ── MODAL EDIT ── -->
<div class="modal fade" id="modalEdit" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="act" value="update">
        <input type="hidden" name="id" id="edit_id">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Pengguna</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-sm-6">
              <label class="form-label">NIS / NIP</label>
              <input type="text" id="edit_nis" class="form-control bg-light" disabled>
            </div>
            <div class="col-sm-6">
              <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
              <input type="text" name="nama_lengkap" id="edit_nama" class="form-control" required>
            </div>
            <div class="col-sm-4">
              <label class="form-label">Role <span class="text-danger">*</span></label>
              <select name="role" id="edit_role" class="form-select">
                <?php foreach ($role_izin as $r): ?>
                  <option value="<?php echo $r; ?>"><?php echo ucfirst(str_replace('_',' ',$r)); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-sm-4">
              <label class="form-label">Jenis Kelamin</label>
              <select name="jenis_kelamin" id="edit_jk" class="form-select">
                <option value="">— Pilih —</option>
                <option value="L">Laki-laki (L)</option>
                <option value="P">Perempuan (P)</option>
              </select>
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

<!-- ── MODAL RESET PASSWORD ── -->
<div class="modal fade" id="modalReset" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="act" value="reset_password">
        <input type="hidden" name="target_id" id="reset_target_id">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-key me-2 text-warning"></i>Reset Password</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-warning py-2">
            <i class="bi bi-exclamation-triangle me-2"></i>
            Reset password untuk: <strong id="reset_target_name"></strong>
          </div>
          <div class="mb-3">
            <label class="form-label">Password Baru <span class="text-danger">*</span></label>
            <div class="input-group">
              <input type="text" name="new_password" class="form-control" minlength="6"
                     placeholder="Min. 6 karakter" required>
              <button type="button" class="btn btn-outline-secondary"
                      onclick="this.previousElementSibling.value=Math.random().toString(36).slice(-8)">
                <i class="bi bi-shuffle"></i>
              </button>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Alasan Reset</label>
            <textarea name="alasan" class="form-control" rows="2" placeholder="Opsional…"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-warning text-dark"><i class="bi bi-key me-1"></i>Reset Password</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ── MODAL IMPORT EXCEL ── -->
<div class="modal fade" id="modalImport" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="act" value="import_excel">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-file-earmark-excel me-2 text-success"></i>Import dari Excel</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-info py-2 mb-3">
            <i class="bi bi-info-circle me-2"></i>
            Format: <strong>NO | NAMA | NIS | L/P</strong>
            <a href="download_template.php" class="alert-link">Download template XLSX</a>
          </div>
          <div class="mb-3">
            <label class="form-label">File Excel (.xlsx) <span class="text-danger">*</span></label>
            <input type="file" name="excel_file" class="form-control" accept=".xlsx" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-success"><i class="bi bi-upload me-1"></i>Import</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php endif; ?>

<!-- Hidden form bulk delete -->
<form id="formBulkDelete" method="POST" style="display:none;">
  <input type="hidden" name="act" value="delete_multiple">
  <div id="bulkIdsContainer"></div>
</form>

<script>
// ── DataTable ──
$(document).ready(function () {
  if ($('#tblUser').length) {
    $('#tblUser').DataTable({ columnDefs: [{ orderable: false, targets: [0, <?php echo $bisa_tulis ? '-1' : '99'; ?>] }] });
  }
  if ($('#tblReset').length) {
    $('#tblReset').DataTable({ order: [[0,'desc']], pageLength: 10 });
  }
});

// ── Edit modal ──
function openEdit(u) {
  document.getElementById('edit_id').value   = u.akun_id;
  document.getElementById('edit_nis').value  = u.nis_nip;
  document.getElementById('edit_nama').value = u.nama_lengkap;
  document.getElementById('edit_role').value = u.role;
  document.getElementById('edit_jk').value   = u.jenis_kelamin ?? '';
  new bootstrap.Modal(document.getElementById('modalEdit')).show();
}

// ── Reset modal ──
function openReset(u) {
  document.getElementById('reset_target_id').value  = u.akun_id;
  document.getElementById('reset_target_name').textContent = u.nama_lengkap;
  new bootstrap.Modal(document.getElementById('modalReset')).show();
}

// ── Bulk select ──
<?php if ($bisa_tulis): ?>
const checkAll = document.getElementById('checkAll');
if (checkAll) {
  checkAll.addEventListener('change', function () {
    document.querySelectorAll('.userCheck').forEach(cb => cb.checked = this.checked);
    updateBulk();
  });
}

function updateBulk() {
  const n = document.querySelectorAll('.userCheck:checked').length;
  const btn = document.getElementById('btnBulkDelete');
  document.getElementById('selectedCount').textContent = n;
  if (btn) btn.classList.toggle('d-none', n === 0);
}

function submitBulkDelete() {
  const ids = [...document.querySelectorAll('.userCheck:checked')].map(cb => cb.value);
  if (!ids.length) return;
  Swal.fire({
    title: 'Hapus ' + ids.length + ' pengguna?',
    text: 'Tindakan ini tidak dapat dibatalkan.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Ya, Hapus',
    cancelButtonText: 'Batal',
  }).then(r => {
    if (!r.isConfirmed) return;
    const container = document.getElementById('bulkIdsContainer');
    container.innerHTML = ids.map(id => `<input type="hidden" name="selected_ids[]" value="${id}">`).join('');
    document.getElementById('formBulkDelete').submit();
  });
}
<?php endif; ?>
</script>

<?php layoutEnd(); ?>