<?php
session_start();
require 'vendor/autoload.php';
$_SERVER['REQUEST_TIME_FLOAT'] ??= microtime(true);

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/* ================= CONFIG ================= */
ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('memory_limit', '1024M');
set_time_limit(600);

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$pdo = new PDO(
    "mysql:host=localhost;dbname=trainingc;charset=utf8mb4",
    "root",
    "Admin123",
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false
    ]
);

/* ================= HELPERS ================= */
function detectDelimiter($file) {
    $h = fopen($file, 'r');
    $l = fgets($h);
    fclose($h);
    return substr_count($l, ';') > substr_count($l, ',') ? ';' : ',';
}

function parseDateFast($v) {
    if ($v === '' || $v === null) return null;
    if (is_numeric($v)) {
        return ExcelDate::excelToDateTimeObject($v)->format('Y-m-d');
    }
    $t = strtotime(str_replace('/', '-', $v));
    return $t ? date('Y-m-d', $t) : null;
}

function f($v) {
    return ($v === '' || $v === null) ? null : (float) str_replace(',', '.', $v);
}

/* ================= UPLOAD ================= */
$file = $_FILES['fileToUpload'];
$uploadDir = 'uploads/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

$target = $uploadDir . uniqid() . '_' . basename($file['name']);
move_uploaded_file($file['tmp_name'], $target);

$ext = strtolower(pathinfo($target, PATHINFO_EXTENSION));
$fileToRead = $target;

if ($ext === 'xlsx') {
    $csv = $uploadDir . uniqid() . '.csv';
    $reader = IOFactory::createReader('Xlsx');
    $reader->setReadDataOnly(true);
    $sheet = $reader->load($target);
    IOFactory::createWriter($sheet, 'Csv')
        ->setDelimiter(';')
        ->setSheetIndex(0)
        ->save($csv);
    $sheet->disconnectWorksheets();
    unlink($target);
    $fileToRead = $csv;
}

/* ================= TRANSACTION ================= */
$startTime = microtime(true);

$pdo->beginTransaction();
$pdo->exec("SET AUTOCOMMIT=0");
$pdo->exec("SET FOREIGN_KEY_CHECKS=0");
$pdo->exec("SET UNIQUE_CHECKS=0");

/* === TEMP TABLES (InnoDB – FIXED) === */
$pdo->exec("
    CREATE TEMPORARY TABLE tmp_existing_keys (
        id_session INT,
        id_karyawan INT,
        PRIMARY KEY (id_session, id_karyawan)
    ) ENGINE=InnoDB
");

$pdo->exec("
    INSERT INTO tmp_existing_keys (id_session, id_karyawan)
    SELECT id_session, id_karyawan FROM score
");

$pdo->exec("
    CREATE TEMPORARY TABLE tmp_upload_keys (
        excel_row INT,
        id_session INT,
        id_karyawan INT,
        nama VARCHAR(255),
        training VARCHAR(255),
        date_start DATE,
        KEY idx_tmp_upload (id_session, id_karyawan)
    ) ENGINE=InnoDB
");

/* === INITIAL COUNT === */
$initialCount = (int)$pdo->query("SELECT COUNT(*) FROM score")->fetchColumn();

/* ================= CACHE ================= */
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

/* ================= PREPARES ================= */
$insBU    = $pdo->prepare("INSERT INTO bu (nama_bu) VALUES (?)");
$insFunc  = $pdo->prepare("INSERT INTO func (func_n1,func_n2) VALUES (?,?)");
$insKar   = $pdo->prepare("INSERT INTO karyawan (index_karyawan,nama_karyawan) VALUES (?,?)");
$insTrain = $pdo->prepare("INSERT INTO training (nama_training,jenis,type) VALUES (?,?,?)");
$insSess  = $pdo->prepare("
    INSERT INTO training_session
    (id_training,code_sub,class,date_start,date_end,credit_hour,place,method)
    VALUES (?,?,?,?,?,?,?,?)
");

/* === SCORE BATCH === */
$BATCH = 500;
$COLS = 9;
$queue = [];

$tpl = '(' . implode(',', array_fill(0, $COLS, '?')) . ')';
$baseSQL = "
INSERT INTO score
(id_session,id_karyawan,id_bu,id_func,pre,post,statis_subject,instructor,statis_infras)
VALUES ";
$updSQL = "
ON DUPLICATE KEY UPDATE
id_bu=VALUES(id_bu),
id_func=VALUES(id_func),
pre=VALUES(pre),
post=VALUES(post),
statis_subject=VALUES(statis_subject),
instructor=VALUES(instructor),
statis_infras=VALUES(statis_infras)
";

/* ================= READ FILE ================= */
$del = detectDelimiter($fileToRead);
$h = fopen($fileToRead, 'r');
fgetcsv($h, 0, $del);

$rowsRead = 0;
$rowsSkipped = 0;
$excelRow = 1;

while ($r = fgetcsv($h, 10000, $del)) {
    $excelRow++;
    $rowsRead++;

    $idx  = trim($r[0] ?? '');
    $name = trim($r[1] ?? '');
    $subj = trim($r[2] ?? '');

    if (!$idx || !$subj) { $rowsSkipped++; continue; }

    $ds = parseDateFast($r[4] ?? '');
    if (!$ds) { $rowsSkipped++; continue; }

    /* BU */
    $b = trim($r[16] ?? '');
    if (!isset($bu[$b])) {
        $insBU->execute([$b]);
        $bu[$b] = $pdo->lastInsertId();
    }

    /* FUNC */
    $f1 = trim($r[17] ?? '');
    $f2 = trim($r[18] ?? '') ?: null;
    $fk = $f1 . '|' . ($f2 ?? '');
    if (!isset($func[$fk])) {
        $insFunc->execute([$f1, $f2]);
        $func[$fk] = $pdo->lastInsertId();
    }

    /* KARYAWAN */
    if (!isset($kar[$idx])) {
        $insKar->execute([$idx, $name]);
        $kar[$idx] = $pdo->lastInsertId();
    }

    /* TRAINING */
    if (!isset($train[$subj])) {
        $insTrain->execute([$subj, trim($r[19] ?? ''), trim($r[8] ?? '')]);
        $train[$subj] = $pdo->lastInsertId();
    }

    /* SESSION */
    $sk = $train[$subj] . '|' . trim($r[3] ?? '') . '|' . $ds;
    if (!isset($sess[$sk])) {
        $de = parseDateFast($r[5] ?? '') ?: $ds;
        
        // --- MODIFIED: Force 0 if blank/null ---
        $creditHours = f($r[6]) ?? 0; 

        $insSess->execute([
            $train[$subj], trim($r[3] ?? ''), trim($r[10] ?? ''),
            $ds, $de, $creditHours, trim($r[7]), trim($r[9])
        ]);
        $sess[$sk] = $pdo->lastInsertId();
    }

    /* Track for duplicate report */
    $pdo->prepare("
        INSERT INTO tmp_upload_keys
        (excel_row,id_session,id_karyawan,nama,training,date_start)
        VALUES (?,?,?,?,?,?)
    ")->execute([
        $excelRow,
        $sess[$sk],
        $kar[$idx],
        $name,
        $subj,
        $ds
    ]);

    /* Queue score */
    array_push(
        $queue,
        $sess[$sk], $kar[$idx], $bu[$b], $func[$fk],
        f($r[14]), f($r[15]), f($r[11]), f($r[12]), f($r[13])
    );

    if (count($queue) >= $BATCH * $COLS) {
        $pdo->prepare($baseSQL . implode(',', array_fill(0, $BATCH, $tpl)) . $updSQL)
            ->execute($queue);
        $queue = [];
    }
}

/* Flush remaining */
if ($queue) {
    $n = count($queue) / $COLS;
    $pdo->prepare($baseSQL . implode(',', array_fill(0, $n, $tpl)) . $updSQL)
        ->execute($queue);
}

fclose($h);
if ($fileToRead !== $target) unlink($fileToRead);

/* ================= DUPLICATES ================= */
$duplicates = $pdo->query("
    SELECT u.excel_row, u.nama, u.training, u.date_start
    FROM tmp_upload_keys u
    JOIN tmp_existing_keys e
      ON u.id_session = e.id_session
     AND u.id_karyawan = e.id_karyawan
")->fetchAll();

/* ================= FINALIZE ================= */
$pdo->exec("SET FOREIGN_KEY_CHECKS=1");
$pdo->exec("SET UNIQUE_CHECKS=1");
$pdo->commit();

$finalCount = (int)$pdo->query("SELECT COUNT(*) FROM score")->fetchColumn();

$totalProcessed = $rowsRead - $rowsSkipped;
$inserted = max(0, $finalCount - $initialCount);
$updated = max(0, $totalProcessed - $inserted);

/* === SESSION OUTPUT === */
$_SESSION['upload_stats'] = [
    'total'      => $totalProcessed,
    'unique'     => $inserted,
    'duplicates' => $updated,
    'skipped'    => $rowsSkipped
];

$_SESSION['duplicate_logs'] = array_map(
    fn($d) => "Row {$d['excel_row']}: {$d['nama']} - {$d['training']} (" .
              date('M d, Y', strtotime($d['date_start'])) . ")",
    $duplicates
);

$time = number_format(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 2);
$_SESSION['upload_message'] =
    "Processed in <b>{$time}s</b> | Total: {$totalProcessed} | Inserted: {$inserted} | Updated: {$updated} | Skipped: {$rowsSkipped}";

/* === LOG UPLOAD === */
$pdo->prepare("
    INSERT INTO uploads (file_name,uploaded_by,status,rows_processed)
    VALUES (?,?,?,?)
")->execute([
    basename($file['name']),
    $_SESSION['username'] ?? 'Admin',
    $rowsSkipped ? 'Partial Success' : 'Success',
    $totalProcessed
]);

header("Location: upload.php?status=success");
exit();