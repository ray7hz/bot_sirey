<?php

function handleState(string $step, string $text, int $chat, array $state, mysqli $db): bool {

    switch ($step) {

        case 'ask_nis':
            if ($text === '') {
                sendTelegramMessage($chat, "Masukkan NIS/NIP");
            } else {
                setState($chat, [
                    'step' => 'ask_password',
                    'nis'  => $text
                ]);
                sendTelegramMessage($chat, "Masukkan password:");
            }
            return true;

        case 'ask_password':

            $login = registerUserToTelegram(
                $db,
                $chat,
                $state['nis'],
                $text
            );

            if (!empty($login['success'])) {

                setState($chat, ['step'=>'menu']);

                sendTelegramMessage(
                    $chat,
                    "Login berhasil\n\n" . getMainMenuText(),
                    buildKeyboardForRole($login['user']['role'])
                );

            } else {

                setState($chat, ['step'=>'ask_nis']);
                sendTelegramMessage($chat, "Login gagal. Masukkan NIS/NIP lagi.");
            }

            return true;
    }

    return false;
}