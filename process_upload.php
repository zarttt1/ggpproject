<?php
session_start();
// 1. START TIMER
$scriptStart = microtime(true);

require 'vendor/autoload.php';

// USE SPECIFIC READERS FOR OPENSPOUT V4
use OpenSpout\Reader\Xlsx\Reader as XlsxReader;
use OpenSpout\Reader\CSV\Reader as CsvReader;

/* ================= CONFIG ================= */
ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('memory_limit', '1024M'); 
set_time_limit(600); 

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

/* ================= DB CONNECTION ================= */
$pdo = new PDO(
    "mysql:host=localhost;dbname=trainingc;charset=utf8mb4",
    "root",
    "Admin123",
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true 
    ]
);

/* ================= HELPERS ================= */
function parseDateFast($v) {
    if (empty($v)) return null;

    if ($v instanceof \DateTime || $v instanceof \DateTimeImmutable) {
        return $v->format('Y-m-d');
    }

    if (is_string($v)) {
        $t = strtotime(str_replace('/', '-', $v));
        return $t ? date('Y-m-d', $t) : null;
    }

    return null;
}

// Helper for BATCH STRINGS (Returns 'NULL' string or number)
function f($v) {
    if ($v === '' || $v === null) return 'NULL';
    if (is_numeric($v)) return (float)$v;
    return (float) str_replace(',', '.', $v);
}

/* ================= UPLOAD HANDLER ================= */
$file = $_FILES['fileToUpload'];
$uploadDir = 'uploads/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

$target = $uploadDir . uniqid() . '_' . basename($file['name']);
move_uploaded_file($file['tmp_name'], $target);

$ext = strtolower(pathinfo($target, PATHINFO_EXTENSION));

/* ================= DATABASE TRANSACTION ================= */
$pdo->beginTransaction();
$pdo->exec("SET FOREIGN_KEY_CHECKS=0");
$pdo->exec("SET UNIQUE_CHECKS=0");

/* Temp Tables */
$pdo->exec("CREATE TEMPORARY TABLE tmp_existing_keys (id_session INT, id_karyawan INT, PRIMARY KEY (id_session, id_karyawan)) ENGINE=InnoDB");
$pdo->exec("INSERT INTO tmp_existing_keys (id_session, id_karyawan) SELECT id_session, id_karyawan FROM score");

$pdo->exec("CREATE TEMPORARY TABLE tmp_upload_keys (excel_row INT, id_session INT, id_karyawan INT, nama VARCHAR(255), training VARCHAR(255), date_start DATE, KEY idx_chk (id_session, id_karyawan)) ENGINE=InnoDB");

$initialCount = (int)$pdo->query("SELECT COUNT(*) FROM score")->fetchColumn();

/* Caching IDs */
$bu = $pdo->query("SELECT nama_bu,id_bu FROM bu")->fetchAll(PDO::FETCH_KEY_PAIR);
$kar = $pdo->query("SELECT index_karyawan,id_karyawan FROM karyawan")->fetchAll(PDO::FETCH_KEY_PAIR);
$train = $pdo->query("SELECT nama_training,id_training FROM training")->fetchAll(PDO::FETCH_KEY_PAIR);

$func = [];
foreach ($pdo->query("SELECT id_func,func_n1,func_n2 FROM func") as $r) {
    $func[$r['func_n1'] . '|' . ($r['func_n2'] ?? '')] = $r['id_func'];
}

$sess = [];
foreach ($pdo->query("SELECT id_session,id_training,code_sub,date_start FROM training_session") as $r) {
    $sess[$r['id_training'] . '|' . $r['code_sub'] . '|' . $r['date_start']] = $r['id_session'];
}

/* Prepared Statements */
$insBU    = $pdo->prepare("INSERT INTO bu (nama_bu) VALUES (?)");
$insFunc  = $pdo->prepare("INSERT INTO func (func_n1,func_n2) VALUES (?,?)");
$insKar   = $pdo->prepare("INSERT INTO karyawan (index_karyawan,nama_karyawan) VALUES (?,?)");
$insTrain = $pdo->prepare("INSERT INTO training (nama_training,jenis,type) VALUES (?,?,?)");
$insSess  = $pdo->prepare("INSERT INTO training_session (id_training,code_sub,class,date_start,date_end,credit_hour,place,method) VALUES (?,?,?,?,?,?,?,?)");

/* Batch Settings */
$BATCH_LIMIT = 500;
$sqlScores = [];
$sqlKeys = [];

$baseScoreSQL = "INSERT INTO score (id_session,id_karyawan,id_bu,id_func,pre,post,statis_subject,instructor,statis_infras) VALUES ";
$baseKeysSQL  = "INSERT INTO tmp_upload_keys (excel_row,id_session,id_karyawan,nama,training,date_start) VALUES ";
$onDupSQL = " ON DUPLICATE KEY UPDATE id_bu=VALUES(id_bu), id_func=VALUES(id_func), pre=VALUES(pre), post=VALUES(post), statis_subject=VALUES(statis_subject), instructor=VALUES(instructor), statis_infras=VALUES(statis_infras)";

/* ================= STREAMING READER ================= */
try {
    if ($ext === 'csv') {
        $reader = new CsvReader();
    } elseif ($ext === 'xlsx') {
        $reader = new XlsxReader();
    } else {
        throw new Exception("Unsupported file extension: $ext");
    }

    $reader->open($target);

    $rowsProcessed = 0;
    $rowsSkipped = 0;
    $excelRow = 0; 

    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $excelRow++;
            
            // Skip Header
            if ($excelRow === 1) continue;

            $r = $row->toArray();
            $rowsProcessed++;

            $idx  = trim($r[0] ?? '');
            $name = trim($r[1] ?? '');
            $subj = trim($r[2] ?? '');

            if (!$idx || !$subj) { $rowsSkipped++; continue; }

            $ds = parseDateFast($r[4] ?? null);
            if (!$ds) { $rowsSkipped++; continue; }

            /* --- ID RESOLUTION --- */
            
            // BU
            $b = trim($r[16] ?? '');
            if (!isset($bu[$b])) {
                $insBU->execute([$b]);
                $bu[$b] = $pdo->lastInsertId();
            }

            // FUNC
            $f1 = trim($r[17] ?? '');
            $f2 = trim($r[18] ?? '') ?: null;
            $fk = $f1 . '|' . ($f2 ?? '');
            if (!isset($func[$fk])) {
                $insFunc->execute([$f1, $f2]);
                $func[$fk] = $pdo->lastInsertId();
            }

            // KARYAWAN
            if (!isset($kar[$idx])) {
                $insKar->execute([$idx, $name]);
                $kar[$idx] = $pdo->lastInsertId();
            }

            // TRAINING
            if (!isset($train[$subj])) {
                $insTrain->execute([$subj, trim($r[19] ?? ''), trim($r[8] ?? '')]);
                $train[$subj] = $pdo->lastInsertId();
            }

            // SESSION
            $sk = $train[$subj] . '|' . trim($r[3] ?? '') . '|' . $ds;
            if (!isset($sess[$sk])) {
                $de = parseDateFast($r[5] ?? null) ?: $ds;
                
                // === FIX: Manual Float Conversion for Prepared Statement ===
                $rawCredit = $r[6] ?? 0;
                $creditHours = 0;
                if (is_numeric($rawCredit)) {
                    $creditHours = (float)$rawCredit;
                } elseif (is_string($rawCredit) && $rawCredit !== '') {
                    $creditHours = (float)str_replace(',', '.', $rawCredit);
                }
                
                $insSess->execute([
                    $train[$subj], trim($r[3] ?? ''), trim($r[10] ?? ''),
                    $ds, $de, $creditHours, trim($r[7] ?? ''), trim($r[9] ?? '')
                ]);
                $sess[$sk] = $pdo->lastInsertId();
            }

            /* --- BATCH STRINGS (f() is safe here) --- */
            $sid = $sess[$sk];
            $kid = $kar[$idx];
            
            $q_name = $pdo->quote($name);
            $q_subj = $pdo->quote($subj);
            $q_ds   = $pdo->quote($ds);
            $sqlKeys[] = "($excelRow, $sid, $kid, $q_name, $q_subj, $q_ds)";

            $bid = $bu[$b];
            $fid = $func[$fk];
            
            $v_pre = f($r[14] ?? null);
            $v_post = f($r[15] ?? null);
            $v_sub = f($r[11] ?? null);
            $v_ins = f($r[12] ?? null);
            $v_inf = f($r[13] ?? null);

            $sqlScores[] = "($sid, $kid, $bid, $fid, $v_pre, $v_post, $v_sub, $v_ins, $v_inf)";

            if (count($sqlScores) >= $BATCH_LIMIT) {
                $pdo->exec($baseKeysSQL . implode(',', $sqlKeys));
                $pdo->exec($baseScoreSQL . implode(',', $sqlScores) . $onDupSQL);
                $sqlKeys = [];
                $sqlScores = [];
            }
        }
        break; 
    }

    $reader->close();

    if (!empty($sqlScores)) {
        $pdo->exec($baseKeysSQL . implode(',', $sqlKeys));
        $pdo->exec($baseScoreSQL . implode(',', $sqlScores) . $onDupSQL);
    }

} catch (Exception $e) {
    $pdo->rollBack();
    die("Error reading file: " . $e->getMessage());
}

/* ================= FINAL REPORT ================= */
unlink($target);

$duplicates = $pdo->query("SELECT u.excel_row, u.nama, u.training, u.date_start FROM tmp_upload_keys u JOIN tmp_existing_keys e ON u.id_session = e.id_session AND u.id_karyawan = e.id_karyawan")->fetchAll();

$pdo->exec("SET FOREIGN_KEY_CHECKS=1");
$pdo->exec("SET UNIQUE_CHECKS=1");
$pdo->commit();

$finalCount = (int)$pdo->query("SELECT COUNT(*) FROM score")->fetchColumn();
$processed = $rowsProcessed - $rowsSkipped;
$inserted = max(0, $finalCount - $initialCount);
$updated = max(0, $processed - $inserted);

$time = number_format(microtime(true) - $scriptStart, 2);

$_SESSION['upload_stats'] = [
    'total'      => $processed,
    'unique'     => $inserted,
    'duplicates' => $updated,
    'skipped'    => $rowsSkipped
];

$_SESSION['duplicate_logs'] = array_map(fn($d) => "Row {$d['excel_row']}: {$d['nama']} - {$d['training']}", $duplicates);

$_SESSION['upload_message'] = "Processed in <b>{$time}s</b> (Fast Mode) | Total: {$processed} | Inserted: {$inserted} | Updated: {$updated}";

$pdo->prepare("INSERT INTO uploads (file_name,uploaded_by,status,rows_processed) VALUES (?,?,?,?)")->execute([basename($file['name']), $_SESSION['username'] ?? 'Admin', 'Success', $processed]);

header("Location: upload.php?status=success");
exit();
?>