<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Jakarta');
/**
 * runner.php
 * Scheduler notifikasi — jalan terus di background.
 *
 * Cara kerja:
 *   - Loop tiap 30 detik
 *   - Catat menit mana yang sudah diproses hari ini (di memori)
 *   - Kalau menit sekarang belum diproses → jalankan notifikasi
 *   - Reset catatan tiap hari baru
 *
 * Cara jalankan:
 *   php runner.php            ← normal
 *   php runner.php debug      ← jalankan sekali sekarang lalu keluar
 */

$phpBin = PHP_BINARY;
$dir    = __DIR__;
$logDir = __DIR__ . '/../data';

if (!is_dir($logDir)) mkdir($logDir, 0755, true);

$lockFile = "{$logDir}/runner.lock";
$logFile  = "{$logDir}/runner.log";

// ── Cegah multiple instance ──────────────────────────────────────────────────
if (file_exists($lockFile)) {
    $pid = trim(file_get_contents($lockFile));
    $age = time() - filemtime($lockFile);
    // Cek apakah PID masih hidup (Linux) atau lewat 5 menit (Windows)
    $masihHidup = (PHP_OS_FAMILY === 'Windows')
        ? $age < 300
        : file_exists("/proc/{$pid}");
    if ($masihHidup) {
        echo "[runner] ⚠️ Runner sudah berjalan (PID {$pid}, umur {$age}s)\n";
        exit(1);
    }
    unlink($lockFile);
}
file_put_contents($lockFile, getmypid());
register_shutdown_function(fn() => file_exists($lockFile) && unlink($lockFile));

// ── Helper log ───────────────────────────────────────────────────────────────
function log_r(string $msg): void
{
    global $logFile;
    $baris = "[" . date('Y-m-d H:i:s') . "] {$msg}\n";
    echo $baris;
    file_put_contents($logFile, $baris, FILE_APPEND | LOCK_EX);
}

// ── Jalankan child script, tampilkan output di log ───────────────────────────
function jalankan(string $phpBin, string $script): void
{
    $nama   = basename($script);
    $output = shell_exec("\"{$phpBin}\" \"{$script}\" 2>&1");

    if ($output === null || $output === '') {
        log_r("  [{$nama}] (tidak ada output)");
        return;
    }
    foreach (explode("\n", trim($output)) as $baris) {
        if ($baris !== '') log_r("  [{$nama}] {$baris}");
    }
}

// ── Mode debug: jalankan sekali lalu keluar ──────────────────────────────────
if (isset($argv[1]) && $argv[1] === 'debug') {
    log_r("▶ MODE DEBUG — jalankan semua notifikasi sekarang");
    jalankan($phpBin, "{$dir}/notifikasi_jadwal.php");
    jalankan($phpBin, "{$dir}/notifikasi_tugas.php");
    log_r("✔ DEBUG selesai");
    exit(0);
}

// ── Loop utama ───────────────────────────────────────────────────────────────
log_r("▶ Runner dimulai (PID: " . getmypid() . ")");

$sudahProses = [];  // ["2026-04-30" => ["07:00", "10:23", ...]]

while (true) {
    $now      = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
    $tanggal  = $now->format('Y-m-d');
    $menit    = $now->format('H:i');   // contoh: "10:23"

    // Reset catatan kalau hari baru
    if (!isset($sudahProses[$tanggal])) {
        $sudahProses = [$tanggal => []];
        log_r("📅 Hari baru: {$tanggal}");
    }

    // Kalau menit ini belum pernah diproses → jalankan
    if (!in_array($menit, $sudahProses[$tanggal], true)) {
        $sudahProses[$tanggal][] = $menit;

        log_r("━━━ {$tanggal} {$menit} ━━━");
        jalankan($phpBin, "{$dir}/notifikasi_jadwal.php");
        jalankan($phpBin, "{$dir}/notifikasi_tugas.php");
    }

    // Tidur 30 detik — cukup presisi, tidak mungkin meleset 1 menit penuh
    sleep(30);
}