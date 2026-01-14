<?php
session_start();
require 'db_connect.php'; // Use your centralized database connection

// 1. Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 2. Get User Details
$username = $_SESSION['username'] ?? 'User';
$initials = strtoupper(substr($username, 0, 2));

// Validate ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: reports.php");
    exit();
}

$id_session = (int)$_GET['id'];
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
        $where_search = " AND (k.nama_karyawan LIKE '%$safe_search%' OR k.index_karyawan LIKE '%$safe_search%')";
    }

    // 1. Count Total
    $count_sql = "SELECT COUNT(*) as total FROM score s JOIN karyawan k ON s.id_karyawan = k.id_karyawan WHERE s.id_session = $id_session $where_search";
    $total_rows = $conn->query($count_sql)->fetch_assoc()['total'];
    $total_pages = ceil($total_rows / $limit);

    // 2. Fetch Data
    $list_sql = "
        SELECT 
            k.index_karyawan, k.nama_karyawan,
            b.nama_bu, f.func_n1,
            s.pre, s.post
        FROM score s
        JOIN karyawan k ON s.id_karyawan = k.id_karyawan
        LEFT JOIN bu b ON s.id_bu = b.id_bu
        LEFT JOIN func f ON s.id_func = f.id_func
        WHERE s.id_session = $id_session $where_search
        ORDER BY k.nama_karyawan ASC
        LIMIT $limit OFFSET $offset
    ";
    $participants = $conn->query($list_sql);

    // 3. Build Table Rows HTML
    ob_start();
    if ($participants->num_rows > 0) {
        while ($p = $participants->fetch_assoc()) {
            $improvement = $p['post'] - $p['pre'];
            $impSign = ($improvement > 0) ? '+' : '';
            $badgeClass = ($improvement >= 0) ? 'badge-improvement' : 'badge-decline';
            $initials = strtoupper(substr($p['nama_karyawan'], 0, 1) . substr(explode(' ', $p['nama_karyawan'])[1] ?? '', 0, 1));
            ?>
            <tr>
                <td><?php echo htmlspecialchars($p['index_karyawan']); ?></td>
                <td>
                    <div class="user-cell">
                        <div class="user-avatar"><?php echo $initials; ?></div> 
                        <?php echo htmlspecialchars($p['nama_karyawan']); ?>
                    </div>
                </td>
                <td><?php echo htmlspecialchars($p['nama_bu']); ?></td>
                <td><?php echo htmlspecialchars($p['func_n1']); ?></td>
                <td><?php echo $p['pre']; ?></td>
                <td><strong style="color:#197B40"><?php echo $p['post']; ?></strong></td>
                <td><span class="<?php echo $badgeClass; ?>"><?php echo $impSign . $improvement; ?></span></td>
            </tr>
            <?php
        }
    } else {
        echo '<tr><td colspan="7" style="text-align:center; padding: 20px; color:#888;">No participants found.</td></tr>';
    }
    $table_html = ob_get_clean();

    // 4. Build Pagination HTML
    ob_start();
    ?>
    <div>Showing <?php echo ($total_rows > 0 ? $offset + 1 : 0); ?> to <?php echo min($offset + $limit, $total_rows); ?> of <?php echo $total_rows; ?> Records</div>
    <div class="pagination-controls">
        <?php if($page > 1): $prev = $page - 1; echo "<a href='?id=$id_session&page=$prev&search=$search_term' class='page-btn'>&lt;</a>"; endif; ?>
        <a href="#" class="page-btn active"><?php echo $page; ?></a>
        <?php if($page < $total_pages): $next = $page + 1; echo "<a href='?id=$id_session&page=$next&search=$search_term' class='page-btn'>&gt;</a>"; endif; ?>
    </div>
    <?php
    $pagination_html = ob_get_clean();

    // Return JSON
    echo json_encode(['table' => $table_html, 'pagination' => $pagination_html]);
    exit();
}
// ==========================================
//  END AJAX HANDLER
// ==========================================


// --- 1. GET SESSION METADATA ---
$meta_sql = "
    SELECT t.nama_training, ts.code_sub, ts.date_start 
    FROM training_session ts 
    JOIN training t ON ts.id_training = t.id_training 
    WHERE ts.id_session = $id_session
";
$meta_result = $conn->query($meta_sql);

if ($meta_result->num_rows == 0) {
    echo "Session not found.";
    exit();
}

$meta = $meta_result->fetch_assoc();
$training_name = htmlspecialchars($meta['nama_training']);
$code_sub = htmlspecialchars($meta['code_sub']);
$training_date = date('d M Y', strtotime($meta['date_start']));

// --- 2. CALCULATE STATS ---
$stats_sql = "
    SELECT 
        COUNT(id_score) as total,
        AVG(pre) as avg_pre,
        AVG(post) as avg_post,
        AVG(statis_subject) as avg_subject,
        AVG(instructor) as avg_instructor,
        AVG(statis_infras) as avg_infras,
        SUM(CASE WHEN pre BETWEEN 0 AND 20 THEN 1 ELSE 0 END) as pre_0_20,
        SUM(CASE WHEN pre BETWEEN 21 AND 40 THEN 1 ELSE 0 END) as pre_21_40,
        SUM(CASE WHEN pre BETWEEN 41 AND 60 THEN 1 ELSE 0 END) as pre_41_60,
        SUM(CASE WHEN pre BETWEEN 61 AND 80 THEN 1 ELSE 0 END) as pre_61_80,
        SUM(CASE WHEN pre BETWEEN 81 AND 100 THEN 1 ELSE 0 END) as pre_81_100,
        SUM(CASE WHEN post BETWEEN 0 AND 20 THEN 1 ELSE 0 END) as post_0_20,
        SUM(CASE WHEN post BETWEEN 21 AND 40 THEN 1 ELSE 0 END) as post_21_40,
        SUM(CASE WHEN post BETWEEN 41 AND 60 THEN 1 ELSE 0 END) as post_41_60,
        SUM(CASE WHEN post BETWEEN 61 AND 80 THEN 1 ELSE 0 END) as post_61_80,
        SUM(CASE WHEN post BETWEEN 81 AND 100 THEN 1 ELSE 0 END) as post_81_100
    FROM score 
    WHERE id_session = $id_session
";
$stats = $conn->query($stats_sql)->fetch_assoc();

$total_participants = $stats['total'] > 0 ? $stats['total'] : 1; 
$avg_pre = number_format($stats['avg_pre'] ?? 0, 1);
$avg_post = number_format($stats['avg_post'] ?? 0, 1);

// Satisfaction Scores
$sat_subject = $stats['avg_subject'] ? number_format($stats['avg_subject'], 1) : '-';
$sat_instructor = $stats['avg_instructor'] ? number_format($stats['avg_instructor'], 1) : '-';
$sat_infras = $stats['avg_infras'] ? number_format($stats['avg_infras'], 1) : '-';

// --- 3. FETCH TOP 3 IMPROVERS (UPDATED LOGIC) ---
// ORDER BY improvement DESC (Highest Gain)
// THEN BY s.pre ASC (Lowest Starting Score gets priority)
$top_sql = "
    SELECT k.nama_karyawan, (s.post - s.pre) as improvement, s.post, s.pre
    FROM score s 
    JOIN karyawan k ON s.id_karyawan = k.id_karyawan
    WHERE s.id_session = $id_session
    ORDER BY improvement DESC, s.pre ASC
    LIMIT 3
";
$top_improvers = $conn->query($top_sql);

// --- 4. FETCH TABLE DATA ---
$where_search = "";
if (!empty($search)) {
    $safe_search = $conn->real_escape_string($search);
    $where_search = " AND (k.nama_karyawan LIKE '%$safe_search%' OR k.index_karyawan LIKE '%$safe_search%')";
}

$count_sql = "SELECT COUNT(*) as total FROM score s JOIN karyawan k ON s.id_karyawan = k.id_karyawan WHERE s.id_session = $id_session $where_search";
$total_rows = $conn->query($count_sql)->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

$list_sql = "
    SELECT 
        k.index_karyawan, k.nama_karyawan,
        b.nama_bu, f.func_n1,
        s.pre, s.post
    FROM score s
    JOIN karyawan k ON s.id_karyawan = k.id_karyawan
    LEFT JOIN bu b ON s.id_bu = b.id_bu
    LEFT JOIN func f ON s.id_func = f.id_func
    WHERE s.id_session = $id_session $where_search
    ORDER BY k.nama_karyawan ASC
    LIMIT $limit OFFSET $offset
";
$participants = $conn->query($list_sql);

// Prepare Histogram Data for JS
$histLabels = ['0-20', '21-40', '41-60', '61-80', '81-100'];
$postHistData = [
    $stats['post_0_20'], 
    $stats['post_21_40'], 
    $stats['post_41_60'], 
    $stats['post_61_80'], 
    $stats['post_81_100']
];
$preHistData = [
    $stats['pre_0_20'], 
    $stats['pre_21_40'], 
    $stats['pre_41_60'], 
    $stats['pre_61_80'], 
    $stats['pre_81_100']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GGF - Training Details</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="icons/icon.png">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Poppins', sans-serif; }
        body { background-color: #117054; padding: 0; margin: 0; overflow: hidden; height: 100vh; }
        .main-wrapper { background-color: #f3f4f7; padding: 20px 40px; height: 100vh; overflow-y: auto; width: 100%; position: relative; }
        
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
            background: linear-gradient(135deg, #197B40 0%, #115c32 100%);
            border-radius: 25px; padding: 30px 50px; margin-bottom: 30px; position: relative; overflow: hidden; display: flex; align-items: center; justify-content: space-between; color: white; box-shadow: 0 10px 25px rgba(25, 123, 64, 0.2);
        }
        .hero-banner::before { content: ''; position: absolute; left: -50px; bottom: -50px; width: 300px; height: 300px; border-radius: 50%; background: rgba(255,255,255,0.05); pointer-events: none; }
        .hero-left { display: flex; align-items: center; gap: 30px; position: relative; z-index: 2; }
        .mascot-img { height: 150px; width: auto; filter: drop-shadow(0 10px 10px rgba(0,0,0,0.2)); transform: scaleX(-1); }
        .hero-text h4 { font-size: 14px; opacity: 0.8; font-weight: 500; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 1px; }
        .hero-text h1 { font-size: 32px; font-weight: 700; margin-bottom: 10px; line-height: 1.2; }
        .hero-meta { display: flex; align-items: center; gap: 10px; font-size: 14px; opacity: 0.9; }
        .hero-stats { display: flex; gap: 30px; position: relative; z-index: 2; }
        .h-stat-box { text-align: right; }
        .h-stat-val { font-size: 42px; font-weight: 700; color: #fff; line-height: 1; }
        .h-stat-lbl { font-size: 12px; opacity: 0.8; margin-top: 5px; text-transform: uppercase; font-weight: 600; }
        .h-stat-box.highlight .h-stat-val { color: #FED404; }
        .back-btn { position: absolute; top: 20px; right: 20px; background: rgba(255,255,255,0.15); color: white; padding: 8px 16px; border-radius: 20px; font-size: 12px; font-weight: 600; text-decoration: none; display: flex; align-items: center; gap: 6px; transition: background 0.2s; z-index: 10; }
        .back-btn:hover { background: rgba(255,255,255,0.25); }

        /* --- 3-COLUMN GRID: Pre Chart | Post Chart | Satisfaction --- */
        .charts-container { display: grid; grid-template-columns: 1fr 1fr 280px; gap: 20px; margin-bottom: 30px; }
        
        .chart-card { background: white; border-radius: 20px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); height: 100%; }
        .chart-title { font-size: 15px; font-weight: 700; color: #197B40; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }

        .satisfaction-stack { display: flex; flex-direction: column; gap: 15px; height: 100%; }
        .stat-card-small { 
            background: white; border-radius: 15px; padding: 15px 20px; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.03); 
            display: flex; align-items: center; justify-content: space-between; 
            flex: 1; 
        }
        .sat-left { display: flex; align-items: center; gap: 15px; }
        .sat-icon-box { 
            width: 45px; height: 45px; border-radius: 12px; 
            display: flex; align-items: center; justify-content: center; font-size: 20px; 
        }
        .sat-text h5 { font-size: 13px; color: #555; margin-bottom: 2px; font-weight: 600; }
        .sat-text p { font-size: 11px; color: #999; margin: 0; }
        .sat-value { font-size: 20px; font-weight: 700; color: #197b40; }

        /* --- LEADERBOARD --- */
        .section-header { margin-bottom: 15px; font-size: 18px; font-weight: 700; color: #333; display: flex; align-items: center; gap: 10px; }
        .improver-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .improver-card { background: white; border-radius: 15px; padding: 20px; display: flex; align-items: center; gap: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); border-bottom: 4px solid #eee; }
        .improver-card.gold { border-bottom-color: #FFD700; }
        .improver-card.gold .medal-icon { background: transparent; }
        .improver-card.silver { border-bottom-color: #C0C0C0; }
        .improver-card.silver .medal-icon { background: transparent; }
        .improver-card.bronze { border-bottom-color: #CD7F32; }
        .improver-card.bronze .medal-icon { background: transparent; }
        .medal-icon { width: 75px; height: 75px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .medal-icon img { width: 100%; height: 100%; object-fit: contain; filter: drop-shadow(0 4px 4px rgba(0,0,0,0.1)); }
        .imp-info h4 { font-size: 14px; font-weight: 700; color: #333; margin-bottom: 2px; }
        .imp-info p { font-size: 12px; color: #777; }
        .imp-score { margin-left: auto; font-size: 18px; font-weight: 800; color: #197B40; margin-right:10px}

        /* --- TABLE --- */
        .table-card { background: white; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.03); overflow: hidden; margin-bottom: 40px; }
        .table-header-strip { background-color: #197b40; color: white; padding: 15px 25px; display: flex; justify-content: space-between; align-items: center; }
        .table-title { font-weight: 600; font-size: 16px; }
        .table-actions { display: flex; gap: 12px; align-items: center; }
        .search-box { background-color: white; border-radius: 50px; height: 35px; width: 250px; display: flex; align-items: center; padding: 0 15px; }
        .search-box i { color: #197B40; width: 16px; height: 16px; }
        .search-box input { border: none; background: transparent; outline: none; height: 100%; flex: 1; padding-left: 10px; font-size: 13px; color: #333; }
        .btn-export { height: 35px; padding: 0 20px; border: none; border-radius: 50px; background: white; color: #197B40; font-weight: 600; font-size: 13px; cursor: pointer; display: flex; align-items: center; gap: 8px; text-decoration: none; }
        .btn-export:hover { background-color: #f0fdf4; }

        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px 25px; font-size: 12px; color: #888; font-weight: 600; border-bottom: 1px solid #eee; }
        td { padding: 15px 25px; font-size: 13px; color: #333; border-bottom: 1px solid #f9f9f9; vertical-align: middle; }
        .user-cell { display: flex; align-items: center; gap: 10px; font-weight: 600; }
        .user-avatar { width: 32px; height: 32px; border-radius: 50%; background-color: #197B40; color: white; font-size: 11px; display: flex; align-items: center; justify-content: center; font-weight: bold; }
        .badge-improvement { background-color: #dcfce7; color: #15803d; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; display: inline-block; }
        .badge-decline { background-color: #fee2e2; color: #991b1b; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; display: inline-block; }
        .pagination-container { padding: 20px 25px; display: flex; justify-content: space-between; align-items: center; font-size: 13px; color: #666; }
        .page-btn { width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; border-radius: 6px; cursor: pointer; color: #666; text-decoration: none; }
        .page-btn.active { background-color: #197B40; color: white; font-weight: 600; }
    </style>
</head>
<body>

    <div class="main-wrapper">
        <nav class="navbar">
            <div class="logo-section"><img src="GGF White.png" alt="GGF Logo"></div>
            <div class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="reports.php">Trainings</a>
                <a href="employee_reports.php">Employees</a>
                <a href="upload.php">Upload Data</a>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <a href="users.php">Users</a>
                <?php endif; ?>
            </div>
            <div class="nav-right">
                <div class="user-profile"><div class="avatar-circle"><?php echo $initials; ?></div></div>
                <a href="logout.php" class="btn-signout">Sign Out</a>
            </div>
        </nav>

        <div class="hero-banner">
            <a href="reports.php" class="back-btn"><i data-lucide="arrow-left" style="width:14px;"></i> Back</a>
            
            <div class="hero-left">
                <img src="icons/Pina - Info.png" alt="Mascot" class="mascot-img">
                <div class="hero-text">
                    <h4>Training Session Report</h4>
                    <h1><?php echo $training_name; ?></h1>
                    <div style="font-size: 13px; opacity: 0.8; margin-bottom: 5px;">
                        Code: <strong><?php echo $code_sub; ?></strong>
                    </div>
                    <div class="hero-meta">
                        <i data-lucide="calendar" style="width:16px;"></i> <?php echo $training_date; ?>
                        <span style="margin: 0 10px;">|</span>
                        <i data-lucide="users" style="width:16px;"></i> <?php echo $total_participants; ?> Participants
                    </div>
                </div>
            </div>

            <div class="hero-stats">
                <div class="h-stat-box">
                    <div class="h-stat-val"><?php echo $avg_pre; ?></div>
                    <div class="h-stat-lbl">Avg Pre-Test</div>
                </div>
                <div class="h-stat-box highlight">
                    <div class="h-stat-val"><?php echo $avg_post; ?></div>
                    <div class="h-stat-lbl">Avg Post-Test</div>
                </div>
            </div>
        </div>

        <div class="charts-container">
            <div class="chart-card">
                <div class="chart-title"><i data-lucide="bar-chart-2"></i> Pre-Test Distribution</div>
                <div style="height: 300px; width: 100%;">
                    <canvas id="preHistogram"></canvas>
                </div>
            </div>

            <div class="chart-card">
                <div class="chart-title"><i data-lucide="trending-up"></i> Post-Test Distribution</div>
                <div style="height: 300px; width: 100%;">
                    <canvas id="postHistogram"></canvas>
                </div>
            </div>

            <div class="satisfaction-stack">
                <div class="stat-card-small">
                    <div class="sat-left">
                        <div class="sat-icon-box" style="background:#e3f2fd; color:#1976d2;">
                            <i data-lucide="book"></i>
                        </div>
                        <div class="sat-text">
                            <h5>Subject</h5>
                            <p>Satisfaction</p>
                        </div>
                    </div>
                    <div class="sat-value"><?php echo $sat_subject; ?></div>
                </div>

                <div class="stat-card-small">
                    <div class="sat-left">
                        <div class="sat-icon-box" style="background:#e8f5e9; color:#2e7d32;">
                            <i data-lucide="user-check"></i>
                        </div>
                        <div class="sat-text">
                            <h5>Instructor</h5>
                            <p>Rating</p>
                        </div>
                    </div>
                    <div class="sat-value"><?php echo $sat_instructor; ?></div>
                </div>

                <div class="stat-card-small">
                    <div class="sat-left">
                        <div class="sat-icon-box" style="background:#fff3e0; color:#f57c00;">
                            <i data-lucide="building-2"></i>
                        </div>
                        <div class="sat-text">
                            <h5>Facilities</h5>
                            <p>Infrastructure</p>
                        </div>
                    </div>
                    <div class="sat-value"><?php echo $sat_infras; ?></div>
                </div>
            </div>
        </div>

        <div class="section-header">
            <i data-lucide="trophy" color="#FF9A02"></i> Top Improvers
        </div>
        <div class="improver-grid">
            <?php 
            $ranks = ['gold', 'silver', 'bronze'];
            $i = 0;
            while($top = $top_improvers->fetch_assoc()): 
                $rankClass = $ranks[$i] ?? 'bronze';
                $initials = strtoupper(substr($top['nama_karyawan'], 0, 1));
            ?>
            <div class="improver-card <?php echo $rankClass; ?>">
                <div class="medal-icon">
                    <?php if($i==0) echo '<img src="icons/First Place Badge.ico" alt="Gold Medal">'; elseif($i==1) echo '<img src="icons/Second Place Badge.ico" alt="Silver Medal">'; else echo '<img src="icons/Third Place Badge.ico" alt="Bronze Medal">'; ?>
                </div>
                <div class="imp-info">
                    <h4><?php echo htmlspecialchars($top['nama_karyawan']); ?></h4>
                    <p>Pre: <?php echo $top['pre']; ?> -> Post: <?php echo $top['post']; ?></p>
                </div>
                <div class="imp-score">+<?php echo $top['improvement']; ?></div>
            </div>
            <?php $i++; endwhile; ?>
            
            <?php if($i == 0): ?>
                <div class="improver-card"><p>No score data available yet.</p></div>
            <?php endif; ?>
        </div>

        <div class="table-card">
            <div class="table-header-strip">
                <div class="table-title">Full Participant List</div>
                <div class="table-actions">
                    <div class="search-box">
                        <img src="icons/search.ico" style="width: 26px; height: 26px; transform: scale(1.8); margin-right: 4px;">
                        <input type="text" id="searchInput" placeholder="Search Employee..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <a href="export_report.php?id=<?php echo $id_session; ?>" id="exportBtn" class="btn-export">
                        <img src="icons/excel.ico" style="width: 26px; height: 26px; transform: scale(1.8); margin-right: 4px;">
                    </a>
                </div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Index</th>
                        <th>Name</th>
                        <th>BU</th>
                        <th>Function</th>
                        <th>Pre-Test</th>
                        <th>Post-Test</th>
                        <th>Improvement</th>
                    </tr>
                </thead>
                <tbody id="participantTableBody">
                    <?php if($participants->num_rows > 0): ?>
                        <?php while($p = $participants->fetch_assoc()): 
                            $improvement = $p['post'] - $p['pre'];
                            $impSign = ($improvement > 0) ? '+' : '';
                            $badgeClass = ($improvement >= 0) ? 'badge-improvement' : 'badge-decline';
                            $initials = strtoupper(substr($p['nama_karyawan'], 0, 1) . substr(explode(' ', $p['nama_karyawan'])[1] ?? '', 0, 1));
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($p['index_karyawan']); ?></td>
                            <td>
                                <div class="user-cell">
                                    <div class="user-avatar"><?php echo $initials; ?></div> 
                                    <?php echo htmlspecialchars($p['nama_karyawan']); ?>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($p['nama_bu']); ?></td>
                            <td><?php echo htmlspecialchars($p['func_n1']); ?></td>
                            <td><?php echo $p['pre']; ?></td>
                            <td><strong style="color:#197B40"><?php echo $p['post']; ?></strong></td>
                            <td><span class="<?php echo $badgeClass; ?>"><?php echo $impSign . $improvement; ?></span></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7" style="text-align:center; padding: 20px; color:#888;">No participants found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="pagination-container" id="paginationContainer">
                <div>Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_rows); ?> of <?php echo $total_rows; ?> Records</div>
                <div class="pagination-controls">
                    <?php if($page > 1): $prev = $page - 1; echo "<a href='?id=$id_session&page=$prev&search=$search' class='page-btn'>&lt;</a>"; endif; ?>
                    <a href="#" class="page-btn active"><?php echo $page; ?></a>
                    <?php if($page < $total_pages): $next = $page + 1; echo "<a href='?id=$id_session&page=$next&search=$search' class='page-btn'>&gt;</a>"; endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        lucide.createIcons();

        // --- CHART CONFIGURATION ---
        const commonOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    titleFont: { family: 'Poppins' },
                    bodyFont: { family: 'Poppins' }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { borderDash: [5, 5], color: '#f0f0f0' },
                    ticks: { font: { family: 'Poppins' }, precision: 0 }
                },
                x: {
                    grid: { display: false },
                    ticks: { font: { family: 'Poppins' } }
                }
            }
        };

        const labels = <?php echo json_encode($histLabels); ?>;

        // 1. Pre-Test Chart
        new Chart(document.getElementById('preHistogram'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Participants',
                    data: <?php echo json_encode($preHistData); ?>,
                    backgroundColor: '#FF9A02', // Orange for Pre-Test
                    borderRadius: 5,
                    barPercentage: 0.8
                }]
            },
            options: commonOptions
        });

        // 2. Post-Test Chart
        new Chart(document.getElementById('postHistogram'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Participants',
                    data: <?php echo json_encode($postHistData); ?>,
                    backgroundColor: '#197B40', // Green for Post-Test
                    borderRadius: 5,
                    barPercentage: 0.8
                }]
            },
            options: commonOptions
        });

        // --- LIVE SEARCH SCRIPT ---
        const searchInput = document.getElementById('searchInput');
        const tableBody = document.getElementById('participantTableBody');
        const paginationContainer = document.getElementById('paginationContainer');
        const exportBtn = document.getElementById('exportBtn');
        const sessionId = "<?php echo $id_session; ?>";

        function debounce(func, wait) {
            let timeout;
            return function(...args) {
                const context = this;
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(context, args), wait);
            };
        }

        const performSearch = debounce(function() {
            const query = searchInput.value;
            exportBtn.href = `export_session.php?id=${sessionId}&search=${encodeURIComponent(query)}`;

            fetch(`?id=${sessionId}&ajax_search=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    tableBody.innerHTML = data.table;
                    paginationContainer.innerHTML = data.pagination;
                    lucide.createIcons();
                })
                .catch(error => console.error('Error:', error));
        }, 300);

        searchInput.addEventListener('input', performSearch);
    </script>
</body>
</html>