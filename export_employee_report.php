<?php
require 'vendor/autoload.php';
require 'db_connect.php';
session_start();

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

if (!isset($_GET['id_karyawan'])) {
    die("ID Karyawan tidak ditemukan.");
}

$id_karyawan = (int)$_GET['id_karyawan'];

/**
 * 1. Ambil Data Profil Karyawan dari tabel Score
 * Kita mengambil baris terakhir dari tabel score untuk karyawan tersebut
 * agar mendapatkan id_func dan id_bu yang terbaru/aktif di sistem.
 */
$user_sql = "
    SELECT 
        k.index_karyawan, 
        k.nama_karyawan, 
        b.nama_bu, 
        f.func_n1, 
        f.func_n2 
    FROM score s
    JOIN karyawan k ON s.id_karyawan = k.id_karyawan
    LEFT JOIN func f ON s.id_func = f.id_func
    LEFT JOIN bu b ON s.id_bu = b.id_bu
    WHERE s.id_karyawan = $id_karyawan
    ORDER BY s.id_score DESC 
    LIMIT 1
";

$user_res = $conn->query($user_sql);
if (!$user_res) {
    die("Error Database Profil: " . $conn->error);
}
$user = $user_res->fetch_assoc();

if (!$user) { 
    die("Data karyawan tidak ditemukan di riwayat training (tabel score)."); 
}

// 2. Ambil Semua Riwayat Training
$history_sql = "
    SELECT t.nama_training, ts.date_start, t.type, ts.method, s.pre, s.post 
    FROM score s
    JOIN training_session ts ON s.id_session = ts.id_session
    JOIN training t ON ts.id_training = t.id_training
    WHERE s.id_karyawan = $id_karyawan
    ORDER BY ts.date_start DESC
";
$history_res = $conn->query($history_sql);

// --- PROSES EXCEL ---
$templatePath = __DIR__ . '/uploads/Employee Reports.xlsx'; 
if (!file_exists($templatePath)) {
    die("File template tidak ditemukan!");
}

$spreadsheet = IOFactory::load($templatePath);
$sheet = $spreadsheet->getActiveSheet();

// 3. Mengisi Data Profil (C7 - C11) sesuai template yang anda kirim
$sheet->setCellValue('C7', ': ' . $user['index_karyawan']);
$sheet->setCellValue('C8', ': ' . $user['nama_karyawan']);
$sheet->setCellValue('C9', ': ' . ($user['nama_bu'] ?? '-')); // Ini akan mengambil NAMA BU yang benar
$sheet->setCellValue('C10', ': ' . ($user['func_n1'] ?? '-'));
$sheet->setCellValue('C11', ': ' . ($user['func_n2'] ?? '-'));

// 4. Mengisi Tabel Riwayat Training (Mulai baris 14)
$row = 14;
$no = 1;

if ($history_res && $history_res->num_rows > 0) {
    while ($h = $history_res->fetch_assoc()) {
        $sheet->setCellValue('A' . $row, $no);
        $sheet->setCellValue('B' . $row, $h['nama_training']);
        $sheet->setCellValue('C' . $row, date('d-M-Y', strtotime($h['date_start'])));
        $sheet->setCellValue('D' . $row, $h['type'] ?? 'Internal');
        $sheet->setCellValue('E' . $row, $h['method'] ?? 'Offline');
        $sheet->setCellValue('F' . $row, $h['pre']);
        $sheet->setCellValue('G' . $row, $h['post']);

        // Styling Border
        $sheet->getStyle("A$row:G$row")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        
        // Alignment
        $sheet->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("C$row:G$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $row++;
        $no++;
    }
}

// 5. Download
$clean_name = str_replace(' ', '_', $user['nama_karyawan']);
$filename = "Report_Training_" . $clean_name . ".xlsx";

if (ob_get_length()) ob_end_clean();

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="'. $filename .'"');
header('Cache-Control: max-age=0');

$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
$writer->save('php://output');
exit();