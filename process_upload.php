<?php
session_start();
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

// 1. Configuration
ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('memory_limit', '1024M'); // Bumped for bulk processing
set_time_limit(600); 

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$host = 'localhost';
$db   = 'trainingc';
$user = 'root';
$pass = 'Admin123';
$charset = 'utf8mb4';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=$charset", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false, // Important for integer mapping
    ]);
} catch (PDOException $e) {
    die("DB Connection failed: " . $e->getMessage());
}

// --- Helper Functions ---
function detectDelimiter($file) {
    $handle = fopen($file, "r");
    if ($handle) {
        $line = fgets($handle);
        fclose($handle);
        return (substr_count($line, ';') > substr_count($line, ',')) ? ';' : ',';
    }
    return ',';
}

function parseDate($raw) {
    if (empty($raw)) return null;
    if (is_numeric($raw)) {
        // Excel serial date
        return ExcelDate::excelToDateTimeObject($raw)->format('Y-m-d');
    }
    // String date
    $ts = strtotime(str_replace('/', '-', $raw));
    return $ts ? date('Y-m-d', $ts) : null;
}

function cleanFloat($val) {
    if ($val === '' || $val === null) return null;
    $val = str_replace(',', '.', $val);
    return is_numeric($val) ? (float)$val : 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['fileToUpload'])) {
    
    $uploadDir = 'uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    
    $file = $_FILES['fileToUpload'];
    $targetPath = $uploadDir . basename($file['name']);
    $fileType = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));

    // Reset logs
    $debugLog = [];
    $stats = ['inserted' => 0, 'updated' => 0, 'skipped' => 0];

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        $startTime = microtime(true);
        $fileToRead = $targetPath;
        $isConverted = false;

        try {
            // Convert XLSX if needed
            if ($fileType === 'xlsx') {
                $tempCsvFile = $uploadDir . 'temp_' . uniqid() . '.csv';
                $reader = IOFactory::createReader('Xlsx');
                $reader->setReadDataOnly(true);
                $spreadsheet = $reader->load($targetPath);
                $writer = IOFactory::createWriter($spreadsheet, 'Csv');
                $writer->setDelimiter(';');
                $writer->setEnclosure('"');
                $writer->save($tempCsvFile);
                $fileToRead = $tempCsvFile;
                $isConverted = true;
                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet, $reader, $writer);
            }

            $delimiter = detectDelimiter($fileToRead);
            $handle = fopen($fileToRead, "r");
            if (!$handle) throw new Exception("Cannot open file");

            $pdo->beginTransaction();

            // --- 1. Load Caches (Reference Arrays) ---
            $buCache = $pdo->query("SELECT nama_bu, id_bu FROM bu")->fetchAll(PDO::FETCH_KEY_PAIR);
            $karCache = $pdo->query("SELECT index_karyawan, id_karyawan FROM karyawan")->fetchAll(PDO::FETCH_KEY_PAIR);
            $trainCache = $pdo->query("SELECT nama_training, id_training FROM training")->fetchAll(PDO::FETCH_KEY_PAIR);
            
            // Complex caches
            $funcCache = [];
            foreach ($pdo->query("SELECT id_func, func_n1, func_n2 FROM func") as $r) {
                $funcCache[$r['func_n1'] . '|' . $r['func_n2']] = $r['id_func'];
            }

            $sessionCache = [];
            foreach ($pdo->query("SELECT id_session, id_training, code_sub, date_start FROM training_session") as $r) {
                $sessionCache[$r['id_training'] . '|' . $r['code_sub'] . '|' . $r['date_start']] = $r['id_session'];
            }

            // --- 2. Prepared Statements for Dimensions (Single Insert) ---
            // We still do single inserts for dimensions because they are rare/low volume compared to scores
            $stmtInsBU = $pdo->prepare("INSERT INTO bu (nama_bu) VALUES (?)");
            $stmtInsFunc = $pdo->prepare("INSERT INTO func (func_n1, func_n2) VALUES (?, ?)");
            $stmtInsKar = $pdo->prepare("INSERT INTO karyawan (index_karyawan, nama_karyawan) VALUES (?, ?)");
            $stmtInsTrain = $pdo->prepare("INSERT INTO training (nama_training, jenis, type) VALUES (?, ?, ?)");
            $stmtInsSess = $pdo->prepare("INSERT INTO training_session (id_training, code_sub, class, date_start, date_end, credit_hour, place, method) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

            // --- 3. BATCH SETTINGS ---
            $batchSize = 500; // Optimal size for MySQL
            $batchQueue = []; // Holds the rows for bulk insert
            $rowCounter = 0;

            fgetcsv($handle, 0, $delimiter); // Skip Header

            while (($data = fgetcsv($handle, 10000, $delimiter)) !== FALSE) {
                $rowCounter++;
                if (count($data) < 18) {
                    $stats['skipped']++; continue;
                }

                // Parse standard fields
                $indeks = trim($data[0] ?? '');
                $subject = trim($data[2] ?? '');
                if ($indeks === '' || $subject === '') { $stats['skipped']++; continue; }

                // --- Handle Dimensions (Check Cache -> Insert -> Update Cache) ---
                
                // BU
                $nama_bu = trim($data[16] ?? '');
                if (!isset($buCache[$nama_bu])) {
                    $stmtInsBU->execute([$nama_bu]);
                    $buCache[$nama_bu] = $pdo->lastInsertId();
                }
                $id_bu = $buCache[$nama_bu];

                // Func
                $f1 = trim($data[17] ?? ''); 
                $f2 = trim($data[18] ?? '');
                $funcKey = $f1 . '|' . $f2;
                if (!isset($funcCache[$funcKey])) {
                    $stmtInsFunc->execute([$f1, $f2]);
                    $funcCache[$funcKey] = $pdo->lastInsertId();
                }
                $id_func = $funcCache[$funcKey];

                // Karyawan
                if (!isset($karCache[$indeks])) {
                    $stmtInsKar->execute([$indeks, trim($data[1] ?? '')]);
                    $karCache[$indeks] = $pdo->lastInsertId();
                }
                $id_karyawan = $karCache[$indeks];

                // Training
                if (!isset($trainCache[$subject])) {
                    $stmtInsTrain->execute([$subject, trim($data[19] ?? ''), trim($data[8] ?? '')]);
                    $trainCache[$subject] = $pdo->lastInsertId();
                }
                $id_training = $trainCache[$subject];

                // Session
                $code_sub = trim($data[3] ?? '');
                $date_start = parseDate(trim($data[4] ?? ''));
                if (!$date_start) { $stats['skipped']++; continue; }
                
                $sessKey = $id_training . '|' . $code_sub . '|' . $date_start;
                if (!isset($sessionCache[$sessKey])) {
                    $date_end = parseDate(trim($data[5] ?? '')) ?: $date_start;
                    $stmtInsSess->execute([
                        $id_training, $code_sub, trim($data[10] ?? ''), $date_start, $date_end, 
                        cleanFloat($data[6]), trim($data[7]), trim($data[9])
                    ]);
                    $sessionCache[$sessKey] = $pdo->lastInsertId();
                }
                $id_session = $sessionCache[$sessKey];

                // --- ADD TO BATCH QUEUE ---
                // We do NOT execute SQL here. We save the data.
                $batchQueue[] = [
                    $id_session, $id_karyawan, $id_bu, $id_func,
                    cleanFloat($data[14]), // pre
                    cleanFloat($data[15]), // post
                    cleanFloat($data[11]), // sat_sub
                    cleanFloat($data[12]), // instructor
                    cleanFloat($data[13])  // infras
                ];

                // If batch full, execute
                if (count($batchQueue) >= $batchSize) {
                    processBatch($pdo, $batchQueue);
                    $stats['inserted'] += count($batchQueue); // Rough count, actual split between ins/upd handled by DB
                    $batchQueue = [];
                }
            }

            // Process remaining rows
            if (!empty($batchQueue)) {
                processBatch($pdo, $batchQueue);
                $stats['inserted'] += count($batchQueue);
            }

            $pdo->commit();
            fclose($handle);
            if ($isConverted && file_exists($fileToRead)) unlink($fileToRead);

            $time = number_format(microtime(true) - $startTime, 2);
            $_SESSION['upload_message'] = "Processed in <b>{$time}s</b>. Rows handled: {$stats['inserted']}. (Skipped: {$stats['skipped']})";
            header("Location: upload.php?status=success");

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if (isset($isConverted) && $isConverted && file_exists($fileToRead)) unlink($fileToRead);
            $_SESSION['upload_message'] = "Error: " . $e->getMessage();
            header("Location: upload.php?status=error");
        }
    }
}

/**
 * Executes a Bulk Insert with On Duplicate Key Update
 */
function processBatch($pdo, $rows) {
    if (empty($rows)) return;

    // 1. Build placeholders (?,?,?,...), (?,?,?,...)
    $rowPlaces = '(' . implode(',', array_fill(0, 9, '?')) . ')';
    $allPlaces = implode(',', array_fill(0, count($rows), $rowPlaces));

    // 2. The SQL
    // NOTE: This assumes a UNIQUE INDEX exists on score(id_session, id_karyawan)
    $sql = "INSERT INTO score (id_session, id_karyawan, id_bu, id_func, pre, post, statis_subject, instructor, statis_infras) 
            VALUES $allPlaces 
            ON DUPLICATE KEY UPDATE 
            id_bu = VALUES(id_bu), 
            id_func = VALUES(id_func), 
            pre = VALUES(pre), 
            post = VALUES(post), 
            statis_subject = VALUES(statis_subject), 
            instructor = VALUES(instructor), 
            statis_infras = VALUES(statis_infras)";

    // 3. Flatten array for binding
    $flatData = [];
    foreach ($rows as $row) {
        foreach ($row as $cell) $flatData[] = $cell;
    }

    // 4. Execute
    $stmt = $pdo->prepare($sql);
    $stmt->execute($flatData);
}
?>