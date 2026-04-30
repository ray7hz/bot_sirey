<?php
declare(strict_types=1);

require_once __DIR__ . '/../koneksi.php';


// ================== SESSION ==================

function startSession(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function requireAdminSession(string $redirectTo = 'login.php'): array
{
    startSession();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout'])) {
        session_destroy();
        header('Location: ' . $redirectTo);
        exit;
    }

    if (empty($_SESSION['admin_id'])) {
        header('Location: ' . $redirectTo);
        exit;
    }

    return [
        'id'      => (int)$_SESSION['admin_id'],
        'nis_nip' => (string)($_SESSION['admin_nis_nip'] ?? ''),
        'role'    => (string)($_SESSION['admin_role'] ?? 'guru'),
        'name'    => (string)($_SESSION['admin_name'] ?? 'Admin'),
    ];
}

function setAdminSession(int $id, string $nis, string $role, string $name): void
{
    startSession();
    session_regenerate_id(true);

    $_SESSION['admin_id']      = $id;
    $_SESSION['admin_nis_nip'] = $nis;
    $_SESSION['admin_role']    = $role;
    $_SESSION['admin_name']    = $name;
}

function redirectTo(string $url): never
{
    header('Location: ' . $url);
    exit;
}


// ================== ACCOUNT ==================

function fetchAccountByNis(string $nis_rayhanrp): ?array
{
    $pernyataan_rayhanrp = sirey_query(
        'SELECT akun_id, nis_nip, password, role, nama_lengkap
         FROM akun_rayhanRP WHERE nis_nip = ? LIMIT 1',
        's',
        $nis_rayhanrp
    );

    $baris_rayhanrp = sirey_fetch($pernyataan_rayhanrp);

    if (!$baris_rayhanrp) return null;

    return [
        'akun_id'      => (int)$baris_rayhanrp['akun_id'],
        'nis_nip'      => (string)$baris_rayhanrp['nis_nip'],
        'password'     => (string)$baris_rayhanrp['password'],
        'role'         => strtolower(trim((string)$baris_rayhanrp['role'])),
        'nama_lengkap' => (string)($baris_rayhanrp['nama_lengkap'] ?? ''),
    ];
}

function verifyAccountPassword(array $akun_rayhanrp, string $input_rayhanrp): bool
{
    $tersimpan_rayhanrp = (string)$akun_rayhanrp['password'];

    if (str_starts_with($tersimpan_rayhanrp, '$2')) {
        return password_verify($input_rayhanrp, $tersimpan_rayhanrp);
    }

    return hash_equals($tersimpan_rayhanrp, $input_rayhanrp);
}

function hashPassword(string $password): string
{
    return password_hash($password, PASSWORD_DEFAULT);
}


// ================== TELEGRAM ==================

function getTelegramBotToken(): string
{
    return (string)sirey_getConfigValue('telegram.bot_token', '');
}

function sendTelegramMessage(int $id_chat_rayhanrp, string $teks_rayhanrp, ?array $keyboard_rayhanrp = null): bool
{
    $token_rayhanrp = getTelegramBotToken();
    if ($token_rayhanrp === '') {
        error_log("[sendTelegramMessage] ❌ Bot token kosong");
        return false;
    }

    $parameter_rayhanrp = [
        'chat_id' => $id_chat_rayhanrp,
        'text' => $teks_rayhanrp,
        'parse_mode' => 'Markdown'
    ];

    if ($keyboard_rayhanrp !== null) {
        $parameter_rayhanrp['reply_markup'] = json_encode($keyboard_rayhanrp);
    }

    $koneksi_rayhanrp = curl_init("https://api.telegram.org/bot{$token_rayhanrp}/sendMessage");

    curl_setopt_array($koneksi_rayhanrp, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($parameter_rayhanrp),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);

    $respons_rayhanrp = curl_exec($koneksi_rayhanrp);
    $curl_error = curl_error($koneksi_rayhanrp);
    curl_close($koneksi_rayhanrp);

    // Parse JSON response untuk check "ok": true
    if (empty($respons_rayhanrp)) {
        error_log("[sendTelegramMessage] ❌ No response dari Telegram API, curl error: $curl_error");
        return false;
    }

    $result = json_decode($respons_rayhanrp, true);
    if (!is_array($result)) {
        error_log("[sendTelegramMessage] ❌ Invalid JSON response: $respons_rayhanrp");
        return false;
    }

    if (!($result['ok'] ?? false)) {
        error_log("[sendTelegramMessage] ❌ Telegram API error: " . json_encode($result));
        return false;
    }

    error_log("[sendTelegramMessage] ✅ Message sent to chat_id=$id_chat_rayhanrp");
    return true;
}


// ================== STATE ==================

function _stateFile(): string
{
    return (string)sirey_getConfigValue(
        'app.state_file',
        __DIR__ . '/../data/state.json'
    );
}

function loadStates(): array
{
    $file_rayhanrp = _stateFile();
    if (!file_exists($file_rayhanrp)) return [];

    // Retry logic untuk handle file locking
    $retries = 3;
    $delay = 100000; // 0.1 second
    
    for ($i = 0; $i < $retries; $i++) {
        $content = @file_get_contents($file_rayhanrp);
        
        if ($content !== false) {
            $data_rayhanrp = json_decode($content, true);
            return is_array($data_rayhanrp) ? $data_rayhanrp : [];
        }
        
        if ($i < $retries - 1) {
            usleep($delay);
        }
    }
    
    return [];
}

function saveStates(array $data_rayhanrp): void
{
    $file_rayhanrp = _stateFile();
    $direktori_rayhanrp  = dirname($file_rayhanrp);

    if (!is_dir($direktori_rayhanrp)) {
        mkdir($direktori_rayhanrp, 0755, true);
    }

    // Retry logic untuk handle file locking
    $retries = 3;
    $delay = 100000; // 0.1 second
    
    for ($i = 0; $i < $retries; $i++) {
        $result = @file_put_contents($file_rayhanrp, json_encode($data_rayhanrp, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
        
        if ($result !== false) {
            return;
        }
        
        if ($i < $retries - 1) {
            usleep($delay);
        }
    }
    
    error_log("[STATE] Failed to save states after {$retries} retries");
}

function getState(int $id_chat_rayhanrp): ?array
{
    $daftar_state_rayhanrp = loadStates();
    return $daftar_state_rayhanrp[(string)$id_chat_rayhanrp] ?? null;
}

function setState(int $id_chat_rayhanrp, ?array $state_rayhanrp): void
{
    $daftar_state_rayhanrp = loadStates();

    if ($state_rayhanrp === null) {
        unset($daftar_state_rayhanrp[(string)$id_chat_rayhanrp]);
    } else {
        $daftar_state_rayhanrp[(string)$id_chat_rayhanrp] = $state_rayhanrp;
    }

    saveStates($daftar_state_rayhanrp);
}


// ================== UTIL ==================

function formatDatetime(?string $datetime_rayhanrp, string $format_rayhanrp = 'd/m/Y H:i'): string
{
    if ($datetime_rayhanrp === null || $datetime_rayhanrp === '') {
        return '-';
    }
    $timestamp_rayhanrp = strtotime($datetime_rayhanrp);
    return $timestamp_rayhanrp ? date($format_rayhanrp, $timestamp_rayhanrp) : '-';
}


// ================== VALIDATION ==================

function isValidNis(string $nis): bool
{
    return (bool)preg_match('/^\d{4,8}$/', trim($nis));
}

function isValidNip(string $nip): bool
{
    return (bool)preg_match('/^\d{8,18}$/', trim($nip));
}

// ================== TELEGRAM HELPERS ==================

/**
 * Check if text is a command (e.g., /start, /stop)
 */
function isCommand(string $text, string $command): bool
{
    $pattern = '/^' . preg_quote($command, '/') . '(@[A-Za-z0-9_]+)?(\s|$)/i';
    return (bool)preg_match($pattern, trim($text));
}

/**
 * Build reply keyboard
 */
function buildKeyboard(array $buttons, bool $resizable = true): array
{
    return [
        'keyboard'         => $buttons,
        'resize_keyboard'  => $resizable,
        'one_time_keyboard' => true,
    ];
}

/**
 * Build inline keyboard
 */
function buildInlineKeyboard(array $buttons): array
{
    return ['inline_keyboard' => $buttons];
}

function getMatpelGuru(int $guruId): array
{
    return sirey_fetchAll(sirey_query(
        'SELECT DISTINCT mp.matpel_id, mp.kode, mp.nama
         FROM guru_mengajar_rayhanRP gm
         INNER JOIN mata_pelajaran_rayhanRP mp ON gm.matpel_id = mp.matpel_id
         WHERE gm.akun_id = ? AND gm.aktif = 1 AND mp.aktif = 1
         ORDER BY mp.nama ASC',
        'i',
        $guruId
    ));
}

function getTeachingCoverageByMatpel(int $matpelId, ?int $guruId = null): array
{
    if ($matpelId <= 0) {
        return [];
    }

    $sql = 'SELECT g.grup_id, g.nama_grup, g.jurusan,
                   GROUP_CONCAT(DISTINCT a.nama_lengkap ORDER BY a.nama_lengkap SEPARATOR ", ") AS guru_nama
            FROM guru_mengajar_rayhanRP gm
            INNER JOIN grup_rayhanRP g ON gm.grup_id = g.grup_id
            INNER JOIN akun_rayhanRP a ON gm.akun_id = a.akun_id
            WHERE gm.matpel_id = ? AND gm.aktif = 1 AND g.aktif = 1';
    $types = 'i';
    $params = [$matpelId];

    if ($guruId !== null && $guruId > 0) {
        $sql .= ' AND gm.akun_id = ?';
        $types .= 'i';
        $params[] = $guruId;
    }

    $sql .= ' GROUP BY g.grup_id, g.nama_grup, g.jurusan
              ORDER BY g.nama_grup ASC';

    return sirey_fetchAll(sirey_query($sql, $types, ...$params));
}

function getGrupDiajarGuru(int $guruId, ?int $matpelId = null): array
{
    $sql = 'SELECT DISTINCT g.grup_id, g.nama_grup, g.jurusan
            FROM guru_mengajar_rayhanRP gm
            INNER JOIN grup_rayhanRP g ON gm.grup_id = g.grup_id
            WHERE gm.akun_id = ? AND gm.aktif = 1 AND g.aktif = 1';
    $types = 'i';
    $params = [$guruId];

    if ($matpelId !== null && $matpelId > 0) {
        $sql .= ' AND gm.matpel_id = ?';
        $types .= 'i';
        $params[] = $matpelId;
    }

    $sql .= ' ORDER BY g.nama_grup ASC';

    return sirey_fetchAll(sirey_query($sql, $types, ...$params));
}

function getPrimaryGroupId(int $akunId): ?int
{
    $row = sirey_fetch(sirey_query(
        'SELECT grup_id
         FROM grup_anggota_rayhanRP
         WHERE akun_id = ? AND tipe_keanggotaan = "utama" AND aktif = 1
         ORDER BY bergabung_pada DESC, keanggotaan_id DESC
         LIMIT 1',
        'i',
        $akunId
    ));

    if (!$row || !isset($row['grup_id'])) {
        return null;
    }

    $grupId = (int)$row['grup_id'];
    return $grupId > 0 ? $grupId : null;
}

function grupHasMatpelAssignment(int $grupId, int $matpelId, ?int $guruId = null): bool
{
    if ($grupId <= 0 || $matpelId <= 0) {
        return false;
    }

    $sql = 'SELECT id
            FROM guru_mengajar_rayhanRP
            WHERE grup_id = ?
              AND matpel_id = ?
              AND aktif = 1';
    $types = 'ii';
    $params = [$grupId, $matpelId];

    if ($guruId !== null && $guruId > 0) {
        $sql .= ' AND akun_id = ?';
        $types .= 'i';
        $params[] = $guruId;
    }

    return (bool)sirey_fetch(sirey_query($sql, $types, ...$params));
}

function getSemuaGrupUser(int $akunId, bool $onlyActive = true): array
{
    $sql = 'SELECT g.grup_id, g.nama_grup, g.jurusan, ga.tipe_keanggotaan, ga.aktif
            FROM grup_anggota_rayhanRP ga
            INNER JOIN grup_rayhanRP g ON ga.grup_id = g.grup_id
            WHERE ga.akun_id = ?';
    $types = 'i';
    $params = [$akunId];

    if ($onlyActive) {
        $sql .= ' AND ga.aktif = 1 AND g.aktif = 1';
    }

    $sql .= ' ORDER BY FIELD(ga.tipe_keanggotaan, "utama", "tambahan"), g.nama_grup ASC';

    return sirey_fetchAll(sirey_query($sql, $types, ...$params));
}

function ensureUserMembership(int $akunId, int $grupId, string $tipeKeanggotaan = 'tambahan'): void
{
    sirey_execute(
        'INSERT INTO grup_anggota_rayhanRP (grup_id, akun_id, tipe_keanggotaan, aktif)
         VALUES (?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE tipe_keanggotaan = VALUES(tipe_keanggotaan), aktif = 1',
        'iis',
        $grupId,
        $akunId,
        $tipeKeanggotaan
    );
}

function removeUserMembership(int $akunId, int $grupId): void
{
    sirey_execute(
        'DELETE FROM grup_anggota_rayhanRP WHERE akun_id = ? AND grup_id = ?',
        'ii',
        $akunId,
        $grupId
    );
}

function syncPrimaryGroup(int $akunId, ?int $grupId): void
{
    $oldGrupId = getPrimaryGroupId($akunId) ?? 0;

    sirey_execute(
        'UPDATE grup_anggota_rayhanRP
         SET tipe_keanggotaan = "tambahan"
         WHERE akun_id = ? AND tipe_keanggotaan = "utama"',
        'i',
        $akunId
    );

    if ($oldGrupId > 0 && $oldGrupId !== $grupId) {
        sirey_execute(
            'UPDATE grup_anggota_rayhanRP SET tipe_keanggotaan = "tambahan" WHERE akun_id = ? AND grup_id = ?',
            'ii',
            $akunId,
            $oldGrupId
        );
    }

    if ($grupId !== null && $grupId > 0) {
        ensureUserMembership($akunId, $grupId, 'utama');
        sirey_execute(
            'UPDATE grup_anggota_rayhanRP
             SET tipe_keanggotaan = "tambahan"
             WHERE akun_id = ? AND grup_id <> ? AND tipe_keanggotaan = "utama"',
            'ii',
            $akunId,
            $grupId
        );
    }
}

// ================== GURU TEACHING SCOPE ==================

/**
 * Get semua siswa dari kelas yang diajar guru
 * Berguna untuk tugas perorang - guru hanya bisa assign ke siswa di kelasnya
 */
function getSiswaFromGuruKelas(int $guruId, ?int $matpelId = null): array
{
    $sql = 'SELECT DISTINCT a.akun_id, a.nis_nip, a.nama_lengkap, g.nama_grup
            FROM akun_rayhanRP a
            INNER JOIN grup_anggota_rayhanRP ga ON a.akun_id = ga.akun_id
            INNER JOIN guru_mengajar_rayhanRP gm ON ga.grup_id = gm.grup_id
            INNER JOIN grup_rayhanRP g ON ga.grup_id = g.grup_id
            WHERE a.role = "siswa"
              AND ga.aktif = 1
              AND gm.akun_id = ?
              AND gm.aktif = 1
              AND g.aktif = 1';
    
    $types = 'i';
    $params = [$guruId];
    
    if ($matpelId !== null && $matpelId > 0) {
        $sql .= ' AND gm.matpel_id = ?';
        $types .= 'i';
        $params[] = $matpelId;
    }
    
    $sql .= ' ORDER BY a.nama_lengkap ASC';
    
    return sirey_fetchAll(sirey_query($sql, $types, ...$params));
}

/**
 * Check apakah guru punya akses ke siswa tertentu 
 * (artinya siswa ada di kelas yang diajar guru)
 */
if (!function_exists('guruHasAccessToSiswa')) {
    function guruHasAccessToSiswa(int $guruId, int $siswaId, ?int $matpelId = null): bool
    {
        $sql = 'SELECT 1
                FROM guru_mengajar_rayhanRP gm
                INNER JOIN grup_anggota_rayhanRP ga ON gm.grup_id = ga.grup_id
                WHERE gm.akun_id = ?
                  AND ga.akun_id = ?
                  AND gm.aktif = 1
                  AND ga.aktif = 1';
        
        $types = 'ii';
        $params = [$guruId, $siswaId];
        
        if ($matpelId !== null && $matpelId > 0) {
            $sql .= ' AND gm.matpel_id = ?';
            $types .= 'i';
            $params[] = $matpelId;
        }
        
        return (bool)sirey_fetch(sirey_query($sql, $types, ...$params));
    }
}
