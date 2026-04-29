<?php
/**
 * download_template.php - Download template CSV untuk import pengguna
 * Akses: http://localhost/bot_sirey/dashboard/download_template.php
 */

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=template_pengguna.csv');

$output = fopen('php://output', 'w');

// Header kolom
fputcsv($output, ['NIS/NIP', 'Nama Lengkap', 'Role', 'Kelas', 'Jenis_Kelamin']);

// Contoh data
$examples = [
    ['1001', 'Ahmad Rizki Pratama', 'siswa', 'XII RPL A', 'L'],
    ['1002', 'Siti Nur Azizah', 'siswa', 'XII RPL A', 'P'],
    ['1003', 'Muhammad Ikhsan', 'siswa', 'XII RPL A', 'L'],
    ['2001', 'Budi Santoso', 'guru', '', 'L'],
    ['2002', 'Ina Mustika Sari', 'guru', '', 'P'],
    ['3001', 'Admin User', 'admin', '', ''],
];

foreach ($examples as $row) {
    fputcsv($output, $row);
}

fclose($output);
?>
