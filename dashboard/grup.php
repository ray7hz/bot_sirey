<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

// Start session for AJAX requests
startSession();

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
// Jika ada GET parameter untuk fetch data
$aksi_rayhanrp = (string)($_GET['action'] ?? '');
$id_grup_rayhanrp = (int)($_GET['grup_id'] ?? 0);

if (!empty($aksi_rayhanrp) && $id_grup_rayhanrp > 0) {
    if ($admin_ajax_rayhanrp['id'] <= 0 || !can('view_grup', $admin_ajax_rayhanrp)) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
        exit;
    }

    // Set header based on action type
    if (in_array($aksi_rayhanrp, ['get_members', 'get_jadwal', 'get_tugas', 'get_available_users'])) {
        header('Content-Type: text/html; charset=utf-8');
    } else {
        header('Content-Type: application/json; charset=utf-8');
    }
    
    if ($aksi_rayhanrp === 'get_members') {
        // Query untuk mendapatkan anggota grup
        $pencarian_rayhanrp = trim((string)($_GET['search'] ?? ''));
        
        $pernyataan_sql_rayhanrp = "
            SELECT 
                a.akun_id,
                a.nis_nip,
                a.nama_lengkap,
                a.role,
                a.jenis_kelamin
            FROM akun_rayhanrp a
            JOIN grup_anggota_rayhanrp ga ON a.akun_id = ga.akun_id
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
                    <button type="button" onclick="deleteMemberFromGroup(<?php echo (int)$baris_anggota_rayhanrp['akun_id']; ?>, <?php echo $id_grup_rayhanrp; ?>, <?php echo htmlspecialchars(json_encode($baris_anggota_rayhanrp['nama_lengkap']), ENT_QUOTES, 'UTF-8'); ?>"
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
    } elseif ($aksi_rayhanrp === 'get_jadwal') {
        // Query untuk mendapatkan jadwal grup dari guru_mengajar_rayhanrp
        // Tab jadwal di grup adalah READ-ONLY untuk semua role
        $pencarian_jadwal_rayhanrp = trim((string)($_GET['search'] ?? ''));
        
        $pernyataan_sql_jadwal_rayhanrp = "
            SELECT 
                gm.id as jadwal_id,
                gm.hari,
                gm.jam_mulai,
                gm.jam_selesai,
                g.nama_grup,
                a.nama_lengkap as guru_nama,
                mp.nama AS nama_mapel
            FROM guru_mengajar_rayhanrp gm
            LEFT JOIN grup_rayhanrp g ON gm.grup_id = g.grup_id
            LEFT JOIN akun_rayhanrp a ON gm.akun_id = a.akun_id
            LEFT JOIN mata_pelajaran_rayhanrp mp ON gm.matpel_id = mp.matpel_id
            WHERE gm.grup_id = ? AND gm.hari IS NOT NULL
        ";
        
        $param_jadwal_rayhanrp = [$id_grup_rayhanrp];
        $tipe_jadwal_rayhanrp = 'i';
        
        $hari_order = "FIELD(gm.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu')";
        $pernyataan_sql_jadwal_rayhanrp .= " ORDER BY $hari_order ASC, gm.jam_mulai ASC";
        
        $hasil_jadwal_rayhanrp = sirey_query($pernyataan_sql_jadwal_rayhanrp, $tipe_jadwal_rayhanrp, ...$param_jadwal_rayhanrp);
        if (!$hasil_jadwal_rayhanrp) {
            echo '<p style="color:red;">❌ Error query database: ' . htmlspecialchars(sirey_lastDbError()) . '</p>';
            exit;
        }
        
        $daftar_jadwal_rayhanrp = sirey_fetchAll($hasil_jadwal_rayhanrp);
        if (!is_array($daftar_jadwal_rayhanrp)) {
            echo '<p style="color:red;">Error fetch schedules</p>';
            exit;
        }
        
        ?>
<div style="margin-bottom:15px; display:flex; gap:10px; align-items:center;">
    <button type="button" onclick="filterJadwal('', <?php echo $id_grup_rayhanrp; ?>)" 
            style="background:#6c757d; color:white; border:none; padding:8px 12px; border-radius:4px; cursor:pointer; font-weight:bold;">
        ✕ Refresh
    </button>
</div>

<?php
        if (empty($daftar_jadwal_rayhanrp)) {
            echo '<p style="text-align:center; color:#999; padding:20px;">Tidak ada jadwal yang cocok</p>';
        } else {
            ?>
<table style="width:100%; border-collapse:collapse;">
    <thead>
        <tr style="background:#f5f5f5; border-bottom:2px solid #dee2e6;">
            <th style="padding:12px; text-align:center; font-weight:bold;">Hari</th>
            <th style="padding:12px; text-align:center; font-weight:bold;">Jam Mulai</th>
            <th style="padding:12px; text-align:center; font-weight:bold;">Jam Selesai</th>
            <th style="padding:12px; text-align:left; font-weight:bold;">Guru</th>
            <th style="padding:12px; text-align:left; font-weight:bold;">Mata Pelajaran</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($daftar_jadwal_rayhanrp as $schedule): ?>
            <tr style="border-bottom:1px solid #dee2e6;">
                <td style="padding:12px; text-align:center;">
                    <span style="display:inline-block; padding:4px 8px; border-radius:4px; background:#e3f2fd; color:#1976d2; font-size:12px; font-weight:bold;">
                        <?php echo htmlspecialchars($schedule['hari']); ?>
                    </span>
                </td>
                <td style="padding:12px; text-align:center;">
                    <span style="display:inline-block; padding:4px 8px; border-radius:4px; background:#fff3cd; color:#856404; font-size:12px; font-weight:bold;">
                        <?php echo htmlspecialchars($schedule['jam_mulai']); ?>
                    </span>
                </td>
                <td style="padding:12px; text-align:center;">
                    <span style="display:inline-block; padding:4px 8px; border-radius:4px; background:#f0f0f0; color:#333; font-size:12px; font-weight:bold;">
                        <?php echo htmlspecialchars($schedule['jam_selesai']); ?>
                    </span>
                </td>
                <td style="padding:12px; text-align:left;">
                    <small style="color:#666;">
                        <?php echo htmlspecialchars($schedule['guru_nama'] ?? '-'); ?>
                    </small>
                </td>
                <td style="padding:12px; text-align:left;">
                    <small style="color:#666;">
                        <?php echo htmlspecialchars($schedule['nama_mapel'] ?? '-'); ?>
                    </small>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
            <?php
        }
        exit;
    } elseif ($aksi_rayhanrp === 'get_tugas') {
        // Query untuk mendapatkan tugas grup
        $search = trim((string)($_GET['search'] ?? ''));
        
        $sql = "
            SELECT 
                t.tugas_id,
                t.judul,
                t.deskripsi,
                t.tenggat,
                g.nama_grup
            FROM tugas_rayhanRP t
            LEFT JOIN grup_rayhanRP g ON t.grup_id = g.grup_id
            WHERE t.grup_id = ?
        ";
        
        $params = [$id_grup_rayhanrp];
        $types = 'i';
        
        if (!empty($search)) {
            $sql .= " AND (t.judul LIKE ? OR t.deskripsi LIKE ?)";
            $searchTerm = '%' . $search . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'ss';
        }
        
        $sql .= " ORDER BY t.tenggat ASC";
        
        $result = sirey_query($sql, $types, ...$params);
        if (!$result) {
            echo '<p style="color:red;">Error query database</p>';
            exit;
        }
        
        $daftar_tugas_rayhanrp = sirey_fetchAll($result);
        if (!is_array($daftar_tugas_rayhanrp)) {
            echo '<p style="color:red;">Error fetch tasks</p>';
            exit;
        }
        
        ?>
<div style="margin-bottom:15px; display:flex; gap:10px; align-items:center;">
    <input type="text" id="searchTugas" placeholder="Cari judul atau deskripsi tugas..." value="<?php echo htmlspecialchars($search); ?>" 
           style="flex:1; padding:8px; border:1px solid #ddd; border-radius:4px;" 
           onkeyup="filterTugas(this.value, <?php echo $id_grup_rayhanrp; ?>)">
    <button type="button" onclick="filterTugas('', <?php echo $id_grup_rayhanrp; ?>)" 
            style="background:#6c757d; color:white; border:none; padding:8px 12px; border-radius:4px; cursor:pointer; font-weight:bold;">
        ✕ Clear
    </button>
</div>

<?php
        if (empty($daftar_tugas_rayhanrp)) {
            echo '<p style="text-align:center; color:#999; padding:20px;">Tidak ada tugas yang cocok</p>';
        } else {
            ?>
<table style="width:100%; border-collapse:collapse;">
    <thead>
        <tr style="background:#f5f5f5; border-bottom:2px solid #dee2e6;">
            <th style="padding:12px; text-align:left; font-weight:bold;">Judul Tugas</th>
            <th style="padding:12px; text-align:left; font-weight:bold;">Deskripsi</th>
            <th style="padding:12px; text-align:center; font-weight:bold;">Tenggat Waktu</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($daftar_tugas_rayhanrp as $item_tugas_rayhanrp): ?>
            <tr style="border-bottom:1px solid #dee2e6;">
                <td style="padding:12px;">
                    <strong><?php echo htmlspecialchars((string)$item_tugas_rayhanrp['judul']); ?></strong>
                </td>
                <td style="padding:12px;">
                    <small style="color:#666;">
                        <?php 
                            $desc = (string)($item_tugas_rayhanrp['deskripsi'] ?? '');
                            if (!empty($desc)) {
                                echo htmlspecialchars(substr($desc, 0, 80)) . (strlen($desc) > 80 ? '...' : '');
                            } else {
                                echo '<em style="color:#999;">-</em>';
                            }
                        ?>
                    </small>
                </td>
                <td style="padding:12px; text-align:center;">
                    <?php 
                        $deadline = strtotime((string)($item_tugas_rayhanrp['tenggat'] ?? ''));
                        if ($deadline === false) {
                            $deadline = time();
                        }
                        $now = time();
                        $bgColor = $deadline < $now ? '#f8d7da' : '#d4edda';
                        $textColor = $deadline < $now ? '#721c24' : '#155724';
                    ?>
                    <span style="display:inline-block; padding:4px 8px; border-radius:4px; background:<?php echo $bgColor; ?>; color:<?php echo $textColor; ?>; font-size:12px; font-weight:bold;">
                        <?php echo date('d-m-Y', $deadline); ?>
                    </span>
                </td>
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
        // Hapus anggota dari grup
        $akunId = (int)($_GET['akun_id'] ?? 0);
        
        if ($akunId <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
            exit;
        }
        
        // Check if this group is the user's primary group
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
        
        // Toggle status aktif/non-aktif grup
        $statusBaru = (int)($_POST['status'] ?? 0);
        if ($statusBaru !== 0 && $statusBaru !== 1) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Status tidak valid']);
            exit;
        }
        
        $sql = 'UPDATE grup_rayhanrp SET aktif = ? WHERE grup_id = ?';
        sirey_execute($sql, 'ii', $statusBaru, $id_grup_rayhanrp);
        
        auditLog($admin_ajax_rayhanrp['id'], 'toggle_grup', 'grup', $id_grup_rayhanrp, ['aktif' => $statusBaru]);
        
        $statusLabel = $statusBaru === 1 ? 'aktif' : 'non-aktif';
        echo json_encode(['success' => true, 'message' => 'Grup berhasil diubah menjadi ' . $statusLabel, 'status' => $statusBaru]);
        exit;
    } elseif ($aksi_rayhanrp === 'delete_jadwal') {
        if (!can('delete_jadwal', $admin_ajax_rayhanrp)) {
            echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
            exit;
        }
        // Hapus jadwal dari guru_mengajar (soft delete dengan set hari/jam ke NULL)
        $jadwalId = (int)($_GET['jadwal_id'] ?? 0);
        
        if ($jadwalId <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
            exit;
        }
        
        // Hapus jadwal dari database (soft delete: set hari dan jam ke NULL)
        $sql = "UPDATE guru_mengajar_rayhanrp SET hari = NULL, jam_mulai = NULL, jam_selesai = NULL WHERE id = ? AND grup_id = ? LIMIT 1";
        sirey_execute($sql, 'ii', $jadwalId, $id_grup_rayhanrp);
        
        echo json_encode(['success' => true, 'message' => 'Jadwal berhasil dihapus']);
        exit;
    } elseif ($aksi_rayhanrp === 'add_member') {
        if (!can('update_grup', $admin_ajax_rayhanrp)) {
            echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
            exit;
        }
        // Tambah anggota ke grup
        $akunId = (int)($_GET['akun_id'] ?? 0);
        
        if ($akunId <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
            exit;
        }
        
        // Cek apakah sudah ada
        $checkSql = "SELECT akun_id FROM grup_anggota_rayhanRP WHERE grup_id = ? AND akun_id = ?";
        $checkResult = sirey_query($checkSql, 'ii', $id_grup_rayhanrp, $akunId);
        $existing = sirey_fetch($checkResult);
        
        if ($existing) {
            echo json_encode(['success' => false, 'message' => 'Anggota sudah ada di grup ini']);
            exit;
        }

        $currentGrupId = getPrimaryGroupId($akunId) ?? 0;
        
        if ($currentGrupId <= 0) {
            // Set as primary group if user doesn't have one yet (all groups are now class-based)
            syncPrimaryGroup($akunId, $id_grup_rayhanrp);
        } else {
            ensureUserMembership($akunId, $id_grup_rayhanrp, 'tambahan');
        }
        
        echo json_encode(['success' => true, 'message' => 'Anggota berhasil ditambahkan']);
        exit;
    } elseif ($aksi_rayhanrp === 'get_available_users') {
        if (!$bisa_kelola_anggota_ajax_rayhanrp) {
            echo '';
            exit;
        }
        // Ambil user yang belum menjadi anggota grup
        $sql = "
            SELECT 
                a.akun_id,
                a.nis_nip,
                a.nama_lengkap
            FROM akun_rayhanrp a
            WHERE a.akun_id NOT IN (
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
            echo '<p style="color:#999; font-style:italic;">Semua pengguna sudah menjadi anggota grup</p>';
        } else {
            ?>
<div style="display:flex; gap:10px; align-items:flex-end;">
    <div style="flex:1;">
        <select id="addMemberSelect" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px; font-size:14px;">
            <option value="">-- Pilih Anggota --</option>
            <?php foreach ($users as $user): ?>
                <option value="<?php echo (int)$user['akun_id']; ?>">
                    <?php echo htmlspecialchars($user['nis_nip'] . ' - ' . $user['nama_lengkap']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="button" onclick="addMemberToGroup(<?php echo $id_grup_rayhanrp; ?>)" 
            style="background:#28a745; color:white; border:none; padding:8px 15px; border-radius:4px; cursor:pointer; font-weight:bold; white-space:nowrap;">
        ✓ Tambah
    </button>
</div>
            <?php
        }
        exit;
    } elseif ($aksi_rayhanrp === 'add_jadwal') {
        if (!can('create_jadwal', $admin_ajax_rayhanrp)) {
            echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
            exit;
        }
        // Tambah jadwal baru ke grup
        $judul = trim((string)($_POST['judul'] ?? ''));
        $hari = trim((string)($_POST['hari'] ?? ''));
        $jamMulai = trim((string)($_POST['jam_mulai'] ?? ''));
        $jamSelesai = trim((string)($_POST['jam_selesai'] ?? ''));
        $pembuatId = (int)($_SESSION['admin_id'] ?? 0);
        
        if (empty($judul) || empty($hari) || empty($jamMulai) || empty($jamSelesai)) {
            echo json_encode(['success' => false, 'message' => 'Semua field harus diisi']);
            exit;
        }
        
        if ($pembuatId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Anda harus login untuk menambah jadwal']);
            exit;
        }
        
        if ($jamSelesai <= $jamMulai) {
            echo json_encode(['success' => false, 'message' => 'Jam selesai harus lebih besar dari jam mulai']);
            exit;
        }
        
        $insertSql = "INSERT INTO jadwal_rayhanRP (grup_id, judul, hari, jam_mulai, jam_selesai, pembuat_id) VALUES (?, ?, ?, ?, ?, ?)";
        $result = sirey_execute($insertSql, 'issssi', $id_grup_rayhanrp, $judul, $hari, $jamMulai, $jamSelesai, $pembuatId);
        
        if ($result <= 0) {
            echo json_encode(['success' => false, 'message' => 'Gagal menambahkan jadwal. Periksa kembali data jadwal.']);
            exit;
        }
        
        echo json_encode(['success' => true, 'message' => 'Jadwal berhasil ditambahkan']);
        exit;
    } elseif ($aksi_rayhanrp === 'update_jadwal') {
        if (!can('update_jadwal', $admin_ajax_rayhanrp)) {
            echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
            exit;
        }
        // Update jadwal di guru_mengajar
        $jadwalId = (int)($_POST['jadwal_id'] ?? 0);
        $hari = trim((string)($_POST['hari'] ?? ''));
        $jamMulai = trim((string)($_POST['jam_mulai'] ?? ''));
        $jamSelesai = trim((string)($_POST['jam_selesai'] ?? ''));
        
        if ($jadwalId <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID jadwal tidak valid']);
            exit;
        }
        
        if (empty($hari) || empty($jamMulai) || empty($jamSelesai)) {
            echo json_encode(['success' => false, 'message' => 'Semua field harus diisi']);
            exit;
        }
        
        if ($jamSelesai <= $jamMulai) {
            echo json_encode(['success' => false, 'message' => 'Jam selesai harus lebih besar dari jam mulai']);
            exit;
        }
        
        // Verify jadwal belongs to this group
        $checkSql = "SELECT id FROM guru_mengajar_rayhanrp WHERE id = ? AND grup_id = ?";
        $checkResult = sirey_query($checkSql, 'ii', $jadwalId, $id_grup_rayhanrp);
        if (!sirey_fetch($checkResult)) {
            echo json_encode(['success' => false, 'message' => 'Jadwal tidak ditemukan atau bukan milik grup ini']);
            exit;
        }
        
        $updateSql = "UPDATE guru_mengajar_rayhanrp SET hari = ?, jam_mulai = ?, jam_selesai = ? WHERE id = ? AND grup_id = ?";
        $result = sirey_execute($updateSql, 'sssii', $hari, $jamMulai, $jamSelesai, $jadwalId, $id_grup_rayhanrp);
        
        if ($result < 0) {
            echo json_encode(['success' => false, 'message' => 'Gagal memperbarui jadwal. Pastikan data jadwal valid.']);
            exit;
        }
        
        echo json_encode(['success' => true, 'message' => 'Jadwal berhasil diperbarui']);
        exit;
    } elseif ($aksi_rayhanrp === 'bulk_delete_members') {
        if (!can('update_grup', $admin_ajax_rayhanrp)) {
            echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
            exit;
        }
        // Hapus banyak anggota sekaligus
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
    } elseif ($aksi_rayhanrp === 'bulk_delete_jadwal') {
        if (!can('delete_jadwal', $admin_ajax_rayhanrp)) {
            echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
            exit;
        }
        // Hapus banyak jadwal sekaligus (soft delete dari guru_mengajar)
        $ids = (array)($_POST['jadwal_ids'] ?? []);
        $ids = array_filter(array_map('intval', $ids));
        
        if (empty($ids)) {
            echo json_encode(['success' => false, 'message' => 'Tidak ada yang dipilih']);
            exit;
        }
        
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "UPDATE guru_mengajar_rayhanrp SET hari = NULL, jam_mulai = NULL, jam_selesai = NULL WHERE grup_id = ? AND id IN ($placeholders)";
        
        $params = [$id_grup_rayhanrp, ...$ids];
        $types = 'i' . str_repeat('i', count($ids));
        
        sirey_execute($sql, $types, ...$params);
        
        echo json_encode(['success' => true, 'message' => count($ids) . ' jadwal berhasil dihapus']);
        exit;
    }
}

// Jika bukan AJAX request, lanjutkan dengan page normal
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
$bisa_buat_jadwal_rayhanrp = can('create_jadwal', $data_admin_rayhanrp);
$bisa_update_jadwal_rayhanrp = can('update_jadwal', $data_admin_rayhanrp);
$bisa_hapus_jadwal_rayhanrp = can('delete_jadwal', $data_admin_rayhanrp);
$bisa_tulis_grup_rayhanrp = $bisa_buat_grup_rayhanrp || $bisa_update_grup_rayhanrp || $bisa_hapus_grup_rayhanrp;

$deskripsi_halaman_rayhanrp = match ($data_admin_rayhanrp['role']) {
    'guru' => 'Hanya kelas yang Anda ajar yang ditampilkan. Halaman ini read-only.',
    'kepala_sekolah' => 'Mode baca saja untuk pemantauan grup, anggota, jadwal, dan tugas.',
    default => 'Kelola grup, anggota, dan jadwal per kelas.',
};

// Ambil info admin/pembuat dari session
$id_pembuat_rayhanrp = (int)($_SESSION['admin_id'] ?? 0);

// Proses form tambah atau hapus grup.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi_rayhanrp = (string)($_POST['act'] ?? '');

    if ($aksi_rayhanrp === 'create') {
        $nama_grup_rayhanrp = trim((string)($_POST['nama_grup'] ?? ''));
        $tingkat_rayhanrp = (int)($_POST['tingkat'] ?? 0);
        $jurusan_rayhanrp = (string)($_POST['jurusan'] ?? '');
        $deskripsi_rayhanrp = trim((string)($_POST['deskripsi'] ?? ''));

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
                'INSERT INTO grup_rayhanrp (nama_grup, tingkat, jurusan, deskripsi, pembuat_id) VALUES (?, ?, ?, ?, ?)',
                'sissi',
                $nama_grup_rayhanrp,
                $tingkat_rayhanrp,
                $jurusan_rayhanrp,
                $deskripsi_rayhanrp !== '' ? $deskripsi_rayhanrp : null,
                $id_pembuat_rayhanrp
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
                'UPDATE grup_rayhanrp SET nama_grup = ?, tingkat = ?, jurusan = ?, deskripsi = ? WHERE grup_id = ?',
                'sissi',
                $nama_grup_rayhanrp,
                $tingkat_rayhanrp,
                $jurusan_rayhanrp,
                $deskripsi_rayhanrp !== '' ? $deskripsi_rayhanrp : null,
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
            sirey_execute('DELETE FROM grup_rayhanrp WHERE grup_id = ?', 'i', $id_grup_rayhanrp);
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
                
                $result = sirey_execute('DELETE FROM grup_rayhanrp WHERE grup_id = ?', 'i', $id_item_rayhanrp);
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

// Ambil nilai pencarian dari form (jika ada)
$teks_pencarian_rayhanrp = trim((string)($_POST['search'] ?? ''));



// Ambil data grup beserta jumlah anggota, jadwal, dan tugas.
$sql = 'SELECT g.grup_id, g.nama_grup, g.tingkat, g.jurusan, g.deskripsi, g.pembuat_id, g.aktif, a.nama_lengkap AS pembuat_nama,
                COUNT(DISTINCT ga.akun_id) AS jml_anggota,
                COUNT(DISTINCT gm.id) AS jml_jadwal,
                COUNT(DISTINCT t.tugas_id) AS jml_tugas
         FROM grup_rayhanrp g
         LEFT JOIN akun_rayhanrp a ON g.pembuat_id = a.akun_id
         LEFT JOIN grup_anggota_rayhanrp ga ON g.grup_id = ga.grup_id
         LEFT JOIN guru_mengajar_rayhanrp gm ON g.grup_id = gm.grup_id AND gm.hari IS NOT NULL
         LEFT JOIN tugas_rayhanrp t ON g.grup_id = t.grup_id';

if ($data_admin_rayhanrp['role'] === 'guru') {
    $sql .= ' INNER JOIN guru_mengajar_rayhanrp gm_scope ON g.grup_id = gm_scope.grup_id AND gm_scope.akun_id = ' . (int)$data_admin_rayhanrp['id'] . ' AND gm_scope.aktif = 1';
}

// Build WHERE clause untuk filter pencarian dan tipe grup
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

$sql .= ' GROUP BY g.grup_id, g.nama_grup, g.tingkat, g.jurusan, g.deskripsi, g.pembuat_id, g.aktif, a.nama_lengkap ORDER BY g.nama_grup ASC';

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

    <!-- FORM PENCARIAN & FILTER -->
    <div style="padding:20px; background:#f8f9fa; border-bottom:1px solid #dee2e6;">
      <form method="POST" id="grupSearchForm" style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
        <!-- Input Pencarian -->
        <div style="flex:1; min-width:200px;">
          <label class="form-label" style="display:block; font-size:14px; margin-bottom:6px;">
            🔍 Cari Nama Grup
          </label>
          <input 
            type="text" 
            name="search" 
            class="form-control" 
            placeholder="Ketik nama grup/kelas..."
            value="<?php echo htmlspecialchars($teks_pencarian_rayhanrp); ?>"
            style="width:100%;"
          >
        </div>

        <!-- Filter Tipe Grup dihapus - menggunakan tingkat dan jurusan dari struktur grup -->

        <!-- Tombol Submit & Reset -->
        <div style="display:flex; gap:8px;">
          <button type="submit" class="btn btn-primary" style="padding:8px 16px;">
            🔍 Cari
          </button>
          
          <!-- Tombol Reset -->
          <?php if ($teks_pencarian_rayhanrp !== ''): ?>
            <button type="button" class="btn btn-secondary" onclick="resetSearch()" style="padding:8px 16px;">
              ✕ Reset
            </button>
          <?php endif; ?>
        </div>
      </form>

      <!-- Tampilkan status pencarian -->
      <?php if ($teks_pencarian_rayhanrp !== ''): ?>
        <div style="margin-top:12px; font-size:14px; color:#666;">
          📊 Hasil: 
          mencari "<?php echo htmlspecialchars($teks_pencarian_rayhanrp); ?>"
          (<?php echo count($daftar_grup_rayhanrp); ?> hasil)
        </div>
      <?php endif; ?>
    </div>

    <?php if (empty($daftar_grup_rayhanrp)): ?>
      <div class="empty-state">
        <div class="empty-icon">🎓</div>
        <p><?php echo $teks_pencarian_rayhanrp !== '' ? 'Tidak ada grup yang cocok dengan pencarian Anda.' : 'Belum ada grup.'; ?></p>
      </div>

    <?php else: ?>
      <?php if ($bisa_hapus_grup_rayhanrp): ?>
      <!-- Tombol Hapus Terpilih -->
      <div style="padding:20px; background:#f8f9fa; border-bottom:1px solid #dee2e6;">
        <div style="display:flex; gap:12px; align-items:center;">
          <div id="bulkDeleteSection" style="display:none; flex:1;">
            <span id="selectedCount" style="font-weight:bold; color:#0066cc;">0 grup dipilih</span>
          </div>
          <form method="POST" id="bulkDeleteForm" style="display:none;" onsubmit="return confirm('Hapus grup terpilih? Semua jadwal dan tugas terkait akan ikut terhapus.')">
            <input type="hidden" name="act" value="delete_multiple">
            <div id="selectedIdsContainer"></div>
            <button type="submit" class="btn btn-danger">
              🗑️ Hapus Terpilih
            </button>
          </form>
        </div>
      </div>
      <?php endif; ?>

      <table class="data-table" style="width:100%; border-collapse:collapse;">
        <thead>
          <tr>
            <?php if ($bisa_hapus_grup_rayhanrp): ?>
            <th style="width:40px;"><input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll(this)"></th>
            <?php endif; ?>
            <th>#</th>
            <th>Nama Grup</th>
            <th>Pembuat</th>
            <th>Anggota</th>
            <th>Jadwal</th>
            <th>Tugas</th>
            <th>Status</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($daftar_grup_rayhanrp as $item_grup_rayhanrp): ?>
            <tr>
              <?php if ($bisa_hapus_grup_rayhanrp): ?>
              <td style="width:40px; text-align:center;">
                <input type="checkbox" class="grupCheckbox" value="<?php echo $item_grup_rayhanrp['grup_id']; ?>" 
                       onchange="updateBulkDeleteUI()">
              </td>
              <?php endif; ?>

              <td style="color:var(--clr-muted); vertical-align:middle;">
                <?php echo (int)$item_grup_rayhanrp['grup_id']; ?>
              </td>

              <td style="vertical-align:middle;">
                <strong><?php echo htmlspecialchars($item_grup_rayhanrp['nama_grup']); ?></strong>
                <br><small style="color:var(--clr-muted); display:inline-flex; align-items:center; gap:6px; flex-wrap:wrap;">
                  <span class="badge badge-info">
                    <?php 
                      $tingkat_label = match((int)($item_grup_rayhanrp['tingkat'] ?? 0)) {
                        10 => 'Kelas X',
                        11 => 'Kelas XI',
                        12 => 'Kelas XII',
                        default => 'Kelas X'
                      };
                      echo htmlspecialchars($tingkat_label);
                    ?>
                  </span>
                  <span class="badge badge-secondary">
                    <?php echo htmlspecialchars($item_grup_rayhanrp['jurusan'] ?? '-'); ?>
                  </span>
                  <?php if (!empty($item_grup_rayhanrp['deskripsi'])): ?>
                    <span><?php echo htmlspecialchars((string)$item_grup_rayhanrp['deskripsi']); ?></span>
                  <?php endif; ?>
                </small>
              </td>

              <td style="vertical-align:middle;">
                <small style="color:#666;">
                  <?php echo htmlspecialchars($item_grup_rayhanrp['pembuat_nama'] ?? '-'); ?>
                </small>
              </td>

              <td><?php echo (int)$item_grup_rayhanrp['jml_anggota']; ?> anggota</td>
              <td><?php echo (int)$item_grup_rayhanrp['jml_jadwal']; ?></td>
              <td><?php echo (int)$item_grup_rayhanrp['jml_tugas']; ?></td>

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

              <td style="min-width:320px;">
                <div style="display:flex; flex-wrap:wrap; gap:6px; align-items:center;">
                  <button type="button" class="btn btn-info btn-sm" 
                          onclick="openAnggotaModal(<?php echo (int)$item_grup_rayhanrp['grup_id']; ?>, <?php echo htmlspecialchars(json_encode($item_grup_rayhanrp['nama_grup']), ENT_QUOTES, 'UTF-8'); ?>)">
                    👥 Anggota
                  </button>
                  <button type="button" class="btn btn-warning btn-sm" 
                          onclick="openJadwalModal(<?php echo (int)$item_grup_rayhanrp['grup_id']; ?>, <?php echo htmlspecialchars(json_encode($item_grup_rayhanrp['nama_grup']), ENT_QUOTES, 'UTF-8'); ?>)">
                    📅 Jadwal
                  </button>
                  <button type="button" class="btn btn-success btn-sm" 
                          onclick="openTugasModal(<?php echo (int)$item_grup_rayhanrp['grup_id']; ?>, <?php echo htmlspecialchars(json_encode($item_grup_rayhanrp['nama_grup']), ENT_QUOTES, 'UTF-8'); ?>)">
                    📝 Tugas
                  </button>
                  <?php if ($bisa_update_grup_rayhanrp): ?>
                  <button type="button" class="btn btn-primary btn-sm" 
                          onclick='openEditModal(<?php echo json_encode($item_grup_rayhanrp); ?>)'>
                    ✏️ Edit
                  </button>
                  <?php endif; ?>
                  <?php if ($bisa_hapus_grup_rayhanrp): ?>
                  <form method="POST" style="display:inline" onsubmit="return confirm('Hapus grup ini? Semua jadwal dan tugas terkait akan ikut terhapus.')">
                    <input type="hidden" name="act" value="delete">
                    <input type="hidden" name="id" value="<?php echo (int)$item_grup_rayhanrp['grup_id']; ?>">
                    <button type="submit" class="btn btn-danger btn-sm">
                      🗑️ Hapus
                    </button>
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
          <small style="color:#999;">Masukkan nama grup atau kelas yang jelas dan unik</small>
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

        <div style="margin-top:20px; padding:14px; background:#f0f7ff; border-left:4px solid #0066cc; border-radius:4px;">
          <strong>ℹ️ Tips:</strong> Gunakan format seperti "XII IPA 1", "XI IPS 2", dll untuk kemudahan identifikasi.
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
      <button type="button" onclick="closeEditModal()" style="background:none; border:none; font-size:24px; cursor:pointer; color:#999;">
        ✕
      </button>
    </div>

    <form method="POST" id="editForm">
      <input type="hidden" name="act" value="update">
      <input type="hidden" name="id" id="edit_id" value="">

      <div class="form-group" style="margin-bottom:16px;">
        <label class="form-label">#ID</label>
        <input type="text" id="edit_grup_id" class="form-control" readonly style="background:#f0f0f0; cursor:not-allowed;">
        <small style="color:#999;">Tidak dapat diubah</small>
      </div>

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

      <div style="display:flex; gap:8px;">
        <button type="submit" class="btn btn-primary" style="flex:1;">
          💾 Simpan
        </button>
        <button type="button" class="btn btn-secondary" onclick="closeEditModal()" style="flex:1;">
          Batal
        </button>
      </div>
    </form>
  </div>
</div>


<!-- ================== ANGGOTA MODAL ================== -->
<div id="anggotaModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center; overflow-y:auto;">
  <div style="background:white; padding:30px; border-radius:8px; max-width:900px; width:92%; margin:20px auto;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
      <h3 style="margin:0;">👥 Anggota Grup: <span id="anggota_grup_name"></span></h3>
      <button type="button" onclick="closeAnggotaModal()" style="background:none; border:none; font-size:24px; cursor:pointer; color:#999;">
        ✕
      </button>
    </div>

    <div id="anggotaContent" style="max-height:70vh; overflow-y:auto;">
      <p style="text-align:center; color:#999;">Memuat data...</p>
    </div>
  </div>
</div>


<!-- ================== JADWAL MODAL ================== -->
<div id="jadwalModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center; overflow-y:auto;">
  <div style="background:white; padding:30px; border-radius:8px; max-width:900px; width:92%; margin:20px auto;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
      <h3 style="margin:0;">📅 Jadwal Grup: <span id="jadwal_grup_name"></span></h3>
      <button type="button" onclick="closeJadwalModal()" style="background:none; border:none; font-size:24px; cursor:pointer; color:#999;">
        ✕
      </button>
    </div>

    <div id="jadwalContent" style="max-height:70vh; overflow-y:auto;">
      <p style="text-align:center; color:#999;">Memuat data...</p>
    </div>
  </div>
</div>


<!-- ================== TUGAS MODAL ================== -->
<div id="tugasModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center; overflow-y:auto;">
  <div style="background:white; padding:30px; border-radius:8px; max-width:900px; width:92%; margin:20px auto;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
      <h3 style="margin:0;">📝 Tugas Grup: <span id="tugas_grup_name"></span></h3>
      <button type="button" onclick="closeTugasModal()" style="background:none; border:none; font-size:24px; cursor:pointer; color:#999;">
        ✕
      </button>
    </div>

    <div id="tugasContent" style="max-height:70vh; overflow-y:auto;">
      <p style="text-align:center; color:#999;">Memuat data...</p>
    </div>
  </div>
</div>

<!-- ================== EDIT JADWAL MODAL ================== -->
<div id="editJadwalInGrupModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1001; align-items:center; justify-content:center; overflow-y:auto;">
  <div style="background:white; padding:30px; border-radius:8px; max-width:500px; width:92%; margin:20px auto;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
      <h3 style="margin:0;">✏️ Edit Jadwal</h3>
      <button type="button" onclick="closeEditJadwalInGroup()" style="background:none; border:none; font-size:24px; cursor:pointer; color:#999;">
        ✕
      </button>
    </div>

    <form id="editJadwalForm" style="display:flex; flex-direction:column; gap:15px;">
      <input type="hidden" id="edit_jadwal_id" value="">
      <input type="hidden" id="edit_jadwal_grup_id" value="">

      <div>
        <label style="display:block; margin-bottom:5px; font-weight:bold; font-size:14px;">Judul Jadwal</label>
        <input type="text" id="edit_jadwal_judul" placeholder="Masukkan judul jadwal" 
               style="width:100%; padding:10px; border:1px solid #ddd; border-radius:4px; box-sizing:border-box; font-size:14px;">
      </div>

      <div>
        <label style="display:block; margin-bottom:5px; font-weight:bold; font-size:14px;">Hari</label>
        <select id="edit_jadwal_hari" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:4px; box-sizing:border-box; font-size:14px;">
          <option value="">-- Pilih Hari --</option>
          <option value="Senin">Senin</option>
          <option value="Selasa">Selasa</option>
          <option value="Rabu">Rabu</option>
          <option value="Kamis">Kamis</option>
          <option value="Jumat">Jumat</option>
          <option value="Sabtu">Sabtu</option>
          <option value="Minggu">Minggu</option>
        </select>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
        <div>
          <label style="display:block; margin-bottom:5px; font-weight:bold; font-size:14px;">Jam Mulai</label>
          <input type="time" id="edit_jadwal_jam_mulai" 
                 style="width:100%; padding:10px; border:1px solid #ddd; border-radius:4px; box-sizing:border-box; font-size:14px;">
        </div>
        <div>
          <label style="display:block; margin-bottom:5px; font-weight:bold; font-size:14px;">Jam Selesai</label>
          <input type="time" id="edit_jadwal_jam_selesai" 
                 style="width:100%; padding:10px; border:1px solid #ddd; border-radius:4px; box-sizing:border-box; font-size:14px;">
        </div>
      </div>

      <div id="editJadwalError" style="display:none; background:#ffe6e6; border:1px solid #ffcccc; padding:10px; border-radius:4px; color:#c00; font-size:14px;">
        <strong>Peringatan:</strong> <span id="editJadwalErrorMsg"></span>
      </div>

      <div style="display:flex; gap:10px; justify-content:flex-end;">
        <button type="button" onclick="closeEditJadwalInGroup()" 
                style="background:#6c757d; color:white; border:none; padding:10px 20px; border-radius:4px; cursor:pointer; font-weight:bold;">
          Batal
        </button>
        <button type="button" onclick="submitEditJadwal()" 
                style="background:#28a745; color:white; border:none; padding:10px 20px; border-radius:4px; cursor:pointer; font-weight:bold;">
          💾 Simpan
        </button>
      </div>
    </form>
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

  function buildAddJadwalForm(grupId) {
    // Jadwal di tab grup hanya untuk viewing, CRUD dilakukan di halaman Guru Mengajar
    return buildReadonlyNote('📋 <strong>Tab ini bersifat read-only.</strong> Untuk menambah atau mengubah jadwal, silakan gunakan menu <strong>Guru Mengajar</strong>.');
  }

  // ===== MAIN TAB SWITCHING (Daftar vs Buat) =====
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

  // Set main tab daftar sebagai default
  switchMainTab('daftar');

  // ===== EDIT MODAL =====
  function openEditModal(group) {
    document.getElementById('edit_id').value = group.grup_id;
    document.getElementById('edit_grup_id').value = group.grup_id;
    document.getElementById('edit_nama_grup').value = group.nama_grup;
    document.getElementById('edit_tingkat').value = group.tingkat || '0';
    document.getElementById('edit_jurusan').value = group.jurusan || '';
    document.getElementById('edit_deskripsi').value = group.deskripsi || '';
    document.getElementById('editModal').style.display = 'flex';
  }

  function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
  }

  // Close modal when clicking outside
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
    console.log('=== openAnggotaModal called ===');
    console.log('grupId:', grupId, 'type:', typeof grupId);
    console.log('grupName:', grupName, 'type:', typeof grupName);
    
    const modal = document.getElementById('anggotaModal');
    const nameEl = document.getElementById('anggota_grup_name');
    const contentEl = document.getElementById('anggotaContent');
    
    console.log('Modal elements:', { modal: !!modal, nameEl: !!nameEl, contentEl: !!contentEl });
    
    if (!modal || !nameEl || !contentEl) {
      console.error('❌ Modal elements not found!', { modal, nameEl, contentEl });
      return;
    }
    
    nameEl.textContent = grupName;
    contentEl.innerHTML = '<p style="text-align:center; color:#999;">Memuat data...</p>';
    modal.style.display = 'flex';
    console.log('✅ Modal displayed, preparing fetch...');
    
    // Fetch data anggota - gunakan path yang eksplisit
    const fetchUrl = './grup.php?action=get_members&grup_id=' + grupId;
    console.log('📡 Fetching from:', fetchUrl);
    
    fetch(fetchUrl)
      .then(response => {
        console.log('📩 Response received - Status:', response.status, 'OK:', response.ok);
        if (!response.ok) {
          throw new Error('HTTP ' + response.status + ' ' + response.statusText);
        }
        return response.text();
      })
      .then(html => {
        console.log('📄 HTML received - Length:', html.length, 'First 100 chars:', html.substring(0, 100));
        if (html.trim()) {
          if (!grupPagePermissions.canManageMembers) {
            contentEl.innerHTML = html + buildReadonlyNote('Mode baca saja. Penambahan atau penghapusan anggota hanya tersedia untuk admin dan kurikulum.');
            return Promise.resolve();
          }

          // Fetch available users
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
              console.log('✅ Content updated with add form');
            });
        } else {
          contentEl.innerHTML = '<p style="text-align:center; color:#999; padding:20px;">Tidak ada data</p>';
          console.log('⚠️ Empty response');
        }
      })
      .catch(error => {
        console.error('❌ Fetch error:', error);
        contentEl.innerHTML = '<p style="color:red;">❌ Error memuat data: ' + error.message + '</p>';
      });
  }

  function closeAnggotaModal() {
    const modal = document.getElementById('anggotaModal');
    if (modal) {
      modal.style.display = 'none';
    }
  }

  // Event delegation untuk close modal
  (function() {
    const modal = document.getElementById('anggotaModal');
    if (modal) {
      modal.addEventListener('click', function(e) {
        if (e.target === this) {
          closeAnggotaModal();
        }
      });
    }
  })();

  // ===== JADWAL MODAL =====
  function openJadwalModal(grupId, grupName) {
    console.log('=== openJadwalModal called ===');
    console.log('grupId:', grupId, 'type:', typeof grupId);
    console.log('grupName:', grupName, 'type:', typeof grupName);
    
    const modal = document.getElementById('jadwalModal');
    const nameEl = document.getElementById('jadwal_grup_name');
    const contentEl = document.getElementById('jadwalContent');
    
    console.log('Modal elements:', { modal: !!modal, nameEl: !!nameEl, contentEl: !!contentEl });
    
    if (!modal || !nameEl || !contentEl) {
      console.error('❌ Modal elements not found!', { modal, nameEl, contentEl });
      return;
    }
    
    nameEl.textContent = grupName;
    contentEl.innerHTML = '<p style="text-align:center; color:#999;">Memuat data...</p>';
    modal.style.display = 'flex';
    console.log('✅ Modal displayed, preparing fetch...');
    
    // Fetch data jadwal
    const fetchUrl = './grup.php?action=get_jadwal&grup_id=' + grupId;
    console.log('📡 Fetching from:', fetchUrl);
    
    fetch(fetchUrl)
      .then(response => {
        console.log('📩 Response received - Status:', response.status, 'OK:', response.ok);
        if (!response.ok) {
          throw new Error('HTTP ' + response.status + ' ' + response.statusText);
        }
        return response.text();
      })
      .then(html => {
        console.log('📄 HTML received - Length:', html.length, 'First 100 chars:', html.substring(0, 100));
        
        // Jadwal tab bersifat read-only untuk semua role
        // CRUD dilakukan di menu Guru Mengajar
        let fullContent = html;
        fullContent += buildReadonlyNote('📋 <strong>Tab ini bersifat read-only.</strong> Untuk menambah atau mengubah jadwal, silakan gunakan menu <strong>Guru Mengajar</strong>.');
        
        contentEl.innerHTML = fullContent;
        console.log('✅ Content updated');
      })
      .catch(error => {
        console.error('❌ Fetch error:', error);
        contentEl.innerHTML = '<p style="color:red;">❌ Error memuat data: ' + error.message + '</p>';
      });
  }

  function submitAddJadwal(event, grupId) {
    event.preventDefault();
    
    const form = document.getElementById('addJadwalForm');
    const judul = document.getElementById('jadwalJudul').value.trim();
    const hari = document.getElementById('jadwalHari').value;
    const jamMulai = document.getElementById('jadwalMulai').value;
    const jamSelesai = document.getElementById('jadwalSelesai').value;
    
    if (!judul || !hari || !jamMulai || !jamSelesai) {
      alert('Semua field harus diisi');
      return;
    }
    
    if (jamSelesai <= jamMulai) {
      alert('Jam selesai harus lebih besar dari jam mulai');
      return;
    }
    
    const formData = new FormData();
    formData.append('judul', judul);
    formData.append('hari', hari);
    formData.append('jam_mulai', jamMulai);
    formData.append('jam_selesai', jamSelesai);
    
    fetch('./grup.php?action=add_jadwal&grup_id=' + grupId, {
      method: 'POST',
      body: formData
    })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          console.log('✅ Jadwal added');
          // Reset form
          form.reset();
          // Reload modal
          const grupNameEl = document.getElementById('jadwal_grup_name');
          if (grupNameEl) {
            filterJadwal('', grupId);
          }
        } else {
          alert('Error: ' + data.message);
        }
      })
      .catch(error => {
        console.error('❌ Error:', error);
        alert('Error menambah jadwal');
      });
  }

  function closeJadwalModal() {
    const modal = document.getElementById('jadwalModal');
    if (modal) {
      modal.style.display = 'none';
    }
  }

  (function() {
    const modal = document.getElementById('jadwalModal');
    if (modal) {
      modal.addEventListener('click', function(e) {
        if (e.target === this) {
          closeJadwalModal();
        }
      });
    }
  })();

  // ===== TUGAS MODAL =====
  function openTugasModal(grupId, grupName) {
    console.log('=== openTugasModal called ===');
    console.log('grupId:', grupId, 'type:', typeof grupId);
    console.log('grupName:', grupName, 'type:', typeof grupName);
    
    const modal = document.getElementById('tugasModal');
    const nameEl = document.getElementById('tugas_grup_name');
    const contentEl = document.getElementById('tugasContent');
    
    console.log('Modal elements:', { modal: !!modal, nameEl: !!nameEl, contentEl: !!contentEl });
    
    if (!modal || !nameEl || !contentEl) {
      console.error('❌ Modal elements not found!', { modal, nameEl, contentEl });
      return;
    }
    
    nameEl.textContent = grupName;
    contentEl.innerHTML = '<p style="text-align:center; color:#999;">Memuat data...</p>';
    modal.style.display = 'flex';
    console.log('✅ Modal displayed, preparing fetch...');
    
    // Fetch data tugas
    const fetchUrl = './grup.php?action=get_tugas&grup_id=' + grupId;
    console.log('📡 Fetching from:', fetchUrl);
    
    fetch(fetchUrl)
      .then(response => {
        console.log('📩 Response received - Status:', response.status, 'OK:', response.ok);
        if (!response.ok) {
          throw new Error('HTTP ' + response.status + ' ' + response.statusText);
        }
        return response.text();
      })
      .then(html => {
        console.log('📄 HTML received - Length:', html.length, 'First 100 chars:', html.substring(0, 100));
        
        // Tugas tab bersifat read-only untuk semua role
        // CRUD dilakukan di menu Tugas oleh Guru
        let fullContent = html;
        fullContent += buildReadonlyNote('📋 <strong>Tab ini bersifat read-only.</strong> Untuk menambah atau mengubah tugas, silakan gunakan menu <strong>Tugas</strong>.');
        
        contentEl.innerHTML = fullContent;
        console.log('✅ Content updated');
      })
      .catch(error => {
        console.error('❌ Fetch error:', error);
        contentEl.innerHTML = '<p style="color:red;">❌ Error memuat data: ' + error.message + '</p>';
      });
  }

  function closeTugasModal() {
    const modal = document.getElementById('tugasModal');
    if (modal) {
      modal.style.display = 'none';
    }
  }

  (function() {
    const modal = document.getElementById('tugasModal');
    if (modal) {
      modal.addEventListener('click', function(e) {
        if (e.target === this) {
          closeTugasModal();
        }
      });
    }
  })();

  // ===== EDIT JADWAL IN GROUP MODAL =====
  function editJadwalInGrup(jadwalData, grupId) {
    console.log('editJadwalInGrup called with:', jadwalData, grupId);
    
    const modal = document.getElementById('editJadwalInGrupModal');
    if (!modal) {
      console.error('❌ editJadwalInGrupModal not found');
      return;
    }
    
    // Helper function to convert time format HH:MM:SS to HH:MM
    function formatTimeInput(timeStr) {
      if (!timeStr) return '';
      // If format is HH:MM:SS, take only HH:MM
      if (timeStr.includes(':')) {
        const parts = timeStr.split(':');
        return parts[0] + ':' + parts[1]; // HH:MM
      }
      return timeStr;
    }
    
    // Populate form
    document.getElementById('edit_jadwal_id').value = jadwalData.jadwal_id || '';
    document.getElementById('edit_jadwal_grup_id').value = grupId || '';
    document.getElementById('edit_jadwal_judul').value = jadwalData.judul || '';
    document.getElementById('edit_jadwal_hari').value = jadwalData.hari || '';
    document.getElementById('edit_jadwal_jam_mulai').value = formatTimeInput(jadwalData.jam_mulai || '');
    document.getElementById('edit_jadwal_jam_selesai').value = formatTimeInput(jadwalData.jam_selesai || '');
    
    // Hide error message
    document.getElementById('editJadwalError').style.display = 'none';
    
    // Show modal
    modal.style.display = 'flex';
    console.log('✅ Edit jadwal modal opened');
  }

  function closeEditJadwalInGroup() {
    const modal = document.getElementById('editJadwalInGrupModal');
    if (modal) {
      modal.style.display = 'none';
    }
  }

  (function() {
    const modal = document.getElementById('editJadwalInGrupModal');
    if (modal) {
      modal.addEventListener('click', function(e) {
        if (e.target === this) {
          closeEditJadwalInGroup();
        }
      });
    }
  })();

  function submitEditJadwal() {
    const jadwalId = document.getElementById('edit_jadwal_id').value;
    const grupId = document.getElementById('edit_jadwal_grup_id').value;
    const judul = document.getElementById('edit_jadwal_judul').value.trim();
    const hari = document.getElementById('edit_jadwal_hari').value;
    const jamMulai = document.getElementById('edit_jadwal_jam_mulai').value;
    const jamSelesai = document.getElementById('edit_jadwal_jam_selesai').value;
    
    const errorDiv = document.getElementById('editJadwalError');
    const errorMsg = document.getElementById('editJadwalErrorMsg');
    
    // Validation
    if (!judul) {
      errorMsg.textContent = 'Judul jadwal tidak boleh kosong';
      errorDiv.style.display = 'block';
      return;
    }
    
    if (!hari) {
      errorMsg.textContent = 'Hari harus dipilih';
      errorDiv.style.display = 'block';
      return;
    }
    
    if (!jamMulai) {
      errorMsg.textContent = 'Jam mulai harus diisi';
      errorDiv.style.display = 'block';
      return;
    }
    
    if (!jamSelesai) {
      errorMsg.textContent = 'Jam selesai harus diisi';
      errorDiv.style.display = 'block';
      return;
    }
    
    if (jamSelesai <= jamMulai) {
      errorMsg.textContent = 'Jam selesai harus lebih besar dari jam mulai';
      errorDiv.style.display = 'block';
      return;
    }
    
    errorDiv.style.display = 'none';
    
    // Submit
    const formData = new FormData();
    formData.append('jadwal_id', jadwalId);
    formData.append('judul', judul);
    formData.append('hari', hari);
    formData.append('jam_mulai', jamMulai);
    formData.append('jam_selesai', jamSelesai);
    
    fetch('./grup.php?action=update_jadwal&grup_id=' + grupId, {
      method: 'POST',
      body: formData
    })
      .then(response => {
        console.log('Response status:', response.status);
        if (!response.ok) {
          throw new Error('HTTP ' + response.status + ' ' + response.statusText);
        }
        return response.text();
      })
      .then(text => {
        console.log('Response text:', text);
        try {
          const data = JSON.parse(text);
          if (data.success) {
            console.log('✅ Jadwal updated');
            closeEditJadwalInGroup();
            // Reload jadwal modal
            const jadwalModal = document.getElementById('jadwalModal');
            if (jadwalModal && jadwalModal.style.display === 'flex') {
              openJadwalModal(grupId, document.getElementById('jadwal_grup_name').textContent);
            }
          } else {
            errorMsg.textContent = data.message || 'Gagal update jadwal';
            errorDiv.style.display = 'block';
          }
        } catch (parseError) {
          console.error('❌ JSON Parse Error:', parseError);
          console.error('Raw response:', text);
          errorMsg.textContent = 'Error: Server response tidak valid - ' + text.substring(0, 100);
          errorDiv.style.display = 'block';
        }
      })
      .catch(error => {
        console.error('❌ Fetch Error:', error);
        errorMsg.textContent = 'Error: ' + error.message;
        errorDiv.style.display = 'block';
      });
  }

  // ===== DELETE & ADD MEMBER/JADWAL FUNCTIONS =====
  function deleteMemberFromGroup(akunId, grupId, memberName) {
    if (!confirm('Yakin ingin menghapus ' + memberName + ' dari grup ini?')) {
      return;
    }
    
    fetch('./grup.php?action=delete_member&grup_id=' + grupId + '&akun_id=' + akunId)
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          console.log('✅ Member deleted:', memberName);
          // Reload modal
          const modal = document.getElementById('anggotaModal');
          if (modal && modal.style.display === 'flex') {
            // Get grup name from modal header
            const grupNameEl = document.getElementById('anggota_grup_name');
            if (grupNameEl) {
              openAnggotaModal(grupId, grupNameEl.textContent);
            }
          }
        } else {
          alert('Error: ' + data.message);
        }
      })
      .catch(error => {
        console.error('❌ Error:', error);
        alert('Error menghapus anggota');
      });
  }

  function deleteJadwalFromGroup(jadwalId, grupId, jadwalTitle) {
    if (!confirm('Yakin ingin menghapus jadwal "' + jadwalTitle + '"? Data jadwal akan terhapus sepenuhnya.')) {
      return;
    }
    
    fetch('./grup.php?action=delete_jadwal&grup_id=' + grupId + '&jadwal_id=' + jadwalId)
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          console.log('✅ Jadwal deleted:', jadwalTitle);
          // Reload modal
          const modal = document.getElementById('jadwalModal');
          if (modal && modal.style.display === 'flex') {
            const grupNameEl = document.getElementById('jadwal_grup_name');
            if (grupNameEl) {
              openJadwalModal(grupId, grupNameEl.textContent);
            }
          }
        } else {
          alert('Error: ' + data.message);
        }
      })
      .catch(error => {
        console.error('❌ Error:', error);
        alert('Error menghapus jadwal');
      });
  }

  function addMemberToGroup(grupId) {
    const akunId = document.getElementById('addMemberSelect').value;
    
    if (!akunId || akunId === '') {
      alert('Pilih anggota terlebih dahulu');
      return;
    }
    
    fetch('./grup.php?action=add_member&grup_id=' + grupId + '&akun_id=' + akunId)
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          console.log('✅ Member added');
          document.getElementById('addMemberSelect').value = '';
          // Reload modal
          const modal = document.getElementById('anggotaModal');
          if (modal && modal.style.display === 'flex') {
            const grupNameEl = document.getElementById('anggota_grup_name');
            if (grupNameEl) {
              openAnggotaModal(grupId, grupNameEl.textContent);
            }
          }
        } else {
          alert('Error: ' + data.message);
        }
      })
      .catch(error => {
        console.error('❌ Error:', error);
        alert('Error menambah anggota');
      });
  }

  // ===== FILTER & SEARCH FUNCTIONS =====
  function filterMembers(searchTerm, grupId) {
    const searchEl = document.getElementById('searchMembers');
    if (searchEl) {
      searchEl.value = searchTerm;
    }
    // Reload modal dengan parameter search
    const modal = document.getElementById('anggotaModal');
    if (modal && modal.style.display === 'flex') {
      const grupNameEl = document.getElementById('anggota_grup_name');
      if (grupNameEl) {
        const contentEl = document.getElementById('anggotaContent');
        const url = './grup.php?action=get_members&grup_id=' + grupId + '&search=' + encodeURIComponent(searchTerm);
        
        fetch(url)
          .then(r => r.text())
          .then(html => {
            let fullContent = html;
            // Fetch available users only if no search
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
                fullContent += buildReadonlyNote('Mode baca saja. Penambahan atau penghapusan anggota hanya tersedia untuk admin dan kurikulum.');
              }
              contentEl.innerHTML = fullContent;
              return Promise.resolve();
            }
          })
          .catch(error => {
            console.error('❌ Fetch error:', error);
            contentEl.innerHTML = '<p style="color:red;">Error loading data</p>';
          });
      }
    }
  }

  function filterJadwal(searchTerm, grupId) {
    const searchEl = document.getElementById('searchJadwal');
    if (searchEl) {
      searchEl.value = searchTerm;
    }
    const modal = document.getElementById('jadwalModal');
    if (modal && modal.style.display === 'flex') {
      const grupNameEl = document.getElementById('jadwal_grup_name');
      if (grupNameEl) {
        const contentEl = document.getElementById('jadwalContent');
        const url = './grup.php?action=get_jadwal&grup_id=' + grupId + '&search=' + encodeURIComponent(searchTerm);
        
        fetch(url)
          .then(r => r.text())
          .then(html => {
            let fullContent = html;
            if (!searchTerm) {
              // Add form to create new jadwal only when not searching
              fullContent += '<hr style="margin:20px 0; border:none; border-top:2px solid #dee2e6;">';
              fullContent += '<div style="margin-top:20px;">';
              fullContent += '<h4 style="margin-top:0; margin-bottom:15px;">➕ Tambah Jadwal Baru</h4>';
              fullContent += '<form id="addJadwalForm" onsubmit="submitAddJadwal(event, ' + grupId + ')" style="display:grid; gap:10px;">';
              fullContent += '<div><label style="display:block; margin-bottom:5px; font-weight:bold;">Judul Jadwal:</label>';
              fullContent += '<input type="text" id="jadwalJudul" name="judul" required style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px; box-sizing:border-box;"></div>';
              fullContent += '<div><label style="display:block; margin-bottom:5px; font-weight:bold;">Hari:</label>';
              fullContent += '<select id="jadwalHari" name="hari" required style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px; box-sizing:border-box;">';
              fullContent += '<option value="">-- Pilih Hari --</option>';
              fullContent += '<option value="Senin">Senin</option><option value="Selasa">Selasa</option><option value="Rabu">Rabu</option>';
              fullContent += '<option value="Kamis">Kamis</option><option value="Jumat">Jumat</option><option value="Sabtu">Sabtu</option><option value="Minggu">Minggu</option>';
              fullContent += '</select></div>';
              fullContent += '<div><label style="display:block; margin-bottom:5px; font-weight:bold;">Jam Mulai:</label>';
              fullContent += '<input type="time" id="jadwalMulai" name="jam_mulai" required style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px; box-sizing:border-box;"></div>';
              fullContent += '<div><label style="display:block; margin-bottom:5px; font-weight:bold;">Jam Selesai:</label>';
              fullContent += '<input type="time" id="jadwalSelesai" name="jam_selesai" required style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px; box-sizing:border-box;"></div>';
              fullContent += '<button type="submit" style="background:#28a745; color:white; border:none; padding:10px; border-radius:4px; cursor:pointer; font-weight:bold;">✓ Tambah Jadwal</button>';
              fullContent += '</form>';
              fullContent += '</div>';
            }
            contentEl.innerHTML = fullContent;
            if (!grupPagePermissions.canCreateJadwal) {
              const addJadwalForm = contentEl.querySelector('#addJadwalForm');
              if (addJadwalForm && addJadwalForm.parentElement) {
                addJadwalForm.parentElement.outerHTML = buildReadonlyNote('Mode baca saja. Penambahan jadwal hanya tersedia untuk admin dan kurikulum.');
              }
            }
          })
          .catch(error => {
            console.error('❌ Fetch error:', error);
            contentEl.innerHTML = '<p style="color:red;">Error loading data</p>';
          });
      }
    }
  }

  function filterTugas(searchTerm, grupId) {
    const searchEl = document.getElementById('searchTugas');
    if (searchEl) {
      searchEl.value = searchTerm;
    }
    const modal = document.getElementById('tugasModal');
    if (modal && modal.style.display === 'flex') {
      const grupNameEl = document.getElementById('tugas_grup_name');
      if (grupNameEl) {
        const contentEl = document.getElementById('tugasContent');
        const url = './grup.php?action=get_tugas&grup_id=' + grupId + '&search=' + encodeURIComponent(searchTerm);
        
        fetch(url)
          .then(r => r.text())
          .then(html => {
            let fullContent = html;
            fullContent += buildReadonlyNote('📋 <strong>Tab ini bersifat read-only.</strong> Untuk menambah atau mengubah tugas, silakan gunakan menu <strong>Tugas</strong>.');
            contentEl.innerHTML = fullContent;
          })
          .catch(error => {
            console.error('❌ Fetch error:', error);
            contentEl.innerHTML = '<p style="color:red;">Error loading data</p>';
          });
      }
    }
  }

  // ===== BULK SELECT FUNCTIONS =====
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

    if (!selectAllCheckbox || !bulkSection || !selectedCount) {
      return;
    }

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
    
    if (ids.length === 0) {
      alert('Tidak ada anggota yang dipilih');
      return;
    }
    
    if (!confirm('Yakin ingin menghapus ' + ids.length + ' anggota dari grup?')) {
      return;
    }
    
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
          console.log('✅ Members deleted:', data.message);
          // Reload modal
          const grupNameEl = document.getElementById('anggota_grup_name');
          if (grupNameEl) {
            openAnggotaModal(grupId, grupNameEl.textContent);
          }
        } else {
          alert('Error: ' + data.message);
        }
      })
      .catch(error => {
        console.error('❌ Error:', error);
        alert('Error menghapus anggota');
      });
  }

  function toggleSelectAllJadwal(checkbox, grupId) {
    const allCheckboxes = document.querySelectorAll('.jadwalCheckbox');
    allCheckboxes.forEach(cb => {
      cb.checked = checkbox.checked;
    });
    updateJadwalBulkUI(grupId);
  }

  function updateJadwalBulkUI(grupId) {
    const allCheckboxes = document.querySelectorAll('.jadwalCheckbox');
    const selectedCheckboxes = document.querySelectorAll('.jadwalCheckbox:checked');
    const selectAllCheckbox = document.getElementById('selectAllJadwal');
    const bulkSection = document.getElementById('bulkDeleteJadwalSection');
    const selectedCount = document.getElementById('jadwalSelectedCount');

    if (!selectAllCheckbox || !bulkSection || !selectedCount) {
      return;
    }

    selectAllCheckbox.checked = selectedCheckboxes.length > 0 && selectedCheckboxes.length === allCheckboxes.length;
    selectAllCheckbox.indeterminate = selectedCheckboxes.length > 0 && selectedCheckboxes.length < allCheckboxes.length;

    if (selectedCheckboxes.length > 0) {
      bulkSection.style.display = 'block';
      selectedCount.textContent = selectedCheckboxes.length + ' jadwal dipilih';
    } else {
      bulkSection.style.display = 'none';
    }
  }

  function bulkDeleteJadwal(grupId) {
    const selectedCheckboxes = document.querySelectorAll('.jadwalCheckbox:checked');
    const ids = Array.from(selectedCheckboxes).map(cb => cb.value);
    
    if (ids.length === 0) {
      alert('Tidak ada jadwal yang dipilih');
      return;
    }
    
    if (!confirm('Yakin ingin menghapus ' + ids.length + ' jadwal dari grup? Data jadwal akan terhapus sepenuhnya.')) {
      return;
    }
    
    const formData = new FormData();
    ids.forEach(id => {
      formData.append('jadwal_ids[]', id);
    });
    
    fetch('./grup.php?action=bulk_delete_jadwal&grup_id=' + grupId, {
      method: 'POST',
      body: formData
    })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          console.log('✅ Jadwal deleted:', data.message);
          // Reload modal
            const grupNameEl = document.getElementById('jadwal_grup_name');
          if (grupNameEl) {
            openJadwalModal(grupId, grupNameEl.textContent);
          }
        } else {
          alert('Error: ' + data.message);
        }
      })
      .catch(error => {
        console.error('❌ Error:', error);
        alert('Error menghapus jadwal');
      });
  }

  // ===== TOGGLE GROUP STATUS FUNCTION =====
  function toggleGrupStatus(grupId, currentStatus) {
    const newStatus = currentStatus === 1 ? 0 : 1;
    const statusLabel = newStatus === 1 ? 'Aktif' : 'Non-Aktif';
    
    if (!confirm('Ubah status grup menjadi ' + statusLabel + '?')) {
      return;
    }

    const formData = new FormData();
    formData.append('status', newStatus);

    fetch('./grup.php?action=toggle_status&grup_id=' + grupId, {
      method: 'POST',
      body: formData
    })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          alert('✅ ' + data.message);
          // Reload page untuk update status
          location.reload();
        } else {
          alert('❌ Error: ' + data.message);
        }
      })
      .catch(error => {
        console.error('❌ Error:', error);
        alert('Error mengubah status grup');
      });
  }

  // ===== CHECKBOX FUNCTIONS =====
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

    if (!selectAllCheckbox || !bulkDeleteSection || !bulkDeleteForm || !selectedCount || !selectedIdsContainer) {
      return;
    }

    // Update select all checkbox state
    selectAllCheckbox.checked = selectedCheckboxes.length > 0 && selectedCheckboxes.length === allCheckboxes.length;
    selectAllCheckbox.indeterminate = selectedCheckboxes.length > 0 && selectedCheckboxes.length < allCheckboxes.length;

    if (selectedCheckboxes.length > 0) {
      bulkDeleteSection.style.display = 'block';
      bulkDeleteForm.style.display = 'block';
      selectedCount.textContent = selectedCheckboxes.length + ' grup dipilih';

      // Update hidden inputs
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
    // Kosongkan input pencarian
    document.querySelector('input[name="search"]').value = '';
    
    // Submit form untuk reload tabel
    document.getElementById('grupSearchForm')?.submit();
  }
</script>

<?php layoutEnd(); ?>
