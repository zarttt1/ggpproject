<?php
session_start();
require 'db_connect.php';

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

// --- 1. GET SESSION METADATA ---
$meta_sql = "
    SELECT t.nama_training, ts.date_start 
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
$training_date = date('Y-m-d', strtotime($meta['date_start']));

// --- 2. CALCULATE STATS & DISTRIBUTION ---
$stats_sql = "
    SELECT 
        COUNT(id_score) as total,
        AVG(pre) as avg_pre,
        AVG(post) as avg_post,
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

$total_participants = $stats['total'] > 0 ? $stats['total'] : 1; // Prevent division by zero
$avg_pre = number_format($stats['avg_pre'] ?? 0, 1);
$avg_post = number_format($stats['avg_post'] ?? 0, 1);

// Helper function to calculate width percentage
function calcWidth($count, $total) {
    return ($total > 0) ? round(($count / $total) * 100) : 0;
}

// --- 3. FETCH PARTICIPANTS (Table) ---
$where_search = "";
if (!empty($search)) {
    $safe_search = $conn->real_escape_string($search);
    $where_search = " AND (k.nama_karyawan LIKE '%$safe_search%' OR k.index_karyawan LIKE '%$safe_search%')";
}

// Count total for pagination
$count_sql = "SELECT COUNT(*) as total FROM score s JOIN karyawan k ON s.id_karyawan = k.id_karyawan WHERE s.id_session = $id_session $where_search";
$total_rows = $conn->query($count_sql)->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

// Fetch Data
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

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GGF - Training Details</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* [Keep your exact CSS from tdetails.html] */
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Poppins', sans-serif; }
        body { background-color: #117054; padding: 0; margin: 0; overflow: hidden; height: 100vh; }
        .main-wrapper { background-color: #f3f4f7; padding: 20px 40px; height: 100vh; overflow-y: auto; width: 100%; position: relative; }
        
        /* Navbar */
        .navbar { background-color: #197B40; height: 70px; border-radius: 50px; display: flex; align-items: center; padding: 0 30px; justify-content: space-between; margin-bottom: 30px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .logo-section img { height: 40px; }
        .nav-links { display: flex; gap: 30px; align-items: center; }
        .nav-links a { color: white; text-decoration: none; font-size: 14px; font-weight: 600; opacity: 0.8; transition: 0.3s; }
        .nav-links a:hover { opacity: 1; }
        .nav-links a.active { background: white; color: #197B40; padding: 8px 20px; border-radius: 20px; opacity: 1; }
        .user-profile { display: flex; align-items: center; gap: 12px; color: white; }
        .avatar-circle { width: 35px; height: 35px; background-color: #FF9A02; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 12px; }

        /* Header */
        .page-header-card { background: white; border-radius: 20px; padding: 25px 30px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); border-left: 5px solid #197B40; }
        .back-link { color: #197B40; text-decoration: none; font-size: 13px; font-weight: 500; display: flex; align-items: center; gap: 5px; margin-bottom: 15px; }
        .header-content { display: flex; justify-content: space-between; align-items: flex-end; }
        .title-group h1 { font-size: 28px; font-weight: 700; color: #000; margin-bottom: 5px; }
        .title-group .date { color: #666; font-size: 14px; }

        /* Stats Grid */
        .stats-grid { display: grid; grid-template-columns: 2.5fr 1fr; gap: 20px; margin-bottom: 30px; align-items: stretch; }
        .dist-card { background: white; border-radius: 20px; padding: 25px; display: grid; grid-template-columns: 1fr 1fr; gap: 30px; box-shadow: 0 5px 20px rgba(0,0,0,0.03); }
        .dist-column { display: flex; flex-direction: column; justify-content: center; }
        .dist-title { color: #197B40; font-weight: 700; font-size: 15px; margin-bottom: 20px; }
        
        /* Bar Charts */
        .bar-row { display: flex; align-items: center; margin-bottom: 15px; font-size: 12px; }
        .bar-row:last-child { margin-bottom: 0; }
        .bar-label { width: 40px; color: #333; font-weight: 500; }
        .bar-track { flex: 1; height: 8px; background-color: #f0f0f0; border-radius: 4px; margin: 0 10px; overflow: hidden; }
        .bar-fill { height: 100%; background-color: #117054; border-radius: 4px; }
        .bar-count { width: 20px; text-align: right; font-weight: 700; color: #333; }

        /* Stats Right */
        .stats-right-col { display: flex; flex-direction: column; gap: 20px; height: 100%; }
        .stat-box { background: white; border-radius: 20px; padding: 20px 25px; display: flex; flex-direction: column; justify-content: center; box-shadow: 0 5px 20px rgba(0,0,0,0.03); flex: 1; border-bottom: 5px solid #FF9A02; }
        .stat-box:first-child { border-bottom: 5px solid #197B40; }
        .stat-icon { width: 35px; height: 35px; background: #e8f5e9; color: #197B40; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-bottom: 10px; }
        .stat-label { font-size: 12px; color: #666; margin-bottom: 4px; }
        .stat-value { font-size: 28px; font-weight: 800; color: #333; line-height: 1; }
        .stat-value span { font-size: 14px; color: #999; font-weight: 500; margin-left: 2px; }

        /* Table */
        .table-card { background: white; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.03); overflow: hidden; margin-bottom: 40px; }
        .table-header-strip { background-color: #117054; color: white; padding: 15px 25px; display: flex; justify-content: space-between; align-items: center; border-top-left-radius: 12px; border-top-right-radius: 12px; }
        .table-title { font-weight: 600; font-size: 16px; }
        .table-actions { display: flex; gap: 12px; align-items: center; }
        
        .search-box { background-color: white; border-radius: 50px; height: 40px; width: 250px; display: flex; align-items: center; padding: 0 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .search-box i { color: #197B40; width: 18px; height: 18px; display: flex; align-items: center; justify-content: center; }
        .search-box input { border: none; background: transparent; outline: none; height: 100%; flex: 1; padding-left: 10px; font-size: 13px; color: #333; }

        .btn-export { height: 40px; padding: 0 25px; border: none; border-radius: 50px; background: white; color: #197B40; font-weight: 600; font-size: 13px; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 8px; white-space: nowrap; }
        .btn-export:hover { background-color: #f0fdf4; transform: translateY(-1px); }

        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px 25px; font-size: 12px; color: #666; font-weight: 600; border-bottom: 1px solid #eee; }
        td { padding: 15px 25px; font-size: 13px; color: #333; border-bottom: 1px solid #f9f9f9; vertical-align: middle; }
        .user-cell { display: flex; align-items: center; gap: 10px; font-weight: 600; }
        .user-avatar { width: 32px; height: 32px; border-radius: 50%; background-color: #197B40; color: white; font-size: 11px; display: flex; align-items: center; justify-content: center; font-weight: bold; }
        
        .score-good { color: #197B40; font-weight: 700; }
        .score-bad { color: #dc2626; font-weight: 700; }
        .score-neutral { color: #333; font-weight: 600; }
        .badge-improvement { background-color: #dcfce7; color: #15803d; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; display: inline-block; }

        .pagination-container { padding: 20px 25px; display: flex; justify-content: space-between; align-items: center; font-size: 13px; color: #666; }
        .pagination-controls { display: flex; align-items: center; gap: 5px; }
        .page-btn { width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; border-radius: 6px; cursor: pointer; color: #666; text-decoration: none; }
        .page-btn.active { background-color: #197B40; color: white; font-weight: 600; }
        .page-btn:hover:not(.active) { background-color: #f3f4f7; }
        .btn-next { display: flex; align-items: center; gap: 5px; cursor: pointer; color: #333; font-weight: 500; text-decoration: none; }
    </style>
</head>
<body>

    <div class="main-wrapper">
        <nav class="navbar">
            <div class="logo-section"><img src="GGF_logo024_putih.png" alt="GGF Logo"></div>
            <div class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="reports.php" class="active">Reports</a>
                <a href="upload.php">Upload Data</a>
            </div>
            <div class="user-profile"><div class="avatar-circle">AD</div></div>
        </nav>

        <div class="page-header-card">
            <a href="reports.php" class="back-link">
                <i data-lucide="arrow-left" style="width:16px;"></i> Back to Training List
            </a>
            <div class="header-content">
                <div class="title-group">
                    <h1><?php echo $training_name; ?></h1>
                    <div class="date">Training Date: <?php echo $training_date; ?></div>
                </div>
            </div>
        </div>

        <div class="stats-grid">
            <div class="dist-card">
                <div class="dist-column">
                    <div class="dist-title">Pre-Test Score Distribution</div>
                    <?php 
                    $ranges = ['0_20' => '0-20', '21_40' => '21-40', '41_60' => '41-60', '61_80' => '61-80', '81_100' => '81-100'];
                    foreach($ranges as $key => $label): 
                        $count = $stats['pre_' . $key];
                        $pct = calcWidth($count, $total_participants);
                    ?>
                    <div class="bar-row">
                        <span class="bar-label"><?php echo $label; ?></span>
                        <div class="bar-track"><div class="bar-fill" style="width: <?php echo $pct; ?>%;"></div></div>
                        <span class="bar-count"><?php echo $count; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="dist-column">
                    <div class="dist-title">Post-Test Score Distribution</div>
                    <?php 
                    foreach($ranges as $key => $label): 
                        $count = $stats['post_' . $key];
                        $pct = calcWidth($count, $total_participants);
                    ?>
                    <div class="bar-row">
                        <span class="bar-label"><?php echo $label; ?></span>
                        <div class="bar-track"><div class="bar-fill" style="width: <?php echo $pct; ?>%;"></div></div>
                        <span class="bar-count"><?php echo $count; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="stats-right-col">
                <div class="stat-box">
                    <div class="stat-icon"><i data-lucide="file-text"></i></div>
                    <div class="stat-label">Avg. Pre-Test Score</div>
                    <div class="stat-value"><?php echo $avg_pre; ?><span>/100</span></div>
                </div>
                <div class="stat-box">
                    <div class="stat-icon"><i data-lucide="file-check"></i></div>
                    <div class="stat-label">Avg. Post-Test Score</div>
                    <div class="stat-value"><?php echo $avg_post; ?><span>/100</span></div>
                </div>
            </div>
        </div>

        <div class="table-card">
            <div class="table-header-strip">
                <div class="table-title">Participant Performance</div>
                
                <div class="table-actions">
                    <form action="" method="GET" class="search-box">
                        <input type="hidden" name="id" value="<?php echo $id_session; ?>">
                        <i data-lucide="search" color="#197B40"></i>
                        <input type="text" name="search" placeholder="Search Employee Name..." value="<?php echo htmlspecialchars($search); ?>">
                    </form>
                    
                    <button class="btn-export" onclick="window.location.href='export_session.php?id=<?php echo $id_session; ?>'">
                        <i data-lucide="file-spreadsheet" style="width:16px;"></i>
                        Export
                    </button>
                </div>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Index</th>
                        <th>Name</th>
                        <th>BU</th>
                        <th>Function</th>
                        <th>Pre-Test Score</th>
                        <th>Post-Test Score</th>
                        <th>Improvement</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($participants->num_rows > 0): ?>
                        <?php while($p = $participants->fetch_assoc()): 
                            $improvement = $p['post'] - $p['pre'];
                            $impSign = ($improvement > 0) ? '+' : '';
                            // Helper for avatars
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
                            <td><span class="score-neutral"><?php echo $p['pre']; ?></span></td>
                            <td><span class="score-good"><?php echo $p['post']; ?></span></td>
                            <td><span class="badge-improvement"><?php echo $impSign . $improvement; ?></span></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7" style="text-align:center; padding: 20px; color:#888;">No participants found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="pagination-container">
                <div>Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_rows); ?> of <?php echo $total_rows; ?> Records</div>
                <div class="pagination-controls">
                    <?php 
                    // Simple Pagination
                    if($page > 1): 
                        $prev = $page - 1;
                        echo "<a href='?id=$id_session&page=$prev&search=$search' class='page-btn'>&lt;</a>";
                    endif; 

                    for($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): 
                        $active = ($i == $page) ? 'active' : '';
                        echo "<a href='?id=$id_session&page=$i&search=$search' class='page-btn $active'>$i</a>";
                    endfor; 

                    if($page < $total_pages): 
                        $next = $page + 1;
                        echo "<a href='?id=$id_session&page=$next&search=$search' class='btn-next'>Next <i data-lucide='chevron-right' style='width:14px;'></i></a>";
                    endif; 
                    ?>
                </div>
            </div>
        </div>

    </div>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        lucide.createIcons();
    </script>
</body>
</html>