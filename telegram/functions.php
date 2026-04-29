<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

// ================= DB =================
function sirey_getDatabase(): mysqli {
    static $db = null;

    if ($db === null) {
        $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

        if ($db->connect_error) {
            throw new Exception("DB Error: " . $db->connect_error);
        }
    }

    return $db;
}


// ================= QUERY =================
function sirey_query(string $query, string $types = '', ...$params): mysqli_stmt {

    $db = sirey_getDatabase();

    $stmt = $db->prepare($query);

    if (!$stmt) {
        throw new Exception("Query Error: " . $db->error);
    }

    if ($types && $params) {
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        throw new Exception("Execute Error: " . $stmt->error);
    }

    return $stmt;
}

function sirey_fetchAll(mysqli_stmt $stmt): array {
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

function sirey_fetchOne(mysqli_stmt $stmt): ?array {
    $result = $stmt->get_result();
    return $result->fetch_assoc() ?: null;
}

function sirey_execute(string $query, string $types = '', ...$params): bool {
    $stmt = sirey_query($query, $types, ...$params);
    return !$stmt->error;
}


// ================= STATE =================
function getState(int $chat): ?array {

    $file = __DIR__ . "/state/{$chat}.json";

    if (!file_exists($file)) return null;

    return json_decode(file_get_contents($file), true);
}

function setState(int $chat, ?array $data): void {

    $dir  = __DIR__ . "/state";
    $file = $dir . "/{$chat}.json";

    if ($data === null) {
        if (file_exists($file)) unlink($file);
        return;
    }

    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        throw new Exception("Failed to create state directory: $dir");
    }

    file_put_contents($file, json_encode($data), LOCK_EX);
}


// ================= TELEGRAM =================
function sendTelegramMessage(int $chat, string $text, ?array $keyboard = null): void {

    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendMessage";

    $payload = [
        'chat_id' => $chat,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];

    if ($keyboard) {
        $payload['reply_markup'] = json_encode([
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ]);
    }

    $options = [
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-type: application/x-www-form-urlencoded",
            'content' => http_build_query($payload),
            'timeout' => 5
        ]
    ];

    $context = stream_context_create($options);
    $result  = file_get_contents($url, false, $context);

    if ($result === false) {
        throw new Exception("Failed to send Telegram message to chat $chat");
    }
}


// ================= AUTH =================
function getRegisteredUser($db, int $chat): ?array {

    $user = sirey_fetchOne(sirey_query(
        'SELECT akun_id, nama, role 
         FROM akun_rayhanRP 
         WHERE telegram_chat_id = ? 
         LIMIT 1',
        'i',
        $chat
    ));

    return $user;
}

function registerUserToTelegram($db, int $chat, string $nis, string $password): array {

    $user = sirey_fetchOne(sirey_query(
        'SELECT akun_id, nama, role, password 
         FROM akun_rayhanRP 
         WHERE nis = ? OR nip = ? 
         LIMIT 1',
        'ss',
        $nis,
        $nis
    ));

    if (!$user) {
        return ['success' => false];
    }

    if (!password_verify($password, $user['password'])) {
        return ['success' => false];
    }

    // bind telegram
    sirey_execute(
        'UPDATE akun_rayhanRP SET telegram_chat_id = ? WHERE akun_id = ?',
        'ii',
        $chat,
        $user['akun_id']
    );

    unset($user['password']);

    return [
        'success' => true,
        'user' => $user
    ];
}

function logoutUserFromTelegram($db, int $chat): bool {
    return sirey_execute(
        'UPDATE akun_rayhanRP SET telegram_chat_id = NULL WHERE telegram_chat_id = ?',
        'i',
        $chat
    );
}


// ================= JADWAL =================
function fetchUserSchedule($db, int $userId): array {

    return sirey_fetchAll(sirey_query(
        'SELECT j.judul, j.hari, j.jam_mulai, j.jam_selesai
         FROM jadwal_rayhanRP j
         INNER JOIN grup_anggota_rayhanRP ga ON j.grup_id = ga.grup_id
         WHERE ga.akun_id = ?
         ORDER BY j.jam_mulai ASC',
        'i',
        $userId
    ));
}


// ================= PENGUMUMAN =================
function fetchRecentAnnouncements(): array {

    return sirey_fetchAll(sirey_query(
        'SELECT judul 
         FROM pengumuman_rayhanRP 
         ORDER BY dibuat_pada DESC 
         LIMIT 5'
    ));
}


// ================= KEYBOARD =================
function buildKeyboardForRole(string $role): array {
    return [
        [['text'=>'Jadwal'], ['text'=>'Pengumuman']],
        [['text'=>'Pengaturan'], ['text'=>'Logout']]
    ];
}

function buildSettingsKeyboard(): array {
    return [
        [['text'=>'Notifikasi'], ['text'=>'Batal']]
    ];
}

function getMainMenuText(): string {
    return "Menu utama:\nPilih fitur:";
}