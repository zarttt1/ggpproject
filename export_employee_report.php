<?php
// Pastikan tidak ada spasi sebelum tag PHP
require 'vendor/autoload.php';
require 'db_connect.php';
session_start();

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

// 1. Ambil Semua Data Karyawan
// Sesuaikan nama kolom dengan tabel 'karyawan' dan 'func' Anda
$sql = "
    SELECT 
        k.index_karyawan, 
        k.nama_karyawan, 
        f.func_n2 as bu, 
        f.func_n1 as jabatan,
        k.gender,
        k.status_karyawan
    FROM karyawan k
    LEFT JOIN func f ON k.id_func = f.id_func
    ORDER BY k.nama_karyawan ASC
";
$result = $conn->query($sql);

// --- PROSES EXCEL ---
$templateFile = __DIR__ . '/uploads/Employee Reports.xlsx'; 
if (!file_exists($templateFile)) {
    die("File template tidak ditemukan.");
}

$spreadsheet = IOFactory::load($templateFile);
$sheet = $spreadsheet->getActiveSheet();

// 2. Isi Header (Contoh: Menampilkan total karyawan)
$total_karyawan = $result->num_rows;
$sheet->setCellValue('C7', ': Seluruh Karyawan');
$sheet->setCellValue('C8', ': ' . date('d M Y'));
$sheet->setCellValue('C9', ': ' . $total_karyawan . ' Orang');

// 3. Isi Tabel Data (Mulai Baris 12 sesuai struktur umum template)
$row = 12; 
$no = 1;

while ($emp = $result->fetch_assoc()) {
    $sheet->setCellValue('A' . $row, $no);
    $sheet->setCellValue('B' . $row, $emp['index_karyawan']);
    $sheet->setCellValue('C' . $row, $emp['nama_karyawan']);
    $sheet->setCellValue('D' . $row, $emp['bu']);
    $sheet->setCellValue('E' . $row, $emp['jabatan']);
    $sheet->setCellValue('F' . $row, $emp['gender']);
    $sheet->setCellValue('G' . $row, $emp['status_karyawan']);

    // Berikan border agar rapi
    $sheet->getStyle("A$row:G$row")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    
    // Rata tengah untuk No dan Index
    $sheet->getStyle("A$row:B$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $row++;
    $no++;
}

// 4. Output ke Browser
$filename = "Employee_Report_" . date('Ymd') . ".xlsx";

if (ob_get_length()) ob_end_clean();

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="'. $filename .'"');
header('Cache-Control: max-age=0');

$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
$writer->save('php://output');
exit();