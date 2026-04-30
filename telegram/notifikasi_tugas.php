<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Jakarta');
/**
 * notifikasi_tugas.php
 * Kirim pengingat tugas deadline besok ke siswa via Telegram.
 *
 * Logika sama dengan notifikasi_jadwal:
 *   1. Filter jam_notif_tugas per user di PHP (bukan SQL)
 *   2. Anti double-send dengan marker unik per user per jam per hari
 */

require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/functions.php';

$jam   = date('H:i');
$besok = date('Y-m-d', strtotime('+1 day'));
$today = date('Y-m-d');

error_log("[notif_tugas] ▶ Dijalankan jam={$jam} cek_deadline={$besok}");

// ── Ambil semua siswa Telegram yang notif tugas aktif ────────────────────────
$users = sirey_fetchAll(sirey_query(
    'SELECT a.akun_id, a.nama_lengkap, at.telegram_chat_id,
            TIME_FORMAT(COALESCE(ns.jam_notif_tugas, "12:00:00"), "%H:%i") AS jam_notif
     FROM akun_telegram_rayhanRP at
     JOIN akun_rayhanRP a ON at.akun_id = a.akun_id
     LEFT JOIN notif_settings_rayhanRP ns ON ns.akun_id = a.akun_id
     WHERE a.role = "siswa"
       AND a.aktif = 1
       AND at.telegram_chat_id > 0
       AND COALESCE(ns.notif_tugas, 1) = 1',
    ''
));

error_log("[notif_tugas] Total siswa terdaftar: " . count($users));

$terkirim = 0;
$diskip   = 0;

foreach ($users as $u) {
    $uid      = (int) $u['akun_id'];
    $chatId   = (int) $u['telegram_chat_id'];
    $jamNotif = $u['jam_notif'];

    // ── 1. Bukan jamnya → lewati ─────────────────────────────────────────────
    if ($jamNotif !== $jam) {
        continue;
    }

    error_log("[notif_tugas] Proses uid={$uid} ({$u['nama_lengkap']}) jam_notif={$jamNotif}");

    // ── 2. Anti double-send ──────────────────────────────────────────────────
    $markerCek = "[uid:{$uid}|jam:{$jam}|tgl:{$today}]";
    $sudah = sirey_fetch(sirey_query(
        'SELECT 1 FROM notifikasi_rayhanRP
         WHERE tipe = "tugas"
           AND pesan LIKE ?
           AND DATE(waktu_kirim) = ?
         LIMIT 1',
        'ss', "%{$markerCek}%", $today
    ));

    if ($sudah) {
        error_log("[notif_tugas] ⏭ Skip uid={$uid} — sudah dikirim jam {$jam} hari ini");
        $diskip++;
        continue;
    }

    // ── 3. Ambil tugas deadline besok yang belum dikumpul ────────────────────
    $tugasList = sirey_fetchAll(sirey_query(
        'SELECT t.judul, t.tenggat,
                COALESCE(mp.nama, "-") AS matpel,
                COALESCE(g.nama_grup, "Perorangan") AS nama_grup
         FROM tugas_rayhanRP t
         JOIN grup_anggota_rayhanRP ga
              ON ga.grup_id = t.grup_id AND ga.akun_id = ? AND ga.aktif = 1
         LEFT JOIN mata_pelajaran_rayhanRP mp ON mp.matpel_id = t.matpel_id
         LEFT JOIN grup_rayhanRP g            ON g.grup_id    = t.grup_id
         LEFT JOIN pengumpulan_rayhanRP p
              ON p.tugas_id = t.tugas_id AND p.akun_id = ?
         WHERE DATE(t.tenggat) = ?
           AND t.status = "active"
           AND p.pengumpulan_id IS NULL

         UNION

         SELECT t.judul, t.tenggat,
                COALESCE(mp.nama, "-") AS matpel,
                "Perorangan" AS nama_grup
         FROM tugas_rayhanRP t
         JOIN tugas_perorang_rayhanRP tp
              ON tp.tugas_id = t.tugas_id AND tp.akun_id = ?
         LEFT JOIN mata_pelajaran_rayhanRP mp ON mp.matpel_id = t.matpel_id
         LEFT JOIN pengumpulan_rayhanRP p
              ON p.tugas_id = t.tugas_id AND p.akun_id = ?
         WHERE DATE(t.tenggat) = ?
           AND t.status = "active"
           AND p.pengumpulan_id IS NULL

         ORDER BY tenggat ASC',
        'iisiis',
        $uid, $uid, $besok,
        $uid, $uid, $besok
    ));

    error_log("[notif_tugas] uid={$uid} tugas deadline besok: " . count($tugasList));

    if (empty($tugasList)) {
        error_log("[notif_tugas] ⏭ Skip uid={$uid} — tidak ada tugas deadline besok");
        continue;
    }

    // ── 4. Susun pesan ───────────────────────────────────────────────────────
    $pesan = "⏰ *Pengingat Tugas — Deadline Besok!*\n\n"
           . "Halo *{$u['nama_lengkap']}*, segera kumpulkan tugas berikut:\n\n";

    foreach ($tugasList as $t) {
        $jamDeadline = date('H:i', strtotime($t['tenggat']));
        $pesan .= "📌 *{$t['judul']}*\n"
                . "   📚 {$t['matpel']} | 🎓 {$t['nama_grup']}\n"
                . "   ⏰ Pukul {$jamDeadline}\n\n";
    }
    $pesan .= "Jangan sampai telat! 📤";

    // ── 5. Kirim & catat ─────────────────────────────────────────────────────
    if (sendMsg($chatId, $pesan)) {
        sirey_execute(
            'INSERT INTO notifikasi_rayhanRP (tipe, pesan, jumlah_terkirim, waktu_kirim)
             VALUES ("tugas", ?, 1, NOW())',
            's', $pesan . " {$markerCek}"
        );
        $terkirim++;
        error_log("[notif_tugas] ✅ Terkirim → uid={$uid} ({$u['nama_lengkap']})");
    } else {
        error_log("[notif_tugas] ❌ GAGAL → uid={$uid} chat_id={$chatId} — cek BOT_TOKEN / CHAT_ID");
    }
}

error_log("[notif_tugas] ✔ Selesai jam={$jam} — terkirim={$terkirim} skip={$diskip}");