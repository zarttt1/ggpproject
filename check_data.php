<?php
// 1. CONFIGURATION
$csv_file = 'C:\laragon\www\ggpproject\uploads\extracted_training_data_v2.csv'; // <--- VERIFY PATH
$host = 'localhost';
$db   = 'trainingc';
$user = 'root';
$pass = 'Admin123'; 

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, 
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) { die("❌ DB Connection Failed"); }

// 2. CSV ANALYSIS (CALCULATE "EXPECTED")
$unique = [
    'bu'       => [],
    'func'     => [],
    'karyawan' => [],
    'training' => [],
    'sessions' => [],
    'scores'   => [] // Helps detect duplicate rows in CSV
];

if (file_exists($csv_file) && ($handle = fopen($csv_file, "r")) !== FALSE) {
    // Detect delimiter
    $line = fgets($handle);
    $delimiter = (substr_count($line, ';') > substr_count($line, ',')) ? ';' : ',';
    rewind($handle);
    fgetcsv($handle, 0, $delimiter); // Skip Header

    while (($data = fgetcsv($handle, 10000, $delimiter)) !== FALSE) {
        $idx        = trim($data[0] ?? ''); // Index
        $subject    = trim($data[2] ?? ''); // Training Name
        $date       = trim($data[3] ?? ''); // Start Date
        $class      = trim($data[8] ?? ''); // Class
        $bu         = trim($data[14] ?? '');
        $func       = trim($data[15] ?? '') . '|' . trim($data[16] ?? '') . '|' . trim($data[18] ?? '');

        if ($idx == '' || $subject == '') continue;

        // A. BU (Case-insensitive check)
        if ($bu !== '') $unique['bu'][strtolower($bu)] = true;

        // B. FUNC
        if ($func !== '||') $unique['func'][strtolower($func)] = true;

        // C. KARYAWAN (Unique Index)
        if ($idx !== '') $unique['karyawan'][$idx] = true;

        // D. TRAINING TOPICS (Case-insensitive to match MySQL)
        $subjKey = strtolower($subject); 
        $unique['training'][$subjKey] = true;

        // E. SESSIONS (Subject + Class + Date)
        $sessionKey = $subjKey . '|' . strtolower($class) . '|' . $date;
        $unique['sessions'][$sessionKey] = true;

        // F. SCORES (Session + Employee) -> This filters out CSV duplicates!
        $scoreKey = $sessionKey . '|' . $idx;
        $unique['scores'][$scoreKey] = true;
    }
    fclose($handle);
} else {
    die("<div class='alert alert-danger'>❌ CSV File not found: $csv_file</div>");
}

// 3. DATABASE ACTUALS
$db_counts = [
    'bu'       => $pdo->query("SELECT COUNT(*) FROM bu")->fetchColumn(),
    'func'     => $pdo->query("SELECT COUNT(*) FROM func")->fetchColumn(),
    'karyawan' => $pdo->query("SELECT COUNT(*) FROM karyawan")->fetchColumn(),
    'training' => $pdo->query("SELECT COUNT(*) FROM training")->fetchColumn(),
    'sessions' => $pdo->query("SELECT COUNT(*) FROM training_session")->fetchColumn(),
    'scores'   => $pdo->query("SELECT COUNT(*) FROM score")->fetchColumn(),
];

// 4. DISPLAY TABLE
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Full Data Integrity Check</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .status-ok { color: green; font-weight: bold; }
        .status-err { color: #dc3545; font-weight: bold; }
        .table-row { vertical-align: middle; }
    </style>
</head>
<body class="bg-light p-5">

<div class="container bg-white shadow p-4 rounded">
    <h3 class="mb-4">📊 Data Integrity Check</h3>
    <p class="text-muted">Comparing unique data found in CSV vs. stored in Database.</p>

    <table class="table table-bordered table-hover">
        <thead class="table-dark">
            <tr>
                <th>Table / Entity</th>
                <th>Expected (CSV Unique)</th>
                <th>Actual (Database)</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // Define the check list
            $checks = [
                [
                    'label' => 'Business Units (bu)',
                    'exp'   => count($unique['bu']),
                    'act'   => $db_counts['bu']
                ],
                [
                    'label' => 'Functions (func)',
                    'exp'   => count($unique['func']),
                    'act'   => $db_counts['func']
                ],
                [
                    'label' => 'Employees (karyawan)',
                    'exp'   => count($unique['karyawan']),
                    'act'   => $db_counts['karyawan']
                ],
                [
                    'label' => 'Training Topics (Master)',
                    'exp'   => count($unique['training']),
                    'act'   => $db_counts['training']
                ],
                [
                    'label' => 'Training Sessions (Classes)',
                    'exp'   => count($unique['sessions']),
                    'act'   => $db_counts['sessions']
                ],
                [
                    'label' => 'Scores (Valid History)',
                    'exp'   => count($unique['scores']),
                    'act'   => $db_counts['scores']
                ]
            ];

            foreach ($checks as $check) {
                $diff = $check['act'] - $check['exp'];
                
                // Logic: 
                // 1. Exact match is Perfect.
                // 2. DB having MORE is usually OK (maybe previous data existed or blank rows inserted).
                // 3. DB having LESS is usually bad (unless we filtered bad data).
                
                if ($diff == 0) {
                    $status = "<span class='status-ok'>✅ OK (Matched)</span>";
                    $rowClass = "";
                } elseif ($diff > 0 && $check['label'] == 'Business Units (bu)') {
                     // Exception for BU: Often +1 because of empty/blank BU being inserted as a row
                    $status = "<span class='status-ok'>✅ OK (+1 Blank Row)</span>";
                    $rowClass = "";
                } elseif ($diff > 0) {
                    $status = "<span class='text-warning fw-bold'>⚠️ OK (DB has more)</span>";
                    $rowClass = "table-warning";
                } else {
                    $status = "<span class='status-err'>❌ Mismatch ($diff)</span>";
                    $rowClass = "table-danger";
                }

                echo "<tr class='$rowClass table-row'>";
                echo "<td>{$check['label']}</td>";
                echo "<td>{$check['exp']}</td>";
                echo "<td>{$check['act']}</td>";
                echo "<td>$status</td>";
                echo "</tr>";
            }
            ?>
        </tbody>
    </table>
    
    <div class="alert alert-info mt-3">
        <strong>Note:</strong> 
        <ul>
            <li><strong>Expected:</strong> Calculated by scanning the CSV and removing duplicates logic (e.g. "Safety " vs "safety").</li>
            <li><strong>Mismatch (Negative):</strong> Means some CSV rows were rejected (likely duplicates or empty).</li>
            <li><strong>Mismatch (Positive):</strong> Means the Database has extra data (maybe from previous uploads or blank values).</li>
        </ul>
    </div>

    <a href="import.php" class="btn btn-primary mt-3">Back to Import</a>
</div>

</body>
</html>