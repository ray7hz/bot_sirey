<?php
declare(strict_types=1);

// ================================================================
// index.php – Entry point webhook
// Semua update dari Telegram masuk ke sini.
// ================================================================

require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/handler/command.php';
require_once __DIR__ . '/handler/state.php';
require_once __DIR__ . '/handler/menu.php';

// Pastikan skema DB sudah lengkap
ensureSireySchema();

// ----------------------------------------------------------------
// 1. Baca dan parse update dari Telegram
// ----------------------------------------------------------------
$rawInput = file_get_contents('php://input');
$update   = json_decode($rawInput, true);

if (!is_array($update)) {
    // Bukan request valid dari Telegram
    exit;
}

// ----------------------------------------------------------------
// 2. Ekstrak data pesan dan callback_query
// ----------------------------------------------------------------
$msg      = null;
$callback = null;

if (!empty($update['callback_query'])) {
    $callback = $update['callback_query'];
    $msg      = $callback['message'] ?? null;
} elseif (!empty($update['message'])) {
    $msg = $update['message'];
}

// Tidak ada pesan sama sekali → abaikan
if (!is_array($msg)) {
    exit;
}

// ----------------------------------------------------------------
// 3. Ekstrak info dasar
// ----------------------------------------------------------------
$chatId    = (int) ($msg['chat']['id'] ?? 0);
$messageId = (int) ($msg['message_id'] ?? 0);
$callId    = $callback['id'] ?? null;

// Teks pesan: dari pesan biasa atau dari tombol callback
$text = '';
if (!empty($callback)) {
    $text = trim((string) ($callback['data'] ?? ''));
} elseif (!empty($msg['text'])) {
    $text = trim((string) $msg['text']);
}

if ($chatId <= 0) {
    exit;
}

// Jawab callback_query segera agar tombol tidak loading lama
if ($callId !== null) {
    answerCallback($callId);
}

// ----------------------------------------------------------------
// 4. Load state percakapan dan data user
// ----------------------------------------------------------------
$state = getState($chatId) ?? ['step' => 'init'];
$step  = (string) ($state['step'] ?? 'init');
$user  = null;

if (!empty($state['user_cache']) && is_array($state['user_cache']) && !empty($state['session_token'])) {
    // User sudah login: validasi session masih aktif di DB
    $userDb = getRegisteredUser($chatId, (string) $state['session_token']);

    if ($userDb !== null) {
        // Merge data DB dengan cache (cache bisa punya data extra seperti tugas dll)
        $user = array_merge($userDb, $state['user_cache']);
    } else {
        // Session tidak valid (mungkin login di tempat lain)
        if (!in_array($step, ['ask_nis', 'ask_password'], true)) {
            setState($chatId, ['step' => 'ask_nis']);
            sendMsgRemoveKeyboard(
                $chatId,
                "⚠️ Sesi Anda sudah berakhir karena akun ini login di perangkat lain.\n\n"
                . "Silakan login kembali. Masukkan *NIS/NIP* Anda:"
            );
            exit;
        }
    }
} else {
    // Tidak ada cache: coba ambil dari DB tanpa token (saat awal)
    $user = getRegisteredUser($chatId);

    if ($user !== null && !in_array($step, ['ask_nis', 'ask_password'], true)) {
        // Simpan cache agar request berikutnya lebih cepat
        $state['user_cache'] = [
            'akun_id'      => $user['akun_id'],
            'nama_lengkap' => $user['nama_lengkap'],
            'role'         => $user['role'],
            'nis_nip'      => $user['nis_nip'],
        ];
        setState($chatId, $state);
    }
}

// ----------------------------------------------------------------
// 5. Handle upload file/foto (pengumpulan tugas)
//    Ini harus dicek sebelum handler lain agar tidak terlewat
// ----------------------------------------------------------------
if ($step === 'kumpul_input_file' && $user !== null) {
    $fileId   = null;
    $fileType = null;
    $fileName = null;

    if (!empty($msg['document'])) {
        $doc      = $msg['document'];
        $fileId   = (string) $doc['file_id'];
        $fileType = 'document';
        $fileName = (string) ($doc['file_name'] ?? 'dokumen_' . time());

    } elseif (!empty($msg['photo']) && is_array($msg['photo'])) {
        // Foto datang sebagai array: ambil resolusi terbesar (index terakhir)
        $foto     = $msg['photo'][count($msg['photo']) - 1];
        $fileId   = (string) $foto['file_id'];
        $fileType = 'photo';
        $fileName = 'foto_' . date('Ymd_His') . '.jpg';
    }

    if ($fileId !== null) {
        if (empty($state['tugas_id'])) {
            sendMsg($chatId, "❌ Info tugas tidak ditemukan. Silakan pilih tugas kembali dari menu.");
            exit;
        }

        // Simpan file_id ke state, unduh nanti saat konfirmasi
        // (menghindari timeout webhook karena download bisa lama)
        setState($chatId, [
            'step'        => 'kumpul_konfirmasi_file',
            'tugas_id'    => (int) $state['tugas_id'],
            'tugas'       => $state['tugas'] ?? [],
            'file_id'     => $fileId,
            'file_type'   => $fileType,
            'file_nama'   => $fileName,
            'tipe_kumpul' => $state['tipe_kumpul'] ?? 'baru',
            'user_cache'  => [
                'akun_id'      => $user['akun_id'],
                'nama_lengkap' => $user['nama_lengkap'],
                'role'         => $user['role'],
                'nis_nip'      => $user['nis_nip'],
            ],
        ]);

        $tugas = $state['tugas'] ?? [];
        $pesan = "✅ *Konfirmasi Pengumpulan File*\n\n"
               . "📝 Tugas: *{$tugas['judul']}*\n"
               . "📎 File: {$fileName}\n"
               . "⏰ " . date('d/m/Y H:i') . "\n\n"
               . "Kirim file ini sebagai jawaban?";

        sendMsg($chatId, $pesan, [['✅ Kirim', '❌ Batal']]);
        exit;
    }

    // Ada teks (misalnya tombol Kembali) → teruskan ke handler di bawah
    if (empty($text)) {
        sendMsg($chatId, "❌ Silakan kirim file atau foto sebagai jawaban.");
        exit;
    }
}

// ----------------------------------------------------------------
// 6. Jalankan handler secara berurutan
//    Setiap handler mengembalikan true jika sudah menangani request
// ----------------------------------------------------------------

// Handler 1: Perintah slash (/start, /logout, /batal)
if (handleCommand($text, $chatId, $user)) {
    exit;
}

// Handler 2: State percakapan (alur login, buat tugas, kumpul tugas, dll)
if (handleState($step, $text, $chatId, $state, $user)) {
    exit;
}

// Handler 3: Menu utama (hanya jika sudah login)
if ($user !== null && handleMenu($text, $chatId, $user)) {
    exit;
}

// ----------------------------------------------------------------
// 7. Fallback: belum login atau pesan tidak dikenali
// ----------------------------------------------------------------
if ($user === null) {
    setState($chatId, ['step' => 'ask_nis']);
    sendMsgRemoveKeyboard(
        $chatId,
        "👋 Selamat datang di *SKADACI BOT*!\n\n"
        . "Silakan login untuk menggunakan bot ini.\n\n"
        . "Masukkan *NIS/NIP* Anda:"
    );
} else {
    // User sudah login tapi pesan tidak dikenali
    sendMsg(
        $chatId,
        "❓ Perintah tidak dikenali.\n\nGunakan tombol menu di bawah untuk navigasi.",
        mainKeyboard((string) $user['role'])
    );
}