<?php
/**
 * Fungsi helper untuk import Excel dan manage grup
 * Format Excel dummy yang didukung:
 *   NO | NAMA | NIS | L/P
 * Kolom "KELAS" juga didukung jika suatu saat ditambahkan.
 */

if (!class_exists('PhpOffice\\PhpSpreadsheet\\IOFactory')) {
    if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
        require_once __DIR__ . '/../vendor/autoload.php';
    }
}

require_once __DIR__ . '/helpers.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// ================== EXCEL IMPORT ==================

function normalizeExcelHeader(string $value): string
{
    // Hapus line breaks, tabs, dan whitespace berlebih
    $value = preg_replace('/[\r\n\t]+/', ' ', $value);  // Hapus line breaks & tabs
    $value = strtolower(trim($value));
    $value = preg_replace('/\s+/', ' ', $value);         // Multiple spaces jadi single space
    $value = str_replace(['_', '-', '/', '\\'], ' ', $value);
    $value = preg_replace('/\s+/', ' ', $value);         // Bersihkan lagi setelah replace

    return $value;
}

function normalizeJenisKelamin(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $first = strtoupper(substr($value, 0, 1));
    return in_array($first, ['L', 'P'], true) ? $first : null;
}

/**
 * Parse XLSX file dan return array of data.
 * Mendukung header:
 * NO | NAMA | NIS | L/P
 * Opsional: KELAS
 */
function parseExcelFile(string $filePath): array
{
    if (!file_exists($filePath)) {
        return ['error' => 'File tidak ditemukan'];
    }

    if (filesize($filePath) < 100) {
        return ['error' => 'File terlalu kecil, bukan Excel yang valid'];
    }

    try {
        // Check if ZipArchive is available (required for xlsx files)
        if (!class_exists('ZipArchive')) {
            return [
                'error' => '❌ Extension ZipArchive tidak aktif!<br><br>' .
                          'Solusi: Aktifkan extension "zip" di php.ini:<br>' .
                          '1. Buka file <code>d:\\xampp\\php\\php.ini</code><br>' .
                          '2. Cari baris <code>;extension=zip</code><br>' .
                          '3. Hapus tanda <code>;</code> menjadi <code>extension=zip</code><br>' .
                          '4. Simpan file<br>' .
                          '5. Restart Apache di XAMPP Control Panel<br>' .
                          '6. Coba import Excel lagi'
            ];
        }

        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);

        if (empty($rows)) {
            return ['error' => 'Tidak ada data di sheet'];
        }

        // Cari header row dari 10 baris pertama
        $headerRowIndex = null;
        $headerMap = [];

        $headerCandidates = [
            'nama' => 'nama',
            'nis' => 'nis',
            'nip' => 'nis',
            'nis nip' => 'nis',
            'nis/nip' => 'nis',
            'l p' => 'jenis_kelamin',
            'lp' => 'jenis_kelamin',
            'jenis kelamin' => 'jenis_kelamin',
            'jk' => 'jenis_kelamin',
            'kelas' => 'kelas',
            'grup' => 'kelas',
            'group' => 'kelas',
            'no' => 'no',
        ];

        foreach ($rows as $i => $row) {
            $normalized = [];
            foreach ($row as $cell) {
                $normalized[] = normalizeExcelHeader((string)$cell);
            }

            $hasNama = false;
            $hasNis = false;
            $hasGender = false;

            foreach ($normalized as $idx => $head) {
                if ($head === '') {
                    continue;
                }
                
                // Exact match
                if (isset($headerCandidates[$head])) {
                    $headerMap[$headerCandidates[$head]] = $idx;
                    if ($headerCandidates[$head] === 'nama') $hasNama = true;
                    if ($headerCandidates[$head] === 'nis') $hasNis = true;
                    if ($headerCandidates[$head] === 'jenis_kelamin') $hasGender = true;
                }
                // Partial match untuk jenis_kelamin (handle "L/P TGL" merged header)
                elseif (!$hasGender && (strpos($head, 'l p') === 0 || strpos($head, 'lp') === 0 || 
                        strpos($head, 'jenis') !== false || strpos($head, 'jk') !== false)) {
                    $headerMap['jenis_kelamin'] = $idx;
                    $hasGender = true;
                }
            }

            if ($hasNama && $hasNis) {
                $headerRowIndex = $i;
                break;
            }
        }

        // Fallback jika header tidak terdeteksi: pakai layout dummy standar
        if ($headerRowIndex === null) {
            $headerRowIndex = 0;
            $headerMap = [
                'no' => 0,
                'nama' => 1,
                'nis' => 2,
                'jenis_kelamin' => 3,
                'kelas' => 4,
            ];
        }

        $data = [];

        for ($i = $headerRowIndex + 1; $i < count($rows); $i++) {
            $row = $rows[$i];

            $nama = '';
            $nis = '';
            $jk = null;
            $kelas = null;

            if (array_key_exists('nama', $headerMap) && isset($row[$headerMap['nama']])) {
                $nama = trim((string)$row[$headerMap['nama']]);
            }

            if (array_key_exists('nis', $headerMap) && isset($row[$headerMap['nis']])) {
                $nis = trim((string)$row[$headerMap['nis']]);
            }

            if (array_key_exists('jenis_kelamin', $headerMap) && isset($row[$headerMap['jenis_kelamin']])) {
                $jk = normalizeJenisKelamin($row[$headerMap['jenis_kelamin']]);
            }

            if (array_key_exists('kelas', $headerMap) && isset($row[$headerMap['kelas']])) {
                $kelas = trim((string)$row[$headerMap['kelas']]);
                $kelas = $kelas !== '' ? $kelas : null;
            }

            // Skip baris kosong
            if ($nis === '' && $nama === '') {
                continue;
            }

            // NIS wajib
            if ($nis === '') {
                continue;
            }

            $data[] = [
                'nama' => $nama,
                'nis' => $nis,
                'jenis_kelamin' => $jk,
                'kelas' => $kelas,
            ];
        }

        if (empty($data)) {
            return ['error' => 'Tidak ada data pengguna ditemukan di sheet'];
        }

        return ['data' => $data];

    } catch (Exception $e) {
        return ['error' => 'Error saat membaca Excel: ' . $e->getMessage()];
    }
}

// ================== GRUP MANAGEMENT ==================

function getAllGrups(mixed $database_rayhanrp): array
{
    $pernyataan_rayhanrp = sirey_query(
        'SELECT grup_id, nama_grup FROM grup_rayhanrp ORDER BY nama_grup ASC',
        ''
    );

    $daftar_grup_rayhanrp = [];
    if ($pernyataan_rayhanrp) {
        $baris_rayhanrp = sirey_fetchAll($pernyataan_rayhanrp);
        foreach ($baris_rayhanrp as $item_rayhanrp) {
            $daftar_grup_rayhanrp[(int)$item_rayhanrp['grup_id']] = $item_rayhanrp['nama_grup'];
        }
    }

    return $daftar_grup_rayhanrp;
}

function getGrupName(mixed $database_rayhanrp, int $id_grup_rayhanrp): ?string
{
    $pernyataan_rayhanrp = sirey_query(
        'SELECT nama_grup FROM grup_rayhanrp WHERE grup_id = ? LIMIT 1',
        'i',
        $id_grup_rayhanrp
    );

    if ($pernyataan_rayhanrp) {
        $item_rayhanrp = sirey_fetch($pernyataan_rayhanrp);
        return $item_rayhanrp ? $item_rayhanrp['nama_grup'] : null;
    }

    return null;
}

function getOrCreateGrup(mixed $database_rayhanrp, string $nama_grup_rayhanrp): ?int
{
    $nama_grup_rayhanrp = trim($nama_grup_rayhanrp);
    if ($nama_grup_rayhanrp === '') {
        return null;
    }

    $pernyataan_rayhanrp = sirey_query(
        'SELECT grup_id FROM grup_rayhanRP WHERE nama_grup = ? LIMIT 1',
        's',
        $nama_grup_rayhanrp
    );

    if ($pernyataan_rayhanrp) {
        $item_rayhanrp = sirey_fetch($pernyataan_rayhanrp);
        if ($item_rayhanrp) {
            return (int)$item_rayhanrp['grup_id'];
        }
    }

    $hasil_rayhanrp = sirey_execute(
        'INSERT INTO grup_rayhanRP (nama_grup) VALUES (?)',
        's',
        $nama_grup_rayhanrp
    );

    if ($hasil_rayhanrp >= 1) {
        return (int)sirey_lastInsertId();
    }

    return null;
}

function addUserToGrup($database_rayhanrp, int $id_akun_rayhanrp, int $id_grup_rayhanrp): bool
{
    if ($id_akun_rayhanrp <= 0 || $id_grup_rayhanrp <= 0) {
        return false;
    }

    syncPrimaryGroup($id_akun_rayhanrp, $id_grup_rayhanrp);
    return true;
}

/**
 * Import pengguna dari Excel.
 * - Default role = siswa
 * - Password default = NIS
 * - Jika $id_grup_override_rayhanrp diisi, semua data masuk ke grup itu
 * - Jika Excel punya kolom KELAS dan tidak ada override, grup akan dibuat dari nama kelas
 */
function importUsersFromExcel(mixed $database_rayhanrp, string $path_file_rayhanrp, ?int $id_grup_override_rayhanrp = null): array
{
    $hasil_parsing_rayhanrp = parseExcelFile($path_file_rayhanrp);
    if (isset($hasil_parsing_rayhanrp['error'])) {
        return ['success' => false, 'error' => $hasil_parsing_rayhanrp['error']];
    }

    $data_rayhanrp = $hasil_parsing_rayhanrp['data'] ?? [];
    $jumlah_imported_rayhanrp = 0;
    $jumlah_gagal_rayhanrp = 0;
    $daftar_error_rayhanrp = [];

    foreach ($data_rayhanrp as $baris_rayhanrp) {
        $nis_rayhanrp = trim((string)($baris_rayhanrp['nis'] ?? ''));
        $nama_rayhanrp = trim((string)($baris_rayhanrp['nama'] ?? ''));
        $jenis_kelamin_rayhanrp = $baris_rayhanrp['jenis_kelamin'] ?? null;
        $kelas_rayhanrp = trim((string)($baris_rayhanrp['kelas'] ?? ''));

        if ($nis_rayhanrp === '' || $nama_rayhanrp === '') {
            $jumlah_gagal_rayhanrp++;
            $daftar_error_rayhanrp[] = 'Baris kosong atau NIS/Nama tidak lengkap';
            continue;
        }

        // Cegah duplikat NIS/NIP
        $hasil_cek_rayhanrp = sirey_query(
            'SELECT akun_id FROM akun_rayhanRP WHERE nis_nip = ? LIMIT 1',
            's',
            $nis_rayhanrp
        );
        if ($hasil_cek_rayhanrp && sirey_fetch($hasil_cek_rayhanrp)) {
            $jumlah_gagal_rayhanrp++;
            $daftar_error_rayhanrp[] = "NIS/NIP $nis_rayhanrp sudah ada, dilewati";
            continue;
        }

        $role_rayhanrp = 'siswa';
        $password_hash_rayhanrp = hashPassword($nis_rayhanrp);

        // Tentukan grup yang dipakai
        $id_grup_simpan_rayhanrp = null;
        if (!empty($id_grup_override_rayhanrp)) {
            $id_grup_simpan_rayhanrp = (int)$id_grup_override_rayhanrp;
        } elseif ($kelas_rayhanrp !== '') {
            $id_grup_simpan_rayhanrp = getOrCreateGrup($database_rayhanrp, $kelas_rayhanrp);
        }

        $hasil_rayhanrp = sirey_execute(
            'INSERT INTO akun_rayhanRP (nis_nip, password, role, nama_lengkap, jenis_kelamin)
             VALUES (?, ?, ?, ?, ?)',
            'sssss',
            $nis_rayhanrp,
            $password_hash_rayhanrp,
            $role_rayhanrp,
            $nama_rayhanrp,
            $jenis_kelamin_rayhanrp
        );

        if ($hasil_rayhanrp < 1) {
            $jumlah_gagal_rayhanrp++;
            $daftar_error_rayhanrp[] = "NIS $nis_rayhanrp: gagal insert";
            continue;
        }

        $id_akun_rayhanrp = (int)sirey_lastInsertId();

        // Sinkronkan ke tabel anggota grup kalau grup dipilih / kelas ada
        if ($id_grup_simpan_rayhanrp !== null) {
            syncPrimaryGroup($id_akun_rayhanrp, $id_grup_simpan_rayhanrp);
        }

        $jumlah_imported_rayhanrp++;
    }

    return [
        'success' => ($jumlah_imported_rayhanrp > 0),
        'imported' => $jumlah_imported_rayhanrp,
        'failed' => $jumlah_gagal_rayhanrp,
        'errors' => $daftar_error_rayhanrp,
    ];
}
?>
