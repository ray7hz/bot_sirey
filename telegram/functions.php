<?php
declare(strict_types=1);

// Pastikan helpers.php sudah di-load (berisi getMatpelGuru, getGrupDiajarGuru, dll)
// telegram/functions.php TIDAK mendefinisikan ulang fungsi yang sudah ada di helpers.php

// ============================================================
// TELEGRAM API
// ============================================================

function getBotToken(): string {
    return (string) sirey_getConfigValue('telegram.bot_token', '');
}

/**
 * Kirim pesan teks ke chat tertentu.
 * $keyboard bisa berupa reply_keyboard (array 2D) atau inline_keyboard (array inline_keyboard).
 */
function sendMsg(int $chat, string $text, ?array $keyboard = null, bool $inline = false): bool {
    $token = getBotToken();
    if (!$token) return false;

    $params = [
        'chat_id'    => $chat,
        'text'       => $text,
        'parse_mode' => 'Markdown',
    ];

    if ($keyboard !== null) {
        if ($inline) {
            $params['reply_markup'] = json_encode(['inline_keyboard' => $keyboard]);
        } else {
            $params['reply_markup'] = json_encode([
                'keyboard'          => $keyboard,
                'resize_keyboard'   => true,
                'one_time_keyboard' => false, // keyboard TETAP tampil
            ]);
        }
    }

    return apiCall($token, 'sendMessage', $params);
}

/**
 * Hapus keyboard / kirim pesan tanpa keyboard.
 */
function sendMsgRemoveKeyboard(int $chat, string $text): bool {
    $token = getBotToken();
    if (!$token) return false;

    $params = [
        'chat_id'      => $chat,
        'text'         => $text,
        'parse_mode'   => 'Markdown',
        'reply_markup' => json_encode(['remove_keyboard' => true]),
    ];

    return apiCall($token, 'sendMessage', $params);
}

/**
 * Jawab callback query (inline button) agar ikon loading hilang.
 */
function answerCallback(string $callbackId, string $text = ''): void {
    $token = getBotToken();
    if (!$token) return;
    apiCall($token, 'answerCallbackQuery', ['callback_query_id' => $callbackId, 'text' => $text]);
}

/**
 * Helper HTTP POST ke Telegram API.
 */
function apiCall(string $token, string $method, array $params): bool {
    $url = "https://api.telegram.org/bot{$token}/{$method}";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($params),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);

    $data = json_decode((string)$res, true);
    return (bool)($data['ok'] ?? false);
}


// ============================================================
// KEYBOARD BUILDERS
// ============================================================

/**
 * Keyboard utama berdasarkan role.
 * Keyboard selalu muncul (one_time_keyboard = false).
 */
function mainKeyboard(string $role): array {
    $base = [
        ['📅 Jadwal Hari Ini', '📆 Jadwal Minggu Ini'],
        ['📢 Pengumuman', '📝 Tugas'],
    ];

    if ($role === 'guru') {
        $base[] = ['✏️ Buat Tugas', '📢 Kirim Pengumuman'];
        $base[] = ['🎓 Kelas Saya', '📋 Analisis Tugas'];
        $base[] = ['⭐ Nilai Tugas'];
    } else if ($role === 'siswa') {
        $base[] = ['🔄 Kumpulkan Tugas', '📊 Lihat Penilaian'];
    }

    $base[] = ['⚙️ Pengaturan'];
    // Logout button di paling bawah
    $base[] = ['🚪 Logout'];

    return $base;
}

/**
 * Keyboard hari untuk pilih jadwal.
 */
function hariKeyboard(): array {
    return [
        ['Senin', 'Selasa', 'Rabu'],
        ['Kamis', 'Jumat', 'Sabtu'],
        ['🔙 Kembali ke Menu'],
    ];
}

/**
 * Keyboard submenu pengaturan.
 */
function settingsKeyboard(): array {
    return [
        ['🔑 Ganti Password'],
        ['🕐 Atur Jam Notifikasi'],
        ['🔙 Kembali ke Menu'],
    ];
}

/**
 * Keyboard pengaturan notifikasi.
 */
function notifKeyboard(array $settings): array {
    $jadwal  = ($settings['notif_jadwal']  ?? 1) ? '✅' : '❌';
    $tugas   = ($settings['notif_tugas']   ?? 1) ? '✅' : '❌';
    $pengum  = ($settings['notif_pengumuman'] ?? 1) ? '✅' : '❌';
    $nilai   = ($settings['notif_nilai']   ?? 1) ? '✅' : '❌';

    return [
        ["{$jadwal} Notif Jadwal"],
        ["{$tugas} Notif Tugas"],
        ["{$pengum} Notif Pengumuman"],
        ["{$nilai} Notif Nilai"],
        ['🔙 Kembali ke Menu'],
    ];
}


// ============================================================
// DATABASE HELPERS
// ============================================================

function createTelegramLoginSession(int $akunId, int $chat): string {
    $plainToken = bin2hex(random_bytes(32));
    $hashedToken = hash('sha256', $plainToken);

    sirey_execute(
        'UPDATE telegram_sessions_rayhanRP
         SET is_active = 0, invalidated_at = NOW()
         WHERE akun_id = ? AND is_active = 1',
        'i',
        $akunId
    );

    sirey_execute(
        'DELETE FROM akun_telegram_rayhanRP
         WHERE akun_id = ? OR telegram_chat_id = ?',
        'ii',
        $akunId,
        $chat
    );

    sirey_execute(
        'INSERT INTO akun_telegram_rayhanRP (akun_id, telegram_chat_id)
         VALUES (?, ?)',
        'ii',
        $akunId,
        $chat
    );

    sirey_execute(
        'INSERT INTO telegram_sessions_rayhanRP
         (akun_id, chat_id, session_token, is_active, created_at, last_seen_at)
         VALUES (?, ?, ?, 1, NOW(), NOW())',
        'iis',
        $akunId,
        $chat,
        $hashedToken
    );

    return $plainToken;
}

function invalidateTelegramSession(int $chat): void {
    sirey_execute(
        'UPDATE telegram_sessions_rayhanRP
         SET is_active = 0, invalidated_at = NOW()
         WHERE chat_id = ? AND is_active = 1',
        'i',
        $chat
    );

    sirey_execute(
        'DELETE FROM akun_telegram_rayhanRP WHERE telegram_chat_id = ?',
        'i',
        $chat
    );
}

/**
 * Ambil data user yang sudah login berdasarkan chat_id.
 */
function getRegisteredUser(int $chat, ?string $sessionToken = null): ?array {
    $tokenCondition = '';
    $types = 'i';
    $params = [$chat];

    if ($sessionToken !== null && $sessionToken !== '') {
        $tokenCondition = ' AND ts.session_token = ?';
        $types .= 's';
        $params[] = hash('sha256', $sessionToken);
    }

    $row = sirey_fetch(sirey_query(
        'SELECT a.akun_id, a.nama_lengkap, a.role, a.nis_nip,
                at.telegram_chat_id,
                COALESCE(ns.notif_jadwal, 1)     AS notif_jadwal,
                COALESCE(ns.notif_tugas, 1)      AS notif_tugas,
                COALESCE(ns.notif_pengumuman, 1) AS notif_pengumuman,
                COALESCE(ns.notif_nilai, 1)      AS notif_nilai
         FROM akun_telegram_rayhanRP at
         INNER JOIN akun_rayhanRP a ON at.akun_id = a.akun_id
         INNER JOIN telegram_sessions_rayhanRP ts
                 ON ts.akun_id = a.akun_id
                AND ts.chat_id = at.telegram_chat_id
                AND ts.is_active = 1
         LEFT JOIN notif_settings_rayhanRP ns ON ns.akun_id = a.akun_id
         WHERE at.telegram_chat_id = ?
           ' . $tokenCondition . '
         LIMIT 1',
        $types,
        ...$params
    ));

    if (!$row) {
        return null;
    }

    sirey_execute(
        'UPDATE telegram_sessions_rayhanRP
         SET last_seen_at = NOW()
         WHERE chat_id = ? AND is_active = 1',
        'i',
        $chat
    );

    return $row;
}

/**
 * Ambil jadwal user untuk hari tertentu.
 */
function getJadwalHari(int $akunId, string $hari): array {
    return sirey_fetchAll(sirey_query(
        'SELECT mp.nama AS matpel, gm.jam_mulai, gm.jam_selesai,
                a.nama_lengkap AS guru_nama, g.nama_grup
         FROM grup_anggota_rayhanRP ga
         INNER JOIN guru_mengajar_rayhanRP gm ON ga.grup_id = gm.grup_id
         INNER JOIN mata_pelajaran_rayhanRP mp ON gm.matpel_id = mp.matpel_id
         INNER JOIN akun_rayhanRP a ON gm.akun_id = a.akun_id
         INNER JOIN grup_rayhanRP g ON ga.grup_id = g.grup_id
         WHERE ga.akun_id = ? AND ga.aktif = 1 AND gm.hari = ? AND gm.aktif = 1
         ORDER BY gm.jam_mulai ASC',
        'is', $akunId, $hari
    ));
}

/**
 * Ambil jadwal guru untuk hari tertentu.
 */
function getJadwalGuruHari(int $akunId, string $hari): array {
    return sirey_fetchAll(sirey_query(
        'SELECT mp.nama AS matpel, gm.jam_mulai, gm.jam_selesai,
                g.nama_grup
         FROM guru_mengajar_rayhanRP gm
         INNER JOIN mata_pelajaran_rayhanRP mp ON gm.matpel_id = mp.matpel_id
         INNER JOIN grup_rayhanRP g ON gm.grup_id = g.grup_id
         WHERE gm.akun_id = ? AND gm.hari = ? AND gm.aktif = 1
         ORDER BY gm.jam_mulai ASC',
        'is', $akunId, $hari
    ));
}

/**
 * Ambil tugas aktif untuk user (siswa).
 */
function getTugasSiswa(int $akunId): array {
    // Tugas grup
    $grup = sirey_fetchAll(sirey_query(
        'SELECT t.tugas_id, t.judul, t.tenggat, mp.nama AS matpel,
                g.nama_grup,
                (SELECT COUNT(*) FROM pengumpulan_rayhanRP p
                 WHERE p.tugas_id = t.tugas_id AND p.akun_id = ?) AS sudah_kumpul
         FROM tugas_rayhanRP t
         INNER JOIN grup_anggota_rayhanRP ga ON t.grup_id = ga.grup_id
         LEFT JOIN mata_pelajaran_rayhanRP mp ON t.matpel_id = mp.matpel_id
         LEFT JOIN grup_rayhanRP g ON t.grup_id = g.grup_id
         WHERE ga.akun_id = ? AND t.status = "active"
         ORDER BY t.tenggat ASC
         LIMIT 10',
        'ii', $akunId, $akunId
    ));

    // Tugas perorangan
    $perorang = sirey_fetchAll(sirey_query(
        'SELECT t.tugas_id, t.judul, t.tenggat, mp.nama AS matpel,
                "Perorangan" AS nama_grup,
                (SELECT COUNT(*) FROM pengumpulan_rayhanRP p
                 WHERE p.tugas_id = t.tugas_id AND p.akun_id = ?) AS sudah_kumpul
         FROM tugas_perorang_rayhanRP tp
         INNER JOIN tugas_rayhanRP t ON tp.tugas_id = t.tugas_id
         LEFT JOIN mata_pelajaran_rayhanRP mp ON t.matpel_id = mp.matpel_id
         WHERE tp.akun_id = ? AND t.status = "active"
         ORDER BY t.tenggat ASC
         LIMIT 5',
        'ii', $akunId, $akunId
    ));

    return array_merge($grup, $perorang);
}

/**
 * Ambil kelas yang diajar guru.
 */
function getGrupAjarGuru(int $guruId): array {
    return sirey_fetchAll(sirey_query(
        'SELECT DISTINCT g.grup_id, g.nama_grup
         FROM guru_mengajar_rayhanRP gm
         INNER JOIN grup_rayhanRP g ON gm.grup_id = g.grup_id
         WHERE gm.akun_id = ? AND gm.aktif = 1 AND g.aktif = 1
         ORDER BY g.nama_grup ASC',
        'i', $guruId
    ));
}

/**
 * Simpan pengumuman dari guru.
 */
function simpanPengumumanGuru(array $data): bool {
    $hasil = sirey_execute(
        'INSERT INTO pengumuman_rayhanRP
         (judul, isi, pembuat_id, grup_id, status, dibuat_pada)
         VALUES (?, ?, ?, ?, "published", NOW())',
        'ssii',
        $data['judul'],
        $data['isi'],
        $data['pembuat_id'],
        $data['grup_id']
    );

    if ($hasil < 1) return false;

    // Kirim notifikasi ke siswa di kelas
    $pengumumanId = sirey_lastInsertId();
    $targets = sirey_fetchAll(sirey_query(
        'SELECT DISTINCT at.telegram_chat_id
         FROM akun_telegram_rayhanRP at
         INNER JOIN grup_anggota_rayhanRP ga ON at.akun_id = ga.akun_id
         INNER JOIN akun_rayhanRP a ON a.akun_id = at.akun_id
         WHERE ga.grup_id = ? AND ga.aktif = 1 AND a.role = "siswa"',
        'i', $data['grup_id']
    ));

    $guru = sirey_fetch(sirey_query(
        'SELECT nama_lengkap FROM akun_rayhanRP WHERE akun_id = ?',
        'i', $data['pembuat_id']
    ));

    $pesan = "📢 *Pengumuman Baru*\n\n"
           . "*{$data['judul']}*\n\n"
           . "{$data['isi']}\n\n"
           . "_— {$guru['nama_lengkap']}_";

    foreach ($targets as $t) {
        $cid = (int)($t['telegram_chat_id'] ?? 0);
        if ($cid > 0) sendMsg($cid, $pesan);
    }

    return true;
}

/**
 * Ambil pengumuman terbaru.
 * Untuk siswa: pengumuman yang ditujukan ke grup/kelasnya atau semua.
 */
function getPengumuman(int $akunId): array {
    return sirey_fetchAll(sirey_query(
        'SELECT p.judul, p.isi, p.dibuat_pada, a.nama_lengkap AS pembuat
         FROM pengumuman_rayhanRP p
         LEFT JOIN akun_rayhanRP a ON p.pembuat_id = a.akun_id
         WHERE p.status = "published"
           AND (
               p.grup_id IS NULL
               OR p.grup_id IN (
                   SELECT grup_id FROM grup_anggota_rayhanRP WHERE akun_id = ? AND aktif = 1
               )
           )
         ORDER BY p.dibuat_pada DESC
         LIMIT 5',
        'i', $akunId
    ));
}

/**
 * Simpan atau update pengaturan notifikasi.
 */
function saveNotifSetting(int $akunId, string $key, int $value): void {
    $allowed = ['notif_jadwal', 'notif_tugas', 'notif_pengumuman', 'notif_nilai'];
    if (!in_array($key, $allowed, true)) return;

    sirey_execute(
        "INSERT INTO notif_settings_rayhanRP (akun_id, {$key})
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE {$key} = ?",
        'iii', $akunId, $value, $value
    );
}

/**
 * Update password akun.
 */
function updatePassword(int $akunId, string $passwordBaru): bool {
    $hash = password_hash($passwordBaru, PASSWORD_BCRYPT, ['cost' => 10]);
    $result = sirey_execute(
        'UPDATE akun_rayhanRP SET password = ?, diubah_pada = NOW() WHERE akun_id = ?',
        'si', $hash, $akunId
    );
    return $result > 0;
}

/**
 * Update jam notifikasi (jam_notif_jadwal, jam_notif_tugas).
 * Format: HH:MM (misal: 07:00, 12:30)
 */
function updateJamNotifikasi(int $akunId, string $jamJadwal, string $jamTugas): bool {
    // Validasi format HH:MM
    $pattern = '/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/';
    
    if (!preg_match($pattern, $jamJadwal) || 
        !preg_match($pattern, $jamTugas)) {
        return false;
    }

    $result = sirey_execute(
        'INSERT INTO notif_settings_rayhanRP (akun_id, jam_notif_jadwal, jam_notif_tugas)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE jam_notif_jadwal = ?, jam_notif_tugas = ?',
        'issss', $akunId, $jamJadwal, $jamTugas, $jamJadwal, $jamTugas
    );
    return $result > 0;
}

/**
 * Ambil jam notifikasi current.
 */
function getJamNotifikasi(int $akunId): array {
    $row = sirey_fetch(sirey_query(
        'SELECT COALESCE(jam_notif_jadwal, "07:00") AS jam_jadwal,
                COALESCE(jam_notif_tugas, "12:00") AS jam_tugas
         FROM notif_settings_rayhanRP
         WHERE akun_id = ?
         LIMIT 1',
        'i', $akunId
    ));
    
    if (!$row) {
        return [
            'jam_jadwal' => '07:00',
            'jam_tugas' => '12:00',
        ];
    }
    return $row;
}

/**
 * Ambil kelas-kelas yang diajar guru beserta info wali kelas & jumlah siswa.
 */
function getAnalisisKelas(int $guruId): array {
    return sirey_fetchAll(sirey_query(
        'SELECT g.grup_id, g.nama_grup, g.jurusan,
                COUNT(DISTINCT ga.akun_id) AS jml_siswa,
                wk.nama_lengkap AS wali_kelas,
                COUNT(DISTINCT t.tugas_id) AS jml_tugas,
                COUNT(DISTINCT CASE WHEN p.pengumpulan_id IS NOT NULL THEN p.pengumpulan_id END) AS jml_kumpul
         FROM guru_mengajar_rayhanRP gm
         INNER JOIN grup_rayhanRP g ON gm.grup_id = g.grup_id
         LEFT JOIN grup_anggota_rayhanRP ga ON g.grup_id = ga.grup_id
             AND ga.aktif = 1
             AND ga.akun_id IN (SELECT akun_id FROM akun_rayhanRP WHERE role = "siswa")
         LEFT JOIN akun_rayhanRP wk ON g.wali_kelas_id = wk.akun_id
         LEFT JOIN tugas_rayhanRP t ON t.grup_id = g.grup_id AND t.pembuat_id = ?
         LEFT JOIN pengumpulan_rayhanRP p ON p.tugas_id = t.tugas_id
         WHERE gm.akun_id = ? AND gm.aktif = 1
         GROUP BY g.grup_id, g.nama_grup, g.jurusan, wk.nama_lengkap
         ORDER BY g.nama_grup ASC',
        'ii', $guruId, $guruId
    ));
}

/**
 * Ambil kelas-kelas yang diwalikan oleh guru (wali kelas).
 * Include data penting: jumlah siswa, wali kelas status, dan statistik tugas.
 * Query dari kolom wali_kelas_id di tabel grup_rayhanRP.
 */
function getKelasWaliKelas(int $guruId): array {
    return sirey_fetchAll(sirey_query(
        'SELECT g.grup_id, g.nama_grup, g.jurusan,
                COUNT(DISTINCT ga.akun_id) AS jml_siswa,
                COUNT(DISTINCT t.tugas_id) AS jml_tugas_dibuat,
                COUNT(DISTINCT CASE WHEN p.pengumpulan_id IS NOT NULL THEN p.pengumpulan_id END) AS jml_kumpul
         FROM grup_rayhanRP g
         LEFT JOIN grup_anggota_rayhanRP ga ON g.grup_id = ga.grup_id
             AND ga.aktif = 1
             AND ga.akun_id IN (SELECT akun_id FROM akun_rayhanRP WHERE role = "siswa")
         LEFT JOIN tugas_rayhanRP t ON t.grup_id = g.grup_id AND t.status = "active"
         LEFT JOIN pengumpulan_rayhanRP p ON p.tugas_id = t.tugas_id
         WHERE g.wali_kelas_id = ? AND g.aktif = 1
         GROUP BY g.grup_id, g.nama_grup, g.jurusan
         ORDER BY g.nama_grup ASC',
        'i', $guruId
    ));
}

/**
 * Ambil kelas yang diajar guru untuk mapel tertentu.
 */
function getKelasGuruByMatpel(int $guruId, int $matpelId): array {
    return sirey_fetchAll(sirey_query(
        'SELECT DISTINCT g.grup_id, g.nama_grup
         FROM guru_mengajar_rayhanRP gm
         INNER JOIN grup_rayhanRP g ON gm.grup_id = g.grup_id
         WHERE gm.akun_id = ? AND gm.matpel_id = ? AND gm.aktif = 1 AND g.aktif = 1
         ORDER BY g.nama_grup ASC',
        'ii', $guruId, $matpelId
    ));
}

/**
 * Simpan tugas baru dari bot.
 */
function simpanTugasBot(array $data): bool {
    $hasil = sirey_execute(
        'INSERT INTO tugas_rayhanRP
         (grup_id, judul, deskripsi, matpel_id, tenggat, poin_maksimal, status, tipe_tugas, pembuat_id)
         VALUES (?, ?, ?, ?, ?, 100, "active", "grup", ?)',
        'issisi',
        $data['grup_id'],
        $data['judul'],
        $data['deskripsi'] ?? '',
        $data['matpel_id'],
        $data['tenggat'],
        $data['pembuat_id']
    );

    if ($hasil < 1) return false;

    // Kirim notifikasi ke siswa di kelas
    $tugasId  = sirey_lastInsertId();
    $targets  = sirey_fetchAll(sirey_query(
        'SELECT at.telegram_chat_id
         FROM akun_telegram_rayhanRP at
         INNER JOIN grup_anggota_rayhanRP ga ON at.akun_id = ga.akun_id
         INNER JOIN akun_rayhanRP a ON a.akun_id = at.akun_id
         WHERE ga.grup_id = ? AND ga.aktif = 1 AND a.role = "siswa"',
        'i', $data['grup_id']
    ));

    $pesan = "📝 *Tugas Baru!*\n\n"
           . "*{$data['judul']}*\n"
           . ($data['deskripsi'] ? "{$data['deskripsi']}\n\n" : "\n")
           . "📅 Deadline: " . date('d/m/Y H:i', strtotime($data['tenggat']));

    foreach ($targets as $t) {
        $cid = (int)($t['telegram_chat_id'] ?? 0);
        if ($cid > 0) sendMsg($cid, $pesan);
    }

    return true;
}


// ============================================================
// FORMATTER
// ============================================================

/**
 * Format satu baris jadwal.
 */
function formatBariJadwal(array $row): string {
    $mulai   = substr($row['jam_mulai'],   0, 5);
    $selesai = substr($row['jam_selesai'], 0, 5);
    $guru    = $row['guru_nama'] ?? '';
    $kelas   = $row['nama_grup'] ?? '';

    $line = "🕐 *{$mulai}–{$selesai}* | {$row['matpel']}";
    if ($guru)  $line .= "\n   👨‍🏫 {$guru}";
    if ($kelas) $line .= " | 🎓 {$kelas}";
    return $line;
}

/**
 * Format jadwal untuk satu hari.
 */
function formatJadwalHari(array $jadwal, string $hari, string $tanggal = ''): string {
    $header = "📅 *Jadwal {$hari}" . ($tanggal ? " ({$tanggal})" : '') . "*\n\n";

    if (empty($jadwal)) {
        return $header . "_(Tidak ada jadwal)_";
    }

    $lines = array_map('formatBariJadwal', $jadwal);
    return $header . implode("\n\n", $lines);
}

/**
 * Daftar nama hari dalam seminggu (mulai Senin).
 */
function daftarHari(): array {
    return ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
}

/**
 * Nama hari hari ini (Indonesia).
 */
function hariIni(): string {
    $map = [0=>'Minggu',1=>'Senin',2=>'Selasa',3=>'Rabu',4=>'Kamis',5=>'Jumat',6=>'Sabtu'];
    return $map[(int)date('w')];
}


// ============================================================
// PENGUMPULAN TUGAS
// ============================================================

/**
 * Ambil tugas siswa yang belum dikumpulkan.
 * Hanya menampilkan tugas yang status-nya "active" dan belum ada submission.
 */
function getTugasBelumDikumpul(int $akunId): array {
    // Tugas grup yang belum dikumpulkan
    $grup = sirey_fetchAll(sirey_query(
        'SELECT t.tugas_id, t.judul, t.tenggat, mp.nama AS matpel,
                g.nama_grup,
                DATEDIFF(t.tenggat, NOW()) AS hari_sisa
         FROM tugas_rayhanRP t
         INNER JOIN grup_anggota_rayhanRP ga ON t.grup_id = ga.grup_id
         LEFT JOIN mata_pelajaran_rayhanRP mp ON t.matpel_id = mp.matpel_id
         LEFT JOIN grup_rayhanRP g ON t.grup_id = g.grup_id
         LEFT JOIN pengumpulan_rayhanRP p ON p.tugas_id = t.tugas_id AND p.akun_id = ?
         WHERE ga.akun_id = ? AND t.status = "active" AND p.pengumpulan_id IS NULL
         ORDER BY t.tenggat ASC',
        'ii', $akunId, $akunId
    ));

    // Tugas perorangan yang belum dikumpulkan
    $perorang = sirey_fetchAll(sirey_query(
        'SELECT t.tugas_id, t.judul, t.tenggat, mp.nama AS matpel,
                "Perorangan" AS nama_grup,
                DATEDIFF(t.tenggat, NOW()) AS hari_sisa
         FROM tugas_perorang_rayhanRP tp
         INNER JOIN tugas_rayhanRP t ON tp.tugas_id = t.tugas_id
         LEFT JOIN mata_pelajaran_rayhanRP mp ON t.matpel_id = mp.matpel_id
         LEFT JOIN pengumpulan_rayhanRP p ON p.tugas_id = t.tugas_id AND p.akun_id = ?
         WHERE tp.akun_id = ? AND t.status = "active" AND p.pengumpulan_id IS NULL
         ORDER BY t.tenggat ASC',
        'ii', $akunId, $akunId
    ));

    return array_merge($grup, $perorang);
}

/**
 * Ambil tugas yang statusnya "revisi" dan belum ada submission revisi.
 * Untuk murid yang perlu mengirim ulang jawaban.
 */
function getTugasRevisiPending(int $akunId): array {
    $rows = sirey_fetchAll(sirey_query(
        'SELECT t.tugas_id, t.judul, t.tenggat, mp.nama AS matpel,
                g.nama_grup, pn.catatan_guru,
                DATEDIFF(t.tenggat, NOW()) AS hari_sisa,
                pn.nilai
         FROM tugas_rayhanRP t
         INNER JOIN pengumpulan_rayhanRP p ON p.tugas_id = t.tugas_id
         INNER JOIN penilaian_rayhanRP pn ON pn.pengumpulan_id = p.pengumpulan_id
         LEFT JOIN mata_pelajaran_rayhanRP mp ON t.matpel_id = mp.matpel_id
         LEFT JOIN grup_rayhanRP g ON t.grup_id = g.grup_id
         WHERE p.akun_id = ? 
           AND pn.status_lulus = "revisi"
           AND NOT EXISTS (
               SELECT 1 FROM pengumpulan_versi_rayhanRP pv
               WHERE pv.pengumpulan_id = p.pengumpulan_id
                 AND pv.versi_tipe = "revisi"
                 AND pv.status_approval = "disetujui"
           )
         ORDER BY t.tenggat ASC',
        'i', $akunId
    ));

    return $rows ?: [];
}

/**
 * Ambil detail tugas tertentu.
 */
function getTugasDetail(int $tugasId): ?array {
    return sirey_fetch(sirey_query(
        'SELECT t.tugas_id, t.judul, t.deskripsi, t.tenggat, mp.nama AS matpel,
                g.nama_grup, t.poin_maksimal, g.grup_id
         FROM tugas_rayhanRP t
         LEFT JOIN mata_pelajaran_rayhanRP mp ON t.matpel_id = mp.matpel_id
         LEFT JOIN grup_rayhanRP g ON t.grup_id = g.grup_id
         WHERE t.tugas_id = ?',
        'i', $tugasId
    ));
}

/**
 * Ambil tugas aktif untuk guru tertentu (dibuat oleh guru).
 */
function getTugasAktif(int $guruId): array {
    return sirey_fetchAll(sirey_query(
        'SELECT t.tugas_id, t.judul, t.tenggat, mp.nama AS matpel,
                g.nama_grup,
                (SELECT COUNT(*) FROM pengumpulan_rayhanRP p WHERE p.tugas_id = t.tugas_id) AS jml_kumpul
         FROM tugas_rayhanRP t
         LEFT JOIN mata_pelajaran_rayhanRP mp ON t.matpel_id = mp.matpel_id
         LEFT JOIN grup_rayhanRP g ON t.grup_id = g.grup_id
         WHERE t.pembuat_id = ? AND t.status = "active"
         ORDER BY t.tenggat DESC
         LIMIT 10',
        'i', $guruId
    ));
}

/**
 * Alias untuk getTugasAktif - Ambil tugas yang dibuat guru.
 */
function getTugasGuru(int $guruId): array {
    return getTugasAktif($guruId);
}

/**
 * Ambil daftar tugas untuk analisis (guru).
 * Include wali_kelas jika ada (dari kolom wali_kelas_id tabel grup_rayhanRP).
 */
function getTugasAnalisisForGuru(int $guruId): array {
    return sirey_fetchAll(sirey_query(
        'SELECT t.tugas_id, t.judul, mp.nama AS matpel, g.nama_grup, g.grup_id,
                t.tenggat, COALESCE(wk.nama_lengkap, "-") AS wali_kelas
         FROM tugas_rayhanRP t
         LEFT JOIN mata_pelajaran_rayhanRP mp ON t.matpel_id = mp.matpel_id
         LEFT JOIN grup_rayhanRP g ON t.grup_id = g.grup_id
         LEFT JOIN akun_rayhanRP wk ON g.wali_kelas_id = wk.akun_id
         WHERE t.pembuat_id = ? AND t.status = "active"
         ORDER BY t.tenggat DESC
         LIMIT 20',
        'i', $guruId
    ));
}

/**
 * Ambil analisis detail tugas: siapa yang sudah & belum mengumpulkan.
 * Include wali_kelas dan validasi student status/aktif.
 */
function getTugasAnalisisDetail(int $tugasId): array {
    $tugas = sirey_fetch(sirey_query(
        'SELECT t.tugas_id, t.judul, g.grup_id, g.nama_grup, mp.nama AS matpel,
                COALESCE(wk.nama_lengkap, "-") AS wali_kelas
         FROM tugas_rayhanRP t
         LEFT JOIN grup_rayhanRP g ON t.grup_id = g.grup_id
         LEFT JOIN mata_pelajaran_rayhanRP mp ON t.matpel_id = mp.matpel_id
         LEFT JOIN akun_rayhanRP wk ON g.wali_kelas_id = wk.akun_id
         WHERE t.tugas_id = ?',
        'i', $tugasId
    ));

    if (!$tugas) return [];

    // Ambil siswa di kelas - VALIDASI: aktif, role siswa (INCLUDE semua tipe keanggotaan)
    $allSiswa = sirey_fetchAll(sirey_query(
        'SELECT DISTINCT a.akun_id, a.nama_lengkap, a.nis_nip
         FROM akun_rayhanRP a
         INNER JOIN grup_anggota_rayhanRP ga ON a.akun_id = ga.akun_id
         WHERE ga.grup_id = ? AND ga.aktif = 1
           AND a.role = "siswa" AND a.aktif = 1
         ORDER BY a.nama_lengkap ASC',
        'i', $tugas['grup_id']
    ));

    // Ambil yang sudah kumpul - VALIDASI: student aktif (use waktu_kumpul, not dibuat_pada)
    $sudahKumpul = sirey_fetchAll(sirey_query(
        'SELECT DISTINCT p.akun_id, a.nama_lengkap, a.nis_nip, p.waktu_kumpul
         FROM pengumpulan_rayhanRP p
         INNER JOIN akun_rayhanRP a ON p.akun_id = a.akun_id
         WHERE p.tugas_id = ? AND a.aktif = 1 AND a.role = "siswa"
         ORDER BY a.nama_lengkap ASC',
        'i', $tugasId
    ));

    $sudahKumpulIds = array_column($sudahKumpul, 'akun_id');

    return [
        'tugas' => $tugas,
        'all_siswa' => $allSiswa,
        'sudah_kumpul' => $sudahKumpul,
        'belum_kumpul' => array_filter($allSiswa, fn($s) => !in_array($s['akun_id'], $sudahKumpulIds)),
    ];
}

/**
 * Ambil penilaian siswa.
 */
function getNilaiSiswa(int $akunId): array {
    return sirey_fetchAll(sirey_query(
        'SELECT t.tugas_id, t.judul, mp.nama AS matpel, g.nama_grup,
                pn.nilai, t.poin_maksimal,
                pn.catatan_guru AS keterangan,
                CAST((pn.nilai / t.poin_maksimal * 100) AS DECIMAL(5,1)) AS persentase
         FROM penilaian_rayhanRP pn
         INNER JOIN pengumpulan_rayhanRP p ON pn.pengumpulan_id = p.pengumpulan_id
         INNER JOIN tugas_rayhanRP t ON p.tugas_id = t.tugas_id
         LEFT JOIN mata_pelajaran_rayhanRP mp ON t.matpel_id = mp.matpel_id
         LEFT JOIN grup_rayhanRP g ON t.grup_id = g.grup_id
         WHERE p.akun_id = ? AND pn.nilai IS NOT NULL
         ORDER BY t.tenggat DESC
         LIMIT 20',
        'i', $akunId
    ));
}

/**
 * Format pesan penilaian untuk ditampilkan di Telegram.
 */
function formatNilaiSiswa(array $nilaiList): string {
    if (empty($nilaiList)) {
        return "📊 *Penilaian*\n\n_(Belum ada penilaian)_";
    }

    $pesan = "📊 *Penilaian Tugas*\n\n";
    
    foreach ($nilaiList as $row) {
        $judul = $row['judul'];
        $nilai = $row['nilai'];
        $maksimal = $row['poin_maksimal'];
        $persentase = $row['persentase'];
        $matpel = $row['matpel'] ?? 'Tugas';
        $emoji = '';
        
        // Emoji berdasarkan persentase
        if ($persentase >= 90) $emoji = '⭐';
        elseif ($persentase >= 80) $emoji = '✅';
        elseif ($persentase >= 70) $emoji = '👍';
        elseif ($persentase >= 60) $emoji = '⚠️';
        else $emoji = '❌';
        
        $pesan .= "{$emoji} *{$judul}*\n"
                . "📚 {$matpel}\n"
                . "📝 Nilai: {$nilai}/{$maksimal} ({$persentase}%)\n";
        
        if ($row['keterangan']) {
            $pesan .= "💬 {$row['keterangan']}\n";
        }
        
        $pesan .= "\n";
    }

    return $pesan;
}

/**
 * Simpan pengumpulan tugas dari telegram.
 * Support: teks jawaban, file path, atau kombinasi.
 */
function simpanPengumpulanTugas(
    int $akunId,
    int $tugasId,
    ?string $teksJawaban = null,
    ?string $filePath = null
): bool {
    $tugas = getTugasDetail($tugasId);
    if (!$tugas) return false;

    // Tentukan status: dikumpulkan (tepat waktu) atau terlambat
    $tenggat = new DateTime($tugas['tenggat']);
    $sekarang = new DateTime();
    $status = $sekarang <= $tenggat ? 'dikumpulkan' : 'terlambat';

    // Simpan pengumpulan
    $hasil = sirey_execute(
        'INSERT INTO pengumpulan_rayhanRP
         (akun_id, tugas_id, teks_jawaban, file_path, status, waktu_kumpul, via)
         VALUES (?, ?, ?, ?, ?, NOW(), "telegram")',
        'iisss',
        $akunId,
        $tugasId,
        $teksJawaban ?? '',
        $filePath ?? '',
        $status
    );

    if ($hasil < 1) return false;

    // Kirim notifikasi ke guru pembuat tugas
    $guru = sirey_fetch(sirey_query(
        'SELECT at.telegram_chat_id FROM tugas_rayhanRP t
         INNER JOIN akun_telegram_rayhanRP at ON t.pembuat_id = at.akun_id
         WHERE t.tugas_id = ?',
        'i', $tugasId
    ));

    if ($guru && !empty($guru['telegram_chat_id'])) {
        $siswa = sirey_fetch(sirey_query(
            'SELECT nama_lengkap FROM akun_rayhanRP WHERE akun_id = ?',
            'i', $akunId
        ));

        $pesan = "📬 *Pengumpulan Tugas*\n\n"
               . "*{$tugas['judul']}*\n"
               . "👤 Dari: {$siswa['nama_lengkap']}\n"
               . "⏰ Waktu: " . date('d/m/Y H:i') . "\n"
               . "Status: {$status}";

        sendMsg((int)$guru['telegram_chat_id'], $pesan);
    }

    return true;
}

/**
 * Format pesan preview tugas untuk review sebelum submit.
 */
function formatPreviewPengumpulan(array $tugas, ?string $teksJawaban, ?string $linkJawaban): string {
    $tgl = date('d/m/Y', strtotime($tugas['tenggat']));
    $pesan = "✅ *Konfirmasi Pengumpulan*\n\n"
           . "📚 Mapel: {$tugas['matpel']}\n"
           . "🎓 Kelas: {$tugas['nama_grup']}\n"
           . "📝 Tugas: {$tugas['judul']}\n";

    if ($tugas['deskripsi']) {
        $pesan .= "📋 Deskripsi: {$tugas['deskripsi']}\n";
    }

    $pesan .= "📅 Deadline: {$tgl}\n\n"
           . "━━ *JAWABAN* ━━\n";

    if ($teksJawaban) {
        $preview = strlen($teksJawaban) > 100 ? substr($teksJawaban, 0, 100) . '...' : $teksJawaban;
        $pesan .= "📄 *Teks:*\n{$preview}\n";
    }

    $pesan .= "\nKumpulkan sekarang?";

    return $pesan;
}

/**
 * Format pesan preview untuk file submission.
 */
function formatPreviewPengumpulanFile(array $tugas, string $fileName): string {
    $tgl = date('d/m/Y', strtotime($tugas['tenggat']));
    $pesan = "✅ *Konfirmasi Pengumpulan*\n\n"
           . "📚 Mapel: {$tugas['matpel']}\n"
           . "🎓 Kelas: {$tugas['nama_grup']}\n"
           . "📝 Tugas: {$tugas['judul']}\n";

    if ($tugas['deskripsi']) {
        $pesan .= "📋 Deskripsi: {$tugas['deskripsi']}\n";
    }

    $pesan .= "📅 Deadline: {$tgl}\n\n"
           . "━━ *FILE* ━━\n"
           . "📎 *Nama File:* {$fileName}\n\n"
           . "Kumpulkan sekarang?";

    return $pesan;
}

/**
 * Simpan revisi pengumpulan tugas (untuk tugas dengan status revisi).
 * Insert ke pengumpulan_versi_rayhanRP dengan status_approval = 'pending'.
 */
function simpanRevisiTugas(
    int $akunId,
    int $tugasId,
    ?string $teksJawaban = null,
    ?string $filePath = null
): bool {
    try {
        // Get pengumpulan_id dari tugas tersebut
        $pengumpulan = sirey_fetch(sirey_query(
            'SELECT pengumpulan_id FROM pengumpulan_rayhanRP WHERE akun_id = ? AND tugas_id = ?',
            'ii', $akunId, $tugasId
        ));

        if (!$pengumpulan) {
            error_log("[simpanRevisiTugas] Pengumpulan tidak ditemukan untuk akun_id=$akunId, tugas_id=$tugasId");
            return false;
        }

        $pengumpulan_id = (int)$pengumpulan['pengumpulan_id'];

        // Hitung nomor versi (ambil versi terbesar, +1)
        $maxVersi = sirey_fetch(sirey_query(
            'SELECT MAX(nomor_versi) AS max_ver FROM pengumpulan_versi_rayhanRP WHERE pengumpulan_id = ?',
            'i', $pengumpulan_id
        ));
        $nomorVersi = ((int)($maxVersi['max_ver'] ?? 0)) + 1;

        // Simpan versi revisi
        $hasil = sirey_execute(
            'INSERT INTO pengumpulan_versi_rayhanRP
             (pengumpulan_id, nomor_versi, teks_jawaban, file_path, file_nama_asli, 
              versi_tipe, disubmit_oleh, status_approval, dibuat_pada)
             VALUES (?, ?, ?, ?, ?, "revisi", ?, "pending", NOW())',
            'iisssi',
            $pengumpulan_id,
            $nomorVersi,
            $teksJawaban ?? '',
            $filePath ?? '',
            $filePath ? basename($filePath) : '',
            $akunId
        );

        if ($hasil < 1) {
            error_log("[simpanRevisiTugas] Database insert gagal untuk pengumpulan_id=$pengumpulan_id");
            return false;
        }

        // Kirim notifikasi ke guru pembuat tugas
        $tugas = sirey_fetch(sirey_query(
            'SELECT t.judul, t.pembuat_id FROM tugas_rayhanRP t WHERE t.tugas_id = ?',
            'i', $tugasId
        ));

        if ($tugas) {
            $guru = sirey_fetch(sirey_query(
                'SELECT at.telegram_chat_id FROM akun_telegram_rayhanRP at WHERE at.akun_id = ?',
                'i', (int)$tugas['pembuat_id']
            ));

            if ($guru && !empty($guru['telegram_chat_id'])) {
                $siswa = sirey_fetch(sirey_query(
                    'SELECT nama_lengkap FROM akun_rayhanRP WHERE akun_id = ?',
                    'i', $akunId
                ));

                $pesan = "📝 *Revisi Tugas Disubmit*\n\n"
                       . "*{$tugas['judul']}* (v{$nomorVersi})\n"
                       . "👤 Dari: {$siswa['nama_lengkap']}\n"
                       . "⏰ Waktu: " . date('d/m/Y H:i') . "\n\n"
                       . "Silakan tinjau revisi tersebut.";

                sendMsg((int)$guru['telegram_chat_id'], $pesan);
            }
        }

        return true;
    } catch (Exception $e) {
        error_log("[simpanRevisiTugas] Exception: " . $e->getMessage());
        return false;
    }
}

/**
 * Download file dari Telegram dan simpan ke server.
 * Return relative path jika sukses, null jika gagal.
 */
function downloadTelegramFile(string $fileId, string $fileType, int $akunId): ?string {
    $token = getBotToken();
    if (!$token) {
        error_log("[DOWNLOAD] No bot token");
        return null;
    }

    try {
        // Step 1: Get file path dari Telegram API
        $url = "https://api.telegram.org/bot{$token}/getFile";
        $params = ['file_id' => $fileId];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        curl_close($ch);

        if (!empty($curlErrno)) {
            error_log("[DOWNLOAD] Curl error (getFile) #{$curlErrno}: $curlError");
            return null;
        }

        if (!empty($curlError)) {
            error_log("[DOWNLOAD] Curl error (getFile): $curlError");
            return null;
        }

        if ($httpCode !== 200) {
            error_log("[DOWNLOAD] getFile HTTP $httpCode");
            return null;
        }

        $data = json_decode((string)$res, true);
        if (!($data['ok'] ?? false) || empty($data['result']['file_path'])) {
            error_log("[DOWNLOAD] Invalid response: " . json_encode($data));
            return null;
        }

        $filePath = $data['result']['file_path'];
        error_log("[DOWNLOAD] Got file path: $filePath");

        // Step 2: Download file content
        $fileUrl = "https://api.telegram.org/file/bot{$token}/{$filePath}";

        $ch = curl_init($fileUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_BINARYTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $fileContent = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        curl_close($ch);

        if (!empty($curlErrno)) {
            error_log("[DOWNLOAD] Curl error (file) #{$curlErrno}: $curlError");
            return null;
        }

        if (!empty($curlError)) {
            error_log("[DOWNLOAD] Curl error (file): $curlError");
            return null;
        }

        if ($httpCode !== 200 || empty($fileContent)) {
            error_log("[DOWNLOAD] File HTTP $httpCode, size: " . strlen($fileContent ?? ''));
            return null;
        }

        $fileSize = strlen($fileContent);
        error_log("[DOWNLOAD] Downloaded: $fileSize bytes");

        if ($fileSize > 10 * 1024 * 1024) {
            error_log("[DOWNLOAD] File too large: $fileSize");
            return null;
        }

        // Step 3: Prepare upload directory
        $year = date('Y');
        $month = date('m');
        $uploadDir = __DIR__ . "/../uploads/submissions/{$year}/{$month}";

        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                error_log("[DOWNLOAD] Failed to create dir: $uploadDir");
                return null;
            }
        }

        // Step 4: Determine file extension
        $ext = pathinfo(basename($filePath), PATHINFO_EXTENSION);
        if (!$ext) {
            // Try to detect from file magic bytes
            if (strpos($fileContent, '%PDF') === 0) $ext = 'pdf';
            elseif (substr($fileContent, 0, 3) === "\xFF\xD8\xFF") $ext = 'jpg';
            elseif (substr($fileContent, 0, 8) === "\x89PNG\r\n\x1A\n") $ext = 'png';
            elseif (substr($fileContent, 0, 3) === "GIF") $ext = 'gif';
            elseif (substr($fileContent, 0, 2) === "PK") $ext = 'docx'; // ZIP-based
            else $ext = 'bin';
        }

        // Step 5: Save file
        $day = date('d');
        $newFileName = "submission_{$akunId}_{$year}{$month}{$day}_" . time() . ".{$ext}";
        $newPath = $uploadDir . '/' . $newFileName;

        $written = file_put_contents($newPath, $fileContent);
        if ($written === false || $written !== $fileSize) {
            error_log("[DOWNLOAD] Failed to write file: $newPath");
            @unlink($newPath);
            return null;
        }

        error_log("[DOWNLOAD] Saved: $newPath ($written bytes)");

        // Return relative path
        return "uploads/submissions/{$year}/{$month}/{$newFileName}";

    } catch (Exception $e) {
        error_log("[DOWNLOAD] Exception: " . $e->getMessage());
        return null;
    }
}


// ============================================================
// PENILAIAN TUGAS (GURU)
// ============================================================

/**
 * Ambil daftar tugas yang punya pengumpulan untuk dinilai guru.
 * Prioritas: tugas yang belum semua dinilai.
 */
function getTugasUntukNilai(int $guruId): array {
    return sirey_fetchAll(sirey_query(
        'SELECT t.tugas_id, t.judul, t.tenggat, t.poin_maksimal, mp.nama AS matpel,
                g.nama_grup,
                (SELECT COUNT(*) FROM pengumpulan_rayhanRP p WHERE p.tugas_id = t.tugas_id) AS jml_pengumpulan,
                (SELECT COUNT(*) FROM pengumpulan_rayhanRP p 
                 INNER JOIN penilaian_rayhanRP pn ON pn.pengumpulan_id = p.pengumpulan_id
                 WHERE p.tugas_id = t.tugas_id AND pn.nilai IS NOT NULL) AS jml_sudah_dinilai
         FROM tugas_rayhanRP t
         LEFT JOIN mata_pelajaran_rayhanRP mp ON t.matpel_id = mp.matpel_id
         LEFT JOIN grup_rayhanRP g ON t.grup_id = g.grup_id
         WHERE t.pembuat_id = ? AND t.status = "active"
           AND EXISTS (
               SELECT 1 FROM pengumpulan_rayhanRP p WHERE p.tugas_id = t.tugas_id
           )
         ORDER BY (jml_pengumpulan - jml_sudah_dinilai) DESC,
                  t.tenggat ASC
         LIMIT 20',
        'i', $guruId
    ));
}

/**
 * Ambil daftar pengumpulan dari satu tugas (untuk penilaian).
 * Include info penilaian jika sudah ada.
 */
function getPengumpulanTugas(int $tugasId): array {
    return sirey_fetchAll(sirey_query(
        'SELECT p.pengumpulan_id, p.akun_id, a.nama_lengkap, a.nis_nip,
                p.teks_jawaban, p.file_path, p.file_nama_asli, p.link_jawaban,
                p.status, p.waktu_kumpul, 
                pn.penilaian_id, pn.nilai, pn.status_lulus, pn.catatan_guru,
                CASE WHEN pn.nilai IS NOT NULL THEN "✅ Dinilai" ELSE "⏳ Belum" END AS status_nilai
         FROM pengumpulan_rayhanRP p
         INNER JOIN akun_rayhanRP a ON p.akun_id = a.akun_id
         LEFT JOIN penilaian_rayhanRP pn ON pn.pengumpulan_id = p.pengumpulan_id
         WHERE p.tugas_id = ?
         ORDER BY pn.nilai IS NOT NULL ASC, a.nama_lengkap ASC',
        'i', $tugasId
    ));
}

/**
 * Ambil detail pengumpulan spesifik untuk ditampilkan sebelum penilaian.
 */
function getPengumpulanDetail(int $pengumpulanId): ?array {
    return sirey_fetch(sirey_query(
        'SELECT p.pengumpulan_id, p.akun_id, a.nama_lengkap, a.nis_nip,
                p.tugas_id, t.judul, t.poin_maksimal, mp.nama AS matpel, g.nama_grup,
                p.teks_jawaban, p.file_path, p.file_nama_asli, p.link_jawaban,
                p.status, p.waktu_kumpul,
                pn.penilaian_id, pn.nilai, pn.status_lulus, pn.catatan_guru
         FROM pengumpulan_rayhanRP p
         INNER JOIN tugas_rayhanRP t ON p.tugas_id = t.tugas_id
         INNER JOIN akun_rayhanRP a ON p.akun_id = a.akun_id
         LEFT JOIN mata_pelajaran_rayhanRP mp ON t.matpel_id = mp.matpel_id
         LEFT JOIN grup_rayhanRP g ON t.grup_id = g.grup_id
         LEFT JOIN penilaian_rayhanRP pn ON pn.pengumpulan_id = p.pengumpulan_id
         WHERE p.pengumpulan_id = ?',
        'i', $pengumpulanId
    ));
}

/**
 * Simpan atau update penilaian.
 * Return: ['success' => bool, 'message' => string, 'penilaian_id' => int]
 */
function savePenilaian(int $pengumpulanId, int $guruId, float $nilai, ?string $catatan = null, string $statusLulus = 'lulus'): array {
    try {
        // Validasi poin
        $pengumpulan = getPengumpulanDetail($pengumpulanId);
        if (!$pengumpulan) {
            return ['success' => false, 'message' => 'Pengumpulan tidak ditemukan'];
        }

        $poinMax = (float)$pengumpulan['poin_maksimal'];
        $nilai = max(0, min($nilai, $poinMax)); // Clamp antara 0 dan poin_maksimal

        // Check apakah sudah ada penilaian
        $penilaianAdaAtau = sirey_fetch(sirey_query(
            'SELECT penilaian_id FROM penilaian_rayhanRP WHERE pengumpulan_id = ? LIMIT 1',
            'i', $pengumpulanId
        ));

        if ($penilaianAdaAtau) {
            // UPDATE existing penilaian
            $hasil = sirey_execute(
                'UPDATE penilaian_rayhanRP 
                 SET nilai = ?, status_lulus = ?, catatan_guru = ?, dinilai_oleh = ?, dinilai_pada = NOW()
                 WHERE pengumpulan_id = ?',
                'dssii',
                $nilai,
                $statusLulus,
                $catatan ?? '',
                $guruId,
                $pengumpulanId
            );

            if ($hasil < 1) {
                return ['success' => false, 'message' => 'Gagal menyimpan penilaian'];
            }

            return [
                'success' => true,
                'message' => '✅ Penilaian berhasil diupdate',
                'penilaian_id' => (int)$penilaianAdaAtau['penilaian_id']
            ];
        } else {
            // INSERT penilaian baru
            $hasil = sirey_execute(
                'INSERT INTO penilaian_rayhanRP 
                 (pengumpulan_id, dinilai_oleh, nilai, status_lulus, catatan_guru, dinilai_pada)
                 VALUES (?, ?, ?, ?, ?, NOW())',
                'iidss',
                $pengumpulanId,
                $guruId,
                $nilai,
                $statusLulus,
                $catatan ?? ''
            );

            if ($hasil < 1) {
                return ['success' => false, 'message' => 'Gagal menyimpan penilaian'];
            }

            $penilaianId = sirey_lastInsertId();

            return [
                'success' => true,
                'message' => '✅ Penilaian berhasil disimpan',
                'penilaian_id' => $penilaianId
            ];
        }

    } catch (Exception $e) {
        error_log("[savePenilaian] Error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()];
    }
}

/**
 * Format pesan preview pengumpulan untuk guru sebelum menilai.
 */
function formatPreviewPengumpulanGuru(array $pengumpulan): string {
    $tgl = date('d/m/Y H:i', strtotime($pengumpulan['waktu_kumpul']));
    $status = match($pengumpulan['status']) {
        'dikumpulkan' => '✅ Dikumpulkan tepat waktu',
        'terlambat' => '⏳ Terlambat',
        'graded' => '📊 Sudah dinilai',
        default => $pengumpulan['status']
    };

    $pesan = "📋 *Preview Pengumpulan*\n\n";
    $pesan .= "👤 *Siswa:* {$pengumpulan['nama_lengkap']}\n";
    $pesan .= "🆔 *NIS:* {$pengumpulan['nis_nip']}\n\n";
    $pesan .= "📚 *Tugas:* {$pengumpulan['judul']}\n";
    $pesan .= "💯 *Poin Maksimal:* {$pengumpulan['poin_maksimal']}\n";
    $pesan .= "📅 *Waktu Kumpul:* {$tgl}\n";
    $pesan .= "📊 *Status:* {$status}\n\n";
    $pesan .= "━━ *JAWABAN* ━━\n";

    if ($pengumpulan['teks_jawaban']) {
        $preview = strlen($pengumpulan['teks_jawaban']) > 200 
            ? substr($pengumpulan['teks_jawaban'], 0, 200) . '...' 
            : $pengumpulan['teks_jawaban'];
        $pesan .= "📄 *Teks:*\n{$preview}\n\n";
    }

    if ($pengumpulan['file_nama_asli']) {
        $pesan .= "📎 *File:* {$pengumpulan['file_nama_asli']}\n\n";
    }

    if ($pengumpulan['link_jawaban']) {
        $pesan .= "🔗 *Link:* {$pengumpulan['link_jawaban']}\n\n";
    }

    if ($pengumpulan['nilai'] !== null) {
        $pesan .= "━━ *PENILAIAN SEBELUMNYA* ━━\n";
        $pesan .= "💯 *Nilai:* {$pengumpulan['nilai']}\n";
        $pesan .= "📝 *Catatan:* {$pengumpulan['catatan_guru']}\n";
    } else {
        $pesan .= "━━ *BELUM DINILAI* ━━\n";
    }

    $pesan .= "\nInput nilai (0 - {$pengumpulan['poin_maksimal']})";

    return $pesan;
}

/**
 * Format pesan input nilai & catatan untuk guru.
 */
function formatFormNilai(array $pengumpulan): string {
    $pesan = "✏️ *Input Nilai & Catatan*\n\n";
    $pesan .= "👤 *Siswa:* {$pengumpulan['nama_lengkap']}\n";
    $pesan .= "📝 *Tugas:* {$pengumpulan['judul']}\n";
    $pesan .= "💯 *Range:* 0 - {$pengumpulan['poin_maksimal']}\n\n";
    
    if ($pengumpulan['nilai'] !== null) {
        $pesan .= "⚠️ *Penilaian sebelumnya:* {$pengumpulan['nilai']} | Catatan: {$pengumpulan['catatan_guru']}\n\n";
    }

    $pesan .= "Kirim *nilai numerik* (misalnya: 85 atau 90.5):\n\n";
    $pesan .= "(Setelah input nilai, kamu bisa tambahkan catatan)";

    return $pesan;
}

/**
 * Format pesan konfirmasi sebelum menyimpan penilaian.
 */
function formatKonfirmasiNilai(array $pengumpulan, float $nilaiInput, ?string $catatan): string {
    $pesan = "✅ *Konfirmasi Penilaian*\n\n";
    $pesan .= "👤 *Siswa:* {$pengumpulan['nama_lengkap']}\n";
    $pesan .= "📝 *Tugas:* {$pengumpulan['judul']}\n\n";
    
    $pesan .= "━━ *DATA PENILAIAN* ━━\n";
    $pesan .= "💯 *Nilai:* {$nilaiInput} / {$pengumpulan['poin_maksimal']}\n";
    
    if ($catatan) {
        $pesan .= "📝 *Catatan:* {$catatan}\n";
    }
    
    $pesan .= "\n🔘 *Konfirmasi:*\n";
    $pesan .= "✅ *Lanjutkan* - Simpan penilaian\n";
    $pesan .= "✏️ *Edit* - Ubah nilai/catatan\n";
    $pesan .= "❌ *Batalkan* - Jangan simpan";

    return $pesan;
}
