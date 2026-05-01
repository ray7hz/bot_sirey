<?php
declare(strict_types=1);

$judul_halaman_rayhanrp = 'Manajemen Pengguna';
$menu_aktif_rayhanrp = 'users';

require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../includes/import_functions.php';

if (!can('view_users', $data_admin_rayhanrp)) {
    header('Location: dashboard.php?err=akses');
    exit;
}

$bisa_tulis_rayhanrp = can('create_user', $data_admin_rayhanrp);
$bisa_lihat_pengguna_rayhanrp = can('view_users', $data_admin_rayhanrp);
$database_rayhanrp = sirey_getDatabase();
$pesan_rayhanrp = '';
$error_rayhanrp = '';
$role_diizinkan_rayhanrp = ['admin', 'guru', 'siswa', 'kepala_sekolah', 'kurikulum'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi_rayhanrp = (string)($_POST['act'] ?? '');

    if (!$bisa_tulis_rayhanrp) {
        $error_rayhanrp = 'Halaman ini dalam mode baca saja.';
    } else {
        requireNotReadonly($data_admin_rayhanrp, 'users.php');

        if ($aksi_rayhanrp === 'create') {
            $nis_nip_rayhanrp = preg_replace('/\s+/', '', trim((string)($_POST['nis_nip'] ?? '')));
            $nama_rayhanrp = trim((string)($_POST['nama_lengkap'] ?? ''));
            $role_rayhanrp = (string)($_POST['role'] ?? 'siswa');
            $jenis_kelamin_rayhanrp = trim((string)($_POST['jenis_kelamin'] ?? ''));

            if ($nis_nip_rayhanrp === '' || $nama_rayhanrp === '') {
                $error_rayhanrp = 'NIS/NIP dan nama wajib diisi.';
            } elseif (!in_array($role_rayhanrp, $role_diizinkan_rayhanrp, true)) {
                $error_rayhanrp = 'Role tidak valid.';
            } elseif (fetchAccountByNis($nis_nip_rayhanrp) !== null) {
                $error_rayhanrp = 'NIS/NIP sudah terdaftar. Gunakan akun yang ada atau ubah data pengguna tersebut.';
            } else {
                $hasil_rayhanrp = sirey_execute(
                    'INSERT INTO akun_rayhanRP (nis_nip, password, role, nama_lengkap, jenis_kelamin)
                     VALUES (?, ?, ?, ?, ?)',
                    'sssss',
                    $nis_nip_rayhanrp,
                    hashPassword($nis_nip_rayhanrp),
                    $role_rayhanrp,
                    $nama_rayhanrp,
                    $jenis_kelamin_rayhanrp !== '' ? $jenis_kelamin_rayhanrp : null
                );

                if ($hasil_rayhanrp >= 1) {
                    $id_baru_rayhanrp = sirey_lastInsertId();

                    auditLog($data_admin_rayhanrp['id'], 'create_user', 'akun', $id_baru_rayhanrp, [
                        'nis_nip' => $nis_nip_rayhanrp,
                        'role' => $role_rayhanrp,
                    ]);
                    $pesan_rayhanrp = 'Pengguna berhasil ditambahkan. Password default: ' . $nis_nip_rayhanrp;
                } else {
                    $error_rayhanrp = sirey_lastDbErrno() === 1062
                        ? 'NIS/NIP sudah terdaftar. Gunakan akun yang ada atau ubah data pengguna tersebut.'
                        : 'Gagal menambah pengguna. Periksa kembali data yang diisi.';
                }
            }
        } elseif ($aksi_rayhanrp === 'update') {
            $id_akun_rayhanrp = (int)($_POST['id'] ?? 0);
            $nama_rayhanrp = trim((string)($_POST['nama_lengkap'] ?? ''));
            $role_rayhanrp = (string)($_POST['role'] ?? 'siswa');
            $jenis_kelamin_rayhanrp = trim((string)($_POST['jenis_kelamin'] ?? ''));

            if ($id_akun_rayhanrp <= 0 || $nama_rayhanrp === '') {
                $error_rayhanrp = 'Data pengguna tidak lengkap.';
            } elseif (!in_array($role_rayhanrp, $role_diizinkan_rayhanrp, true)) {
                $error_rayhanrp = 'Role tidak valid.';
            } else {
                sirey_execute(
                    'UPDATE akun_rayhanRP
                     SET nama_lengkap = ?, role = ?, jenis_kelamin = ?
                     WHERE akun_id = ?',
                    'sssi',
                    $nama_rayhanrp,
                    $role_rayhanrp,
                    $jenis_kelamin_rayhanrp !== '' ? $jenis_kelamin_rayhanrp : null,
                    $id_akun_rayhanrp
                );

                auditLog($data_admin_rayhanrp['id'], 'update_user', 'akun', $id_akun_rayhanrp, [
                    'role' => $role_rayhanrp,
                ]);
                $pesan_rayhanrp = 'Pengguna berhasil diperbarui.';
            }
        } elseif ($aksi_rayhanrp === 'delete') {
            $id_akun_rayhanrp = (int)($_POST['id'] ?? 0);

            if ($id_akun_rayhanrp <= 0) {
                $error_rayhanrp = 'ID pengguna tidak valid.';
            } elseif ($id_akun_rayhanrp === (int)$data_admin_rayhanrp['id']) {
                $error_rayhanrp = 'Anda tidak dapat menghapus akun sendiri.';
            } else {
                sirey_execute('DELETE FROM grup_anggota_rayhanRP WHERE akun_id = ?', 'i', $id_akun_rayhanrp);
                sirey_execute('DELETE FROM akun_rayhanRP WHERE akun_id = ?', 'i', $id_akun_rayhanrp);
                auditLog($data_admin_rayhanrp['id'], 'delete_user', 'akun', $id_akun_rayhanrp);
                $pesan_rayhanrp = 'Pengguna berhasil dihapus.';
            }
        } elseif ($aksi_rayhanrp === 'delete_multiple') {
            $id_terpilih_rayhanrp = array_filter(array_map('intval', (array)($_POST['selected_ids'] ?? [])));
            $jumlah_dihapus_rayhanrp = 0;

            foreach ($id_terpilih_rayhanrp as $id_akun_rayhanrp) {
                if ($id_akun_rayhanrp === (int)$data_admin_rayhanrp['id']) {
                    continue;
                }
                sirey_execute('DELETE FROM grup_anggota_rayhanRP WHERE akun_id = ?', 'i', $id_akun_rayhanrp);
                if (sirey_execute('DELETE FROM akun_rayhanRP WHERE akun_id = ?', 'i', $id_akun_rayhanrp) >= 1) {
                    $jumlah_dihapus_rayhanrp++;
                }
            }

            auditLog($data_admin_rayhanrp['id'], 'bulk_delete_user', 'akun', null, ['ids' => $id_terpilih_rayhanrp]);
            $pesan_rayhanrp = $jumlah_dihapus_rayhanrp > 0 ? $jumlah_dihapus_rayhanrp . ' pengguna berhasil dihapus.' : 'Tidak ada pengguna yang dihapus.';
        } elseif ($aksi_rayhanrp === 'reset_password') {
            $id_target_rayhanrp = (int)($_POST['target_id'] ?? 0);
            $password_baru_rayhanrp = trim((string)($_POST['new_password'] ?? ''));
            $alasan_rayhanrp = trim((string)($_POST['alasan'] ?? ''));

            if (!can('reset_password', $data_admin_rayhanrp)) {
                $error_rayhanrp = 'Anda tidak memiliki izin untuk mereset password.';
            } elseif ($id_target_rayhanrp === (int)$data_admin_rayhanrp['id']) {
                $error_rayhanrp = 'Gunakan halaman profil jika ingin mengganti password sendiri.';
            } elseif ($id_target_rayhanrp <= 0 || strlen($password_baru_rayhanrp) < 6) {
                $error_rayhanrp = 'Target harus valid dan password baru minimal 6 karakter.';
            } else {
                $data_target_rayhanrp = sirey_fetch(sirey_query(
                    'SELECT akun_id, nama_lengkap, role FROM akun_rayhanRP WHERE akun_id = ?',
                    'i',
                    $id_target_rayhanrp
                ));

                if (!$data_target_rayhanrp) {
                    $error_rayhanrp = 'Akun target tidak ditemukan.';
                } elseif ($data_target_rayhanrp['role'] === 'admin') {
                    $error_rayhanrp = 'Password admin lain tidak dapat direset melalui form ini.';
                    auditLog($data_admin_rayhanrp['id'], 'reset_password', 'akun', $id_target_rayhanrp, ['alasan' => 'target admin'], 'ditolak');
                } else {
                    $password_hash_rayhanrp = hashPassword($password_baru_rayhanrp);
                    $hasil_update_rayhanrp = sirey_execute('UPDATE akun_rayhanRP SET password = ? WHERE akun_id = ?', 'si', $password_hash_rayhanrp, $id_target_rayhanrp);

                    sirey_execute(
                        'INSERT INTO reset_password_log_rayhanRP (target_akun_id, direset_oleh, alasan, status, sumber, ip_address)
                         VALUES (?, ?, ?, ?, "web", ?)',
                        'iisss',
                        $id_target_rayhanrp,
                        $data_admin_rayhanrp['id'],
                        $alasan_rayhanrp !== '' ? $alasan_rayhanrp : null,
                        $hasil_update_rayhanrp >= 1 ? 'berhasil' : 'gagal',
                        (string)($_SERVER['REMOTE_ADDR'] ?? null)
                    );

                    if ($hasil_update_rayhanrp >= 1) {
                        auditLog($data_admin_rayhanrp['id'], 'reset_password', 'akun', $id_target_rayhanrp, ['alasan' => $alasan_rayhanrp !== '' ? $alasan_rayhanrp : null]);
                        $pesan_rayhanrp = 'Password untuk ' . $data_target_rayhanrp['nama_lengkap'] . ' berhasil direset.';
                    } else {
                        auditLog($data_admin_rayhanrp['id'], 'reset_password', 'akun', $id_target_rayhanrp, ['alasan' => $alasan_rayhanrp !== '' ? $alasan_rayhanrp : null], 'gagal');
                        $error_rayhanrp = 'Gagal mereset password.';
                    }
                }
            }
        } elseif ($aksi_rayhanrp === 'import_excel' && isset($_FILES['excel_file'])) {
            $file_rayhanrp = $_FILES['excel_file'];

            if (($file_rayhanrp['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $error_rayhanrp = 'Upload file gagal.';
            } else {
                $hasil_import_rayhanrp = importUsersFromExcel($database_rayhanrp, (string)$file_rayhanrp['tmp_name'], null);
                
                // Cek jika ada parsing error (file invalid, dll)
                if (isset($hasil_import_rayhanrp['error'])) {
                    $error_rayhanrp = (string)$hasil_import_rayhanrp['error'];
                } else {
                    // Tampilkan hasil import (berhasil + gagal/dilewati)
                    $jumlah_berhasil_rayhanrp = (int)($hasil_import_rayhanrp['imported'] ?? 0);
                    $jumlah_gagal_rayhanrp = (int)($hasil_import_rayhanrp['failed'] ?? 0);
                    $daftar_error_rayhanrp = $hasil_import_rayhanrp['errors'] ?? [];
                    
                    if ($jumlah_berhasil_rayhanrp > 0) {
                        $pesan_rayhanrp = '✅ Import selesai: ' . $jumlah_berhasil_rayhanrp . ' pengguna berhasil ditambahkan';
                        if ($jumlah_gagal_rayhanrp > 0) {
                            $pesan_rayhanrp .= ', ' . $jumlah_gagal_rayhanrp . ' dilewati (sudah ada atau error).';
                        } else {
                            $pesan_rayhanrp .= '.';
                        }
                    } else {
                        if ($jumlah_gagal_rayhanrp > 0) {
                            $pesan_rayhanrp = '⚠️ Semua data dilewati karena sudah ada atau error (' . $jumlah_gagal_rayhanrp . ' baris).';
                        } else {
                            $error_rayhanrp = 'File Excel tidak memiliki data yang valid.';
                        }
                    }
                    
                    // Log audit
                    auditLog($data_admin_rayhanrp['id'], 'import_user_excel', 'akun', null, [
                        'imported' => $jumlah_berhasil_rayhanrp,
                        'failed' => $jumlah_gagal_rayhanrp,
                    ]);
                }
            }
        }
    }
}

$teks_pencarian_rayhanrp = trim((string)($_GET['search'] ?? ''));
$filter_role_rayhanrp = trim((string)($_GET['filter_role'] ?? ''));

$pernyataan_sql_rayhanrp = 'SELECT a.akun_id, a.nis_nip, a.role, a.nama_lengkap,
               a.jenis_kelamin, a.dibuat_pada
        FROM akun_rayhanRP a
        WHERE 1=1';
$tipe_rayhanrp = '';
$parameter_rayhanrp = [];

if ($teks_pencarian_rayhanrp !== '') {
    $pernyataan_sql_rayhanrp .= ' AND (a.nis_nip LIKE ? OR a.nama_lengkap LIKE ?)';
    $tipe_rayhanrp .= 'ss';
    $parameter_rayhanrp[] = '%' . $teks_pencarian_rayhanrp . '%';
    $parameter_rayhanrp[] = '%' . $teks_pencarian_rayhanrp . '%';
}

if ($filter_role_rayhanrp !== '') {
    $pernyataan_sql_rayhanrp .= ' AND a.role = ?';
    $tipe_rayhanrp .= 's';
    $parameter_rayhanrp[] = $filter_role_rayhanrp;
}

$pernyataan_sql_rayhanrp .= ' ORDER BY a.akun_id DESC';

$daftar_pengguna_rayhanrp = $tipe_rayhanrp !== ''
    ? sirey_fetchAll(sirey_query($pernyataan_sql_rayhanrp, $tipe_rayhanrp, ...$parameter_rayhanrp))
    : sirey_fetchAll(sirey_query($pernyataan_sql_rayhanrp));

$daftar_log_reset_rayhanrp = sirey_fetchAll(sirey_query(
    'SELECT r.log_id, r.alasan, r.status, r.waktu,
            target.nama_lengkap AS target_nama,
            actor.nama_lengkap AS actor_nama
     FROM reset_password_log_rayhanRP r
     INNER JOIN akun_rayhanRP target ON r.target_akun_id = target.akun_id
     INNER JOIN akun_rayhanRP actor ON r.direset_oleh = actor.akun_id
     ORDER BY r.waktu DESC
     LIMIT 50'
));
?>

<div class="page-header">
  <h2>Manajemen Pengguna</h2>
  <p><?php echo $bisa_tulis_rayhanrp ? 'Kelola akun, grup utama, dan reset password.' : 'Mode lihat untuk kurikulum.'; ?></p>
</div>

<?php if ($pesan_rayhanrp !== ''): ?>
  <div class="alert alert-success"><?php echo htmlspecialchars($pesan_rayhanrp); ?></div>
<?php endif; ?>
<?php if ($error_rayhanrp !== ''): ?>
  <div class="alert alert-error"><?php echo htmlspecialchars($error_rayhanrp); ?></div>
<?php endif; ?>

<?php if ($bisa_tulis_rayhanrp): ?>
  <div class="card" style="margin-bottom:24px;">
    <div style="display:flex; border-bottom:2px solid #e2e8f0; gap:2px;">
      <button type="button" class="subnav-tab active" data-tab="tab-tambah" onclick="switchTab('tab-tambah')" style="flex:1; padding:14px 20px; border:none; background:none; cursor:pointer; font-weight:600; color:#0f172a; border-bottom:3px solid #3b82f6;">
        ➕ Tambah Manual
      </button>
      <button type="button" class="subnav-tab" data-tab="tab-import" onclick="switchTab('tab-import')" style="flex:1; padding:14px 20px; border:none; background:none; cursor:pointer; font-weight:600; color:#64748b; border-bottom:3px solid transparent;">
        📥 Import Excel
      </button>
    </div>

    <div id="tab-tambah" class="subnav-content" style="display:block; padding:20px;">
      <form method="POST" style="display:grid; grid-template-columns:1fr 1.5fr 1fr 1fr auto; gap:14px; align-items:flex-end;">
        <input type="hidden" name="act" value="create">
        <div class="form-group" style="margin:0;">
          <label class="form-label">NIS/NIP</label>
          <input type="text" name="nis_nip" class="form-control" required>
        </div>
        <div class="form-group" style="margin:0;">
          <label class="form-label">Nama Lengkap</label>
          <input type="text" name="nama_lengkap" class="form-control" required>
        </div>
        <div class="form-group" style="margin:0;">
          <label class="form-label">Role</label>
          <select name="role" class="form-control">
            <?php foreach ($role_diizinkan_rayhanrp as $role_option_rayhanrp): ?>
              <option value="<?php echo $role_option_rayhanrp; ?>"><?php echo htmlspecialchars($role_option_rayhanrp); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="margin:0;">
          <label class="form-label">JK</label>
          <select name="jenis_kelamin" class="form-control">
            <option value="">-</option>
            <option value="L">L</option>
            <option value="P">P</option>
          </select>
        </div>
        <button type="submit" class="btn btn-primary">Tambah</button>
      </form>
    </div>

    <div id="tab-import" class="subnav-content" style="display:none; padding:20px;">
      <form method="POST" enctype="multipart/form-data" style="display:grid; grid-template-columns:1.4fr auto; gap:14px; align-items:flex-end;">
        <input type="hidden" name="act" value="import_excel">
        <div class="form-group" style="margin:0;">
          <label class="form-label">File XLSX (Format: NO | NAMA | NIS | L/P | KELAS)</label>
          <input type="file" name="excel_file" class="form-control" accept=".xlsx" required>
        </div>
        <button type="submit" class="btn btn-success">Import</button>
      </form>
    </div>
  </div>

  <script>
    function switchTab(tabName) {
      // Hide all tabs
      document.querySelectorAll('.subnav-content').forEach(el => el.style.display = 'none');
      
      // Remove active state from all tabs
      document.querySelectorAll('.subnav-tab').forEach(el => {
        el.style.color = '#64748b';
        el.style.borderBottomColor = 'transparent';
      });
      
      // Show selected tab
      document.getElementById(tabName).style.display = 'block';
      
      // Add active state to clicked tab
      event.target.style.color = '#0f172a';
      event.target.style.borderBottomColor = '#3b82f6';
    }
  </script>

<?php endif; ?>

<div class="card" style="margin-bottom:24px;">
  <div class="card-header"><h3>Filter Pengguna</h3></div>
  <form method="GET" style="display:grid; grid-template-columns:1.5fr 1fr auto auto; gap:12px; align-items:flex-end;">
    <div class="form-group" style="margin:0;">
      <label class="form-label">Cari</label>
      <input type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($teks_pencarian_rayhanrp); ?>" placeholder="NIS/NIP atau nama">
    </div>
      <div class="form-group" style="margin:0;">
        <label class="form-label">Role</label>
        <select name="filter_role" class="form-control">
          <option value="">Semua Role</option>
        <?php foreach (['siswa', 'guru', 'admin', 'kurikulum', 'kepala_sekolah'] as $role_item_rayhanrp): ?>
          <option value="<?php echo $role_item_rayhanrp; ?>" <?php echo $filter_role_rayhanrp === $role_item_rayhanrp ? 'selected' : ''; ?>><?php echo htmlspecialchars($role_item_rayhanrp); ?></option>
        <?php endforeach; ?>
        </select>
      </div>
    <button type="submit" class="btn btn-primary">Filter</button>
    <a href="users.php" class="btn btn-secondary">Reset</a>
  </form>
</div>

<div class="card" style="margin-bottom:24px;">
  <div class="card-header">
    <h3>Daftar Pengguna (<?php echo count($daftar_pengguna_rayhanrp); ?>)</h3>
    <?php if ($bisa_tulis_rayhanrp): ?>
      <button type="button" id="bulkDeleteBtn" class="btn btn-danger btn-sm" style="display:none;" onclick="submitBulkDelete()">Hapus Terpilih</button>
    <?php endif; ?>
  </div>

  <?php if (empty($daftar_pengguna_rayhanrp)): ?>
    <div class="empty-state">
      <div class="empty-icon">User</div>
      <p>Tidak ada pengguna yang cocok.</p>
    </div>
  <?php else: ?>
    <table class="data-table">
      <thead>
        <tr>
          <?php if ($bisa_tulis_rayhanrp): ?><th style="width:40px;"><input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)"></th><?php endif; ?>
          <th>NIS/NIP</th>
          <th>Nama</th>
          <th>JK</th>
          <th>Role</th>
          <th>Dibuat</th>
          <?php if ($bisa_tulis_rayhanrp): ?><th>Aksi</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($daftar_pengguna_rayhanrp as $item_pengguna_rayhanrp): ?>
          <tr>
            <?php if ($bisa_tulis_rayhanrp): ?>
              <td>
                <?php if ((int)$item_pengguna_rayhanrp['akun_id'] !== (int)$data_admin_rayhanrp['id']): ?>
                  <input type="checkbox" class="userCheckbox" value="<?php echo (int)$item_pengguna_rayhanrp['akun_id']; ?>" onchange="updateBulkState()">
                <?php endif; ?>
              </td>
            <?php endif; ?>
            <td><strong><?php echo htmlspecialchars((string)$item_pengguna_rayhanrp['nis_nip']); ?></strong></td>
            <td><?php echo htmlspecialchars((string)$item_pengguna_rayhanrp['nama_lengkap']); ?></td>
            <td><?php echo htmlspecialchars((string)($item_pengguna_rayhanrp['jenis_kelamin'] ?? '-')); ?></td>
            <td><span class="badge <?php echo roleBadgeClass((string)$item_pengguna_rayhanrp['role']); ?>"><?php echo htmlspecialchars((string)$item_pengguna_rayhanrp['role']); ?></span></td>
            <td><?php echo formatDatetime((string)$item_pengguna_rayhanrp['dibuat_pada']); ?></td>
            <?php if ($bisa_tulis_rayhanrp): ?>
              <td style="white-space:nowrap;">
                <button type="button" class="btn btn-info btn-sm" onclick='openEditModal(<?php echo json_encode($item_pengguna_rayhanrp, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'>Edit</button>
                <?php if ((int)$item_pengguna_rayhanrp['akun_id'] !== (int)$data_admin_rayhanrp['id']): ?>
                  <button type="button" class="btn btn-secondary btn-sm" onclick='openResetModal(<?php echo json_encode($item_pengguna_rayhanrp, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'>Reset PW</button>
                  <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus pengguna ini?')">
                    <input type="hidden" name="act" value="delete">
                    <input type="hidden" name="id" value="<?php echo (int)$item_pengguna_rayhanrp['akun_id']; ?>">
                    <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                  </form>
                <?php endif; ?>
              </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

  <div id="editModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1000; align-items:center; justify-content:center;">
    <div style="background:#fff; padding:28px; border-radius:8px; width:min(560px, 92vw);">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
        <h3 style="margin:0;">Edit Pengguna</h3>
        <button type="button" class="btn btn-secondary btn-sm" onclick="closeEditModal()">Tutup</button>
      </div>
      <form method="POST">
        <input type="hidden" name="act" value="update">
        <input type="hidden" name="id" id="edit_id">
        <div class="form-group">
          <label class="form-label">NIS/NIP</label>
          <input type="text" id="edit_nis_nip" class="form-control" disabled>
        </div>
        <div class="form-group">
          <label class="form-label">Nama Lengkap</label>
          <input type="text" name="nama_lengkap" id="edit_nama_lengkap" class="form-control" required>
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
          <div class="form-group">
            <label class="form-label">Role</label>
            <select name="role" id="edit_role" class="form-control">
              <?php foreach ($role_diizinkan_rayhanrp as $role): ?>
                <option value="<?php echo $role; ?>"><?php echo htmlspecialchars($role); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">JK</label>
            <select name="jenis_kelamin" id="edit_jenis_kelamin" class="form-control">
              <option value="">-</option>
              <option value="L">L</option>
              <option value="P">P</option>
            </select>
          </div>
        </div>
        <div style="display:flex; gap:10px;">
          <button type="submit" class="btn btn-primary">Simpan</button>
          <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Batal</button>
        </div>
      </form>
    </div>
  </div>

  <div id="resetModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1000; align-items:center; justify-content:center;">
    <div style="background:#fff; padding:28px; border-radius:8px; width:min(520px, 92vw);">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
        <h3 style="margin:0;">Reset Password</h3>
        <button type="button" class="btn btn-secondary btn-sm" onclick="closeResetModal()">Tutup</button>
      </div>
      <form method="POST">
        <input type="hidden" name="act" value="reset_password">
        <input type="hidden" name="target_id" id="reset_target_id">
        <div class="form-group">
          <label class="form-label">Target</label>
          <input type="text" id="reset_target_name" class="form-control" disabled>
        </div>
        <div class="form-group">
          <label class="form-label">Password Baru</label>
          <input type="text" name="new_password" class="form-control" minlength="6" required>
        </div>
        <div class="form-group">
          <label class="form-label">Alasan</label>
          <textarea name="alasan" class="form-control" rows="3" placeholder="Opsional"></textarea>
        </div>
        <div style="display:flex; gap:10px;">
          <button type="submit" class="btn btn-primary">Konfirmasi Reset</button>
          <button type="button" class="btn btn-secondary" onclick="closeResetModal()">Batal</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    function toggleSelectAll(source) {
      document.querySelectorAll('.userCheckbox').forEach((checkbox) => {
        checkbox.checked = source.checked;
      });
      updateBulkState();
    }

    function updateBulkState() {
      const selected = document.querySelectorAll('.userCheckbox:checked').length;
      const button = document.getElementById('bulkDeleteBtn');
      if (button) {
        button.style.display = selected > 0 ? 'inline-flex' : 'none';
      }
    }

    function submitBulkDelete() {
      const ids = Array.from(document.querySelectorAll('.userCheckbox:checked')).map((el) => el.value);
      if (!ids.length) return;
      if (!confirm('Hapus ' + ids.length + ' pengguna?')) return;

      const form = document.createElement('form');
      form.method = 'POST';
      form.innerHTML = '<input type="hidden" name="act" value="delete_multiple">';
      ids.forEach((id) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'selected_ids[]';
        input.value = id;
        form.appendChild(input);
      });
      document.body.appendChild(form);
      form.submit();
    }

    function openEditModal(user) {
      document.getElementById('edit_id').value = user.akun_id || '';
      document.getElementById('edit_nis_nip').value = user.nis_nip || '';
      document.getElementById('edit_nama_lengkap').value = user.nama_lengkap || '';
      document.getElementById('edit_role').value = user.role || 'siswa';
      document.getElementById('edit_jenis_kelamin').value = user.jenis_kelamin || '';
      document.getElementById('editModal').style.display = 'flex';
    }

    function closeEditModal() {
      document.getElementById('editModal').style.display = 'none';
    }

    function openResetModal(user) {
      document.getElementById('reset_target_id').value = user.akun_id || '';
      document.getElementById('reset_target_name').value = user.nama_lengkap || '';
      document.getElementById('resetModal').style.display = 'flex';
    }

    function closeResetModal() {
      document.getElementById('resetModal').style.display = 'none';
    }

    document.getElementById('editModal')?.addEventListener('click', function (event) {
      if (event.target === this) closeEditModal();
    });

    document.getElementById('resetModal')?.addEventListener('click', function (event) {
      if (event.target === this) closeResetModal();
    });
  </script>

<div class="card">
  <div class="card-header"><h3>Riwayat Reset Password</h3></div>
  <?php if (empty($resetLogs)): ?>
    <div class="empty-state">
      <div class="empty-icon">Reset</div>
      <p>Belum ada riwayat reset password.</p>
    </div>
  <?php else: ?>
    <table class="data-table">
      <thead>
        <tr>
          <th>Waktu</th>
          <th>Target</th>
          <th>Oleh</th>
          <th>Status</th>
          <th>Alasan</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($resetLogs as $log): ?>
          <tr>
            <td><?php echo formatDatetime((string)$log['waktu']); ?></td>
            <td><?php echo htmlspecialchars((string)$log['target_nama']); ?></td>
            <td><?php echo htmlspecialchars((string)$log['actor_nama']); ?></td>
            <td><span class="badge badge-default"><?php echo htmlspecialchars((string)$log['status']); ?></span></td>
            <td><?php echo htmlspecialchars((string)($log['alasan'] ?? '-')); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php layoutEnd(); ?>
