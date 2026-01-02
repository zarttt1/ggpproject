<?php
// 1. SETTINGS & CONNECTION
$csv_file = 'C:\laragon\www\ggpproject\uploads\extracted_training_data_v2.csv'; // <--- CHECK FILENAME
$host = 'localhost';
$db   = 'trainingc';
$user = 'root';
$pass = 'Admin123'; // <--- CHECK PASSWORD
$charset = 'utf8mb4';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=$charset", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, 
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) { die("❌ DB Connection Failed: " . $e->getMessage()); }

// 2. ANALYZE CSV FILE
$expected = [
    'bu' => [],
    'func_n1' => [], // Independent List for N1
    'func_n2' => [], // Independent List for N2
    'karyawan' => [],
    'training' => []
];

if (($handle = fopen($csv_file, "r")) !== FALSE) {
    // Detect delimiter
    $line = fgets($handle);
    $delimiter = (substr_count($line, ';') > substr_count($line, ',')) ? ';' : ',';
    rewind($handle);
    fgetcsv($handle, 0, $delimiter); // Skip Header

    while (($data = fgetcsv($handle, 10000, $delimiter)) !== FALSE) {
        if (count($data) < 17) continue;

        $indeks = trim($data[0]);
        $subject = trim($data[2]);
        $date = trim($data[3]);
        $class = trim($data[8]);
        $bu = trim($data[14]);
        $func1 = trim($data[15]); // N1
        $func2 = trim($data[16]); // N2

        if ($indeks == '') continue;

        // Collect Unique Values Independently
        if ($bu) $expected['bu'][$bu] = true;
        
        // Split Checks
        if ($func1) $expected['func_n1'][$func1] = true;
        if ($func2) $expected['func_n2'][$func2] = true;

        if ($indeks) $expected['karyawan'][$indeks] = true;

        $trainKey = $subject . $class . $date . $bu;
        $expected['training'][$trainKey] = true;
    }
    fclose($handle);
}

// 3. GET ACTUAL COUNTS FROM DB (Using DISTINCT to match independent checks)
$actual = [];
$actual['bu'] = $pdo->query("SELECT COUNT(*) FROM bu")->fetchColumn();

// Count DISTINCT values to match the independent CSV list
$actual['func_n1'] = $pdo->query("SELECT COUNT(DISTINCT func_n1) FROM func WHERE func_n1 IS NOT NULL AND func_n1 != ''")->fetchColumn();
$actual['func_n2'] = $pdo->query("SELECT COUNT(DISTINCT func_n2) FROM func WHERE func_n2 IS NOT NULL AND func_n2 != ''")->fetchColumn();

$actual['karyawan'] = $pdo->query("SELECT COUNT(*) FROM karyawan")->fetchColumn();
$actual['training'] = $pdo->query("SELECT COUNT(*) FROM training")->fetchColumn();

// 4. DISPLAY COMPARISON
$style = "border:1px solid #ddd; padding:12px; text-align:left;";
$green = "color:green; font-weight:bold;";
$red   = "color:red; font-weight:bold;";

echo "<h2>📊 Integrity Check (Split Functions)</h2>";
echo "<p>Analyzing file: <code>$csv_file</code></p>";
echo "<table style='border-collapse:collapse; width:60%; font-family:sans-serif;'>";
echo "<tr style='background:#f4f4f4;'><th style='$style'>Table / Check</th><th style='$style'>Expected (CSV Unique)</th><th style='$style'>Actual (DB Distinct)</th><th style='$style'>Status</th></tr>";

// BU
$countExp = count($expected['bu']);
$countAct = $actual['bu'];
$status = ($countExp == $countAct) ? "<span style='$green'>✅ OK</span>" : "<span style='$red'>❌ Mismatch</span>";
echo "<tr><td style='$style'>Business Units</td><td style='$style'>$countExp</td><td style='$style'>$countAct</td><td style='$style'>$status</td></tr>";

// FUNC N1
$countExp = count($expected['func_n1']);
$countAct = $actual['func_n1'];
$status = ($countExp == $countAct) ? "<span style='$green'>✅ OK</span>" : "<span style='$red'>❌ Mismatch</span>";
echo "<tr><td style='$style'>Function N-1 (Unique Names)</td><td style='$style'>$countExp</td><td style='$style'>$countAct</td><td style='$style'>$status</td></tr>";

// FUNC N2
$countExp = count($expected['func_n2']);
$countAct = $actual['func_n2'];
$status = ($countExp == $countAct) ? "<span style='$green'>✅ OK</span>" : "<span style='$red'>❌ Mismatch</span>";
echo "<tr><td style='$style'>Function N-2 (Unique Names)</td><td style='$style'>$countExp</td><td style='$style'>$countAct</td><td style='$style'>$status</td></tr>";

// KARYAWAN
$countExp = count($expected['karyawan']);
$countAct = $actual['karyawan'];
$status = ($countExp == $countAct) ? "<span style='$green'>✅ OK</span>" : "<span style='$red'>❌ Mismatch</span>";
echo "<tr><td style='$style'>Employees</td><td style='$style'>$countExp</td><td style='$style'>$countAct</td><td style='$style'>$status</td></tr>";

// TRAINING
$countExp = count($expected['training']);
$countAct = $actual['training'];
$status = ($countExp == $countAct) ? "<span style='$green'>✅ OK</span>" : "<span style='$red'>❌ Mismatch</span>";
echo "<tr><td style='$style'>Training Sessions</td><td style='$style'>$countExp</td><td style='$style'>$countAct</td><td style='$style'>$status</td></tr>";

echo "</table>";
?>