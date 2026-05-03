<?php
declare(strict_types=1);

require_once __DIR__ . '/../koneksi.php';

function can(string $permission, array $admin): bool
{
    $role_rayhanrp = strtolower(trim((string)($admin['role'] ?? '')));

    $matriks_rayhanrp = [
        'view_dashboard'        => ['guru', 'admin', 'kurikulum', 'kepala_sekolah'],
        'view_users'            => ['admin', 'kepala_sekolah', 'kurikulum'],
        'create_user'           => ['admin'],
        'update_user'           => ['admin'],
        'delete_user'           => ['admin'],
        'reset_password'        => ['admin'],
        'view_grup'             => ['kurikulum', 'kepala_sekolah'],
        'create_grup'           => ['kurikulum'],
        'update_grup'           => ['kurikulum'],
        'delete_grup'           => ['kurikulum'],
        'view_kelas_saya'       => ['guru'],
        'view_jadwal'           => ['guru', 'kurikulum', 'kepala_sekolah'],
        'create_jadwal'         => ['admin'],
        'update_jadwal'         => ['admin'],
        'delete_jadwal'         => ['admin'],
        'view_tugas'            => ['guru', 'kurikulum', 'kepala_sekolah'],
        'create_tugas'          => ['guru'],
        'update_tugas'          => ['guru'],
        'delete_tugas'          => ['guru'],
        'view_penilaian'        => ['guru', 'kurikulum', 'kepala_sekolah'],
        'update_penilaian'      => ['guru'],
        'view_notifikasi'       => ['kurikulum', 'kepala_sekolah'],
        'create_pengumuman'     => ['guru', 'kurikulum'],
        'view_pengumuman'       => ['guru', 'kurikulum', 'kepala_sekolah'],
        'view_audit_log'        => ['kepala_sekolah'],
        'view_mata_pelajaran'   => ['admin', 'kurikulum', 'kepala_sekolah'],
        'manage_mata_pelajaran' => ['admin', 'kurikulum'],
        'manage_guru_mengajar'  => ['kurikulum'],
        'write_any'             => [],
    ];

    return in_array($role_rayhanrp, $matriks_rayhanrp[$permission] ?? [], true);
}

function isReadonlyRole(array $admin): bool
{
    return strtolower(trim((string)($admin['role'] ?? ''))) === 'kepala_sekolah';
}

function requireNotReadonly(array $admin, string $redirectTo = 'dashboard.php'): void
{
    if (isReadonlyRole($admin)) {
        header('Location: ' . $redirectTo . '?err=readonly');
        exit;
    }
}

if (!function_exists('guruHasScopeToGrup')) {
    function guruHasScopeToGrup(int $id_guru_rayhanrp, int $id_grup_rayhanrp, ?int $id_matpel_rayhanrp = null): bool
    {
        $pernyataan_sql_rayhanrp = 'SELECT id
                FROM guru_mengajar_rayhanRP
                WHERE akun_id = ?
                  AND grup_id = ?
                  AND aktif = 1';
        $tipe_rayhanrp = 'ii';
        $parameter_rayhanrp = [$id_guru_rayhanrp, $id_grup_rayhanrp];

        if ($id_matpel_rayhanrp !== null && $id_matpel_rayhanrp > 0) {
            $pernyataan_sql_rayhanrp .= ' AND matpel_id = ?';
            $tipe_rayhanrp .= 'i';
            $parameter_rayhanrp[] = $id_matpel_rayhanrp;
        }

        return (bool) sirey_fetch(sirey_query($pernyataan_sql_rayhanrp, $tipe_rayhanrp, ...$parameter_rayhanrp));
    }
}

function auditLog(
    ?int $akunId,
    string $aksi,
    ?string $objekTipe = null,
    ?int $objekId = null,
    array $detail = [],
    string $status = 'sukses'
): void {
    static $tableChecked = false;
    static $tableExists = false;

    if (!$tableChecked) {
        try {
            $db = sirey_getDatabase();
            $res = mysqli_query($db, "SHOW TABLES LIKE 'audit_log_rayhanRP'");
            $tableExists = $res && mysqli_num_rows($res) > 0;
        } catch (Throwable) {
            $tableExists = false;
        }
        $tableChecked = true;
    }

    if (!$tableExists) {
        return;
    }

    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    $jsonDetail = $detail !== [] ? json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;

    sirey_execute(
        'INSERT INTO audit_log_rayhanRP (akun_id, aksi, objek_tipe, objek_id, detail, status, ip_address, user_agent)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        'ississss',
        $akunId,
        $aksi,
        $objekTipe,
        $objekId,
        $jsonDetail,
        $status,
        $ip !== '' ? $ip : null,
        $ua !== '' ? $ua : null
    );
}
