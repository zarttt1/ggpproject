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

// --- GET PARAMETERS ---
$search = $_GET['search'] ?? '';
$filter_bu = $_GET['bu'] ?? 'All BUs';
$filter_fn1 = $_GET['fn1'] ?? 'All Func N-1';
$filter_fn2 = $_GET['fn2'] ?? 'All Func N-2';

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// --- HELPER FUNCTION: Abbreviate BU Names ---
function getAbbreviation($name) {
    if (empty($name) || $name === '-') return '-';
    $manual_map = [
        'Human Resources' => 'HR', 'Information Technology' => 'IT',
        'Quality Assurance' => 'QA', 'General Affairs' => 'GA',
        'Supply Chain' => 'SCM', 'Research and Development' => 'R&D',
        'Production' => 'PROD', 'Finance' => 'FIN'
    ];
    if (isset($manual_map[$name])) return $manual_map[$name];
    $words = explode(' ', $name);
    if (count($words) > 1) {
        $acronym = '';
        foreach ($words as $w) $acronym .= strtoupper(substr($w, 0, 1));
        return $acronym;
    }
    return (strlen($name) > 4) ? strtoupper(substr($name, 0, 3)) : strtoupper($name);
}

// --- BUILD WHERE CLAUSE (Used by both Main Load and AJAX) ---
$where_clauses = ["1=1"];

// 1. Search Logic
if (!empty($search)) {
    $safe_search = $conn->real_escape_string($search);
    $where_clauses[] = "(k.nama_karyawan LIKE '%$safe_search%' OR k.index_karyawan LIKE '%$safe_search%')";
}

// 2. Define Subqueries for Filtering
// (We repeat these subqueries in the WHERE clause to filter by the *latest* status)
$sub_bu = "(SELECT b.nama_bu FROM score s JOIN bu b ON s.id_bu = b.id_bu WHERE s.id_karyawan = k.id_karyawan ORDER BY s.id_session DESC LIMIT 1)";
$sub_fn1 = "(SELECT f.func_n1 FROM score s JOIN func f ON s.id_func = f.id_func WHERE s.id_karyawan = k.id_karyawan ORDER BY s.id_session DESC LIMIT 1)";
$sub_fn2 = "(SELECT f.func_n2 FROM score s JOIN func f ON s.id_func = f.id_func WHERE s.id_karyawan = k.id_karyawan ORDER BY s.id_session DESC LIMIT 1)";

// 3. Filter Logic
if ($filter_bu !== 'All BUs') {
    $where_clauses[] = "$sub_bu = '" . $conn->real_escape_string($filter_bu) . "'";
}
if ($filter_fn1 !== 'All Func N-1') {
    $where_clauses[] = "$sub_fn1 = '" . $conn->real_escape_string($filter_fn1) . "'";
}
if ($filter_fn2 !== 'All Func N-2') {
    $where_clauses[] = "$sub_fn2 = '" . $conn->real_escape_string($filter_fn2) . "'";
}

$where_sql = implode(' AND ', $where_clauses);


// ==========================================
//  AJAX HANDLER (For Live Search)
// ==========================================
if (isset($_GET['ajax_search'])) {
    // Note: The $where_sql above already incorporates $_GET['search'] and all filters
    
    $count_sql = "SELECT COUNT(*) as total FROM karyawan k WHERE $where_sql";
    $total_rows = $conn->query($count_sql)->fetch_assoc()['total'];
    $total_pages = ceil($total_rows / $limit);

    $list_sql = "
        SELECT 
            k.id_karyawan, k.index_karyawan, k.nama_karyawan,
            (SELECT COUNT(*) FROM score s WHERE s.id_karyawan = k.id_karyawan) as total_participation,
            $sub_bu as latest_bu,
            $sub_fn1 as latest_func_n1,
            $sub_fn2 as latest_func_n2
        FROM karyawan k
        WHERE $where_sql
        ORDER BY k.nama_karyawan ASC
        LIMIT $limit OFFSET $offset
    ";
    $employees = $conn->query($list_sql);

    ob_start();
    if ($employees->num_rows > 0) {
        while ($e = $employees->fetch_assoc()) {
            $initials = strtoupper(substr($e['nama_karyawan'], 0, 1));
            $partCount = $e['total_participation'];
            $badgeClass = ($partCount > 5) ? 'badge-high' : (($partCount > 0) ? 'badge-med' : 'badge-low');
            
            $bu = getAbbreviation($e['latest_bu'] ?? '-');
            $fn1 = $e['latest_func_n1'] ?? '-';
            $fn2 = $e['latest_func_n2'] ?? '-';
            ?>
            <tr>
                <td><?php echo htmlspecialchars($e['index_karyawan']); ?></td>
                <td>
                    <div class="user-cell">
                        <div class="user-avatar"><?php echo $initials; ?></div> 
                        <?php echo htmlspecialchars($e['nama_karyawan']); ?>
                    </div>
                </td>
                <td><strong><?php echo htmlspecialchars($bu); ?></strong></td>
                <td><?php echo htmlspecialchars($fn1); ?></td>
                <td><?php echo htmlspecialchars($fn2); ?></td>
                <td><span class="<?php echo $badgeClass; ?>"><?php echo $partCount; ?> Sessions</span></td>
                <td>
                    <button class="btn-view" onclick="window.location.href='employee_training_history.php?id_karyawan=<?php echo $e['id_karyawan']; ?>'">
                        <span>View History</span>
                        <svg><rect x="0" y="0"></rect></svg>
                    </button>
                </td>
            </tr>
            <?php
        }
    } else {
        echo '<tr><td colspan="7" style="text-align:center; padding: 20px; color:#888;">No employees found.</td></tr>';
    }
    $table_html = ob_get_clean();

    ob_start();
    ?>
    <div>Showing <?php echo ($total_rows > 0 ? $offset + 1 : 0); ?> to <?php echo min($offset + $limit, $total_rows); ?> of <?php echo $total_rows; ?> Records</div>
    <div class="pagination-controls">
        <?php if($page > 1): $prev = $page - 1; echo "<a href='#' onclick='changePage($prev); return false;' class='page-btn'>&lt;</a>"; endif; ?>
        <a href="#" class="page-btn active"><?php echo $page; ?></a>
        <?php if($page < $total_pages): $next = $page + 1; echo "<a href='#' onclick='changePage($next); return false;' class='page-btn'>&gt;</a>"; endif; ?>
    </div>
    <?php
    $pagination_html = ob_get_clean();

    echo json_encode(['table' => $table_html, 'pagination' => $pagination_html]);
    exit();
}
// ==========================================
//  END AJAX HANDLER
// ==========================================


// --- STANDARD LOAD QUERY ---
$count_sql = "SELECT COUNT(*) as total FROM karyawan k WHERE $where_sql";
$total_rows = $conn->query($count_sql)->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

$list_sql = "
    SELECT 
        k.id_karyawan, k.index_karyawan, k.nama_karyawan,
        (SELECT COUNT(*) FROM score s WHERE s.id_karyawan = k.id_karyawan) as total_participation,
        $sub_bu as latest_bu,
        $sub_fn1 as latest_func_n1,
        $sub_fn2 as latest_func_n2
    FROM karyawan k
    WHERE $where_sql
    ORDER BY k.nama_karyawan ASC
    LIMIT $limit OFFSET $offset
";
$employees = $conn->query($list_sql);

// --- FETCH FILTER OPTIONS (Distinct values for dropdowns) ---
$bu_opts = $conn->query("SELECT DISTINCT nama_bu FROM bu WHERE nama_bu IS NOT NULL ORDER BY nama_bu");
$fn1_opts = $conn->query("SELECT DISTINCT func_n1 FROM func WHERE func_n1 IS NOT NULL ORDER BY func_n1");
$fn2_opts = $conn->query("SELECT DISTINCT func_n2 FROM func WHERE func_n2 IS NOT NULL ORDER BY func_n2");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GGF - Employee Directory</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="icons/icon.png">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Poppins', sans-serif; }
        body { background-color: #117054; padding: 0; margin: 0; overflow: hidden; height: 100vh; }
        .main-wrapper { background-color: #f3f4f7; padding: 20px 40px; height: 100vh; overflow-y: auto; width: 100%; position: relative; display: flex; flex-direction: column; transition: transform 0.3s, border-radius 0.3s; }
        
        /* Drawer Open State */
        .drawer-open .main-wrapper { transform: scale(0.85) translateX(24px); border-radius: 35px; pointer-events: auto; box-shadow: -20px 0 40px rgba(0,0,0,0.2); overflow: hidden; }

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

        /* --- TABLE --- */
        .table-card { background: white; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.03); overflow: hidden; margin-bottom: 40px; margin-top: 20px; flex-grow: 1; display: flex; flex-direction: column; }
        .table-header-strip { background-color: #197b40; color: white; padding: 15px 25px; display: flex; justify-content: space-between; align-items: center; }
        .table-title { font-weight: 600; font-size: 16px; }
        .table-actions { display: flex; gap: 12px; align-items: center; }
        .search-box { background-color: white; border-radius: 50px; height: 35px; width: 250px; display: flex; align-items: center; padding: 0 15px; }
        .search-box i { color: #197B40; width: 16px; height: 16px; }
        .search-box input { border: none; background: transparent; outline: none; height: 100%; flex: 1; padding-left: 10px; font-size: 13px; color: #333; }
        
        .btn-action-small { height: 35px; padding: 0 15px; border: none; border-radius: 50px; background: white; color: #197B40; font-weight: 600; font-size: 13px; cursor: pointer; display: flex; align-items: center; gap: 6px; text-decoration: none; transition: 0.2s; }
        .btn-action-small:hover { background-color: #f0fdf4; }

        .table-responsive { flex-grow: 1; overflow-y: auto; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px 25px; font-size: 12px; color: #888; font-weight: 600; border-bottom: 1px solid #eee; position: sticky; top: 0; background: white; z-index: 10; }
        td { padding: 15px 25px; font-size: 13px; color: #333; border-bottom: 1px solid #f9f9f9; vertical-align: middle; }
        .user-cell { display: flex; align-items: center; gap: 10px; font-weight: 600; }
        .user-avatar { width: 32px; height: 32px; border-radius: 50%; background-color: #197B40; color: white; font-size: 11px; display: flex; align-items: center; justify-content: center; font-weight: bold; }
        
        .badge-high { background-color: #dcfce7; color: #15803d; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; display: inline-block; }
        .badge-med { background-color: #fff7ed; color: #c2410c; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; display: inline-block; }
        .badge-low { background-color: #f3f4f6; color: #6b7280; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; display: inline-block; }
        
        /* SNAKE ANIMATION BUTTON STYLES */
        .btn-view { 
            position: relative; 
            background: linear-gradient(90deg, #FF9A02 0%, #FED404 100%); 
            color: white; 
            border: none; 
            padding: 10px 14px; 
            border-radius: 25px; 
            font-size: 12px; 
            font-weight: bold; 
            cursor: pointer; 
            display: inline-flex; 
            align-items: center; 
            justify-content: center; 
            gap: 5px; 
            overflow: visible; 
            transition: transform 0.2s; 
            white-space: nowrap; 
        }
        .btn-view:active { transform: scale(0.98); }
        .btn-view span { position: relative; z-index: 2; }
        .btn-view svg { position: absolute; top: -2px; left: -2px; width: calc(100% + 4px); height: calc(100% + 4px); fill: none; pointer-events: none; overflow: visible; }
        .btn-view rect { width: 100%; height: 100%; rx: 25px; ry: 25px; stroke: url(#multiColorGradient); stroke-width: 2; stroke-dasharray: 120, 380; stroke-dashoffset: 0; opacity: 0; transition: opacity 0.3s; }
        .btn-view:hover rect { opacity: 1; animation: snakeMove 2s linear infinite; }
        @keyframes snakeMove { from { stroke-dashoffset: 500; } to { stroke-dashoffset: 0; } }

        .pagination-container { padding: 20px 25px; display: flex; justify-content: space-between; align-items: center; font-size: 13px; color: #666; border-top: 1px solid #f9f9f9; }
        .page-btn { width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; border-radius: 6px; cursor: pointer; color: #666; text-decoration: none; }
        .page-btn.active { background-color: #197B40; color: white; font-weight: 600; }

        /* --- FILTER DRAWER STYLES --- */
        .filter-overlay { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.05); z-index: 900; display: none; opacity: 0; transition: opacity 0.3s; pointer-events: none; }
        .filter-drawer { position: fixed; top: 20px; bottom: 20px; right: -400px; width: 350px; background: white; z-index: 1001; box-shadow: -10px 0 30px rgba(0,0,0,0.15); transition: right 0.4s cubic-bezier(0.32, 1, 0.23, 1); display: flex; flex-direction: column; border-radius: 35px; overflow: hidden; }
        .drawer-open .filter-overlay { display: block; opacity: 1; pointer-events: auto; }
        .drawer-open .filter-drawer { right: 20px; }
        .drawer-header { background-color: #197B40; color: white; padding: 25px; display: flex; justify-content: space-between; align-items: center; }
        .drawer-content { padding: 25px; overflow-y: auto; flex-grow: 1; }
        .filter-group { margin-bottom: 25px; }
        .filter-group label { display: block; font-size: 14px; font-weight: 600; color: #333; margin-bottom: 10px; }
        .filter-group select { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 12px; outline: none; font-size: 13px; font-family: 'Poppins', sans-serif; }
        .drawer-footer { padding: 20px 25px; border-top: 1px solid #eee; display: flex; gap: 15px; }
        .btn-reset { background: #f3f4f7; color: #666; border: none; padding: 12px; border-radius: 50px; flex: 1; font-weight: 600; cursor: pointer; }
        
        /* Apply Button with Snake Animation */
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
            <div class="logo-section"><img src="GGF White.png" alt="GGF Logo"></div>
            <div class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="reports.php">Trainings</a>
                <a href="employee_reports.php" class="active">Employees</a>
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

        <div class="table-card">
            <div class="table-header-strip">
                <div class="table-title">Employee List</div>
                <div class="table-actions">
                    <div class="search-box">
                        <img src="icons/search.ico" style="width: 26px; height: 26px; transform: scale(1.8); margin-right: 4px;">
                        <input type="text" id="searchInput" placeholder="Search by Name or Index..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <button class="btn-action-small" onclick="toggleDrawer()">
                        <img src="icons/filter.ico" style="width: 26px; height: 26px; transform: scale(1.8); margin-right: 4px;">
                        Filter
                    </button>

                    <a href="export_employees.php" id="exportBtn" class="btn-action-small">
                        <img src="icons/excel.ico" style="width: 26px; height: 26px; transform: scale(1.8); margin-right: 4px;">
                        Export
                    </a>
                </div>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Index</th>
                            <th>Name</th>
                            <th>BU</th>
                            <th>Function N-1</th>
                            <th>Function N-2</th>
                            <th>Total Participation</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <?php if($employees->num_rows > 0): ?>
                            <?php while($e = $employees->fetch_assoc()): 
                                $initials = strtoupper(substr($e['nama_karyawan'], 0, 1));
                                $partCount = $e['total_participation'];
                                $badgeClass = ($partCount > 5) ? 'badge-high' : (($partCount > 0) ? 'badge-med' : 'badge-low');
                                
                                $bu = getAbbreviation($e['latest_bu'] ?? '-');
                                $fn1 = $e['latest_func_n1'] ?? '-';
                                $fn2 = $e['latest_func_n2'] ?? '-';
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($e['index_karyawan']); ?></td>
                                <td>
                                    <div class="user-cell">
                                        <div class="user-avatar"><?php echo $initials; ?></div> 
                                        <?php echo htmlspecialchars($e['nama_karyawan']); ?>
                                    </div>
                                </td>
                                <td><strong><?php echo htmlspecialchars($bu); ?></strong></td>
                                <td><?php echo htmlspecialchars($fn1); ?></td>
                                <td><?php echo htmlspecialchars($fn2); ?></td>
                                <td><span class="<?php echo $badgeClass; ?>"><?php echo $partCount; ?> Sessions</span></td>
                                <td>
                                    <button class="btn-view" onclick="window.location.href='employee_training_history.php?id_karyawan=<?php echo $e['id_karyawan']; ?>'">
                                        <span>View History</span>
                                        <svg><rect x="0" y="0"></rect></svg>
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="7" style="text-align:center; padding: 20px; color:#888;">No employees found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="pagination-container" id="paginationContainer">
                <div>Showing <?php echo ($total_rows > 0 ? $offset + 1 : 0); ?> to <?php echo min($offset + $limit, $total_rows); ?> of <?php echo $total_rows; ?> Records</div>
                <div class="pagination-controls">
                    <?php if($page > 1): $prev = $page - 1; echo "<a href='#' onclick='changePage($prev); return false;' class='page-btn'>&lt;</a>"; endif; ?>
                    <a href="#" class="page-btn active"><?php echo $page; ?></a>
                    <?php if($page < $total_pages): $next = $page + 1; echo "<a href='#' onclick='changePage($next); return false;' class='page-btn'>&gt;</a>"; endif; ?>
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
                <label>Business Unit (BU)</label>
                <select id="filterBU">
                    <option value="All BUs">All BUs</option>
                    <?php while($row = $bu_opts->fetch_assoc()): ?>
                        <option value="<?php echo htmlspecialchars($row['nama_bu']); ?>" <?php if($filter_bu == $row['nama_bu']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($row['nama_bu']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="filter-group">
                <label>Function N-1</label>
                <select id="filterFn1">
                    <option value="All Func N-1">All Func N-1</option>
                    <?php while($row = $fn1_opts->fetch_assoc()): ?>
                        <option value="<?php echo htmlspecialchars($row['func_n1']); ?>" <?php if($filter_fn1 == $row['func_n1']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($row['func_n1']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="filter-group">
                <label>Function N-2</label>
                <select id="filterFn2">
                    <option value="All Func N-2">All Func N-2</option>
                    <?php while($row = $fn2_opts->fetch_assoc()): ?>
                        <option value="<?php echo htmlspecialchars($row['func_n2']); ?>" <?php if($filter_fn2 == $row['func_n2']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($row['func_n2']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>
        
        <div class="drawer-footer">
            <button class="btn-reset" onclick="window.location.href='employee_reports.php'">Reset</button>
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

        function applyFilters() {
            const search = document.getElementById('searchInput').value;
            const bu = document.getElementById('filterBU').value;
            const fn1 = document.getElementById('filterFn1').value;
            const fn2 = document.getElementById('filterFn2').value;

            const params = new URLSearchParams();
            if(search) params.set('search', search);
            if(bu !== 'All BUs') params.set('bu', bu);
            if(fn1 !== 'All Func N-1') params.set('fn1', fn1);
            if(fn2 !== 'All Func N-2') params.set('fn2', fn2);

            window.location.search = params.toString();
        }

        // --- LIVE SEARCH LOGIC ---
        const searchInput = document.getElementById('searchInput');
        const tableBody = document.getElementById('tableBody');
        const paginationContainer = document.getElementById('paginationContainer');
        const exportBtn = document.getElementById('exportBtn');

        // Capture current filters for AJAX search
        const currentBU = "<?php echo htmlspecialchars($filter_bu); ?>";
        const currentFn1 = "<?php echo htmlspecialchars($filter_fn1); ?>";
        const currentFn2 = "<?php echo htmlspecialchars($filter_fn2); ?>";

        function changePage(page) {
            const query = searchInput.value;
            fetchData(query, page);
        }

        function fetchData(query, page = 1) {
            // Include filters in AJAX URL
            let url = `?ajax_search=${encodeURIComponent(query)}&page=${page}`;
            if(currentBU !== 'All BUs') url += `&bu=${encodeURIComponent(currentBU)}`;
            if(currentFn1 !== 'All Func N-1') url += `&fn1=${encodeURIComponent(currentFn1)}`;
            if(currentFn2 !== 'All Func N-2') url += `&fn2=${encodeURIComponent(currentFn2)}`;

            // Update Export Link
            let exportUrl = `export_employees.php?search=${encodeURIComponent(query)}`;
            if(currentBU !== 'All BUs') exportUrl += `&bu=${encodeURIComponent(currentBU)}`;
            if(currentFn1 !== 'All Func N-1') exportUrl += `&fn1=${encodeURIComponent(currentFn1)}`;
            if(currentFn2 !== 'All Func N-2') exportUrl += `&fn2=${encodeURIComponent(currentFn2)}`;
            exportBtn.href = exportUrl;

            fetch(url)
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