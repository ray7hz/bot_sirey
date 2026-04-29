<?php
/**
 * telegram/notifikasi_tugas.php
 * Kirim pengingat tugas yang tenggat-nya besok ke murid yang BELUM mengumpulkan.
 *
 * Cron: 0 15 * * * php /path/to/bot_sirey/telegram/notifikasi_tugas.php
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

// Only execute main code if run directly (via cron), not when included as library
if (__FILE__ === $_SERVER['SCRIPT_FILENAME'] ?? null) {

$hari_besok_rayhanrp     = date('Y-m-d', strtotime('+1 day'));
$hari_besok_tampil_rayhanrp = date('d/m/Y', strtotime($hari_besok_rayhanrp));

error_log("[notifikasi_tugas] Menjalankan untuk deadline: {$hari_besok_rayhanrp}");

// Ambil murid yang punya tugas deadline besok DAN BELUM mengumpulkan
$baris_rayhanrp = sirey_fetchAll(sirey_query(
    'SELECT at.telegram_chat_id, a.nama_lengkap,
            t.tugas_id, t.judul, t.deskripsi, t.tenggat, mp.nama AS matpel_nama
     FROM akun_telegram_rayhanRP at
     INNER JOIN akun_rayhanRP a          ON at.akun_id  = a.akun_id
     INNER JOIN grup_anggota_rayhanRP ga ON a.akun_id   = ga.akun_id
     INNER JOIN tugas_rayhanRP t         ON t.grup_id   = ga.grup_id
                                AND DATE(t.tenggat) = ?
                                AND t.status = "active"
     LEFT JOIN mata_pelajaran_rayhanRP mp ON t.matpel_id = mp.matpel_id
     LEFT JOIN pengumpulan_rayhanRP p    ON p.tugas_id  = t.tugas_id
                                AND p.akun_id   = a.akun_id
     WHERE p.pengumpulan_id IS NULL        -- hanya yang BELUM mengumpulkan
     ORDER BY at.telegram_chat_id, t.tenggat ASC',
    's', $hari_besok_rayhanrp
));

if (empty($baris_rayhanrp)) {
    error_log("[notifikasi_tugas] Tidak ada reminder yang perlu dikirim untuk {$hari_besok_rayhanrp}.");
    echo json_encode(['ok' => true, 'sent' => 0, 'deadline' => $hari_besok_rayhanrp]);
    exit;
}

// Kelompokkan per chat_id
$menurut_chat_rayhanrp = [];
foreach ($baris_rayhanrp as $item_rayhanrp) {
    $id_chat_rayhanrp = (int)$item_rayhanrp['telegram_chat_id'];
    if ($id_chat_rayhanrp > 0) {
        $menurut_chat_rayhanrp[$id_chat_rayhanrp][] = $item_rayhanrp;
    }
}

$terkirim_rayhanrp = 0;
foreach ($menurut_chat_rayhanrp as $id_chat_rayhanrp => $daftar_tugas_rayhanrp) {
    $nama_pengguna_rayhanrp  = $daftar_tugas_rayhanrp[0]['nama_lengkap'];
    $pesan_rayhanrp = "⏰ *Pengingat Deadline Tugas*\n\n"
           . "Halo *{$nama_pengguna_rayhanrp}*, tugas berikut harus dikumpulkan *besok, {$hari_besok_tampil_rayhanrp}*:\n\n";

    foreach ($daftar_tugas_rayhanrp as $tugas_item_rayhanrp) {
        $jam_rayhanrp  = date('H:i', strtotime($tugas_item_rayhanrp['tenggat']));
        $deskripsi_rayhanrp = mb_substr((string)$tugas_item_rayhanrp['deskripsi'], 0, 60);

        $pesan_rayhanrp .= "📝 *{$tugas_item_rayhanrp['judul']}*\n";
        if (!empty($tugas_item_rayhanrp['matpel_nama'])) {
            $pesan_rayhanrp .= "   📚 {$tugas_item_rayhanrp['matpel_nama']}\n";
        }
        if ($deskripsi_rayhanrp) {
            $pesan_rayhanrp .= "   {$deskripsi_rayhanrp}" . (strlen((string)$tugas_item_rayhanrp['deskripsi']) > 60 ? '...' : '') . "\n";
        }
        $pesan_rayhanrp .= "   🕐 Pukul {$jam_rayhanrp}\n\n";
    }

    $pesan_rayhanrp .= "_Jangan lupa kumpulkan tepat waktu!_\n"
            . "Ketik /kumpul untuk mengumpulkan sekarang.\n\n"
            . "— Bot SiRey";

    if (sendTelegramMessage($id_chat_rayhanrp, $pesan_rayhanrp)) {
        $terkirim_rayhanrp++;
    }
}

// Log pengiriman
if ($terkirim_rayhanrp > 0) {
    sirey_execute(
        'INSERT INTO notifikasi_rayhanRP (tipe, pesan, jumlah_terkirim)
         VALUES ("tugas", ?, ?)',
        'si',
        "Pengingat deadline {$hari_besok_rayhanrp} dikirim ke {$terkirim_rayhanrp} murid.",
        $terkirim_rayhanrp
    );
}

error_log("[notifikasi_tugas] Selesai. Reminder terkirim ke {$terkirim_rayhanrp} murid.");
echo json_encode(['ok' => true, 'sent' => $terkirim_rayhanrp, 'deadline' => $hari_besok_rayhanrp]);

} // END OF CRON EXECUTION

// ============================================================================
// NOTIFICATION FUNCTIONS FOR REVISION WORKFLOW (NEW)
// ============================================================================

/**
 * Notify guru bahwa ada revisi menunggu approval
 */
function notifyGuruRevisiPending(int $pengumpulan_id, int $nomor_versi, int $akun_id_pengumpul): bool {
    
    if ($pengumpulan_id <= 0 || $nomor_versi <= 0 || $akun_id_pengumpul <= 0) {
        error_log("[notifyGuruRevisiPending] Invalid parameters: pengumpulan_id=$pengumpulan_id, nomor_versi=$nomor_versi, akun_id=$akun_id_pengumpul");
        return false;
    }
    
    // Get all data needed
    $data = sirey_fetch(sirey_query(
        'SELECT 
           p.tugas_id,
           t.judul AS tugas_judul,
           t.pembuat_id,
           a_siswa.nama_lengkap AS siswa_nama,
           a_siswa.nis_nip,
           pv.alasan_revisi,
           pv.dibuat_pada,
           a_guru.telegram_chat_id AS guru_tg_id,
           a_guru.nama_lengkap AS guru_nama
         FROM pengumpulan_rayhanRP p
         INNER JOIN tugas_rayhanRP t ON p.tugas_id = t.tugas_id
         INNER JOIN akun_rayhanRP a_siswa ON p.akun_id = a_siswa.akun_id
         INNER JOIN pengumpulan_versi_rayhanRP pv ON pv.pengumpulan_id = p.pengumpulan_id
         LEFT JOIN akun_rayhanRP a_guru ON t.pembuat_id = a_guru.akun_id
         WHERE p.pengumpulan_id = ?
           AND pv.nomor_versi = ?
           AND pv.versi_tipe = "revisi"
         LIMIT 1',
        'ii', $pengumpulan_id, $nomor_versi
    ));
    
    if (!$data) {
        error_log("[notifyGuruRevisiPending] Data not found: pengumpulan_id=$pengumpulan_id, nomor_versi=$nomor_versi");
        return false;
    }
    
    if (empty($data['guru_tg_id'])) {
        error_log("[notifyGuruRevisiPending] No Telegram chat_id for guru {$data['guru_nama']}");
        return false;
    }
    
    $emoji = "📝";
    $pesan = "{$emoji} *REVISI TUGAS - MENUNGGU PERSETUJUAN*\n\n";
    $pesan .= "📚 Tugas: *{$data['tugas_judul']}*\n";
    $pesan .= "👤 Siswa: *{$data['siswa_nama']}*\n";
    $pesan .= "📝 NIS: {$data['nis_nip']}\n\n";
    $pesan .= "🔄 Revisi Versi: *{$nomor_versi}*\n";
    $pesan .= "⏰ Waktu Submit: " . date('d-m-Y H:i:s', strtotime($data['dibuat_pada'])) . "\n\n";
    
    if (!empty($data['alasan_revisi'])) {
        $pesan .= "💬 Alasan Revisi:\n";
        $pesan .= "_" . substr($data['alasan_revisi'], 0, 200) . "_\n\n";
    }
    
    $pesan .= "🔗 Login ke dashboard untuk approve atau reject revisi ini\n";
    $pesan .= "⏳ Status: Pending\n";
    $pesan .= "\n" . str_repeat("—", 40);
    
    $result = sendTelegramMessage($data['guru_tg_id'], $pesan);
    
    if ($result) {
        error_log("[notifyGuruRevisiPending] ✅ Notification sent to guru {$data['guru_nama']}");
    } else {
        error_log("[notifyGuruRevisiPending] ❌ Failed to send notification to guru {$data['guru_nama']}");
    }
    
    return $result;
}

/**
 * Notify siswa bahwa revisinya disetujui
 */
function notifySiswaRevisiApproved(int $pengumpulan_id, int $nomor_versi, int $guru_id, string $catatan_approval = ''): bool {
    
    if ($pengumpulan_id <= 0 || $nomor_versi <= 0 || $guru_id <= 0) {
        error_log("[notifySiswaRevisiApproved] Invalid parameters: pengumpulan_id=$pengumpulan_id, nomor_versi=$nomor_versi, guru_id=$guru_id");
        return false;
    }
    
    $data = sirey_fetch(sirey_query(
        'SELECT 
           p.tugas_id,
           t.judul AS tugas_judul,
           a_siswa.nama_lengkap AS siswa_nama,
           a_siswa.telegram_chat_id AS siswa_tg_id,
           a_guru.nama_lengkap AS guru_nama
         FROM pengumpulan_rayhanRP p
         INNER JOIN tugas_rayhanRP t ON p.tugas_id = t.tugas_id
         INNER JOIN akun_rayhanRP a_siswa ON p.akun_id = a_siswa.akun_id
         LEFT JOIN akun_rayhanRP a_guru ON a_guru.akun_id = ?
         WHERE p.pengumpulan_id = ?
         LIMIT 1',
        'ii', $guru_id, $pengumpulan_id
    ));
    
    if (!$data) {
        error_log("[notifySiswaRevisiApproved] Data not found: pengumpulan_id=$pengumpulan_id, guru_id=$guru_id");
        return false;
    }
    
    if (empty($data['siswa_tg_id'])) {
        error_log("[notifySiswaRevisiApproved] No Telegram chat_id for student {$data['siswa_nama']}");
        return false;
    }
    
    $emoji = "✅";
    $pesan = "{$emoji} *REVISI TUGAS ANDA TELAH DISETUJUI*\n\n";
    $pesan .= "🎉 Selamat! Revisi Anda telah diterima.\n\n";
    $pesan .= "📚 Tugas: *{$data['tugas_judul']}*\n";
    $pesan .= "🔄 Revisi Versi: *{$nomor_versi}*\n";
    $pesan .= "✔️ Status: *DISETUJUI*\n";
    $pesan .= "👨‍🏫 Oleh: {$data['guru_nama']}\n\n";
    
    if (!empty($catatan_approval)) {
        $pesan .= "💬 Feedback Guru:\n";
        $pesan .= "_" . $catatan_approval . "_\n\n";
    }
    
    $pesan .= "⏰ Waktu: " . date('d-m-Y H:i:s') . "\n";
    
    $result = sendTelegramMessage($data['siswa_tg_id'], $pesan);
    
    if ($result) {
        error_log("[notifySiswaRevisiApproved] ✅ Notification sent to {$data['siswa_nama']}");
    } else {
        error_log("[notifySiswaRevisiApproved] ❌ Failed to send notification to {$data['siswa_nama']}");
    }
    
    return $result;
}

/**
 * Notify siswa bahwa revisinya ditolak
 */
function notifySiswaRevisiRejected(int $pengumpulan_id, int $nomor_versi, int $guru_id, string $catatan_rejection): bool {
    
    if ($pengumpulan_id <= 0 || $nomor_versi <= 0 || $guru_id <= 0) {
        error_log("[notifySiswaRevisiRejected] Invalid parameters: pengumpulan_id=$pengumpulan_id, nomor_versi=$nomor_versi, guru_id=$guru_id");
        return false;
    }
    
    if (empty($catatan_rejection)) {
        error_log("[notifySiswaRevisiRejected] Catatan rejection is required but empty");
        return false;
    }
    
    $data = sirey_fetch(sirey_query(
        'SELECT 
           p.tugas_id,
           t.judul AS tugas_judul,
           t.tenggat,
           a_siswa.nama_lengkap AS siswa_nama,
           a_siswa.telegram_chat_id AS siswa_tg_id,
           a_guru.nama_lengkap AS guru_nama
         FROM pengumpulan_rayhanRP p
         INNER JOIN tugas_rayhanRP t ON p.tugas_id = t.tugas_id
         INNER JOIN akun_rayhanRP a_siswa ON p.akun_id = a_siswa.akun_id
         LEFT JOIN akun_rayhanRP a_guru ON a_guru.akun_id = ?
         WHERE p.pengumpulan_id = ?
         LIMIT 1',
        'ii', $guru_id, $pengumpulan_id
    ));
    
    if (!$data) {
        error_log("[notifySiswaRevisiRejected] Data not found: pengumpulan_id=$pengumpulan_id, guru_id=$guru_id");
        return false;
    }
    
    if (empty($data['siswa_tg_id'])) {
        error_log("[notifySiswaRevisiRejected] No Telegram chat_id for student {$data['siswa_nama']}");
        return false;
    }
    
    $emoji = "❌";
    $pesan = "{$emoji} *REVISI TUGAS ANDA DITOLAK*\n\n";
    $pesan .= "📚 Tugas: *{$data['tugas_judul']}*\n";
    $pesan .= "🔄 Revisi Versi: *{$nomor_versi}*\n";
    $pesan .= "✖️ Status: *DITOLAK*\n";
    $pesan .= "👨‍🏫 Oleh: {$data['guru_nama']}\n\n";
    
    $pesan .= "💬 Alasan Penolakan:\n";
    $pesan .= "_" . $catatan_rejection . "_\n\n";
    
    $pesan .= "📌 *Tindak Lanjut:*\n";
    $pesan .= "1. Baca catatan guru dengan seksama\n";
    $pesan .= "2. Perbaiki revisi sesuai masukan\n";
    $pesan .= "3. Kirim revisi baru melalui /kumpul\n\n";
    
    $pesan .= "⏰ Batas Revisi: " . date('d-m-Y', strtotime($data['tenggat'])) . "\n";
    $pesan .= "Waktu: " . date('d-m-Y H:i:s') . "\n";
    
    $result = sendTelegramMessage($data['siswa_tg_id'], $pesan);
    
    if ($result) {
        error_log("[notifySiswaRevisiRejected] ✅ Notification sent to {$data['siswa_nama']}");
    } else {
        error_log("[notifySiswaRevisiRejected] ❌ Failed to send notification to {$data['siswa_nama']}");
    }
    
    return $result;
}
