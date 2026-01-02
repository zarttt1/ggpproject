<?php
// 1. DATABASE CONNECTION
$host = 'localhost';
$db   = 'trainingc';
$user = 'root';
$pass = 'Admin123'; // <--- CHECK PASSWORD
$charset = 'utf8mb4';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=$charset", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    // DISABLE STRICT MODE
    $pdo->exec("SET sql_mode = ''");
} catch (\PDOException $e) {
    die("❌ Connection Failed: " . $e->getMessage());
}

// 2. AUTO-FIX DATABASE
try {
    $pdo->exec("ALTER TABLE karyawan MODIFY index_karyawan VARCHAR(100)");
    $pdo->exec("ALTER TABLE score MODIFY pre FLOAT, MODIFY post FLOAT");
    $pdo->exec("ALTER TABLE training MODIFY credit_hour FLOAT");
    $pdo->exec("ALTER TABLE score MODIFY statis_subject DOUBLE, MODIFY instructor DOUBLE, MODIFY statis_infras DOUBLE");
} catch (Exception $e) { /* Ignore */ }

// 3. HELPER
function detectDelimiter($file) {
    $handle = fopen($file, "r");
    $line = fgets($handle);
    fclose($handle);
    return (substr_count($line, ';') > substr_count($line, ',')) ? ';' : ',';
}

// 4. MAIN PROCESS
$report = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $startTime = microtime(true);
    $file = $_FILES['csv_file']['tmp_name'];
    $delimiter = detectDelimiter($file);

    try {
        // A. CREATE TEMP TABLE
        $pdo->exec("DROP TABLE IF EXISTS temp_import");
        $pdo->exec("CREATE TABLE temp_import (
            indeks TEXT, name TEXT, subject TEXT, date_start TEXT, date_end TEXT, 
            credit_hours TEXT, place TEXT, method TEXT, class_code TEXT, 
            satis_subject TEXT, satis_instructor TEXT, satis_infras TEXT, 
            prescore TEXT, postscore TEXT, bu TEXT, func TEXT, func_n2 TEXT, jenis TEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        // B. STREAM CSV
        if (($handle = fopen($file, "r")) !== FALSE) {
            $stmt = $pdo->prepare("INSERT INTO temp_import VALUES (" . str_repeat("?,", 17) . "?)");
            
            // 🛑 SKIP HEADER (Row A1)
            fgetcsv($handle, 0, $delimiter); 
            
            $pdo->beginTransaction();
            while (($data = fgetcsv($handle, 10000, $delimiter)) !== FALSE) {
                $data = array_slice($data, 0, 18);
                $data = array_pad($data, 18, null);
                // Skip completely empty rows
                if (count(array_filter($data)) == 0) continue; 
                $stmt->execute($data);
            }
            $pdo->commit();
            fclose($handle);
        }

        // C. DISTRIBUTE DATA (Allows '0', Blocks '')
        $pdo->beginTransaction();

        // 1. BU
        $pdo->exec("INSERT INTO bu (nama_bu)
            SELECT DISTINCT TRIM(bu) FROM temp_import t
            WHERE bu IS NOT NULL 
            AND TRIM(bu) != '' 
            AND NOT EXISTS (SELECT 1 FROM bu b WHERE b.nama_bu = TRIM(t.bu) COLLATE utf8mb4_general_ci)");

        // 2. Func
        $pdo->exec("INSERT INTO func (func_n1, func_n2)
            SELECT DISTINCT TRIM(func), TRIM(func_n2) FROM temp_import t
            WHERE func IS NOT NULL 
            AND TRIM(func) != ''
            AND NOT EXISTS (SELECT 1 FROM func f WHERE f.func_n1 = TRIM(t.func) COLLATE utf8mb4_general_ci AND IFNULL(f.func_n2,'') = IFNULL(TRIM(t.func_n2),'') COLLATE utf8mb4_general_ci)");

        // 3. Employees
        $pdo->exec("INSERT INTO karyawan (index_karyawan, nama_karyawan, id_bu, id_func)
            SELECT DISTINCT TRIM(t.indeks), TRIM(t.name), b.id_bu, f.id_func
            FROM temp_import t
            JOIN bu b ON TRIM(t.bu) = b.nama_bu COLLATE utf8mb4_general_ci
            JOIN func f ON TRIM(t.func) = f.func_n1 COLLATE utf8mb4_general_ci AND IFNULL(TRIM(t.func_n2),'') = IFNULL(f.func_n2,'') COLLATE utf8mb4_general_ci
            WHERE t.indeks IS NOT NULL AND TRIM(t.indeks) != ''
            AND NOT EXISTS (SELECT 1 FROM karyawan k WHERE k.index_karyawan = TRIM(t.indeks) COLLATE utf8mb4_general_ci)");

        // 4. Training
        $pdo->exec("INSERT INTO training (id_bu, id_func, nama_subject, date_start, date_end, credit_hour, place, method, jenis)
            SELECT DISTINCT b.id_bu, f.id_func, CONCAT(TRIM(t.subject), ' (', TRIM(t.class_code), ')'), 
            CASE WHEN t.date_start LIKE '%/%' THEN STR_TO_DATE(t.date_start, '%d/%m/%Y') ELSE STR_TO_DATE(t.date_start, '%Y-%m-%d') END,
            CASE WHEN t.date_end LIKE '%/%'   THEN STR_TO_DATE(t.date_end, '%d/%m/%Y')   ELSE STR_TO_DATE(t.date_end, '%Y-%m-%d') END,
            NULLIF(REPLACE(TRIM(t.credit_hours), ',', '.'), ''), 
            t.place, t.method, t.jenis
            FROM temp_import t
            JOIN bu b ON TRIM(t.bu) = b.nama_bu COLLATE utf8mb4_general_ci
            JOIN func f ON TRIM(t.func) = f.func_n1 COLLATE utf8mb4_general_ci AND IFNULL(TRIM(t.func_n2),'') = IFNULL(f.func_n2,'') COLLATE utf8mb4_general_ci
            WHERE NOT EXISTS (
                SELECT 1 FROM training tr 
                WHERE tr.nama_subject = CONCAT(TRIM(t.subject), ' (', TRIM(t.class_code), ')') COLLATE utf8mb4_general_ci 
                AND tr.date_start = (CASE WHEN t.date_start LIKE '%/%' THEN STR_TO_DATE(t.date_start, '%d/%m/%Y') ELSE STR_TO_DATE(t.date_start, '%Y-%m-%d') END)
                AND tr.id_bu = b.id_bu
            )");

        // 5. Score
        $pdo->exec("INSERT INTO score (id_subject, id_karyawan, pre, post, statis_subject, instructor, statis_infras)
            SELECT DISTINCT tr.id_subject, k.id_karyawan, 
            NULLIF(REPLACE(TRIM(t.prescore), ',', '.'), ''), 
            NULLIF(REPLACE(TRIM(t.postscore), ',', '.'), ''), 
            NULLIF(REPLACE(TRIM(t.satis_subject), ',', '.'), ''), 
            NULLIF(REPLACE(TRIM(t.satis_instructor), ',', '.'), ''), 
            NULLIF(REPLACE(TRIM(t.satis_infras), ',', '.'), '')
            FROM temp_import t
            JOIN bu b ON TRIM(t.bu) = b.nama_bu COLLATE utf8mb4_general_ci
            JOIN karyawan k ON k.index_karyawan = TRIM(t.indeks) COLLATE utf8mb4_general_ci
            JOIN training tr ON 
                tr.nama_subject = CONCAT(TRIM(t.subject), ' (', TRIM(t.class_code), ')') COLLATE utf8mb4_general_ci 
                AND tr.date_start = (CASE WHEN t.date_start LIKE '%/%' THEN STR_TO_DATE(t.date_start, '%d/%m/%Y') ELSE STR_TO_DATE(t.date_start, '%Y-%m-%d') END)
                AND tr.id_bu = b.id_bu
            WHERE NOT EXISTS (SELECT 1 FROM score s WHERE s.id_subject = tr.id_subject AND s.id_karyawan = k.id_karyawan)");

        $pdo->commit();
        $pdo->exec("DROP TABLE temp_import"); 

        $time = round(microtime(true) - $startTime, 2);
        $report = "<div style='color:green; font-weight:bold; font-size:18px; padding:20px; border:2px solid green;'>✅ SUCCESS! Database updated in $time seconds.</div>";

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $report = "<div style='color:red; font-weight:bold; padding:20px; border:2px solid red;'>❌ Error: " . $e->getMessage() . "</div>";
    }
}
?>

<!DOCTYPE html>
<html>
<head><title>Clean Data Importer</title></head>
<body style="font-family: sans-serif; padding: 50px;">
    <h2>🚀 Clean Data Importer (Corrected)</h2>
    <?= $report ?>
    <div style="background: #f4f4f4; padding: 20px; border-radius: 8px; width: 400px;">
        <form method="post" enctype="multipart/form-data">
            <label><b>Step 1:</b> Save your Excel as <code>.CSV</code></label><br><br>
            <label><b>Step 2:</b> Upload CSV File:</label><br>
            <input type="file" name="csv_file" required accept=".csv"><br><br>
            <button type="submit" style="padding: 10px 20px; cursor: pointer;">Upload Data</button>
        </form>
    </div>
</body>
</html>