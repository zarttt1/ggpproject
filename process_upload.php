<?php
// process_upload.php

// DB CONFIGURATION
$host = 'localhost';
$db   = 'trainingc';
$user = 'root';
$pass = 'Admin123';
$charset = 'utf8mb4';

// Helper function to redirect with message
function redirect($type, $message) {
    // Redirect back to upload.php with query params
    header("Location: upload.php?" . $type . "=" . urlencode($message));
    exit();
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=$charset", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) { 
    redirect('error', "Connection Failed: " . $e->getMessage());
}

function detectDelimiter($file) {
    $handle = fopen($file, "r");
    $line = fgets($handle);
    fclose($handle);
    // Determine delimiter by counting occurrences
    return (substr_count($line, ';') > substr_count($line, ',')) ? ';' : ',';
}

// Safely convert CSV numbers to MySQL Float
function cleanFloat($val) {
    $val = trim($val ?? '');
    if ($val === '') return null;           // Handle empty
    $val = str_replace(',', '.', $val);     // Handle 3,5 -> 3.5
    return is_numeric($val) ? $val : null;  // Return number or null
}

// MAIN PROCESSING LOGIC
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Check if file was uploaded without errors
    if (!isset($_FILES['fileToUpload']) || $_FILES['fileToUpload']['error'] !== UPLOAD_ERR_OK) {
        redirect('error', "No file uploaded or upload error occurred.");
    }

    $startTime = microtime(true);
    $file = $_FILES['fileToUpload']['tmp_name'];
    $originalFileName = $_FILES['fileToUpload']['name']; // For logging purposes
    
    // Validate File Type (Simple Check)
    $ext = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
    if($ext !== 'csv') {
        redirect('error', "Only CSV files are currently supported by this processor.");
    }

    $delimiter = detectDelimiter($file);

    try {
        if (($handle = fopen($file, "r")) !== FALSE) {
            $pdo->beginTransaction();
            
            // Skip Header
            fgetcsv($handle, 0, $delimiter);

            $rowCount = 0;

            // PREPARE STATEMENTS
            $stmtCheckBU = $pdo->prepare("SELECT id_bu FROM bu WHERE nama_bu = ? LIMIT 1");
            $stmtInsBU   = $pdo->prepare("INSERT INTO bu (nama_bu) VALUES (?)");

            $stmtCheckFunc = $pdo->prepare("SELECT id_func FROM func WHERE func_n1 = ? AND func_n2 = ? AND func_n3 = ? LIMIT 1");
            $stmtInsFunc   = $pdo->prepare("INSERT INTO func (func_n1, func_n2, func_n3) VALUES (?, ?, ?)");

            $stmtCheckKar = $pdo->prepare("SELECT id_karyawan FROM karyawan WHERE index_karyawan = ? LIMIT 1");
            $stmtInsKar   = $pdo->prepare("INSERT INTO karyawan (index_karyawan, nama_karyawan) VALUES (?, ?)");

            $stmtCheckTrain = $pdo->prepare("SELECT id_training FROM training WHERE nama_training = ? LIMIT 1");
            $stmtInsTrain   = $pdo->prepare("INSERT INTO training (nama_training, jenis) VALUES (?, ?)");

            $stmtCheckSess = $pdo->prepare("SELECT id_session FROM training_session WHERE id_training = ? AND class = ? AND date_start = ? LIMIT 1");
            $stmtInsSess   = $pdo->prepare("INSERT INTO training_session (id_training, class, date_start, date_end, credit_hour, place, method) VALUES (?, ?, ?, ?, ?, ?, ?)");

            $stmtCheckScore = $pdo->prepare("SELECT id_score FROM score WHERE id_session = ? AND id_karyawan = ? LIMIT 1");
            $stmtInsScore   = $pdo->prepare("INSERT INTO score (id_session, id_karyawan, id_bu, id_func, pre, post, statis_subject, instructor, statis_infras) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

            while (($data = fgetcsv($handle, 10000, $delimiter)) !== FALSE) {
                
                // 1. EXTRACT RAW DATA
                $indeks     = trim($data[0] ?? '');
                $nama       = trim($data[1] ?? '');
                $subject    = trim($data[2] ?? '');
                $date_start = trim($data[3] ?? '');
                $date_end   = trim($data[4] ?? '');
                // SANITIZE NUMBERS HERE
                $credit     = cleanFloat($data[5] ?? ''); 
                
                $place      = trim($data[6] ?? '');
                $method     = trim($data[7] ?? '');
                $class      = trim($data[8] ?? '');
                
                // SANITIZE SCORES
                $sat_sub    = cleanFloat($data[9] ?? '');
                $sat_ins    = cleanFloat($data[10] ?? '');
                $sat_inf    = cleanFloat($data[11] ?? '');
                $pre        = cleanFloat($data[12] ?? '');
                $post       = cleanFloat($data[13] ?? '');
                
                $nama_bu    = trim($data[14] ?? '');
                $func_n1    = trim($data[15] ?? '');
                $func_n2    = trim($data[16] ?? '');
                $jenis      = trim($data[17] ?? '');
                $func_n3    = trim($data[18] ?? '');

                if ($indeks == '' || $subject == '') continue;

                // --- LOGIC START ---

                // 2. BU
                $stmtCheckBU->execute([$nama_bu]);
                $id_bu = $stmtCheckBU->fetchColumn();
                if (!$id_bu) {
                    $stmtInsBU->execute([$nama_bu]);
                    $id_bu = $pdo->lastInsertId();
                }

                // 3. FUNC
                $stmtCheckFunc->execute([$func_n1, $func_n2, $func_n3]);
                $id_func = $stmtCheckFunc->fetchColumn();
                if (!$id_func) {
                    $stmtInsFunc->execute([$func_n1, $func_n2, $func_n3]);
                    $id_func = $pdo->lastInsertId();
                }

                // 4. KARYAWAN (ID Only Check)
                $stmtCheckKar->execute([$indeks]);
                $id_karyawan = $stmtCheckKar->fetchColumn();
                if (!$id_karyawan) {
                    $stmtInsKar->execute([$indeks, $nama]);
                    $id_karyawan = $pdo->lastInsertId();
                }

                // 5. TRAINING
                $stmtCheckTrain->execute([$subject]);
                $id_training = $stmtCheckTrain->fetchColumn();
                if (!$id_training) {
                    $stmtInsTrain->execute([$subject, $jenis]);
                    $id_training = $pdo->lastInsertId();
                }

                // 6. SESSION
                $d_start = (!empty($date_start)) ? date('Y-m-d', strtotime(str_replace('/', '-', $date_start))) : null;
                $d_end   = (!empty($date_end))   ? date('Y-m-d', strtotime(str_replace('/', '-', $date_end)))   : null;

                $stmtCheckSess->execute([$id_training, $class, $d_start]);
                $id_session = $stmtCheckSess->fetchColumn();
                if (!$id_session) {
                    // $credit is now safe (cleaned float or null)
                    $stmtInsSess->execute([$id_training, $class, $d_start, $d_end, $credit, $place, $method]);
                    $id_session = $pdo->lastInsertId();
                }

                // 7. SCORE
                $stmtCheckScore->execute([$id_session, $id_karyawan]);
                if (!$stmtCheckScore->fetchColumn()) {
                    $stmtInsScore->execute([
                        $id_session, $id_karyawan, $id_bu, $id_func,
                        $pre, $post, $sat_sub, $sat_ins, $sat_inf
                    ]);
                    $rowCount++;
                }
            }
            
            // 8. LOG UPLOAD TO DB (For the table in your UI)
            $stmtLogUpload = $pdo->prepare("INSERT INTO uploads (file_name, uploaded_by, status, rows_processed) VALUES (?, ?, ?, ?)");
            $stmtLogUpload->execute([$originalFileName, 'Admin', 'Success', $rowCount]); // Replace 'Admin' with session user if available

            $pdo->commit();
            fclose($handle);
            
            $time = round(microtime(true) - $startTime, 2);
            redirect('success', "Processed $rowCount rows in $time seconds.");
            
        } else {
            throw new Exception("Could not open file.");
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        
        // Log failed upload attempt
        try {
            $stmtLogFail = $pdo->prepare("INSERT INTO uploads (file_name, uploaded_by, status, rows_processed) VALUES (?, ?, ?, ?)");
            $stmtLogFail->execute([$originalFileName, 'Admin', 'Failed', 0]);
        } catch(Exception $logEx) { /* Ignore logging error */ }

        redirect('error', "Error: " . $e->getMessage());
    }
} else {
    redirect('warning', "Invalid request method.");
}
?>