<?php
require 'vendor/autoload.php'; // Autoload the Composer packages

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

// Database connection details
$servername = "localhost"; // Database server
$username = "root"; // Database username
$password = "Admin123"; // Database password
$dbname = "trainingc"; // Database name

// Create a connection to the database
$conn = new mysqli($servername, $username, $password, $dbname);

// Check if the connection was successful
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set the allowed file extensions and the upload directory
$allowed_extensions = ['xlsx', 'csv'];
$upload_dir = 'uploads/'; // Make sure this directory is writable

// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Check if a file is uploaded
    if (isset($_FILES['fileToUpload'])) {

        $file_name = $_FILES['fileToUpload']['name'];
        $file_tmp = $_FILES['fileToUpload']['tmp_name'];
        $file_size = $_FILES['fileToUpload']['size'];
        $file_error = $_FILES['fileToUpload']['error'];

        // Extract file extension
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        // Validate file size (max 10MB)
        if ($file_size > 10 * 1024 * 1024) {
            echo "File size exceeds the maximum limit of 10MB.";
            exit;
        }

        // Check if the file extension is allowed
        if (!in_array($file_ext, $allowed_extensions)) {
            echo "Invalid file type. Only .xlsx and .csv files are allowed.";
            exit;
        }

        // Check for any upload errors
        if ($file_error !== 0) {
            echo "Error uploading file.";
            exit;
        }

        // Generate a unique name for the file
        $new_file_name = uniqid('', true) . '.' . $file_ext;

        // Move the uploaded file to the designated directory
        if (move_uploaded_file($file_tmp, $upload_dir . $new_file_name)) {
            echo "File uploaded successfully.";

            // Process the Excel file using PhpSpreadsheet
            $spreadsheet = IOFactory::load($upload_dir . $new_file_name);
            $sheet = $spreadsheet->getActiveSheet();
            $data = $sheet->toArray(null, true, true, true); // Convert to an array

            // Loop through the data and insert each row into the database
            foreach ($data as $index => $row) {
                // Skip the header row
                if ($index == 1) continue;

                $index = $row['A']; // Assuming 'A' column is INDEX
                $name = $row['B']; // 'B' column is NAME
                $subject = $row['C']; // 'C' column is SUBJECT
                $date_start = $row['D']; // 'D' column is DATE_START
                $date_end = $row['E']; // 'E' column is DATE_END
                $credit_hol = $row['F']; // 'F' column is CREDIT_HOL
                $place = $row['G']; // 'G' column is PLACE
                $method = $row['H']; // 'H' column is METH
                $class = $row['I']; // 'I' column is CLASS
                $sat_subj = $row['J']; // 'J' column is SATIS_SUBJ
                $sat_instr = $row['K']; // 'K' column is SATIS_INSTRUCT
                $sat_inf = $row['L']; // 'L' column is SATIS_INF
                $prescor = $row['M']; // 'M' column is PRESCOR
                $postcor = $row['N']; // 'N' column is POSTSCOR
                $bu = $row['O']; // 'O' column is BU
                $func = $row['P']; // 'P' column is FUNC
                $func_n2 = $row['Q']; // 'Q' column is FUNC_N2
                $jenis = $row['R']; // 'R' column is JENIS

                // Prepare and insert the data into the database
                $stmt = $conn->prepare("INSERT INTO training_data (index_num, name, subject, date_start, date_end, credit_hol, place, method, class, sat_subj, sat_instr, sat_inf, prescor, postcor, bu, func, func_n2, jenis) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssssssssssssss", $index, $name, $subject, $date_start, $date_end, $credit_hol, $place, $method, $class, $sat_subj, $sat_instr, $sat_inf, $prescor, $postcor, $bu, $func, $func_n2, $jenis);

                // Ensure id_bu exists in bu table
$stmt = $conn->prepare("SELECT id_bu FROM bu WHERE nama_bu = ?");
$stmt->bind_param("s", $bu_name);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    $stmt_insert_bu = $conn->prepare("INSERT INTO bu (nama_bu) VALUES (?)");
    $stmt_insert_bu->bind_param("s", $bu_name);
    $stmt_insert_bu->execute();
    $id_bu = $conn->insert_id; // Get the inserted id_bu
} else {
    $row = $result->fetch_assoc();
    $id_bu = $row['id_bu'];
}

// Ensure id_func exists in func table
$stmt = $conn->prepare("SELECT id_func FROM func WHERE func_n1 = ?");
$stmt->bind_param("s", $func_name);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    $stmt_insert_func = $conn->prepare("INSERT INTO func (func_n1) VALUES (?)");
    $stmt_insert_func->bind_param("s", $func_name);
    $stmt_insert_func->execute();
    $id_func = $conn->insert_id; // Get the inserted id_func
} else {
    $row = $result->fetch_assoc();
    $id_func = $row['id_func'];
}

// Insert data into karyawan and training tables
// Insert into karyawan
$stmt_karyawan = $conn->prepare("INSERT INTO karyawan (id_bu, id_func, index_karyawan, nama_karyawan) VALUES (?, ?, ?, ?)");
$stmt_karyawan->bind_param("iiss", $id_bu, $id_func, $index, $name);
$stmt_karyawan->execute();

// Insert into training
$stmt_training = $conn->prepare("INSERT INTO training (id_subject, id_bu, id_func, nama_subject, date_start, date_end, credit_hour, place, method, jenis) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt_training->bind_param("iissssssss", $subject_id, $id_bu, $id_func, $subject_name, $date_start, $date_end, $credit_hour, $place, $method, $jenis);
$stmt_training->execute();

                if ($stmt->execute()) {
                    echo "Row inserted successfully.<br>";
                } else {
                    echo "Error inserting row: " . $stmt->error . "<br>";
                }
                $stmt->close();
            }
        } else {
            echo "Error moving the file.";
        }
    } else {
        echo "No file uploaded.";
    }
} else {
    echo "Invalid request method.";
}

// Close the database connection
$conn->close();
?>
