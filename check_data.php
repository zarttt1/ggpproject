<?php
// 1. CONFIGURATION
// NOTE: Since the main script converts XLSX to a temp CSV and deletes it, 
// you can't easily check against the "last uploaded file" unless you disable the unlink() in process_upload.php
// However, this script is still useful for general DB integrity checking.

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
    <title>Database Status Check</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light p-5">

<div class="container bg-white shadow p-4 rounded">
    <h3 class="mb-4">📊 Database Status Check</h3>
    
    <table class="table table-bordered table-hover">
        <thead class="table-dark">
            <tr>
                <th>Table / Entity</th>
                <th>Rows in Database</th>
            </tr>
        </thead>
        <tbody>
            <tr><td>Business Units (bu)</td><td><?= $db_counts['bu'] ?></td></tr>
            <tr><td>Functions (func)</td><td><?= $db_counts['func'] ?></td></tr>
            <tr><td>Employees (karyawan)</td><td><?= $db_counts['karyawan'] ?></td></tr>
            <tr><td>Training Topics</td><td><?= $db_counts['training'] ?></td></tr>
            <tr><td>Training Sessions</td><td><?= $db_counts['sessions'] ?></td></tr>
            <tr><td class="fw-bold">Scores (Total Records)</td><td class="fw-bold text-primary"><?= $db_counts['scores'] ?></td></tr>
        </tbody>
    </table>
    
    <div class="alert alert-info">
        <strong>Tip:</strong> If you uploaded ~24,000 rows but see fewer Scores:
        <ul>
            <li>Duplicate entries (Same User + Same Session) are skipped to prevent double counting.</li>
            <li>Rows with missing "Employee Index" or "Training Subject" are skipped.</li>
            <li>Check the "Skipped Rows Log" on the upload page for details.</li>
        </ul>
    </div>

    <a href="upload.php" class="btn btn-primary mt-3">Back to Upload</a>
</div>

</body>
</html>