<?php

function handleCommand(string $text, int $chat, ?array $user, mysqli $db): bool {

    if ($text === '/start') {

        if ($user) {
            setState($chat, ['step'=>'menu']);
            sendTelegramMessage(
                $chat,
                getMainMenuText(),
                buildKeyboardForRole($user['role'])
            );
        } else {
            setState($chat, ['step'=>'ask_nis']);
            sendTelegramMessage($chat, "Masukkan NIS/NIP:");
        }

        return true;
    }

    if ($text === '/logout') {
        logoutUserFromTelegram($db, $chat);
        setState($chat, null);
        sendTelegramMessage($chat, "Logout berhasil. Ketik /start");
        return true;
    }

    if ($text === '/batal') {
        setState($chat, ['step'=>'menu']);
        sendTelegramMessage($chat, "Dibatalkan");
        return true;
    }

    return false;
}