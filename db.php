<?php
// Memasukkan autoload untuk PHPSpreadsheet (Pastikan Anda sudah install library PhpSpreadsheet via Composer)
require 'vendor/autoload.php';

// Konfigurasi Database
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "your_database_name"; // Ganti dengan nama database Anda

// Membuat koneksi ke database
$conn = new mysqli($servername, $username, $password, $dbname);

// Mengecek koneksi
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (isset($_POST['submit'])) {
    // Mendapatkan file yang diupload
    $file = $_FILES['file']['tmp_name'];
    
    // Memeriksa apakah file terupload
    if ($file) {
        // Membaca file Excel menggunakan PHPSpreadsheet
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
        $sheet = $spreadsheet->getActiveSheet();
        
        // Mengambil data dari sheet yang pertama
        $data = $sheet->toArray();

        // Memproses data dan memasukkannya ke dalam database
        foreach ($data as $row) {
            // Mapping kolom dari file ke kolom di tabel database
            $indeks = $row[0];  // INDEKS
            $name = $row[1];     // NAME
            $subject = $row[2];  // SUBJECT
            $date_start = $row[3]; // DATE START
            $date_end = $row[4];   // DATE END
            $credit_hours = $row[5]; // CREDIT HOURS
            $place = $row[6];    // PLACE
            $method = $row[7];   // METHOD
            $class = $row[8];    // CLASS
            $satis_subject = $row[9]; // SATIS SUBJECT
            $satis_infras = $row[10]; // SATIS INFRAS
            $satis_instructor = $row[11]; // SATIS INSTRUCTOR
            $pre_score = $row[12]; // PRE SCORE
            $post_score = $row[13]; // POST SCORE
            $bu = $row[14];  // BU
            $func = $row[15];  // FUNC
            $func_n2 = $row[16];  // FUNC N2
            $jenis = $row[17];  // JENIS

            // Menyimpan data karyawan ke dalam tabel Karyawan
            $stmt_karyawan = $conn->prepare("INSERT INTO Karyawan (id_karyawan, name, id_bu, id_func) VALUES (?, ?, ?, ?)");
            $stmt_karyawan->bind_param("issi", $indeks, $name, $bu, $func);
            $stmt_karyawan->execute();

            // Menyimpan data pelatihan ke dalam tabel Training
            $stmt_training = $conn->prepare("INSERT INTO Training (subject, date_start, date_end, credit_hour, place, method, jenis) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt_training->bind_param("sssdsss", $subject, $date_start, $date_end, $credit_hours, $place, $method, $jenis);
            $stmt_training->execute();

            // Menyimpan data nilai ke dalam tabel Score
            $stmt_score = $conn->prepare("INSERT INTO Score (id_subject, id_karyawan, pre, post, statis_subject, statis_infras, statis_instructor) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt_score->bind_param("iiidddd", $subject_id, $indeks, $pre_score, $post_score, $satis_subject, $satis_infras, $satis_instructor);
            $stmt_score->execute();
        }

        echo "Data has been successfully uploaded and processed!";
    } else {
        echo "Please upload a valid Excel file.";
    }
}

$conn->close();
?>
