<?php
session_start();
require 'db_connect.php';

// 1. Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 2. Get User Details
$username = $_SESSION['username'] ?? 'User';
$initials = strtoupper(substr($username, 0, 2));

// 3. Get Messages & Logs
$uploadMessage = $_SESSION['upload_message'] ?? '';
$uploadLogs = $_SESSION['upload_logs'] ?? [];  // Errors
$updateLogs = $_SESSION['update_logs'] ?? [];  // Updates/Renewals

// Clear session data
unset($_SESSION['upload_message']);
unset($_SESSION['upload_logs']);
unset($_SESSION['update_logs']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GGF - Upload Data</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="icons/icon.png">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: "Poppins", sans-serif; }
        body { background-color: #117054; padding: 0; margin: 0; overflow: hidden; height: 100vh; }
        .main-wrapper { background-color: #f3f4f7; padding: 20px 40px; height: 100vh; overflow-y: auto; width: 100%; position: relative; }
        
        /* NAVBAR */
        .navbar {
            background-color: #197B40; height: 70px; border-radius: 0px 0px 25px 25px; 
            display: flex; align-items: center; padding: 0 30px; justify-content: space-between; 
            margin: -20px 0 30px 0; box-shadow: 0 4px 10px rgba(0,0,0,0.1); 
            flex-shrink: 0; position: sticky; top: -20px; z-index: 1000; 
        }
        .logo-section img { height: 40px; }
        .nav-links { display: flex; gap: 30px; align-items: center; }
        .nav-links a { color: white; text-decoration: none; font-size: 14px; font-weight: 600; opacity: 0.8; transition: 0.3s; }
        .nav-links a:hover { opacity: 1; }
        .nav-links a.active { background: white; color: #197B40; padding: 8px 20px; border-radius: 20px; opacity: 1; }
        .nav-right { display: flex; align-items: center; gap: 20px; }
        .user-profile { display: flex; align-items: center; gap: 12px; color: white; }
        .avatar-circle { width: 35px; height: 35px; background-color: #FF9A02; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 12px; }
        .btn-signout { background-color: #d32f2f; color: white !important; text-decoration: none; font-size: 13px; font-weight: 600; padding: 8px 20px; border-radius: 20px; transition: background 0.3s; opacity: 1 !important; }
        .btn-signout:hover { background-color: #b71c1c; }
        
        /* CONTENT */
        .content-card { background: white; border-radius: 20px; overflow: hidden; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05); margin-bottom: 50px; padding: 40px; }
        .card-header { margin-bottom: 25px; }
        .card-title { font-size: 20px; font-weight: 700; color: #197b40; }
        
        .alert { padding: 15px 20px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; animation: slideIn 0.3s ease-out; }
        .alert-success { background-color: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
        .alert-error { background-color: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }
        .alert-warning { background-color: #fef3c7; color: #92400e; border-left: 4px solid #f59e0b; }
        
        .instruction-box { background-color: #e8f5e9; border-left: 5px solid #197b40; padding: 20px; border-radius: 10px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
        .instruction-text h4 { color: #197b40; margin-bottom: 5px; font-weight: 700; font-size: 14px; }
        .instruction-text p { color: #555; font-size: 13px; margin: 0; }
        
        .upload-zone { border: 2px dashed #cbd5e1; border-radius: 20px; background-color: #fafafa; padding: 50px; text-align: center; transition: all 0.3s; margin-bottom: 30px; cursor: pointer; }
        .upload-zone:hover { border-color: #197b40; background-color: #f0fdf4; }
        .upload-zone.file-selected { border-color: #197b40; background-color: #f0fdf4; }
        .upload-icon-circle { width: 70px; height: 70px; background-color: #e8f5e9; color: #197b40; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px auto; }
        .upload-title { font-size: 16px; font-weight: 600; margin-bottom: 8px; color: #333; }
        .upload-subtitle { color: #888; font-size: 13px; margin-bottom: 10px; }
        
        .btn-snake { position: relative; background: #197b40; color: white; border: none; padding: 10px 24px; border-radius: 25px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 8px; transition: background 0.2s, color 0.2s; overflow: visible; font-size: 13px; }
        .btn-snake.secondary { background: white; color: #197b40; border: 1px solid #197b40; }
        .btn-snake.disabled { background: #cbd5e1; color: white; cursor: not-allowed; border: none; }
        .btn-snake span { position: relative; z-index: 2; }
        
        /* LOG BOX - SHARED STYLES */
        .log-container { margin-bottom: 20px; border-radius: 12px; overflow: hidden; border: 1px solid #ccc; }
        .log-header { padding: 12px 20px; font-size: 14px; font-weight: 700; display: flex; align-items: center; gap: 8px; }
        .log-body { max-height: 200px; overflow-y: auto; background: #fff; padding: 0; }
        .log-item { padding: 8px 20px; border-bottom: 1px solid #f3f4f6; font-size: 13px; color: #555; display: flex; gap: 10px; }
        .log-item:last-child { border-bottom: none; }

        /* Error Log Specifics */
        .log-error { border-color: #f59e0b; }
        .log-error .log-header { background: #fffbeb; color: #92400e; border-bottom: 1px solid #fcd34d; }
        .tag-error { background: #fee2e2; color: #991b1b; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; white-space: nowrap; }

        /* Update Log Specifics */
        .log-update { border-color: #3b82f6; }
        .log-update .log-header { background: #eff6ff; color: #1e40af; border-bottom: 1px solid #bfdbfe; }
        .tag-update { background: #dbeafe; color: #1e40af; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; white-space: nowrap; }

        /* TABLE */
        .table-section-title { margin-bottom: 20px; font-size: 16px; font-weight: 700; color: #333; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 13px; color: #888; padding: 15px 0; border-bottom: 1px solid #eee; }
        td { padding: 20px 0; font-size: 14px; color: #333; border-bottom: 1px solid #f9f9f9; }
        .file-cell { display: flex; align-items: center; gap: 12px; }
        .icon-box { background: #e8f5e9; color: #197b40; padding: 8px; border-radius: 8px; display: flex; align-items: center; }
        .icon-box.csv { background: #fff3e0; color: #f57c00; }
        .file-name-text { font-weight: 700; }
        .badge { padding: 5px 12px; border-radius: 15px; font-size: 11px; font-weight: 600; }
        .status-success { background: #d1fae5; color: #065f46; }
        .status-failed { background: #fee2e2; color: #991b1b; }
        .row-count { font-weight: bold; color: #197b40; }
        
        .loading-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(255, 255, 255, 0.9); z-index: 9999; flex-direction: column; justify-content: center; align-items: center; backdrop-filter: blur(5px); }
        .loading-spinner { width: 80px; height: 80px; border: 5px solid #e0e0e0; border-top: 5px solid #197b40; border-right: 5px solid #ff9a02; border-radius: 50%; animation: spin 1s linear infinite; margin-bottom: 20px; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>
    
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <div style="color:#197b40; font-size:24px; font-weight:bold;">Processing Data...</div>
        <p style="color:#666; margin-top:10px;">Please wait, do not close this window.</p>
    </div>

    <div class="main-wrapper">
        <nav class="navbar">
            <div class="logo-section"><img src="GGF White.png" alt="GGF Logo"></div>
            <div class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="reports.php">Trainings</a>
                <a href="employee_reports.php">Employees</a>
                <a href="upload.php" class="active">Upload Data</a>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <a href="users.php">Users</a>
                <?php endif; ?>
            </div>
            <div class="nav-right">
                <div class="user-profile"><div class="avatar-circle"><?php echo $initials; ?></div></div>
                <a href="logout.php" class="btn-signout">Sign Out</a>
            </div>
        </nav>

        <div class="content-card">
            <div class="card-header">
                <div class="card-title">Upload Data</div>
            </div>

            <div id="alertContainer">
                <?php if (!empty($uploadMessage)): ?>
                    <?php 
                        $statusClass = ($_GET['status'] == 'success') ? 'alert-success' : (($_GET['status'] == 'warning') ? 'alert-warning' : 'alert-error');
                        $icon = ($_GET['status'] == 'success') ? 'check-circle' : 'alert-circle';
                    ?>
                    <div class="alert <?php echo $statusClass; ?>">
                        <i data-lucide="<?php echo $icon; ?>" style="width: 20px;"></i>
                        <span><?php echo $uploadMessage; ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($uploadLogs)): ?>
                <div class="log-container log-error">
                    <div class="log-header">
                        <i data-lucide="alert-triangle" style="width:16px"></i>
                        Import Issues: <?php echo count($uploadLogs); ?> rows skipped
                    </div>
                    <div class="log-body">
                        <?php foreach($uploadLogs as $log): ?>
                            <div class="log-item">
                                <span class="tag-error">Skipped</span>
                                <span><?php echo htmlspecialchars($log); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($updateLogs)): ?>
                <div class="log-container log-update">
                    <div class="log-header">
                        <i data-lucide="info" style="width:16px"></i>
                        Updated Records: <?php echo count($updateLogs); ?> rows renewed
                    </div>
                    <div class="log-body">
                        <?php foreach($updateLogs as $log): ?>
                            <div class="log-item">
                                <span class="tag-update">Updated</span>
                                <span><?php echo htmlspecialchars($log); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="instruction-box">
                <div class="instruction-text">
                    <h4>Before you upload</h4>
                    <p>To ensure data accuracy, please use the standardized Excel template.</p>
                </div>
                <form action="download_template.php" method="POST">
                    <button type="submit" class="btn-snake secondary">
                        <i data-lucide="download" style="width: 14px"></i>
                        <span>Download Template</span>
                    </button>
                </form>
            </div>

            <form action="process_upload.php" method="POST" enctype="multipart/form-data" id="uploadForm">
                <div class="upload-zone" id="uploadZone" onclick="document.getElementById('fileToUpload').click()">
                    <div class="upload-icon-circle">
                        <img src="icons/upload.ico" style="width: 32px; height: 32px; transform: scale(1.8); margin-right: 4px;">
                    </div>
                    <div class="upload-title">Drag &amp; drop your file here or click to browse</div>
                    <div class="upload-subtitle" id="fileNameDisplay">Supported formats: .XLSX, .CSV (Max 10MB)</div>
                    <input type="file" name="fileToUpload" id="fileToUpload" accept=".xlsx,.csv" style="display: none">
                </div>

                <div style="display: flex; justify-content: center; margin-bottom: 40px">
                    <button type="submit" class="btn-snake disabled" id="uploadBtn" disabled>
                        <span>Upload Data</span>
                    </button>
                </div>
            </form>

            <div class="table-section-title">Recent Uploads</div>
            <table>
                <thead>
                    <tr>
                        <th>Date &amp; Time</th>
                        <th>Filename</th>
                        <th>Uploaded By</th>
                        <th>Status</th>
                        <th>Rows Processed</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($conn) {
                        $sql = "SELECT * FROM uploads ORDER BY upload_time DESC LIMIT 10";
                        $result = $conn->query($sql);
                        if ($result && $result->num_rows > 0) {
                            while($row = $result->fetch_assoc()) {
                                $file_ext = strtolower(pathinfo($row['file_name'], PATHINFO_EXTENSION));
                                $icon_class = ($file_ext == 'csv') ? 'csv' : '';
                                $icon_name = ($file_ext == 'csv') ? 'file-text' : 'file-spreadsheet';
                                $status_class = ($row['status'] == 'Success') ? 'status-success' : 'status-failed';
                                echo "<tr>";
                                echo "<td>" . date('Y-m-d H:i', strtotime($row['upload_time'])) . "</td>";
                                echo "<td><div class='file-cell'><div class='icon-box {$icon_class}'><i data-lucide='{$icon_name}' style='width: 18px'></i></div><span class='file-name-text'>" . htmlspecialchars($row['file_name']) . "</span></div></td>";
                                echo "<td>" . htmlspecialchars($row['uploaded_by']) . "</td>";
                                echo "<td><span class='badge {$status_class}'>" . htmlspecialchars($row['status']) . "</span></td>";
                                echo "<td><span class='row-count'>" . htmlspecialchars($row['rows_processed']) . "</span></td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='5' class='empty-state'>No uploads yet.</td></tr>";
                        }
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        lucide.createIcons();
        const fileInput = document.getElementById('fileToUpload');
        const uploadZone = document.getElementById('uploadZone');
        const fileNameDisplay = document.getElementById('fileNameDisplay');
        const uploadBtn = document.getElementById('uploadBtn');
        const uploadForm = document.getElementById('uploadForm');
        const loadingOverlay = document.getElementById('loadingOverlay');

        uploadForm.addEventListener('submit', function() {
            if (!uploadBtn.disabled) { loadingOverlay.style.display = 'flex'; }
        });

        fileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                fileNameDisplay.textContent = 'Selected: ' + this.files[0].name;
                fileNameDisplay.style.fontWeight = 'bold';
                fileNameDisplay.style.color = '#197b40';
                uploadZone.classList.add('file-selected');
                uploadBtn.disabled = false;
                uploadBtn.classList.remove('disabled');
            }
        });

        uploadZone.addEventListener('dragover', function(e) { e.preventDefault(); this.style.borderColor = '#197b40'; this.style.backgroundColor = '#f0fdf4'; });
        uploadZone.addEventListener('dragleave', function(e) { e.preventDefault(); if (!fileInput.files || !fileInput.files[0]) { this.style.borderColor = '#cbd5e1'; this.style.backgroundColor = '#fafafa'; } });
        uploadZone.addEventListener('drop', function(e) { e.preventDefault(); e.stopPropagation(); const files = e.dataTransfer.files; if (files.length > 0) { fileInput.files = files; const event = new Event('change'); fileInput.dispatchEvent(event); } });
    </script>
</body>
</html>