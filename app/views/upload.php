<?php
$username = $_SESSION['username'] ?? 'User';
$initials = strtoupper(substr($username, 0, 2));

// Variables passed from Controller directly (not via SESSION here, because Controller already extracted them)
// But just in case you use this file standalone, we keep these lines as fallback, though Controller does the unset.
if (!isset($logs)) {
    $logs = $_SESSION['upload_logs'] ?? [];
    $uploadMessage = $_SESSION['upload_message'] ?? '';
    $uploadStats = $_SESSION['upload_stats'] ?? null;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GGF - Upload Data</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="public/icons/icon.png">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: "Poppins", sans-serif; }
        body { background-color: #117054; padding: 0; margin: 0; overflow: hidden; height: 100vh; }
        .main-wrapper { background-color: #f3f4f7; padding: 20px 40px; height: 100vh; overflow-y: auto; width: 100%; position: relative; }
        
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
        
        .content-card { background: white; border-radius: 20px; overflow: hidden; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05); margin-bottom: 50px; padding: 40px; }
        .card-header { margin-bottom: 25px; }
        .card-title { font-size: 20px; font-weight: 700; color: #197b40; }
        
        .alert { padding: 15px 20px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; animation: slideIn 0.3s ease-out; }
        .alert-success { background-color: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
        .alert-error { background-color: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }
        .alert-warning { background-color: #fef3c7; color: #92400e; border-left: 4px solid #f59e0b; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 25px; }
        .stat-card { background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%); border-radius: 12px; padding: 20px; border: 1px solid #e9ecef; transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-2px); }
        .stat-card.primary { border-left: 4px solid #197b40; }
        .stat-card.info { border-left: 4px solid #3b82f6; }
        .stat-card.warning { border-left: 4px solid #f59e0b; }
        .stat-card.success { border-left: 4px solid #10b981; }
        .stat-label { font-size: 11px; color: #6b7280; font-weight: 600; text-transform: uppercase; margin-bottom: 8px; }
        .stat-value { font-size: 32px; font-weight: 700; color: #1f2937; line-height: 1; }
        
        .log-container { margin: 25px 0; border: 1px solid #eee; border-radius: 12px; overflow: hidden; background: #fff; }
        .log-header { background: #f8f9fa; padding: 10px 20px; border-bottom: 1px solid #eee; display:flex; justify-content:space-between; align-items:center; }
        .log-tabs { display: flex; gap: 8px; }
        .log-tab { padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600; cursor: pointer; color: #555; background: white; border: 1px solid #ddd; transition: 0.2s; }
        .log-tab:hover { background: #eee; }
        .log-tab.active { background: #197B40; color: white; border-color: #197B40; }
        
        .log-scroll { max-height: 350px; overflow-y: auto; }
        .log-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .log-table th { background: #fff; position: sticky; top: 0; color: #666; font-weight: 600; text-transform: uppercase; padding: 10px 20px; border-bottom: 2px solid #f0f0f0; }
        .log-table td { padding: 8px 20px; border-bottom: 1px solid #f5f5f5; vertical-align:middle; }
        
        .status-badge { padding: 3px 10px; border-radius: 6px; font-weight: 600; font-size: 10px; text-transform: uppercase; display: inline-block; }
        .status-Duplicate { background: #e0f2f1; color: #00695c; }
        .status-Skipped { background: #ffebee; color: #c62828; }
        .status-New { background: #e8f5e9; color: #2e7d32; }
        .status-Warning { background: #fff3e0; color: #f57f17; }

        .instruction-box { background-color: #e8f5e9; border-left: 5px solid #197b40; padding: 20px; border-radius: 10px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
        .instruction-text h4 { color: #197b40; margin-bottom: 5px; font-weight: 700; font-size: 14px; }
        .instruction-text p { color: #555; font-size: 13px; margin: 0; }
        
        .upload-zone { border: 2px dashed #cbd5e1; border-radius: 20px; background-color: #fafafa; padding: 50px; text-align: center; transition: all 0.3s; margin-bottom: 30px; cursor: pointer; }
        .btn-snake { position: relative; background: #197b40; color: white; border: none; padding: 10px 24px; border-radius: 25px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 8px; font-size: 13px; }
        .btn-snake.secondary { background: white; color: #197b40; border: 1px solid #197b40; }
        
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
            <div class="logo-section"><img src="public/GGF White.png" alt="GGF Logo"></div>
            <div class="nav-links">
                <a href="index.php?action=dashboard">Dashboard</a>
                <a href="index.php?action=reports">Trainings</a>
                <a href="index.php?action=employees">Employees</a>
                <a href="index.php?action=upload" class="active">Upload Data</a>
                <a href="index.php?action=users">Users</a>
            </div>
            <div class="nav-right">
                <div class="user-profile"><div class="avatar-circle"><?php echo $initials; ?></div></div>
                <a href="index.php?action=logout" class="btn-signout">Sign Out</a>
            </div>
        </nav>

        <div class="content-card">
            <div class="card-header">
                <div class="card-title">Upload Data</div>
            </div>

            <?php if (!empty($uploadMessage)): ?>
                <?php 
                    $statusClass = ($_GET['status'] ?? '') == 'success' ? 'alert-success' : (($_GET['status'] ?? '') == 'warning' ? 'alert-warning' : 'alert-error');
                    $icon = ($_GET['status'] ?? '') == 'success' ? 'check-circle' : 'alert-circle';
                ?>
                <div class="alert <?php echo $statusClass; ?>">
                    <i data-lucide="<?php echo $icon; ?>" style="width: 20px;"></i>
                    <span><?php echo $uploadMessage; ?></span>
                </div>
            <?php endif; ?>

            <?php if ($uploadStats && is_array($uploadStats)): ?>
                <div class="stats-grid">
                    <div class="stat-card primary">
                        <div class="stat-label">📊 Total Rows</div>
                        <div class="stat-value"><?php echo number_format($uploadStats['total']); ?></div>
                    </div>
                    <div class="stat-card success">
                        <div class="stat-label">✅ New Records</div>
                        <div class="stat-value"><?php echo number_format($uploadStats['unique']); ?></div>
                    </div>
                    <div class="stat-card info">
                        <div class="stat-label">🔄 Duplicates</div>
                        <div class="stat-value"><?php echo number_format($uploadStats['duplicates']); ?></div>
                    </div>
                    <div class="stat-card warning">
                        <div class="stat-label">⚠️ Warnings</div>
                        <div class="stat-value"><?php echo number_format($uploadStats['warnings']); ?></div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($logs)): ?>
                <div class="log-container">
                    <div class="log-header">
                        <div class="log-tabs">
                            <button class="log-tab active" onclick="filterLogs('All', this)">All Logs</button>
                            <button class="log-tab" onclick="filterLogs('New', this)">New</button>
                            <button class="log-tab" onclick="filterLogs('Duplicate', this)">Duplicate</button>
                            <button class="log-tab" onclick="filterLogs('Warning', this)">Warning</button>
                        </div>
                        <span style="font-size:11px; font-weight:normal; color:#888;"><?php echo count($logs); ?> Events Logged</span>
                    </div>
                    <div class="log-scroll">
                        <table class="log-table" id="logTable">
                            <thead>
                                <tr>
                                    <th style="width: 70px;">Row</th>
                                    <th style="width: 100px;">Status</th>
                                    <th>Message</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($logs as $log): ?>
                                    <tr class="log-row" data-status="<?php echo $log['status']; ?>">
                                        <td style="color:#888; font-weight:600;">#<?php echo $log['row']; ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $log['status']; ?>">
                                                <?php echo $log['status']; ?>
                                            </span>
                                        </td>
                                        <td style="color:#444;"><?php echo htmlspecialchars($log['msg']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <div class="instruction-box">
                <div class="instruction-text">
                    <h4>Before you upload</h4>
                    <p>To ensure data accuracy, please use the standardized Excel template.</p>
                </div>
                <form action="index.php?action=download_template" method="POST">
                    <button type="submit" class="btn-snake secondary">
                        <i data-lucide="download" style="width: 14px"></i>
                        <span>Download Template</span>
                    </button>
                </form>
            </div>

            <form action="index.php?action=upload_file" method="POST" enctype="multipart/form-data" id="uploadForm">
                <div class="upload-zone" id="uploadZone" onclick="document.getElementById('fileToUpload').click()">
                    <div class="upload-icon-circle">
                        <img src="public/icons/upload.ico" style="width: 32px; height: 32px; transform: scale(1.8); margin-right: 4px;">
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
                    <?php if (!empty($history)): ?>
                        <?php foreach($history as $row): 
                            $file_ext = strtolower(pathinfo($row['file_name'], PATHINFO_EXTENSION));
                            $icon_class = ($file_ext == 'csv') ? 'csv' : '';
                            $icon_name = ($file_ext == 'csv') ? 'file-text' : 'file-spreadsheet';
                            $status_class = ($row['status'] == 'Success' || $row['status'] == 'Partial Success') ? 'status-success' : 'status-failed';
                            $time = isset($row['upload_time']) ? date('Y-m-d H:i', strtotime($row['upload_time'])) : date('Y-m-d H:i');
                        ?>
                        <tr>
                            <td><?php echo $time; ?></td>
                            <td>
                                <div class="file-cell">
                                    <div class="icon-box <?php echo $icon_class; ?>"><i data-lucide="<?php echo $icon_name; ?>" style="width: 18px"></i></div>
                                    <span class="file-name-text"><?php echo htmlspecialchars($row['file_name']); ?></span>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($row['uploaded_by'] ?? 'System'); ?></td>
                            <td><span class="badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($row['status']); ?></span></td>
                            <td><span class="row-count"><?php echo number_format($row['rows_processed']); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align:center; padding: 40px; color:#999;">No uploads yet.</td></tr>
                    <?php endif; ?>
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

        function filterLogs(status, btn) {
            const rows = document.querySelectorAll('.log-row');
            const tabs = document.querySelectorAll('.log-tab');
            
            tabs.forEach(tab => {
                tab.classList.remove('active');
            });
            if(btn) btn.classList.add('active');

            rows.forEach(row => {
                if (status === 'All' || row.dataset.status === status) {
                    row.style.display = 'table-row';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        uploadZone.addEventListener('dragover', function(e) { e.preventDefault(); this.style.borderColor = '#197b40'; this.style.backgroundColor = '#f0fdf4'; });
        uploadZone.addEventListener('dragleave', function(e) { e.preventDefault(); if (!fileInput.files || !fileInput.files[0]) { this.style.borderColor = '#cbd5e1'; this.style.backgroundColor = '#fafafa'; } });
        uploadZone.addEventListener('drop', function(e) { e.preventDefault(); e.stopPropagation(); const files = e.dataTransfer.files; if (files.length > 0) { fileInput.files = files; const event = new Event('change'); fileInput.dispatchEvent(event); } });
    </script>
</body>
</html>