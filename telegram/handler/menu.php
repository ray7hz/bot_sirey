<?php

function handleMenu(string $text, int $chat, array $user, mysqli $db): bool {

    // ================= JADWAL =================
    if (str_contains($text, 'Jadwal')) {

        $jadwal = fetchUserSchedule($db, $user['akun_id']);

        if (!$jadwal) {
            sendTelegramMessage($chat, "Tidak ada jadwal.");
        } else {
            $msg = "Jadwal:\n\n";
            foreach ($jadwal as $j) {
                $msg .= "- {$j['judul']} ({$j['hari']})\n";
            }
            sendTelegramMessage($chat, $msg);
        }

        return true;
    }

    // ================= PENGUMUMAN =================
    if (str_contains($text, 'Pengumuman')) {

        $data = fetchRecentAnnouncements();

        if (!$data) {
            sendTelegramMessage($chat, "Tidak ada pengumuman.");
        } else {
            $msg = "Pengumuman:\n\n";
            foreach ($data as $p) {
                $msg .= "- {$p['judul']}\n";
            }
            sendTelegramMessage($chat, $msg);
        }

        return true;
    }

    // ================= PENGATURAN =================
    if (str_contains($text, 'Pengaturan')) {

        sendTelegramMessage($chat, "Menu Pengaturan:", buildSettingsKeyboard());
        setState($chat, ['step'=>'menu_settings']);

        return true;
    }

    return false;
}