<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Include PhpSpreadsheet library
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// Database connection
$servername = "localhost";
$username = "root";
$password = "Admin123";
$dbname = "trainingc";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    header('Location: upload.php?error=' . urlencode('Database connection failed'));
    exit;
}

$allowed_extensions = ['xlsx', 'csv'];
$upload_dir = 'uploads/';

if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_FILES['fileToUpload']) && $_FILES['fileToUpload']['error'] !== UPLOAD_ERR_NO_FILE) {
        
        $file_name = $_FILES['fileToUpload']['name'];
        $file_tmp = $_FILES['fileToUpload']['tmp_name'];
        $file_size = $_FILES['fileToUpload']['size'];
        $file_error = $_FILES['fileToUpload']['error'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        // Validation
        if ($file_size > 10 * 1024 * 1024) {
            header('Location: upload.php?error=' . urlencode('File size exceeds 10MB limit'));
            exit;
        }

        if (!in_array($file_ext, $allowed_extensions)) {
            header('Location: upload.php?error=' . urlencode('Only .xlsx and .csv files are allowed'));
            exit;
        }

        if ($file_error !== 0) {
            header('Location: upload.php?error=' . urlencode('File upload error'));
            exit;
        }

        // Save file
        $new_file_name = uniqid('', true) . '.' . $file_ext;
        $upload_path = $upload_dir . $new_file_name;

        if (move_uploaded_file($file_tmp, $upload_path)) {
            
            $uploaded_by = 'Admin User';
            $status = 'Processing';
            $rows_processed = 0;
            
            try {
                // Load the spreadsheet
                $spreadsheet = IOFactory::load($upload_path);
                $sheet = $spreadsheet->getActiveSheet();
                $data = $sheet->toArray(null, true, true, true);
                
                // Remove header row
                array_shift($data);
                
                $conn->begin_transaction();
                
                foreach ($data as $row) {
                    // Skip empty rows
                    if (empty($row['A']) || empty($row['B'])) continue;
                    
                    // Extract data from columns
                    $index_karyawan = $row['A'];
                    $nama_karyawan = $row['B'];
                    $nama_subject = $row['C'];
                    $date_start = $row['D'];
                    $date_end = $row['E'];
                    $credit_hour = $row['F'];
                    $place = $row['G'];
                    $method = $row['H'];
                    $class = $row['I'];
                    
                    // Scores (Decimals)
                    $satis_subject = $row['J'];
                    $satis_instructor = $row['K']; // This is the Score
                    $satis_infras = $row['L'];
                    
                    $pre_score = $row['M'];
                    $post_score = $row['N'];
                    $bu_name = $row['O'];
                    $func_n1 = $row['P'];
                    $func_n2 = $row['Q'];
                    $jenis = $row['R'];
                    
                    // 1. Insert or get BU
                    $stmt = $conn->prepare("SELECT id_bu FROM bu WHERE nama_bu = ?");
                    $stmt->bind_param("s", $bu_name);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows == 0) {
                        $stmt2 = $conn->prepare("INSERT INTO bu (nama_bu) VALUES (?)");
                        $stmt2->bind_param("s", $bu_name);
                        $stmt2->execute();
                        $id_bu = $conn->insert_id;
                        $stmt2->close();
                    } else {
                        $id_bu = $result->fetch_assoc()['id_bu'];
                    }
                    $stmt->close();
                    
                    // 2. Insert or get Function
                    $stmt = $conn->prepare("SELECT id_func FROM func WHERE func_n1 = ? AND func_n2 = ?");
                    $stmt->bind_param("ss", $func_n1, $func_n2);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows == 0) {
                        $stmt2 = $conn->prepare("INSERT INTO func (func_n1, func_n2) VALUES (?, ?)");
                        $stmt2->bind_param("ss", $func_n1, $func_n2);
                        $stmt2->execute();
                        $id_func = $conn->insert_id;
                        $stmt2->close();
                    } else {
                        $id_func = $result->fetch_assoc()['id_func'];
                    }
                    $stmt->close();
                    
                    // 3. Insert or get Karyawan
                    $stmt = $conn->prepare("SELECT id_karyawan FROM karyawan WHERE index_karyawan = ?");
                    $stmt->bind_param("i", $index_karyawan);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows == 0) {
                        $stmt2 = $conn->prepare("INSERT INTO karyawan (id_bu, id_func, index_karyawan, nama_karyawan) VALUES (?, ?, ?, ?)");
                        $stmt2->bind_param("iiis", $id_bu, $id_func, $index_karyawan, $nama_karyawan);
                        $stmt2->execute();
                        $id_karyawan = $conn->insert_id;
                        $stmt2->close();
                    } else {
                        $id_karyawan = $result->fetch_assoc()['id_karyawan'];
                    }
                    $stmt->close();
                    
                    // 4. Insert or get Training Subject
                    // NOTE: Removed 'instructor' column from INSERT as it is not in the DB schema anymore
                    $stmt = $conn->prepare("SELECT id_subject FROM training WHERE nama_subject = ? AND date_start = ?");
                    $stmt->bind_param("ss", $nama_subject, $date_start);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows == 0) {
                        $stmt2 = $conn->prepare("INSERT INTO training (id_bu, id_func, nama_subject, date_start, date_end, credit_hour, place, method, jenis) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt2->bind_param("iisssdsss", $id_bu, $id_func, $nama_subject, $date_start, $date_end, $credit_hour, $place, $method, $jenis);
                        $stmt2->execute();
                        $id_subject = $conn->insert_id;
                        $stmt2->close();
                    } else {
                        $id_subject = $result->fetch_assoc()['id_subject'];
                    }
                    $stmt->close();
                    
                    // 5. Insert Score
                    // NOTE: 'instructor' column here receives the score (decimal)
                    // Used 'd' (double) for satisfaction scores to preserve decimals
                    $stmt = $conn->prepare("INSERT INTO score (id_subject, id_karyawan, pre, post, statis_subject, instructor, statis_infras) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("iiiiddd", $id_subject, $id_karyawan, $pre_score, $post_score, $satis_subject, $satis_instructor, $satis_infras);
                    $stmt->execute();
                    $stmt->close();
                    
                    $rows_processed++;
                }
                
                $conn->commit();
                $status = 'Success';
                
            } catch (Exception $e) {
                $conn->rollback();
                $status = 'Failed';
                $error_msg = $e->getMessage();
            }
            
            // Update uploads table
            $stmt = $conn->prepare("INSERT INTO uploads (file_name, uploaded_by, status, rows_processed) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sssi", $file_name, $uploaded_by, $status, $rows_processed);
            $stmt->execute();
            $stmt->close();
            
            $conn->close();
            
            if ($status == 'Success') {
                header('Location: upload.php?success=' . urlencode('File uploaded and processed: ' . $rows_processed . ' rows'));
            } else {
                header('Location: upload.php?error=' . urlencode('Processing failed: ' . $error_msg));
            }
            exit;
            
        } else {
            $conn->close();
            header('Location: upload.php?error=' . urlencode('Failed to save file'));
            exit;
        }
    } else {
        $conn->close();
        header('Location: upload.php?error=' . urlencode('No file selected'));
        exit;
    }
} else {
    // If not POST, you might want to display the form here or redirect
    // For now, mirroring the logic to error out if accessed directly without POST
    // Or you can include the HTML form below this PHP block if it's a single file.
}
?>