<?php
/**
 * Generate sample Excel file untuk import pengguna
 * Usage: http://localhost/bot_sirey/generate_template.php
 */

declare(strict_types=1);

if (!class_exists('PhpOffice\\PhpSpreadsheet\\IOFactory')) {
    $autoloadPath = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
    } else {
        http_response_code(500);
        die('Error: vendor/autoload.php not found at ' . $autoloadPath);
    }
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

try {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Set column widths
    $sheet->getColumnDimension('A')->setWidth(5);
    $sheet->getColumnDimension('B')->setWidth(25);
    $sheet->getColumnDimension('C')->setWidth(12);
    $sheet->getColumnDimension('D')->setWidth(8);
    
    // Header row
    $headers = ['NO', 'NAMA', 'NIS', 'L/P'];
    $sheet->fromArray($headers, NULL, 'A1');
    
    // Style header
    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    ];
    
    for ($col = 'A'; $col <= 'D'; $col++) {
        $sheet->getStyle($col . '1')->applyFromArray($headerStyle);
    }
    
    // Sample data
    $sampleData = [
        [1, 'Adi Pranoto', '001', 'L'],
        [2, 'Budi Santoso', '002', 'L'],
        [3, 'Citra Dewi', '003', 'P'],
        [4, 'Diana Putri', '004', 'P'],
        [5, 'Eka Wijaya', '005', 'L'],
        [6, 'Fatimah Zahra', '006', 'P'],
        [7, 'Guntur Dwi', '007', 'L'],
        [8, 'Hana Kusumo', '008', 'P'],
        [9, 'Indra Kusuma', '009', 'L'],
        [10, 'Joko Santoso', '010', 'L'],
    ];
    
    $sheet->fromArray($sampleData, NULL, 'A2');
    
    // Center align columns
    $sheet->getStyle('A2:A11')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('D2:D11')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    // Freeze panes
    $sheet->freezePane('A2');
    
    // Output file
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="Template_Import_Pengguna.xlsx"');
    header('Cache-Control: max-age=0');
    
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    
} catch (Exception $e) {
    http_response_code(500);
    echo 'Error: ' . htmlspecialchars($e->getMessage());
}
?>