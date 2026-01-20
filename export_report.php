<?php
// Pastikan tidak ada spasi/baris kosong sebelum <?php
require 'vendor/autoload.php';
require 'db_connect.php';
session_start();

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Border;

if (!isset($_GET['id'])) {
    die("ID Sesi tidak ditemukan.");
}

$id_session = (int)$_GET['id'];

// Ambil Metadata Sesi & Training (tambahkan t.code)
$meta_sql = "
    SELECT t.nama_training, ts.code_sub, ts.date_start
    FROM training_session ts 
    JOIN training t ON ts.id_training = t.id_training 
    WHERE ts.id_session = $id_session
";
$meta_res = $conn->query($meta_sql);
$meta = $meta_res->fetch_assoc();

if (!$meta) { die("Data sesi tidak ditemukan."); }

// 2. Ambil Rata-rata Feedback
$feedback_sql = "
    SELECT AVG(statis_subject) as avg_sub, AVG(instructor) as avg_inst, AVG(statis_infras) as avg_inf, COUNT(id_score) as total_p
    FROM score WHERE id_session = $id_session
";
$fb = $conn->query($feedback_sql)->fetch_assoc();

// 3. Ambil Data Peserta
$list_sql = "
    SELECT k.index_karyawan, k.nama_karyawan, f.func_n2, s.pre, s.post
    FROM score s
    JOIN karyawan k ON s.id_karyawan = k.id_karyawan
    LEFT JOIN func f ON s.id_func = f.id_func
    WHERE s.id_session = $id_session
    ORDER BY k.nama_karyawan ASC
";
$participants = $conn->query($list_sql);

// --- PROSES EXCEL ---
$templateFile = __DIR__ . '/uploads/Training Reports.xlsx'; 
if (!file_exists($templateFile)) {
    die("File template tidak ditemukan di: " . $templateFile);
}

$spreadsheet = IOFactory::load($templateFile);
$sheet = $spreadsheet->getActiveSheet();

// 4. Isi Header
$sheet->setCellValue('C7', ': ' . $meta['nama_training']);
$sheet->setCellValue('C8', ': ' . date('d M Y', strtotime($meta['date_start'])));
$sheet->setCellValue('C9', ': ' . $fb['total_p'] . ' Orang');
$sheet->setCellValue('F7', ': ' . ($meta['code_sub']));

// 5. Isi Feedback (Logika IF diperbaiki menggunakan PHP)
$scores = [
    '12' => $fb['avg_sub'],
    '13' => $fb['avg_inst'],
    '14' => $fb['avg_inf']
];

foreach ($scores as $rowIdx => $scoreValue) {
    $val = (float)$scoreValue;
    $sheet->setCellValue('C' . $rowIdx, ': ' . number_format($val, 2));
    
    // Logika penentuan keterangan (BAIK, CUKUP, dll)
    if ($val >= 8.51) $ket = "SANGAT BAIK";
    elseif ($val >= 7.01) $ket = "BAIK";
    elseif ($val >= 5.01) $ket = "CUKUP";
    elseif ($val >= 3.01) $ket = "KURANG";
    else $ket = "SGT KURANG";
    
    $sheet->setCellValue('E' . $rowIdx, ': ' . $ket);
}

// 6. Isi Tabel Peserta (Mulai Baris 17)
$row = 17;
$no = 1;
while ($p = $participants->fetch_assoc()) {
    $sheet->setCellValue('A' . $row, $no);
    $sheet->setCellValue('B' . $row, $p['index_karyawan']);
    $sheet->setCellValue('C' . $row, $p['nama_karyawan']);
    $sheet->setCellValue('D' . $row, $p['func_n2']);
    $sheet->setCellValue('E' . $row, $p['pre']);
    $sheet->setCellValue('F' . $row, $p['post']);
    
    $status = ($p['post'] >= 75) ? "Lulus" : "Remedial";
    $sheet->setCellValue('G' . $row, $status);

    $sheet->getStyle("A$row:G$row")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    
    $row++;
    $no++;
}

// 7. Output
$filename = "Report_" . str_replace(' ', '_', $meta['nama_training']) . ".xlsx";

if (ob_get_length()) ob_end_clean();

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="'. $filename .'"');
header('Cache-Control: max-age=0');

$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
$writer->save('php://output');
exit();