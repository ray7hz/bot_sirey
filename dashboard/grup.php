<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

startSession();

$admin = [
    'id'   => (int)($_SESSION['admin_id'] ?? 0),
    'role' => (string)($_SESSION['admin_role'] ?? ''),
    'name' => (string)($_SESSION['admin_name'] ?? ''),
];

// ═══ AJAX HANDLERS ═══
$aksi_ajax = (string)($_GET['action'] ?? '');
$id_grup   = (int)($_GET['grup_id'] ?? 0);

if ($aksi_ajax !== '') {
    if ($admin['id'] <= 0 || !can('view_grup', $admin)) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
        exit;
    }

    $bisa_kelola = can('update_grup', $admin);

    // ── GET MEMBERS ──────────────────────────────────────────────
    if ($aksi_ajax === 'get_members') {
        header('Content-Type: text/html; charset=utf-8');
        $cari = trim((string)($_GET['search'] ?? ''));
        $sql  = "SELECT a.akun_id,a.nis_nip,a.nama_lengkap,a.role,a.jenis_kelamin
                 FROM akun_rayhanrp a JOIN grup_anggota_rayhanrp ga ON a.akun_id=ga.akun_id
                 WHERE ga.grup_id=?";
        $types = 'i'; $params = [$id_grup];
        if ($cari !== '') {
            $sql .= " AND (a.nis_nip LIKE ? OR a.nama_lengkap LIKE ?)";
            $types .= 'ss'; $params[] = "%$cari%"; $params[] = "%$cari%";
        }
        $sql .= " ORDER BY a.nama_lengkap ASC";
        $rows = sirey_fetchAll(sirey_query($sql, $types, ...$params));
        
        // Render member list directly
        if (empty($rows)) {
            echo '<p class="text-center text-muted py-4">Belum ada anggota.</p>';
        } else {
            echo '<div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead><tr>';
            echo '<th>NIS/NIP</th><th>Nama</th><th>Role</th><th style="width:80px;" class="text-center">Aksi</th>';
            echo '</tr></thead><tbody>';
            foreach ($rows as $m) {
                echo '<tr>';
                echo '<td><code style="font-size:11px;">'.htmlspecialchars($m['nis_nip']).'</code></td>';
                echo '<td>'.htmlspecialchars($m['nama_lengkap']).'</td>';
                echo '<td><span class="badge '.(match($m['role']) {
                    'admin' => 'bg-danger',
                    'guru' => 'bg-success',
                    'kurikulum' => 'bg-primary',
                    'kepala_sekolah' => 'bg-info text-dark',
                    default => 'bg-secondary'
                }).'">'.htmlspecialchars($m['role']).'</span></td>';
                echo '<td class="text-center">';
                if ($bisa_kelola) {
                    echo '<button class="btn btn-sm btn-outline-danger" onclick="deleteMember('.(int)$m['akun_id'].',\''.addslashes($m['nama_lengkap']).'\')">'; 
                    echo '<i class="bi bi-trash"></i></button>';
                }
                echo '</td></tr>';
            }
            echo '</tbody></table></div>';
        }
        exit;
    }

    // ── GET AVAILABLE USERS (checkbox untuk bulk add) ──────────────
    if ($aksi_ajax === 'get_available_users') {
        header('Content-Type: text/html; charset=utf-8');
        if (!$bisa_kelola) { echo ''; exit; }
        $rows = sirey_fetchAll(sirey_query(
            "SELECT a.akun_id,a.nis_nip,a.nama_lengkap,a.jenis_kelamin FROM akun_rayhanrp a
             WHERE a.akun_id NOT IN (SELECT akun_id FROM grup_anggota_rayhanRP WHERE grup_id=?)
             ORDER BY a.nama_lengkap ASC", 'i', $id_grup
        ));
        if (empty($rows)) { echo '<p class="text-muted fst-italic small">Semua pengguna sudah menjadi anggota grup.</p>'; exit; }
        echo '<div class="row g-2" id="availableUsersContainer">';
        foreach ($rows as $u) {
            $icon = $u['jenis_kelamin'] === 'L' ? '<i class="bi bi-person-fill text-primary"></i>' : '<i class="bi bi-person-fill text-danger"></i>';
            $uid = 'siswa-'.(int)$u['akun_id'];
            echo '<div class="col-md-6 col-lg-4">';
            echo '<label class="form-check d-flex align-items-start p-2 border rounded cursor-pointer checkbox-item" style="cursor:pointer; transition: all 0.2s;" data-checkbox-id="'.$uid.'">';
            echo '<input type="checkbox" class="form-check-input d-none siswaCheckbox" value="'.(int)$u['akun_id'].'" data-label-id="'.$uid.'">';
            echo '<div class="ms-0 text-start" style="font-size:13px; width:100%;">';
            echo '<div class="fw-600">'.htmlspecialchars($u['nis_nip']).'</div>';
            echo '<div class="text-muted">'.$icon.' '.htmlspecialchars($u['nama_lengkap']).'</div>';
            echo '</div></label></div>';
        }
        echo '</div>';
        exit;
    }

    // ── GET CURRENT MEMBERS (checkbox untuk bulk delete) ──────────
    if ($aksi_ajax === 'get_current_members') {
        header('Content-Type: text/html; charset=utf-8');
        if (!$bisa_kelola) { echo ''; exit; }
        $rows = sirey_fetchAll(sirey_query(
            "SELECT a.akun_id,a.nis_nip,a.nama_lengkap,a.role,a.jenis_kelamin
             FROM akun_rayhanrp a JOIN grup_anggota_rayhanrp ga ON a.akun_id=ga.akun_id
             WHERE ga.grup_id=?
             ORDER BY a.nama_lengkap ASC", 'i', $id_grup
        ));
        if (empty($rows)) { echo '<p class="text-center text-muted py-4">Belum ada anggota.</p>'; exit; }
        echo '<div class="row g-2" id="currentMembersContainer">';
        foreach ($rows as $m) {
            $icon = $m['jenis_kelamin'] === 'L' ? '<i class="bi bi-person-fill text-primary"></i>' : '<i class="bi bi-person-fill text-danger"></i>';
            $uid = 'member-'.(int)$m['akun_id'];
            $badgeClass = match($m['role']) {
                'admin' => 'bg-danger',
                'guru' => 'bg-success',
                'kurikulum' => 'bg-primary',
                'kepala_sekolah' => 'bg-info text-dark',
                default => 'bg-secondary'
            };
            echo '<div class="col-md-6 col-lg-4">';
            echo '<label class="form-check d-flex align-items-start p-2 border rounded cursor-pointer checkbox-item" style="cursor:pointer; transition: all 0.2s;" data-checkbox-id="'.$uid.'">';
            echo '<input type="checkbox" class="form-check-input d-none memberCheckbox" value="'.(int)$m['akun_id'].'" data-label-id="'.$uid.'">';
            echo '<div class="ms-0 text-start" style="font-size:13px; width:100%;">';
            echo '<div class="fw-600">'.htmlspecialchars($m['nis_nip']).'</div>';
            echo '<div class="text-muted">'.$icon.' '.htmlspecialchars($m['nama_lengkap']).'</div>';
            echo '<div><span class="badge '.$badgeClass.'" style="font-size:10px;">'.htmlspecialchars($m['role']).'</span></div>';
            echo '</div></label></div>';
        }
        echo '</div>';
        exit;
    }

    // ── IMPORT SISWA VIA EXCEL ────────────────────────────────────
    if ($aksi_ajax === 'import_excel_siswa') {
        header('Content-Type: application/json; charset=utf-8');

        if (!$bisa_kelola) {
            echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
            exit;
        }
        if ($id_grup <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID kelas tidak valid.']);
            exit;
        }
        if (empty($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'File gagal diupload. Pastikan ukuran tidak melebihi batas.']);
            exit;
        }

        $ext = strtolower(pathinfo($_FILES['excel_file']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'xls'], true)) {
            echo json_encode(['success' => false, 'message' => 'Hanya file .xlsx atau .xls yang diterima.']);
            exit;
        }

        // Coba load PhpSpreadsheet
        $autoload = __DIR__ . '/../vendor/autoload.php';
        if (!file_exists($autoload)) {
            echo json_encode(['success' => false, 'message' => 'Library PhpSpreadsheet belum diinstall. Jalankan: composer require phpoffice/phpspreadsheet']);
            exit;
        }
        require_once $autoload;

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($_FILES['excel_file']['tmp_name']);
            $sheet       = $spreadsheet->getActiveSheet();
            $rows        = $sheet->toArray(null, true, true, false);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Gagal membaca file Excel: ' . $e->getMessage()]);
            exit;
        }

        if (empty($rows)) {
            echo json_encode(['success' => false, 'message' => 'File Excel kosong.']);
            exit;
        }

        // --- Deteksi baris header & kolom NIS ---
        // Cari baris pertama yang mengandung kata "NIS" di salah satu selnya
        $header_row  = 0;
        $nis_col_idx = null;

        foreach ($rows as $i => $row) {
            foreach ((array)$row as $j => $cell) {
                if (stripos(trim((string)$cell), 'nis') !== false) {
                    $header_row  = $i;
                    $nis_col_idx = $j;
                    break 2;
                }
            }
        }

        // Jika tidak ketemu header NIS, anggap kolom index 2 (kolom C, pola NO|NAMA|NIS|L/P)
        if ($nis_col_idx === null) {
            $nis_col_idx = 2;
            $header_row  = 0;
        }

        // --- Proses setiap baris data (lewati header) ---
        $berhasil    = 0;
        $gagal       = 0;
        $sudah_ada   = 0;
        $pesan_error = [];
        $nis_diproses = [];

        for ($i = $header_row + 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            $nis = trim((string)($row[$nis_col_idx] ?? ''));

            // Lewati baris kosong
            if ($nis === '') continue;

            // Lewati duplikat dalam file yang sama
            if (isset($nis_diproses[$nis])) continue;
            $nis_diproses[$nis] = true;

            // 1. Cari akun_id berdasarkan NIS — HANYA baca, tidak update master
            $akun = sirey_fetch(sirey_query(
                'SELECT akun_id FROM akun_rayhanRP WHERE nis_nip = ? AND role = "siswa" AND aktif = 1 LIMIT 1',
                's', $nis
            ));

            if (!$akun) {
                $gagal++;
                $pesan_error[] = "NIS <strong>{$nis}</strong>: tidak ditemukan atau bukan akun siswa aktif.";
                continue;
            }

            $akun_id = (int)$akun['akun_id'];

            // 2. Cek apakah sudah jadi anggota
            $cek = sirey_fetch(sirey_query(
                'SELECT keanggotaan_id FROM grup_anggota_rayhanRP WHERE grup_id = ? AND akun_id = ?',
                'ii', $id_grup, $akun_id
            ));

            if ($cek) {
                // Jika sudah ada tapi nonaktif, aktifkan kembali
                sirey_execute(
                    'UPDATE grup_anggota_rayhanRP SET aktif = 1 WHERE grup_id = ? AND akun_id = ?',
                    'ii', $id_grup, $akun_id
                );
                $sudah_ada++;
                continue;
            }

            // 3. INSERT ke tabel pivot — tidak menyentuh tabel master sama sekali
            $tipe = getPrimaryGroupId($akun_id) ? 'tambahan' : 'utama';
            $hasil = sirey_execute(
                'INSERT INTO grup_anggota_rayhanRP (grup_id, akun_id, tipe_keanggotaan, aktif) VALUES (?, ?, ?, 1)',
                'iis', $id_grup, $akun_id, $tipe
            );

            if ($hasil >= 1) {
                $berhasil++;
            } else {
                $gagal++;
                $pesan_error[] = "NIS <strong>{$nis}</strong>: gagal ditambahkan ke database.";
            }
        }

        auditLog($admin['id'], 'import_siswa_excel', 'grup', $id_grup, [
            'berhasil' => $berhasil, 'gagal' => $gagal, 'sudah_ada' => $sudah_ada
        ]);

        $total_diproses = $berhasil + $gagal + $sudah_ada;
        echo json_encode([
            'success'   => $berhasil > 0 || $sudah_ada > 0,
            'berhasil'  => $berhasil,
            'gagal'     => $gagal,
            'sudah_ada' => $sudah_ada,
            'total'     => $total_diproses,
            'errors'    => array_slice($pesan_error, 0, 15),
            'message'   => $berhasil > 0
                ? "{$berhasil} siswa berhasil ditambahkan" . ($sudah_ada > 0 ? ", {$sudah_ada} sudah ada sebelumnya" : "") . ($gagal > 0 ? ", {$gagal} gagal." : ".")
                : ($sudah_ada > 0 ? "Semua {$sudah_ada} siswa sudah terdaftar di kelas ini." : "Tidak ada siswa yang berhasil ditambahkan.")
        ]);
        exit;
    }

    // ── GET JADWAL ───────────────────────────────────────────────
    if ($aksi_ajax === 'get_jadwal') {
        header('Content-Type: text/html; charset=utf-8');
        $rows = sirey_fetchAll(sirey_query(
            "SELECT gm.id AS jadwal_id,gm.hari,gm.jam_mulai,gm.jam_selesai,
                    a.nama_lengkap AS guru_nama,mp.nama AS nama_mapel
             FROM guru_mengajar_rayhanrp gm
             LEFT JOIN akun_rayhanrp a ON gm.akun_id=a.akun_id
             LEFT JOIN mata_pelajaran_rayhanrp mp ON gm.matpel_id=mp.matpel_id
             WHERE gm.grup_id=? AND gm.hari IS NOT NULL
             ORDER BY FIELD(gm.hari,'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'),gm.jam_mulai ASC",
            'i', $id_grup
        ));
        ob_start();
        if (empty($rows)) { echo '<p class="text-center text-muted py-4">Tidak ada jadwal.</p>'; }
        else {
            echo '<div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead><tr>
                  <th>Hari</th><th>Jam</th><th>Guru</th><th>Mapel</th></tr></thead><tbody>';
            foreach ($rows as $r) {
                echo '<tr><td><span class="badge bg-primary">'.$r['hari'].'</span></td>'
                    .'<td>'.substr($r['jam_mulai'],0,5).' – '.substr($r['jam_selesai'],0,5).'</td>'
                    .'<td>'.htmlspecialchars($r['guru_nama'] ?? '-').'</td>'
                    .'<td>'.htmlspecialchars($r['nama_mapel'] ?? '-').'</td></tr>';
            }
            echo '</tbody></table></div>';
        }
        echo '<div class="alert alert-info mt-3 py-2 small"><i class="bi bi-info-circle me-1"></i>Jadwal dikelola melalui menu <strong>Guru Mengajar</strong>.</div>';
        echo ob_get_clean();
        exit;
    }

    // ── GET TUGAS ────────────────────────────────────────────────
    if ($aksi_ajax === 'get_tugas') {
        header('Content-Type: text/html; charset=utf-8');
        $rows = sirey_fetchAll(sirey_query(
            "SELECT t.tugas_id,t.judul,t.tenggat,mp.nama AS matpel_nama
             FROM tugas_rayhanRP t LEFT JOIN mata_pelajaran_rayhanRP mp ON t.matpel_id=mp.matpel_id
             WHERE t.grup_id=? ORDER BY t.tenggat DESC", 'i', $id_grup
        ));
        if (empty($rows)) { echo '<p class="text-center text-muted py-4">Belum ada tugas.</p>'; }
        else {
            echo '<div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead><tr>
                  <th>Judul</th><th>Mapel</th><th>Tenggat</th></tr></thead><tbody>';
            foreach ($rows as $r) {
                $over = strtotime($r['tenggat']) < time();
                echo '<tr><td>'.htmlspecialchars($r['judul']).'</td>'
                    .'<td>'.htmlspecialchars($r['matpel_nama'] ?? '-').'</td>'
                    .'<td><span class="badge '.($over ? 'bg-danger' : 'bg-success').'">'.date('d/m/Y', strtotime($r['tenggat'])).'</span></td></tr>';
            }
            echo '</tbody></table></div>';
        }
        echo '<div class="alert alert-info mt-3 py-2 small"><i class="bi bi-info-circle me-1"></i>Tugas dikelola melalui menu <strong>Tugas</strong>.</div>';
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');

    // ── DELETE MEMBER ────────────────────────────────────────────
    if ($aksi_ajax === 'delete_member') {
        if (!$bisa_kelola) { echo json_encode(['success'=>false,'message'=>'Akses ditolak']); exit; }
        $akunId = (int)($_GET['akun_id'] ?? 0);
        $primary = getPrimaryGroupId($akunId) ?? 0;
        removeUserMembership($akunId, $id_grup);
        if ($primary === $id_grup) syncPrimaryGroup($akunId, null);
        echo json_encode(['success'=>true,'message'=>'Anggota berhasil dihapus']);
        exit;
    }

    // ── ADD MEMBER (1 orang) ─────────────────────────────────────
    if ($aksi_ajax === 'add_member') {
        if (!$bisa_kelola) { echo json_encode(['success'=>false,'message'=>'Akses ditolak']); exit; }
        $akunId = (int)($_GET['akun_id'] ?? 0);
        $check  = sirey_fetch(sirey_query("SELECT akun_id FROM grup_anggota_rayhanRP WHERE grup_id=? AND akun_id=?",'ii',$id_grup,$akunId));
        if ($check) { echo json_encode(['success'=>false,'message'=>'Anggota sudah ada di grup ini']); exit; }
        $primary = getPrimaryGroupId($akunId) ?? 0;
        if ($primary <= 0) syncPrimaryGroup($akunId, $id_grup);
        else ensureUserMembership($akunId, $id_grup, 'tambahan');
        echo json_encode(['success'=>true,'message'=>'Anggota berhasil ditambahkan']);
        exit;
    }

    // ── BULK ADD MEMBERS ────────────────────────────────────────
    if ($aksi_ajax === 'bulk_add_members') {
        if (!$bisa_kelola) { echo json_encode(['success'=>false,'message'=>'Akses ditolak']); exit; }
        $ids = array_filter(array_map('intval',(array)($_POST['siswa_id'] ?? [])));
        if (empty($ids)) { echo json_encode(['success'=>false,'message'=>'Tidak ada siswa yang dipilih']); exit; }
        $berhasil = 0; $gagal = 0;
        foreach ($ids as $aid) {
            $check  = sirey_fetch(sirey_query("SELECT akun_id FROM grup_anggota_rayhanRP WHERE grup_id=? AND akun_id=?",'ii',$id_grup,$aid));
            if ($check) { $gagal++; continue; }
            $primary = getPrimaryGroupId($aid) ?? 0;
            if ($primary <= 0) { syncPrimaryGroup($aid, $id_grup); $berhasil++; }
            else { ensureUserMembership($aid, $id_grup, 'tambahan'); $berhasil++; }
        }
        auditLog($admin['id'],'bulk_add_members','grup',$id_grup,['berhasil'=>$berhasil,'gagal'=>$gagal]);
        echo json_encode(['success'=>true,'message'=>"$berhasil anggota berhasil ditambahkan".($gagal>0?" ($gagal sudah ada)":"")]);
        exit;
    }

    // ── TOGGLE STATUS ────────────────────────────────────────────
    if ($aksi_ajax === 'toggle_status') {
        if (!$bisa_kelola) { echo json_encode(['success'=>false,'message'=>'Akses ditolak']); exit; }
        $status = (int)($_POST['status'] ?? 0);
        sirey_execute('UPDATE grup_rayhanrp SET aktif=? WHERE grup_id=?','ii',$status,$id_grup);
        auditLog($admin['id'],'toggle_grup','grup',$id_grup,['aktif'=>$status]);
        echo json_encode(['success'=>true,'message'=>'Status grup diperbarui','status'=>$status]);
        exit;
    }

    // ── BULK DELETE MEMBERS ──────────────────────────────────────
    if ($aksi_ajax === 'bulk_delete_members') {
        if (!$bisa_kelola) { echo json_encode(['success'=>false,'message'=>'Akses ditolak']); exit; }
        $ids = array_filter(array_map('intval',(array)($_POST['member_ids'] ?? [])));
        foreach ($ids as $aid) {
            $p = getPrimaryGroupId($aid) ?? 0;
            removeUserMembership($aid, $id_grup);
            if ($p === $id_grup) syncPrimaryGroup($aid, null);
        }
        echo json_encode(['success'=>true,'message'=>count($ids).' anggota dihapus']);
        exit;
    }

    echo json_encode(['success'=>false,'message'=>'Aksi tidak dikenal']);
    exit;
}

// ═══ NORMAL PAGE ═══
$judul_halaman_rayhanrp = 'Grup / Kelas';
$menu_aktif_rayhanrp    = 'grup';
require_once __DIR__ . '/_layout.php';

if (!can('view_grup', $data_admin_rayhanrp)) {
    header('Location: dashboard.php?err=akses'); exit;
}

$bisa_buat   = can('create_grup', $data_admin_rayhanrp);
$bisa_ubah   = can('update_grup', $data_admin_rayhanrp);
$bisa_hapus  = can('delete_grup', $data_admin_rayhanrp);
$bisa_tulis  = $bisa_buat || $bisa_ubah || $bisa_hapus;
$id_pembuat  = (int)($data_admin_rayhanrp['id'] ?? 0);
$pesan = $error = '';

$jurusan_list = ['Teknik Pemesinan','Teknik Mekatronika','Teknik Kimia Industri',
                 'Pengembangan Perangkat Lunak dan Gim','Desain Komunikasi Visual','Animasi'];

// ── POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = (string)($_POST['act'] ?? '');

    if ($aksi === 'create' && $bisa_buat) {
        requireNotReadonly($data_admin_rayhanrp, 'grup.php');
        $nama    = trim((string)($_POST['nama_grup'] ?? ''));
        $tingkat = (int)($_POST['tingkat'] ?? 0);
        $jurusan = (string)($_POST['jurusan'] ?? '');
        $desk    = trim((string)($_POST['deskripsi'] ?? ''));
        if ($nama === '') { $error = 'Nama grup tidak boleh kosong.'; }
        elseif ($tingkat < 10 || $tingkat > 12) { $error = 'Tingkat harus 10–12.'; }
        elseif (!in_array($jurusan, $jurusan_list, true)) { $error = 'Jurusan tidak valid.'; }
        else {
            $h = sirey_execute('INSERT INTO grup_rayhanrp (nama_grup,tingkat,jurusan,deskripsi,pembuat_id) VALUES (?,?,?,?,?)',
                'sissi', $nama, $tingkat, $jurusan, $desk ?: null, $id_pembuat);
            if ($h >= 1) { auditLog($id_pembuat,'create_grup','grup',sirey_lastInsertId(),['tingkat'=>$tingkat,'jurusan'=>$jurusan]); $pesan = "Grup '$nama' berhasil dibuat."; }
            else { $error = 'Gagal membuat grup. Nama mungkin sudah dipakai.'; }
        }

    } elseif ($aksi === 'update' && $bisa_ubah) {
        requireNotReadonly($data_admin_rayhanrp, 'grup.php');
        $gid     = (int)($_POST['id'] ?? 0);
        $nama    = trim((string)($_POST['nama_grup'] ?? ''));
        $tingkat = (int)($_POST['tingkat'] ?? 0);
        $jurusan = (string)($_POST['jurusan'] ?? '');
        $desk    = trim((string)($_POST['deskripsi'] ?? ''));
        $waliId  = (int)($_POST['wali_id'] ?? 0);
        if ($gid <= 0 || $nama === '') { $error = 'Data tidak valid.'; }
        elseif ($tingkat < 10 || $tingkat > 12) { $error = 'Tingkat harus 10–12.'; }
        elseif (!in_array($jurusan, $jurusan_list, true)) { $error = 'Jurusan tidak valid.'; }
        else {
            sirey_execute('UPDATE grup_rayhanrp SET nama_grup=?,tingkat=?,jurusan=?,deskripsi=?,wali_kelas_id=? WHERE grup_id=?',
                'sissii', $nama, $tingkat, $jurusan, $desk ?: null, $waliId > 0 ? $waliId : null, $gid);
            auditLog($id_pembuat,'update_grup','grup',$gid,['tingkat'=>$tingkat,'jurusan'=>$jurusan,'wali_kelas_id'=>$waliId]);
            $pesan = 'Grup berhasil diperbarui.';
        }

    } elseif ($aksi === 'delete' && $bisa_hapus) {
        requireNotReadonly($data_admin_rayhanrp, 'grup.php');
        $gid = (int)($_POST['id'] ?? 0);
        if ($gid > 0) {
            sirey_execute('DELETE FROM grup_rayhanrp WHERE grup_id=?','i',$gid);
            auditLog($id_pembuat,'delete_grup','grup',$gid);
            $pesan = 'Grup berhasil dihapus.';
        }

    } elseif ($aksi === 'delete_multiple' && $bisa_hapus) {
        requireNotReadonly($data_admin_rayhanrp, 'grup.php');
        $ids = array_filter(array_map('intval',(array)($_POST['selected_ids'] ?? [])));
        $n = 0;
        foreach ($ids as $gid) {
            if (sirey_execute('DELETE FROM grup_rayhanrp WHERE grup_id=?','i',$gid) >= 1) $n++;
        }
        auditLog($id_pembuat,'bulk_delete_grup','grup',null,['ids'=>$ids]);
        $pesan = "$n grup berhasil dihapus.";
    }
}

// ── Query daftar grup ──
$cari = trim((string)($_POST['search'] ?? ''));
$sql  = 'SELECT g.grup_id,g.nama_grup,g.tingkat,g.jurusan,g.deskripsi,g.aktif,g.wali_kelas_id,
                COALESCE(w.nama_lengkap,"—") AS wali_nama,
                COUNT(DISTINCT ga.akun_id) AS jml_anggota,
                COUNT(DISTINCT CASE WHEN gm.hari IS NOT NULL THEN gm.id END) AS jml_jadwal,
                COUNT(DISTINCT t.tugas_id) AS jml_tugas
         FROM grup_rayhanrp g
         LEFT JOIN akun_rayhanrp w ON g.wali_kelas_id=w.akun_id
         LEFT JOIN grup_anggota_rayhanrp ga ON g.grup_id=ga.grup_id
         LEFT JOIN guru_mengajar_rayhanrp gm ON g.grup_id=gm.grup_id AND gm.aktif=1
         LEFT JOIN tugas_rayhanrp t ON g.grup_id=t.grup_id';

if ($data_admin_rayhanrp['role'] === 'guru') {
    $sql .= ' INNER JOIN guru_mengajar_rayhanrp gm_s ON g.grup_id=gm_s.grup_id AND gm_s.akun_id='.(int)$data_admin_rayhanrp['id'].' AND gm_s.aktif=1';
}

$whereArr = []; $types = ''; $params = [];
if ($cari !== '') { $whereArr[] = 'g.nama_grup LIKE ?'; $types .= 's'; $params[] = "%$cari%"; }
if ($whereArr) $sql .= ' WHERE '.implode(' AND ', $whereArr);
$sql .= ' GROUP BY g.grup_id ORDER BY g.nama_grup ASC';

$daftarGrup = $params ? sirey_fetchAll(sirey_query($sql,$types,...$params)) : sirey_fetchAll(sirey_query($sql));
?>

<!-- Page Header -->
<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2">
  <div>
    <h2><i class="bi bi-mortarboard-fill text-primary me-2"></i>Manajemen Grup / Kelas</h2>
    <p><?php echo match($data_admin_rayhanrp['role']) {
      'guru' => 'Hanya kelas yang Anda ajar yang ditampilkan. Mode baca saja.',
      'kepala_sekolah' => 'Mode baca saja untuk pemantauan.',
      default => 'Kelola grup, anggota, dan jadwal per kelas.'
    }; ?></p>
  </div>
  <?php if ($bisa_buat): ?>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalBuat">
      <i class="bi bi-plus-lg me-1"></i>Buat Grup Baru
    </button>
  <?php endif; ?>
</div>

<!-- Flash -->
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

<?php if (!$bisa_tulis): ?>
  <div class="alert alert-info mb-3"><i class="bi bi-info-circle me-2"></i>Mode baca saja aktif.</div>
<?php endif; ?>

<!-- Filter + Tabel -->
<div class="card">
  <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
    <h5><i class="bi bi-table me-2"></i>Daftar Grup <span class="badge bg-primary ms-1"><?php echo count($daftarGrup); ?></span></h5>
    <div class="d-flex gap-2">
      <?php if ($bisa_hapus): ?>
        <button id="btnBulkHapus" class="btn btn-sm btn-danger d-none" onclick="submitBulkHapus()">
          <i class="bi bi-trash me-1"></i>Hapus Terpilih (<span id="selCount">0</span>)
        </button>
      <?php endif; ?>
      <!-- Search inline -->
      <form method="POST" class="d-flex gap-2">
        <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari nama grup…"
               value="<?php echo htmlspecialchars($cari); ?>" style="min-width:180px;">
        <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i></button>
        <?php if ($cari !== ''): ?><a href="grup.php" class="btn btn-sm btn-outline-secondary">✕</a><?php endif; ?>
      </form>
    </div>
  </div>
  <div class="card-body p-0">
    <?php if (empty($daftarGrup)): ?>
      <div class="empty-state"><i class="bi bi-mortarboard"></i><p><?php echo $cari ? 'Tidak ada grup yang cocok.' : 'Belum ada grup.'; ?></p></div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover mb-0" id="tblGrup">
          <thead>
            <tr>
              <?php if ($bisa_hapus): ?><th style="width:40px;"><input type="checkbox" id="checkAll" class="form-check-input"></th><?php endif; ?>
              <th>Nama Grup</th><th>Tingkat & Jurusan</th><th>Wali Kelas</th>
              <th class="text-center">Anggota</th><th class="text-center">Jadwal</th><th class="text-center">Tugas</th>
              <th class="text-center">Status</th><th class="text-center">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($daftarGrup as $g): ?>
              <tr>
                <?php if ($bisa_hapus): ?>
                  <td><input type="checkbox" class="form-check-input grupCheck" value="<?php echo (int)$g['grup_id']; ?>" onchange="updateBulk()"></td>
                <?php endif; ?>
                <td>
                  <div class="fw-600"><?php echo htmlspecialchars($g['nama_grup']); ?></div>
                  <?php if (!empty($g['deskripsi'])): ?>
                    <div class="text-muted" style="font-size:11px;"><?php echo htmlspecialchars($g['deskripsi']); ?></div>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="badge bg-info text-dark me-1">Kelas <?php echo (int)$g['tingkat']; ?></span>
                  <span class="badge bg-light text-dark border" style="font-size:10px;"><?php echo htmlspecialchars($g['jurusan']); ?></span>
                </td>
                <td>
                  <?php if ((int)$g['wali_kelas_id'] > 0): ?>
                    <span class="badge bg-success"><?php echo htmlspecialchars($g['wali_nama']); ?></span>
                  <?php else: ?>
                    <span class="text-muted small">—</span>
                  <?php endif; ?>
                </td>
                <td class="text-center"><span class="badge bg-secondary"><?php echo (int)$g['jml_anggota']; ?></span></td>
                <td class="text-center"><span class="badge bg-secondary"><?php echo (int)$g['jml_jadwal']; ?></span></td>
                <td class="text-center"><span class="badge bg-secondary"><?php echo (int)$g['jml_tugas']; ?></span></td>
                <td class="text-center">
                  <?php if ($bisa_ubah): ?>
                    <button class="btn btn-sm <?php echo (int)$g['aktif'] ? 'btn-success' : 'btn-secondary'; ?>"
                            onclick="toggleStatus(<?php echo (int)$g['grup_id']; ?>, <?php echo (int)$g['aktif']; ?>)">
                      <?php echo (int)$g['aktif'] ? 'Aktif' : 'Nonaktif'; ?>
                    </button>
                  <?php else: ?>
                    <span class="badge <?php echo (int)$g['aktif'] ? 'bg-success' : 'bg-secondary'; ?>"><?php echo (int)$g['aktif'] ? 'Aktif' : 'Nonaktif'; ?></span>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="d-flex gap-1 justify-content-center flex-wrap">
                    <button class="btn btn-sm btn-outline-info" title="Anggota"
                            onclick="openModal(<?php echo (int)$g['grup_id']; ?>, <?php echo htmlspecialchars(json_encode($g['nama_grup']), ENT_QUOTES); ?>, 'anggota')">
                      <i class="bi bi-people"></i>
                    </button>
                    <?php if ($bisa_ubah): ?>
                      <button class="btn btn-sm btn-outline-primary" title="Edit"
                              onclick='openEdit(<?php echo json_encode($g, JSON_HEX_TAG|JSON_HEX_QUOT|JSON_HEX_AMP); ?>)'>
                        <i class="bi bi-pencil"></i>
                      </button>
                    <?php endif; ?>
                    <?php if ($bisa_hapus): ?>
                      <form method="POST" class="m-0" data-confirm="Hapus grup '<?php echo htmlspecialchars($g['nama_grup']); ?>'? Semua data terkait ikut terhapus.">
                        <input type="hidden" name="act" value="delete">
                        <input type="hidden" name="id" value="<?php echo (int)$g['grup_id']; ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button>
                      </form>
                    <?php endif; ?>
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

<?php if ($bisa_buat): ?>
<!-- MODAL BUAT GRUP -->
<div class="modal fade" id="modalBuat" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="act" value="create">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Buat Grup Baru</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Nama Grup / Kelas <span class="text-danger">*</span></label>
            <input type="text" name="nama_grup" class="form-control" placeholder="Contoh: XI PPLG A" required>
          </div>
          <div class="row g-3">
            <div class="col-sm-6">
              <label class="form-label">Tingkat <span class="text-danger">*</span></label>
              <select name="tingkat" class="form-select" required>
                <option value="">— Pilih —</option>
                <option value="10">Kelas X</option>
                <option value="11">Kelas XI</option>
                <option value="12">Kelas XII</option>
              </select>
            </div>
            <div class="col-sm-6">
              <label class="form-label">Jurusan <span class="text-danger">*</span></label>
              <select name="jurusan" class="form-select" required>
                <option value="">— Pilih —</option>
                <?php foreach ($jurusan_list as $j): ?>
                  <option value="<?php echo $j; ?>"><?php echo $j; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="mt-3">
            <label class="form-label">Deskripsi</label>
            <input type="text" name="deskripsi" class="form-control" placeholder="Opsional">
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

<?php if ($bisa_ubah): ?>
<!-- MODAL EDIT -->
<div class="modal fade" id="modalEdit" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="act" value="update">
        <input type="hidden" name="id" id="edit_id">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Grup</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Nama Grup / Kelas <span class="text-danger">*</span></label>
            <input type="text" name="nama_grup" id="edit_nama" class="form-control" required>
          </div>
          <div class="row g-3">
            <div class="col-sm-6">
              <label class="form-label">Tingkat <span class="text-danger">*</span></label>
              <select name="tingkat" id="edit_tingkat" class="form-select" required>
                <option value="">— Pilih —</option>
                <option value="10">Kelas X</option>
                <option value="11">Kelas XI</option>
                <option value="12">Kelas XII</option>
              </select>
            </div>
            <div class="col-sm-6">
              <label class="form-label">Jurusan <span class="text-danger">*</span></label>
              <select name="jurusan" id="edit_jurusan" class="form-select" required>
                <option value="">— Pilih —</option>
                <?php foreach ($jurusan_list as $j): ?>
                  <option value="<?php echo $j; ?>"><?php echo $j; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="mt-3">
            <label class="form-label">Deskripsi</label>
            <input type="text" name="deskripsi" id="edit_deskripsi" class="form-control">
          </div>
          <div class="mt-3">
            <label class="form-label">Wali Kelas</label>
            <select name="wali_id" id="edit_wali_id" class="form-select">
              <option value="">— Pilih Guru (Opsional) —</option>
              <?php 
                $daftarGuruWali = sirey_fetchAll(sirey_query('SELECT akun_id, nama_lengkap FROM akun_rayhanRP WHERE role="guru" ORDER BY nama_lengkap ASC'));
                foreach ($daftarGuruWali as $gr): 
              ?>
                <option value="<?php echo $gr['akun_id']; ?>"><?php echo htmlspecialchars($gr['nama_lengkap']); ?></option>
              <?php endforeach; ?>
            </select>
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

<!-- MODAL DETAIL (Anggota) -->
<div class="modal fade" id="modalDetail" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="detailTitle">Detail</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="detailBody" style="min-height: 500px; max-height: 70vh; overflow-y: auto;">
        <div class="text-center py-5"><div class="spinner-border text-primary"></div></div>
      </div>
    </div>
  </div>
</div>

<!-- Hidden bulk delete form -->
<form id="formBulk" method="POST" style="display:none;">
  <input type="hidden" name="act" value="delete_multiple">
  <div id="bulkContainer"></div>
</form>

<script>
let currentGrupId = 0, currentTab = 'anggota';
// Apakah user boleh kelola (dari PHP ke JS)
const bisaKelola = <?php echo json_encode($bisa_kelola ?? $bisa_ubah); ?>;

// ── Open modal detail ──
function openModal(gid, gname, tab) {
  currentGrupId = gid;
  document.getElementById('detailTitle').textContent = gname;
  new bootstrap.Modal(document.getElementById('modalDetail')).show();
  switchTab(tab || 'anggota');
}

function switchTab(tab) {
  currentTab = tab;
  // Only anggota tab exists now
  const tabBtn = document.getElementById('tab' + tab.charAt(0).toUpperCase() + tab.slice(1) + 'Btn');
  if (tabBtn) tabBtn.classList.add('active');
  loadTab(tab);
}

function loadTab(tab) {
  const body = document.getElementById('detailBody');
  body.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>';
  // Only members tab now
  if (tab !== 'anggota') return;
  // Clear body dan langsung load form tambah + list anggota dengan checkbox
  body.innerHTML = '';
  loadAddMember();
}

// ── Tambah anggota dengan checkbox (bulk insert) + tombol import Excel ──
function loadAddMember() {
  if (!bisaKelola) return; // Tidak tampilkan form jika tidak punya hak

  const body = document.getElementById('detailBody');
  if (!body) {
    console.error('detailBody not found');
    return;
  }

  // ── Button Tambah Anggota (Always Visible) ──
  const btnSection = document.createElement('div');
  btnSection.className = 'mb-3 d-flex gap-2 align-items-center justify-content-between flex-wrap';
  btnSection.innerHTML = `
    <div>
      <button class="btn btn-sm btn-primary" id="btnShowAddForm" onclick="toggleAddForm(true)">
        <i class="bi bi-plus-circle me-1"></i>Tambah Anggota Baru
      </button>
      <button class="btn btn-sm btn-outline-success d-none" id="btnHideAddForm" onclick="toggleAddForm(false)">
        <i class="bi bi-x-circle me-1"></i>Tutup Form
      </button>
    </div>
    <button class="btn btn-sm btn-outline-success" onclick="bukaImportExcel()" title="Import banyak siswa sekaligus via file Excel">
      <i class="bi bi-file-earmark-excel me-1"></i>Import Excel
    </button>
  `;
  body.appendChild(btnSection);

  // ── Seksi: Tambah Anggota (Bulk Checkbox) - HIDDEN BY DEFAULT ──
  const sectionAdd = document.createElement('div');
  sectionAdd.className = 'p-3 bg-light rounded border mb-3 d-none';
  sectionAdd.id = 'addFormSection';
  body.appendChild(sectionAdd);

  // ── Seksi: Daftar Anggota Saat Ini (dengan checkbox untuk bulk delete) ──
  const sectionCurrent = document.createElement('div');
  sectionCurrent.className = 'p-3 bg-white rounded border';
  sectionCurrent.id = 'currentSection';
  body.appendChild(sectionCurrent);

  // Fetch both available users dan current members
  Promise.all([
    fetch(`./grup.php?action=get_available_users&grup_id=${currentGrupId}`).then(r => r.text()).catch(e => { console.error('get_available_users error:', e); return ''; }),
    fetch(`./grup.php?action=get_current_members&grup_id=${currentGrupId}`).then(r => r.text()).catch(e => { console.error('get_current_members error:', e); return ''; })
  ])
  .then(([availableHtml, memberHtml]) => {
    // ── Populate Available Users Section ──
    let addContent = `
      <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <span class="fw-semibold" style="font-size:14px;"><i class="bi bi-plus-circle text-success me-2"></i>Tambah Anggota</span>
        <div class="d-flex gap-2">
          <button class="btn btn-sm btn-outline-secondary" id="selectAllAdd" onclick="toggleSelectAll('add')" title="Pilih Semua">
            <i class="bi bi-check2-square me-1"></i>Pilih Semua
          </button>
        </div>
      </div>`;

    if (availableHtml.trim().includes('Semua pengguna sudah')) {
      addContent += availableHtml;
    } else {
      addContent += `
        <div class="mb-3">
          <input type="text" class="form-control form-control-sm" id="searchAvailable" 
                 placeholder="🔍 Cari siswa (NIS/Nama)..." onkeyup="filterAvailableUsers()">
        </div>
        <div id="siswaListContainer" style="max-height: 400px; overflow-y: auto;">
          ${availableHtml}
        </div>
        <div class="d-flex gap-2 mt-3 pt-2 border-top">
          <button class="btn btn-sm btn-success" onclick="bulkAddMembers()" id="btnBulkAdd" disabled>
            <i class="bi bi-plus me-1"></i>Tambah <span id="addCount">0</span> Anggota
          </button>
          <button class="btn btn-sm btn-outline-secondary" onclick="clearSelectAll('add')">
            <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
          </button>
        </div>
      `;
    }
    sectionAdd.innerHTML = addContent;

    // ── Populate Current Members Section ──
    let memberContent = `
      <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <span class="fw-semibold" style="font-size:14px;"><i class="bi bi-people text-info me-2"></i>Anggota Saat Ini</span>
        <div class="d-flex gap-2">
          <button class="btn btn-sm btn-outline-secondary" id="selectAllDel" onclick="toggleSelectAll('delete')" title="Pilih Semua">
            <i class="bi bi-check2-square me-1"></i>Pilih Semua
          </button>
        </div>
      </div>`;

    if (memberHtml.trim().includes('Belum ada anggota')) {
      memberContent += memberHtml;
    } else {
      memberContent += `
        <div class="mb-3">
          <input type="text" class="form-control form-control-sm" id="searchMembers" 
                 placeholder="🔍 Cari anggota (NIS/Nama)..." onkeyup="filterMemberUsers()">
        </div>
        <div id="membersListContainer" style="max-height: 400px; overflow-y: auto;">
          ${memberHtml}
        </div>
        <div class="d-flex gap-2 mt-3 pt-2 border-top">
          <button class="btn btn-sm btn-danger" onclick="bulkDeleteMembers()" id="btnBulkDelete" disabled>
            <i class="bi bi-trash me-1"></i>Hapus <span id="delCount">0</span> Anggota
          </button>
          <button class="btn btn-sm btn-outline-secondary" onclick="clearSelectAll('delete')">
            <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
          </button>
        </div>
      `;
    }
    sectionCurrent.innerHTML = memberContent;

    // Attach event listeners untuk checkbox
    attachCheckboxListeners();
  })
  .catch(err => {
    console.error('Error loading members:', err);
    body.innerHTML = '<p class="text-danger text-center py-4"><i class="bi bi-exclamation-triangle me-2"></i>Gagal memuat data anggota.</p>';
  });
}

// ── Toggle Add Form Visibility ──
function toggleAddForm(show) {
  const form = document.getElementById('addFormSection');
  const btnShow = document.getElementById('btnShowAddForm');
  const btnHide = document.getElementById('btnHideAddForm');
  
  if (show) {
    form.classList.remove('d-none');
    btnShow.classList.add('d-none');
    btnHide.classList.remove('d-none');
  } else {
    form.classList.add('d-none');
    btnShow.classList.remove('d-none');
    btnHide.classList.add('d-none');
    clearSelectAll('add');
  }
}

// ── Filter Available Users ──
function filterAvailableUsers() {
  const keyword = document.getElementById('searchAvailable')?.value?.toLowerCase() || '';
  const items = document.querySelectorAll('#siswaListContainer .col-md-6');
  
  items.forEach(item => {
    const text = item.textContent.toLowerCase();
    item.style.display = text.includes(keyword) ? '' : 'none';
  });
}

// ── Filter Member Users ──
function filterMemberUsers() {
  const keyword = document.getElementById('searchMembers')?.value?.toLowerCase() || '';
  const items = document.querySelectorAll('#membersListContainer .col-md-6');
  
  items.forEach(item => {
    const text = item.textContent.toLowerCase();
    item.style.display = text.includes(keyword) ? '' : 'none';
  });
}

// ── Attach event listeners untuk checkbox ──
function attachCheckboxListeners() {
  // Untuk tambah
  const siswaCheckboxes = document.querySelectorAll('.siswaCheckbox');
  siswaCheckboxes.forEach(cb => {
    cb.addEventListener('change', () => {
      updateCheckboxCount('add', siswaCheckboxes);
      updateCheckboxVisualFeedback(cb);
    });
  });

  // Untuk hapus
  const memberCheckboxes = document.querySelectorAll('.memberCheckbox');
  memberCheckboxes.forEach(cb => {
    cb.addEventListener('change', () => {
      updateCheckboxCount('delete', memberCheckboxes);
      updateCheckboxVisualFeedback(cb);
    });
  });
}

// ── Update visual feedback saat checkbox berubah ──
function updateCheckboxVisualFeedback(checkbox) {
  const labelId = checkbox.getAttribute('data-label-id');
  if (!labelId) return;
  
  const label = document.querySelector(`[data-checkbox-id="${labelId}"]`);
  if (!label) return;
  
  if (checkbox.checked) {
    // Add highlight styling saat checked
    label.style.borderColor = '#0d6efd';
    label.style.borderWidth = '2px';
    label.style.backgroundColor = '#e7f1ff';
    label.style.boxShadow = '0 0 0 0.2rem rgba(13, 110, 253, 0.25)';
  } else {
    // Remove highlight styling saat unchecked
    label.style.borderColor = '';
    label.style.borderWidth = '';
    label.style.backgroundColor = '';
    label.style.boxShadow = '';
  }
}

// ── Update count dan enable/disable tombol ──
function updateCheckboxCount(type, checkboxes) {
  const count = [...checkboxes].filter(cb => cb.checked).length;
  if (type === 'add') {
    document.getElementById('addCount').textContent = count;
    document.getElementById('btnBulkAdd').disabled = count === 0;
    document.getElementById('btnBulkAdd').classList.toggle('btn-success', count > 0);
    document.getElementById('btnBulkAdd').classList.toggle('btn-outline-success', count === 0);
  } else if (type === 'delete') {
    document.getElementById('delCount').textContent = count;
    document.getElementById('btnBulkDelete').disabled = count === 0;
    document.getElementById('btnBulkDelete').classList.toggle('btn-danger', count > 0);
    document.getElementById('btnBulkDelete').classList.toggle('btn-outline-danger', count === 0);
  }
}

// ── Toggle Select All ──
function toggleSelectAll(type) {
  if (type === 'add') {
    const checkboxes = document.querySelectorAll('.siswaCheckbox');
    const allChecked = [...checkboxes].every(cb => cb.checked);
    checkboxes.forEach(cb => {
      cb.checked = !allChecked;
      updateCheckboxVisualFeedback(cb);
    });
    updateCheckboxCount('add', checkboxes);
  } else if (type === 'delete') {
    const checkboxes = document.querySelectorAll('.memberCheckbox');
    const allChecked = [...checkboxes].every(cb => cb.checked);
    checkboxes.forEach(cb => {
      cb.checked = !allChecked;
      updateCheckboxVisualFeedback(cb);
    });
    updateCheckboxCount('delete', checkboxes);
  }
}

// ── Clear Select All ──
function clearSelectAll(type) {
  if (type === 'add') {
    const checkboxes = document.querySelectorAll('.siswaCheckbox');
    checkboxes.forEach(cb => {
      cb.checked = false;
      updateCheckboxVisualFeedback(cb);
    });
    updateCheckboxCount('add', checkboxes);
  } else if (type === 'delete') {
    const checkboxes = document.querySelectorAll('.memberCheckbox');
    checkboxes.forEach(cb => {
      cb.checked = false;
      updateCheckboxVisualFeedback(cb);
    });
    updateCheckboxCount('delete', checkboxes);
  }
}

// ── Bulk Add Members ──
function bulkAddMembers() {
  const checkboxes = document.querySelectorAll('.siswaCheckbox:checked');
  const ids = [...checkboxes].map(cb => cb.value);
  
  if (ids.length === 0) {
    Swal.fire('Pilih minimal 1 siswa', '', 'warning');
    return;
  }

  Swal.fire({
    title: `Tambah ${ids.length} anggota?`,
    text: 'Siswa dipilih akan ditambahkan ke kelas ini.',
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#198754',
    confirmButtonText: 'Ya, Tambah',
    cancelButtonText: 'Batal'
  }).then(r => {
    if (!r.isConfirmed) return;
    
    const fd = new FormData();
    ids.forEach(id => fd.append('siswa_id[]', id));

    fetch(`./grup.php?action=bulk_add_members&grup_id=${currentGrupId}`, {
      method: 'POST',
      body: fd
    })
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        Swal.fire({
          icon: 'success',
          title: d.message,
          timer: 1500,
          showConfirmButton: false
        });
        loadTab('anggota');
      } else {
        Swal.fire('Error', d.message, 'error');
      }
    });
  });
}

// ── Bulk Delete Members ──
function bulkDeleteMembers() {
  const checkboxes = document.querySelectorAll('.memberCheckbox:checked');
  const ids = [...checkboxes].map(cb => cb.value);
  
  if (ids.length === 0) {
    Swal.fire('Pilih minimal 1 anggota', '', 'warning');
    return;
  }

  Swal.fire({
    title: `Hapus ${ids.length} anggota?`,
    text: 'Anggota akan dikeluarkan dari kelas ini.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
    confirmButtonText: 'Ya, Hapus',
    cancelButtonText: 'Batal'
  }).then(r => {
    if (!r.isConfirmed) return;
    
    const fd = new FormData();
    ids.forEach(id => fd.append('member_ids[]', id));

    fetch(`./grup.php?action=bulk_delete_members&grup_id=${currentGrupId}`, {
      method: 'POST',
      body: fd
    })
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        Swal.fire({
          icon: 'success',
          title: d.message,
          timer: 1500,
          showConfirmButton: false
        });
        loadTab('anggota');
      } else {
        Swal.fire('Error', d.message, 'error');
      }
    });
  });
}

// ── Import Excel ─────────────────────────────────────────────────

function bukaImportExcel() {
  // Tampilkan dialog konfirmasi info dulu, lalu trigger input file
  Swal.fire({
    title: '<i class="bi bi-file-earmark-excel text-success me-2"></i>Import Siswa via Excel',
    html: `
      <div class="text-start" style="font-size:13px;">
        <div class="alert alert-info py-2 mb-3">
          <i class="bi bi-info-circle me-1"></i>
          File Excel harus memiliki kolom <strong>NIS</strong>.<br>
          Format yang didukung: <strong>.xlsx</strong> atau <strong>.xls</strong><br>
          Data master siswa <strong>tidak akan diubah</strong>.
        </div>
        <label class="form-label fw-semibold">Pilih File Excel:</label>
        <input type="file" id="inputFileExcel" class="form-control" accept=".xlsx,.xls">
        <div id="hasilImport" class="mt-3"></div>
      </div>`,
    showCancelButton: true,
    confirmButtonText: '<i class="bi bi-upload me-1"></i>Import Sekarang',
    cancelButtonText: 'Tutup',
    confirmButtonColor: '#198754',
    width: '520px',
    didOpen: () => {
      // Reset hasil setiap kali modal dibuka
      document.getElementById('hasilImport').innerHTML = '';
    },
    preConfirm: () => {
      return prosesImportExcel();
    },
    allowOutsideClick: false,
  });
}

function prosesImportExcel() {
  const input    = document.getElementById('inputFileExcel');
  const hasilDiv = document.getElementById('hasilImport');

  if (!input || !input.files[0]) {
    Swal.showValidationMessage('Pilih file Excel terlebih dahulu.');
    return false; // Cegah Swal tutup
  }

  const file = input.files[0];
  const ext  = file.name.split('.').pop().toLowerCase();
  if (!['xlsx','xls'].includes(ext)) {
    Swal.showValidationMessage('Hanya file .xlsx atau .xls yang diterima.');
    return false;
  }

  // Tampilkan loading di dalam Swal
  hasilDiv.innerHTML = `
    <div class="d-flex align-items-center gap-2 text-primary">
      <div class="spinner-border spinner-border-sm"></div>
      <span>Sedang memproses file, harap tunggu…</span>
    </div>`;
  Swal.resetValidationMessage();

  const fd = new FormData();
  fd.append('excel_file', file);

  // Kembalikan Promise agar Swal menunggu selesai
  return fetch(`./grup.php?action=import_excel_siswa&grup_id=${currentGrupId}`, {
    method: 'POST',
    body: fd
  })
  .then(r => r.json())
  .then(d => {
    // Render hasil di dalam Swal
    let html = `
      <div class="alert alert-${d.success ? 'success' : 'warning'} py-2 mb-0">
        <i class="bi bi-${d.success ? 'check-circle-fill' : 'exclamation-triangle-fill'} me-1"></i>
        <strong>${d.message}</strong>
      </div>`;

    if (d.berhasil > 0 || d.sudah_ada > 0 || d.gagal > 0) {
      html += `
        <div class="d-flex gap-3 mt-2 justify-content-center" style="font-size:13px;">
          <span class="text-success"><i class="bi bi-check-circle me-1"></i><strong>${d.berhasil}</strong> ditambahkan</span>
          <span class="text-secondary"><i class="bi bi-dash-circle me-1"></i><strong>${d.sudah_ada}</strong> sudah ada</span>
          <span class="text-danger"><i class="bi bi-x-circle me-1"></i><strong>${d.gagal}</strong> gagal</span>
        </div>`;
    }

    if (d.errors && d.errors.length > 0) {
      html += `<details class="mt-2 text-start" style="font-size:12px;">
        <summary class="text-muted" style="cursor:pointer;">Lihat detail error (${d.errors.length})</summary>
        <ul class="mt-1 mb-0 ps-3 text-danger">
          ${d.errors.map(e => `<li>${e}</li>`).join('')}
        </ul>
      </details>`;
    }

    hasilDiv.innerHTML = html;

    // Jika ada yang berhasil, refresh tab anggota di background
    if (d.success) {
      // Simpan flag untuk refresh setelah Swal ditutup
      window._importBerhasil = true;
    }

    // Kembalikan false agar Swal tidak otomatis tutup (user bisa baca hasil dulu)
    Swal.resetValidationMessage();
    // Ganti tombol confirm jadi "Tutup"
    const confirmBtn = Swal.getConfirmButton();
    if (confirmBtn) {
      confirmBtn.innerHTML = '<i class="bi bi-x-lg me-1"></i>Tutup';
      confirmBtn.onclick = () => {
        Swal.close();
        if (window._importBerhasil) {
          window._importBerhasil = false;
          loadTab('anggota'); // Refresh daftar anggota
        }
      };
    }
    return false; // Cegah Swal auto-close
  })
  .catch(() => {
    hasilDiv.innerHTML = '<div class="alert alert-danger py-2">Terjadi error saat upload. Coba lagi.</div>';
    return false;
  });
}

// ── Toggle status grup ──
function toggleStatus(gid, current) {
  const newStatus = current ? 0 : 1;
  const label = newStatus ? 'Aktif' : 'Nonaktif';
  Swal.fire({
    title: `Ubah status menjadi ${label}?`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Ya',
    cancelButtonText: 'Batal'
  }).then(r => {
    if (!r.isConfirmed) return;
    const fd = new FormData();
    fd.append('status', newStatus);
    fetch(`./grup.php?action=toggle_status&grup_id=${gid}`, { method:'POST', body:fd })
      .then(r => r.json())
      .then(d => {
        if (d.success) location.reload();
        else Swal.fire({ icon:'error', title:d.message });
      });
  });
}

// ── Edit modal ──
function openEdit(g) {
  document.getElementById('edit_id').value        = g.grup_id;
  document.getElementById('edit_nama').value      = g.nama_grup;
  document.getElementById('edit_tingkat').value   = g.tingkat;
  document.getElementById('edit_jurusan').value   = g.jurusan;
  document.getElementById('edit_deskripsi').value = g.deskripsi || '';
  document.getElementById('edit_wali_id').value   = g.wali_kelas_id || '';
  new bootstrap.Modal(document.getElementById('modalEdit')).show();
}

// ── Bulk delete grup ──
<?php if ($bisa_hapus): ?>
document.getElementById('checkAll')?.addEventListener('change', function () {
  document.querySelectorAll('.grupCheck').forEach(cb => cb.checked = this.checked);
  updateBulk();
});
function updateBulk() {
  const n = document.querySelectorAll('.grupCheck:checked').length;
  document.getElementById('selCount').textContent = n;
  document.getElementById('btnBulkHapus').classList.toggle('d-none', n === 0);
}
function submitBulkHapus() {
  const ids = [...document.querySelectorAll('.grupCheck:checked')].map(cb => cb.value);
  Swal.fire({
    title: `Hapus ${ids.length} grup?`,
    text: 'Semua data terkait ikut terhapus!',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
    confirmButtonText: 'Ya Hapus',
    cancelButtonText: 'Batal'
  }).then(r => {
    if (!r.isConfirmed) return;
    const c = document.getElementById('bulkContainer');
    c.innerHTML = ids.map(id => `<input type="hidden" name="selected_ids[]" value="${id}">`).join('');
    document.getElementById('formBulk').submit();
  });
}
<?php endif; ?>
</script>

<style>
  /* Styling untuk checkbox items */
  .checkbox-item {
    transition: all 0.2s ease !important;
    border: 1px solid #e0e0e0 !important;
    position: relative;
  }
  
  .checkbox-item:hover {
    border-color: #ccc !important;
    background-color: #f9f9f9;
    cursor: pointer;
  }
  
  .checkbox-item input[type="checkbox"]:checked ~ * {
    font-weight: 500;
  }
</style>

<?php layoutEnd(); ?>