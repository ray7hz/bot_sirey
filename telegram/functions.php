<?php
declare(strict_types=1);

// ============================================================
// KONFIGURASI & TOKEN
// ============================================================

function getBotToken(): string
{
    return (string) sirey_getConfigValue('telegram.bot_token', '');
}

function getBotApiUrl(string $method): string
{
    $token = getBotToken();
    return "https://api.telegram.org/bot{$token}/{$method}";
}


// ============================================================
// FUNGSI DASAR KIRIM PESAN
// ============================================================

/**
 * Kirim HTTP POST ke Telegram API pakai cURL.
 * Mengembalikan array hasil decode JSON atau null jika gagal.
 */
function telegramRequest(string $method, array $params): ?array
{
    $url = getBotApiUrl($method);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($params),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response  = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError !== '') {
        error_log("[TG] cURL error ({$method}): {$curlError}");
        return null;
    }

    $data = json_decode((string) $response, true);

    if (!is_array($data)) {
        error_log("[TG] JSON decode gagal ({$method}): {$response}");
        return null;
    }

    if (!($data['ok'] ?? false)) {
        $desc = $data['description'] ?? 'unknown';
        error_log("[TG] API error ({$method}): {$desc}");
        return null;
    }

    return $data;
}

/**
 * Kirim pesan teks biasa dengan Reply Keyboard (keyboard bawah layar).
 * $keyboard = array 2D berisi label tombol.
 * Jika $keyboard = null, keyboard tidak berubah.
 * Jika $keyboard = [], keyboard dihapus.
 */
function sendMsg(int $chat, string $text, ?array $keyboard = null, bool $inline = false): bool
{
    $params = [
        'chat_id'    => $chat,
        'text'       => $text,
        'parse_mode' => 'Markdown',
    ];

    if ($inline && $keyboard !== null) {
        // Inline keyboard (tombol di dalam pesan)
        $params['reply_markup'] = json_encode([
            'inline_keyboard' => $keyboard,
        ]);
    } elseif (!$inline && $keyboard !== null) {
        // Reply keyboard (tombol di bawah layar)
        $params['reply_markup'] = json_encode([
            'keyboard'          => $keyboard,
            'resize_keyboard'   => true,
            'one_time_keyboard' => false,
        ]);
    }

    $result = telegramRequest('sendMessage', $params);
    return $result !== null;
}

/**
 * Kirim pesan dan hapus keyboard (tombol bawah layar menghilang).
 */
function sendMsgRemoveKeyboard(int $chat, string $text): bool
{
    $params = [
        'chat_id'      => $chat,
        'text'         => $text,
        'parse_mode'   => 'Markdown',
        'reply_markup' => json_encode(['remove_keyboard' => true]),
    ];

    $result = telegramRequest('sendMessage', $params);
    return $result !== null;
}

/**
 * Edit pesan yang sudah ada (untuk memperbarui konten tanpa kirim baru).
 * Biasanya dipakai saat menekan tombol inline.
 */
function editMessage(int $chat, int $messageId, string $text, ?array $inlineKeyboard = null): bool
{
    $params = [
        'chat_id'    => $chat,
        'message_id' => $messageId,
        'text'       => $text,
        'parse_mode' => 'Markdown',
    ];

    if ($inlineKeyboard !== null) {
        $params['reply_markup'] = json_encode([
            'inline_keyboard' => $inlineKeyboard,
        ]);
    }

    $result = telegramRequest('editMessageText', $params);
    return $result !== null;
}

/**
 * Jawab callback_query agar ikon loading di tombol inline hilang.
 * $text = teks notifikasi kecil (opsional), $alert = tampilkan sebagai popup.
 */
function answerCallback(string $callbackId, string $text = '', bool $alert = false): void
{
    telegramRequest('answerCallbackQuery', [
        'callback_query_id' => $callbackId,
        'text'              => $text,
        'show_alert'        => $alert,
    ]);
}

/**
 * Kirim dokumen/file ke pengguna.
 * $filePath = path absolut file di server.
 */
function sendDocument(int $chat, string $filePath, string $caption = ''): bool
{
    if (!file_exists($filePath)) {
        error_log("[TG] sendDocument: file tidak ditemukan: {$filePath}");
        return false;
    }

    $token = getBotToken();
    $url   = "https://api.telegram.org/bot{$token}/sendDocument";

    $params = [
        'chat_id'  => $chat,
        'document' => new CURLFile($filePath),
    ];

    if ($caption !== '') {
        $params['caption']    = $caption;
        $params['parse_mode'] = 'Markdown';
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $params,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode((string) $response, true);
    return (bool) ($data['ok'] ?? false);
}

/**
 * Kirim foto ke pengguna.
 * $filePathOrUrl = path file di server ATAU URL gambar publik.
 */
function sendPhoto(int $chat, string $filePathOrUrl, string $caption = ''): bool
{
    $token = getBotToken();
    $url   = "https://api.telegram.org/bot{$token}/sendPhoto";

    // Jika URL, kirim sebagai string; jika path lokal, kirim sebagai CURLFile
    $photoValue = filter_var($filePathOrUrl, FILTER_VALIDATE_URL)
        ? $filePathOrUrl
        : new CURLFile($filePathOrUrl);

    $params = [
        'chat_id' => $chat,
        'photo'   => $photoValue,
    ];

    if ($caption !== '') {
        $params['caption']    = $caption;
        $params['parse_mode'] = 'Markdown';
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $params,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode((string) $response, true);
    return (bool) ($data['ok'] ?? false);
}

/**
 * Hapus pesan tertentu.
 */
function deleteMessage(int $chat, int $messageId): bool
{
    $result = telegramRequest('deleteMessage', [
        'chat_id'    => $chat,
        'message_id' => $messageId,
    ]);
    return $result !== null;
}

/**
 * Kirim file/foto jawaban siswa ke guru saat penilaian.
 * Deteksi tipe file dan kirim dengan caption informatif.
 * 
 * @param int $chatId Chat ID guru
 * @param array $detail Array detail pengumpulan (dari getPengumpulanDetail)
 * @return bool true jika berhasil dikirim
 */
function sendSubmissionFileToGuru(int $chatId, array $detail): bool
{
    // Jika tidak ada file, hanya teks - tidak perlu kirim
    if (empty($detail['file_path'])) {
        return true; // Bukan error, hanya tidak ada file
    }

    $filePath = (string) $detail['file_path'];
    
    // Convert relative path ke absolute path
    // Path dari DB bisa relative seperti "uploads/submissions/2026/04/sub_123.jpg"
    if (!str_starts_with($filePath, '/')) {
        // Path relative: tambahkan base directory
        $filePath = __DIR__ . '/../' . $filePath;
    }

    // Normalize path
    $filePath = realpath($filePath);
    
    // Pastikan file ada
    if (!$filePath || !file_exists($filePath)) {
        error_log("[TG] File submission tidak ditemukan: " . ($detail['file_path'] ?? 'unknown'));
        return false;
    }

    // Validasi file dalam direktori yang diizinkan (security check)
    $uploadsDir = realpath(__DIR__ . '/../uploads');
    if ($uploadsDir && !str_starts_with($filePath, $uploadsDir)) {
        error_log("[TG] File submission di luar direktori uploads: {$filePath}");
        return false;
    }

    $fileName = $detail['file_nama_asli'] ?? basename($filePath);

    // Deteksi tipe file dari extension
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp']);

    // Format caption informatif
    $siswa = $detail['nama_lengkap'] ?? 'Siswa';
    $tugas = $detail['judul'] ?? 'Tugas';
    $waktu = date('d/m/Y H:i', strtotime($detail['waktu_kumpul'] ?? 'now'));

    $caption = "📎 *Jawaban Siswa*\n"
             . "👤 {$siswa}\n"
             . "📝 {$tugas}\n"
             . "🕐 {$waktu}\n"
             . "📄 {$fileName}";

    // Kirim file sesuai tipe
    if ($isImage) {
        return sendPhoto($chatId, $filePath, $caption);
    } else {
        return sendDocument($chatId, $filePath, $caption);
    }
}

/**
 * Kirim aksi "mengetik..." agar pengguna tahu bot sedang memproses.
 */
function sendTyping(int $chat): void
{
    telegramRequest('sendChatAction', [
        'chat_id' => $chat,
        'action'  => 'typing',
    ]);
}

/**
 * Pin pesan di chat.
 */
function pinMessage(int $chat, int $messageId): bool
{
    $result = telegramRequest('pinChatMessage', [
        'chat_id'              => $chat,
        'message_id'           => $messageId,
        'disable_notification' => true,
    ]);
    return $result !== null;
}


// ============================================================
// KEYBOARD BUILDERS
// ============================================================

/**
 * Keyboard utama berdasarkan role pengguna.
 * Ini adalah reply keyboard yang selalu tampil di bawah layar.
 */
function mainKeyboard(string $role): array
{
    $base = [
        ['📅 Jadwal Hari Ini', '📆 Jadwal Minggu Ini'],
        ['📢 Pengumuman', '📝 Tugas'],
    ];

    switch ($role) {
        case 'guru':
            $base[] = ['📢 Kirim Pengumuman', '✏️ Buat Tugas'];
            $base[] = ['🎓 Kelas Saya', '📋 Analisis Tugas'];
            $base[] = ['⭐ Nilai Tugas'];
            break;

        case 'siswa':
            $base[] = ['🔄 Kumpulkan Tugas', '🏆 Lihat Nilai'];
            break;
    }

    $base[] = ['⚙️ Pengaturan', '🚪 Logout'];

    return $base;
}

/**
 * Keyboard sub-menu Pengaturan.
 */
function settingsKeyboard(): array
{
    return [
        ['🔑 Ganti Password'],
        ['🕐 Atur Jam Notifikasi'],
        ['🔙 Kembali ke Menu'],
    ];
}

/**
 * Buat inline keyboard dari array asosiatif [label => callback_data].
 * $perBaris = jumlah tombol per baris.
 *
 * Contoh: buildInlineKeyboard(['Lihat' => 'view_1', 'Hapus' => 'del_1'], 2)
 */
function buildInlineKeyboard(array $buttons, int $perBaris = 2): array
{
    $keyboard = [];
    $row      = [];

    foreach ($buttons as $label => $callbackData) {
        $row[] = ['text' => $label, 'callback_data' => $callbackData];

        if (count($row) >= $perBaris) {
            $keyboard[] = $row;
            $row        = [];
        }
    }

    // Sisa tombol yang belum penuh satu baris
    if (!empty($row)) {
        $keyboard[] = $row;
    }

    return $keyboard;
}

/**
 * Buat satu tombol inline dengan URL (buka browser).
 */
function buildInlineUrlButton(string $label, string $url): array
{
    return [['text' => $label, 'url' => $url]];
}


// ============================================================
// HELPER FORMAT TEKS
// ============================================================

/**
 * Escape karakter khusus Markdown V1 agar tidak merusak format.
 * (Telegram Markdown V1 hanya perlu escape backtick dan underscore di luar bold/italic)
 */
function escMd(string $text): string
{
    return str_replace(['_', '*', '`', '['], ['\_', '\*', '\`', '\['], $text);
}

/**
 * Format tanggal dari Y-m-d H:i:s ke format Indonesia yang ramah.
 * Contoh: "Kamis, 15 Mei 2025 | 09:30"
 */
function formatTglWib(string $datetime): string
{
    $ts = strtotime($datetime);
    if (!$ts) return '-';

    $hariMap = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $bulanMap = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
        5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu',
        9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
    ];

    $hari  = $hariMap[(int) date('w', $ts)];
    $tgl   = (int) date('j', $ts);
    $bln   = $bulanMap[(int) date('n', $ts)];
    $tahun = date('Y', $ts);
    $jam   = date('H:i', $ts);

    return "{$hari}, {$tgl} {$bln} {$tahun} | {$jam}";
}

/**
 * Format hanya tanggal saja (tanpa jam).
 */
function formatTgl(string $date): string
{
    $ts = strtotime($date);
    if (!$ts) return '-';

    $bulanMap = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    return (int) date('j', $ts) . ' ' . $bulanMap[(int) date('n', $ts)] . ' ' . date('Y', $ts);
}

/**
 * Format sisa waktu sampai deadline menjadi teks manusiawi.
 * Contoh: "2 hari lagi", "3 jam lagi", "sudah lewat"
 */
function sisaWaktu(string $tenggat): string
{
    $sisa = strtotime($tenggat) - time();

    if ($sisa <= 0) return '⚠️ sudah lewat';
    if ($sisa < 3600) return round($sisa / 60) . ' menit lagi';
    if ($sisa < 86400) return round($sisa / 3600) . ' jam lagi';

    return round($sisa / 86400) . ' hari lagi';
}

/**
 * Nama hari Indonesia dari tanggal hari ini.
 */
function hariIni(): string
{
    $map = [0 => 'Minggu', 1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu'];
    return $map[(int) date('w')];
}

/**
 * Daftar nama hari mulai Senin.
 */
function daftarHari(): array
{
    return ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
}

/**
 * Potong teks panjang dan tambahkan "..." jika melebihi batas.
 */
function potongTeks(string $text, int $maks = 200): string
{
    if (mb_strlen($text) <= $maks) return $text;
    return mb_substr($text, 0, $maks) . '...';
}

/**
 * Buat progress bar teks sederhana.
 * Contoh: "████░░░░░░ 40%"
 */
function progressBar(int $value, int $total, int $panjang = 10): string
{
    if ($total <= 0) return str_repeat('░', $panjang) . ' 0%';

    $persen = min(100, (int) round($value / $total * 100));
    $terisi = (int) round($panjang * $persen / 100);
    $kosong = $panjang - $terisi;

    return str_repeat('█', $terisi) . str_repeat('░', $kosong) . " {$persen}%";
}

/**
 * Format nilai dengan bintang (untuk feedback penilaian).
 * Contoh: nilai 85 dari 100 → "⭐⭐⭐⭐☆ (85/100)"
 */
function formatNilaiBintang(float $nilai, int $maksimal = 100): string
{
    $persen    = $nilai / $maksimal * 100;
    $bintang   = (int) round($persen / 20); // 0-5 bintang
    $terisi    = str_repeat('⭐', $bintang);
    $kosong    = str_repeat('☆', 5 - $bintang);
    $nilaiStr  = is_int($nilai) ? (string)(int)$nilai : number_format($nilai, 1);

    return "{$terisi}{$kosong} ({$nilaiStr}/{$maksimal})";
}


// ============================================================
// STATE MANAGEMENT (percakapan beruntun)
// ============================================================

function _stateFilePath(): string
{
    return (string) sirey_getConfigValue('app.state_file', __DIR__ . '/../data/state.json');
}

function loadAllStates(): array
{
    $file = _stateFilePath();
    if (!file_exists($file)) return [];

    for ($i = 0; $i < 3; $i++) {
        $content = @file_get_contents($file);
        if ($content !== false) {
            $data = json_decode($content, true);
            return is_array($data) ? $data : [];
        }
        usleep(100_000); // tunggu 0.1 detik lalu coba lagi
    }

    return [];
}

function saveAllStates(array $data): void
{
    $file = _stateFilePath();
    $dir  = dirname($file);

    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    for ($i = 0; $i < 3; $i++) {
        $result = @file_put_contents(
            $file,
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            LOCK_EX
        );
        if ($result !== false) return;
        usleep(100_000);
    }

    error_log('[STATE] Gagal menyimpan state setelah 3 percobaan');
}

function getState(int $chatId): ?array
{
    $states = loadAllStates();
    return $states[(string) $chatId] ?? null;
}

function setState(int $chatId, ?array $state): void
{
    $states = loadAllStates();

    if ($state === null) {
        unset($states[(string) $chatId]);
    } else {
        $states[(string) $chatId] = $state;
    }

    saveAllStates($states);
}


// ============================================================
// SESSION TELEGRAM
// ============================================================

/**
 * Buat session baru saat login Telegram.
 * Invalidasi session lama agar tidak bisa login ganda.
 * Mengembalikan token sesi (plain, bukan hash).
 */
function createTelegramLoginSession(int $akunId, int $chatId): string
{
    $plainToken  = bin2hex(random_bytes(32));
    $hashedToken = hash('sha256', $plainToken);

    // Nonaktifkan session lama milik akun ini
    sirey_execute(
        'UPDATE telegram_sessions_rayhanrp
         SET is_active = 0, invalidated_at = NOW()
         WHERE akun_id = ? AND is_active = 1',
        'i',
        $akunId
    );

    // Hapus mapping Telegram lama (satu akun = satu device)
    sirey_execute(
        'DELETE FROM akun_telegram_rayhanrp WHERE akun_id = ? OR telegram_chat_id = ?',
        'ii',
        $akunId,
        $chatId
    );

    // Daftarkan mapping baru
    sirey_execute(
        'INSERT INTO akun_telegram_rayhanrp (akun_id, telegram_chat_id) VALUES (?, ?)',
        'ii',
        $akunId,
        $chatId
    );

    // Buat session baru
    sirey_execute(
        'INSERT INTO telegram_sessions_rayhanrp
         (akun_id, chat_id, session_token, is_active, created_at, last_seen_at)
         VALUES (?, ?, ?, 1, NOW(), NOW())',
        'iis',
        $akunId,
        $chatId,
        $hashedToken
    );

    return $plainToken;
}

/**
 * Nonaktifkan session Telegram (logout).
 */
function invalidateTelegramSession(int $chatId): void
{
    sirey_execute(
        'UPDATE telegram_sessions_rayhanRP
         SET is_active = 0, invalidated_at = NOW()
         WHERE chat_id = ? AND is_active = 1',
        'i',
        $chatId
    );

    sirey_execute(
        'DELETE FROM akun_telegram_rayhanrp WHERE telegram_chat_id = ?',
        'i',
        $chatId
    );
}

/**
 * Ambil data user yang sudah login berdasarkan chat_id dan session_token.
 * Jika sessionToken null, cek berdasarkan chat_id saja (kurang aman, hanya fallback).
 */
function getRegisteredUser(int $chatId, ?string $sessionToken = null): ?array
{
    $tokenCondition = '';
    $types          = 'i';
    $params         = [$chatId];

    if ($sessionToken !== null && $sessionToken !== '') {
        $tokenCondition = ' AND ts.session_token = ?';
        $types         .= 's';
        $params[]       = hash('sha256', $sessionToken);
    }

    $row = sirey_fetch(sirey_query(
        "SELECT a.akun_id, a.nama_lengkap, a.role, a.nis_nip,
                at.telegram_chat_id,
                COALESCE(ns.notif_jadwal, 1)     AS notif_jadwal,
                COALESCE(ns.notif_tugas, 1)      AS notif_tugas,
                COALESCE(ns.notif_pengumuman, 1) AS notif_pengumuman,
                COALESCE(ns.notif_nilai, 1)      AS notif_nilai
         FROM akun_telegram_rayhanrp at
         INNER JOIN akun_rayhanrp a ON at.akun_id = a.akun_id
         INNER JOIN telegram_sessions_rayhanrp ts
                 ON ts.akun_id = a.akun_id
                AND ts.chat_id = at.telegram_chat_id
                AND ts.is_active = 1
         LEFT JOIN notif_settings_rayhanrp ns ON ns.akun_id = a.akun_id
         WHERE at.telegram_chat_id = ?
           {$tokenCondition}
         LIMIT 1",
        $types,
        ...$params
    ));

    if ($row === null) return null;

    // Update waktu terakhir aktif
    sirey_execute(
        'UPDATE telegram_sessions_rayhanrp
         SET last_seen_at = NOW()
         WHERE chat_id = ? AND is_active = 1',
        'i',
        $chatId
    );

    return $row;
}

/**
 * Verifikasi password yang tersimpan di DB (support bcrypt dan plain).
 */
function verifyPassword(array $akun, string $inputPassword): bool
{
    $stored = (string) $akun['password'];

    // Bcrypt hash dimulai dengan $2
    if (str_starts_with($stored, '$2')) {
        return password_verify($inputPassword, $stored);
    }

    // Plain text (legacy) — bandingkan aman tanpa timing attack
    return hash_equals($stored, $inputPassword);
}

/**
 * Update password akun ke bcrypt baru.
 */
function updatePassword(int $akunId, string $passwordBaru): bool
{
    $hash   = password_hash($passwordBaru, PASSWORD_BCRYPT, ['cost' => 10]);
    $result = sirey_execute(
        'UPDATE akun_rayhanrp SET password = ? WHERE akun_id = ?',
        'si',
        $hash,
        $akunId
    );
    return $result > 0;
}


// ============================================================
// PENGATURAN NOTIFIKASI
// ============================================================

/**
 * Ambil jam notifikasi pengguna.
 * Default: jadwal 07:00, tugas 12:00.
 */
function getJamNotifikasi(int $akunId): array
{
    $row = sirey_fetch(sirey_query(
        "SELECT TIME_FORMAT(COALESCE(jam_notif_jadwal, '07:00:00'), '%H:%i') AS jam_jadwal,
                TIME_FORMAT(COALESCE(jam_notif_tugas,  '12:00:00'), '%H:%i') AS jam_tugas
         FROM notif_settings_rayhanrp
         WHERE akun_id = ?
         LIMIT 1",
        'i',
        $akunId
    ));

    return $row ?? ['jam_jadwal' => '07:00', 'jam_tugas' => '12:00'];
}

/**
 * Simpan jam notifikasi baru.
 * Format jam: "HH:MM" (misal: "07:00").
 */
function updateJamNotifikasi(int $akunId, string $jamJadwal, string $jamTugas): bool
{
    $pola = '/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/';
    if (!preg_match($pola, $jamJadwal) || !preg_match($pola, $jamTugas)) {
        return false;
    }

    $result = sirey_execute(
        'INSERT INTO notif_settings_rayhanrp (akun_id, jam_notif_jadwal, jam_notif_tugas)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE jam_notif_jadwal = ?, jam_notif_tugas = ?',
        'issss',
        $akunId,
        $jamJadwal,
        $jamTugas,
        $jamJadwal,
        $jamTugas
    );

    return $result >= 0;
}


// ============================================================
// DATA: JADWAL
// ============================================================

/**
 * Ambil jadwal siswa untuk hari tertentu.
 */
function getJadwalHari(int $akunId, string $hari): array
{
    return sirey_fetchAll(sirey_query(
        'SELECT mp.nama AS matpel, gm.jam_mulai, gm.jam_selesai,
                a.nama_lengkap AS guru_nama, g.nama_grup
         FROM grup_anggota_rayhanrp ga
         INNER JOIN guru_mengajar_rayhanrp gm ON ga.grup_id = gm.grup_id
         INNER JOIN mata_pelajaran_rayhanrp mp ON gm.matpel_id = mp.matpel_id
         INNER JOIN akun_rayhanrp a ON gm.akun_id = a.akun_id
         INNER JOIN grup_rayhanrp g ON ga.grup_id = g.grup_id
         WHERE ga.akun_id = ? AND ga.aktif = 1 AND gm.hari = ? AND gm.aktif = 1
         ORDER BY gm.jam_mulai ASC',
        'is',
        $akunId,
        $hari
    ));
}

/**
 * Ambil jadwal mengajar guru untuk hari tertentu.
 */
function getJadwalGuruHari(int $akunId, string $hari): array
{
    return sirey_fetchAll(sirey_query(
        'SELECT mp.nama AS matpel, gm.jam_mulai, gm.jam_selesai, g.nama_grup
         FROM guru_mengajar_rayhanrp gm
         INNER JOIN mata_pelajaran_rayhanrp mp ON gm.matpel_id = mp.matpel_id
         INNER JOIN grup_rayhanrp g ON gm.grup_id = g.grup_id
         WHERE gm.akun_id = ? AND gm.hari = ? AND gm.aktif = 1
         ORDER BY gm.jam_mulai ASC',
        'is',
        $akunId,
        $hari
    ));
}

/**
 * Format daftar jadwal satu hari menjadi teks Telegram yang rapi.
 */
function formatJadwalHari(array $jadwal, string $hari, string $tanggal, string $role): string
{
    $header = "📅 *Jadwal {$hari}* — {$tanggal}\n";
    $header .= str_repeat('─', 24) . "\n\n";

    if (empty($jadwal)) {
        return $header . "_(Tidak ada jadwal hari ini)_ 😴\n\nSelamat beristirahat! 🌟";
    }

    $baris = [];
    foreach ($jadwal as $j) {
        $mulai   = substr((string) $j['jam_mulai'],   0, 5);
        $selesai = substr((string) $j['jam_selesai'], 0, 5);
        $entry   = "🕐 *{$mulai} – {$selesai}*\n";
        $entry  .= "   📚 {$j['matpel']}\n";

        if ($role === 'siswa' && !empty($j['guru_nama'])) {
            $entry .= "   👨‍🏫 {$j['guru_nama']}\n";
        }

        if ($role === 'guru' && !empty($j['nama_grup'])) {
            $entry .= "   🎓 {$j['nama_grup']}\n";
        }

        $baris[] = $entry;
    }
    
    return $header . implode("\n", $baris) . "\n" . str_repeat('─', 24) . "\n_Semangat belajar hari ini!_ 💪";
}


// ============================================================
// DATA: PENGUMUMAN
// ============================================================

/**
 * Ambil pengumuman terbaru yang relevan untuk pengguna.
 */
function getPengumuman(int $akunId): array
{
    return sirey_fetchAll(sirey_query(
        "SELECT p.judul, p.isi, p.prioritas, p.dibuat_pada, a.nama_lengkap AS pembuat
         FROM pengumuman_rayhanRP p
         LEFT JOIN akun_rayhanRP a ON p.pembuat_id = a.akun_id
         WHERE p.status = 'published'
           AND (
               p.grup_id IS NULL
               OR p.grup_id IN (
                   SELECT grup_id FROM grup_anggota_rayhanRP WHERE akun_id = ? AND aktif = 1
               )
           )
         ORDER BY p.dibuat_pada DESC
         LIMIT 5",
        'i',
        $akunId
    ));
}

/**
 * Simpan pengumuman yang dikirim guru lewat bot.
 * Juga mengirim notifikasi ke siswa di kelas yang bersangkutan.
 */
function simpanPengumumanGuru(array $data): bool
{
    $hasil = sirey_execute(
        "INSERT INTO pengumuman_rayhanRP
         (judul, isi, pembuat_id, grup_id, prioritas, target_role, status, tanggal_tayang, via_telegram)
         VALUES (?, ?, ?, ?, 'biasa', 'siswa', 'published', CURDATE(), 1)",
        'ssii',
        $data['judul'],
        $data['isi'],
        $data['pembuat_id'],
        $data['grup_id']
    );

    if ($hasil < 1) return false;

    $pengumumanId = sirey_lastInsertId();

    // Kirim notifikasi ke siswa di kelas
    $targets = sirey_fetchAll(sirey_query(
        'SELECT DISTINCT at.telegram_chat_id
         FROM akun_telegram_rayhanRP at
         INNER JOIN grup_anggota_rayhanRP ga ON at.akun_id = ga.akun_id
         INNER JOIN akun_rayhanRP a ON at.akun_id = a.akun_id
         WHERE ga.grup_id = ? AND ga.aktif = 1 AND a.role = "siswa"',
        'i',
        $data['grup_id']
    ));

    $guru = sirey_fetch(sirey_query(
        'SELECT nama_lengkap FROM akun_rayhanRP WHERE akun_id = ?',
        'i',
        $data['pembuat_id']
    ));

    $namaPembuat = $guru['nama_lengkap'] ?? 'Guru';

    $pesan = "📢 *Pengumuman Baru*\n\n"
           . "*{$data['judul']}*\n\n"
           . "{$data['isi']}\n\n"
           . "_— {$namaPembuat}_";

    $terkirim = 0;
    foreach ($targets as $t) {
        $cid = (int) ($t['telegram_chat_id'] ?? 0);
        if ($cid > 0 && sendMsg($cid, $pesan)) {
            $terkirim++;
        }
    }

    // Catat di log notifikasi
    if ($terkirim > 0) {
        sirey_execute(
            "INSERT INTO notifikasi_rayhanRP (tipe, sumber_tipe, sumber_id, grup_id, pesan, jumlah_terkirim)
             VALUES ('pengumuman', 'pengumuman', ?, ?, ?, ?)",
            'iisi',
            $pengumumanId,
            $data['grup_id'],
            "[{$data['judul']}] {$data['isi']}",
            $terkirim
        );
    }

    return true;
}


// ============================================================
// DATA: TUGAS
// ============================================================

/**
 * Ambil tugas aktif untuk siswa yang belum dikumpulkan.
 */
function getTugasBelumDikumpul(int $akunId): array
{
    // Tugas grup
    $grup = sirey_fetchAll(sirey_query(
        'SELECT t.tugas_id, t.judul, t.tenggat, mp.nama AS matpel,
                g.nama_grup, DATEDIFF(t.tenggat, NOW()) AS hari_sisa
         FROM tugas_rayhanRP t
         INNER JOIN grup_anggota_rayhanRP ga ON t.grup_id = ga.grup_id
         LEFT JOIN mata_pelajaran_rayhanRP mp ON t.matpel_id = mp.matpel_id
         LEFT JOIN grup_rayhanRP g ON t.grup_id = g.grup_id
         LEFT JOIN pengumpulan_rayhanRP p
                ON p.tugas_id = t.tugas_id AND p.akun_id = ?
         WHERE ga.akun_id = ? AND t.status = "active" AND p.pengumpulan_id IS NULL
         ORDER BY t.tenggat ASC',
        'ii',
        $akunId,
        $akunId
    ));

    // Tugas perorangan
    $perorang = sirey_fetchAll(sirey_query(
        'SELECT t.tugas_id, t.judul, t.tenggat, mp.nama AS matpel,
                "Perorangan" AS nama_grup, DATEDIFF(t.tenggat, NOW()) AS hari_sisa
         FROM tugas_perorang_rayhanRP tp
         INNER JOIN tugas_rayhanRP t ON tp.tugas_id = t.tugas_id
         LEFT JOIN mata_pelajaran_rayhanRP mp ON t.matpel_id = mp.matpel_id
         LEFT JOIN pengumpulan_rayhanRP p
                ON p.tugas_id = t.tugas_id AND p.akun_id = ?
         WHERE tp.akun_id = ? AND t.status = "active" AND p.pengumpulan_id IS NULL
         ORDER BY t.tenggat ASC',
        'ii',
        $akunId,
        $akunId
    ));

    return array_merge($grup, $perorang);
}

/**
 * Ambil tugas yang diminta revisi oleh guru.
 */
function getTugasRevisiPending(int $akunId): array
{
    return sirey_fetchAll(sirey_query(
        'SELECT t.tugas_id, t.judul, t.tenggat, mp.nama AS matpel,
                g.nama_grup, pn.catatan_guru, pn.nilai
         FROM tugas_rayhanRP t
         INNER JOIN pengumpulan_rayhanRP p ON p.tugas_id = t.tugas_id
         INNER JOIN penilaian_rayhanRP pn ON pn.pengumpulan_id = p.pengumpulan_id
         LEFT JOIN mata_pelajaran_rayhanRP mp ON t.matpel_id = mp.matpel_id
         LEFT JOIN grup_rayhanRP g ON t.grup_id = g.grup_id
         WHERE p.akun_id = ?
           AND pn.status_lulus = "revisi"
         ORDER BY t.tenggat ASC',
        'i',
        $akunId
    ));
}

/**
 * Ambil semua tugas aktif untuk siswa (sudah dan belum dikumpulkan).
 */
function getTugasSiswa(int $akunId): array
{
    $grup = sirey_fetchAll(sirey_query(
        'SELECT t.tugas_id, t.judul, t.tenggat, mp.nama AS matpel, g.nama_grup,
                (SELECT COUNT(*) FROM pengumpulan_rayhanRP p
                 WHERE p.tugas_id = t.tugas_id AND p.akun_id = ?) AS sudah_kumpul
         FROM tugas_rayhanRP t
         INNER JOIN grup_anggota_rayhanRP ga ON t.grup_id = ga.grup_id
         LEFT JOIN mata_pelajaran_rayhanRP mp ON t.matpel_id = mp.matpel_id
         LEFT JOIN grup_rayhanRP g ON t.grup_id = g.grup_id
         WHERE ga.akun_id = ? AND t.status = "active"
         ORDER BY t.tenggat ASC
         LIMIT 10',
        'ii',
        $akunId,
        $akunId
    ));

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
        'ii',
        $akunId,
        $akunId
    ));

    return array_merge($grup, $perorang);
}

/**
 * Ambil tugas yang dibuat oleh guru (untuk ditampilkan di menu "Tugas" guru).
 */
function getTugasGuru(int $guruId): array
{
    return sirey_fetchAll(sirey_query(
        'SELECT t.tugas_id, t.judul, t.tenggat, mp.nama AS matpel, g.nama_grup,
                (SELECT COUNT(*) FROM pengumpulan_rayhanRP p WHERE p.tugas_id = t.tugas_id) AS jml_kumpul
         FROM tugas_rayhanRP t
         LEFT JOIN mata_pelajaran_rayhanRP mp ON t.matpel_id = mp.matpel_id
         LEFT JOIN grup_rayhanRP g ON t.grup_id = g.grup_id
         WHERE t.pembuat_id = ? AND t.status = "active"
         ORDER BY t.tenggat DESC
         LIMIT 10',
        'i',
        $guruId
    ));
}

/**
 * Simpan tugas baru dari bot Telegram (dibuat guru).
 */
function simpanTugasBot(array $data): bool
{
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

    $tugasId = sirey_lastInsertId();

    // Notifikasi ke siswa
    $targets = sirey_fetchAll(sirey_query(
        'SELECT at.telegram_chat_id
         FROM akun_telegram_rayhanRP at
         INNER JOIN grup_anggota_rayhanRP ga ON at.akun_id = ga.akun_id
         INNER JOIN akun_rayhanRP a ON at.akun_id = a.akun_id
         WHERE ga.grup_id = ? AND ga.aktif = 1 AND a.role = "siswa"',
        'i',
        $data['grup_id']
    ));

    $tglDeadline = date('d/m/Y H:i', strtotime($data['tenggat']));
    $pesan       = "📝 *Tugas Baru!*\n\n"
                 . "*{$data['judul']}*\n"
                 . ($data['deskripsi'] ? "{$data['deskripsi']}\n\n" : "\n")
                 . "📅 Deadline: {$tglDeadline}\n\n"
                 . "_Segera kerjakan!_ ✍️";

    $terkirim = 0;
    foreach ($targets as $t) {
        $cid = (int) ($t['telegram_chat_id'] ?? 0);
        if ($cid > 0 && sendMsg($cid, $pesan)) {
            $terkirim++;
        }
    }

    if ($terkirim > 0) {
        sirey_execute(
            "INSERT INTO notifikasi_rayhanRP (tipe, grup_id, pesan, jumlah_terkirim)
             VALUES ('tugas', ?, ?, ?)",
            'isi',
            $data['grup_id'],
            "Tugas baru: {$data['judul']}",
            $terkirim
        );
    }

    return true;
}

/**
 * Ambil detail satu tugas berdasarkan ID.
 */
function getTugasDetail(int $tugasId): ?array
{
    return sirey_fetch(sirey_query(
        'SELECT t.tugas_id, t.judul, t.deskripsi, t.tenggat, t.poin_maksimal,
                mp.nama AS matpel, g.nama_grup, g.grup_id
         FROM tugas_rayhanRP t
         LEFT JOIN mata_pelajaran_rayhanRP mp ON t.matpel_id = mp.matpel_id
         LEFT JOIN grup_rayhanRP g ON t.grup_id = g.grup_id
         WHERE t.tugas_id = ?',
        'i',
        $tugasId
    ));
}


// ============================================================
// DATA: PENGUMPULAN TUGAS
// ============================================================

/**
 * Simpan pengumpulan tugas dari bot Telegram.
 */
function simpanPengumpulanTugas(
    int     $akunId,
    int     $tugasId,
    ?string $teksJawaban = null,
    ?string $filePath    = null,
    ?string $fileNamaAsli  = null
): bool {
    $tugas = getTugasDetail($tugasId);
    if (!$tugas) return false;

    // Tepat waktu atau terlambat?
    $status = time() <= strtotime((string) $tugas['tenggat']) ? 'dikumpulkan' : 'terlambat';

    // Generate nama file asli dari file path jika tidak diberikan
    if ($filePath && !$fileNamaAsli) {
        $fileNamaAsli = basename($filePath);
    }

    // Cek apakah sudah ada pengumpulan sebelumnya
    $existing = sirey_fetch(sirey_query(
        'SELECT pengumpulan_id FROM pengumpulan_rayhanRP WHERE akun_id = ? AND tugas_id = ?',
        'ii',
        $akunId,
        $tugasId
    ));

    if ($existing) {
        // UPDATE pengumpulan yang ada (untuk revisi/resubmit)
        $hasil = sirey_execute(
            'UPDATE pengumpulan_rayhanRP 
             SET teks_jawaban = ?, file_path = ?, file_nama_asli = ?, status = ?, waktu_kumpul = NOW()
             WHERE akun_id = ? AND tugas_id = ?',
            'ssssii',
            $teksJawaban ?? '',
            $filePath ?? '',
            $fileNamaAsli ?? '',
            $status,
            $akunId,
            $tugasId
        );

        // UPDATE bisa return 0 jika data tidak berubah, tapi query tetap berhasil
        // Hanya return false jika query error (false bukan 0)
        if ($hasil === false) {
            error_log("[KUMPUL] UPDATE query error untuk akun_id=$akunId, tugas_id=$tugasId");
            return false;
        }

        error_log("[KUMPUL] UPDATE berhasil: akun_id=$akunId, tugas_id=$tugasId, affected_rows=$hasil");
    } else {
        // INSERT pengumpulan baru (pertama kali)
        $hasil = sirey_execute(
            'INSERT INTO pengumpulan_rayhanRP
             (akun_id, tugas_id, teks_jawaban, file_path, file_nama_asli, status, waktu_kumpul, via)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), "telegram")',
            'iissss',
            $akunId,
            $tugasId,
            $teksJawaban ?? '',
            $filePath    ?? '',
            $fileNamaAsli  ?? '',
            $status
        );

        if ($hasil < 1) {
            error_log("[KUMPUL] INSERT gagal untuk akun_id=$akunId, tugas_id=$tugasId");
            return false;
        }

        error_log("[KUMPUL] INSERT berhasil: akun_id=$akunId, tugas_id=$tugasId");
    }

    // Notifikasi ke guru pembuat tugas
    $guru = sirey_fetch(sirey_query(
        'SELECT at.telegram_chat_id FROM tugas_rayhanRP t
         INNER JOIN akun_telegram_rayhanRP at ON t.pembuat_id = at.akun_id
         WHERE t.tugas_id = ?',
        'i',
        $tugasId
    ));

    if ($guru && !empty($guru['telegram_chat_id'])) {
        $siswa = sirey_fetch(sirey_query(
            'SELECT nama_lengkap FROM akun_rayhanRP WHERE akun_id = ?',
            'i',
            $akunId
        ));

        $emoji = $status === 'dikumpulkan' ? '✅' : '⚠️';
        $pesan = "📬 *Pengumpulan Tugas*\n\n"
               . "*{$tugas['judul']}*\n"
               . "👤 {$siswa['nama_lengkap']}\n"
               . "⏰ " . date('d/m/Y H:i') . "\n"
               . "{$emoji} " . ucfirst($status);

        sendMsg((int) $guru['telegram_chat_id'], $pesan);
    }

    return true;
}

/**
 * Simpan revisi pengumpulan tugas.
 * Data revisi masuk ke tabel pengumpulan_versi_rayhanRP, menunggu persetujuan guru.
 */
function simpanRevisiTugas(
    int     $akunId,
    int     $tugasId,
    ?string $teksJawaban = null,
    ?string $filePath    = null
): bool {
    $pengumpulan = sirey_fetch(sirey_query(
        'SELECT pengumpulan_id FROM pengumpulan_rayhanRP WHERE akun_id = ? AND tugas_id = ?',
        'ii',
        $akunId,
        $tugasId
    ));

    if (!$pengumpulan) return false;

    $pengumpulanId = (int) $pengumpulan['pengumpulan_id'];

    // Nomor versi berikutnya
    $maxVersi = sirey_fetch(sirey_query(
        'SELECT MAX(nomor_versi) AS max_ver FROM pengumpulan_versi_rayhanRP WHERE pengumpulan_id = ?',
        'i',
        $pengumpulanId
    ));
    $nomorVersi = ((int) ($maxVersi['max_ver'] ?? 0)) + 1;

    $hasil = sirey_execute(
        'INSERT INTO pengumpulan_versi_rayhanRP
         (pengumpulan_id, nomor_versi, teks_jawaban, file_path, file_nama_asli,
          versi_tipe, disubmit_oleh, status_approval, dibuat_pada)
         VALUES (?, ?, ?, ?, ?, "revisi", ?, "disetujui", NOW())',
        'iisssi',
        $pengumpulanId,
        $nomorVersi,
        $teksJawaban ?? '',
        $filePath    ?? '',
        $filePath    ? basename($filePath) : '',
        $akunId
    );

    if ($hasil < 1) return false;

    // Update pengumpulan_rayhanRP dengan data revisi terbaru
    // Sehingga dashboard penilaian menampilkan versi terbaru
    sirey_execute(
        'UPDATE pengumpulan_rayhanRP 
         SET teks_jawaban = ?, file_path = ?, file_nama_asli = ?, waktu_kumpul = NOW()
         WHERE pengumpulan_id = ?',
        'sssi',
        $teksJawaban ?? '',
        $filePath ?? '',
        $filePath ? basename($filePath) : '',
        $pengumpulanId
    );

    // CATATAN: Jangan auto-approve revisi ke "lulus"!
    // Guru harus re-evaluate revisi dan memilih status sendiri
    // Penilaian lama tetap berlaku sampai guru memilih status baru

    // Notifikasi ke guru
    $tugas = sirey_fetch(sirey_query(
        'SELECT t.judul, at.telegram_chat_id
         FROM tugas_rayhanRP t
         INNER JOIN akun_telegram_rayhanRP at ON t.pembuat_id = at.akun_id
         WHERE t.tugas_id = ?',
        'i',
        $tugasId
    ));

    if ($tugas && !empty($tugas['telegram_chat_id'])) {
        $siswa = sirey_fetch(sirey_query(
            'SELECT nama_lengkap FROM akun_rayhanRP WHERE akun_id = ?',
            'i',
            $akunId
        ));

        $pesan = "📝 *Revisi Tugas Masuk (v{$nomorVersi})*\n\n"
               . "*{$tugas['judul']}*\n"
               . "👤 {$siswa['nama_lengkap']}\n"
               . "⏰ " . date('d/m/Y H:i') . "\n\n"
               . "_Tinjau revisi di dashboard._";

        sendMsg((int) $tugas['telegram_chat_id'], $pesan);
    }

    return true;
}


// ============================================================
// DATA: PENILAIAN
// ============================================================

/**
 * Ambil nilai tugas siswa (sudah dinilai).
 */
function getNilaiSiswa(int $akunId): array
{
    return sirey_fetchAll(sirey_query(
        'SELECT t.judul, mp.nama AS matpel, g.nama_grup,
                pn.nilai, t.poin_maksimal, pn.catatan_guru, pn.status_lulus,
                ROUND(pn.nilai / t.poin_maksimal * 100, 1) AS persentase
         FROM penilaian_rayhanRP pn
         INNER JOIN pengumpulan_rayhanRP p ON pn.pengumpulan_id = p.pengumpulan_id
         INNER JOIN tugas_rayhanRP t ON p.tugas_id = t.tugas_id
         LEFT JOIN mata_pelajaran_rayhanRP mp ON t.matpel_id = mp.matpel_id
         LEFT JOIN grup_rayhanRP g ON t.grup_id = g.grup_id
         WHERE p.akun_id = ? AND pn.nilai IS NOT NULL
         ORDER BY pn.dinilai_pada DESC
         LIMIT 20',
        'i',
        $akunId
    ));
}

/**
 * Ambil tugas yang sudah dikumpulkan tapi belum dinilai.
 */
function getTugasYangBelumDinilai(int $akunId): array
{
    return sirey_fetchAll(sirey_query(
        'SELECT t.judul, mp.nama AS matpel, g.nama_grup, p.waktu_kumpul, t.tenggat, t.poin_maksimal
         FROM pengumpulan_rayhanRP p
         INNER JOIN tugas_rayhanRP t ON p.tugas_id = t.tugas_id
         LEFT JOIN mata_pelajaran_rayhanRP mp ON t.matpel_id = mp.matpel_id
         LEFT JOIN grup_rayhanRP g ON t.grup_id = g.grup_id
         LEFT JOIN penilaian_rayhanRP pn ON pn.pengumpulan_id = p.pengumpulan_id
         WHERE p.akun_id = ? AND pn.penilaian_id IS NULL
         ORDER BY p.waktu_kumpul DESC
         LIMIT 20',
        'i',
        $akunId
    ));
}

/**
 * Format daftar nilai dan tugas belum dinilai menjadi teks Telegram.
 */
function formatNilaiSiswa(array $nilaiList, array $belumDinilaiList = []): string
{
    if (empty($nilaiList) && empty($belumDinilaiList)) {
        return "📊 *Nilai Tugas*\n\n_(Belum ada tugas yang dikumpulkan)_";
    }

    $pesan = "📊 *Nilai Tugas*\n";
    $pesan .= str_repeat('─', 24) . "\n\n";

    // ─── SUDAH DINILAI ───
    if (!empty($nilaiList)) {
        $pesan .= "✅ *SUDAH DINILAI* (" . count($nilaiList) . ")\n";
        $pesan .= str_repeat('─', 16) . "\n\n";

        foreach ($nilaiList as $row) {
            $persen = (float) ($row['persentase'] ?? 0);
            $emoji  = match (true) {
                $persen >= 90 => '🏆',
                $persen >= 80 => '⭐',
                $persen >= 70 => '✅',
                $persen >= 60 => '⚠️',
                default       => '❌',
            };

            $statusLabel = match ($row['status_lulus'] ?? '') {
                'lulus'       => 'Lulus',
                'tidak_lulus' => 'Tidak Lulus',
                'revisi'      => 'Revisi',
                default       => '-',
            };

            $nilaiStr = is_numeric($row['nilai'])
                ? (floor((float)$row['nilai']) == (float)$row['nilai']
                    ? (string)(int)$row['nilai']
                    : number_format((float)$row['nilai'], 1))
                : '-';

            $pesan .= "{$emoji} *{$row['judul']}*\n";
            $pesan .= "   📚 {$row['matpel']}";
            if (!empty($row['nama_grup'])) {
                $pesan .= " | 🎓 {$row['nama_grup']}";
            }
            $pesan .= "\n";
            $pesan .= "   💯 *{$nilaiStr}/{$row['poin_maksimal']}* ({$persen}%) — {$statusLabel}\n";

            if (!empty($row['catatan_guru'])) {
                $pesan .= "   💬 _{$row['catatan_guru']}_\n";
            }

            $pesan .= "\n";
        }

        // Rata-rata nilai
        $totalNilai  = array_sum(array_column($nilaiList, 'nilai'));
        $rataRata    = count($nilaiList) > 0 ? round($totalNilai / count($nilaiList), 1) : 0;
        $pesan      .= str_repeat('─', 24) . "\n";
        $pesan      .= "📈 Rata-rata: *{$rataRata}*\n\n";
    }

    // ─── BELUM DINILAI ───
    if (!empty($belumDinilaiList)) {
        $pesan .= "⏳ *BELUM DINILAI* (" . count($belumDinilaiList) . ")\n";
        $pesan .= str_repeat('─', 16) . "\n\n";

        foreach ($belumDinilaiList as $row) {
            $tglKumpul = date('d/m H:i', strtotime((string) $row['waktu_kumpul']));
            $pesan .= "📝 *{$row['judul']}*\n";
            $pesan .= "   📚 {$row['matpel']}";
            if (!empty($row['nama_grup'])) {
                $pesan .= " | 🎓 {$row['nama_grup']}";
            }
            $pesan .= "\n";
            $pesan .= "   ⏰ Dikumpulkan: {$tglKumpul}\n";
            $pesan .= "   💯 Nilai Max: {$row['poin_maksimal']}\n\n";
        }
    }

    return $pesan;
}


// ============================================================
// DATA: GURU – KELAS & MAPEL
// ============================================================

/**
 * Ambil mata pelajaran yang diajar guru.
 */

/**
 * Ambil kelas yang diajar guru untuk satu mapel tertentu.
 */
function getKelasGuruByMatpel(int $guruId, int $matpelId): array
{
    return sirey_fetchAll(sirey_query(
        'SELECT DISTINCT g.grup_id, g.nama_grup
         FROM guru_mengajar_rayhanRP gm
         INNER JOIN grup_rayhanRP g ON gm.grup_id = g.grup_id
         WHERE gm.akun_id = ? AND gm.matpel_id = ? AND gm.aktif = 1 AND g.aktif = 1
         ORDER BY g.nama_grup ASC',
        'ii',
        $guruId,
        $matpelId
    ));
}

/**
 * Ambil semua kelas yang diajar guru.
 */
function getGrupAjarGuru(int $guruId): array
{
    return sirey_fetchAll(sirey_query(
        'SELECT DISTINCT g.grup_id, g.nama_grup
         FROM guru_mengajar_rayhanRP gm
         INNER JOIN grup_rayhanRP g ON gm.grup_id = g.grup_id
         WHERE gm.akun_id = ? AND gm.aktif = 1 AND g.aktif = 1
         ORDER BY g.nama_grup ASC',
        'i',
        $guruId
    ));
}

/**
 * Ambil kelas yang diwalikan guru (wali kelas).
 */
function getKelasWaliKelas(int $guruId): array
{
    return sirey_fetchAll(sirey_query(
        'SELECT g.grup_id, g.nama_grup, g.jurusan,
                COUNT(DISTINCT ga.akun_id) AS jml_siswa,
                COUNT(DISTINCT t.tugas_id) AS jml_tugas_dibuat,
                COUNT(DISTINCT p.pengumpulan_id) AS jml_kumpul
         FROM grup_rayhanRP g
         LEFT JOIN grup_anggota_rayhanRP ga
                ON g.grup_id = ga.grup_id AND ga.aktif = 1
                AND ga.akun_id IN (SELECT akun_id FROM akun_rayhanRP WHERE role = "siswa")
         LEFT JOIN tugas_rayhanRP t ON t.grup_id = g.grup_id AND t.status = "active"
         LEFT JOIN pengumpulan_rayhanRP p ON p.tugas_id = t.tugas_id
         WHERE g.wali_kelas_id = ? AND g.aktif = 1
         GROUP BY g.grup_id, g.nama_grup, g.jurusan
         ORDER BY g.nama_grup ASC',
        'i',
        $guruId
    ));
}

/**
 * Ambil tugas aktif guru untuk fitur analisis.
 */
function getTugasAnalisisForGuru(int $guruId): array
{
    return sirey_fetchAll(sirey_query(
        'SELECT t.tugas_id, t.judul, t.tenggat, mp.nama AS matpel, g.nama_grup,
                COALESCE(wk.nama_lengkap, "-") AS wali_kelas
         FROM tugas_rayhanRP t
         LEFT JOIN mata_pelajaran_rayhanRP mp ON t.matpel_id = mp.matpel_id
         LEFT JOIN grup_rayhanRP g ON t.grup_id = g.grup_id
         LEFT JOIN akun_rayhanRP wk ON g.wali_kelas_id = wk.akun_id
         WHERE t.pembuat_id = ? AND t.status = "active"
         ORDER BY t.tenggat DESC
         LIMIT 20',
        'i',
        $guruId
    ));
}

/**
 * Ambil detail analisis satu tugas: siapa sudah dan belum mengumpulkan.
 */
function getTugasAnalisisDetail(int $tugasId): array
{
    $tugas = sirey_fetch(sirey_query(
        'SELECT t.tugas_id, t.judul, g.grup_id, g.nama_grup,
                mp.nama AS matpel, COALESCE(wk.nama_lengkap, "-") AS wali_kelas
         FROM tugas_rayhanRP t
         LEFT JOIN grup_rayhanRP g ON t.grup_id = g.grup_id
         LEFT JOIN mata_pelajaran_rayhanRP mp ON t.matpel_id = mp.matpel_id
         LEFT JOIN akun_rayhanRP wk ON g.wali_kelas_id = wk.akun_id
         WHERE t.tugas_id = ?',
        'i',
        $tugasId
    ));

    if (!$tugas) return [];

    $semuaSiswa = sirey_fetchAll(sirey_query(
        'SELECT DISTINCT a.akun_id, a.nama_lengkap, a.nis_nip
         FROM akun_rayhanRP a
         INNER JOIN grup_anggota_rayhanRP ga ON a.akun_id = ga.akun_id
         WHERE ga.grup_id = ? AND ga.aktif = 1 AND a.role = "siswa" AND a.aktif = 1
         ORDER BY a.nama_lengkap ASC',
        'i',
        $tugas['grup_id']
    ));

    $sudahKumpul = sirey_fetchAll(sirey_query(
        'SELECT DISTINCT p.akun_id, a.nama_lengkap, a.nis_nip, p.waktu_kumpul
         FROM pengumpulan_rayhanRP p
         INNER JOIN akun_rayhanRP a ON p.akun_id = a.akun_id
         WHERE p.tugas_id = ? AND a.aktif = 1
         ORDER BY a.nama_lengkap ASC',
        'i',
        $tugasId
    ));

    $sudahIds    = array_column($sudahKumpul, 'akun_id');
    $belumKumpul = array_values(array_filter($semuaSiswa, fn($s) => !in_array($s['akun_id'], $sudahIds, true)));

    return [
        'tugas'       => $tugas,
        'all_siswa'   => $semuaSiswa,
        'sudah_kumpul' => $sudahKumpul,
        'belum_kumpul' => $belumKumpul,
    ];
}

/**
 * Ambil tugas yang punya pengumpulan dan belum semua dinilai (untuk guru).
 */
function getTugasUntukNilai(int $guruId): array
{
    return sirey_fetchAll(sirey_query(
        'SELECT t.tugas_id, t.judul, t.tenggat, t.poin_maksimal,
                mp.nama AS matpel, g.nama_grup,
                (SELECT COUNT(*) FROM pengumpulan_rayhanRP p
                 WHERE p.tugas_id = t.tugas_id) AS jml_pengumpulan,
                (SELECT COUNT(*) FROM pengumpulan_rayhanRP p
                 INNER JOIN penilaian_rayhanRP pn ON pn.pengumpulan_id = p.pengumpulan_id
                 WHERE p.tugas_id = t.tugas_id AND pn.nilai IS NOT NULL) AS jml_sudah_dinilai
         FROM tugas_rayhanRP t
         LEFT JOIN mata_pelajaran_rayhanRP mp ON t.matpel_id = mp.matpel_id
         LEFT JOIN grup_rayhanRP g ON t.grup_id = g.grup_id
         WHERE t.pembuat_id = ? AND t.status = "active"
           AND EXISTS (SELECT 1 FROM pengumpulan_rayhanRP p WHERE p.tugas_id = t.tugas_id)
         ORDER BY t.tenggat ASC
         LIMIT 20',
        'i',
        $guruId
    ));
}

/**
 * Ambil daftar pengumpulan satu tugas beserta status penilaiannya.
 */
function getPengumpulanTugas(int $tugasId): array
{
    return sirey_fetchAll(sirey_query(
        'SELECT p.pengumpulan_id, p.akun_id, a.nama_lengkap, a.nis_nip,
                p.teks_jawaban, p.file_path, p.file_nama_asli, p.status, p.waktu_kumpul,
                pn.penilaian_id, pn.nilai, pn.status_lulus, pn.catatan_guru
         FROM pengumpulan_rayhanRP p
         INNER JOIN akun_rayhanRP a ON p.akun_id = a.akun_id
         LEFT JOIN penilaian_rayhanRP pn ON pn.pengumpulan_id = p.pengumpulan_id
         WHERE p.tugas_id = ?
         ORDER BY (pn.nilai IS NOT NULL) ASC, a.nama_lengkap ASC',
        'i',
        $tugasId
    ));
}

/**
 * Ambil detail satu pengumpulan.
 */
function getPengumpulanDetail(int $pengumpulanId): ?array
{
    return sirey_fetch(sirey_query(
        'SELECT p.pengumpulan_id, p.akun_id, a.nama_lengkap, a.nis_nip,
                p.tugas_id, t.judul, t.poin_maksimal, mp.nama AS matpel, g.nama_grup,
                p.teks_jawaban, p.file_path, p.file_nama_asli, p.status, p.waktu_kumpul,
                pn.penilaian_id, pn.nilai, pn.status_lulus, pn.catatan_guru
         FROM pengumpulan_rayhanRP p
         INNER JOIN tugas_rayhanRP t ON p.tugas_id = t.tugas_id
         INNER JOIN akun_rayhanRP a ON p.akun_id = a.akun_id
         LEFT JOIN mata_pelajaran_rayhanRP mp ON t.matpel_id = mp.matpel_id
         LEFT JOIN grup_rayhanRP g ON t.grup_id = g.grup_id
         LEFT JOIN penilaian_rayhanRP pn ON pn.pengumpulan_id = p.pengumpulan_id
         WHERE p.pengumpulan_id = ?',
        'i',
        $pengumpulanId
    ));
}

/**
 * Simpan atau perbarui penilaian dari guru.
 * Mengembalikan array ['success' => bool, 'message' => string].
 */
function savePenilaian(
    int     $pengumpulanId,
    int     $guruId,
    float   $nilai,
    ?string $catatan     = null,
    string  $statusLulus = 'lulus'
): array {
    $detail = getPengumpulanDetail($pengumpulanId);
    if (!$detail) {
        return ['success' => false, 'message' => 'Pengumpulan tidak ditemukan'];
    }

    $poinMax = (float) $detail['poin_maksimal'];
    $nilai   = max(0.0, min($nilai, $poinMax));

    $ada = sirey_fetch(sirey_query(
        'SELECT penilaian_id FROM penilaian_rayhanRP WHERE pengumpulan_id = ? LIMIT 1',
        'i',
        $pengumpulanId
    ));

    if ($ada) {
        sirey_execute(
            'UPDATE penilaian_rayhanRP
             SET nilai = ?, status_lulus = ?, catatan_guru = ?, dinilai_oleh = ?, dinilai_pada = NOW()
             WHERE penilaian_id = ?',
            'dssii',
            $nilai,
            $statusLulus,
            $catatan ?? '',
            $guruId,
            (int) $ada['penilaian_id']
        );
    } else {
        sirey_execute(
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
    }

    // Kirim notifikasi ke siswa
    $userTg = sirey_fetch(sirey_query(
        'SELECT at.telegram_chat_id
         FROM akun_telegram_rayhanRP at
         INNER JOIN pengumpulan_rayhanRP p ON at.akun_id = p.akun_id
         WHERE p.pengumpulan_id = ?',
        'i',
        $pengumpulanId
    ));

    if ($userTg && !empty($userTg['telegram_chat_id'])) {
        $emojiStatus = match ($statusLulus) {
            'lulus'       => '✅ Lulus',
            'revisi'      => '✏️ Revisi',
            'tidak_lulus' => '❌ Tidak Lulus',
            default       => $statusLulus,
        };

        $nilaiStr = floor($nilai) == $nilai ? (string)(int)$nilai : number_format($nilai, 1);
        $pesan    = "🎉 *Tugas Anda Sudah Dinilai!*\n\n"
                  . "*{$detail['judul']}*\n"
                  . formatNilaiBintang($nilai, (int) $poinMax) . "\n"
                  . "Status: {$emojiStatus}";

        if ($catatan) {
            $pesan .= "\n\n💬 _Catatan guru:_\n{$catatan}";
        }

        if ($statusLulus === 'revisi') {
            $pesan .= "\n\n📝 *Mohon perbaiki dan kirim ulang jawaban Anda.*";
        }

        sendMsg((int) $userTg['telegram_chat_id'], $pesan);
    }

    return ['success' => true, 'message' => 'Penilaian berhasil disimpan'];
}


// ============================================================
// DOWNLOAD FILE DARI TELEGRAM
// ============================================================

/**
 * Unduh file yang dikirim pengguna dari server Telegram dan simpan ke lokal.
 * Mengembalikan relative path file atau null jika gagal.
 */
function downloadTelegramFile(string $fileId, string $fileType, int $akunId): ?string
{
    $token = getBotToken();
    if (!$token) {
        error_log("[DOWNLOAD] Bot token tidak ditemukan");
        return null;
    }

    // Langkah 1: Dapatkan path file dari Telegram
    error_log("[DOWNLOAD] Meminta info file dari Telegram (file_id: {$fileId})");
    $infoResponse = telegramRequest('getFile', ['file_id' => $fileId]);
    if (!$infoResponse || empty($infoResponse['result']['file_path'])) {
        error_log("[DOWNLOAD] Gagal getFile untuk file_id: {$fileId}");
        error_log("[DOWNLOAD] Response: " . json_encode($infoResponse));
        return null;
    }

    $telegramFilePath = $infoResponse['result']['file_path'];
    $fileUrl          = "https://api.telegram.org/file/bot{$token}/{$telegramFilePath}";
    error_log("[DOWNLOAD] Telegram file path: {$telegramFilePath}");

    // Langkah 2: Unduh konten file
    error_log("[DOWNLOAD] Mendownload file dari: {$fileUrl}");
    $ch = curl_init($fileUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_BINARYTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $fileContent = curl_exec($ch);
    $httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError   = curl_error($ch);
    curl_close($ch);

    if ($curlError !== '') {
        error_log("[DOWNLOAD] cURL error: {$curlError}");
        return null;
    }

    if ($httpCode !== 200 || empty($fileContent)) {
        error_log("[DOWNLOAD] HTTP {$httpCode}, file kosong atau error");
        return null;
    }

    error_log("[DOWNLOAD] File berhasil didownload, size: " . strlen($fileContent) . " bytes");

    // Langkah 3: Tentukan ekstensi file
    $ext = pathinfo(basename($telegramFilePath), PATHINFO_EXTENSION);
    if (empty($ext)) {
        // Deteksi dari magic bytes
        $ext = match (true) {
            str_starts_with($fileContent, '%PDF')             => 'pdf',
            str_starts_with($fileContent, "\xFF\xD8\xFF")     => 'jpg',
            str_starts_with($fileContent, "\x89PNG\r\n\x1A\n") => 'png',
            str_starts_with($fileContent, 'GIF')              => 'gif',
            str_starts_with($fileContent, 'PK')               => 'zip',
            default                                            => 'bin',
        };
        error_log("[DOWNLOAD] Extension terdeteksi dari magic bytes: {$ext}");
    } else {
        error_log("[DOWNLOAD] Extension dari filename: {$ext}");
    }

    // Langkah 4: Simpan ke direktori uploads
    $year    = date('Y');
    $month   = date('m');
    $dir     = __DIR__ . "/../uploads/submissions/{$year}/{$month}";

    if (!is_dir($dir)) {
        error_log("[DOWNLOAD] Membuat direktori: {$dir}");
        mkdir($dir, 0755, true);
    }

    $namaFile    = "sub_{$akunId}_" . time() . ".{$ext}";
    $fullPath    = "{$dir}/{$namaFile}";
    $relativePath = "uploads/submissions/{$year}/{$month}/{$namaFile}";

    if (file_put_contents($fullPath, $fileContent) === false) {
        error_log("[DOWNLOAD] Gagal menyimpan file ke: {$fullPath}");
        return null;
    }

    error_log("[DOWNLOAD] File tersimpan: {$relativePath}");
    error_log("[DOWNLOAD] Full path: {$fullPath}");
    return $relativePath;
}


// ============================================================
// FORMATTER UNTUK KONFIRMASI PENGUMPULAN
// ============================================================

/**
 * Format pesan preview pengumpulan sebelum dikirim (teks jawaban).
 */
function formatPreviewPengumpulan(array $tugas, ?string $teksJawaban, ?string $linkJawaban): string
{
    $tgl   = date('d/m/Y H:i', strtotime((string) $tugas['tenggat']));
    $sisa  = sisaWaktu((string) $tugas['tenggat']);
    $pesan = "✅ *Konfirmasi Pengumpulan*\n\n"
           . "📝 *{$tugas['judul']}*\n"
           . "📚 {$tugas['matpel']} | 🎓 {$tugas['nama_grup']}\n"
           . "📅 Deadline: {$tgl} _{$sisa}_\n\n"
           . "━━━━━━━━━━━━━━\n";

    if ($teksJawaban) {
        $preview = potongTeks($teksJawaban, 150);
        $pesan  .= "📄 *Jawaban:*\n_{$preview}_\n";
    }

    $pesan .= "━━━━━━━━━━━━━━\n\n";
    $pesan .= "Kirim pengumpulan ini?";

    return $pesan;
}

/**
 * Format pesan konfirmasi penilaian sebelum disimpan guru.
 */
function formatKonfirmasiNilai(array $detail, float $nilaiInput, ?string $catatan): string
{
    $nilaiStr = floor($nilaiInput) == $nilaiInput ? (string)(int)$nilaiInput : number_format($nilaiInput, 1);

    $pesan  = "✅ *Konfirmasi Penilaian*\n\n";
    $pesan .= "👤 *{$detail['nama_lengkap']}*\n";
    $pesan .= "📝 {$detail['judul']}\n\n";
    $pesan .= "💯 Nilai: *{$nilaiStr} / {$detail['poin_maksimal']}*\n";
    $pesan .= formatNilaiBintang($nilaiInput, (int) $detail['poin_maksimal']) . "\n";

    if ($catatan) {
        $pesan .= "💬 Catatan: _{$catatan}_\n";
    }

    $pesan .= "\nSimpan penilaian ini?";

    return $pesan;
}

/**
 * Format pesan form input nilai untuk guru.
 */
function formatFormNilai(array $detail): string
{
    $pesan  = "✏️ *Input Nilai*\n\n";
    $pesan .= "👤 *{$detail['nama_lengkap']}*\n";
    $pesan .= "📝 {$detail['judul']}\n";
    $pesan .= "💯 Range: 0 – {$detail['poin_maksimal']}\n\n";

    if ($detail['teks_jawaban']) {
        $preview = potongTeks((string) $detail['teks_jawaban'], 200);
        $pesan  .= "📄 *Jawaban:*\n_{$preview}_\n\n";
    }

    if ($detail['file_nama_asli']) {
        $pesan .= "📎 *File:* {$detail['file_nama_asli']} _(lihat di pesan atas)_\n\n";
    }

    if ($detail['nilai'] !== null) {
        $pesan .= "⚠️ _Nilai sebelumnya: {$detail['nilai']}_\n\n";
    }

    $pesan .= "Kirim angka nilai (contoh: 85 atau 90.5):";

    return $pesan;
}


// ============================================================
// NOTIFIKASI REVISI (dipanggil dari penilaian.php)
// ============================================================

/**
 * Kirim notifikasi ke siswa bahwa revisinya disetujui.
 */
function notifySiswaRevisiApproved(int $pengumpulanId, int $nomorVersi, int $guruId, string $catatan = ''): bool
{
    $data = sirey_fetch(sirey_query(
        'SELECT t.judul, at.telegram_chat_id, guru.nama_lengkap AS guru_nama
         FROM pengumpulan_rayhanRP p
         JOIN tugas_rayhanRP t ON p.tugas_id = t.tugas_id
         JOIN akun_rayhanRP a ON p.akun_id = a.akun_id
         JOIN akun_telegram_rayhanRP at ON at.akun_id = a.akun_id
         LEFT JOIN akun_rayhanRP guru ON guru.akun_id = ?
         WHERE p.pengumpulan_id = ?
         LIMIT 1',
        'ii',
        $guruId,
        $pengumpulanId
    ));

    if (!$data || empty($data['telegram_chat_id'])) return false;

    $pesan = "✅ *Revisi Disetujui!*\n\n"
           . "Tugas: *{$data['judul']}* (v{$nomorVersi})\n"
           . "Oleh: {$data['guru_nama']}"
           . ($catatan ? "\n\n💬 _{$catatan}_" : '');

    return sendMsg((int) $data['telegram_chat_id'], $pesan);
}

/**
 * Kirim notifikasi ke siswa bahwa revisinya ditolak.
 */
function notifySiswaRevisiRejected(int $pengumpulanId, int $nomorVersi, int $guruId, string $catatan): bool
{
    $data = sirey_fetch(sirey_query(
        'SELECT t.judul, at.telegram_chat_id, guru.nama_lengkap AS guru_nama
         FROM pengumpulan_rayhanRP p
         JOIN tugas_rayhanRP t ON p.tugas_id = t.tugas_id
         JOIN akun_rayhanRP a ON p.akun_id = a.akun_id
         JOIN akun_telegram_rayhanRP at ON at.akun_id = a.akun_id
         LEFT JOIN akun_rayhanRP guru ON guru.akun_id = ?
         WHERE p.pengumpulan_id = ?
         LIMIT 1',
        'ii',
        $guruId,
        $pengumpulanId
    ));

    if (!$data || empty($data['telegram_chat_id'])) return false;

    $pesan = "❌ *Revisi Ditolak*\n\n"
           . "Tugas: *{$data['judul']}* (v{$nomorVersi})\n"
           . "Oleh: {$data['guru_nama']}\n\n"
           . "💬 Alasan:\n_{$catatan}_\n\n"
           . "Silakan perbaiki dan kirim ulang. 📤";

    return sendMsg((int) $data['telegram_chat_id'], $pesan);
}