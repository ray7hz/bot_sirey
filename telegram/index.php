<?php
declare(strict_types=1);

require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/handler/command.php';
require_once __DIR__ . '/handler/state.php';
require_once __DIR__ . '/handler/menu.php';

$input = json_decode(file_get_contents('php://input'), true);

$msg      = $input['message'] ?? $input['callback_query']['message'] ?? null;
$callback = $input['callback_query'] ?? null;

if (!$msg) exit;

$chat   = (int)($msg['chat']['id']);
$text   = trim($msg['text'] ?? ($callback ? $callback['data'] : ''));
$callId = $callback['id'] ?? null;

if ($callId) answerCallback($callId);

// Ambil state & user
$state = getState($chat) ?? ['step' => 'init'];
$step  = $state['step'] ?? 'init';

$user = null;
if (!empty($state['user_cache']) && is_array($state['user_cache'])) {
    $user   = $state['user_cache'];
    $userDb = getRegisteredUser($chat);
    if ($userDb) $user = array_merge($userDb, $state['user_cache']);
} else {
    $user = getRegisteredUser($chat);
    if ($user && $step !== 'ask_nis' && $step !== 'ask_password') {
        $state['user_cache'] = [
            'akun_id'      => $user['akun_id'],
            'nama_lengkap' => $user['nama_lengkap'],
            'role'         => $user['role'],
            'nis_nip'      => $user['nis_nip'],
        ];
        setState($chat, $state);
    }
}

// ── HANDLE FILE/FOTO UPLOAD ──────────────────────────────────────────────────
// PENTING: Kita hanya simpan file_id ke state — TIDAK download file di sini.
// Download dilakukan nanti saat user tekan "✅ Kirim" di state kumpul_konfirmasi_file.
// Ini mencegah timeout webhook Telegram (download bisa makan waktu >5 detik
// sehingga Telegram retry dan tombol konfirmasi muncul terlambat).
if ($step === 'kumpul_input_file' && $user) {
    $fileId   = null;
    $fileType = null;
    $fileName = null;

    if (!empty($msg['document'])) {
        $doc      = $msg['document'];
        $fileId   = $doc['file_id'];
        $fileType = 'document';
        $fileName = $doc['file_name'] ?? ('dokumen_' . time());
    } elseif (!empty($msg['photo']) && is_array($msg['photo'])) {
        // Foto datang sebagai array ukuran berbeda — ambil yang terbesar (index terakhir)
        $foto     = $msg['photo'][count($msg['photo']) - 1];
        $fileId   = $foto['file_id'];
        $fileType = 'photo';
        $fileName = 'foto_' . date('Ymd_His') . '.jpg';
    }

    if ($fileId) {
        if (empty($state['tugas_id'])) {
            sendMsg($chat, "❌ Info tugas tidak ditemukan. Silakan pilih tugas kembali.");
            exit;
        }

        $tugas = $state['tugas'] ?? [];

        // Simpan file_id — konfirmasi muncul instan tanpa tunggu download
        setState($chat, [
            'step'       => 'kumpul_konfirmasi_file',
            'tugas_id'   => (int)$state['tugas_id'],
            'tugas'      => $tugas,
            'file_id'    => $fileId,
            'file_type'  => $fileType,
            'file_nama'  => $fileName,
            'tipe_kumpul' => $state['tipe_kumpul'] ?? 'baru',
            'user_cache' => [
                'akun_id'      => $user['akun_id'],
                'nama_lengkap' => $user['nama_lengkap'],
                'role'         => $user['role'],
                'nis_nip'      => $user['nis_nip'],
            ],
        ]);

        $pesan = "✅ *Konfirmasi Pengumpulan File*\n\n"
               . "📝 Tugas: *{$tugas['judul']}*\n"
               . "📎 File: {$fileName}\n"
               . "⏰ Waktu: " . date('d/m/Y H:i') . "\n\n"
               . "Apakah ingin mengumpulkan file ini?";

        sendMsg($chat, $pesan, [['✅ Kirim', '❌ Batal']]);
        exit;
    }

    // Ada teks (misal tombol Kembali) — teruskan ke handler bawah
    // Tidak ada teks dan tidak ada file → beri peringatan
    if (empty($text)) {
        sendMsg($chat, "❌ Silakan kirim file atau foto.");
        exit;
    }
}

// Jalankan handler secara berurutan
if (handleCommand($text, $chat, $user)) exit;
if (handleState($step, $text, $chat, $state, $user)) exit;
if ($user && handleMenu($text, $chat, $user)) exit;

// Belum login → minta login
if (!$user) {
    setState($chat, ['step' => 'ask_nis']);
    sendMsg($chat, "👋 Selamat datang di *Bot SiRey*!\n\nSilakan login terlebih dahulu.\n\nMasukkan *NIS/NIP* Anda:");
}