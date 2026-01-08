<?php
session_start();
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

// 1. Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

// 2. DB Connection
$host = 'localhost';
$db   = 'trainingc';
$user = 'root';
$pass = 'Admin123';
$charset = 'utf8mb4';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=$charset", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    $_SESSION['upload_message'] = "Database connection failed: " . $e->getMessage();
    header("Location: upload.php?status=error");
    exit();
}

// Helper Functions
function detectDelimiter($file) {
    $handle = fopen($file, "r");
    $line = fgets($handle);
    fclose($handle);
    return (substr_count($line, ';') > substr_count($line, ',')) ? ';' : ',';
}

function cleanFloat($val) {
    $val = trim($val ?? '');
    if ($val === '') return null;
    $val = str_replace(',', '.', $val);
    return is_numeric($val) ? $val : null;
}

// 3. Process Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['fileToUpload'])) {
    
    $uploadDir = 'uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    
    $file = $_FILES['fileToUpload'];
    $fileName = basename($file['name']);
    $targetPath = $uploadDir . $fileName;
    $fileType = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));

    // Reset previous logs
    unset($_SESSION['upload_logs']); 
    unset($_SESSION['update_logs']); // New session variable for updates
    unset($_SESSION['upload_message']);

    if ($fileType != "csv" && $fileType != "xlsx") {
        $_SESSION['upload_message'] = "Only CSV and XLSX files are allowed.";
        header("Location: upload.php?status=error");
        exit();
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['upload_message'] = "Upload error occurred (Code: {$file['error']}).";
        header("Location: upload.php?status=error");
        exit();
    } elseif (move_uploaded_file($file['tmp_name'], $targetPath)) {
        
        $startTime = microtime(true);
        $debugLog = [];  // For Errors/Skips
        $updateLog = []; // For Successful Updates (Duplicates)

        try {
            $fileToRead = $targetPath;
            $isConverted = false;

            // Convert XLSX to CSV
            if ($fileType === 'xlsx') {
                $tempCsvFile = $uploadDir . 'temp_' . uniqid() . '.csv';
                $reader = IOFactory::createReader('Xlsx');
                $reader->setReadDataOnly(true);
                $spreadsheet = $reader->load($targetPath);
                $writer = IOFactory::createWriter($spreadsheet, 'Csv');
                $writer->setDelimiter(';');
                $writer->setEnclosure('"');
                $writer->setSheetIndex(0);
                $writer->save($tempCsvFile);
                $fileToRead = $tempCsvFile;
                $isConverted = true;
                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet, $reader, $writer);
            }

            $delimiter = detectDelimiter($fileToRead);

            if (($handle = fopen($fileToRead, "r")) !== FALSE) {
                
                $pdo->beginTransaction();
                
                // Skip header
                fgetcsv($handle, 0, $delimiter);
                
                $countInsert = 0;
                $countUpdate = 0;
                $skippedCount = 0;

                // PREPARE STATEMENTS
                $stmtCheckBU = $pdo->prepare("SELECT id_bu FROM bu WHERE nama_bu = ? LIMIT 1");
                $stmtInsBU   = $pdo->prepare("INSERT INTO bu (nama_bu) VALUES (?)");

                $stmtCheckFunc = $pdo->prepare("SELECT id_func FROM func WHERE func_n1 = ? AND func_n2 = ? LIMIT 1");
                $stmtInsFunc   = $pdo->prepare("INSERT INTO func (func_n1, func_n2) VALUES (?, ?)");

                $stmtCheckKar = $pdo->prepare("SELECT id_karyawan FROM karyawan WHERE index_karyawan = ? LIMIT 1");
                $stmtInsKar   = $pdo->prepare("INSERT INTO karyawan (index_karyawan, nama_karyawan) VALUES (?, ?)");

                $stmtCheckTrain = $pdo->prepare("SELECT id_training FROM training WHERE nama_training = ? LIMIT 1");
                $stmtInsTrain   = $pdo->prepare("INSERT INTO training (nama_training, jenis) VALUES (?, ?)");

                $stmtCheckSess = $pdo->prepare("SELECT id_session FROM training_session WHERE id_training = ? AND code_sub = ? AND date_start = ? LIMIT 1");
                $stmtInsSess   = $pdo->prepare("INSERT INTO training_session (id_training, code_sub, class, date_start, date_end, credit_hour, place, method) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

                $stmtCheckScore = $pdo->prepare("SELECT id_score FROM score WHERE id_session = ? AND id_karyawan = ? LIMIT 1");
                $stmtInsScore   = $pdo->prepare("INSERT INTO score (id_session, id_karyawan, id_bu, id_func, pre, post, statis_subject, instructor, statis_infras) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmtUpdScore   = $pdo->prepare("UPDATE score SET id_bu=?, id_func=?, pre=?, post=?, statis_subject=?, instructor=?, statis_infras=? WHERE id_score=?");

                $lineNumber = 1;

                while (($data = fgetcsv($handle, 10000, $delimiter)) !== FALSE) {
                    $lineNumber++;
                    
                    if (count($data) < 19) {
                        $debugLog[] = "Line $lineNumber: Skipped (Insufficient columns)";
                        $skippedCount++;
                        continue;
                    }

                    try {
                        // Data Mapping
                        $indeks     = trim($data[0] ?? '');
                        $nama       = trim($data[1] ?? '');
                        $subject    = trim($data[2] ?? '');
                        $code_sub   = trim($data[3] ?? '');
                        $date_start = trim($data[4] ?? '');
                        $date_end   = trim($data[5] ?? '');
                        $credit     = cleanFloat($data[6] ?? '');
                        $place      = trim($data[7] ?? '');
                        $method     = trim($data[8] ?? '');
                        $class      = trim($data[9] ?? '');
                        $sat_sub    = cleanFloat($data[10] ?? '');
                        $sat_ins    = cleanFloat($data[11] ?? '');
                        $sat_inf    = cleanFloat($data[12] ?? '');
                        $pre        = cleanFloat($data[13] ?? '');
                        $post       = cleanFloat($data[14] ?? '');
                        $nama_bu    = trim($data[15] ?? '');
                        $func_n1    = trim($data[16] ?? '');
                        $func_n2    = trim($data[17] ?? '');
                        $jenis      = trim($data[18] ?? '');

                        if ($indeks == '' || $subject == '') {
                            $debugLog[] = "Line $lineNumber: Skipped (Missing Index or Subject)";
                            $skippedCount++;
                            continue;
                        }

                        // 1. BU
                        $stmtCheckBU->execute([$nama_bu]);
                        $id_bu = $stmtCheckBU->fetchColumn();
                        if (!$id_bu) {
                            $stmtInsBU->execute([$nama_bu]);
                            $id_bu = $pdo->lastInsertId();
                        }

                        // 2. Func
                        $stmtCheckFunc->execute([$func_n1, $func_n2]);
                        $id_func = $stmtCheckFunc->fetchColumn();
                        if (!$id_func) {
                            $stmtInsFunc->execute([$func_n1, $func_n2]);
                            $id_func = $pdo->lastInsertId();
                        }

                        // 3. Karyawan
                        $stmtCheckKar->execute([$indeks]);
                        $id_karyawan = $stmtCheckKar->fetchColumn();
                        if (!$id_karyawan) {
                            $stmtInsKar->execute([$indeks, $nama]);
                            $id_karyawan = $pdo->lastInsertId();
                        }

                        // 4. Training
                        $stmtCheckTrain->execute([$subject]);
                        $id_training = $stmtCheckTrain->fetchColumn();
                        if (!$id_training) {
                            $stmtInsTrain->execute([$subject, $jenis]);
                            $id_training = $pdo->lastInsertId();
                        }

                        // 5. Session
                        $d_start = null; $d_end = null;
                        if (!empty($date_start)) {
                            $d_start = (is_numeric($date_start)) 
                                ? ExcelDate::excelToDateTimeObject($date_start)->format('Y-m-d') 
                                : date('Y-m-d', strtotime(str_replace('/', '-', $date_start)));
                        } else {
                            $debugLog[] = "Line $lineNumber: Skipped (Missing Date)";
                            $skippedCount++;
                            continue;
                        }
                        
                        if (!empty($date_end)) {
                            $d_end = (is_numeric($date_end)) 
                                ? ExcelDate::excelToDateTimeObject($date_end)->format('Y-m-d') 
                                : date('Y-m-d', strtotime(str_replace('/', '-', $date_end)));
                        } else {
                            $d_end = $d_start;
                        }

                        $stmtCheckSess->execute([$id_training, $code_sub, $d_start]);
                        $id_session = $stmtCheckSess->fetchColumn();
                        if (!$id_session) {
                            $stmtInsSess->execute([$id_training, $code_sub, $class, $d_start, $d_end, $credit, $place, $method]);
                            $id_session = $pdo->lastInsertId();
                        }

                        // 6. Score (Update vs Insert)
                        $stmtCheckScore->execute([$id_session, $id_karyawan]);
                        $existing_score_id = $stmtCheckScore->fetchColumn();

                        if ($existing_score_id) {
                            // UPDATE
                            $stmtUpdScore->execute([$id_bu, $id_func, $pre, $post, $sat_sub, $sat_ins, $sat_inf, $existing_score_id]);
                            $countUpdate++;
                            // Add to Update Log (User requested feature)
                            $updateLog[] = "Line $lineNumber: $nama - $subject (Score Updated)";
                        } else {
                            // INSERT
                            $stmtInsScore->execute([$id_session, $id_karyawan, $id_bu, $id_func, $pre, $post, $sat_sub, $sat_ins, $sat_inf]);
                            $countInsert++;
                        }

                    } catch (Exception $e) {
                        $debugLog[] = "Line $lineNumber: Error - " . $e->getMessage();
                        $skippedCount++;
                    }
                }
                
                fclose($handle);
                if ($isConverted && file_exists($fileToRead)) unlink($fileToRead);

                // Log to Database
                $totalProcessed = $countInsert + $countUpdate;
                $user_name = $_SESSION['username'] ?? 'Admin';
                $status = empty($debugLog) ? 'Success' : 'Partial Success';
                
                $stmtLogUpload = $pdo->prepare("INSERT INTO uploads (file_name, uploaded_by, status, rows_processed) VALUES (?, ?, ?, ?)");
                $stmtLogUpload->execute([$fileName, $user_name, $status, $totalProcessed]);

                $pdo->commit();
                
                $time = round(microtime(true) - $startTime, 2);
                
                // Store results in Session
                $_SESSION['upload_message'] = "Processing Complete ($time s). <b>Added:</b> $countInsert new. <b>Updated:</b> $countUpdate existing.";
                
                // Save logs if they exist
                if (!empty($debugLog)) $_SESSION['upload_logs'] = $debugLog;
                if (!empty($updateLog)) $_SESSION['update_logs'] = $updateLog;

                // Status logic: Warning if errors exist, otherwise Success
                if (!empty($debugLog)) {
                    header("Location: upload.php?status=warning");
                } else {
                    header("Location: upload.php?status=success");
                }
                exit();

            } else {
                throw new Exception("Could not open file.");
            }

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if (isset($isConverted) && $isConverted && file_exists($fileToRead)) unlink($fileToRead);
            
            $_SESSION['upload_message'] = "Critical Error: " . $e->getMessage();
            header("Location: upload.php?status=error");
            exit();
        }
    }
}
?>