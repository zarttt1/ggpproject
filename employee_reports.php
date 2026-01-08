<?php
session_start();
require 'db_connect.php'; 

// 1. Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

$username = $_SESSION['username'] ?? 'User';
$initials = strtoupper(substr($username, 0, 2));

$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// --- HELPER FUNCTION: Abbreviate BU Names ---
function getAbbreviation($name) {
    if (empty($name) || $name === '-') return '-';

    // 1. Manual Mappings (Add your specific BUs here)
    $manual_map = [
        'Human Resources' => 'HR',
        'Information Technology' => 'IT',
        'Quality Assurance' => 'QA',
        'General Affairs' => 'GA',
        'Supply Chain' => 'SCM',
        'Research and Development' => 'R&D',
        'Production' => 'PROD',
        'Finance' => 'FIN'
    ];

    if (isset($manual_map[$name])) {
        return $manual_map[$name];
    }

    // 2. Auto-Generate Acronym (e.g., "Fresh Fruit" -> "FF")
    $words = explode(' ', $name);
    if (count($words) > 1) {
        $acronym = '';
        foreach ($words as $w) {
            $acronym .= strtoupper(substr($w, 0, 1));
        }
        return $acronym;
    }

    // 3. If single word and long, take first 3 letters (e.g., "Marketing" -> "MAR")
    return (strlen($name) > 4) ? strtoupper(substr($name, 0, 3)) : strtoupper($name);
}

// ==========================================
//  AJAX HANDLER (For Live Search)
// ==========================================
if (isset($_GET['ajax_search'])) {
    $search_term = $_GET['ajax_search'];
    $where_search = "1=1";
    
    if (!empty($search_term)) {
        $safe_search = $conn->real_escape_string($search_term);
        $where_search .= " AND (
            k.nama_karyawan LIKE '%$safe_search%' 
            OR k.index_karyawan LIKE '%$safe_search%'
        )";
    }

    $count_sql = "SELECT COUNT(*) as total FROM karyawan k WHERE $where_search";
    $total_rows = $conn->query($count_sql)->fetch_assoc()['total'];
    $total_pages = ceil($total_rows / $limit);

    $list_sql = "
        SELECT 
            k.id_karyawan, k.index_karyawan, k.nama_karyawan,
            (SELECT COUNT(*) FROM score s WHERE s.id_karyawan = k.id_karyawan) as total_participation,
            (SELECT b.nama_bu FROM score s JOIN bu b ON s.id_bu = b.id_bu WHERE s.id_karyawan = k.id_karyawan ORDER BY s.id_session DESC LIMIT 1) as latest_bu,
            (SELECT f.func_n1 FROM score s JOIN func f ON s.id_func = f.id_func WHERE s.id_karyawan = k.id_karyawan ORDER BY s.id_session DESC LIMIT 1) as latest_func_n1,
            (SELECT f.func_n2 FROM score s JOIN func f ON s.id_func = f.id_func WHERE s.id_karyawan = k.id_karyawan ORDER BY s.id_session DESC LIMIT 1) as latest_func_n2
        FROM karyawan k
        WHERE $where_search
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
            
            // Apply Abbreviation Logic Here
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
        <?php if($page > 1): $prev = $page - 1; echo "<a href='?page=$prev&search=$search_term' class='page-btn'>&lt;</a>"; endif; ?>
        <a href="#" class="page-btn active"><?php echo $page; ?></a>
        <?php if($page < $total_pages): $next = $page + 1; echo "<a href='?page=$next&search=$search_term' class='page-btn'>&gt;</a>"; endif; ?>
    </div>
    <?php
    $pagination_html = ob_get_clean();

    echo json_encode(['table' => $table_html, 'pagination' => $pagination_html]);
    exit();
}
// ==========================================
//  END AJAX HANDLER
// ==========================================

$where_search = "1=1";
if (!empty($search)) {
    $safe_search = $conn->real_escape_string($search);
    $where_search .= " AND (k.nama_karyawan LIKE '%$safe_search%' OR k.index_karyawan LIKE '%$safe_search%')";
}

$count_sql = "SELECT COUNT(*) as total FROM karyawan k WHERE $where_search";
$total_rows = $conn->query($count_sql)->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

$list_sql = "
    SELECT 
        k.id_karyawan, k.index_karyawan, k.nama_karyawan,
        (SELECT COUNT(*) FROM score s WHERE s.id_karyawan = k.id_karyawan) as total_participation,
        (SELECT b.nama_bu FROM score s JOIN bu b ON s.id_bu = b.id_bu WHERE s.id_karyawan = k.id_karyawan ORDER BY s.id_session DESC LIMIT 1) as latest_bu,
        (SELECT f.func_n1 FROM score s JOIN func f ON s.id_func = f.id_func WHERE s.id_karyawan = k.id_karyawan ORDER BY s.id_session DESC LIMIT 1) as latest_func_n1,
        (SELECT f.func_n2 FROM score s JOIN func f ON s.id_func = f.id_func WHERE s.id_karyawan = k.id_karyawan ORDER BY s.id_session DESC LIMIT 1) as latest_func_n2
    FROM karyawan k
    WHERE $where_search
    ORDER BY k.nama_karyawan ASC
    LIMIT $limit OFFSET $offset
";
$employees = $conn->query($list_sql);
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

        /* --- TABLE --- */
        .table-card { background: white; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.03); overflow: hidden; margin-bottom: 40px; margin-top: 20px; }
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
        
        .badge-high { background-color: #dcfce7; color: #15803d; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; display: inline-block; }
        .badge-med { background-color: #fff7ed; color: #c2410c; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; display: inline-block; }
        .badge-low { background-color: #f3f4f6; color: #6b7280; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; display: inline-block; }
        
        /* SNAKE ANIMATION BUTTON STYLES - with NO WRAPPING */
        .btn-view { 
            position: relative; 
            background: linear-gradient(90deg, #FF9A02 0%, #FED404 100%); 
            color: white; 
            border: none; 
            padding: 10px 14px; /* Slightly tighter padding */
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
            white-space: nowrap; /* CRITICAL: Prevents text from wrapping */
        }
        .btn-view:active { transform: scale(0.98); }
        .btn-view span { position: relative; z-index: 2; }
        .btn-view svg { position: absolute; top: -2px; left: -2px; width: calc(100% + 4px); height: calc(100% + 4px); fill: none; pointer-events: none; overflow: visible; }
        .btn-view rect { width: 100%; height: 100%; rx: 25px; ry: 25px; stroke: url(#multiColorGradient); stroke-width: 2; stroke-dasharray: 120, 380; stroke-dashoffset: 0; opacity: 0; transition: opacity 0.3s; }
        .btn-view:hover rect { opacity: 1; animation: snakeMove 2s linear infinite; }
        @keyframes snakeMove { from { stroke-dashoffset: 500; } to { stroke-dashoffset: 0; } }

        .pagination-container { padding: 20px 25px; display: flex; justify-content: space-between; align-items: center; font-size: 13px; color: #666; }
        .page-btn { width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; border-radius: 6px; cursor: pointer; color: #666; text-decoration: none; }
        .page-btn.active { background-color: #197B40; color: white; font-weight: 600; }
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
                <a href="reports.php">Trainings</a>
                <a href="employee_reports.php" class="active">Employees</a>
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

        <div class="table-card">
            <div class="table-header-strip">
                <div class="table-title">Employee List</div>
                <div class="table-actions">
                    <div class="search-box">
                        <img src="icons/search.ico" style="width: 26px; height: 26px; transform: scale(1.8); margin-right: 4px;">
                        <input type="text" id="searchInput" placeholder="Search by Name or Index..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <a href="export_employees.php" id="exportBtn" class="btn-export">
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
                            
                            // Apply Abbreviation Logic
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

            <div class="pagination-container" id="paginationContainer">
                <div>Showing <?php echo ($total_rows > 0 ? $offset + 1 : 0); ?> to <?php echo min($offset + $limit, $total_rows); ?> of <?php echo $total_rows; ?> Records</div>
                <div class="pagination-controls">
                    <?php if($page > 1): $prev = $page - 1; echo "<a href='?page=$prev&search=$search' class='page-btn'>&lt;</a>"; endif; ?>
                    <a href="#" class="page-btn active"><?php echo $page; ?></a>
                    <?php if($page < $total_pages): $next = $page + 1; echo "<a href='?page=$next&search=$search' class='page-btn'>&gt;</a>"; endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        lucide.createIcons();

        // --- LIVE SEARCH LOGIC ---
        const searchInput = document.getElementById('searchInput');
        const tableBody = document.getElementById('tableBody');
        const paginationContainer = document.getElementById('paginationContainer');
        const exportBtn = document.getElementById('exportBtn');

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
            exportBtn.href = `export_employees.php?search=${encodeURIComponent(query)}`;

            fetch(`?ajax_search=${encodeURIComponent(query)}`)
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