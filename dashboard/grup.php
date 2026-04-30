<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/import_functions.php';

// Start session for AJAX requests
startSession();
ensureSireySchema();

$admin_ajax_rayhanrp = [
    'id' => (int)($_SESSION['admin_id'] ?? 0),
    'role' => (string)($_SESSION['admin_role'] ?? ''),
    'name' => (string)($_SESSION['admin_name'] ?? ''),
];

$bisa_kelola_anggota_ajax_rayhanrp = can('update_grup', $admin_ajax_rayhanrp);
$bisa_buat_jadwal_ajax_rayhanrp = can('create_jadwal', $admin_ajax_rayhanrp);
$bisa_update_jadwal_ajax_rayhanrp = can('update_jadwal', $admin_ajax_rayhanrp);
$bisa_hapus_jadwal_ajax_rayhanrp = can('delete_jadwal', $admin_ajax_rayhanrp);

// ===== HANDLE AJAX REQUESTS =====
$aksi_rayhanrp = (string)($_GET['action'] ?? '');
$id_grup_rayhanrp = (int)($_GET['grup_id'] ?? 0);

if (!empty($aksi_rayhanrp) && $id_grup_rayhanrp > 0) {
    if ($admin_ajax_rayhanrp['id'] <= 0 || !can('view_grup', $admin_ajax_rayhanrp)) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
        exit;
    }

    if (in_array($aksi_rayhanrp, ['get_members', 'get_jadwal', 'get_tugas', 'get_available_users'])) {
        header('Content-Type: text/html; charset=utf-8');
    } else {
        header('Content-Type: application/json; charset=utf-8');
    }
    
    if ($aksi_rayhanrp === 'get_members') {
        $pencarian_rayhanrp = trim((string)($_GET['search'] ?? ''));
        
        $pernyataan_sql_rayhanrp = "
            SELECT 
                a.akun_id,
                a.nis_nip,
                a.nama_lengkap,
                a.role,
                a.jenis_kelamin
            FROM akun_rayhanRP a
            JOIN grup_anggota_rayhanRP ga ON a.akun_id = ga.akun_id
            WHERE ga.grup_id = ?
        ";
        
        $parameter_rayhanrp = [$id_grup_rayhanrp];
        $tipe_rayhanrp = 'i';
        
        if (!empty($pencarian_rayhanrp)) {
            $pernyataan_sql_rayhanrp .= " AND (a.nis_nip LIKE ? OR a.nama_lengkap LIKE ?)";
            $istilah_pencarian_rayhanrp = '%' . $pencarian_rayhanrp . '%';
            $parameter_rayhanrp[] = $istilah_pencarian_rayhanrp;
            $parameter_rayhanrp[] = $istilah_pencarian_rayhanrp;
            $tipe_rayhanrp .= 'ss';
        }
        
        $pernyataan_sql_rayhanrp .= " ORDER BY a.nama_lengkap ASC";
        
        $hasil_rayhanrp = sirey_query($pernyataan_sql_rayhanrp, $tipe_rayhanrp, ...$parameter_rayhanrp);
        if (!$hasil_rayhanrp) {
            echo '<p style="color:red;">Error query database</p>';
            exit;
        }
        
        $daftar_anggota_rayhanrp = sirey_fetchAll($hasil_rayhanrp);
        if (!is_array($daftar_anggota_rayhanrp)) {
            echo '<p style="color:red;">Error fetch members</p>';
            exit;
        }
        
        ?>
<div style="margin-bottom:15px; display:flex; gap:10px; align-items:center;">
    <input type="text" id="searchMembers" placeholder="Cari nama atau NIS..." value="<?php echo htmlspecialchars($pencarian_rayhanrp); ?>" 
           style="flex:1; padding:8px; border:1px solid #ddd; border-radius:4px;" 
           onkeyup="filterMembers(this.value, <?php echo $id_grup_rayhanrp; ?>)">
    <button type="button" onclick="filterMembers('', <?php echo $id_grup_rayhanrp; ?>)" 
            style="background:#6c757d; color:white; border:none; padding:8px 12px; border-radius:4px; cursor:pointer; font-weight:bold;">
        ✕ Clear
    </button>
</div>

<?php
        if (empty($daftar_anggota_rayhanrp)) {
            echo '<p style="text-align:center; color:#999; padding:20px;">Tidak ada anggota yang cocok</p>';
        } else {
            ?>
<?php if ($bisa_kelola_anggota_ajax_rayhanrp): ?>
<div style="margin-bottom:15px; display:none;" id="bulkDeleteMembersSection">
    <div style="background:#ffe6e6; border:1px solid #ffcccc; padding:10px; border-radius:4px; display:flex; justify-content:space-between; align-items:center;">
        <span style="color:#c00;">
            <strong id="memberSelectedCount">0 anggota dipilih</strong>
        </span>
        <button type="button" onclick="bulkDeleteMembers(<?php echo $id_grup_rayhanrp; ?>)" 
                style="background:#dc3545; color:white; border:none; padding:6px 12px; border-radius:4px; cursor:pointer; font-weight:bold;">
            🗑️ Hapus Terpilih
        </button>
    </div>
</div>
<?php endif; ?>

<table style="width:100%; border-collapse:collapse;">
    <thead>
        <tr style="background:#f5f5f5; border-bottom:2px solid #dee2e6;">
            <?php if ($bisa_kelola_anggota_ajax_rayhanrp): ?>
            <th style="padding:12px; text-align:center; font-weight:bold; width:40px;">
                <input type="checkbox" id="selectAllMembers" onchange="toggleSelectAllMembers(this, <?php echo $id_grup_rayhanrp; ?>)">
            </th>
            <?php endif; ?>
            <th style="padding:12px; text-align:left; font-weight:bold;">NIS/NIP</th>
            <th style="padding:12px; text-align:left; font-weight:bold;">Nama Lengkap</th>
            <th style="padding:12px; text-align:left; font-weight:bold;">Role</th>
            <th style="padding:12px; text-align:left; font-weight:bold;">Jenis Kelamin</th>
            <?php if ($bisa_kelola_anggota_ajax_rayhanrp): ?>
            <th style="padding:12px; text-align:center; font-weight:bold;">Aksi</th>
            <?php endif; ?>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($daftar_anggota_rayhanrp as $baris_anggota_rayhanrp): ?>
            <tr style="border-bottom:1px solid #dee2e6;">
                <?php if ($bisa_kelola_anggota_ajax_rayhanrp): ?>
                <td style="padding:12px; text-align:center;">
                    <input type="checkbox" class="memberCheckbox" value="<?php echo (int)$baris_anggota_rayhanrp['akun_id']; ?>" 
                           onchange="updateMemberBulkUI(<?php echo $id_grup_rayhanrp; ?>)">
                </td>
                <?php endif; ?>
                <td style="padding:12px;"><?php echo htmlspecialchars($baris_anggota_rayhanrp['nis_nip'] ?? $baris_anggota_rayhanrp['akun_id']); ?></td>
                <td style="padding:12px;"><?php echo htmlspecialchars($baris_anggota_rayhanrp['nama_lengkap']); ?></td>
                <td style="padding:12px;">
                    <span style="display:inline-block; padding:4px 8px; border-radius:4px; font-size:12px; font-weight:bold; 
                                 background:<?php echo $baris_anggota_rayhanrp['role'] === 'guru' ? '#e3f2fd' : '#f3e5f5'; ?>; 
                                 color:<?php echo $baris_anggota_rayhanrp['role'] === 'guru' ? '#1976d2' : '#7b1fa2'; ?>;">
                        <?php echo htmlspecialchars(ucfirst($baris_anggota_rayhanrp['role'])); ?>
                    </span>
                </td>
                <td style="padding:12px;"><?php echo htmlspecialchars($baris_anggota_rayhanrp['jenis_kelamin'] ?? '-'); ?></td>
                <?php if ($bisa_kelola_anggota_ajax_rayhanrp): ?>
                <td style="padding:12px; text-align:center;">
                    <button type="button" onclick="deleteMemberFromGroup(<?php echo (int)$baris_anggota_rayhanrp['akun_id']; ?>, <?php echo $id_grup_rayhanrp; ?>, <?php echo htmlspecialchars(json_encode($baris_anggota_rayhanrp['nama_lengkap']), ENT_QUOTES, 'UTF-8'); ?>)"
                            style="background:#dc3545; color:white; border:none; padding:4px 10px; border-radius:4px; cursor:pointer; font-size:12px; font-weight:bold;">
                        🗑️ Hapus
                    </button>
                </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
            <?php
        }
        exit;
    } elseif ($aksi_rayhanrp === 'delete_member') {
        if (!can('update_grup', $admin_ajax_rayhanrp)) {
            echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
            exit;
        }
        
        $akunId = (int)($_GET['akun_id'] ?? 0);
        
        if ($akunId <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
            exit;
        }
        
        $userPrimaryGrupId = getPrimaryGroupId($akunId) ?? 0;
        
        removeUserMembership($akunId, $id_grup_rayhanrp);
        if ($userPrimaryGrupId === $id_grup_rayhanrp) {
            syncPrimaryGroup($akunId, null);
        }
        
        echo json_encode(['success' => true, 'message' => 'Anggota berhasil dihapus']);
        exit;
    } elseif ($aksi_rayhanrp === 'toggle_status') {
        if (!can('update_grup', $admin_ajax_rayhanrp)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
            exit;
        }
        
        $statusBaru = (int)($_POST['status'] ?? 0);
        if ($statusBaru !== 0 && $statusBaru !== 1) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Status tidak valid']);
            exit;
        }
        
        $sql = 'UPDATE grup_rayhanRP SET aktif = ? WHERE grup_id = ?';
        sirey_execute($sql, 'ii', $statusBaru, $id_grup_rayhanrp);
        
        auditLog($admin_ajax_rayhanrp['id'], 'toggle_grup', 'grup', $id_grup_rayhanrp, ['aktif' => $statusBaru]);
        
        $statusLabel = $statusBaru === 1 ? 'aktif' : 'non-aktif';
        echo json_encode(['success' => true, 'message' => 'Grup berhasil diubah menjadi ' . $statusLabel, 'status' => $statusBaru]);
        exit;
    } elseif ($aksi_rayhanrp === 'get_available_users') {
        if (!$bisa_kelola_anggota_ajax_rayhanrp) {
            echo '';
            exit;
        }
        
        $sql = "
            SELECT 
                a.akun_id,
                a.nis_nip,
                a.nama_lengkap
            FROM akun_rayhanRP a
            WHERE a.role = 'siswa' AND a.akun_id NOT IN (
                SELECT akun_id FROM grup_anggota_rayhanRP WHERE grup_id = ?
            )
            ORDER BY a.nama_lengkap ASC
        ";
        
        $result = sirey_query($sql, 'i', $id_grup_rayhanrp);
        if (!$result) {
            echo '<p style="color:red;">Error query database</p>';
            exit;
        }
        
        $users = sirey_fetchAll($result);
        if (!is_array($users) || empty($users)) {
            echo '<p style="color:#999; font-style:italic;">Semua siswa sudah menjadi anggota grup</p>';
        } else {
            ?>
<form id="addMembersForm" onsubmit="submitAddMembers(event, <?php echo $id_grup_rayhanrp; ?>)">
    <!-- Checkbox Select All untuk Bulk Insert -->
    <label style="display:block; margin-bottom:10px; font-weight:bold; cursor:pointer;">
        <input type="checkbox" id="selectAllAvailableMembers" onchange="toggleSelectAllAvailableMembers(this)">
        Pilih semua siswa
    </label>
    
    <div style="max-height:260px; overflow:auto; border:1px solid #e5e7eb; border-radius:6px; padding:10px;">
        <?php foreach ($users as $user): ?>
            <label style="display:block; padding:6px 4px; border-bottom:1px solid #f1f5f9; cursor:pointer;" class="siswa-option">
                <input type="checkbox" class="availableMemberCheckbox" name="siswa_id[]" value="<?php echo (int)$user['akun_id']; ?>">
                <span><?php echo htmlspecialchars($user['nis_nip'] . ' - ' . $user['nama_lengkap']); ?></span>
            </label>
        <?php endforeach; ?>
    </div>
    <button type="submit" style="margin-top:12px; background:#28a745; color:white; border:none; padding:8px 15px; border-radius:4px; cursor:pointer; font-weight:bold;">
        Tambah Anggota Terpilih
    </button>
</form>
<form id="importMembersExcelForm" onsubmit="submitImportMembersExcel(event, <?php echo $id_grup_rayhanrp; ?>)" enctype="multipart/form-data" style="margin-top:18px; padding-top:14px; border-top:1px solid #e5e7eb;">
    <label style="display:block; margin-bottom:8px; font-weight:bold;">Import siswa dari Excel</label>
    <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
        <input type="file" name="excel_file" accept=".xlsx,.xls,.csv" required>
        <button type="submit" style="background:#0369a1; color:white; border:none; padding:8px 15px; border-radius:4px; cursor:pointer; font-weight:bold;">
            Import NIS
        </button>
    </div>
    <small style="display:block; margin-top:6px; color:#64748b;">Kolom NIS dicocokkan ke master akun siswa; data master tidak diubah.</small>
</form>
            <?php
        }
        exit;
    } elseif ($aksi_rayhanrp === 'bulk_add_members') {
        if (!can('update_grup', $admin_ajax_rayhanrp)) {
            echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
            exit;
        }

        $ids = array_unique(array_filter(array_map('intval', (array)($_POST['siswa_id'] ?? []))));
        if (empty($ids)) {
            echo json_encode(['success' => false, 'message' => 'Pilih minimal satu siswa.']);
            exit;
        }

        foreach ($ids as $akunId) {
            $currentGrupId = getPrimaryGroupId($akunId) ?? 0;
            if ($currentGrupId <= 0) {
                syncPrimaryGroup($akunId, $id_grup_rayhanrp);
            } else {
                ensureUserMembership($akunId, $id_grup_rayhanrp, 'tambahan');
            }
        }

        echo json_encode(['success' => true, 'message' => count($ids) . ' anggota berhasil ditambahkan.']);
        exit;
    } elseif ($aksi_rayhanrp === 'import_members_excel') {
        if (!can('update_grup', $admin_ajax_rayhanrp)) {
            echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
            exit;
        }

        if (empty($_FILES['excel_file']['tmp_name']) || !is_uploaded_file($_FILES['excel_file']['tmp_name'])) {
            echo json_encode(['success' => false, 'message' => 'File Excel wajib diunggah.']);
            exit;
        }

        $hasil_import_rayhanrp = importSiswaKeKelasDariExcel($_FILES['excel_file']['tmp_name'], $id_grup_rayhanrp);
        echo json_encode([
            'success' => (bool)$hasil_import_rayhanrp['success'],
            'message' => ($hasil_import_rayhanrp['imported'] ?? 0) . ' siswa berhasil diimport, ' . ($hasil_import_rayhanrp['failed'] ?? 0) . ' gagal.',
            'errors' => array_slice((array)($hasil_import_rayhanrp['errors'] ?? []), 0, 10),
        ]);
        exit;
    } elseif ($aksi_rayhanrp === 'bulk_delete_members') {
        if (!can('update_grup', $admin_ajax_rayhanrp)) {
            echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
            exit;
        }
        $ids = (array)($_POST['member_ids'] ?? []);
        $ids = array_filter(array_map('intval', $ids));
        
        if (empty($ids)) {
            echo json_encode(['success' => false, 'message' => 'Tidak ada yang dipilih']);
            exit;
        }
        
        foreach ($ids as $akunId) {
            $userPrimaryGrupId = getPrimaryGroupId($akunId) ?? 0;

            removeUserMembership($akunId, $id_grup_rayhanrp);
            if ($userPrimaryGrupId === $id_grup_rayhanrp) {
                syncPrimaryGroup($akunId, null);
            }
        }
        
        echo json_encode(['success' => true, 'message' => count($ids) . ' anggota berhasil dihapus']);
        exit;
    }
}

// ===== PAGE LOAD =====
$judul_halaman_rayhanrp  = 'Grup / Kelas';
$menu_aktif_rayhanrp = 'grup';
require_once __DIR__ . '/_layout.php';

if (!can('view_grup', $data_admin_rayhanrp)) {
    header('Location: dashboard.php?err=akses');
    exit;
}

$pesan_rayhanrp = '';
$error_rayhanrp = '';
$bisa_buat_grup_rayhanrp = can('create_grup', $data_admin_rayhanrp);
$bisa_update_grup_rayhanrp = can('update_grup', $data_admin_rayhanrp);
$bisa_hapus_grup_rayhanrp = can('delete_grup', $data_admin_rayhanrp);
$bisa_kelola_anggota_rayhanrp = $bisa_update_grup_rayhanrp;
$bisa_tulis_grup_rayhanrp = $bisa_buat_grup_rayhanrp || $bisa_update_grup_rayhanrp || $bisa_hapus_grup_rayhanrp;

$bisa_buat_jadwal_rayhanrp = can('create_jadwal', $data_admin_rayhanrp);
$bisa_update_jadwal_rayhanrp = can('update_jadwal', $data_admin_rayhanrp);
$bisa_hapus_jadwal_rayhanrp = can('delete_jadwal', $data_admin_rayhanrp);

$deskripsi_halaman_rayhanrp = match ($data_admin_rayhanrp['role']) {
    'guru' => 'Hanya kelas yang Anda ajar yang ditampilkan. Halaman ini read-only.',
    'kepala_sekolah' => 'Mode baca saja untuk pemantauan grup, anggota, jadwal, dan tugas.',
    default => 'Kelola grup, anggota, dan kelas.',
};

$id_pembuat_rayhanrp = (int)($_SESSION['admin_id'] ?? 0);
$daftar_guru_wali_rayhanrp = sirey_fetchAll(sirey_query(
    'SELECT akun_id, nama_lengkap
     FROM akun_rayhanRP
     WHERE role = "guru"
     ORDER BY nama_lengkap ASC'
));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi_rayhanrp = (string)($_POST['act'] ?? '');

    if ($aksi_rayhanrp === 'create') {
        $nama_grup_rayhanrp = trim((string)($_POST['nama_grup'] ?? ''));
        $tingkat_rayhanrp = (int)($_POST['tingkat'] ?? 0);
        $jurusan_rayhanrp = (string)($_POST['jurusan'] ?? '');
        $deskripsi_rayhanrp = trim((string)($_POST['deskripsi'] ?? ''));
        $wali_kelas_id_rayhanrp = (int)($_POST['wali_kelas_id'] ?? 0);

        if (!can('create_grup', $data_admin_rayhanrp)) {
            $error_rayhanrp = 'Anda tidak memiliki izin membuat grup.';
        } elseif ($nama_grup_rayhanrp === '') {
            $error_rayhanrp = 'Nama grup tidak boleh kosong.';
        } elseif ($tingkat_rayhanrp < 10 || $tingkat_rayhanrp > 12) {
            $error_rayhanrp = 'Tingkat harus antara 10-12 (Kelas X, XI, XII).';
        } elseif (empty($jurusan_rayhanrp)) {
            $error_rayhanrp = 'Jurusan harus dipilih.';
        } elseif ($id_pembuat_rayhanrp <= 0) {
            $error_rayhanrp = 'User session tidak valid. Silakan login ulang.';
        } else {
            requireNotReadonly($data_admin_rayhanrp, 'grup.php');
            $hasil_rayhanrp = sirey_execute(
                'INSERT INTO grup_rayhanRP (nama_grup, tingkat, jurusan, deskripsi, pembuat_id, wali_kelas_id) VALUES (?, ?, ?, ?, ?, ?)',
                'sissii',
                $nama_grup_rayhanrp,
                $tingkat_rayhanrp,
                $jurusan_rayhanrp,
                $deskripsi_rayhanrp !== '' ? $deskripsi_rayhanrp : null,
                $id_pembuat_rayhanrp,
                $wali_kelas_id_rayhanrp > 0 ? $wali_kelas_id_rayhanrp : null
            );

            if ($hasil_rayhanrp >= 1) {
                $id_baru_rayhanrp = sirey_lastInsertId();
                auditLog($data_admin_rayhanrp['id'], 'create_grup', 'grup', $id_baru_rayhanrp, ['tingkat' => $tingkat_rayhanrp, 'jurusan' => $jurusan_rayhanrp]);
                $pesan_rayhanrp = "Grup '{$nama_grup_rayhanrp}' berhasil dibuat.";
            } else {
                $error_rayhanrp = 'Gagal membuat grup. Nama mungkin sudah dipakai.';
            }
        }
    } elseif ($aksi_rayhanrp === 'update') {
        $id_grup_rayhanrp = (int)($_POST['id'] ?? 0);
        $nama_grup_rayhanrp = trim((string)($_POST['nama_grup'] ?? ''));
        $tingkat_rayhanrp = (int)($_POST['tingkat'] ?? 0);
        $jurusan_rayhanrp = (string)($_POST['jurusan'] ?? '');
        $deskripsi_rayhanrp = trim((string)($_POST['deskripsi'] ?? ''));
        $wali_kelas_id_rayhanrp = (int)($_POST['wali_kelas_id'] ?? 0);

        if (!can('update_grup', $data_admin_rayhanrp)) {
            $error_rayhanrp = 'Anda tidak memiliki izin mengubah grup.';
        } elseif ($id_grup_rayhanrp <= 0) {
            $error_rayhanrp = 'ID grup tidak valid.';
        } elseif ($nama_grup_rayhanrp === '') {
            $error_rayhanrp = 'Nama grup tidak boleh kosong.';
        } elseif ($tingkat_rayhanrp < 10 || $tingkat_rayhanrp > 12) {
            $error_rayhanrp = 'Tingkat harus antara 10-12 (Kelas X, XI, XII).';
        } elseif (empty($jurusan_rayhanrp)) {
            $error_rayhanrp = 'Jurusan harus dipilih.';
        } else {
            requireNotReadonly($data_admin_rayhanrp, 'grup.php');
            $hasil_rayhanrp = sirey_execute(
                'UPDATE grup_rayhanRP SET nama_grup = ?, tingkat = ?, jurusan = ?, deskripsi = ?, wali_kelas_id = ? WHERE grup_id = ?',
                'sissii',
                $nama_grup_rayhanrp,
                $tingkat_rayhanrp,
                $jurusan_rayhanrp,
                $deskripsi_rayhanrp !== '' ? $deskripsi_rayhanrp : null,
                $wali_kelas_id_rayhanrp > 0 ? $wali_kelas_id_rayhanrp : null,
                $id_grup_rayhanrp
            );

            if ($hasil_rayhanrp >= 0) {
                auditLog($data_admin_rayhanrp['id'], 'update_grup', 'grup', $id_grup_rayhanrp, ['tingkat' => $tingkat_rayhanrp, 'jurusan' => $jurusan_rayhanrp]);
                $pesan_rayhanrp = 'Grup berhasil diperbarui.';
            } else {
                $error_rayhanrp = 'Gagal memperbarui grup.';
            }
        }
    } elseif ($aksi_rayhanrp === 'delete') {
        $id_grup_rayhanrp = (int)($_POST['id'] ?? 0);

        if (!can('delete_grup', $data_admin_rayhanrp)) {
            $error_rayhanrp = 'Anda tidak memiliki izin menghapus grup.';
        } elseif ($id_grup_rayhanrp > 0) {
            requireNotReadonly($data_admin_rayhanrp, 'grup.php');
            sirey_execute('DELETE FROM grup_rayhanRP WHERE grup_id = ?', 'i', $id_grup_rayhanrp);
            auditLog($data_admin_rayhanrp['id'], 'delete_grup', 'grup', $id_grup_rayhanrp);
            $pesan_rayhanrp = 'Grup dihapus.';
        } else {
            $error_rayhanrp = 'ID grup tidak valid.';
        }
    } elseif ($aksi_rayhanrp === 'delete_multiple') {
        $id_terpilih_rayhanrp = $_POST['selected_ids'] ?? [];
        
        if (!can('delete_grup', $data_admin_rayhanrp)) {
            $error_rayhanrp = 'Anda tidak memiliki izin menghapus grup.';
        } elseif (empty($id_terpilih_rayhanrp)) {
            $error_rayhanrp = 'Pilih grup yang akan dihapus terlebih dahulu.';
        } else {
            requireNotReadonly($data_admin_rayhanrp, 'grup.php');
            $deleted = 0;
            $failed = 0;
            
            foreach ($id_terpilih_rayhanrp as $id_item_rayhanrp) {
                $id_item_rayhanrp = (int)$id_item_rayhanrp;
                
                if ($id_item_rayhanrp <= 0) {
                    $failed++;
                    continue;
                }
                
                $result = sirey_execute('DELETE FROM grup_rayhanRP WHERE grup_id = ?', 'i', $id_item_rayhanrp);
                if ($result >= 1) {
                    $deleted++;
                } else {
                    $failed++;
                }
            }
            
            if ($deleted > 0) {
                auditLog($data_admin_rayhanrp['id'], 'bulk_delete_grup', 'grup', null, ['ids' => $id_terpilih_rayhanrp]);
                $pesan_rayhanrp = "✓ Berhasil menghapus $deleted grup";
                if ($failed > 0) {
                    $pesan_rayhanrp .= " ($failed gagal)";
                }
            } else {
                $error_rayhanrp = 'Tidak ada grup yang berhasil dihapus.';
            }
        }
    }
}

$teks_pencarian_rayhanrp = trim((string)($_POST['search'] ?? ''));

$sql = 'SELECT g.grup_id, g.nama_grup, g.tingkat, g.jurusan, g.deskripsi, g.pembuat_id, g.wali_kelas_id, g.aktif,
                a.nama_lengkap AS pembuat_nama,
                wali.nama_lengkap AS wali_kelas,
                COUNT(DISTINCT ga.akun_id) AS jml_anggota
         FROM grup_rayhanRP g
         LEFT JOIN akun_rayhanRP a ON g.pembuat_id = a.akun_id
         LEFT JOIN akun_rayhanRP wali ON g.wali_kelas_id = wali.akun_id
         LEFT JOIN grup_anggota_rayhanRP ga ON g.grup_id = ga.grup_id';

if ($data_admin_rayhanrp['role'] === 'guru') {
    $sql .= ' INNER JOIN guru_mengajar_rayhanRP gm_scope ON g.grup_id = gm_scope.grup_id AND gm_scope.akun_id = ' . (int)$data_admin_rayhanrp['id'] . ' AND gm_scope.aktif = 1';
}

$where_conditions_rayhanrp = [];
$query_params_rayhanrp = [];
$param_types_rayhanrp = '';

if ($teks_pencarian_rayhanrp !== '') {
    $where_conditions_rayhanrp[] = 'g.nama_grup LIKE ?';
    $query_params_rayhanrp[] = '%' . $teks_pencarian_rayhanrp . '%';
    $param_types_rayhanrp .= 's';
}

if (!empty($where_conditions_rayhanrp)) {
    $sql .= ' WHERE ' . implode(' AND ', $where_conditions_rayhanrp);
}

$sql .= ' GROUP BY g.grup_id, g.nama_grup, g.tingkat, g.jurusan, g.deskripsi, g.pembuat_id, g.wali_kelas_id, g.aktif, a.nama_lengkap, wali.nama_lengkap ORDER BY g.nama_grup ASC';

if (!empty($query_params_rayhanrp)) {
    $daftar_grup_rayhanrp = sirey_fetchAll(sirey_query($sql, $param_types_rayhanrp, ...$query_params_rayhanrp));
} else {
    $daftar_grup_rayhanrp = sirey_fetchAll(sirey_query($sql));
}
?>

<div class="page-header">
  <h2>🎓 Manajemen Grup / Kelas</h2>
  <p><?php echo htmlspecialchars($deskripsi_halaman_rayhanrp); ?></p>
</div>

<?php if ($pesan_rayhanrp !== ''): ?>
  <div class="alert alert-success">✓ <?php echo htmlspecialchars($pesan_rayhanrp); ?></div>
<?php endif; ?>

<?php if ($error_rayhanrp !== ''): ?>
  <div class="alert alert-error">⚠️ <?php echo htmlspecialchars($error_rayhanrp); ?></div>
<?php endif; ?>

<?php if (!$bisa_tulis_grup_rayhanrp): ?>
  <div class="alert alert-info">
    Mode baca saja aktif. Anda tetap bisa membuka detail anggota, jadwal, dan tugas tanpa opsi perubahan data.
  </div>
<?php endif; ?>


<!-- ================== MAIN TAB (DAFTAR vs BUAT) ================== -->
<div style="display:flex; border-bottom:2px solid #dee2e6; margin-bottom:24px;">
  <button type="button" onclick="switchMainTab('daftar')" id="maintab-daftar" 
          style="flex:1; padding:16px; text-align:center; font-weight:bold; 
                 color:#666; cursor:pointer; border-bottom:3px solid transparent;
                 background:transparent; border:none; transition:all 0.3s;">
    📋 Daftar Grup
  </button>
  <?php if ($bisa_buat_grup_rayhanrp): ?>
  <button type="button" onclick="switchMainTab('buat')" id="maintab-buat"
          style="flex:1; padding:16px; text-align:center; font-weight:bold;
                 color:#666; cursor:pointer; border-bottom:3px solid transparent;
                 background:transparent; border:none; transition:all 0.3s;">
    ➕ Buat Grup Baru
  </button>
  <?php endif; ?>
</div>


<!-- ================== TAB 1: DAFTAR GRUP ================== -->
<div id="maincontent-daftar" style="display:block;">
  <div class="card">
    <div class="card-header">
      <h3>Daftar Grup (<?php echo count($daftar_grup_rayhanrp); ?>)</h3>
    </div>

    <!-- FORM PENCARIAN -->
    <div style="padding:20px; background:#f8f9fa; border-bottom:1px solid #dee2e6;">
      <form method="POST" id="grupSearchForm" style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
        <div style="flex:1; min-width:200px;">
          <label class="form-label" style="display:block; font-size:14px; margin-bottom:6px;">
            🔍 Cari Nama Grup
          </label>
          <input type="text" name="search" class="form-control" placeholder="Ketik nama grup/kelas..."
                 value="<?php echo htmlspecialchars($teks_pencarian_rayhanrp); ?>" style="width:100%;">
        </div>

        <div style="display:flex; gap:8px;">
          <button type="submit" class="btn btn-primary" style="padding:8px 16px;">🔍 Cari</button>
          <?php if ($teks_pencarian_rayhanrp !== ''): ?>
            <button type="button" class="btn btn-secondary" onclick="resetSearch()" style="padding:8px 16px;">✕ Reset</button>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <?php if (empty($daftar_grup_rayhanrp)): ?>
      <div class="empty-state">
        <div class="empty-icon">🎓</div>
        <p><?php echo $teks_pencarian_rayhanrp !== '' ? 'Tidak ada grup yang cocok dengan pencarian Anda.' : 'Belum ada grup.'; ?></p>
      </div>
    <?php else: ?>
      <?php if ($bisa_hapus_grup_rayhanrp): ?>
      <div style="padding:20px; background:#f8f9fa; border-bottom:1px solid #dee2e6;">
        <div style="display:flex; gap:12px; align-items:center;">
          <div id="bulkDeleteSection" style="display:none; flex:1;">
            <span id="selectedCount" style="font-weight:bold; color:#0066cc;">0 grup dipilih</span>
          </div>
          <form method="POST" id="bulkDeleteForm" style="display:none;" onsubmit="return confirm('Hapus grup terpilih? Semua data terkait akan ikut terhapus.')">
            <input type="hidden" name="act" value="delete_multiple">
            <div id="selectedIdsContainer"></div>
            <button type="submit" class="btn btn-danger">🗑️ Hapus Terpilih</button>
          </form>
        </div>
      </div>
      <?php endif; ?>

      <!-- TABEL DAFTAR KELAS (DIPERBARUI: TANPA JADWAL & TUGAS) -->
      <table class="data-table" style="width:100%; border-collapse:collapse;">
        <thead>
          <tr>
            <?php if ($bisa_hapus_grup_rayhanrp): ?>
            <th style="width:40px;"><input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll(this)"></th>
            <?php endif; ?>
            <th>#</th>
            <th>Nama Grup</th>
            <th>Pembuat</th>
            <th>Wali Kelas</th>
            <th>Total Anggota</th>
            <th>Status</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($daftar_grup_rayhanrp as $item_grup_rayhanrp): ?>
            <tr>
              <?php if ($bisa_hapus_grup_rayhanrp): ?>
              <td style="width:40px; text-align:center;">
                <input type="checkbox" class="grupCheckbox" value="<?php echo $item_grup_rayhanrp['grup_id']; ?>" onchange="updateBulkDeleteUI()">
              </td>
              <?php endif; ?>

              <td style="color:var(--clr-muted); vertical-align:middle;">
                <?php echo (int)$item_grup_rayhanrp['grup_id']; ?>
              </td>

              <td style="vertical-align:middle;">
                <strong><?php echo htmlspecialchars($item_grup_rayhanrp['nama_grup']); ?></strong>
                <br><small style="color:var(--clr-muted); display:inline-flex; align-items:center; gap:6px; flex-wrap:wrap;">
                  <span class="badge badge-info">Kelas <?php echo htmlspecialchars((string)($item_grup_rayhanrp['tingkat'] ?? '')); ?></span>
                  <span class="badge badge-secondary"><?php echo htmlspecialchars($item_grup_rayhanrp['jurusan'] ?? '-'); ?></span>
                </small>
              </td>

              <td style="vertical-align:middle;">
                <small style="color:#666;">
                  <?php echo htmlspecialchars($item_grup_rayhanrp['pembuat_nama'] ?? '-'); ?>
                </small>
              </td>

              <td style="vertical-align:middle;">
                <span style="color:#1d4ed8; font-weight:500;">
                  <?php echo htmlspecialchars($item_grup_rayhanrp['wali_kelas'] ?? 'Belum Ditentukan'); ?>
                </span>
              </td>

              <td style="vertical-align:middle;">
                <?php echo (int)$item_grup_rayhanrp['jml_anggota']; ?> siswa
              </td>

              <td style="vertical-align:middle; text-align:center;">
                <?php if ($bisa_update_grup_rayhanrp): ?>
                  <button type="button" class="btn btn-sm" 
                          style="<?php echo (int)$item_grup_rayhanrp['aktif'] === 1 ? 'background:#28a745; color:white;' : 'background:#dc3545; color:white;'; ?>"
                          onclick="toggleGrupStatus(<?php echo (int)$item_grup_rayhanrp['grup_id']; ?>, <?php echo (int)$item_grup_rayhanrp['aktif']; ?>)">
                    <?php echo (int)$item_grup_rayhanrp['aktif'] === 1 ? '✓ Aktif' : '✕ Non-Aktif'; ?>
                  </button>
                <?php else: ?>
                  <span style="<?php echo (int)$item_grup_rayhanrp['aktif'] === 1 ? 'color:#28a745; font-weight:bold;' : 'color:#dc3545; font-weight:bold;'; ?>">
                    <?php echo (int)$item_grup_rayhanrp['aktif'] === 1 ? '✓ Aktif' : '✕ Non-Aktif'; ?>
                  </span>
                <?php endif; ?>
              </td>

              <td style="min-width:200px;">
                <div style="display:flex; flex-wrap:wrap; gap:6px; align-items:center;">
                  <button type="button" class="btn btn-info btn-sm" 
                          onclick="openAnggotaModal(<?php echo (int)$item_grup_rayhanrp['grup_id']; ?>, <?php echo htmlspecialchars(json_encode($item_grup_rayhanrp['nama_grup']), ENT_QUOTES, 'UTF-8'); ?>)">
                    👥 Anggota
                  </button>
                  
                  <?php if ($bisa_update_grup_rayhanrp): ?>
                  <button type="button" class="btn btn-primary btn-sm" onclick='openEditModal(<?php echo json_encode($item_grup_rayhanrp); ?>)'>
                    ✏️ Edit
                  </button>
                  <?php endif; ?>
                  
                  <?php if ($bisa_hapus_grup_rayhanrp): ?>
                  <form method="POST" style="display:inline" onsubmit="return confirm('Hapus grup ini?')">
                    <input type="hidden" name="act" value="delete">
                    <input type="hidden" name="id" value="<?php echo (int)$item_grup_rayhanrp['grup_id']; ?>">
                    <button type="submit" class="btn btn-danger btn-sm">🗑️ Hapus</button>
                  </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>


<!-- ================== TAB 2: BUAT GRUP BARU ================== -->
<?php if ($bisa_buat_grup_rayhanrp): ?>
<div id="maincontent-buat" style="display:none;">
  <div class="card">
    <div class="card-header">
      <h3>➕ Buat Grup Baru</h3>
    </div>

    <div style="padding:20px;">
      <form method="POST" style="max-width:620px;">
        <input type="hidden" name="act" value="create">

        <div class="form-group" style="margin-bottom:16px;">
          <label class="form-label">Nama Grup / Kelas *</label>
          <input name="nama_grup" type="text" class="form-control" placeholder="Contoh: XII IPA 1" required>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
          <div class="form-group" style="margin-bottom:16px;">
            <label class="form-label">Tingkat *</label>
            <select name="tingkat" class="form-control" required>
              <option value="">-- Pilih Tingkat --</option>
              <option value="10">Kelas X</option>
              <option value="11">Kelas XI</option>
              <option value="12">Kelas XII</option>
            </select>
          </div>
          <div class="form-group" style="margin-bottom:16px;">
            <label class="form-label">Jurusan *</label>
            <select name="jurusan" class="form-control" required>
              <option value="">-- Pilih Jurusan --</option>
              <option value="Teknik Pemesinan">Teknik Pemesinan</option>
              <option value="Teknik Mekatronika">Teknik Mekatronika</option>
              <option value="Teknik Kimia Industri">Teknik Kimia Industri</option>
              <option value="Pengembangan Perangkat Lunak dan Gim">Pengembangan Perangkat Lunak dan Gim</option>
              <option value="Desain Komunikasi Visual">Desain Komunikasi Visual</option>
              <option value="Animasi">Animasi</option>
            </select>
          </div>
        </div>

        <div class="form-group" style="margin-bottom:16px;">
          <label class="form-label">Deskripsi</label>
          <input name="deskripsi" type="text" class="form-control" placeholder="Opsional">
        </div>

        <div class="form-group" style="margin-bottom:16px;">
          <label class="form-label">Wali Kelas</label>
          <select name="wali_kelas_id" class="form-control">
            <option value="">-- Belum ditentukan --</option>
            <?php foreach ($daftar_guru_wali_rayhanrp as $guru_wali_rayhanrp): ?>
              <option value="<?php echo (int)$guru_wali_rayhanrp['akun_id']; ?>">
                <?php echo htmlspecialchars((string)$guru_wali_rayhanrp['nama_lengkap']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div style="margin-top:20px;">
          <button type="submit" class="btn btn-primary">➕ Buat Grup</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>


<!-- ================== EDIT MODAL ================== -->
<div id="editModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
  <div style="background:white; padding:30px; border-radius:8px; max-width:400px; width:90%; max-height:90vh; overflow-y:auto;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
      <h3 style="margin:0;">✏️ Edit Grup</h3>
      <button type="button" onclick="closeEditModal()" style="background:none; border:none; font-size:24px; cursor:pointer; color:#999;">✕</button>
    </div>

    <!-- FORM EDIT KELAS (DIPERBARUI: DENGAN WALI KELAS) -->
    <form method="POST" id="editForm">
      <input type="hidden" name="act" value="update">
      <input type="hidden" name="id" id="edit_id" value="">

      <div class="form-group" style="margin-bottom:20px;">
        <label class="form-label">Nama Grup / Kelas *</label>
        <input type="text" name="nama_grup" id="edit_nama_grup" class="form-control" required>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
        <div class="form-group" style="margin-bottom:16px;">
          <label class="form-label">Tingkat *</label>
          <select name="tingkat" id="edit_tingkat" class="form-control" required>
            <option value="">-- Pilih Tingkat --</option>
            <option value="10">Kelas X</option>
            <option value="11">Kelas XI</option>
            <option value="12">Kelas XII</option>
          </select>
        </div>
        <div class="form-group" style="margin-bottom:16px;">
          <label class="form-label">Jurusan *</label>
          <select name="jurusan" id="edit_jurusan" class="form-control" required>
            <option value="">-- Pilih Jurusan --</option>
            <option value="Teknik Pemesinan">Teknik Pemesinan</option>
            <option value="Teknik Mekatronika">Teknik Mekatronika</option>
            <option value="Teknik Kimia Industri">Teknik Kimia Industri</option>
            <option value="Pengembangan Perangkat Lunak dan Gim">Pengembangan Perangkat Lunak dan Gim</option>
            <option value="Desain Komunikasi Visual">Desain Komunikasi Visual</option>
            <option value="Animasi">Animasi</option>
          </select>
        </div>
      </div>

      <div class="form-group" style="margin-bottom:20px;">
        <label class="form-label">Deskripsi</label>
        <input type="text" name="deskripsi" id="edit_deskripsi" class="form-control">
      </div>

      <!-- Penambahan Form Edit Wali Kelas -->
      <div class="form-group" style="margin-bottom:20px;">
        <label class="form-label">Wali Kelas</label>
        <select name="wali_kelas_id" id="edit_wali_kelas_id" class="form-control">
          <option value="">-- Belum ditentukan --</option>
          <?php foreach ($daftar_guru_wali_rayhanrp as $guru_wali_rayhanrp): ?>
            <option value="<?php echo (int)$guru_wali_rayhanrp['akun_id']; ?>">
              <?php echo htmlspecialchars((string)$guru_wali_rayhanrp['nama_lengkap']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="display:flex; gap:8px;">
        <button type="submit" class="btn btn-primary" style="flex:1;">💾 Simpan</button>
        <button type="button" class="btn btn-secondary" onclick="closeEditModal()" style="flex:1;">Batal</button>
      </div>
    </form>
  </div>
</div>


<!-- ================== ANGGOTA MODAL ================== -->
<div id="anggotaModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center; overflow-y:auto;">
  <div style="background:white; padding:30px; border-radius:8px; max-width:900px; width:92%; margin:20px auto;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
      <h3 style="margin:0;">👥 Anggota Grup: <span id="anggota_grup_name"></span></h3>
      <button type="button" onclick="closeAnggotaModal()" style="background:none; border:none; font-size:24px; cursor:pointer; color:#999;">✕</button>
    </div>

    <div id="anggotaContent" style="max-height:70vh; overflow-y:auto;">
      <p style="text-align:center; color:#999;">Memuat data...</p>
    </div>
  </div>
</div>


<!-- ================== JAVASCRIPT ================== -->
<script>
  const grupPagePermissions = <?php echo json_encode([
    'userRole' => $data_admin_rayhanrp['role'],
    'canCreateGrup' => $bisa_buat_grup_rayhanrp,
    'canUpdateGrup' => $bisa_update_grup_rayhanrp,
    'canDeleteGrup' => $bisa_hapus_grup_rayhanrp,
    'canManageMembers' => $bisa_kelola_anggota_rayhanrp,
    'canCreateJadwal' => $bisa_buat_jadwal_rayhanrp,
    'canUpdateJadwal' => $bisa_update_jadwal_rayhanrp,
    'canDeleteJadwal' => $bisa_hapus_jadwal_rayhanrp,
  ], JSON_UNESCAPED_SLASHES); ?>;

  function buildReadonlyNote(message) {
    return '<div style="margin-top:20px; padding:12px 14px; background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; color:#1d4ed8; font-size:13px;">' + message + '</div>';
  }

  function switchMainTab(tabName) {
    ['daftar', 'buat'].forEach((name) => {
      const content = document.getElementById('maincontent-' + name);
      const tab = document.getElementById('maintab-' + name);
      if (content) {
        content.style.display = 'none';
      }
      if (tab) {
        tab.style.borderBottom = '3px solid transparent';
        tab.style.color = '#666';
      }
    });

    const targetContent = document.getElementById('maincontent-' + tabName) || document.getElementById('maincontent-daftar');
    const targetTab = document.getElementById('maintab-' + tabName) || document.getElementById('maintab-daftar');

    if (targetContent) {
      targetContent.style.display = 'block';
    }
    if (targetTab) {
      targetTab.style.borderBottom = '3px solid #0066cc';
      targetTab.style.color = '#0066cc';
    }
  }

  switchMainTab('daftar');

  // ===== EDIT MODAL =====
  function openEditModal(group) {
    document.getElementById('edit_id').value = group.grup_id;
    document.getElementById('edit_nama_grup').value = group.nama_grup;
    document.getElementById('edit_tingkat').value = group.tingkat || '0';
    document.getElementById('edit_jurusan').value = group.jurusan || '';
    document.getElementById('edit_deskripsi').value = group.deskripsi || '';
    document.getElementById('edit_wali_kelas_id').value = group.wali_kelas_id || '';
    document.getElementById('editModal').style.display = 'flex';
  }

  function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
  }

  const editModal = document.getElementById('editModal');
  if (editModal) {
    editModal.addEventListener('click', function(e) {
      if (e.target === this) {
        closeEditModal();
      }
    });
  }

  // ===== ANGGOTA MODAL =====
  function openAnggotaModal(grupId, grupName) {
    const modal = document.getElementById('anggotaModal');
    const nameEl = document.getElementById('anggota_grup_name');
    const contentEl = document.getElementById('anggotaContent');
    
    if (!modal || !nameEl || !contentEl) return;
    
    nameEl.textContent = grupName;
    contentEl.innerHTML = '<p style="text-align:center; color:#999;">Memuat data...</p>';
    modal.style.display = 'flex';
    
    const fetchUrl = './grup.php?action=get_members&grup_id=' + grupId;
    
    fetch(fetchUrl)
      .then(response => {
        if (!response.ok) throw new Error('HTTP ' + response.status);
        return response.text();
      })
      .then(html => {
        if (html.trim()) {
          if (!grupPagePermissions.canManageMembers) {
            contentEl.innerHTML = html + buildReadonlyNote('Mode baca saja. Penambahan atau penghapusan anggota hanya tersedia untuk admin dan kurikulum.');
            return Promise.resolve();
          }

          return fetch('./grup.php?action=get_available_users&grup_id=' + grupId)
            .then(r => r.text())
            .then(usersHtml => {
              let fullContent = html;
              if (usersHtml.trim()) {
                fullContent += '<hr style="margin:20px 0; border:none; border-top:2px solid #dee2e6;">';
                fullContent += '<div style="margin-top:20px;">';
                fullContent += '<h4 style="margin-top:0; margin-bottom:15px;">➕ Tambah Anggota</h4>';
                fullContent += usersHtml;
                fullContent += '</div>';
              }
              contentEl.innerHTML = fullContent;
            });
        } else {
          contentEl.innerHTML = '<p style="text-align:center; color:#999; padding:20px;">Tidak ada data</p>';
        }
      })
      .catch(error => {
        contentEl.innerHTML = '<p style="color:red;">❌ Error memuat data: ' + error.message + '</p>';
      });
  }

  function closeAnggotaModal() {
    const modal = document.getElementById('anggotaModal');
    if (modal) modal.style.display = 'none';
  }

  (function() {
    const modal = document.getElementById('anggotaModal');
    if (modal) {
      modal.addEventListener('click', function(e) {
        if (e.target === this) closeAnggotaModal();
      });
    }
  })();

  // ===== FILTER & SEARCH FUNCTIONS =====
  function filterMembers(searchTerm, grupId) {
    const searchEl = document.getElementById('searchMembers');
    if (searchEl) searchEl.value = searchTerm;
    
    const modal = document.getElementById('anggotaModal');
    if (modal && modal.style.display === 'flex') {
      const contentEl = document.getElementById('anggotaContent');
      const url = './grup.php?action=get_members&grup_id=' + grupId + '&search=' + encodeURIComponent(searchTerm);
      
      fetch(url)
        .then(r => r.text())
        .then(html => {
          let fullContent = html;
          if (!searchTerm && grupPagePermissions.canManageMembers) {
            return fetch('./grup.php?action=get_available_users&grup_id=' + grupId)
              .then(r => r.text())
              .then(usersHtml => {
                if (usersHtml.trim()) {
                  fullContent += '<hr style="margin:20px 0; border:none; border-top:2px solid #dee2e6;">';
                  fullContent += '<div style="margin-top:20px;">';
                  fullContent += '<h4 style="margin-top:0; margin-bottom:15px;">➕ Tambah Anggota</h4>';
                  fullContent += usersHtml;
                  fullContent += '</div>';
                }
                contentEl.innerHTML = fullContent;
              });
          } else {
            if (!searchTerm && !grupPagePermissions.canManageMembers) {
              fullContent += buildReadonlyNote('Mode baca saja.');
            }
            contentEl.innerHTML = fullContent;
            return Promise.resolve();
          }
        })
        .catch(error => {
          contentEl.innerHTML = '<p style="color:red;">Error loading data</p>';
        });
    }
  }

  // ===== BULK ACTIONS =====
  function toggleSelectAllAvailableMembers(checkbox) {
    document.querySelectorAll('.availableMemberCheckbox').forEach((item) => {
      item.checked = checkbox.checked;
    });
  }

  function submitAddMembers(event, grupId) {
    event.preventDefault();
    const form = event.currentTarget;
    const checked = form.querySelectorAll('.availableMemberCheckbox:checked');

    if (checked.length === 0) {
      alert('Pilih minimal satu siswa.');
      return;
    }

    const formData = new FormData(form);
    fetch('./grup.php?action=bulk_add_members&grup_id=' + grupId, {
      method: 'POST',
      body: formData
    })
      .then(response => response.json())
      .then(data => {
        if (!data.success) {
          alert(data.message || 'Gagal menambah anggota.');
          return;
        }
        const grupNameEl = document.getElementById('anggota_grup_name');
        openAnggotaModal(grupId, grupNameEl ? grupNameEl.textContent : '');
      })
      .catch(error => {
        alert('Error menambah anggota.');
      });
  }

  function submitImportMembersExcel(event, grupId) {
    event.preventDefault();
    const formData = new FormData(event.currentTarget);

    fetch('./grup.php?action=import_members_excel&grup_id=' + grupId, {
      method: 'POST',
      body: formData
    })
      .then(response => response.json())
      .then(data => {
        let message = data.message || 'Import selesai.';
        if (Array.isArray(data.errors) && data.errors.length > 0) {
          message += '\n\n' + data.errors.join('\n');
        }
        alert(message);

        if (data.success) {
          const grupNameEl = document.getElementById('anggota_grup_name');
          openAnggotaModal(grupId, grupNameEl ? grupNameEl.textContent : '');
        }
      })
      .catch(error => {
        alert('Error import Excel.');
      });
  }

  function toggleSelectAllMembers(checkbox, grupId) {
    const allCheckboxes = document.querySelectorAll('.memberCheckbox');
    allCheckboxes.forEach(cb => {
      cb.checked = checkbox.checked;
    });
    updateMemberBulkUI(grupId);
  }

  function updateMemberBulkUI(grupId) {
    const allCheckboxes = document.querySelectorAll('.memberCheckbox');
    const selectedCheckboxes = document.querySelectorAll('.memberCheckbox:checked');
    const selectAllCheckbox = document.getElementById('selectAllMembers');
    const bulkSection = document.getElementById('bulkDeleteMembersSection');
    const selectedCount = document.getElementById('memberSelectedCount');

    if (!selectAllCheckbox || !bulkSection || !selectedCount) return;

    selectAllCheckbox.checked = selectedCheckboxes.length > 0 && selectedCheckboxes.length === allCheckboxes.length;
    selectAllCheckbox.indeterminate = selectedCheckboxes.length > 0 && selectedCheckboxes.length < allCheckboxes.length;

    if (selectedCheckboxes.length > 0) {
      bulkSection.style.display = 'block';
      selectedCount.textContent = selectedCheckboxes.length + ' anggota dipilih';
    } else {
      bulkSection.style.display = 'none';
    }
  }

  function bulkDeleteMembers(grupId) {
    const selectedCheckboxes = document.querySelectorAll('.memberCheckbox:checked');
    const ids = Array.from(selectedCheckboxes).map(cb => cb.value);
    
    if (ids.length === 0) return;
    
    if (!confirm('Yakin ingin menghapus ' + ids.length + ' anggota dari grup?')) return;
    
    const formData = new FormData();
    ids.forEach(id => {
      formData.append('member_ids[]', id);
    });
    
    fetch('./grup.php?action=bulk_delete_members&grup_id=' + grupId, {
      method: 'POST',
      body: formData
    })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          const grupNameEl = document.getElementById('anggota_grup_name');
          if (grupNameEl) {
            openAnggotaModal(grupId, grupNameEl.textContent);
          }
        } else {
          alert('Error: ' + data.message);
        }
      })
      .catch(error => {
        alert('Error menghapus anggota');
      });
  }

  function deleteMemberFromGroup(akunId, grupId, memberName) {
    if (!confirm('Yakin ingin menghapus ' + memberName + ' dari grup ini?')) return;
    
    fetch('./grup.php?action=delete_member&grup_id=' + grupId + '&akun_id=' + akunId)
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          const modal = document.getElementById('anggotaModal');
          if (modal && modal.style.display === 'flex') {
            const grupNameEl = document.getElementById('anggota_grup_name');
            if (grupNameEl) openAnggotaModal(grupId, grupNameEl.textContent);
          }
        } else {
          alert('Error: ' + data.message);
        }
      })
      .catch(error => {
        alert('Error menghapus anggota');
      });
  }

  // ===== TOGGLE GROUP STATUS =====
  function toggleGrupStatus(grupId, currentStatus) {
    const newStatus = currentStatus === 1 ? 0 : 1;
    const statusLabel = newStatus === 1 ? 'Aktif' : 'Non-Aktif';
    
    if (!confirm('Ubah status grup menjadi ' + statusLabel + '?')) return;

    const formData = new FormData();
    formData.append('status', newStatus);

    fetch('./grup.php?action=toggle_status&grup_id=' + grupId, {
      method: 'POST',
      body: formData
    })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          location.reload();
        } else {
          alert('❌ Error: ' + data.message);
        }
      })
      .catch(error => {
        alert('Error mengubah status grup');
      });
  }

  // ===== GENERAL BULK CHECKBOX =====
  function toggleSelectAll(checkbox) {
    const allCheckboxes = document.querySelectorAll('.grupCheckbox');
    allCheckboxes.forEach(cb => {
      cb.checked = checkbox.checked;
    });
    updateBulkDeleteUI();
  }

  function updateBulkDeleteUI() {
    const allCheckboxes = document.querySelectorAll('.grupCheckbox');
    const selectedCheckboxes = document.querySelectorAll('.grupCheckbox:checked');
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    const bulkDeleteSection = document.getElementById('bulkDeleteSection');
    const bulkDeleteForm = document.getElementById('bulkDeleteForm');
    const selectedCount = document.getElementById('selectedCount');
    const selectedIdsContainer = document.getElementById('selectedIdsContainer');

    if (!selectAllCheckbox || !bulkDeleteSection || !bulkDeleteForm || !selectedCount || !selectedIdsContainer) return;

    selectAllCheckbox.checked = selectedCheckboxes.length > 0 && selectedCheckboxes.length === allCheckboxes.length;
    selectAllCheckbox.indeterminate = selectedCheckboxes.length > 0 && selectedCheckboxes.length < allCheckboxes.length;

    if (selectedCheckboxes.length > 0) {
      bulkDeleteSection.style.display = 'block';
      bulkDeleteForm.style.display = 'block';
      selectedCount.textContent = selectedCheckboxes.length + ' grup dipilih';

      selectedIdsContainer.innerHTML = '';
      selectedCheckboxes.forEach(cb => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'selected_ids[]';
        input.value = cb.value;
        selectedIdsContainer.appendChild(input);
      });
    } else {
      bulkDeleteSection.style.display = 'none';
      bulkDeleteForm.style.display = 'none';
      selectedIdsContainer.innerHTML = '';
    }
  }

  function resetSearch() {
    document.querySelector('input[name="search"]').value = '';
    document.getElementById('grupSearchForm')?.submit();
  }
</script>

<?php layoutEnd(); ?>