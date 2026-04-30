<?php
declare(strict_types=1);

/**
 * Tangani perintah /start, /logout, /batal.
 * Return true jika sudah ditangani.
 */
function handleCommand(string $text, int $chat, ?array $user): bool {

    // /start — entry point utama
    if ($text === '/start' || $text === '/mulai') {

        if ($user) {
            // Sudah login → langsung ke menu, keyboard muncul otomatis
            setState($chat, ['step' => 'menu']);
            sendMsg(
                $chat,
                "👋 Halo, *{$user['nama_lengkap']}*!\n\nPilih menu di bawah ini:",
                mainKeyboard($user['role'])
            );
        } else {
            // Belum login → minta NIS/NIP
            setState($chat, ['step' => 'ask_nis']);
            sendMsg($chat, "👋 Selamat datang di *Bot SiRey*!\n\nMasukkan *NIS/NIP* Anda:");
        }

        return true;
    }

    // /logout — hanya jika sudah login
    if ($text === '/logout' && $user) {
        sirey_execute(
            'DELETE FROM akun_telegram_rayhanRP WHERE telegram_chat_id = ?',
            'i', $chat
        );
        setState($chat, null);
        sendMsgRemoveKeyboard($chat, "✅ Anda berhasil logout.\n\nKetik /start untuk login kembali.");
        return true;
    }

    // /batal — kembali ke menu
    if ($text === '/batal' && $user) {
        setState($chat, ['step' => 'menu']);
        sendMsg($chat, "↩️ Dibatalkan.", mainKeyboard($user['role']));
        return true;
    }

    return false;
}