<?php
declare(strict_types=1);

require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
// require_once __DIR__ . '/../telegram/notifikasi_tugas.php'; // DEPRECATED - gunakan notifikasi_scheduler.php

// ========== FUNCTIONS ==========

function getPendingRevisions(int $guru_id, int $limit = 20): array {
    $rows = sirey_fetchAll(sirey_query(
        'SELECT 
           pv.versi_id, pv.pengumpulan_id, pv.nomor_versi,
           pv.teks_jawaban, pv.file_path, pv.file_nama_asli, pv.link_jawaban,
           pv.disubmit_oleh, pv.alasan_revisi, pv.status_approval,
           pv.dibuat_pada,
           p.tugas_id, p.akun_id,
           t.judul AS tugas_judul,
           a_siswa.nama_lengkap AS siswa_nama,
           a_guru.nama_lengkap AS guru_nama
         FROM pengumpulan_versi_rayhanRP pv
         INNER JOIN pengumpulan_rayhanRP p ON pv.pengumpulan_id = p.pengumpulan_id
         INNER JOIN tugas_rayhanRP t ON p.tugas_id = t.tugas_id
         INNER JOIN akun_rayhanRP a_siswa ON p.akun_id = a_siswa.akun_id
         LEFT JOIN akun_rayhanRP a_guru ON t.pembuat_id = a_guru.akun_id
         WHERE pv.status_approval = "pending"
           AND pv.versi_tipe = "revisi"
           AND t.pembuat_id = ?
         ORDER BY pv.dibuat_pada ASC
         LIMIT ?',
        'ii', $guru_id, $limit
    ));
    return $rows ?: [];
}

function approveRevision(int $versi_id, int $guru_id, string $catatan_approval = ''): array {
    try {
        $versi = sirey_fetch(sirey_query('SELECT * FROM pengumpulan_versi_rayhanRP WHERE versi_id = ?', 'i', $versi_id));
        if (!$versi) return ['success' => false, 'message' => 'Versi tidak ditemukan'];
        
        $pengumpulan_id = (int)$versi['pengumpulan_id'];
        
        $updateVersiResult = sirey_execute(
            'UPDATE pengumpulan_versi_rayhanRP SET status_approval = "disetujui", disetujui_oleh = ?, catatan_approval = ?, diubah_pada = NOW() WHERE versi_id = ?',
            'isi', $guru_id, $catatan_approval, $versi_id
        );
        if ($updateVersiResult <= 0) return ['success' => false, 'message' => 'Gagal menyimpan approval'];
        
        $updatePengumpulanResult = sirey_execute(
            'UPDATE pengumpulan_rayhanRP SET teks_jawaban = ?, file_path = ?, file_nama_asli = ?, link_jawaban = ?, status = "graded", diubah_pada = NOW() WHERE pengumpulan_id = ?',
            'ssssi',
            $versi['teks_jawaban'], $versi['file_path'], $versi['file_nama_asli'], $versi['link_jawaban'], $pengumpulan_id
        );
        if ($updatePengumpulanResult <= 0) return ['success' => false, 'message' => 'Gagal update pengumpulan'];
        
        auditLog($guru_id, 'REVISI_APPROVED', 'pengumpulan_versi', $versi_id, ['pengumpulan_id' => $pengumpulan_id, 'catatan' => $catatan_approval]);
        notifySiswaRevisiApproved($pengumpulan_id, (int)$versi['nomor_versi'], $guru_id, $catatan_approval);
        
        return ['success' => true, 'message' => 'Revisi berhasil disetujui', 'versi_id' => $versi_id];
    } catch (Exception $e) {
        error_log("[approveRevision] Error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()];
    }
}

function rejectRevision(int $versi_id, int $guru_id, string $catatan_rejection = ''): array {
    try {
        if (empty($catatan_rejection)) return ['success' => false, 'message' => 'Catatan penolakan wajib diisi'];
        
        $versi = sirey_fetch(sirey_query('SELECT pengumpulan_id FROM pengumpulan_versi_rayhanRP WHERE versi_id = ?', 'i', $versi_id));
        if (!$versi) return ['success' => false, 'message' => 'Versi tidak ditemukan'];
        
        $pengumpulan_id = (int)$versi['pengumpulan_id'];
        
        $updateResult = sirey_execute(
            'UPDATE pengumpulan_versi_rayhanRP SET status_approval = "ditolak", disetujui_oleh = ?, catatan_approval = ?, diubah_pada = NOW() WHERE versi_id = ?',
            'isi', $guru_id, $catatan_rejection, $versi_id
        );
        if ($updateResult <= 0) return ['success' => false, 'message' => 'Gagal menyimpan rejection'];
        
        auditLog($guru_id, 'REVISI_REJECTED', 'pengumpulan_versi', $versi_id, ['pengumpulan_id' => $pengumpulan_id, 'catatan' => $catatan_rejection]);
        notifySiswaRevisiRejected($pengumpulan_id, (int)$versi['nomor_versi'], $guru_id, $catatan_rejection);
        
        return ['success' => true, 'message' => 'Revisi ditolak', 'versi_id' => $versi_id];
    } catch (Exception $e) {
        error_log("[rejectRevision] Error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()];
    }
}

// ========== AJAX HANDLERS ==========
$aksi = (string)($_GET['action'] ?? '');

if (!empty($aksi)) {
    startSession();
    $guru_id = (int)($_SESSION['admin_id'] ?? 0);
    $role = (string)($_SESSION['admin_role'] ?? '');
    
    // ── Ambil daftar pengumpulan per tugas ────────────────────────────────────
    if ($aksi === 'get_pengumpulan' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        header('Content-Type: text/html; charset=utf-8');
        $tugas_id = (int)($_GET['tugas_id'] ?? 0);

        $data_tugas = sirey_fetch(sirey_query(
            'SELECT t.tugas_id, t.judul, t.tenggat, t.poin_maksimal, mp.nama AS matpel_nama, g.nama_grup
             FROM tugas_rayhanRP t
             LEFT JOIN grup_rayhanRP g ON t.grup_id = g.grup_id
             LEFT JOIN mata_pelajaran_rayhanRP mp ON t.matpel_id = mp.matpel_id
             WHERE t.tugas_id = ?', 'i', $tugas_id
        ));
        if (!$data_tugas) { echo '<p style="color:red;">Tugas tidak ditemukan.</p>'; exit; }

        $daftar_pengumpulan = sirey_fetchAll(sirey_query(
            'SELECT p.pengumpulan_id, p.akun_id, a.nama_lengkap, a.nis_nip, p.status, p.waktu_kumpul, p.via,
                    p.teks_jawaban, p.file_path, p.file_nama_asli, p.link_jawaban,
                    pn.penilaian_id, pn.nilai, pn.status_lulus, pn.catatan_guru, pn.dinilai_pada
             FROM pengumpulan_rayhanRP p
             INNER JOIN akun_rayhanRP a ON p.akun_id = a.akun_id
             LEFT JOIN penilaian_rayhanRP pn ON pn.pengumpulan_id = p.pengumpulan_id
             WHERE p.tugas_id = ? ORDER BY a.nama_lengkap ASC',
            'i', $tugas_id
        ));

        $poin_maksimal = (int)$data_tugas['poin_maksimal'];
        $sudah_dinilai = count(array_filter($daftar_pengumpulan, fn($s) => $s['nilai'] !== null));
        $belum_dinilai = count($daftar_pengumpulan) - $sudah_dinilai;
        ?>
<div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:14px; margin-bottom:18px;">
    <div style="font-size:14px; font-weight:bold; color:#1e293b; margin-bottom:4px;">
        📝 <?php echo htmlspecialchars($data_tugas['judul']); ?>
    </div>
    <div style="font-size:13px; color:#64748b; display:flex; gap:20px; flex-wrap:wrap;">
        <?php if (!empty($data_tugas['matpel_nama'])): ?>
            <span>📚 <?php echo htmlspecialchars((string)$data_tugas['matpel_nama']); ?></span>
        <?php endif; ?>
        <span>🎓 <?php echo htmlspecialchars($data_tugas['nama_grup'] ?? '-'); ?></span>
        <span>💯 Nilai Maks: <?php echo $poin_maksimal; ?></span>
        <span>📊 <?php echo $sudah_dinilai; ?>/<?php echo count($daftar_pengumpulan); ?> sudah dinilai</span>
        <?php if ($belum_dinilai > 0): ?>
            <span style="color:#b45309; font-weight:600;">⏳ <?php echo $belum_dinilai; ?> belum dinilai</span>
        <?php endif; ?>
    </div>
</div>

<?php if (empty($daftar_pengumpulan)): ?>
    <p style="text-align:center; color:#999; padding:20px;">Belum ada murid yang mengumpulkan tugas ini.</p>
<?php else: ?>
<table style="width:100%; border-collapse:collapse; font-size:13px;">
    <thead>
        <tr style="background:#f1f5f9; border-bottom:2px solid #e2e8f0;">
            <th style="padding:10px; text-align:left;">Murid</th>
            <th style="padding:10px; text-align:center;">Status</th>
            <th style="padding:10px; text-align:center;">Waktu Kumpul</th>
            <th style="padding:10px; text-align:center;">Jawaban</th>
            <th style="padding:10px; text-align:center; width:260px;">Nilai & Catatan</th>
            <th style="padding:10px; text-align:center;">Aksi</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($daftar_pengumpulan as $s): ?>
        <tr style="border-bottom:1px solid #e2e8f0;" id="row-<?php echo $s['pengumpulan_id']; ?>">
            <td style="padding:10px;">
                <strong><?php echo htmlspecialchars($s['nama_lengkap']); ?></strong>
                <br><small style="color:#94a3b8;"><?php echo htmlspecialchars($s['nis_nip']); ?></small>
            </td>

            <td style="padding:10px; text-align:center;">
                <?php
                $statusStyle = match($s['status']) {
                    'dikumpulkan' => ['✅ Dikumpulkan', '#dcfce7', '#166534'],
                    'terlambat' => ['⚠️ Terlambat', '#fef3c7', '#b45309'],
                    'graded' => ['✔️ Dinilai', '#dbeafe', '#0c4a6e'],
                    default => ['❌ Belum', '#fee2e2', '#b91c1c']
                };
                ?>
                <span style="background:<?php echo $statusStyle[1]; ?>; color:<?php echo $statusStyle[2]; ?>;
                             padding:4px 8px; border-radius:4px; font-weight:500; font-size:12px;">
                    <?php echo $statusStyle[0]; ?>
                </span>
                <br><small style="color:#94a3b8; margin-top:2px; display:block;">via <?php echo ucfirst($s['via'] ?? 'web'); ?></small>
            </td>

            <td style="padding:10px; text-align:center; color:#64748b; font-size:12px; white-space:nowrap;">
                <?php echo formatDatetime($s['waktu_kumpul']); ?>
            </td>

            <td style="padding:10px; text-align:center;">
                <?php if (!empty($s['file_path'])): ?>
                    <a href="<?php echo htmlspecialchars('../' . $s['file_path']); ?>" target="_blank" style="color:#0369a1; text-decoration:none; font-weight:500;">📎 File</a>
                <?php elseif (!empty($s['link_jawaban'])): ?>
                    <a href="<?php echo htmlspecialchars($s['link_jawaban']); ?>" target="_blank" style="color:#0369a1; text-decoration:none; font-weight:500;">🔗 Link</a>
                <?php elseif (!empty($s['teks_jawaban'])): ?>
                    <button onclick="lihatTeks(<?php echo htmlspecialchars(json_encode($s['teks_jawaban'])); ?>, '<?php echo htmlspecialchars($s['nama_lengkap']); ?>')" style="background:none; border:none; color:#0369a1; cursor:pointer; font-weight:500; text-decoration:underline;">💬 Lihat</button>
                <?php else: ?>
                    <span style="color:#94a3b8; font-size:12px;">—</span>
                <?php endif; ?>
            </td>

            <td style="padding:10px;">
                <div style="display:flex; flex-direction:column; gap:6px;">
                    <?php if ($role === 'guru'): ?>
                        <input type="number" id="nilai-<?php echo $s['pengumpulan_id']; ?>" value="<?php echo $s['nilai'] ?? ''; ?>" min="0" max="<?php echo $poin_maksimal; ?>" step="0.01" placeholder="Nilai" style="width:100%; padding:6px; border:1px solid #ddd; border-radius:4px; box-sizing:border-box; font-size:12px;" oninput="updateNilaiBar(<?php echo $s['pengumpulan_id']; ?>, this.value, <?php echo $poin_maksimal; ?>)">
                        <div id="bar-<?php echo $s['pengumpulan_id']; ?>" style="height:4px; background:#ef4444; border-radius:2px; width:<?php echo $s['nilai'] ? (int)(($s['nilai'] / $poin_maksimal) * 100) : 0; ?>%;"></div>
                    <?php else: ?>
                        <span style="font-weight:bold; color:#1e293b;"><?php echo $s['nilai'] !== null ? $s['nilai'] : '—'; ?> / <?php echo $poin_maksimal; ?></span>
                    <?php endif; ?>
                    
                    <?php if ($role === 'guru'): ?>
                        <select id="lulus-<?php echo $s['pengumpulan_id']; ?>" style="padding:4px 6px; border:1px solid #ddd; border-radius:4px; font-size:12px;">
                            <option value="lulus" <?php echo ($s['status_lulus'] ?? 'lulus') === 'lulus' ? 'selected' : ''; ?>>✅ Lulus</option>
                            <option value="tidak_lulus" <?php echo ($s['status_lulus'] ?? '') === 'tidak_lulus' ? 'selected' : ''; ?>>❌ Tidak Lulus</option>
                            <option value="revisi" <?php echo ($s['status_lulus'] ?? '') === 'revisi' ? 'selected' : ''; ?>>✏️ Revisi</option>
                        </select>
                    <?php else: ?>
                        <span style="font-size:12px; color:#64748b;"><?php echo match($s['status_lulus'] ?? 'lulus') { 'lulus' => '✅ Lulus', 'tidak_lulus' => '❌ Tidak Lulus', 'revisi' => '✏️ Revisi', default => '—' }; ?></span>
                    <?php endif; ?>
                    
                    <?php if ($role === 'guru'): ?>
                        <textarea id="catatan-<?php echo $s['pengumpulan_id']; ?>" placeholder="Catatan guru..." style="width:100%; padding:6px; border:1px solid #ddd; border-radius:4px; font-size:12px; font-family:inherit; resize:vertical; min-height:40px; box-sizing:border-box;"><?php echo htmlspecialchars($s['catatan_guru'] ?? ''); ?></textarea>
                    <?php else: ?>
                        <small style="color:#64748b; display:block; line-height:1.4;"><?php echo htmlspecialchars($s['catatan_guru'] ?? '(tidak ada catatan)'); ?></small>
                    <?php endif; ?>
                </div>
            </td>

            <td style="padding:10px; text-align:center; white-space:nowrap;">
                <div style="display:flex; flex-direction:column; gap:6px; align-items:center;">
                    <?php if ($role === 'guru'): ?>
                        <button onclick="simpanNilai(<?php echo $s['pengumpulan_id']; ?>)" style="padding:4px 10px; background:#16a34a; color:white; border:none; border-radius:4px; cursor:pointer; font-size:12px; font-weight:500;">💾 Simpan</button>
                    <?php endif; ?>
                    <?php if (!empty($s['penilaian_id'])): ?>
                        <button onclick="lihatRiwayat(<?php echo $s['penilaian_id']; ?>, '<?php echo htmlspecialchars($s['nama_lengkap']); ?>')" style="padding:4px 10px; background:#6366f1; color:white; border:none; border-radius:4px; cursor:pointer; font-size:12px;">📜 Riwayat</button>
                    <?php endif; ?>
                </div>
                <div id="notif-<?php echo $s['pengumpulan_id']; ?>" style="margin-top:4px; font-size:11px; height:14px;"></div>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
        <?php
        exit;
    }

    // ── Simpan nilai satu pengumpulan ────────────────────────────────────────
    if ($aksi === 'simpan_nilai' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json; charset=utf-8');

        $pengumpulan_id = (int)($_POST['pengumpulan_id'] ?? 0);
        $nilai = (float)($_POST['nilai'] ?? 0);
        $status_lulus = in_array($_POST['status_lulus'] ?? '', ['lulus','tidak_lulus','revisi'])
                        ? $_POST['status_lulus'] : 'lulus';
        $catatan_guru = trim((string)($_POST['catatan_guru'] ?? ''));

        if ($pengumpulan_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID pengumpulan tidak valid.']);
            exit;
        }

        if ($role !== 'guru') {
            echo json_encode(['success' => false, 'message' => 'Hanya guru dapat memberikan nilai.']);
            exit;
        }

        $data_pengumpulan = sirey_fetch(sirey_query(
            'SELECT p.tugas_id, t.poin_maksimal FROM pengumpulan_rayhanRP p 
             INNER JOIN tugas_rayhanRP t ON p.tugas_id = t.tugas_id WHERE p.pengumpulan_id = ?', 'i', $pengumpulan_id
        ));
        if (!$data_pengumpulan) {
            echo json_encode(['success' => false, 'message' => 'Pengumpulan tidak ditemukan.']);
            exit;
        }
        
        $poin_maksimal = (int)$data_pengumpulan['poin_maksimal'];
        if ($nilai < 0 || $nilai > $poin_maksimal) {
            echo json_encode(['success' => false, 'message' => "Nilai harus 0 - {$poin_maksimal}."]);
            exit;
        }

        $data_penilaian_existing = sirey_fetch(sirey_query(
            'SELECT penilaian_id FROM penilaian_rayhanRP WHERE pengumpulan_id = ?', 'i', $pengumpulan_id
        ));

        if ($data_penilaian_existing) {
            sirey_execute(
                'UPDATE penilaian_rayhanRP SET nilai = ?, status_lulus = ?, catatan_guru = ?, dinilai_oleh = ?, dinilai_pada = NOW() WHERE penilaian_id = ?',
                'dssii',
                $nilai, $status_lulus, $catatan_guru, $guru_id, (int)$data_penilaian_existing['penilaian_id']
            );
        } else {
            sirey_execute(
                'INSERT INTO penilaian_rayhanRP (pengumpulan_id, dinilai_oleh, nilai, status_lulus, catatan_guru, dinilai_pada)
                 VALUES (?, ?, ?, ?, ?, NOW())',
                'iidss',
                $pengumpulan_id, $guru_id, $nilai, $status_lulus, $catatan_guru
            );
        }

        $data_tugas = sirey_fetch(sirey_query(
            'SELECT t.judul FROM tugas_rayhanRP t
             INNER JOIN pengumpulan_rayhanRP p ON p.tugas_id = t.tugas_id WHERE p.pengumpulan_id = ?', 'i', $pengumpulan_id
        ));
        $data_user_tg = sirey_fetch(sirey_query(
            'SELECT at.telegram_chat_id FROM akun_telegram_rayhanRP at
             INNER JOIN pengumpulan_rayhanRP p ON at.akun_id = p.akun_id WHERE p.pengumpulan_id = ?', 'i', $pengumpulan_id
        ));

        if ($data_user_tg && !empty($data_user_tg['telegram_chat_id']) && $data_tugas) {
            $emoji_status = match($status_lulus) { 'lulus' => '✅ Lulus', 'revisi' => '✏️ Revisi', default => '❌ Tidak Lulus' };
            $pesan = "🎉 *Tugas Anda Sudah Dinilai!*\n\nTugas: *{$data_tugas['judul']}*\nNilai: *{$nilai}*\nStatus: {$emoji_status}";
            if ($catatan_guru) $pesan .= "\nCatatan:\n_{$catatan_guru}_";
            if ($status_lulus === 'revisi') $pesan .= "\n\n📝 *REVISI DIMINTA*\nSilakan perbaiki dan resubmit.";
            $pesan .= "\n\n— Bot SiRey";
            sendTelegramMessage((int)$data_user_tg['telegram_chat_id'], $pesan);
        }

        echo json_encode(['success' => true, 'message' => 'Nilai tersimpan.']);
        exit;
    }

    // Lihat riwayat nilai
    if ($aksi === 'get_riwayat' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        header('Content-Type: text/html; charset=utf-8');
        $penilaian_id = (int)($_GET['penilaian_id'] ?? 0);

        $penilaian = sirey_fetch(sirey_query(
            'SELECT pn.nilai, pn.status_lulus, pn.catatan_guru, pn.dinilai_pada, a.nama_lengkap
             FROM penilaian_rayhanRP pn LEFT JOIN akun_rayhanRP a ON pn.dinilai_oleh = a.akun_id
             WHERE pn.penilaian_id = ?', 'i', $penilaian_id
        ));

        if (!$penilaian) {
            echo '<p style="color:#999; text-align:center; padding:20px;">Tidak ada riwayat.</p>';
            exit;
        }
        ?>
<table style="width:100%; border-collapse:collapse; font-size:13px;">
    <tr style="border-bottom:1px solid #e2e8f0;">
        <td style="padding:10px; font-weight:500; color:#475569;">Nilai:</td>
        <td style="padding:10px; color:#1e293b;"><?php echo $penilaian['nilai']; ?></td>
    </tr>
    <tr style="border-bottom:1px solid #e2e8f0;">
        <td style="padding:10px; font-weight:500; color:#475569;">Status:</td>
        <td style="padding:10px;"><?php echo match($penilaian['status_lulus']) { 'lulus' => '<span style="background:#dcfce7; color:#166534; padding:2px 6px; border-radius:3px;">✅ Lulus</span>', 'tidak_lulus' => '<span style="background:#fee2e2; color:#b91c1c; padding:2px 6px; border-radius:3px;">❌ Tidak Lulus</span>', 'revisi' => '<span style="background:#fef3c7; color:#b45309; padding:2px 6px; border-radius:3px;">✏️ Revisi</span>', default => '—' }; ?></td>
    </tr>
    <tr style="border-bottom:1px solid #e2e8f0;">
        <td style="padding:10px; font-weight:500; color:#475569;">Catatan:</td>
        <td style="padding:10px; color:#555; font-size:12px;"><?php echo htmlspecialchars($penilaian['catatan_guru'] ?? '(tidak ada)'); ?></td>
    </tr>
    <tr>
        <td style="padding:10px; font-weight:500; color:#475569;">Dinilai:</td>
        <td style="padding:10px; color:#94a3b8; font-size:12px;"><strong><?php echo htmlspecialchars($penilaian['nama_lengkap'] ?? '—'); ?></strong> pada <?php echo formatDatetime($penilaian['dinilai_pada']); ?></td>
    </tr>
</table>
        <?php
        exit;
    }

    // Revision handlers
    if ($aksi === 'approve_revision' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json; charset=utf-8');
        $versi_id = (int)($_POST['versi_id'] ?? 0);
        $catatan = trim((string)($_POST['catatan_approval'] ?? ''));
        if ($versi_id <= 0) { echo json_encode(['success' => false]); exit; }
        $check = sirey_fetch(sirey_query('SELECT t.pembuat_id FROM pengumpulan_versi_rayhanRP pv INNER JOIN pengumpulan_rayhanRP p ON pv.pengumpulan_id = p.pengumpulan_id INNER JOIN tugas_rayhanRP t ON p.tugas_id = t.tugas_id WHERE pv.versi_id = ?', 'i', $versi_id));
        if (!$check || (int)$check['pembuat_id'] !== $guru_id) { echo json_encode(['success' => false]); exit; }
        echo json_encode(approveRevision($versi_id, $guru_id, $catatan));
        exit;
    }

    if ($aksi === 'reject_revision' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json; charset=utf-8');
        $versi_id = (int)($_POST['versi_id'] ?? 0);
        $catatan = trim((string)($_POST['catatan_rejection'] ?? ''));
        if ($versi_id <= 0 || empty($catatan)) { echo json_encode(['success' => false]); exit; }
        $check = sirey_fetch(sirey_query('SELECT t.pembuat_id FROM pengumpulan_versi_rayhanRP pv INNER JOIN pengumpulan_rayhanRP p ON pv.pengumpulan_id = p.pengumpulan_id INNER JOIN tugas_rayhanRP t ON p.tugas_id = t.tugas_id WHERE pv.versi_id = ?', 'i', $versi_id));
        if (!$check || (int)$check['pembuat_id'] !== $guru_id) { echo json_encode(['success' => false]); exit; }
        echo json_encode(rejectRevision($versi_id, $guru_id, $catatan));
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenal']);
    exit;
}

// ========== RENDER PAGE ==========
startSession();
$data_admin = requireAdminSession('login.php');
$judul_halaman_rayhanrp = 'Penilaian Tugas';
$menu_aktif_rayhanrp = 'penilaian';
require_once __DIR__ . '/_layout.php';

$id_guru = (int)($data_admin['id'] ?? 0);
$role_guru = (string)($data_admin['role'] ?? 'guru');

$where_clause = $role_guru === 'guru' ? 'WHERE t.pembuat_id = ?' : '';
$where_params = $role_guru === 'guru' ? [$id_guru] : [];

$daftar_tugas = sirey_fetchAll(sirey_query(
    'SELECT t.tugas_id, t.judul, t.tipe_tugas, t.grup_id, mp.nama AS matpel_nama, t.tenggat, t.poin_maksimal, t.status,
            (SELECT COUNT(*) FROM pengumpulan_rayhanRP p WHERE p.tugas_id = t.tugas_id) AS total_kumpul,
            (SELECT COUNT(*) FROM pengumpulan_rayhanRP p WHERE p.tugas_id = t.tugas_id 
             AND NOT EXISTS (SELECT 1 FROM penilaian_rayhanRP pn WHERE pn.pengumpulan_id = p.pengumpulan_id AND pn.nilai IS NOT NULL)) AS belum_dinilai,
            (SELECT COUNT(*) FROM pengumpulan_rayhanRP p WHERE p.tugas_id = t.tugas_id 
             AND EXISTS (SELECT 1 FROM penilaian_rayhanRP pn WHERE pn.pengumpulan_id = p.pengumpulan_id AND pn.nilai IS NOT NULL)) AS sudah_dinilai,
            (SELECT AVG(pn.nilai) FROM pengumpulan_rayhanRP p LEFT JOIN penilaian_rayhanRP pn ON pn.pengumpulan_id = p.pengumpulan_id 
             WHERE p.tugas_id = t.tugas_id AND pn.nilai IS NOT NULL) AS rata_nilai,
            CASE WHEN t.tipe_tugas = "grup" 
                 THEN (SELECT COUNT(*) FROM grup_anggota_rayhanRP ga INNER JOIN akun_rayhanRP a ON ga.akun_id = a.akun_id 
                       WHERE ga.grup_id = t.grup_id AND ga.aktif = 1 AND a.role = "siswa")
                 ELSE (SELECT COUNT(*) FROM tugas_perorang_rayhanRP WHERE tugas_id = t.tugas_id) 
            END AS total_siswa
     FROM tugas_rayhanRP t LEFT JOIN mata_pelajaran_rayhanRP mp ON t.matpel_id = mp.matpel_id
     ' . $where_clause . ' ORDER BY t.tenggat DESC',
    empty($where_params) ? '' : 'i', ...$where_params
));

$total_belum_dinilai = (int)array_sum(array_column($daftar_tugas, 'belum_dinilai'));
$total_sudah_dinilai = (int)array_sum(array_column($daftar_tugas, 'sudah_dinilai'));
$total_tugas = count($daftar_tugas);
$pending_revisions = $role_guru === 'guru' ? getPendingRevisions($id_guru) : [];
?>

<div class="page-header">
  <h2>⭐ Penilaian Tugas</h2>
  <p>Berikan nilai, catatan, dan feedback untuk setiap pengumpulan murid.</p>
</div>

<!-- PENDING REVISIONS ALERT -->
<?php if ($role_guru === 'guru' && !empty($pending_revisions)): ?>
<div style="background:#fff5e6; border:1px solid #ffd666; border-radius:8px; padding:16px; margin-bottom:20px;">
    <h3 style="margin-top:0; color:#b87803;">📝 Revisi Menunggu Persetujuan (<?php echo count($pending_revisions); ?>)</h3>
    <div style="display:flex; flex-direction:column; gap:10px;">
        <?php foreach ($pending_revisions as $rev): ?>
        <div style="background:white; border:1px solid #ffd666; border-radius:6px; padding:12px; display:flex; justify-content:space-between; align-items:center;">
            <div>
                <strong><?php echo htmlspecialchars($rev['siswa_nama']); ?></strong> 
                <span style="color:#999;">— <?php echo htmlspecialchars($rev['tugas_judul']); ?> (v<?php echo $rev['nomor_versi']; ?>)</span>
            </div>
            <div style="display:flex; gap:8px;">
                <button onclick="approveRevisionUI(<?php echo $rev['versi_id']; ?>)" style="padding:6px 12px; background:#16a34a; color:white; border:none; border-radius:4px; cursor:pointer; font-size:12px;">✅ Setujui</button>
                <button onclick="rejectRevisionUI(<?php echo $rev['versi_id']; ?>)" style="padding:6px 12px; background:#dc2626; color:white; border:none; border-radius:4px; cursor:pointer; font-size:12px;">❌ Tolak</button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- STATISTIK -->
<div class="stat-grid" style="margin-bottom:24px;">
  <div class="stat-card">
    <div class="stat-icon blue">📋</div>
    <div><div class="stat-value"><?php echo $total_tugas; ?></div><div class="stat-label">Tugas</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon amber">⏳</div>
    <div><div class="stat-value"><?php echo $total_belum_dinilai; ?></div><div class="stat-label">Belum Dinilai</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green">✅</div>
    <div><div class="stat-value"><?php echo $total_sudah_dinilai; ?></div><div class="stat-label">Sudah Dinilai</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon purple">📊</div>
    <div><div class="stat-value"><?php echo $total_tugas > 0 ? round(($total_sudah_dinilai / ($total_sudah_dinilai + $total_belum_dinilai)) * 100) : 0; ?>%</div><div class="stat-label">Progress</div></div>
  </div>
</div>

<!-- DAFTAR TUGAS -->
<div class="card">
  <div class="card-header"><h3>Daftar Tugas</h3></div>
  <?php if (empty($daftar_tugas)): ?>
    <div class="empty-state"><div class="empty-icon">⭐</div><p>Belum ada tugas.</p></div>
  <?php else: ?>
    <table class="data-table">
      <thead>
        <tr><th>Tugas</th><th>Mapel</th><th>Tenggat</th><th>Pengumpulan</th><th>Progress</th><th>Aksi</th></tr>
      </thead>
      <tbody>
        <?php foreach ($daftar_tugas as $t): ?>
        <tr>
          <td><strong><?php echo htmlspecialchars($t['judul']); ?></strong></td>
          <td><?php echo htmlspecialchars($t['matpel_nama'] ?? '—'); ?></td>
          <td><small><?php echo formatDatetime($t['tenggat']); ?></small></td>
          <td>
            <?php $belum_kumpul = (int)$t['total_siswa'] - (int)$t['total_kumpul']; ?>
            <span style="background:#e0e7ff; color:#3730a3; padding:6px 10px; border-radius:4px; font-size:12px; font-weight:500;">
              <?php echo (int)$t['total_kumpul']; ?> / <?php echo (int)$t['total_siswa']; ?>
            </span>
            <br>
            <small style="color:#64748b; display:block; margin-top:4px;">
              ✅ <?php echo (int)$t['total_kumpul']; ?> mengumpulkan
              <br>❌ <?php echo $belum_kumpul; ?> belum
            </small>
          </td>
          <td>
            <div style="background:#e5e7eb; height:20px; border-radius:3px; overflow:hidden;">
              <?php $pct = $t['total_siswa'] > 0 ? round(($t['sudah_dinilai'] / $t['total_siswa']) * 100) : 0; ?>
              <div style="background:#16a34a; height:100%; width:<?php echo $pct; ?>%;"></div>
            </div>
          </td>
          <td><button onclick="bukaPenilaian(<?php echo $t['tugas_id']; ?>, '<?php echo htmlspecialchars($t['judul']); ?>')" style="padding:6px 12px; background:#0369a1; color:white; border:none; border-radius:4px; cursor:pointer; font-size:12px;">📊 Buka</button></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<!-- MODAL PENILAIAN -->
<div id="penilaianModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.55); z-index:1000; align-items:flex-start; justify-content:center; overflow-y:auto; padding:20px 0;">
  <div style="background:white; border-radius:10px; max-width:1200px; width:95%; margin:0 auto;">
    <div style="display:flex; justify-content:space-between; align-items:center; padding:20px 24px; border-bottom:1px solid #e2e8f0; position:sticky; top:0; background:white; border-radius:10px 10px 0 0; z-index:1;">
      <div id="penilaianJudul" style="font-weight:600;"></div>
      <button type="button" onclick="tutupPenilaian()" style="background:none; border:none; font-size:22px; cursor:pointer; color:#999;">✕</button>
    </div>
    <div id="penilaianContent" style="padding:20px; min-height:200px;"><p style="text-align:center; color:#94a3b8; padding:40px;">Memuat...</p></div>
    <?php if ($role_guru === 'guru'): ?>
    <div style="padding:16px 24px; border-top:1px solid #e2e8f0; background:#f8fafc; display:flex; justify-content:flex-end; gap:10px;">
      <button type="button" onclick="tutupPenilaian()" style="padding:8px 16px; border:1px solid #ddd; background:#f5f5f5; border-radius:4px; cursor:pointer;">Tutup</button>
      <button type="button" id="btnSimpanSemua" onclick="simpanSemuaNilai()" style="padding:8px 16px; background:#16a34a; color:white; border:none; border-radius:4px; cursor:pointer;">💾 Simpan Semua</button>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- MODAL TEKS -->
<div id="teksModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.55); z-index:1100; align-items:center; justify-content:center;">
  <div style="background:white; border-radius:10px; max-width:600px; width:92%; padding:24px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
      <h4 style="margin:0;">💬 Jawaban: <span id="teksNama"></span></h4>
      <button type="button" onclick="tutupTeks()" style="background:none; border:none; font-size:22px; cursor:pointer; color:#999;">✕</button>
    </div>
    <div id="teksIsi" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:16px; font-size:14px; line-height:1.7; white-space:pre-wrap; max-height:400px; overflow-y:auto; color:#1e293b;"></div>
  </div>
</div>

<!-- MODAL RIWAYAT -->
<div id="riwayatModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.55); z-index:1100; align-items:center; justify-content:center;">
  <div style="background:white; border-radius:10px; max-width:700px; width:92%; padding:24px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
      <h4 style="margin:0;">📜 Riwayat: <span id="riwayatNama"></span></h4>
      <button type="button" onclick="tutupRiwayat()" style="background:none; border:none; font-size:22px; cursor:pointer; color:#999;">✕</button>
    </div>
    <div id="riwayatContent" style="max-height:400px; overflow-y:auto;"><p style="text-align:center; color:#999;">Memuat...</p></div>
  </div>
</div>

<!-- MODAL APPROVE REVISI -->
<div id="modal-approve" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:1200; align-items:center; justify-content:center;">
    <div style="background:white; padding:24px; border-radius:8px; min-width:400px;">
        <h3>Setujui Revisi</h3>
        <textarea id="catatan-approve" placeholder="Catatan (opsional)..." style="width:100%; height:100px; padding:10px; border:1px solid #ddd; margin-bottom:15px; box-sizing:border-box;"></textarea>
        <div style="display:flex; gap:10px; justify-content:flex-end;">
            <button onclick="document.getElementById('modal-approve').style.display='none'" style="padding:8px 16px; border:1px solid #ddd; background:#f5f5f5; border-radius:4px; cursor:pointer;">Batal</button>
            <button onclick="submitApproveRevision()" style="padding:8px 16px; background:#16a34a; color:white; border:none; border-radius:4px; cursor:pointer;">Setujui</button>
        </div>
    </div>
</div>

<!-- MODAL REJECT REVISI -->
<div id="modal-reject" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:1200; align-items:center; justify-content:center;">
    <div style="background:white; padding:24px; border-radius:8px; min-width:400px;">
        <h3>Tolak Revisi</h3>
        <textarea id="catatan-reject" placeholder="Alasan (WAJIB)..." style="width:100%; height:100px; padding:10px; border:1px solid #ddd; margin-bottom:15px; box-sizing:border-box;" required></textarea>
        <div style="display:flex; gap:10px; justify-content:flex-end;">
            <button onclick="document.getElementById('modal-reject').style.display='none'" style="padding:8px 16px; border:1px solid #ddd; background:#f5f5f5; border-radius:4px; cursor:pointer;">Batal</button>
            <button onclick="submitRejectRevision()" style="padding:8px 16px; background:#dc2626; color:white; border:none; border-radius:4px; cursor:pointer;">Tolak</button>
        </div>
    </div>
</div>

<script>
let currentTugasId = null, currentVersiId = 0;

function bukaPenilaian(tugasId, judul) {
  currentTugasId = tugasId;
  document.getElementById('penilaianJudul').textContent = judul;
  document.getElementById('penilaianContent').innerHTML = '<p style="text-align:center; color:#94a3b8; padding:40px;">⏳ Memuat...</p>';
  document.getElementById('penilaianModal').style.display = 'flex';
  fetch('./penilaian.php?action=get_pengumpulan&tugas_id=' + tugasId)
    .then(r => r.text())
    .then(html => { document.getElementById('penilaianContent').innerHTML = html; })
    .catch(() => { document.getElementById('penilaianContent').innerHTML = '<p style="color:red; text-align:center; padding:40px;">❌ Gagal</p>'; });
}
function tutupPenilaian() { document.getElementById('penilaianModal').style.display = 'none'; }
document.getElementById('penilaianModal').addEventListener('click', function(e) { if (e.target === this) tutupPenilaian(); });

function simpanNilai(pid) {
  const nilaiEl = document.getElementById('nilai-' + pid), lulusEl = document.getElementById('lulus-' + pid), catatanEl = document.getElementById('catatan-' + pid), notifEl = document.getElementById('notif-' + pid);
  if (!nilaiEl) return;
  const nilai = parseFloat(nilaiEl.value);
  if (isNaN(nilai) || nilai < 0) { notifEl.textContent = '⚠ Isi nilai!'; notifEl.style.color = '#b45309'; return; }
  const fd = new FormData(); fd.append('pengumpulan_id', pid); fd.append('nilai', nilai); fd.append('status_lulus', lulusEl ? lulusEl.value : 'lulus'); fd.append('catatan_guru', catatanEl ? catatanEl.value : '');
  notifEl.textContent = '⏳...'; notifEl.style.color = '#64748b';
  fetch('./penilaian.php?action=simpan_nilai', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => { if (d.success) { notifEl.textContent = '✅'; notifEl.style.color = '#16a34a'; setTimeout(() => { notifEl.textContent = ''; }, 2000); } else { notifEl.textContent = '❌'; notifEl.style.color = '#dc2626'; } })
    .catch(() => { notifEl.textContent = '❌'; });
}

function updateNilaiBar(pid, val, maks) {
  const bar = document.getElementById('bar-' + pid);
  if (!bar) return;
  const v = Math.max(0, Math.min(maks, parseFloat(val) || 0)), pct = Math.round(v / maks * 100);
  bar.style.width = pct + '%';
  bar.style.background = v >= 70 ? '#22c55e' : (v >= 50 ? '#f59e0b' : '#ef4444');
}

function simpanSemuaNilai() {
  const inputs = document.querySelectorAll('[id^="nilai-"]');
  if (!inputs.length) { alert('Tidak ada data.'); return; }
  const items = [];
  let kosong = 0;
  inputs.forEach(input => {
    const pid = input.id.replace('nilai-', '');
    const nilai = parseFloat(input.value);
    if (isNaN(nilai)) { kosong++; return; }
    const lulus = document.getElementById('lulus-' + pid);
    const catatan = document.getElementById('catatan-' + pid);
    items.push({ pengumpulan_id: parseInt(pid), nilai: nilai, status_lulus: lulus ? lulus.value : 'lulus', catatan_guru: catatan ? catatan.value : '' });
  });
  if (items.length === 0) { alert('Isi minimal satu nilai.'); return; }
  if (!confirm(`Simpan ${items.length} nilai?`)) return;
  const btn = document.getElementById('btnSimpanSemua');
  btn.textContent = '⏳...'; btn.disabled = true;
  let saved = 0;
  items.forEach(item => {
    const fd = new FormData();
    fd.append('pengumpulan_id', item.pengumpulan_id);
    fd.append('nilai', item.nilai);
    fd.append('status_lulus', item.status_lulus);
    fd.append('catatan_guru', item.catatan_guru);
    fetch('./penilaian.php?action=simpan_nilai', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(d => { if (d.success) saved++; if (saved === items.length) { btn.textContent = '💾 Simpan Semua'; btn.disabled = false; alert('✅ ' + saved + ' nilai tersimpan!'); } });
  });
}

function lihatTeks(teks, nama) { document.getElementById('teksNama').textContent = nama; document.getElementById('teksIsi').textContent = teks; document.getElementById('teksModal').style.display = 'flex'; }
function tutupTeks() { document.getElementById('teksModal').style.display = 'none'; }
document.getElementById('teksModal').addEventListener('click', function(e) { if (e.target === this) tutupTeks(); });

function lihatRiwayat(pid, nama) {
  document.getElementById('riwayatNama').textContent = nama;
  document.getElementById('riwayatContent').innerHTML = '<p style="text-align:center; color:#999; padding:20px;">Memuat...</p>';
  document.getElementById('riwayatModal').style.display = 'flex';
  fetch('./penilaian.php?action=get_riwayat&penilaian_id=' + pid)
    .then(r => r.text())
    .then(html => { document.getElementById('riwayatContent').innerHTML = html; })
    .catch(() => { document.getElementById('riwayatContent').innerHTML = '<p style="color:red; text-align:center;">Gagal.</p>'; });
}
function tutupRiwayat() { document.getElementById('riwayatModal').style.display = 'none'; }
document.getElementById('riwayatModal').addEventListener('click', function(e) { if (e.target === this) tutupRiwayat(); });

function approveRevisionUI(vid) { currentVersiId = vid; document.getElementById('catatan-approve').value = ''; document.getElementById('modal-approve').style.display = 'flex'; }
function rejectRevisionUI(vid) { currentVersiId = vid; document.getElementById('catatan-reject').value = ''; document.getElementById('modal-reject').style.display = 'flex'; }

function submitApproveRevision() {
  if (currentVersiId <= 0) { alert('Error'); return; }
  const catatan = document.getElementById('catatan-approve').value;
  fetch('./penilaian.php?action=approve_revision', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'versi_id=' + currentVersiId + '&catatan_approval=' + encodeURIComponent(catatan) })
    .then(r => r.json())
    .then(d => { if (d.success) { alert('✅ Disetujui!'); document.getElementById('modal-approve').style.display = 'none'; location.reload(); } else { alert('❌ ' + (d.message || 'Gagal')); } })
    .catch(e => alert('Error: ' + e));
}

function submitRejectRevision() {
  const catatan = document.getElementById('catatan-reject').value;
  if (!catatan.trim()) { alert('Alasan wajib diisi!'); return; }
  if (currentVersiId <= 0) { alert('Error'); return; }
  fetch('./penilaian.php?action=reject_revision', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'versi_id=' + currentVersiId + '&catatan_rejection=' + encodeURIComponent(catatan) })
    .then(r => r.json())
    .then(d => { if (d.success) { alert('✅ Ditolak!'); document.getElementById('modal-reject').style.display = 'none'; location.reload(); } else { alert('❌ ' + (d.message || 'Gagal')); } })
    .catch(e => alert('Error: ' + e));
}

document.addEventListener('keydown', function(e) { if (e.key === 'Escape') { document.getElementById('penilaianModal').style.display = 'none'; document.getElementById('teksModal').style.display = 'none'; document.getElementById('riwayatModal').style.display = 'none'; document.getElementById('modal-approve').style.display = 'none'; document.getElementById('modal-reject').style.display = 'none'; } });
</script>

<?php layoutEnd(); ?>
