<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Jakarta');
/**
 * notifikasi_jadwal.php
 * Kirim notifikasi jadwal harian ke guru & siswa via Telegram.
 *
 * Logika pengiriman per user:
 *   1. Cek apakah jam sekarang = jam_notif_jadwal user
 *   2. Cek apakah sudah dikirim untuk jam ini hari ini (anti double-send)
 *   3. Ambil jadwal hari ini dari DB
 *   4. Kirim via Telegram
 */

require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/functions.php';

$jam   = date('H:i');           // contoh: "10:23"
$hari  = hariIni();             // contoh: "Kamis"
$tgl   = date('d/m/Y');         // contoh: "30/04/2026"
$today = date('Y-m-d');

error_log("[notif_jadwal] ▶ Dijalankan jam={$jam} hari={$hari}");

// ── Ambil semua user Telegram yang notif jadwal aktif ────────────────────────
// Sekalian ambil jam_notif_jadwal mereka untuk di-filter di PHP
// (lebih reliable daripada filter TIME_FORMAT di SQL yang sensitif timezone)
$users = sirey_fetchAll(sirey_query(
    'SELECT a.akun_id, a.nama_lengkap, a.role, at.telegram_chat_id,
            TIME_FORMAT(COALESCE(ns.jam_notif_jadwal, "07:00:00"), "%H:%i") AS jam_notif
     FROM akun_telegram_rayhanrp at
     JOIN akun_rayhanrp a ON at.akun_id = a.akun_id
     LEFT JOIN notif_settings_rayhanrp ns ON ns.akun_id = a.akun_id
     WHERE at.telegram_chat_id > 0
       AND a.aktif = 1
       AND COALESCE(ns.notif_jadwal, 1) = 1',
    ''
));

error_log("[notif_jadwal] Total user terdaftar: " . count($users));

$terkirim = 0;
$diskip   = 0;

foreach ($users as $u) {
    $uid      = (int) $u['akun_id'];
    $chatId   = (int) $u['telegram_chat_id'];
    $role     = $u['role'];
    $jamNotif = $u['jam_notif'];  // "10:23", "07:00", dst

    // ── 1. Cek apakah sekarang waktunya notif untuk user ini ────────────────
    if ($jamNotif !== $jam) {
        // Bukan jamnya — lewati (tidak perlu log, terlalu banyak)
        continue;
    }

    error_log("[notif_jadwal] Proses uid={$uid} ({$u['nama_lengkap']}) jam_notif={$jamNotif}");

    // ── 2. Anti double-send: cek apakah sudah kirim jam ini hari ini ────────
    // Key unik: tipe=jadwal, pesan mengandung marker [uid:X|jam:H:i|tgl:Y-m-d]
    $markerCek = "[uid:{$uid}|jam:{$jam}|tgl:{$today}]";
    $sudah = sirey_fetch(sirey_query(
        'SELECT 1 FROM notifikasi_rayhanrp
         WHERE tipe = "jadwal"
           AND pesan LIKE ?
           AND DATE(waktu_kirim) = ?
         LIMIT 1',
        'ss', "%{$markerCek}%", $today
    ));

    if ($sudah) {
        error_log("[notif_jadwal] ⏭ Skip uid={$uid} — sudah dikirim jam {$jam} hari ini");
        $diskip++;
        continue;
    }

    // ── 3. Ambil jadwal hari ini ─────────────────────────────────────────────
    if ($role === 'guru') {
        $jadwal = sirey_fetchAll(sirey_query(
            'SELECT gm.jam_mulai, gm.jam_selesai,
                    mp.nama  AS matpel,
                    g.nama_grup
             FROM guru_mengajar_rayhanrp gm
             JOIN mata_pelajaran_rayhanrp mp ON mp.matpel_id = gm.matpel_id
             JOIN grup_rayhanrp g            ON g.grup_id    = gm.grup_id
             WHERE gm.akun_id = ?
               AND gm.hari    = ?
               AND gm.aktif   = 1
             ORDER BY gm.jam_mulai ASC',
            'is', $uid, $hari
        ));
    } else {
        // Siswa: jadwal = semua guru yang mengajar di kelas siswa ini hari ini
        $jadwal = sirey_fetchAll(sirey_query(
            'SELECT gm.jam_mulai, gm.jam_selesai,
                    mp.nama          AS matpel,
                    g.nama_grup,
                    guru.nama_lengkap AS nama_guru
             FROM grup_anggota_rayhanrp ga
             JOIN grup_rayhanrp g              ON g.grup_id    = ga.grup_id
             JOIN guru_mengajar_rayhanrp gm    ON gm.grup_id   = ga.grup_id
             JOIN mata_pelajaran_rayhanrp mp   ON mp.matpel_id = gm.matpel_id
             JOIN akun_rayhanrp guru           ON guru.akun_id = gm.akun_id
             WHERE ga.akun_id = ?
               AND ga.aktif   = 1
               AND gm.hari    = ?
               AND gm.aktif   = 1
             ORDER BY gm.jam_mulai ASC',
            'is', $uid, $hari
        ));
    }

    error_log("[notif_jadwal] uid={$uid} jadwal hari {$hari}: " . count($jadwal) . " item");

    // ── 4. Susun pesan ───────────────────────────────────────────────────────
    if (empty($jadwal)) {
        $pesan = "📅 *Jadwal {$hari}, {$tgl}*\n\n"
               . "_Tidak ada jadwal hari ini_ 📭\n\n"
               . "_Semangat!_ 😊";
    } else {
        $pesan = "📅 *Jadwal {$hari}, {$tgl}*\n\n";
        foreach ($jadwal as $j) {
            $mulai   = substr($j['jam_mulai'],   0, 5);
            $selesai = substr($j['jam_selesai'], 0, 5);
            if ($role === 'guru') {
                $pesan .= "🕐 *{$mulai}–{$selesai}* | {$j['matpel']}\n"
                        . "   🎓 {$j['nama_grup']}\n\n";
            } else {
                $pesan .= "🕐 *{$mulai}–{$selesai}* | {$j['matpel']}\n"
                        . "   👨‍🏫 {$j['nama_guru']} | 🎓 {$j['nama_grup']}\n\n";
            }
        }
        $pesan .= "_Semangat hari ini!_ 💪";
    }

    // ── 5. Kirim & catat ─────────────────────────────────────────────────────
    if (sendMsg($chatId, $pesan)) {
        $marker = $markerCek; // "[uid:X|jam:H:i|tgl:Y-m-d]"
        sirey_execute(
            'INSERT INTO notifikasi_rayhanrp (tipe, pesan, jumlah_terkirim, waktu_kirim)
             VALUES ("jadwal", ?, 1, NOW())',
            's', $pesan . " {$marker}"
        );
        $terkirim++;
        error_log("[notif_jadwal] ✅ Terkirim → uid={$uid} ({$u['nama_lengkap']})");
    } else {
        error_log("[notif_jadwal] ❌ GAGAL → uid={$uid} chat_id={$chatId} — cek BOT_TOKEN / CHAT_ID");
    }
}

error_log("[notif_jadwal] ✔ Selesai jam={$jam} — terkirim={$terkirim} skip={$diskip}");