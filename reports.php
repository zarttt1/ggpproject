<?php
session_start();
require 'db_connect.php'; 

if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

$username = $_SESSION['username'] ?? 'User';
$initials = strtoupper(substr($username, 0, 2));

$search = $_GET['search'] ?? '';
$filter_type = $_GET['type'] ?? 'All Types';
$filter_method = $_GET['method'] ?? 'All Methods';
$start_date = $_GET['start'] ?? '';
$end_date = $_GET['end'] ?? '';

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// --- AJAX HANDLER ---
if (isset($_GET['ajax_search'])) {
    $search_term = $_GET['ajax_search'];
    $where_ajax = ["1=1"];
    if (!empty($search_term)) {
        $safe_search = $conn->real_escape_string($search_term);
        // Added search capability for code_sub
        $where_ajax[] = "(t.nama_training LIKE '%$safe_search%' OR ts.code_sub LIKE '%$safe_search%')";
    }
    $where_sql_ajax = implode(' AND ', $where_ajax);

    $count_sql = "SELECT COUNT(DISTINCT ts.id_session) as total FROM training_session ts JOIN training t ON ts.id_training = t.id_training WHERE $where_sql_ajax";
    $total_records = $conn->query($count_sql)->fetch_assoc()['total'];
    
    // FETCH DATA including code_sub
    $data_sql = "
        SELECT ts.id_session, t.nama_training, ts.code_sub, t.jenis AS type, ts.method, ts.date_start AS date, 
               COUNT(s.id_score) as participants, AVG(s.pre) as avg_pre, AVG(s.post) as avg_post
        FROM training_session ts
        JOIN training t ON ts.id_training = t.id_training
        LEFT JOIN score s ON ts.id_session = s.id_session
        WHERE $where_sql_ajax
        GROUP BY ts.id_session
        ORDER BY ts.date_start DESC
        LIMIT $limit OFFSET 0 
    "; 
    $result = $conn->query($data_sql);

    ob_start();
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $typeClass = (stripos($row['type'], 'Technical') !== false) ? 'type-tech' : ((stripos($row['type'], 'Soft') !== false) ? 'type-soft' : 'type-default');
            $methodClass = (stripos($row['method'], 'Inclass') !== false) ? 'method-inclass' : 'method-online';
            $avgScore = $row['avg_post'] ? number_format($row['avg_post'], 1) . '%' : '-';
            ?>
            <tr>
                <td>
                    <div class="training-cell">
                        <div class="icon-box"><i data-lucide="book-open" style="width:18px;"></i></div>
                        <div>
                            <div class="training-name-text"><?php echo htmlspecialchars($row['nama_training']); ?></div>
                            <div style="font-size:11px; color:#888;"><?php echo htmlspecialchars($row['code_sub']); ?></div>
                        </div>
                    </div>
                </td>
                <td><?php echo date('M d, Y', strtotime($row['date'])); ?></td>
                <td><span class="badge <?php echo $typeClass; ?>"><?php echo htmlspecialchars($row['type']); ?></span></td>
                <td><span class="badge <?php echo $methodClass; ?>"><?php echo htmlspecialchars($row['method']); ?></span></td>
                <td><?php echo $row['participants']; ?></td>
                <td class="score"><?php echo $avgScore; ?></td>
                <td>
                    <button class="btn-view" onclick="window.location.href='tdetails.php?id=<?php echo $row['id_session']; ?>'">
                        <span>View Details</span>
                        <svg><rect x="0" y="0"></rect></svg>
                    </button>
                </td>
            </tr>
            <?php
        }
    } else {
        echo '<tr><td colspan="7" style="text-align:center; color:#888;">No records found.</td></tr>';
    }
    $html = ob_get_clean();
    echo json_encode(['html' => $html, 'total' => $total_records]);
    exit;
}

// --- STANDARD LOAD QUERY ---
$where_clauses = ["1=1"];
if (!empty($search)) {
    $where_clauses[] = "(t.nama_training LIKE '%" . $conn->real_escape_string($search) . "%' OR ts.code_sub LIKE '%" . $conn->real_escape_string($search) . "%')";
}
if ($filter_type !== 'All Types') $where_clauses[] = "t.jenis = '" . $conn->real_escape_string($filter_type) . "'";
if ($filter_method !== 'All Methods') $where_clauses[] = "ts.method = '" . $conn->real_escape_string($filter_method) . "'";
if (!empty($start_date) && !empty($end_date)) {
    $where_clauses[] = "ts.date_start >= '$start_date' AND ts.date_start <= '$end_date'";
} elseif (!empty($start_date)) {
    $where_clauses[] = "ts.date_start >= '$start_date'";
} elseif (!empty($end_date)) {
    $where_clauses[] = "ts.date_start <= '$end_date'";
}

$where_sql = implode(' AND ', $where_clauses);

$count_sql = "SELECT COUNT(DISTINCT ts.id_session) as total FROM training_session ts JOIN training t ON ts.id_training = t.id_training WHERE $where_sql";
$total_result = $conn->query($count_sql);
$total_records = $total_result->fetch_assoc()['total'] ?? 0;
$total_pages = ceil($total_records / $limit);

$data_sql = "
    SELECT ts.id_session, t.nama_training, ts.code_sub, t.jenis AS type, ts.method, ts.date_start AS date, 
           COUNT(s.id_score) as participants, AVG(s.pre) as avg_pre, AVG(s.post) as avg_post
    FROM training_session ts
    JOIN training t ON ts.id_training = t.id_training
    LEFT JOIN score s ON ts.id_session = s.id_session
    WHERE $where_sql
    GROUP BY ts.id_session
    ORDER BY ts.date_start DESC
    LIMIT $limit OFFSET $offset
";
$result = $conn->query($data_sql);

$types_opt = $conn->query("SELECT DISTINCT jenis FROM training WHERE jenis IS NOT NULL AND jenis != '' ORDER BY jenis");
$methods_opt = $conn->query("SELECT DISTINCT method FROM training_session WHERE method IS NOT NULL AND method != '' ORDER BY method");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GGF - Training Sessions Report</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="icons/icon.png">
    <style>
        /* (KEEP ALL YOUR EXISTING CSS HERE - I HAVE NOT CHANGED STYLES) */
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Poppins', sans-serif; }
        body { background-color: #117054; padding: 0; margin: 0; overflow: hidden; height: 100vh; }
        .main-wrapper { background-color: #f3f4f7; padding: 20px 40px; height: 100vh; overflow-y: auto; width: 100%; position: relative; display: flex; flex-direction: column; }
        .drawer-open .main-wrapper { transform: scale(0.85) translateX(24px); border-radius: 35px; pointer-events: auto; box-shadow: -20px 0 40px rgba(0,0,0,0.2); overflow: hidden; }
        .navbar { background-color: #197B40; height: 70px; border-radius: 0px 0px 50px 50px; display: flex; align-items: center; padding: 0 30px; justify-content: space-between; margin: -20px 0 30px 0; box-shadow: 0 4px 10px rgba(0,0,0,0.1); flex-shrink: 0; position: sticky; top: -20px; z-index: 1000; }
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
        .report-card { background: white; border-radius: 20px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.05); margin-bottom: 50px; flex-grow: 1; display: flex; flex-direction: column; }
        .report-header { background-color: #197B40; padding: 25px 40px; display: flex; justify-content: space-between; align-items: center; color: white; }
        .header-actions { display: flex; align-items: center; gap: 12px; }
        .search-bar { background: white; border-radius: 50px; padding: 10px 20px; display: flex; align-items: center; width: 250px; }
        .search-bar input { border: none; outline: none; margin-left: 10px; width: 100%; font-size: 13px; color: #333; }
        .btn-action { background: white; color: #197B40; border: none; padding: 10px 20px; border-radius: 50px; font-size: 13px; font-weight: bold; display: flex; align-items: center; gap: 8px; cursor: pointer; transition: 0.3s; }
        .btn-action:hover { background: #f0f0f0; }
        .table-container { padding: 20px 40px 0 40px; flex-grow: 1; overflow-y: auto; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 13px; color: #888; padding: 15px 0; border-bottom: 1px solid #eee; }
        td { padding: 20px 0; font-size: 14px; color: #333; border-bottom: 1px solid #f9f9f9; vertical-align: middle; }
        .training-cell { display: flex; align-items: center; gap: 12px; }
        .icon-box { background: #e8f5e9; color: #197B40; padding: 8px; border-radius: 8px; display: flex; align-items: center; }
        .training-name-text { font-weight: 700; }
        .badge { padding: 5px 12px; border-radius: 15px; font-size: 11px; font-weight: 600; }
        .type-tech { background: #e3f2fd; color: #1e88e5; }
        .type-soft { background: #fff3e0; color: #f57c00; }
        .type-default { background: #f5f5f5; color: #666; }
        .method-inclass { background: #f3e5f5; color: #8e24aa; }
        .method-online { background: #e0f2f1; color: #00695c; }
        .score { color: #197B40; font-weight: bold; }
        .btn-view { position: relative; background: linear-gradient(90deg, #FF9A02 0%, #FED404 100%); color: white; border: none; padding: 10px 18px; border-radius: 25px; font-size: 12px; font-weight: bold; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 5px; overflow: visible; transition: transform 0.2s; }
        .btn-view:active { transform: scale(0.98); }
        .btn-view span { position: relative; z-index: 2; }
        .btn-view svg { position: absolute; top: -2px; left: -2px; width: calc(100% + 4px); height: calc(100% + 4px); fill: none; pointer-events: none; overflow: visible; }
        .btn-view rect { width: 100%; height: 100%; rx: 25px; ry: 25px; stroke: url(#multiColorGradient); stroke-width: 2; stroke-dasharray: 120, 380; stroke-dashoffset: 0; opacity: 0; transition: opacity 0.3s; }
        .btn-view:hover rect { opacity: 1; animation: snakeMove 2s linear infinite; }
        @keyframes snakeMove { from { stroke-dashoffset: 500; } to { stroke-dashoffset: 0; } }
        .table-footer { padding: 25px 40px; display: flex; justify-content: space-between; align-items: center; color: #7a7a7a; font-size: 13px; border-top: 1px solid #f9f9f9; }
        .pagination { display: flex; align-items: center; gap: 8px; }
        .page-num { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 8px; cursor: pointer; text-decoration: none; color: #4a4a4a; font-weight: 500; }
        .page-num.active { background-color: #197B40; color: white; }
        .btn-next { display: flex; align-items: center; gap: 5px; color: #4a4a4a; text-decoration: none; }
        .filter-overlay { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.05); z-index: 900; display: none; opacity: 0; transition: opacity 0.3s; pointer-events: none; }
        .filter-drawer { position: fixed; top: 20px; bottom: 20px; right: -400px; width: 380px; background: white; z-index: 1001; box-shadow: -10px 0 30px rgba(0,0,0,0.15); transition: right 0.4s cubic-bezier(0.32, 1, 0.23, 1); display: flex; flex-direction: column; border-radius: 35px; overflow: hidden; }
        .drawer-open .filter-overlay { display: block; opacity: 1; pointer-events: auto; }
        .drawer-open .filter-drawer { right: 20px; }
        .drawer-header { background-color: #197B40; color: white; padding: 25px; display: flex; justify-content: space-between; align-items: center; }
        .drawer-content { padding: 25px; overflow-y: auto; flex-grow: 1; }
        .filter-group { margin-bottom: 25px; }
        .filter-group label { display: block; font-size: 14px; font-weight: 600; color: #333; margin-bottom: 10px; }
        .filter-group select { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 12px; outline: none; }
        .date-row { display: flex; gap: 10px; }
        .date-input-wrapper { position: relative; flex: 1; }
        .date-input-wrapper input[type="date"] { width: 100%; padding: 10px 40px 10px 20px; border: 1px solid #e0e0e0; border-radius: 50px; font-size: 13px; outline: none; color: #333; font-family: 'Poppins', sans-serif; background-color: #fff; cursor: pointer; transition: all 0.2s; position: relative; }
        .date-input-wrapper input[type="date"]::-webkit-calendar-picker-indicator { position: absolute; top: 0; left: 0; width: 100%; height: 100%; padding: 0; margin: 0; opacity: 0; cursor: pointer; }
        .custom-date-icon { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); color: #197B40; width: 18px; height: 18px; pointer-events: none; z-index: 1; }
        .drawer-footer { padding: 20px 25px; border-top: 1px solid #eee; display: flex; gap: 15px; }
        .btn-reset { background: #f3f4f7; color: #666; border: none; padding: 12px; border-radius: 50px; flex: 1; font-weight: 600; cursor: pointer; }
        .btn-apply { position: relative; background: #197B40; color: white; border: none; padding: 12px 24px; border-radius: 25px; flex: 1; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: background 0.2s; overflow: visible; }
        .btn-apply svg { position: absolute; top: -2px; left: -2px; width: calc(100% + 4px); height: calc(100% + 4px); fill: none; pointer-events: none; overflow: visible; }
        .btn-apply rect { width: 100%; height: 100%; rx: 25px; ry: 25px; stroke: #FF9A02; stroke-width: 3; stroke-dasharray: 120, 380; stroke-dashoffset: 0; opacity: 0; transition: opacity 0.3s; }
        .btn-apply:hover { background: #145a32; }
        .btn-apply:hover rect { opacity: 1; animation: snakeMove 2s linear infinite; }
    </style>
</head>
<body id="body">
    <svg style="position: absolute; width: 0; height: 0; overflow: hidden;" version="1.1" xmlns="http://www.w3.org/2000/svg">
        <defs>
            <linearGradient id="multiColorGradient" x1="0%" y1="0%" x2="100%" y2="0%">
                <stop offset="0%" stop-color="#197B40" />
                <stop offset="100%" stop-color="#14674b" />
            </linearGradient>
        </defs>
    </svg>

    <div class="main-wrapper">
        <nav class="navbar">
            <div class="logo-section"><img src="GGF_logo024_putih.png" alt="GGF Logo"></div>
            <div class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="reports.php" class="active">Reports</a>
                <a href="upload.php">Upload Data</a>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <a href="users.php">Users</a>
                <?php endif; ?>
            </div>
            <div class="nav-right">
                <div class="user-profile"><div class="avatar-circle"><?php echo $initials; ?></div></div>
                <a href="login.html" class="btn-signout">Sign Out</a>
            </div>
        </nav>

        <div class="report-card">
            <div class="report-header">
                <h3>Training Sessions Report</h3>
                <div class="header-actions">
                    <div class="search-bar">
                        <img src="icons/search.ico" style="width: 26px; height: 26px; transform: scale(1.8); margin-right: 4px;">
                        <input type="text" id="liveSearchInput" placeholder="Search training..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <button class="btn-action" onclick="toggleDrawer()">
                        <img src="icons/filter.ico" style="width: 26px; height: 26px; transform: scale(1.8); margin-right: 4px;">
                        Filters
                    </button>

                    <button class="btn-action" onclick="window.location.href='export_report.php?<?php echo $_SERVER['QUERY_STRING']; ?>'">
                        <img src="icons/excel.ico" style="width: 26px; height: 26px; transform: scale(1.8); margin-right: 4px;">
                        Export Report
                    </button>
                </div>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Training Name</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Method</th>
                            <th>Participants</th>
                            <th>Avg Score</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <?php if ($result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): 
                                $typeClass = (stripos($row['type'], 'Technical') !== false) ? 'type-tech' : ((stripos($row['type'], 'Soft') !== false) ? 'type-soft' : 'type-default');
                                $methodClass = (stripos($row['method'], 'Inclass') !== false) ? 'method-inclass' : 'method-online';
                                $avgScore = $row['avg_post'] ? number_format($row['avg_post'], 1) . '%' : '-';
                            ?>
                            <tr>
                                <td>
                                    <div class="training-cell">
                                        <div class="icon-box"><i data-lucide="book-open" style="width:18px;"></i></div>
                                        <div>
                                            <div class="training-name-text"><?php echo htmlspecialchars($row['nama_training']); ?></div>
                                            <div style="font-size:11px; color:#888;"><?php echo htmlspecialchars($row['code_sub']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($row['date'])); ?></td>
                                <td><span class="badge <?php echo $typeClass; ?>"><?php echo htmlspecialchars($row['type']); ?></span></td>
                                <td><span class="badge <?php echo $methodClass; ?>"><?php echo htmlspecialchars($row['method']); ?></span></td>
                                <td><?php echo $row['participants']; ?></td>
                                <td class="score"><?php echo $avgScore; ?></td>
                                <td>
                                    <button class="btn-view" onclick="window.location.href='tdetails.php?id=<?php echo $row['id_session']; ?>'">
                                        <span>View Details</span>
                                        <svg><rect x="0" y="0"></rect></svg>
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="7" style="text-align:center; color:#888;">No records found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="table-footer">
                <div class="records-info" id="recordInfo">
                    Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_records); ?> of <?php echo $total_records; ?> Records
                </div>
                <div class="pagination" id="paginationControls">
                    <?php if($page > 1): ?>
                        <a href="?page=<?php echo $page-1; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page'=>''])); ?>" class="btn-next" style="transform: rotate(180deg); display:inline-block;"><i data-lucide="chevron-right" style="width:16px;"></i></a>
                    <?php endif; ?>

                    <?php for($i=1; $i<=$total_pages; $i++): ?>
                        <?php if ($i == 1 || $i == $total_pages || ($i >= $page - 1 && $i <= $page + 1)): ?>
                            <a href="?page=<?php echo $i; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page'=>''])); ?>" class="page-num <?php if($i==$page) echo 'active'; ?>"><?php echo $i; ?></a>
                        <?php elseif ($i == $page - 2 || $i == $page + 2): ?>
                            <span class="dots">...</span>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if($page < $total_pages): ?>
                        <a href="?page=<?php echo $page+1; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page'=>''])); ?>" class="btn-next">Next <i data-lucide="chevron-right" style="width:16px;"></i></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="filter-overlay" onclick="toggleDrawer()"></div>
    
    <div class="filter-drawer">
        <div class="drawer-header">
            <h4>Filter Options</h4>
            <i data-lucide="x" style="cursor:pointer" onclick="toggleDrawer()"></i>
        </div>
        <div class="drawer-content">
            <div class="filter-group">
                <label>Training Type</label>
                <select id="filterType">
                    <option value="All Types">All Types</option>
                    <?php while($t = $types_opt->fetch_assoc()): ?>
                        <option value="<?php echo htmlspecialchars($t['jenis']); ?>" <?php if($filter_type == $t['jenis']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($t['jenis']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Method</label>
                <select id="filterMethod">
                    <option value="All Methods">All Methods</option>
                    <?php while($m = $methods_opt->fetch_assoc()): ?>
                        <option value="<?php echo htmlspecialchars($m['method']); ?>" <?php if($filter_method == $m['method']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($m['method']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Date Range</label>
                <div class="date-row">
                    <div class="date-input-wrapper">
                        <input type="date" id="startDate" value="<?php echo $start_date; ?>">
                        <i data-lucide="calendar" class="custom-date-icon"></i>
                    </div>
                    <div class="date-input-wrapper">
                        <input type="date" id="endDate" value="<?php echo $end_date; ?>">
                        <i data-lucide="calendar" class="custom-date-icon"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="drawer-footer">
            <button class="btn-reset" onclick="window.location.href='reports.php'">Reset</button>
            <button class="btn-apply" onclick="applyFilters()">
                <span>Apply Filters</span>
                <svg><rect x="0" y="0"></rect></svg>
            </button>
        </div>
    </div>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        lucide.createIcons();

        function toggleDrawer() {
            document.getElementById('body').classList.toggle('drawer-open');
        }

        const searchInput = document.getElementById('liveSearchInput');
        const tableBody = document.getElementById('tableBody');
        const recordInfo = document.getElementById('recordInfo');
        
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
            fetch(`?ajax_search=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    tableBody.innerHTML = data.html;
                    recordInfo.textContent = `Found ${data.total} records`;
                    lucide.createIcons();
                })
                .catch(error => console.error('Error:', error));
                
        }, 300);

        searchInput.addEventListener('input', performSearch);

        function applyFilters() {
            const search = document.getElementById('liveSearchInput').value;
            const type = document.getElementById('filterType').value;
            const method = document.getElementById('filterMethod').value;
            const start = document.getElementById('startDate').value;
            const end = document.getElementById('endDate').value;

            const params = new URLSearchParams();
            if(search) params.set('search', search);
            if(type !== 'All Types') params.set('type', type);
            if(method !== 'All Methods') params.set('method', method);
            if(start) params.set('start', start);
            if(end) params.set('end', end);

            window.location.search = params.toString();
        }
    </script>
</body>
</html>