<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
require 'db_connect.php';

// --- HELPER FUNCTION: Smart Date Formatting ---
function formatDateRange($start_date, $end_date) {
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

// ==========================================
//  0. AJAX HANDLER FOR FILTER DROPDOWNS
// ==========================================
if (isset($_GET['get_filter_options'])) {
    $sel_bu = $_GET['bu'] ?? 'All';
    $sel_fn1 = $_GET['func_n1'] ?? 'All';

    $response = ['fn1' => [], 'fn2' => []];

    // 1. Get valid Func N-1
    $q1 = "SELECT DISTINCT f.func_n1 FROM func f 
           JOIN score s ON f.id_func = s.id_func 
           JOIN bu b ON s.id_bu = b.id_bu 
           WHERE f.func_n1 IS NOT NULL AND f.func_n1 != ''";
    if ($sel_bu !== 'All') {
        $q1 .= " AND b.nama_bu = '" . $conn->real_escape_string($sel_bu) . "'";
    }
    $q1 .= " ORDER BY f.func_n1";
    $res1 = $conn->query($q1);
    while($r = $res1->fetch_assoc()) { $response['fn1'][] = $r['func_n1']; }

    // 2. Get valid Func N-2
    $q2 = "SELECT DISTINCT f.func_n2 FROM func f 
           JOIN score s ON f.id_func = s.id_func 
           JOIN bu b ON s.id_bu = b.id_bu 
           WHERE f.func_n2 IS NOT NULL AND f.func_n2 != ''";
    if ($sel_bu !== 'All') {
        $q2 .= " AND b.nama_bu = '" . $conn->real_escape_string($sel_bu) . "'";
    }
    if ($sel_fn1 !== 'All') {
        $q2 .= " AND f.func_n1 = '" . $conn->real_escape_string($sel_fn1) . "'";
    }
    $q2 .= " ORDER BY f.func_n2";
    $res2 = $conn->query($q2);
    while($r = $res2->fetch_assoc()) { $response['fn2'][] = $r['func_n2']; }

    echo json_encode($response);
    exit;
}

// ==========================================
//  1. AJAX HANDLER (Live Search - Training List)
// ==========================================
if (isset($_GET['ajax_search'])) {
    $search_term = $_GET['ajax_search'];
    
    // Updated query to fetch date, method, code for the table
    $base_sql = "
        SELECT t.nama_training, ts.code_sub, ts.date_start, ts.date_end, ts.method
        FROM score s
        JOIN training_session ts ON s.id_session = ts.id_session
        JOIN training t ON ts.id_training = t.id_training
        LEFT JOIN bu b ON s.id_bu = b.id_bu
        LEFT JOIN func f ON s.id_func = f.id_func
    ";

    $where_ajax = ["1=1"];
    if (!empty($search_term)) {
        $safe_search = $conn->real_escape_string($search_term);
        $where_ajax[] = "t.nama_training LIKE '%$safe_search%'";
    }
    
    // Group by session to distinct dates
    $sql_ajax = $base_sql . " WHERE " . implode(' AND ', $where_ajax) . " GROUP BY ts.id_session ORDER BY ts.date_start DESC LIMIT 50";
    
    $result = $conn->query($sql_ajax);

    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $date_display = formatDateRange($row['date_start'], $row['date_end']);
            $methodClass = (stripos($row['method'], 'Inclass') !== false) ? 'method-inclass' : 'method-online';
            ?>
            <tr onclick="selectTraining(this, '<?php echo addslashes($row['nama_training']); ?>')" style="cursor: pointer;">
                <td>
                    <div class="training-cell">
                        <div class="icon-box"><i data-lucide="book-open" style="width:18px;"></i></div>
                        <div>
                            <div class="training-name-text"><?php echo htmlspecialchars($row['nama_training']); ?></div>
                            <div style="font-size:11px; color:#888;"><?php echo htmlspecialchars($row['code_sub']); ?></div>
                        </div>
                    </div>
                </td>
                <td style="white-space: nowrap; font-family:'Poppins', sans-serif; font-size:12px; font-weight:500; color: #555;"><?php echo $date_display; ?></td>
                <td><span class="badge <?php echo $methodClass; ?>"><?php echo htmlspecialchars($row['method']); ?></span></td>
            </tr>
            <?php
        }
    } else {
        echo '<tr><td colspan="3" style="text-align:center; padding: 20px; color:#777;">No training programs found.</td></tr>';
    }
    exit; 
}
// ==========================================
//  END AJAX HANDLER
// ==========================================


// --- 2. STANDARD PAGE LOAD LOGIC ---

$f_bu = $_GET['bu'] ?? 'All';
$f_func1 = $_GET['func_n1'] ?? 'All';
$f_func2 = $_GET['func_n2'] ?? 'All';
$f_type = $_GET['type'] ?? 'All';
$f_search = $_GET['search'] ?? '';
$f_start = $_GET['start'] ?? '';
$f_end = $_GET['end'] ?? '';
$f_training_name = $_GET['training_name'] ?? 'All';

// Check if any filter is active for button styling
$has_active_filters = (
    $f_bu !== 'All' || 
    $f_func1 !== 'All' || 
    $f_func2 !== 'All' || 
    $f_type !== 'All' || 
    !empty($f_start) || 
    !empty($f_end)
);

// Build SQL Query
$where_clauses = ["1=1"];

if ($f_bu !== 'All') $where_clauses[] = "b.nama_bu = '$f_bu'";
if ($f_func1 !== 'All') $where_clauses[] = "f.func_n1 = '$f_func1'";
if ($f_func2 !== 'All') $where_clauses[] = "f.func_n2 = '$f_func2'";
if ($f_type !== 'All') $where_clauses[] = "t.jenis = '$f_type'";
if (!empty($f_search)) $where_clauses[] = "t.nama_training LIKE '%$f_search%'";

// Date Logic
if (!empty($f_start) && !empty($f_end)) {
    $where_clauses[] = "ts.date_start >= '$f_start' AND ts.date_start <= '$f_end'";
} elseif (!empty($f_start)) {
    $where_clauses[] = "ts.date_start >= '$f_start'";
} elseif (!empty($f_end)) {
    $where_clauses[] = "ts.date_start <= '$f_end'";
}

// Apply specific training filter for the TOP STATS only
if ($f_training_name !== 'All') $where_clauses[] = "t.nama_training = '$f_training_name'";

$where_sql = implode(' AND ', $where_clauses);

// Base Join String
$join_sql = "
    FROM score s
    JOIN training_session ts ON s.id_session = ts.id_session
    JOIN training t ON ts.id_training = t.id_training
    LEFT JOIN bu b ON s.id_bu = b.id_bu
    LEFT JOIN func f ON s.id_func = f.id_func
    WHERE $where_sql
";

// --- 3. CALCULATE STATS ---
$res_hours = $conn->query("SELECT SUM(ts.credit_hour) as total " . $join_sql);
$total_hours_raw = $res_hours->fetch_assoc()['total'] ?? 0;

$res_offline = $conn->query("SELECT SUM(ts.credit_hour) as total " . $join_sql . " AND (ts.method LIKE '%Inclass%')");
$hours_offline_raw = $res_offline->fetch_assoc()['total'] ?? 0;

$res_online = $conn->query("SELECT SUM(ts.credit_hour) as total " . $join_sql . " AND (ts.method LIKE '%Hybrid%' OR ts.method LIKE '%Webinar%' OR ts.method LIKE '%Self-paced%')");
$hours_online_raw = $res_online->fetch_assoc()['total'] ?? 0;

$res_part = $conn->query("SELECT COUNT(s.id_score) as total " . $join_sql);
$total_participants_raw = $res_part->fetch_assoc()['total'] ?? 0;

// --- 4. FETCH TRAINING LIST (Initial Load) ---
// Exclude specific training filter for the list
$where_clauses_list = array_diff($where_clauses, ["t.nama_training = '$f_training_name'"]);
$where_sql_list = implode(' AND ', $where_clauses_list);

$sql_list = "
    SELECT t.nama_training, ts.code_sub, ts.date_start, ts.date_end, ts.method
    FROM score s
    JOIN training_session ts ON s.id_session = ts.id_session
    JOIN training t ON ts.id_training = t.id_training
    LEFT JOIN bu b ON s.id_bu = b.id_bu
    LEFT JOIN func f ON s.id_func = f.id_func
    WHERE $where_sql_list
    GROUP BY ts.id_session
    ORDER BY ts.date_start DESC
    LIMIT 50
";
$list_trainings = $conn->query($sql_list);

// --- 5. FETCH FILTER OPTIONS ---
$opt_bu = $conn->query("SELECT DISTINCT nama_bu FROM bu WHERE nama_bu IS NOT NULL ORDER BY nama_bu");

// Fn1 Logic
$fn1_query = "SELECT DISTINCT f.func_n1 FROM func f";
if ($f_bu !== 'All') {
    $fn1_query .= " JOIN score s ON f.id_func = s.id_func JOIN bu b ON s.id_bu = b.id_bu WHERE b.nama_bu = '" . $conn->real_escape_string($f_bu) . "' AND f.func_n1 IS NOT NULL";
} else {
    $fn1_query .= " WHERE f.func_n1 IS NOT NULL";
}
$fn1_query .= " ORDER BY f.func_n1";
$opt_func1 = $conn->query($fn1_query);

// Fn2 Logic
$fn2_query = "SELECT DISTINCT f.func_n2 FROM func f";
if ($f_bu !== 'All') {
     $fn2_query .= " JOIN score s ON f.id_func = s.id_func JOIN bu b ON s.id_bu = b.id_bu WHERE b.nama_bu = '" . $conn->real_escape_string($f_bu) . "'";
     if ($f_func1 !== 'All') {
         $fn2_query .= " AND f.func_n1 = '" . $conn->real_escape_string($f_func1) . "'";
     }
     $fn2_query .= " AND f.func_n2 IS NOT NULL";
} elseif ($f_func1 !== 'All') {
    $fn2_query .= " WHERE f.func_n1 = '" . $conn->real_escape_string($f_func1) . "' AND f.func_n2 IS NOT NULL";
} else {
    $fn2_query .= " WHERE f.func_n2 IS NOT NULL";
}
$fn2_query .= " ORDER BY f.func_n2";
$opt_func2 = $conn->query($fn2_query);

$opt_type = $conn->query("SELECT DISTINCT jenis FROM training WHERE jenis IS NOT NULL ORDER BY jenis");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GGF - Dashboard</title>
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
            margin: -20px -40px 30px -40px; /* Negative margin to span full width */
            padding-left: 70px; padding-right: 70px;
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

        /* --- SUMMARY CARDS --- */
        .summary-grid { 
            display: grid; 
            grid-template-columns: repeat(3, 1fr); 
            gap: 20px; 
            margin-bottom: 25px; 
            width: 100%; 
            flex-shrink: 0; 
        }
        .summary-card {
            background: white; border-radius: 15px; padding: 20px 25px; display: flex; align-items: center; gap: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03); border-left: 5px solid #197B40; cursor: pointer; 
            transition: transform 0.2s; min-width: 0; position: relative;
        }
        .summary-card:hover { transform: translateY(-3px); }
        .summary-card.has-filter { border-left: 5px solid #FF9A02; background-color: #fffcf5; }
        .s-close {
            position: absolute; top: 5px; right: 5px; width: 20px; height: 20px; border-radius: 50%;
            background: #ffe0b2; color: #e65100; display: none; align-items: center; justify-content: center;
            font-size: 12px; transition: 0.2s; z-index: 5;
        }
        .s-close:hover { background: #ffcc80; transform: scale(1.1); }
        .summary-card.has-filter .s-close { display: flex; }
        .s-icon { color: #888; display: flex; align-items: center; transition: color 0.3s; flex-shrink: 0; }
        .summary-card.has-filter .s-icon { color: #FF9A02; }
        .s-content { display: flex; flex-direction: column; white-space: nowrap; overflow: hidden; width: 100%; }
        .s-label { font-size: 11px; color: #999; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; margin-bottom: 4px; }
        .s-value { font-size: 15px; font-weight: 700; color: #333; overflow: hidden; text-overflow: ellipsis; }

        /* --- HERO CARD --- */
        .hero-card {
            background-color: #0e5e45; background-image: linear-gradient(135deg, #117054 0%, #0a4d38 100%);
            border-radius: 20px; padding: 30px 50px; color: white; display: flex; align-items: center; justify-content: space-between;
            box-shadow: 0 10px 30px rgba(17, 112, 84, 0.2); margin-bottom: 25px; width: 100%; 
            position: relative; overflow: hidden; min-height: 180px; flex-shrink: 0;
        }
        .hero-card::after { 
            content: ''; position: absolute; right: -50px; top: -50px; width: 350px; height: 350px; 
            background: rgba(255,255,255,0.05); border-radius: 50%; pointer-events: none; 
        }
        .hero-left-wrapper { display: flex; align-items: center; margin-right: auto; position: static; }
        .hero-illustration-img { position: absolute; height: 150px; width: auto; left: 0px; z-index: 1; pointer-events: none; }
        .hero-main { display: flex; flex-direction: column; gap: 5px; white-space: nowrap; position: relative; z-index: 2; margin-left: 120px; }
        .hero-number { font-size: 56px; font-weight: 700; line-height: 1; letter-spacing: -1px; }
        .hero-label { font-size: 13px; opacity: 0.85; font-weight: 500; letter-spacing: 1px; text-transform: uppercase; }
        .hero-breakdown { 
            display: flex; flex-direction: column; gap: 15px; border-left: 1px solid rgba(255,255,255,0.15);
            padding-left: 30px; position: relative; z-index: 2;
        }
        .breakdown-item { display: flex; align-items: center; gap: 15px; }
        .icon-box { width: 40px; height: 40px; background: rgba(255,255,255,0.15); border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .b-text h4 { font-size: 12px; font-weight: 400; opacity: 0.85; margin-bottom: 2px; white-space: nowrap; }
        .b-text p { font-size: 18px; font-weight: 700; white-space: nowrap; }

        /* --- TRAINING SECTION --- */
        .training-section {
            background: white; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            display: flex; flex-direction: column; flex-grow: 1; min-height: 400px; overflow: hidden;
        }
        .section-header {
            background-color: #197B40; padding: 15px 25px; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; gap: 15px;
        }
        .section-title { font-size: 16px; font-weight: 600; color: white; white-space: nowrap; }
        .header-actions { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; justify-content: flex-end; }
        
        .search-box {
            background: white; border-radius: 50px; height: 35px; width: 250px; display: flex; align-items: center; padding: 0 15px; transition: width 0.3s;
        }
        .search-box img { width: 16px; height: 16px; margin-right: 8px; flex-shrink: 0; }
        .search-box input { border: none; outline: none; background: transparent; width: 100%; font-size: 13px; color: #333; height: 100%; }
        
        .btn-filter {
            height: 35px; padding: 0 15px; border: none; border-radius: 50px; background: white; color: #197B40; 
            font-weight: 600; font-size: 13px; cursor: pointer; display: flex; align-items: center; gap: 6px; transition: 0.2s; white-space: nowrap;
        }
        .btn-filter:hover { background-color: #f0fdf4; }
        
        /* ACTIVE FILTER STATE */
        .btn-filter.active-filter {
            background-color: #fffcf5;
            color: #e65100;
            border: 1px solid #FF9A02;
        }
        .btn-filter.active-filter:hover {
            background-color: #fff5e0;
        }

        /* --- TABLE STYLES --- */
        .table-responsive { flex-grow: 1; overflow: auto; padding: 0; width: 100%; }
        table { width: 100%; border-collapse: collapse; min-width: 600px; /* Ensure table doesn't get too squashed */ }
        
        th { 
            text-align: left; padding: 15px 20px; font-size: 12px; color: #555; 
            font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
            background: white; border-bottom: 2px solid #eee; position: sticky; top: 0; z-index: 10; 
        }
        td { padding: 15px 20px; font-size: 13px; color: #333; border-bottom: 1px solid #f9f9f9; vertical-align: middle; }
        tr:hover { background-color: #fafbfc; }
        tr.selected { background-color: #e8f5e9; }

        .training-cell { display: flex; align-items: center; gap: 15px; }
        .training-cell .icon-box { 
            background: #e8f5e9; color: #197B40; width: 40px; height: 40px; 
            border-radius: 8px; display: flex; align-items: center; justify-content: center; 
            flex-shrink: 0; 
        }
        .training-name-text { font-weight: 700; line-height: 1.2; font-size: 14px; }
        
        /* BADGES */
        .badge { padding: 6px 14px; border-radius: 6px; font-size: 11px; font-weight: 600; display: inline-block; letter-spacing: 0.3px; white-space: nowrap; }
        .method-online { background: #E0F2F1; color: #00695C; border: 1px solid rgba(0, 105, 92, 0.1); }
        .method-inclass { background: #FCE4EC; color: #C2185B; border: 1px solid rgba(194, 24, 91, 0.1); }

        /* --- DRAWER STYLES --- */
        .filter-overlay {
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            background: rgba(0,0,0,0.05); z-index: 900; display: none; opacity: 0; transition: opacity 0.3s; pointer-events: none;
        }
        .filter-drawer {
            position: fixed; top: 20px; bottom: 20px; right: -400px; width: 380px; 
            background: white; z-index: 1001;
            box-shadow: -10px 0 30px rgba(0,0,0,0.15); 
            transition: right 0.4s cubic-bezier(0.32, 1, 0.23, 1);
            display: flex; flex-direction: column; 
            border-radius: 35px;
        }
        .drawer-open .filter-overlay { display: block; opacity: 1; pointer-events: auto; }
        .drawer-open .filter-drawer { right: 20px; }
        
        .drawer-header { 
            background-color: #197B40; color: white; padding: 25px; 
            display: flex; justify-content: space-between; align-items: center;
            border-top-left-radius: 35px; border-top-right-radius: 35px; 
        }
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
        /* FIX FOR DATE PICKER */
        .date-input-wrapper input[type="date"]::-webkit-calendar-picker-indicator {
            position: absolute; top: 0; left: 0; right: 0; bottom: 0; 
            width: 100%; height: 100%; color: transparent; background: transparent; cursor: pointer;
        }
        .date-icon { 
            position: absolute; left: 15px; top: 50%; transform: translateY(-50%); 
            color: #197B40; width: 16px; pointer-events: none; z-index: 1; 
        }

        .filter-group select { 
            width: 100%; padding: 10px 15px; border: 1px solid #ddd; border-radius: 8px; outline: none; font-size: 13px; color: #333; appearance: none;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="%23999" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>') no-repeat right 12px center;
        }
        
        .drawer-footer { 
            padding: 20px 25px; border-top: 1px solid #eee; display: flex; gap: 15px; 
            border-bottom-left-radius: 35px; border-bottom-right-radius: 35px;
        }
        .btn-reset { background: #fff; border: 1px solid #ddd; color: #666; padding: 12px; border-radius: 50px; flex: 1; font-weight: 600; cursor: pointer; transition: 0.2s; }
        .btn-reset:hover { background: #f9f9f9; }
        .btn-apply {
            position: relative; background: #197B40; color: white; border: none; padding: 12px 24px; border-radius: 25px;
            flex: 1; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; 
            transition: background 0.2s; overflow: visible;
        }
        .btn-apply svg { position: absolute; top: -2px; left: -2px; width: calc(100% + 4px); height: calc(100% + 4px); fill: none; pointer-events: none; overflow: visible; }
        .btn-apply rect { width: 100%; height: 100%; rx: 25px; ry: 25px; stroke: #FF9A02; stroke-width: 3; stroke-dasharray: 120, 380; stroke-dashoffset: 0; opacity: 0; transition: opacity 0.3s; }
        .btn-apply:hover { background: #145a32; }
        .btn-apply:hover rect { opacity: 1; animation: snakeBorder 2s linear infinite; }
        @keyframes snakeBorder { from { stroke-dashoffset: 500; } to { stroke-dashoffset: 0; } }

        /* ========================================= */
        /* --- RESPONSIVE MEDIA QUERIES --- */
        /* ========================================= */

        /* 1. Tablet & Smaller Laptops (Max 1024px) */
        @media (max-width: 1024px) {
            .main-wrapper { padding: 20px; }
            .navbar { margin: -20px -20px 20px -20px; padding-left: 30px; padding-right: 30px; }
            .summary-grid { grid-template-columns: repeat(2, 1fr); }
            .hero-card { padding: 30px; }
            .hero-main { margin-left: 100px; }
        }

        /* 2. Mobile Landscape & Tablets (Max 768px) */
        @media (max-width: 768px) {
            .main-wrapper { padding: 15px; height: 100vh; }
            
            /* Compact Nav */
            .navbar { 
                margin: -15px -15px 15px -15px; padding: 10px 20px; height: auto;
                flex-wrap: wrap; gap: 10px; border-radius: 0 0 20px 20px;
            }
            .logo-section { order: 1; }
            .nav-right { order: 2; margin-left: auto; }
            .nav-links {
                order: 3; width: 100%; overflow-x: auto; white-space: nowrap; 
                padding-bottom: 5px; gap: 20px;
                /* Hide scrollbar for cleaner look */
                -ms-overflow-style: none; scrollbar-width: none; 
            }
            .nav-links::-webkit-scrollbar { display: none; }
            
            .summary-grid { grid-template-columns: 1fr; gap: 15px; }
            
            /* Responsive Hero Card */
            .hero-card { flex-direction: column; align-items: center; padding: 25px; height: auto; text-align: center; }
            .hero-left-wrapper { margin-right: 0; margin-bottom: 20px; display: flex; flex-direction: column; align-items: center; }
            .hero-illustration-img { position: static; height: 80px; margin-bottom: 10px; }
            .hero-main { margin-left: 0; align-items: center; }
            .hero-breakdown { 
                border-left: none; border-top: 1px solid rgba(255,255,255,0.2); 
                padding-left: 0; padding-top: 20px; width: 100%; 
                flex-direction: row; justify-content: space-between; flex-wrap: wrap;
            }
            .breakdown-item { flex: 1; min-width: 120px; justify-content: center; }
            .hero-card::after { width: 200px; height: 200px; top: -100px; right: -50px; }
        }

        /* 3. Mobile Phones (Max 480px) */
        @media (max-width: 480px) {
            .summary-card { padding: 15px; }
            .section-header { flex-direction: column; align-items: stretch; gap: 15px; }
            .header-actions { flex-direction: column; width: 100%; }
            .search-box { width: 100%; }
            .btn-filter { width: 100%; justify-content: center; }
            
            /* Filter Drawer Full Width on Small Screens */
            .filter-drawer { width: 90%; right: -100%; }
            .drawer-open .filter-drawer { right: 5%; }
            
            .hero-breakdown { flex-direction: column; align-items: flex-start; gap: 15px; }
            .breakdown-item { width: 100%; justify-content: flex-start; }
        }
    </style>
</head>
<body id="body">

    <div class="main-wrapper">
        <nav class="navbar">
            <div class="logo-section"><img src="GGF White.png" alt="GGF Logo"></div>
            <div class="nav-links">
                <a href="dashboard.php" class="active">Dashboard</a>
                <a href="reports.php">Trainings</a>
                <a href="employee_reports.php">Employees</a>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <a href="upload.php">Upload Data</a>
                <?php endif; ?>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <a href="users.php">Users</a>
                <?php endif; ?>
            </div>
            <div class="nav-right">
                <div class="user-profile"><div class="avatar-circle">AD</div></div>
                <a href="logout.php" class="btn-signout">Sign Out</a>
            </div>
        </nav>

        <div class="summary-grid">
            <div class="summary-card" id="card-training">
                <div class="s-close" onclick="clearFilter(event, 'training')"><i data-lucide="x"></i></div>
                <div class="s-icon"><img src="icons/training title.ico" style="width: 36px; height: 36px; transform: scale(1.8); margin-right: 4px;"></div>
                <div class="s-content">
                    <span class="s-label">Training</span>
                    <span class="s-value" id="val-training">All Trainings</span>
                </div>
            </div>

            <div class="summary-card <?php echo ($f_start || $f_end) ? 'has-filter' : ''; ?>" id="card-date" onclick="toggleDrawer()">
                <div class="s-close" onclick="clearFilter(event, 'date')"><i data-lucide="x"></i></div>
                <div class="s-icon"><img src="icons/calendar.ico" style="width: 36px; height: 36px; transform: scale(1.8); margin-right: 4px;"></div>
                <div class="s-content">
                    <span class="s-label">Date Range</span>
                    <span class="s-value" id="val-date"><?php echo ($f_start || $f_end) ? ($f_start.' - '.$f_end) : 'All Time'; ?></span>
                </div>
            </div>

            <div class="summary-card <?php echo ($f_bu !== 'All') ? 'has-filter' : ''; ?>" id="card-bu" onclick="toggleDrawer()">
                <div class="s-close" onclick="clearFilter(event, 'bu')"><i data-lucide="x"></i></div>
                <div class="s-icon"><img src="icons/businessunits.ico" style="width: 36px; height: 36px; transform: scale(1.8); margin-right: 4px;"></div>
                <div class="s-content">
                    <span class="s-label">Business Unit</span>
                    <span class="s-value" id="val-bu"><?php echo htmlspecialchars($f_bu); ?></span>
                </div>
            </div>

            <div class="summary-card <?php echo ($f_func1 !== 'All') ? 'has-filter' : ''; ?>" id="card-func-n1" onclick="toggleDrawer()">
                <div class="s-close" onclick="clearFilter(event, 'func-n1')"><i data-lucide="x"></i></div>
                <div class="s-icon"><img src="icons/function.ico" style="width: 36px; height: 36px; transform: scale(1.8); margin-right: 4px;"></div>
                <div class="s-content">
                    <span class="s-label">Function N-1</span>
                    <span class="s-value" id="val-func-n1"><?php echo htmlspecialchars($f_func1); ?></span>
                </div>
            </div>

            <div class="summary-card <?php echo ($f_func2 !== 'All') ? 'has-filter' : ''; ?>" id="card-func-n2" onclick="toggleDrawer()">
                <div class="s-close" onclick="clearFilter(event, 'func-n2')"><i data-lucide="x"></i></div>
                <div class="s-icon"><img src="icons/function.ico" style="width: 36px; height: 36px; transform: scale(1.8); margin-right: 4px;"></div>
                <div class="s-content">
                    <span class="s-label">Function N-2</span>
                    <span class="s-value" id="val-func-n2"><?php echo htmlspecialchars($f_func2); ?></span>
                </div>
            </div>

            <div class="summary-card <?php echo ($f_type !== 'All') ? 'has-filter' : ''; ?>" id="card-type" onclick="toggleDrawer()">
                <div class="s-close" onclick="clearFilter(event, 'type')"><i data-lucide="x"></i></div>
                <div class="s-icon"><img src="icons/training type.ico" style="width: 36px; height: 36px; transform: scale(1.8); margin-right: 4px;"></div>
                <div class="s-content">
                    <span class="s-label">Training Type</span>
                    <span class="s-value" id="val-type"><?php echo htmlspecialchars($f_type); ?></span>
                </div>
            </div>
        </div>

        <div class="hero-card">
            <div class="hero-left-wrapper">
                <img src="icons/Pina - Study.png" alt="Illustration" class="hero-illustration-img">
                <div class="hero-main">
                    <div class="hero-number" id="counter-total" data-target="<?php echo $total_hours_raw; ?>">0</div>
                    <div class="hero-label">TOTAL LEARNING HOURS</div>
                </div>
            </div>
            <div class="hero-breakdown">
                <div class="breakdown-item">
                    <div class="icon-box"><img src="icons/inclass.ico" style="width: 35px; height: 35px;"></div>
                    <div class="b-text">
                        <h4>Offline Training</h4>
                        <p><span id="counter-offline" data-target="<?php echo $hours_offline_raw; ?>">0</span>h</p>
                    </div>
                </div>
                <div class="breakdown-item">
                    <div class="icon-box"><img src="icons/selfpaced.ico" style="width: 35px; height: 35px;"></div>
                    <div class="b-text">
                        <h4>Online Training</h4>
                        <p><span id="counter-online" data-target="<?php echo $hours_online_raw; ?>">0</span>h</p>
                    </div>
                </div>
                <div class="breakdown-item">
                    <div class="icon-box">
                        <i data-lucide="users" style="color: white; width: 24px; height: 24px;"></i>
                    </div>
                    <div class="b-text">
                        <h4>Participants</h4>
                        <p><span id="counter-participants" data-target="<?php echo $total_participants_raw; ?>">0</span></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="training-section">
            <div class="section-header">
                <div class="section-title">Training Programs</div>
                <div class="header-actions">
                    <div class="search-box">
                        <img src="icons/search.ico" style="width: 26px; height: 26px; transform: scale(1.8); margin-right: 4px;">
                        <input type="text" id="dashboardSearchInput" placeholder="Search training name..." value="<?php echo htmlspecialchars($f_search); ?>">
                    </div>
                    <button class="btn-filter <?php echo $has_active_filters ? 'active-filter' : ''; ?>" onclick="toggleDrawer()">
                        <img src="icons/filter.ico" style="width: 24px; height: 24px; transform: scale(1.8); margin-right: 4px;"> 
                        Filters
                    </button>
                </div>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 50%;">Training Name</th>
                            <th>Date</th>
                            <th>Method</th>
                        </tr>
                    </thead>
                    <tbody id="trainingListContainer">
                        <?php if ($list_trainings->num_rows > 0): ?>
                            <?php while($row = $list_trainings->fetch_assoc()): 
                                $date_display = formatDateRange($row['date_start'], $row['date_end']);
                                $methodClass = (stripos($row['method'], 'Inclass') !== false) ? 'method-inclass' : 'method-online';
                            ?>
                            <tr onclick="selectTraining(this, '<?php echo addslashes($row['nama_training']); ?>')" style="cursor: pointer;">
                                <td>
                                    <div class="training-cell">
                                        <div class="icon-box"><i data-lucide="book-open" style="width:18px;"></i></div>
                                        <div>
                                            <div class="training-name-text"><?php echo htmlspecialchars($row['nama_training']); ?></div>
                                            <div style="font-size:11px; color:#888;"><?php echo htmlspecialchars($row['code_sub']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td style="white-space: nowrap; font-family:'Poppins', sans-serif; font-size:12px; font-weight:500; color: #555;"><?php echo $date_display; ?></td>
                                <td><span class="badge <?php echo $methodClass; ?>"><?php echo htmlspecialchars($row['method']); ?></span></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="3" style="text-align:center; padding: 30px; color:#777;">No training programs found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
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
                        <input type="date" id="input-date-start" value="<?php echo $f_start; ?>">
                    </div>
                    <div class="date-input-wrapper">
                        <i data-lucide="calendar" class="date-icon"></i>
                        <input type="date" id="input-date-end" value="<?php echo $f_end; ?>">
                    </div>
                </div>
            </div>
            <div class="filter-group">
                <label>Business Unit</label>
                <select id="select-bu" onchange="updateFilterDropdowns('bu')">
                    <option value="All">All Units</option>
                    <?php while($r = $opt_bu->fetch_assoc()): ?>
                        <option value="<?php echo $r['nama_bu']; ?>" <?php echo ($f_bu == $r['nama_bu'])?'selected':''; ?>><?php echo $r['nama_bu']; ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Function N-1</label>
                <select id="select-func-n1" onchange="updateFilterDropdowns('fn1')">
                    <option value="All">All Functions</option>
                    <?php while($r = $opt_func1->fetch_assoc()): ?>
                        <option value="<?php echo $r['func_n1']; ?>" <?php echo ($f_func1 == $r['func_n1'])?'selected':''; ?>><?php echo $r['func_n1']; ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Function N-2</label>
                <select id="select-func-n2">
                    <option value="All">All Functions</option>
                    <?php while($r = $opt_func2->fetch_assoc()): ?>
                        <option value="<?php echo $r['func_n2']; ?>" <?php echo ($f_func2 == $r['func_n2'])?'selected':''; ?>><?php echo $r['func_n2']; ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Training Type</label>
                <select id="select-type">
                    <option value="All">All Types</option>
                    <?php while($r = $opt_type->fetch_assoc()): ?>
                        <option value="<?php echo $r['jenis']; ?>" <?php echo ($f_type == $r['jenis'])?'selected':''; ?>><?php echo $r['jenis']; ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>
        <div class="drawer-footer">
            <button class="btn-reset" onclick="resetDrawer()">Reset</button>
            <button class="btn-apply" onclick="applyFilters()">
                <span>Apply Filters</span>
                <svg><rect x="0" y="0"></rect></svg>
            </button>
        </div>
    </div>

<script src="https://unpkg.com/lucide@latest"></script>
<script>
    lucide.createIcons();

    // --- 1. ROLLING NUMBERS ANIMATION ---
    function animateValue(obj, start, end, duration) {
        if (start === end) {
            obj.innerHTML = end.toLocaleString();
            return;
        }
        let startTimestamp = null;
        const step = (timestamp) => {
            if (!startTimestamp) startTimestamp = timestamp;
            const progress = Math.min((timestamp - startTimestamp) / duration, 1);
            const currentVal = Math.floor(progress * (end - start) + start);
            obj.innerHTML = currentVal.toLocaleString();
            if (progress < 1) {
                window.requestAnimationFrame(step);
            } else {
                obj.innerHTML = end.toLocaleString();
            }
        };
        window.requestAnimationFrame(step);
    }

    document.addEventListener("DOMContentLoaded", () => {
        // Init Counters
        const counters = [
            { id: "counter-total", key: "prev_total" },
            { id: "counter-offline", key: "prev_offline" },
            { id: "counter-online", key: "prev_online" },
            { id: "counter-participants", key: "prev_participants" } 
        ];

        counters.forEach(item => {
            const el = document.getElementById(item.id);
            if(el) {
                const target = parseInt(el.getAttribute("data-target"));
                let startVal = parseInt(sessionStorage.getItem(item.key));
                if (isNaN(startVal)) startVal = 0;
                animateValue(el, startVal, target, 1000); 
                sessionStorage.setItem(item.key, target);
            }
        });

        // Restore UI State (Specific Training)
        const urlParams = new URLSearchParams(window.location.search);
        const activeTraining = urlParams.get('training_name');
        
        if (activeTraining) {
            const card = document.getElementById('card-training');
            const valSpan = document.getElementById('val-training');
            valSpan.textContent = activeTraining;
            card.classList.add('has-filter');

            // Highlight selected row
            const items = document.querySelectorAll('.training-name-text');
            items.forEach(div => {
                if(div.textContent === activeTraining) {
                    div.closest('tr').classList.add('selected');
                }
            });
        }
    });

    // --- 2. LIVE SEARCH LOGIC (AJAX) ---
    const searchInput = document.getElementById('dashboardSearchInput');
    const listContainer = document.getElementById('trainingListContainer');

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
        // AJAX Call
        fetch(`?ajax_search=${encodeURIComponent(query)}`)
            .then(response => response.text())
            .then(html => {
                // Replace tbody content
                listContainer.innerHTML = html;
                lucide.createIcons(); // Re-initialize icons for new items
            })
            .catch(error => console.error('Error:', error));
    }, 300); // 300ms delay

    if(searchInput) {
        searchInput.addEventListener('input', performSearch);
    }

    // --- 3. FILTER LOGIC & DYNAMIC DROPDOWNS ---
    function toggleDrawer() { document.getElementById('body').classList.toggle('drawer-open'); }

    // --- NEW: Dynamic Dropdown Logic ---
    function updateFilterDropdowns(trigger) {
        const bu = document.getElementById('select-bu').value;
        const fn1 = document.getElementById('select-func-n1').value;

        // Fetch new options based on current selections
        fetch(`?get_filter_options=1&bu=${encodeURIComponent(bu)}&func_n1=${encodeURIComponent(fn1)}`)
            .then(res => res.json())
            .then(data => {
                // Update Fn1 if BU changed
                if (trigger === 'bu') {
                    const fn1Select = document.getElementById('select-func-n1');
                    const currentVal = fn1Select.value;
                    // Reset
                    fn1Select.innerHTML = '<option value="All">All Functions</option>';
                    
                    data.fn1.forEach(opt => {
                        const option = document.createElement('option');
                        option.value = opt;
                        option.textContent = opt;
                        if (opt === currentVal) option.selected = true;
                        fn1Select.appendChild(option);
                    });
                }

                // Always update Fn2 based on BU & Fn1
                const fn2Select = document.getElementById('select-func-n2');
                const currentFn2Val = fn2Select.value;
                // Reset
                fn2Select.innerHTML = '<option value="All">All Functions</option>';
                
                data.fn2.forEach(opt => {
                    const option = document.createElement('option');
                    option.value = opt;
                    option.textContent = opt;
                    if (opt === currentFn2Val) option.selected = true;
                    fn2Select.appendChild(option);
                });
            })
            .catch(err => console.error('Error fetching filter options:', err));
    }

    function selectTraining(element, trainingName) {
        const url = new URL(window.location.href);
        url.searchParams.set('training_name', trainingName);
        window.location.href = url.toString();
    }

    function applyFilters() {
        const dStart = document.getElementById('input-date-start').value;
        const dEnd = document.getElementById('input-date-end').value;
        const selBu = document.getElementById('select-bu').value;
        const selFuncN1 = document.getElementById('select-func-n1').value;
        const selFuncN2 = document.getElementById('select-func-n2').value;
        const selType = document.getElementById('select-type').value;
        const searchVal = document.getElementById('dashboardSearchInput').value;

        const params = new URLSearchParams(window.location.search);
        
        if(selBu !== 'All') params.set('bu', selBu); else params.delete('bu');
        if(selFuncN1 !== 'All') params.set('func_n1', selFuncN1); else params.delete('func_n1');
        if(selFuncN2 !== 'All') params.set('func_n2', selFuncN2); else params.delete('func_n2');
        if(selType !== 'All') params.set('type', selType); else params.delete('type');
        
        if(dStart) params.set('start', dStart); else params.delete('start');
        if(dEnd) params.set('end', dEnd); else params.delete('end');
        
        if(searchVal) params.set('search', searchVal);

        window.location.search = params.toString();
    }

    function clearFilter(event, type) {
        event.stopPropagation();
        const url = new URL(window.location.href);
        if(type === 'training') url.searchParams.delete('training_name');
        else if(type === 'date') { url.searchParams.delete('start'); url.searchParams.delete('end'); }
        else if(type === 'bu') url.searchParams.delete('bu');
        else if(type === 'func-n1') url.searchParams.delete('func_n1');
        else if(type === 'func-n2') url.searchParams.delete('func_n2');
        else if(type === 'type') url.searchParams.delete('type');
        window.location.href = url.toString();
    }

    function resetDrawer() { window.location.href = 'dashboard.php'; }
</script>
</body>
</html>