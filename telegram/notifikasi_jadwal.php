<?php
/**
 * telegram/notifikasi_jadwal.php
 * Kirim jadwal hari ini ke semua pengguna terdaftar yang punya jadwal.
 *
 * Cron: 0 7 * * * php /path/to/bot_sirey/telegram/notifikasi_jadwal.php
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$tanggal_hari_ini_rayhanrp   = date('Y-m-d');
$nama_hari_rayhanrp = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'][date('w')];

error_log("[notifikasi_jadwal] Menjalankan untuk hari: {$nama_hari_rayhanrp} ({$tanggal_hari_ini_rayhanrp})");

// Ambil semua pengguna beserta jadwal hari ini berdasarkan nama hari
$baris_rayhanrp = sirey_fetchAll(sirey_query(
    'SELECT at.telegram_chat_id, a.nama_lengkap,
            j.judul, j.jam_mulai, j.jam_selesai, g.nama_grup
     FROM akun_telegram_rayhanRP at
     INNER JOIN akun_rayhanRP a         ON at.akun_id  = a.akun_id
     INNER JOIN grup_anggota_rayhanRP ga ON a.akun_id  = ga.akun_id
     INNER JOIN grup_rayhanRP g          ON ga.grup_id = g.grup_id
     INNER JOIN jadwal_rayhanRP j        ON j.grup_id  = g.grup_id
                                AND j.hari    = ?
     ORDER BY at.telegram_chat_id, j.jam_mulai ASC',
    's', $nama_hari_rayhanrp
));

if (empty($baris_rayhanrp)) {
    error_log("[notifikasi_jadwal] Tidak ada jadwal hari ini untuk {$nama_hari_rayhanrp}.");
    echo json_encode(['ok' => true, 'sent' => 0, 'date' => $tanggal_hari_ini_rayhanrp, 'hari' => $nama_hari_rayhanrp]);
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
foreach ($menurut_chat_rayhanrp as $id_chat_rayhanrp => $daftar_jadwal_rayhanrp) {
    $nama_pengguna_rayhanrp  = $daftar_jadwal_rayhanrp[0]['nama_lengkap'];
    $pesan_rayhanrp = "📅 *Jadwal Hari Ini — {$nama_hari_rayhanrp}, " . date('d/m/Y') . "*\n\n"
           . "Halo *{$nama_pengguna_rayhanrp}*, berikut jadwal Anda:\n\n";

    foreach ($daftar_jadwal_rayhanrp as $jadwal_item_rayhanrp) {
        $jam_mulai_rayhanrp   = substr((string)$jadwal_item_rayhanrp['jam_mulai'],   0, 5);
        $jam_selesai_rayhanrp = substr((string)$jadwal_item_rayhanrp['jam_selesai'], 0, 5);
        $pesan_rayhanrp  .= "• *{$jadwal_item_rayhanrp['judul']}*\n"
                . "  🕐 {$jam_mulai_rayhanrp} – {$jam_selesai_rayhanrp}\n\n";
    }

    $pesan_rayhanrp .= "_Semangat belajar hari ini!_ 💪\n— Bot SiRey";

    if (sendTelegramMessage($id_chat_rayhanrp, $pesan_rayhanrp)) {
        $terkirim_rayhanrp++;
    }
}

// Log pengiriman
if ($terkirim_rayhanrp > 0) {
    sirey_execute(
        'INSERT INTO notifikasi_rayhanRP (tipe, pesan, jumlah_terkirim)
         VALUES ("jadwal", ?, ?)',
        'si',
        "Jadwal {$nama_hari_rayhanrp} {$tanggal_hari_ini_rayhanrp} dikirim.",
        $terkirim_rayhanrp
    );
}

error_log("[notifikasi_jadwal] Selesai. Terkirim ke {$terkirim_rayhanrp} pengguna.");
echo json_encode(['ok' => true, 'sent' => $terkirim_rayhanrp, 'date' => $tanggal_hari_ini_rayhanrp, 'hari' => $nama_hari_rayhanrp]);