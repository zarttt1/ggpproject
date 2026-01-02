<?php
session_start();
require 'db_connect.php';

// --- 1. GET FILTER VALUES FROM URL ---
// We check if values exist in the URL, otherwise set defaults
$f_bu = $_GET['bu'] ?? 'All';
$f_func1 = $_GET['func_n1'] ?? 'All';
$f_func2 = $_GET['func_n2'] ?? 'All';
$f_type = $_GET['type'] ?? 'All';
$f_search = $_GET['search'] ?? '';
$f_start = $_GET['start'] ?? '';
$f_end = $_GET['end'] ?? '';

// --- 2. BUILD SQL QUERY ---
$where_clauses = ["1=1"];

if ($f_bu !== 'All') $where_clauses[] = "b.nama_bu = '$f_bu'";
if ($f_func1 !== 'All') $where_clauses[] = "f.func_n1 = '$f_func1'";
if ($f_func2 !== 'All') $where_clauses[] = "f.func_n2 = '$f_func2'";
if ($f_type !== 'All') $where_clauses[] = "t.jenis = '$f_type'";
if (!empty($f_search)) $where_clauses[] = "t.nama_training LIKE '%$f_search%'";
if (!empty($f_start) && !empty($f_end)) $where_clauses[] = "ts.tanggal BETWEEN '$f_start' AND '$f_end'";

$where_sql = implode(' AND ', $where_clauses);

// Base Join Query
$join_sql = "
    FROM score s
    JOIN training_session ts ON s.id_session = ts.id_session
    JOIN training t ON ts.id_training = t.id_training
    LEFT JOIN bu b ON s.id_bu = b.id_bu
    LEFT JOIN func f ON s.id_func = f.id_func
    WHERE $where_sql
";

// --- 3. CALCULATE STATS ---
// Total Hours
$res_hours = $conn->query("SELECT SUM(ts.credit_hour) as total " . $join_sql);
$total_hours = number_format($res_hours->fetch_assoc()['total'] ?? 0);

// Breakdown: In-Class vs Self-Paced (Adjust logic based on your specific 'method' values in DB)
$res_inclass = $conn->query("SELECT SUM(ts.credit_hour) as total " . $join_sql . " AND ts.method LIKE '%Class%'");
$hours_inclass = number_format($res_inclass->fetch_assoc()['total'] ?? 0);

$res_online = $conn->query("SELECT SUM(ts.credit_hour) as total " . $join_sql . " AND (ts.method LIKE '%Online%' OR ts.method LIKE '%Self%')");
$hours_online = number_format($res_online->fetch_assoc()['total'] ?? 0);

// --- 4. FETCH TRAINING LIST ---
// We limit to 50 to prevent overcrowding the list
$sql_list = "
    SELECT t.nama_training
    $join_sql
    GROUP BY t.nama_training
    LIMIT 50
";
$list_trainings = $conn->query($sql_list);

// --- 5. FETCH FILTER DROPDOWN OPTIONS ---
$opt_bu = $conn->query("SELECT DISTINCT nama_bu FROM bu WHERE nama_bu IS NOT NULL ORDER BY nama_bu");
$opt_func1 = $conn->query("SELECT DISTINCT func_n1 FROM func WHERE func_n1 IS NOT NULL ORDER BY func_n1");
$opt_func2 = $conn->query("SELECT DISTINCT func_n2 FROM func WHERE func_n2 IS NOT NULL ORDER BY func_n2");
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
        /* [User's CSS Original] */
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

        /* --- NAVBAR STYLES --- */
        .navbar {
            background-color: #197B40; 
            height: 70px; 
            border-radius: 0px 0px 50px 50px; 
            display: flex; 
            align-items: center;
            padding: 0 30px; 
            justify-content: space-between; 
            margin: -20px 0 30px 0; 
            box-shadow: 0 4px 10px rgba(0,0,0,0.1); 
            flex-shrink: 0;
            position: sticky;
            top: -20px; 
            z-index: 1000; 
        }

        .logo-section img { height: 40px; }
        
        /* Links section (Middle) */
        .nav-links { display: flex; gap: 30px; align-items: center; }
        .nav-links a { color: white; text-decoration: none; font-size: 14px; font-weight: 600; opacity: 0.8; transition: 0.3s; }
        .nav-links a:hover { opacity: 1; }
        .nav-links a.active { background: white; color: #197B40; padding: 8px 20px; border-radius: 20px; opacity: 1; }
        
        /* Right Side Wrapper (User + Sign Out) */
        .nav-right {
            display: flex;
            align-items: center;
            gap: 20px; /* Space between user circle and sign out button */
        }

        .user-profile { display: flex; align-items: center; gap: 12px; color: white; }
        .avatar-circle { width: 35px; height: 35px; background-color: #FF9A02; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 12px; }

        /* RED SIGN OUT BUTTON */
        .btn-signout {
            background-color: #d32f2f; /* Standard Red */
            color: white !important; /* Force white text */
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            padding: 8px 20px;
            border-radius: 20px;
            transition: background 0.3s;
            opacity: 1 !important; /* Override default nav link opacity */
        }
        .btn-signout:hover {
            background-color: #b71c1c; /* Darker red on hover */
        }

        /* --- REST OF CSS --- */
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
        
        .summary-card.has-filter {
            border-left: 5px solid #FF9A02;
            background-color: #fffcf5;
        }

        .s-close {
            position: absolute; top: 5px; right: 5px;
            width: 20px; height: 20px; border-radius: 50%;
            background: #ffe0b2; color: #e65100;
            display: none; align-items: center; justify-content: center;
            font-size: 12px; transition: 0.2s; z-index: 5;
        }
        .s-close:hover { background: #ffcc80; transform: scale(1.1); }
        .summary-card.has-filter .s-close { display: flex; }

        .s-icon { color: #888; display: flex; align-items: center; transition: color 0.3s; }
        .summary-card.has-filter .s-icon { color: #FF9A02; }

        .s-content { display: flex; flex-direction: column; white-space: nowrap; overflow: hidden; width: 100%; }
        .s-label { font-size: 11px; color: #999; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; margin-bottom: 4px; }
        .s-value { font-size: 15px; font-weight: 700; color: #333; overflow: hidden; text-overflow: ellipsis; }

        /* HERO CARD STYLES */
        .hero-card {
            background-color: #0e5e45; 
            background-image: linear-gradient(135deg, #117054 0%, #0a4d38 100%);
            border-radius: 20px; 
            padding: 30px 50px; 
            color: white; 
            
            display: flex; 
            align-items: center; 
            justify-content: space-between;
            
            box-shadow: 0 10px 30px rgba(17, 112, 84, 0.2); 
            margin-bottom: 25px;
            width: 100%; 
            position: relative; 
            overflow: hidden; 
            min-height: 180px; 
            flex-shrink: 0;
        }

        .hero-card::after { 
            content: ''; position: absolute; right: -50px; top: -50px; 
            width: 350px; height: 350px; background: rgba(255,255,255,0.05); 
            border-radius: 50%; pointer-events: none; 
        }

        .hero-left-wrapper {
            display: flex;
            align-items: center;
            margin-right: auto;
            position: static; 
        }

        .hero-illustration-img {
            position: absolute; 
            height: 150px; 
            width: auto;
            left: 0px; 
            z-index: 1; 
            opacity: 1;
        }

        .hero-main { 
            display: flex; 
            flex-direction: column; 
            gap: 5px; 
            white-space: nowrap; 
            position: relative;
            z-index: 2;
            margin-left: 120px; 
        }

        .hero-number { font-size: 56px; font-weight: 700; line-height: 1; letter-spacing: -1px; }
        .hero-label { font-size: 13px; opacity: 0.85; font-weight: 500; letter-spacing: 1px; text-transform: uppercase; }

        .hero-breakdown { 
            display: flex; 
            flex-direction: column; 
            gap: 15px; 
            border-left: 1px solid rgba(255,255,255,0.15);
            padding-left: 30px;
            position: relative;
            z-index: 2;
        }

        .breakdown-item { display: flex; align-items: center; gap: 15px; }
        .icon-box { width: 40px; height: 40px; background: rgba(255,255,255,0.15); border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .b-text h4 { font-size: 12px; font-weight: 400; opacity: 0.85; margin-bottom: 2px; white-space: nowrap; }
        .b-text p { font-size: 18px; font-weight: 700; white-space: nowrap; }

        .training-section {
            background: white;
            border-radius: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
            flex-grow: 1;
            min-height: 500px;
            overflow: hidden;
        }
        .section-header {
            background-color: #197B40; padding: 25px 30px;
            display: flex; justify-content: space-between; align-items: center; flex-shrink: 0;
        }
        .section-title { font-size: 20px; font-weight: 700; color: white; }
        .header-actions { display: flex; gap: 15px; align-items: center; }
        .search-box {
            background: white; border-radius: 50px; padding: 12px 20px;
            display: flex; align-items: center; gap: 10px; width: 350px;
        }
        .search-box input { border: none; outline: none; background: transparent; width: 100%; font-size: 14px; color: #333; }
        .btn-filter {
            background: white; border: none; color: #197B40;
            padding: 12px 24px; border-radius: 50px; font-size: 14px; font-weight: 600; cursor: pointer;
            display: flex; align-items: center; gap: 8px; transition: all 0.3s;
        }
        .btn-filter:hover { background: #f0f0f0; }

        .training-list {
            display: flex; flex-direction: column; gap: 0;
            overflow-y: auto; padding: 20px 30px 30px 30px; flex-grow: 1;
        }
        .training-list::-webkit-scrollbar { width: 6px; }
        .training-list::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
        .training-list::-webkit-scrollbar-thumb { background: #ccc; border-radius: 10px; }
        .training-header {
            display: grid; grid-template-columns: 1fr; gap: 20px; padding: 15px 20px;
            border-bottom: 1px solid #eee; font-size: 13px; color: #888; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px;
        }
        .training-item {
            display: grid; grid-template-columns: 1fr; gap: 20px; padding: 20px;
            align-items: center; cursor: pointer; transition: all 0.2s; border-bottom: 1px solid #f9f9f9;
        }
        .training-item:hover { background: #fafbfc; }
        .training-item.selected { background: #e8f5e9; }
        .training-name-col { display: flex; align-items: center; gap: 12px; }
        .training-icon {
            background: #e8f5e9; color: #197B40; width: 40px; height: 40px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .training-info h4 { font-size: 14px; font-weight: 700; color: #333; }

        .filter-overlay {
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            background: rgba(0,0,0,0.05); z-index: 900; display: none; opacity: 0; transition: opacity 0.3s; pointer-events: none;
        }
        .filter-drawer {
            position: fixed; top: 20px; bottom: 20px; right: -400px; width: 380px; background: white; z-index: 1001;
            box-shadow: -10px 0 30px rgba(0,0,0,0.15); transition: right 0.4s cubic-bezier(0.32, 1, 0.23, 1);
            display: flex; flex-direction: column; border-radius: 35px; overflow: hidden;
        }
        .drawer-open .filter-overlay { display: block; opacity: 1; pointer-events: auto; }
        .drawer-open .filter-drawer { right: 20px; }
        .drawer-header { background-color: #197B40; color: white; padding: 25px; display: flex; justify-content: space-between; align-items: center; }
        .drawer-header h4 { font-size: 16px; font-weight: 600; }
        .drawer-content { padding: 25px; overflow-y: auto; flex-grow: 1; }
        .filter-group { margin-bottom: 20px; }
        .filter-group label { display: block; font-size: 13px; font-weight: 600; color: #555; margin-bottom: 8px; }
        .date-row { display: flex; gap: 10px; }
        .date-input-wrapper { flex: 1; position: relative; }
        .date-input-wrapper input { width: 100%; padding: 10px 10px 10px 35px; border: 1px solid #ddd; border-radius: 8px; font-size: 13px; outline: none; color: #333; }
        .date-icon { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #999; width: 14px; }
        .filter-group select { 
            width: 100%; padding: 10px 15px; border: 1px solid #ddd; border-radius: 8px; outline: none; font-size: 13px; color: #333; appearance: none;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="%23999" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>') no-repeat right 12px center;
        }
        .drawer-footer { padding: 20px 25px; border-top: 1px solid #eee; display: flex; gap: 15px; }
        .btn-reset { background: #fff; border: 1px solid #ddd; color: #666; padding: 12px; border-radius: 50px; flex: 1; font-weight: 600; cursor: pointer; transition: 0.2s; }
        .btn-reset:hover { background: #f9f9f9; }

        .btn-apply {
            position: relative; background: #197B40; color: white; border: none; padding: 12px 24px; border-radius: 25px;
            flex: 1; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; 
            transition: background 0.2s; overflow: visible;
        }
        .btn-apply span { position: relative; z-index: 2; }
        .btn-apply svg { position: absolute; top: -2px; left: -2px; width: calc(100% + 4px); height: calc(100% + 4px); fill: none; pointer-events: none; overflow: visible; }
        .btn-apply rect { width: 100%; height: 100%; rx: 25px; ry: 25px; stroke: #FF9A02; stroke-width: 3; stroke-dasharray: 120, 380; stroke-dashoffset: 0; opacity: 0; transition: opacity 0.3s; }
        .btn-apply:hover { background: #145a32; }
        .btn-apply:hover rect { opacity: 1; animation: snakeBorder 2s linear infinite; }
        @keyframes snakeBorder { from { stroke-dashoffset: 500; } to { stroke-dashoffset: 0; } }
    </style>
</head>
<body id="body">

    <div class="main-wrapper">
        <nav class="navbar">
            <div class="logo-section"><img src="GGF_logo024_putih.png" alt="GGF Logo"></div>
            
            <div class="nav-links">
                <a href="#" class="active">Dashboard</a>
                <a href="reports.html">Reports</a>
                <a href="upload.php">Upload Data</a>
            </div>

            <div class="nav-right">
                <div class="user-profile"><div class="avatar-circle">AD</div></div>
                <a href="login.html" class="btn-signout">Sign Out</a>
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
                    <div class="hero-number"><?php echo $total_hours; ?></div>
                    <div class="hero-label">TOTAL LEARNING HOURS</div>
                </div>
            </div>

            <div class="hero-breakdown">
                <div class="breakdown-item">
                    <div class="icon-box"><img src="icons/inclass.ico" style="width: 35px; height: 35px;"></div>
                    <div class="b-text">
                        <h4>In-Class Training</h4>
                        <p><?php echo $hours_inclass; ?>h</p>
                    </div>
                </div>
                <div class="breakdown-item">
                    <div class="icon-box"><img src="icons/selfpaced.ico" style="width: 35px; height: 35 px;"></div>
                    <div class="b-text">
                        <h4>Self-Paced Online</h4>
                        <p><?php echo $hours_online; ?>h</p>
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
                        <input type="text" id="search-input" placeholder="Search training name..." value="<?php echo htmlspecialchars($f_search); ?>" onkeypress="if(event.key === 'Enter') applyFilters()">
                    </div>
                    <button class="btn-filter" onclick="toggleDrawer()">
                        <img src="icons/filter.ico" style="width: 24px; height: 24px; transform: scale(1.8); margin-right: 4px;"> 
                        Filters
                    </button>
                </div>
            </div>

            <div class="training-list">
                <div class="training-header">
                    <div>Training Name</div>
                </div>
                <?php while($row = $list_trainings->fetch_assoc()): ?>
                <div class="training-item" onclick="selectTraining(this, '<?php echo addslashes($row['nama_training']); ?>')">
                    <div class="training-name-col">
                        <div class="training-icon"><i data-lucide="book-open" style="width:18px;"></i></div>
                        <div class="training-info"><h4><?php echo htmlspecialchars($row['nama_training']); ?></h4></div>
                    </div>
                </div>
                <?php endwhile; ?>
                <?php if($list_trainings->num_rows == 0): ?>
                    <div class="training-item"><div style="padding:10px; color:#777;">No training programs found.</div></div>
                <?php endif; ?>
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
                        <input type="text" id="input-date-start" placeholder="YYYY-MM-DD" value="<?php echo $f_start; ?>">
                    </div>
                    <div class="date-input-wrapper">
                        <i data-lucide="calendar" class="date-icon"></i>
                        <input type="text" id="input-date-end" placeholder="YYYY-MM-DD" value="<?php echo $f_end; ?>">
                    </div>
                </div>
            </div>
            <div class="filter-group">
                <label>Business Unit</label>
                <select id="select-bu">
                    <option value="All">All Units</option>
                    <?php while($r = $opt_bu->fetch_assoc()): ?>
                        <option value="<?php echo $r['nama_bu']; ?>" <?php echo ($f_bu == $r['nama_bu'])?'selected':''; ?>>
                            <?php echo $r['nama_bu']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Function N-1</label>
                <select id="select-func-n1">
                    <option value="All">All Functions</option>
                    <?php while($r = $opt_func1->fetch_assoc()): ?>
                        <option value="<?php echo $r['func_n1']; ?>" <?php echo ($f_func1 == $r['func_n1'])?'selected':''; ?>>
                            <?php echo $r['func_n1']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Function N-2</label>
                <select id="select-func-n2">
                    <option value="All">All Functions</option>
                    <?php while($r = $opt_func2->fetch_assoc()): ?>
                        <option value="<?php echo $r['func_n2']; ?>" <?php echo ($f_func2 == $r['func_n2'])?'selected':''; ?>>
                            <?php echo $r['func_n2']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="filter-group">
                <label>Training Type</label>
                <select id="select-type">
                    <option value="All">All Types</option>
                    <?php while($r = $opt_type->fetch_assoc()): ?>
                        <option value="<?php echo $r['jenis']; ?>" <?php echo ($f_type == $r['jenis'])?'selected':''; ?>>
                            <?php echo $r['jenis']; ?>
                        </option>
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
    
    function toggleDrawer() { 
        document.getElementById('body').classList.toggle('drawer-open'); 
    }

    function selectTraining(element, trainingName) {
        document.querySelectorAll('.training-item').forEach(item => { item.classList.remove('selected'); });
        element.classList.add('selected');
        
        const card = document.getElementById('card-training');
        const valSpan = document.getElementById('val-training');
        
        valSpan.textContent = trainingName;
        card.classList.add('has-filter');
    }

    function applyFilters() {
        // Gather values
        const dStart = document.getElementById('input-date-start').value;
        const dEnd = document.getElementById('input-date-end').value;
        const selBu = document.getElementById('select-bu').value;
        const selFuncN1 = document.getElementById('select-func-n1').value;
        const selFuncN2 = document.getElementById('select-func-n2').value;
        const selType = document.getElementById('select-type').value;
        const searchVal = document.getElementById('search-input').value;

        // Construct URL parameters
        const params = new URLSearchParams();
        if(selBu !== 'All') params.set('bu', selBu);
        if(selFuncN1 !== 'All') params.set('func_n1', selFuncN1);
        if(selFuncN2 !== 'All') params.set('func_n2', selFuncN2);
        if(selType !== 'All') params.set('type', selType);
        if(dStart) params.set('start', dStart);
        if(dEnd) params.set('end', dEnd);
        if(searchVal) params.set('search', searchVal);

        // Reload page with new params
        window.location.search = params.toString();
    }

    function clearFilter(event, type) {
        event.stopPropagation();
        
        const url = new URL(window.location.href);
        const defaults = {
            'training': 'All Trainings',
            'date': 'All Time',
            'bu': 'All Units',
            'func-n1': 'All Functions',
            'func-n2': 'All Functions',
            'type': 'All Types'
        };

        // Reset visual state (optimistic) and URL param
        if(document.getElementById('val-' + type)) {
            document.getElementById('val-' + type).textContent = defaults[type];
            document.getElementById('card-' + type).classList.remove('has-filter');
        }

        if(type === 'date') {
            url.searchParams.delete('start');
            url.searchParams.delete('end');
        } else if (type === 'bu') {
            url.searchParams.delete('bu');
        } else if (type === 'func-n1') {
            url.searchParams.delete('func_n1');
        } else if (type === 'func-n2') {
            url.searchParams.delete('func_n2');
        } else if (type === 'type') {
            url.searchParams.delete('type');
        }
        
        // Reload
        window.location.href = url.toString();
    }

    function resetDrawer() {
        // Reset inputs
        document.getElementById('input-date-start').value = '';
        document.getElementById('input-date-end').value = '';
        document.getElementById('select-bu').value = 'All';
        document.getElementById('select-func-n1').value = 'All';
        document.getElementById('select-func-n2').value = 'All';
        document.getElementById('select-type').value = 'All';
        
        // Clear all filters via reload
        window.location.href = 'dashboard.php';
    }
</script>
</body>
</html>