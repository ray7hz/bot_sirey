<?php
declare(strict_types=1);

// ================================================================
// handler/command.php
// Menangani perintah slash: /start, /help, /logout, /batal, /info
// Mengembalikan true jika perintah sudah ditangani.
// ================================================================

function handleCommand(string $text, int $chatId, ?array $user): bool
{
    // ── /start ──────────────────────────────────────────────────
    if ($text === '/start' || $text === '/mulai') {

        if ($user !== null) {
            // Sudah login → langsung tampilkan menu utama
            $role = (string) $user['role'];
            $nama = (string) $user['nama_lengkap'];

            setState($chatId, [
                'step'        => 'menu',
                'session_token' => getState($chatId)['session_token'] ?? '',
                'user_cache'  => [
                    'akun_id'      => $user['akun_id'],
                    'nama_lengkap' => $nama,
                    'role'         => $role,
                    'nis_nip'      => $user['nis_nip'],
                ],
            ]);

            $labelRole = labelRole($role);
            $sapaan    = sapaanWaktu();

            sendMsg(
                $chatId,
                "{$sapaan}, *{$nama}*! 👋\n\n"
                . "Anda login sebagai _{$labelRole}_.\n"
                . "Silakan pilih menu di bawah ini:",
                mainKeyboard($role)
            );
        } else {
            // Belum login → mulai alur login
            setState($chatId, ['step' => 'ask_nis']);
            sendMsgRemoveKeyboard(
                $chatId,
                "👋 Selamat datang di *SKADACI BOT*!\n\n"
                . "🏫 _Sistem Informasi Sekolah berbasis Telegram_\n\n"
                . "Silakan login untuk melanjutkan.\n"
                . "Masukkan *NIS/NIP* Anda:"
            );
        }

        return true;
    }

    // ── /help ────────────────────────────────────────────────────
    if ($text === '/help' || $text === '/bantuan') {
        $pesan = "ℹ️ *Bantuan SKADACI BOT*\n\n"
               . "*Perintah yang tersedia:*\n"
               . "`/start` — Masuk ke bot / tampilkan menu\n"
               . "`/logout` — Keluar dari akun\n"
               . "`/batal` — Batalkan aksi saat ini\n"
               . "`/info` — Info akun yang sedang login\n"
               . "`/help` — Tampilkan pesan ini\n\n"
               . "*Navigasi:*\n"
               . "Gunakan tombol menu di bawah layar untuk navigasi.\n"
               . "Tombol *🔙 Kembali ke Menu* akan membawa Anda ke menu utama.\n\n"
               . "*Masalah?*\n"
               . "Hubungi administrator sekolah jika mengalami kendala.";

        if ($user !== null) {
            sendMsg($chatId, $pesan, mainKeyboard((string) $user['role']));
        } else {
            sendMsg($chatId, $pesan);
        }

        return true;
    }

    // ── /info ────────────────────────────────────────────────────
    if ($text === '/info' || $text === '/profil') {
        if ($user === null) {
            sendMsg($chatId, "❌ Anda belum login. Ketik /start untuk mulai.");
            return true;
        }

        $jamNotif  = getJamNotifikasi((int) $user['akun_id']);
        $labelRole = labelRole((string) $user['role']);

        $pesan = "👤 *Profil Akun*\n\n"
               . "Nama   : *{$user['nama_lengkap']}*\n"
               . "NIS/NIP: `{$user['nis_nip']}`\n"
               . "Role   : _{$labelRole}_\n\n"
               . "⏰ *Jam Notifikasi:*\n"
               . "📅 Jadwal : {$jamNotif['jam_jadwal']}\n"
               . "📝 Tugas  : {$jamNotif['jam_tugas']}";

        sendMsg($chatId, $pesan, mainKeyboard((string) $user['role']));
        return true;
    }

    // ── /logout ──────────────────────────────────────────────────
    if ($text === '/logout') {
        if ($user === null) {
            sendMsg($chatId, "❌ Anda belum login.");
            return true;
        }

        invalidateTelegramSession($chatId);
        setState($chatId, null);

        sendMsgRemoveKeyboard(
            $chatId,
            "✅ Anda berhasil *logout*.\n\n"
            . "Terima kasih telah menggunakan SKADACI BOT! 👋\n\n"
            . "Ketik /start untuk login kembali."
        );

        return true;
    }

    // ── /batal ───────────────────────────────────────────────────
    if ($text === '/batal') {
        if ($user === null) {
            setState($chatId, ['step' => 'ask_nis']);
            sendMsg($chatId, "↩️ Dibatalkan. Masukkan *NIS/NIP* untuk login:");
            return true;
        }

        setState($chatId, [
            'step'        => 'menu',
            'session_token' => getState($chatId)['session_token'] ?? '',
            'user_cache'  => [
                'akun_id'      => $user['akun_id'],
                'nama_lengkap' => $user['nama_lengkap'],
                'role'         => $user['role'],
                'nis_nip'      => $user['nis_nip'],
            ],
        ]);

        sendMsg($chatId, "↩️ Dibatalkan. Kembali ke menu utama.", mainKeyboard((string) $user['role']));
        return true;
    }

    return false;
}


// ================================================================
// Helper internal
// ================================================================

/**
 * Label tampilan role dalam Bahasa Indonesia.
 */
function labelRole(string $role): string
{
    return match ($role) {
        'admin'         => 'Administrator',
        'guru'          => 'Guru',
        'siswa'         => 'Siswa',
        'kurikulum'     => 'Staf Kurikulum',
        'kepala_sekolah' => 'Kepala Sekolah',
        default         => ucfirst($role),
    };
}

/**
 * Sapaan berdasarkan jam saat ini (WIB).
 */
function sapaanWaktu(): string
{
    $jam = (int) date('H');

    return match (true) {
        $jam >= 5  && $jam < 11 => '🌅 Selamat pagi',
        $jam >= 11 && $jam < 15 => '☀️ Selamat siang',
        $jam >= 15 && $jam < 18 => '🌤️ Selamat sore',
        default                 => '🌙 Selamat malam',
    };
}