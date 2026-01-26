<?php
session_start();
require 'db_connect.php'; 

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'] ?? 'User';
$initials = strtoupper(substr($username, 0, 2));

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

// --- GET PARAMETERS ---
$search = $_GET['search'] ?? '';
$filter_category = $_GET['category'] ?? 'All Categories'; 
$filter_type = $_GET['type'] ?? 'All Types'; 
$filter_method = $_GET['method'] ?? 'All Methods';
$filter_code = $_GET['code'] ?? 'All Codes'; 
$start_date = $_GET['start'] ?? '';
$end_date = $_GET['end'] ?? '';

// Check active filters for button styling
$has_active_filters = (
    $filter_category !== 'All Categories' || 
    $filter_type !== 'All Types' || 
    $filter_method !== 'All Methods' || 
    $filter_code !== 'All Codes' || 
    !empty($start_date) || 
    !empty($end_date)
);

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// --- AJAX HANDLER (Live Search) ---
if (isset($_GET['ajax_search'])) {
    $search_term = $_GET['ajax_search'];
    $where_ajax = ["1=1"];
    $params = [];

    if (!empty($search_term)) {
        $where_ajax[] = "(t.nama_training LIKE ? OR ts.code_sub LIKE ?)";
        $params[] = "%$search_term%";
        $params[] = "%$search_term%";
    }
    
    if (isset($_GET['category']) && $_GET['category'] !== 'All Categories') {
        $where_ajax[] = "t.jenis = ?";
        $params[] = $_GET['category'];
    }
    if (isset($_GET['type']) && $_GET['type'] !== 'All Types') {
        $where_ajax[] = "t.type = ?";
        $params[] = $_GET['type'];
    }
    if (isset($_GET['method']) && $_GET['method'] !== 'All Methods') {
        $where_ajax[] = "ts.method = ?";
        $params[] = $_GET['method'];
    }
    if (isset($_GET['code']) && $_GET['code'] !== 'All Codes') {
        $where_ajax[] = "ts.code_sub = ?";
        $params[] = $_GET['code'];
    }
    if (isset($_GET['start']) && !empty($_GET['start'])) {
        $where_ajax[] = "ts.date_start >= ?";
        $params[] = $_GET['start'];
    }
    if (isset($_GET['end']) && !empty($_GET['end'])) {
        $where_ajax[] = "ts.date_start <= ?";
        $params[] = $_GET['end'];
    }
    
    $where_sql_ajax = implode(' AND ', $where_ajax);

    // Count Total
    $count_sql = "SELECT COUNT(DISTINCT ts.id_session) FROM training_session ts JOIN training t ON ts.id_training = t.id_training WHERE $where_sql_ajax";
    $stmtCount = $pdo->prepare($count_sql);
    $stmtCount->execute($params);
    $total_records = $stmtCount->fetchColumn();
    $total_pages = ceil($total_records / $limit);
    
    // Fetch Data
    $data_sql = "
        SELECT ts.id_session, t.nama_training, ts.code_sub, t.jenis AS category, t.type AS training_type, ts.method, ts.credit_hour, ts.date_start, ts.date_end, 
               COUNT(s.id_score) as participants, AVG(s.pre) as avg_pre, AVG(s.post) as avg_post
        FROM training_session ts
        JOIN training t ON ts.id_training = t.id_training
        LEFT JOIN score s ON ts.id_session = s.id_session
        WHERE $where_sql_ajax
        GROUP BY ts.id_session
        ORDER BY ts.date_start DESC
        LIMIT $limit OFFSET $offset 
    "; 
    $stmtData = $pdo->prepare($data_sql);
    $stmtData->execute($params);
    $results = $stmtData->fetchAll();

    ob_start();
    if (count($results) > 0) {
        foreach($results as $row) {
            $category = $row['category'] ?? '';
            $method = $row['method'] ?? '';
            $training_type = $row['training_type'] ?? '';
            
            $catClass = (stripos($category, 'Technical') !== false) ? 'type-tech' : ((stripos($category, 'Soft') !== false) ? 'type-soft' : 'type-default');
            $methodClass = (stripos($method, 'Inclass') !== false) ? 'method-inclass' : 'method-online';
            $avgScore = $row['avg_post'] ? number_format($row['avg_post'], 1) . '%' : '-';
            
            $date_display = formatDateRange($row['date_start'] ?? '', $row['date_end'] ?? '');
            ?>
            <tr>
                <td>
                    <div class="training-cell">
                        <div class="icon-box"><i data-lucide="book-open" style="width:18px;"></i></div>
                        <div>
                            <div class="training-name-text"><?php echo htmlspecialchars($row['nama_training'] ?? ''); ?></div>
                            <div style="font-size:11px; color:#888;"><?php echo htmlspecialchars($row['code_sub'] ?? ''); ?></div>
                        </div>
                    </div>
                </td>
                <td style="white-space: nowrap; font-family:'Poppins', sans-serif; font-size:12px; font-weight:500; color: #555;"><?php echo $date_display; ?></td>
                <td><span class="badge <?php echo $catClass; ?>"><?php echo htmlspecialchars($category); ?></span></td>
                <td><span class="badge type-info"><?php echo htmlspecialchars($training_type); ?></span></td>
                <td><span class="badge <?php echo $methodClass; ?>"><?php echo htmlspecialchars($method); ?></span></td>
                <td style="text-align:center; font-weight:600;"><?php echo htmlspecialchars($row['credit_hour'] ?? '0'); ?></td>
                <td style="text-align:center;"><?php echo $row['participants']; ?></td>
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
        echo '<tr><td colspan="9" style="text-align:center; padding: 25px; color:#888;">No records found.</td></tr>';
    }
    $table_html = ob_get_clean();

    ob_start();
    ?>
    <div>Showing <?php echo ($total_records > 0 ? $offset + 1 : 0); ?> to <?php echo min($offset + $limit, $total_records); ?> of <?php echo $total_records; ?> Records</div>
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
    exit;
}

// --- STANDARD LOAD QUERY ---
$where_clauses = ["1=1"];
$params = [];

if (!empty($search)) {
    $where_clauses[] = "(t.nama_training LIKE ? OR ts.code_sub LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($filter_category !== 'All Categories') {
    $where_clauses[] = "t.jenis = ?";
    $params[] = $filter_category;
}
if ($filter_type !== 'All Types') {
    $where_clauses[] = "t.type = ?";
    $params[] = $filter_type;
}
if ($filter_method !== 'All Methods') {
    $where_clauses[] = "ts.method = ?";
    $params[] = $filter_method;
}
if ($filter_code !== 'All Codes') {
    $where_clauses[] = "ts.code_sub = ?";
    $params[] = $filter_code;
}

if (!empty($start_date) && !empty($end_date)) {
    $where_clauses[] = "ts.date_start >= ? AND ts.date_start <= ?";
    $params[] = $start_date;
    $params[] = $end_date;
} elseif (!empty($start_date)) {
    $where_clauses[] = "ts.date_start >= ?";
    $params[] = $start_date;
} elseif (!empty($end_date)) {
    $where_clauses[] = "ts.date_start <= ?";
    $params[] = $end_date;
}

$where_sql = implode(' AND ', $where_clauses);

// Count Total
$count_sql = "SELECT COUNT(DISTINCT ts.id_session) FROM training_session ts JOIN training t ON ts.id_training = t.id_training WHERE $where_sql";
$stmtCount = $pdo->prepare($count_sql);
$stmtCount->execute($params);
$total_records = $stmtCount->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Fetch Data
$data_sql = "
    SELECT ts.id_session, t.nama_training, ts.code_sub, t.jenis AS category, t.type AS training_type, ts.method, ts.credit_hour, ts.date_start, ts.date_end, 
           COUNT(s.id_score) as participants, AVG(s.pre) as avg_pre, AVG(s.post) as avg_post
    FROM training_session ts
    JOIN training t ON ts.id_training = t.id_training
    LEFT JOIN score s ON ts.id_session = s.id_session
    WHERE $where_sql
    GROUP BY ts.id_session
    ORDER BY ts.date_start DESC
    LIMIT $limit OFFSET $offset
";
$stmtData = $pdo->prepare($data_sql);
$stmtData->execute($params);
$results = $stmtData->fetchAll();

// Fetch Filter Options
$categories_opt = $pdo->query("SELECT DISTINCT jenis FROM training WHERE jenis IS NOT NULL AND jenis != '' ORDER BY jenis")->fetchAll();
$types_opt = $pdo->query("SELECT DISTINCT type FROM training WHERE type IS NOT NULL AND type != '' ORDER BY type")->fetchAll();
$methods_opt = $pdo->query("SELECT DISTINCT method FROM training_session WHERE method IS NOT NULL AND method != '' ORDER BY method")->fetchAll();
$codes_opt = $pdo->query("SELECT DISTINCT code_sub FROM training_session WHERE code_sub IS NOT NULL AND code_sub != '' ORDER BY code_sub")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GGF - Training Sessions Report</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="icons/icon.png">
    <style>
        /* --- RESET & BASIC --- */
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Poppins', sans-serif; }
        body { background-color: #117054; padding: 0; margin: 0; overflow: hidden; height: 100vh; }

        .main-wrapper {
            background-color: #f3f4f7; padding: 20px 40px; height: 100vh; overflow-y: auto;
            transition: all 0.4s cubic-bezier(0.32, 1, 0.23, 1); transform-origin: center left;
            width: 100%; position: relative; display: flex; flex-direction: column;
        }
        .drawer-open .main-wrapper {
            transform: scale(0.85) translateX(24px); border-radius: 35px; pointer-events: auto;
            box-shadow: -20px 0 40px rgba(0,0,0,0.2); overflow: hidden;
        }

        /* --- NAVBAR --- */
        .navbar {
            background-color: #197B40; height: 70px; border-radius: 0px 0px 25px 25px; 
            display: flex; align-items: center; padding: 0 30px; justify-content: space-between; 
            margin: -20px -40px 30px -40px; padding-left: 70px; padding-right: 70px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1); flex-shrink: 0;
            position: sticky; top: -20px; z-index: 1000; 
        }
        .logo-section img { height: 40px; }
        .nav-links { display: flex; gap: 30px; align-items: center; }
        .nav-links a { color: white; text-decoration: none; font-size: 14px; font-weight: 600; opacity: 0.8; transition: 0.3s; white-space: nowrap; }
        .nav-links a:hover { opacity: 1; }
        .nav-links a.active { background: white; color: #197B40; padding: 8px 20px; border-radius: 20px; opacity: 1; }
        .nav-right { display: flex; align-items: center; gap: 20px; }
        .user-profile { display: flex; align-items: center; gap: 12px; color: white; }
        .avatar-circle { width: 35px; height: 35px; background-color: #FF9A02; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 12px; flex-shrink: 0; }
        .btn-signout {
            background-color: #d32f2f; color: white !important; text-decoration: none;
            font-size: 13px; font-weight: 600; padding: 8px 20px; border-radius: 20px;
            transition: background 0.3s; opacity: 1 !important; white-space: nowrap;
        }
        .btn-signout:hover { background-color: #b71c1c; }

        /* --- TABLE CARD --- */
        .table-card { 
            background: white; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.03); 
            overflow: hidden; margin-bottom: 40px; margin-top: 20px; 
            display: flex; flex-direction: column; flex-grow: 1;
        }
        .table-header-strip { 
            background-color: #197B40; color: white; padding: 15px 25px; 
            display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; gap: 15px;
        }
        .table-title { font-weight: 700; font-size: 16px; margin: 0; white-space: nowrap; }
        .table-actions { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; justify-content: flex-end; }
        
        .search-box { 
            background-color: white; border-radius: 50px; height: 35px; width: 250px; 
            display: flex; align-items: center; padding: 0 15px; transition: width 0.3s;
        }
        .search-box img { width: 16px; height: 16px; margin-right: 8px; flex-shrink: 0; }
        .search-box input { border: none; background: transparent; outline: none; height: 100%; flex: 1; padding-left: 10px; font-size: 13px; color: #333; }
        
        .btn-action-small { 
            height: 35px; padding: 0 15px; border: none; border-radius: 50px; 
            background: white; color: #197B40; font-weight: 600; font-size: 13px; 
            cursor: pointer; display: flex; align-items: center; gap: 6px; 
            text-decoration: none; transition: 0.2s; white-space: nowrap;
        }
        .btn-action-small:hover { background-color: #f0fdf4; }
        .btn-action-small.active-filter { background-color: #fffcf5; color: #e65100; border: 1px solid #FF9A02; }
        .btn-action-small.active-filter:hover { background-color: #fff5e0; }
        
        .table-responsive { flex-grow: 1; overflow-y: auto; width: 100%; }
        table { width: 100%; border-collapse: collapse; min-width: 800px; }
        th { 
            text-align: left; padding: 15px 30px; font-size: 12px; color: #555; 
            font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
            background: white; border-bottom: 2px solid #eee; position: sticky; top: 0; z-index: 10; 
        }
        td { padding: 15px 30px; font-size: 13px; color: #333; border-bottom: 1px solid #f9f9f9; vertical-align: middle; }
        td:not(:first-child) { white-space: nowrap; }
        tr:hover { background-color: #fafbfc; }

        .training-cell { display: flex; align-items: center; gap: 15px; }
        .training-cell .icon-box { 
            background: #e8f5e9; color: #197B40; width: 40px; height: 40px; 
            border-radius: 8px; display: flex; align-items: center; justify-content: center;
            flex-shrink: 0; min-width: 40px;
        }
        .training-name-text { font-weight: 700; line-height: 1.2; font-size: 14px; }
        
        .badge { padding: 6px 14px; border-radius: 6px; font-size: 11px; font-weight: 600; display: inline-block; letter-spacing: 0.3px; }
        .type-tech { background: #E3F2FD; color: #1565C0; border: 1px solid rgba(21, 101, 192, 0.1); }
        .type-soft { background: #FFF3E0; color: #EF6C00; border: 1px solid rgba(239, 108, 0, 0.1); }
        .type-default { background: #F5F5F5; color: #616161; }
        .type-info { background: #F3E5F5; color: #7B1FA2; border: 1px solid rgba(123, 31, 162, 0.1); }
        .method-online { background: #E0F2F1; color: #00695C; border: 1px solid rgba(0, 105, 92, 0.1); }
        .method-inclass { background: #FCE4EC; color: #C2185B; border: 1px solid rgba(194, 24, 91, 0.1); }
        .score { color: #197B40; font-weight: bold; }
        
        .btn-view { 
            position: relative; background: linear-gradient(90deg, #FF9A02 0%, #FED404 100%); 
            color: white; border: none; padding: 10px 14px; border-radius: 25px; 
            font-size: 12px; font-weight: bold; cursor: pointer; display: inline-flex; 
            align-items: center; justify-content: center; gap: 5px; overflow: visible; 
            transition: transform 0.2s; white-space: nowrap; 
        }
        .btn-view:active { transform: scale(0.98); }
        .btn-view span { position: relative; z-index: 2; }
        .btn-view svg { position: absolute; top: -2px; left: -2px; width: calc(100% + 4px); height: calc(100% + 4px); fill: none; pointer-events: none; overflow: visible; }
        .btn-view rect { width: 100%; height: 100%; rx: 25px; ry: 25px; stroke: url(#multiColorGradient); stroke-width: 2; stroke-dasharray: 120, 380; stroke-dashoffset: 0; opacity: 0; transition: opacity 0.3s; }
        .btn-view:hover rect { opacity: 1; animation: snakeMove 2s linear infinite; }
        @keyframes snakeMove { from { stroke-dashoffset: 500; } to { stroke-dashoffset: 0; } }
        
        .pagination-container { padding: 20px 25px; display: flex; justify-content: space-between; align-items: center; font-size: 13px; color: #666; border-top: 1px solid #f9f9f9; flex-shrink: 0; }
        .pagination-controls { display: flex; align-items: center; gap: 8px; }
        .page-num { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 8px; cursor: pointer; text-decoration: none; color: #4a4a4a; font-weight: 500; }
        .page-num.active { background-color: #197B40; color: white; }
        .btn-next { display: flex; align-items: center; gap: 5px; color: #4a4a4a; text-decoration: none; cursor: pointer; }

        .filter-overlay { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.05); z-index: 900; display: none; opacity: 0; transition: opacity 0.3s; pointer-events: none; }
        .filter-drawer { position: fixed; top: 20px; bottom: 20px; right: -400px; width: 380px; background: white; z-index: 1001; box-shadow: -10px 0 30px rgba(0,0,0,0.15); transition: right 0.4s cubic-bezier(0.32, 1, 0.23, 1); display: flex; flex-direction: column; border-radius: 35px; }
        .drawer-open .filter-overlay { display: block; opacity: 1; pointer-events: auto; }
        .drawer-open .filter-drawer { right: 20px; }
        .drawer-header { background-color: #197B40; color: white; padding: 25px; display: flex; justify-content: space-between; align-items: center; border-top-left-radius: 35px; border-top-right-radius: 35px; }
        .drawer-header h4 { font-size: 16px; font-weight: 600; }
        .drawer-content { padding: 25px; overflow-y: auto; flex-grow: 1; }
        
        .filter-group { margin-bottom: 20px; }
        .filter-group label { display: block; font-size: 13px; font-weight: 600; color: #555; margin-bottom: 8px; }
        .date-row { display: flex; gap: 10px; }
        
        .date-input-wrapper { position: relative; flex: 1; }
        .date-input-wrapper input[type="date"] { 
            width: 100%; padding: 10px 15px 10px 40px; border: 1px solid #e0e0e0; 
            border-radius: 50px; font-size: 13px; outline: none; color: #333; font-family: 'Poppins', sans-serif;
            background-color: #fff; cursor: pointer; transition: all 0.2s; position: relative;
        }
        /* FIX FOR DATE PICKER: Make full button clickable & hide default icon */
        .date-input-wrapper input[type="date"]::-webkit-calendar-picker-indicator {
            position: absolute; top: 0; left: 0; right: 0; bottom: 0; 
            width: 100%; height: 100%; color: transparent; background: transparent; cursor: pointer;
        }
        .date-icon { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #197B40; width: 16px; pointer-events: none; z-index: 1; }

        .filter-group select { width: 100%; padding: 10px 15px; border: 1px solid #ddd; border-radius: 8px; outline: none; font-size: 13px; color: #333; appearance: none; background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="%23999" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>') no-repeat right 12px center; }
        
        .drawer-footer { padding: 20px 25px; border-top: 1px solid #eee; display: flex; gap: 15px; border-bottom-left-radius: 35px; border-bottom-right-radius: 35px; }
        .btn-reset { background: #fff; border: 1px solid #ddd; color: #666; padding: 12px; border-radius: 50px; flex: 1; font-weight: 600; cursor: pointer; transition: 0.2s; }
        .btn-reset:hover { background: #f9f9f9; }
        .btn-apply { position: relative; background: #197B40; color: white; border: none; padding: 12px 24px; border-radius: 25px; flex: 1; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: background 0.2s; overflow: visible; }
        .btn-apply svg { position: absolute; top: -2px; left: -2px; width: calc(100% + 4px); height: calc(100% + 4px); fill: none; pointer-events: none; overflow: visible; }
        .btn-apply rect { width: 100%; height: 100%; rx: 25px; ry: 25px; stroke: #FF9A02; stroke-width: 3; stroke-dasharray: 120, 380; stroke-dashoffset: 0; opacity: 0; transition: opacity 0.3s; }
        .btn-apply:hover { background: #145a32; }
        .btn-apply:hover rect { opacity: 1; animation: snakeBorder 2s linear infinite; }
        @keyframes snakeBorder { from { stroke-dashoffset: 500; } to { stroke-dashoffset: 0; } }

        @media (max-width: 1024px) {
            .main-wrapper { padding: 20px; }
            .navbar { margin: -20px -20px 20px -20px; padding-left: 30px; padding-right: 30px; }
        }
        @media (max-width: 768px) {
            .main-wrapper { padding: 15px; height: 100vh; }
            .navbar { margin: -15px -15px 15px -15px; padding: 10px 20px; height: auto; flex-wrap: wrap; gap: 10px; border-radius: 0 0 20px 20px; }
            .logo-section { order: 1; }
            .nav-right { order: 2; margin-left: auto; }
            .nav-links { order: 3; width: 100%; overflow-x: auto; white-space: nowrap; padding-bottom: 5px; gap: 20px; -ms-overflow-style: none; scrollbar-width: none; }
            .nav-links::-webkit-scrollbar { display: none; }
        }
        @media (max-width: 480px) {
            .table-header-strip { flex-direction: column; align-items: stretch; gap: 15px; }
            .table-actions { flex-direction: column; width: 100%; }
            .search-box { width: 100%; }
            .btn-action-small { width: 100%; justify-content: center; }
            .filter-drawer { width: 90%; right: -100%; }
            .drawer-open .filter-drawer { right: 5%; }
        }
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
                <a href="reports.php" class="active">Trainings</a>
                <a href="employee_reports.php">Employees</a>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <a href="upload.php">Upload Data</a>
                <?php endif; ?>
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
                <div class="table-title">Training Sessions Report</div>
                <div class="table-actions">
                    <div class="search-box">
                        <img src="icons/search.ico" style="width: 26px; height: 26px; transform: scale(1.8); margin-right: 4px;" alt="Search">
                        <input type="text" id="liveSearchInput" placeholder="Search training..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <button class="btn-action-small <?php echo $has_active_filters ? 'active-filter' : ''; ?>" onclick="toggleDrawer()">
                        <img src="icons/filter.ico" style="width: 26px; height: 26px; transform: scale(1.8); margin-right: 4px;" alt="Filter">
                        Filters
                    </button>
                </div>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Training Name</th>
                            <th>Date</th>
                            <th>Category</th> 
                            <th>Type</th>     
                            <th>Method</th>
                            <th style="text-align:center;">Credit Hour</th> 
                            <th style="text-align:center;">Participants</th>
                            <th>Avg Score</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <?php if (count($results) > 0): ?>
                            <?php foreach($results as $row): 
                                $category = $row['category'] ?? '';
                                $method = $row['method'] ?? '';
                                $training_type = $row['training_type'] ?? '';
                                
                                $catClass = (stripos($category, 'Technical') !== false) ? 'type-tech' : ((stripos($category, 'Soft') !== false) ? 'type-soft' : 'type-default');
                                $methodClass = (stripos($method, 'Inclass') !== false) ? 'method-inclass' : 'method-online';
                                $avgScore = $row['avg_post'] ? number_format($row['avg_post'], 1) . '%' : '-';
                                
                                $date_display = formatDateRange($row['date_start'] ?? '', $row['date_end'] ?? '');
                            ?>
                            <tr>
                                <td>
                                    <div class="training-cell">
                                        <div class="icon-box"><i data-lucide="book-open" style="width:18px;"></i></div>
                                        <div>
                                            <div class="training-name-text"><?php echo htmlspecialchars($row['nama_training'] ?? ''); ?></div>
                                            <div style="font-size:11px; color:#888;"><?php echo htmlspecialchars($row['code_sub'] ?? ''); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td style="white-space: nowrap; font-family:'Poppins', sans-serif; font-size:12px; font-weight:500; color: #555;"><?php echo $date_display; ?></td>
                                <td><span class="badge <?php echo $catClass; ?>"><?php echo htmlspecialchars($category); ?></span></td>
                                <td><span class="badge type-info"><?php echo htmlspecialchars($training_type); ?></span></td>
                                <td><span class="badge <?php echo $methodClass; ?>"><?php echo htmlspecialchars($method); ?></span></td>
                                <td style="text-align:center; font-weight:600;"><?php echo htmlspecialchars($row['credit_hour'] ?? '0'); ?></td>
                                <td style="text-align:center;"><?php echo $row['participants']; ?></td>
                                <td class="score"><?php echo $avgScore; ?></td>
                                <td>
                                    <button class="btn-view" onclick="window.location.href='tdetails.php?id=<?php echo $row['id_session']; ?>'">
                                        <span>View Details</span>
                                        <svg><rect x="0" y="0"></rect></svg>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="9" style="text-align:center; padding: 25px; color:#888;">No records found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="pagination-container">
                <div>Showing <?php echo ($total_records > 0 ? $offset + 1 : 0); ?> to <?php echo min($offset + $limit, $total_records); ?> of <?php echo $total_records; ?> Records</div>
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

    <div class="filter-overlay" onclick="toggleDrawer()"></div>
    <div class="filter-drawer">
        <div class="drawer-header">
            <h4>Filter Options</h4>
            <i data-lucide="x" style="cursor:pointer; color: white;" onclick="toggleDrawer()"></i>
        </div>
        <div class="drawer-content">
            <div class="filter-group">
                <label>Date Range</label>
                <div class="date-row">
                    <div class="date-input-wrapper">
                        <i data-lucide="calendar" class="date-icon"></i>
                        <input type="date" id="startDate" value="<?php echo $start_date; ?>">
                    </div>
                    <div class="date-input-wrapper">
                        <i data-lucide="calendar" class="date-icon"></i>
                        <input type="date" id="endDate" value="<?php echo $end_date; ?>">
                    </div>
                </div>
            </div>

            <div class="filter-group">
                <label>Category</label>
                <select id="filterCategory">
                    <option value="All Categories">All Categories</option>
                    <?php foreach($categories_opt as $t): ?>
                        <option value="<?php echo htmlspecialchars($t['jenis']); ?>" <?php if($filter_category == $t['jenis']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($t['jenis']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label>Training Type</label>
                <select id="filterType">
                    <option value="All Types">All Types</option>
                    <?php foreach($types_opt as $t): ?>
                        <option value="<?php echo htmlspecialchars($t['type']); ?>" <?php if($filter_type == $t['type']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($t['type']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label>Method</label>
                <select id="filterMethod">
                    <option value="All Methods">All Methods</option>
                    <?php foreach($methods_opt as $m): ?>
                        <option value="<?php echo htmlspecialchars($m['method']); ?>" <?php if($filter_method == $m['method']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($m['method']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label>Sub Code</label>
                <select id="filterCode">
                    <option value="All Codes">All Codes</option>
                    <?php foreach($codes_opt as $c): ?>
                        <option value="<?php echo htmlspecialchars($c['code_sub']); ?>" <?php if($filter_code == $c['code_sub']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($c['code_sub']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
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
        
        // Capture current filters
        const currentCategory = "<?php echo htmlspecialchars($filter_category); ?>";
        const currentType = "<?php echo htmlspecialchars($filter_type); ?>";
        const currentMethod = "<?php echo htmlspecialchars($filter_method); ?>";
        const currentCode = "<?php echo htmlspecialchars($filter_code); ?>";
        const currentStart = "<?php echo htmlspecialchars($start_date); ?>";
        const currentEnd = "<?php echo htmlspecialchars($end_date); ?>";

        function changePage(page) {
            const query = searchInput.value;
            fetchData(query, page);
        }

        function fetchData(query, page = 1) {
            // Include filters in AJAX URL
            let url = `?ajax_search=${encodeURIComponent(query)}&page=${page}`;
            if(currentCategory !== 'All Categories') url += `&category=${encodeURIComponent(currentCategory)}`;
            if(currentType !== 'All Types') url += `&type=${encodeURIComponent(currentType)}`;
            if(currentMethod !== 'All Methods') url += `&method=${encodeURIComponent(currentMethod)}`;
            if(currentCode !== 'All Codes') url += `&code=${encodeURIComponent(currentCode)}`;
            if(currentStart) url += `&start=${encodeURIComponent(currentStart)}`;
            if(currentEnd) url += `&end=${encodeURIComponent(currentEnd)}`;

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    tableBody.innerHTML = data.table;
                    document.querySelector('.pagination-container').innerHTML = data.pagination;
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

        function applyFilters() {
            const search = document.getElementById('liveSearchInput').value;
            const category = document.getElementById('filterCategory').value;
            const type = document.getElementById('filterType').value;
            const method = document.getElementById('filterMethod').value;
            const code = document.getElementById('filterCode').value;
            const start = document.getElementById('startDate').value;
            const end = document.getElementById('endDate').value;

            if (start && end) {
                if (new Date(start) > new Date(end)) {
                    alert("Date Range Error: The End Date cannot be earlier than the Start Date.");
                    return; 
                }
            }

            const params = new URLSearchParams();
            if(search) params.set('search', search);
            if(category !== 'All Categories') params.set('category', category);
            if(type !== 'All Types') params.set('type', type);
            if(method !== 'All Methods') params.set('method', method);
            if(code !== 'All Codes') params.set('code', code);
            if(start) params.set('start', start);
            if(end) params.set('end', end);

            window.location.search = params.toString();
        }
    </script>
</body>
</html>