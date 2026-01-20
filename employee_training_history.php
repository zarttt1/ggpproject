<?php
session_start();
require 'db_connect.php'; 

// 1. Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'] ?? 'User';
$initials = strtoupper(substr($username, 0, 2));

// 2. Validate Employee ID
if (!isset($_GET['id_karyawan']) || empty($_GET['id_karyawan'])) {
    header("Location: employee_reports.php");
    exit();
}

$id_karyawan = (int)$_GET['id_karyawan'];

// --- HELPER FUNCTION: Smart Date Formatting ---
function formatDateRange($start_date, $end_date) {
    if (empty($start_date)) return '-';
    
    $start = strtotime($start_date);
    $end = (!empty($end_date) && $end_date != '0000-00-00') ? strtotime($end_date) : $start;

    if (date('Y-m-d', $start) === date('Y-m-d', $end)) {
        return date('M d, Y', $start);
    }

    if (date('Y', $start) === date('Y', $end)) {
        if (date('M', $start) === date('M', $end)) {
            return date('M d', $start) . ' - ' . date('d, Y', $end);
        }
        return date('M d', $start) . ' - ' . date('M d, Y', $end);
    }

    return date('M d, Y', $start) . ' - ' . date('M d, Y', $end);
}

// --- GET PARAMETERS FOR SEARCH/PAGINATION ---
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// ==========================================
//  AJAX HANDLER (For Live Search)
// ==========================================
if (isset($_GET['ajax_search'])) {
    $search_term = $_GET['ajax_search'];
    $where_search = "";
    
    if (!empty($search_term)) {
        $safe_search = $conn->real_escape_string($search_term);
        // Search by Training Name or Category
        $where_search = " AND (t.nama_training LIKE '%$safe_search%' OR t.jenis LIKE '%$safe_search%')";
    }

    // 1. Count Total Records
    $count_sql = "
        SELECT COUNT(*) as total 
        FROM score s 
        JOIN training_session ts ON s.id_session = ts.id_session
        JOIN training t ON ts.id_training = t.id_training
        WHERE s.id_karyawan = $id_karyawan $where_search
    ";
    $total_rows = $conn->query($count_sql)->fetch_assoc()['total'];
    $total_pages = ceil($total_rows / $limit);

    // 2. Fetch Data (Added ts.date_end)
    $list_sql = "
        SELECT 
            t.nama_training, t.jenis AS category, t.type AS training_type, 
            ts.date_start, ts.date_end, ts.method, ts.place, ts.credit_hour,
            s.pre, s.post
        FROM score s
        JOIN training_session ts ON s.id_session = ts.id_session
        JOIN training t ON ts.id_training = t.id_training
        WHERE s.id_karyawan = $id_karyawan $where_search
        ORDER BY ts.date_start DESC
        LIMIT $limit OFFSET $offset
    ";
    $result = $conn->query($list_sql);

    // 3. Build Table Rows HTML
    ob_start();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $catClass = (stripos($row['category'], 'Technical') !== false) ? 'type-tech' : ((stripos($row['category'], 'Soft') !== false) ? 'type-soft' : 'type-default');
            $methodClass = (stripos($row['method'], 'Online') !== false) ? 'method-online' : 'method-class';
            
            // Format Date Range
            $date_display = formatDateRange($row['date_start'], $row['date_end'] ?? '');
            ?>
            <tr>
                <td>
                    <div style="font-weight:600; color:#333; line-height:1.4;">
                        <?php echo htmlspecialchars($row['nama_training']); ?>
                    </div>
                </td>
                <td style="color:#666; font-family:'Poppins', sans-serif; font-size:12px; font-weight:500; white-space: nowrap;">
                    <?php echo $date_display; ?>
                </td>
                <td><span class="badge <?php echo $catClass; ?>"><?php echo htmlspecialchars($row['category']); ?></span></td>
                <td><span class="badge type-info"><?php echo htmlspecialchars($row['training_type']); ?></span></td>
                <td><span class="badge <?php echo $methodClass; ?>"><?php echo htmlspecialchars($row['method']); ?></span></td>
                <td style="text-align: center; font-weight:600;"><?php echo htmlspecialchars($row['credit_hour']); ?></td>
                <td style="text-align: center; color:#888;"><?php echo $row['pre']; ?></td>
                <td style="text-align: center;">
                    <span class="score-box"><?php echo $row['post']; ?></span>
                </td>
            </tr>
            <?php
        }
    } else {
        echo '<tr><td colspan="8" style="text-align:center; padding: 25px; color:#888;">No training history found.</td></tr>';
    }
    $table_html = ob_get_clean();

    // 4. Build Pagination HTML
    ob_start();
    ?>
    <div>Showing <?php echo ($total_rows > 0 ? $offset + 1 : 0); ?> to <?php echo min($offset + $limit, $total_rows); ?> of <?php echo $total_rows; ?> Records</div>
    <div class="pagination-controls">
        <?php if($page > 1): $prev = $page - 1; echo "<a href='#' onclick='changePage($prev); return false;' class='btn-next' style='transform: rotate(180deg); display:inline-block;'><i data-lucide='chevron-right' style='width:16px;'></i></a>"; endif; ?>
        
        <?php for($i=1; $i<=$total_pages; $i++): ?>
            <?php if ($i == 1 || $i == $total_pages || ($i >= $page - 1 && $i <= $page + 1)): ?>
                <a href="#" onclick="changePage(<?php echo $i; ?>); return false;" class="page-num <?php if($i==$page) echo 'active'; ?>"><?php echo $i; ?></a>
            <?php elseif ($i == $page - 2 || $i == $page + 2): ?>
                <span class="dots">...</span>
            <?php endif; ?>
        <?php endfor; ?>

        <?php if($page < $total_pages): $next = $page + 1; echo "<a href='#' onclick='changePage($next); return false;' class='btn-next'>Next <i data-lucide='chevron-right' style='width:16px;'></i></a>"; endif; ?>
    </div>
    <?php
    $pagination_html = ob_get_clean();

    echo json_encode(['table' => $table_html, 'pagination' => $pagination_html]);
    exit();
}
// ==========================================
//  END AJAX HANDLER
// ==========================================


// --- FETCH EMPLOYEE DETAILS ---
$emp_sql = "
    SELECT 
        k.nama_karyawan, k.index_karyawan,
        (SELECT b.nama_bu FROM score s JOIN bu b ON s.id_bu = b.id_bu WHERE s.id_karyawan = k.id_karyawan ORDER BY s.id_session DESC LIMIT 1) as bu,
        (SELECT f.func_n1 FROM score s JOIN func f ON s.id_func = f.id_func WHERE s.id_karyawan = k.id_karyawan ORDER BY s.id_session DESC LIMIT 1) as func,
        (SELECT f.func_n2 FROM score s JOIN func f ON s.id_func = f.id_func WHERE s.id_karyawan = k.id_karyawan ORDER BY s.id_session DESC LIMIT 1) as func2
    FROM karyawan k
    WHERE k.id_karyawan = ?
";
$stmt = $conn->prepare($emp_sql);
$stmt->bind_param("i", $id_karyawan);
$stmt->execute();
$employee = $stmt->get_result()->fetch_assoc();

if (!$employee) {
    die("Employee not found.");
}

// --- FETCH AGGREGATE STATS ---
$stats_sql = "
    SELECT 
        COUNT(id_score) as total_sessions,
        AVG(post) as avg_score,
        SUM(CASE WHEN t.jenis LIKE '%Technical%' THEN 1 ELSE 0 END) as count_tech,
        SUM(CASE WHEN t.jenis LIKE '%Soft%' THEN 1 ELSE 0 END) as count_soft,
        SUM(ts.credit_hour) as total_hours  
    FROM score s
    JOIN training_session ts ON s.id_session = ts.id_session
    JOIN training t ON ts.id_training = t.id_training
    WHERE s.id_karyawan = ?
";
$stmt = $conn->prepare($stats_sql);
$stmt->bind_param("i", $id_karyawan);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

$total_sessions = $stats['total_sessions'];
$total_hours = $stats['total_hours'] ?? 0;

// --- FETCH TRAINING HISTORY LIST (Initial Load) ---
$where_search = "";
if (!empty($search)) {
    $safe_search = $conn->real_escape_string($search);
    $where_search = " AND (t.nama_training LIKE '%$safe_search%' OR t.jenis LIKE '%$safe_search%')";
}

// Count for Pagination
$count_sql = "
    SELECT COUNT(*) as total 
    FROM score s 
    JOIN training_session ts ON s.id_session = ts.id_session
    JOIN training t ON ts.id_training = t.id_training
    WHERE s.id_karyawan = $id_karyawan $where_search
";
$total_rows = $conn->query($count_sql)->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

// Data Query (Added ts.date_end)
$hist_sql = "
    SELECT 
        t.nama_training, t.jenis AS category, t.type AS training_type, 
        ts.date_start, ts.date_end, ts.method, ts.place, ts.credit_hour,
        s.pre, s.post
    FROM score s
    JOIN training_session ts ON s.id_session = ts.id_session
    JOIN training t ON ts.id_training = t.id_training
    WHERE s.id_karyawan = $id_karyawan $where_search
    ORDER BY ts.date_start DESC
    LIMIT $limit OFFSET $offset
";
$history_result = $conn->query($hist_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GGF - <?php echo htmlspecialchars($employee['nama_karyawan']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="icons/icon.png">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* --- GLOBAL STYLES --- */
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Poppins', sans-serif; }
        body { background-color: #117054; padding: 0; margin: 0; overflow-y: auto; height: 100vh; }
        .main-wrapper { background-color: #f3f4f7; padding: 20px 40px; min-height: 100vh; width: 100%; position: relative; }
        
        /* NAVBAR */
        .navbar {
            background-color: #197B40; height: 70px; border-radius: 0px 0px 25px 25px; 
            display: flex; align-items: center; padding: 0 30px; justify-content: space-between; 
            margin: -20px 0 30px 0; box-shadow: 0 4px 10px rgba(0,0,0,0.1); flex-shrink: 0; position: sticky; top: -20px; z-index: 1000; 
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

        /* --- HERO BANNER --- */
        .hero-banner {
            background: linear-gradient(135deg, #197B40 0%, #0d5e36 100%);
            border-radius: 20px; 
            padding: 25px 35px; 
            position: relative; 
            overflow: hidden; 
            display: flex; 
            align-items: center;
            gap: 30px;
            color: white; 
            box-shadow: 0 10px 25px rgba(25, 123, 64, 0.15);
            height: 100%;
        }

        .hero-banner::after {
            content: '';
            position: absolute;
            right: -50px;
            bottom: -50px;
            width: 250px;
            height: 250px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
        }

        .hero-mascot { flex-shrink: 0; width: 200px; height: 200px; margin-left: -30px; display: flex; align-items: center; justify-content: center; }
        .hero-mascot img { width: 100%; height: 100%; object-fit: contain; filter: drop-shadow(0 5px 15px rgba(0,0,0,0.2)); }

        .hero-content { flex-grow: 1; display: flex; flex-direction: column; gap: 10px; z-index: 2; }
        .hero-label { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; opacity: 0.8; font-weight: 600; }
        .hero-name { font-size: 28px; font-weight: 700; line-height: 1.1; margin: 0; }
        .hero-id { background: rgba(255,255,255,0.2); padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: 600; display: inline-block; width: fit-content; margin-bottom: 5px; }

        .hero-details-stack { display: flex; flex-direction: column; gap: 4px; font-size: 13px; opacity: 0.95; margin-top: 5px; }
        .detail-row { display: flex; align-items: center; gap: 8px; }
        .detail-row i { width: 16px; opacity: 0.7; }

        .hero-stats-stack { display: flex; flex-direction: column; gap: 12px; min-width: 140px; z-index: 2; margin-left: auto; margin-top: 45px; margin-right: 0px; }
        .stat-card { background: rgba(255,255,255,0.1); backdrop-filter: blur(5px); padding: 12px 20px; border-radius: 12px; display: flex; align-items: center; justify-content: flex-end; gap: 15px; border: 1px solid rgba(255,255,255,0.1); }
        .stat-info { text-align: right; }
        .stat-value { font-size: 24px; font-weight: 700; color: #FED404; line-height: 1; }
        .stat-label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.9; margin-top: 2px; }

        .back-btn { position: absolute; top: 15px; right: 35px; background: rgba(255,255,255,0.15); color: white; padding: 8px 16px; border-radius: 20px; font-size: 12px; font-weight: 600; text-decoration: none; display: flex; align-items: center; gap: 10px; transition: background 0.2s; z-index: 10; }
        .back-btn:hover { background: rgba(255,255,255,0.25); }

        .top-grid { display: grid; grid-template-columns: 2.5fr 1fr; gap: 25px; margin-bottom: 30px; }
        .chart-card { background: white; border-radius: 20px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); height: 100%; display: flex; flex-direction: column; }
        .chart-title { font-size: 15px; font-weight: 700; color: #197B40; margin-bottom: 15px; display: flex; align-items: center; gap: 8px; }

        /* --- TABLE STYLES --- */
        .table-card { background: white; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.03); overflow: hidden; margin-bottom: 40px; }
        .table-header-strip { background-color: #197B40; color: white; padding: 15px 25px; display: flex; justify-content: space-between; align-items: center; }
        .table-title { font-weight: 700; font-size: 16px; display: flex; align-items: center; gap: 10px; }
        .table-actions { display: flex; gap: 12px; align-items: center; }

        .search-box { background-color: white; border-radius: 50px; height: 35px; width: 250px; display: flex; align-items: center; padding: 0 15px; }
        .search-box img { width: 16px; height: 16px; margin-right: 8px; }
        .search-box input { border: none; background: transparent; outline: none; height: 100%; flex: 1; padding-left: 10px; font-size: 13px; color: #333; }
        
        .btn-export { height: 35px; padding: 0 20px; border: none; border-radius: 50px; background: white; color: #197B40; font-weight: 600; font-size: 13px; cursor: pointer; display: flex; align-items: center; gap: 8px; text-decoration: none; }
        .btn-export:hover { background-color: #f0fdf4; }

        .table-responsive { flex-grow: 1; overflow-y: auto; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px 25px; font-size: 12px; color: #555; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; background-color: #fff; border-bottom: 2px solid #eee; position: sticky; top: 0; z-index: 10; }
        td { padding: 16px 25px; font-size: 13px; color: #333; border-bottom: 1px solid #f9f9f9; vertical-align: middle; }
        
        .badge { padding: 6px 14px; border-radius: 6px; font-size: 11px; font-weight: 600; display: inline-block; letter-spacing: 0.3px; }
        .type-tech { background: #E3F2FD; color: #1565C0; border: 1px solid rgba(21, 101, 192, 0.1); }
        .type-soft { background: #FFF3E0; color: #EF6C00; border: 1px solid rgba(239, 108, 0, 0.1); }
        .type-default { background: #F5F5F5; color: #616161; }
        .type-info { background: #F3E5F5; color: #7B1FA2; border: 1px solid rgba(123, 31, 162, 0.1); }
        .method-online { background: #E0F2F1; color: #00695C; border: 1px solid rgba(0, 105, 92, 0.1); }
        .method-class { background: #FCE4EC; color: #C2185B; border: 1px solid rgba(194, 24, 91, 0.1); }
        
        .score-box { font-weight: 700; color: #197B40; background: rgba(25, 123, 64, 0.08); padding: 4px 8px; border-radius: 4px; }

        .pagination-container { padding: 20px 25px; display: flex; justify-content: space-between; align-items: center; font-size: 13px; color: #666; border-top: 1px solid #f9f9f9; }
        .pagination-controls { display: flex; align-items: center; gap: 8px; }
        .page-num { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 8px; cursor: pointer; text-decoration: none; color: #4a4a4a; font-weight: 500; }
        .page-num.active { background-color: #197B40; color: white; }
        .btn-next { display: flex; align-items: center; gap: 5px; color: #4a4a4a; text-decoration: none; cursor: pointer; }
    </style>
</head>
<body>

    <div class="main-wrapper">
        <nav class="navbar">
            <div class="logo-section"><img src="GGF White.png" alt="GGF Logo"></div>
            <div class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="reports.php">Trainings</a>
                <a href="employee_reports.php" class="active">Employees</a>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <a href="upload.php">Upload Data</a>
                    <a href="users.php">Users</a>
                <?php endif; ?>
            </div>
            <div class="nav-right">
                <div class="user-profile"><div class="avatar-circle"><?php echo $initials; ?></div></div>
                <a href="logout.php" class="btn-signout">Sign Out</a>
            </div>
        </nav>

        <div class="top-grid">
            
            <div class="hero-banner">
                <a href="employee_reports.php" class="back-btn"><i data-lucide="arrow-left" style="width:14px;"></i> Back</a>

                <div class="hero-mascot">
                    <img src="icons/Pina - Greetings.png" alt="Mascot">
                </div>
                
                <div class="hero-content">
                    <div>
                        <span class="hero-label">Employee Profile</span>
                        <h1 class="hero-name"><?php echo htmlspecialchars($employee['nama_karyawan']); ?></h1>
                        <span class="hero-id">ID: <?php echo htmlspecialchars($employee['index_karyawan']); ?></span>
                    </div>

                    <div class="hero-details-stack">
                        <div class="detail-row">
                            <i data-lucide="building-2"></i>
                            <span><?php echo htmlspecialchars($employee['bu'] ?? '-'); ?></span>
                        </div>
                        <div class="detail-row">
                            <i data-lucide="network"></i>
                            <span><?php echo htmlspecialchars($employee['func'] ?? '-'); ?></span>
                        </div>
                        <div class="detail-row">
                            <i data-lucide="git-branch"></i>
                            <span><?php echo htmlspecialchars($employee['func2'] ?? '-'); ?></span>
                        </div>
                    </div>
                </div>

                <div class="hero-stats-stack">
                    <div class="stat-card">
                        <div class="stat-info">
                            <div class="stat-value"><?php echo $total_sessions; ?></div>
                            <div class="stat-label">Trainings</div>
                        </div>
                        <i data-lucide="book-open" style="color:white; opacity:0.8;"></i>
                    </div>
                    <div class="stat-card">
                        <div class="stat-info">
                            <div class="stat-value"><?php echo number_format($total_hours, 1); ?></div>
                            <div class="stat-label">Hours</div>
                        </div>
                        <i data-lucide="clock" style="color:white; opacity:0.8;"></i>
                    </div>
                </div>
            </div>

            <div class="chart-card">
                <div class="chart-title"><i data-lucide="pie-chart" style="width:18px"></i> Training Focus</div>
                <div style="height: 80%; width: 80%; position: relative;">
                    <canvas id="mixChart"></canvas>
                </div>
            </div>

        </div>

        <div class="table-card">
            <div class="table-header-strip">
                <div class="table-title">
                    <i data-lucide="history" style="width:20px;"></i> 
                    Training History Log
                </div>
                <div class="table-actions">
                    <div class="search-box">
                        <img src="icons/search.ico" style="width: 26px; height: 26px; transform: scale(1.8); margin-right: 4px;" alt="Search">
                        <input type="text" id="searchInput" placeholder="Search training..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <a href="export_employee_report.php?id_karyawan=<?php echo $id_karyawan; ?>" id="exportBtn" class="btn-export">
                        <img src="icons/excel.ico" style="width: 26px; height: 26px; transform: scale(1.8); margin-right: 4px;">
                    </a>
                </div>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 30%;">Training Name</th>
                            <th>Date</th>
                            <th>Category</th> 
                            <th>Type</th> 
                            <th>Method</th>
                            <th style="text-align: center;">Credit</th>
                            <th style="text-align: center;">Pre Score</th>
                            <th style="text-align: center;">Post Score</th>
                        </tr>
                    </thead>
                    <tbody id="historyTableBody">
                        <?php if ($history_result->num_rows > 0): ?>
                            <?php while($row = $history_result->fetch_assoc()): 
                                $catClass = (stripos($row['category'], 'Technical') !== false) ? 'type-tech' : ((stripos($row['category'], 'Soft') !== false) ? 'type-soft' : 'type-default');
                                $methodClass = (stripos($row['method'], 'Online') !== false) ? 'method-online' : 'method-class';
                                
                                // FORMAT DATE RANGE HERE
                                $date_display = formatDateRange($row['date_start'], $row['date_end'] ?? '');
                            ?>
                            <tr>
                                <td>
                                    <div style="font-weight:600; color:#333; line-height:1.4;">
                                        <?php echo htmlspecialchars($row['nama_training']); ?>
                                    </div>
                                </td>
                                <td style="color:#666; font-family:'Poppins', sans-serif; font-size:12px; font-weight:500; white-space: nowrap;">
                                    <?php echo $date_display; ?>
                                </td>
                                <td><span class="badge <?php echo $catClass; ?>"><?php echo htmlspecialchars($row['category']); ?></span></td>
                                
                                <td><span class="badge type-info"><?php echo htmlspecialchars($row['training_type']); ?></span></td>
                                
                                <td><span class="badge <?php echo $methodClass; ?>"><?php echo htmlspecialchars($row['method']); ?></span></td>
                                
                                <td style="text-align: center; font-weight:600;"><?php echo htmlspecialchars($row['credit_hour']); ?></td>
                                <td style="text-align: center; color:#888;"><?php echo $row['pre']; ?></td>
                                <td style="text-align: center;">
                                    <span class="score-box"><?php echo $row['post']; ?></span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="8" style="text-align:center; padding: 30px; color:#999; font-style:italic;">No training history found for this employee.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="pagination-container" id="paginationContainer">
                <div>Showing <?php echo ($total_rows > 0 ? $offset + 1 : 0); ?> to <?php echo min($offset + $limit, $total_rows); ?> of <?php echo $total_rows; ?> Records</div>
                <div class="pagination-controls">
                    <?php if($page > 1): $prev = $page - 1; ?>
                        <a href="#" onclick="changePage(<?php echo $prev; ?>); return false;" class="btn-next" style="transform: rotate(180deg); display:inline-block;">
                            <i data-lucide="chevron-right" style="width:16px; height:16px;"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php for($i=1; $i<=$total_pages; $i++): ?>
                        <?php if ($i == 1 || $i == $total_pages || ($i >= $page - 1 && $i <= $page + 1)): ?>
                            <a href="#" onclick="changePage(<?php echo $i; ?>); return false;" class="page-num <?php if($i==$page) echo 'active'; ?>"><?php echo $i; ?></a>
                        <?php elseif ($i == $page - 2 || $i == $page + 2): ?>
                            <span class="dots">...</span>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if($page < $total_pages): $next = $page + 1; ?>
                        <a href="#" onclick="changePage(<?php echo $next; ?>); return false;" class="btn-next">
                            Next <i data-lucide="chevron-right" style="width:16px; height:16px;"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        lucide.createIcons();

        // --- CHART CONFIG ---
        const commonOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'right', labels: { font: { family: 'Poppins', size: 11 }, boxWidth: 10, usePointStyle: true } }
            },
            layout: { padding: 0 }
        };

        const mixCtx = document.getElementById('mixChart').getContext('2d');
        new Chart(mixCtx, {
            type: 'doughnut',    
            data: {
                labels: ['Technical', 'Soft Skills'],
                datasets: [{
                    data: [<?php echo $stats['count_tech']; ?>, <?php echo $stats['count_soft']; ?>],
                    backgroundColor: ['#1565C0', '#EF6C00'],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                ...commonOptions,
                cutout: '65%',
            }
        });

        // --- LIVE SEARCH SCRIPT (ADDED) ---
        const searchInput = document.getElementById('searchInput');
        const tableBody = document.getElementById('historyTableBody');
        const paginationContainer = document.getElementById('paginationContainer');
        const exportBtn = document.getElementById('exportBtn');
        const empId = "<?php echo $id_karyawan; ?>";

        function changePage(page) {
            const query = searchInput.value;
            fetchData(query, page);
        }

        function fetchData(query, page) {
            exportBtn.href = `export_employee_report.php?id_karyawan=${empId}&search=${encodeURIComponent(query)}`;
            
            fetch(`?id_karyawan=${empId}&ajax_search=${encodeURIComponent(query)}&page=${page}`)
                .then(response => response.json())
                .then(data => {
                    tableBody.innerHTML = data.table;
                    paginationContainer.innerHTML = data.pagination;
                    lucide.createIcons();
                })
                .catch(error => console.error('Error:', error));
        }

        function debounce(func, wait) {
            let timeout;
            return function(...args) {
                const context = this;
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(context, args), wait);
            };
        }

        const performSearch = debounce(function() {
            fetchData(searchInput.value, 1);
        }, 300);

        searchInput.addEventListener('input', performSearch);
    </script>
</body>
</html>