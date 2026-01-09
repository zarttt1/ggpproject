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

// --- FETCH EMPLOYEE DETAILS (Added func_n2) ---
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
        SUM(CASE WHEN t.jenis LIKE '%Soft%' THEN 1 ELSE 0 END) as count_soft
    FROM score s
    JOIN training_session ts ON s.id_session = ts.id_session
    JOIN training t ON ts.id_training = t.id_training
    WHERE s.id_karyawan = ?
";
$stmt = $conn->prepare($stats_sql);
$stmt->bind_param("i", $id_karyawan);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Format Stats
$total_sessions = $stats['total_sessions'];

// --- FETCH TRAINING HISTORY LIST ---
$hist_sql = "
    SELECT 
        t.nama_training, t.jenis, 
        ts.date_start, ts.method, ts.place,
        s.pre, s.post
    FROM score s
    JOIN training_session ts ON s.id_session = ts.id_session
    JOIN training t ON ts.id_training = t.id_training
    WHERE s.id_karyawan = ?
    ORDER BY ts.date_start DESC
";
$stmt = $conn->prepare($hist_sql);
$stmt->bind_param("i", $id_karyawan);
$stmt->execute();
$history_result = $stmt->get_result();

$history_data = []; 
while($row = $history_result->fetch_assoc()) {
    $history_data[] = $row;
}
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

        /* HERO PROFILE - NEW LAYOUT */
        .hero-banner {
            background: linear-gradient(135deg, #197B40 0%, #115c32 100%);
            border-radius: 20px; 
            padding: 40px; 
            position: relative; 
            overflow: hidden; 
            display: flex; 
            align-items: center; 
            gap: 40px;
            color: white; 
            box-shadow: 0 4px 15px rgba(25, 123, 64, 0.2);
            height: 100%;
        }

        .hero-banner::before { 
            content: ''; 
            position: absolute; 
            right: -100px; 
            top: -100px; 
            width: 400px; 
            height: 400px; 
            border-radius: 50%; 
            background: rgba(255,255,255,0.03); 
            pointer-events: none; 
        }

        /* Mascot Section */
        .hero-mascot {
            flex-shrink: 0;
            width: 200px;
            height: 200px;
            display: flex;
            margin-left: -20px;
            align-items: center;
            justify-content: center;
            position: relative;
            z-index: 2;
        }

        .hero-mascot img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            filter: drop-shadow(0 10px 30px rgba(0,0,0,0.2));
        }

        /* Center Content Section */
        .hero-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 12px;
            position: relative;
            z-index: 2;
        }

        .hero-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 2px;
            opacity: 0.7;
            font-weight: 600;
        }

        .hero-name {
            font-size: 36px;
            font-weight: 700;
            line-height: 1.1;
            margin-bottom: 8px;
        }

        .hero-details {
            display: flex;
            flex-direction: column;
            gap: 6px;
            font-size: 13px;
            opacity: 0.95;
        }

        .hero-detail-row {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .hero-detail-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .hero-detail-item strong {
            font-weight: 600;
            opacity: 0.8;
        }

        .detail-separator {
            opacity: 0.3;
            font-weight: 300;
        }

        /* Right Stats Section */
        .hero-stats {
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 20px;
            align-items: flex-end;
            position: relative;
            z-index: 2;
            height: 100%;
        }

        /* Stats Styling Updated: No Background, Yellow Text */
        .stat-box {
            text-align: right;
            padding: 0;
            min-width: auto;
            /* Background removed */
        }

        .stat-value {
            font-size: 50px; /* Slightly larger */
            font-weight: 700;
            line-height: 1;
            margin-bottom: 5px;
            color: #FED404; /* Yellow color */
        }

        .stat-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: white;
            font-weight: 600;
            opacity: 0.9;
        }

        /* Back Button */
        .back-btn { 
            position: absolute; 
            top: 20px; 
            right: 20px; 
            background: rgba(255,255,255,0.2); 
            color: white; 
            padding: 8px 16px; 
            border-radius: 20px; 
            font-size: 11px; 
            font-weight: 600; 
            text-decoration: none; 
            display: flex; 
            align-items: center; 
            gap: 6px; 
            transition: all 0.2s; 
            z-index: 10; 
            backdrop-filter: blur(5px);
        }

        .back-btn:hover { 
            background: rgba(255,255,255,0.3);
            transform: translateX(-2px);
        }

        /* GRID LAYOUT (Profile + Donut Chart) */
        .top-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 30px; }
        
        .chart-card { background: white; border-radius: 20px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); height: 100%; display: flex; flex-direction: column; }
        .chart-title { font-size: 15px; font-weight: 700; color: #197B40; margin-bottom: 15px; display: flex; align-items: center; gap: 8px; }

        /* TABLE */
        .table-card { background: white; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.03); overflow: hidden; margin-bottom: 40px; }
        .table-header-strip { background-color: #197b40; color: white; padding: 15px 25px; display: flex; justify-content: space-between; align-items: center; }
        .table-title { font-weight: 600; font-size: 16px; }

        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px 25px; font-size: 12px; color: #888; font-weight: 600; border-bottom: 1px solid #eee; }
        td { padding: 15px 25px; font-size: 13px; color: #333; border-bottom: 1px solid #f9f9f9; vertical-align: middle; }
        
        /* BADGES */
        .badge { padding: 5px 12px; border-radius: 15px; font-size: 11px; font-weight: 600; display: inline-block; }
        .type-tech { background: #e3f2fd; color: #1e88e5; }
        .type-soft { background: #fff3e0; color: #f57c00; }
        .method-online { background: #e0f2f1; color: #00695c; }
        .method-class { background: #f3e5f5; color: #8e24aa; }
        
        /* Responsive adjustments */
        @media (max-width: 1200px) {
            .hero-mascot { width: 140px; height: 140px; }
            .hero-name { font-size: 28px; }
            .stat-value { font-size: 40px; }
        }
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

        <div class="top-grid">
            
            <div class="hero-banner">
                <a href="employee_reports.php" class="back-btn">
                    <i data-lucide="arrow-left" style="width:14px;"></i> Back
                </a>
                
                <div class="hero-mascot">
                    <img src="icons/Pina - Greetings.png" alt="Mascot">
                </div>

                <div class="hero-content">
                    <div class="hero-label">EMPLOYEE PROFILE</div>
                    <h1 class="hero-name"><?php echo htmlspecialchars($employee['nama_karyawan']); ?></h1>
                    
                    <div class="hero-details">
                        <div class="hero-detail-row">
                            <div class="hero-detail-item">
                                <strong>ID:</strong> 
                                <span><?php echo htmlspecialchars($employee['index_karyawan']); ?></span>
                            </div>
                            <span class="detail-separator">|</span>
                            <div class="hero-detail-item">
                                <strong>BU:</strong> 
                                <span><?php echo htmlspecialchars($employee['bu'] ?? '-'); ?></span>
                            </div>
                        </div>
                        <div class="hero-detail-row">
                            <div class="hero-detail-item">
                                <strong>Func N-1:</strong> 
                                <span><?php echo htmlspecialchars($employee['func'] ?? '-'); ?></span>
                            </div>
                            <span class="detail-separator">|</span>
                            <div class="hero-detail-item">
                                <strong>Func N-2:</strong> 
                                <span><?php echo htmlspecialchars($employee['func2'] ?? '-'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="hero-stats">
                    <div class="stat-box">
                        <div class="stat-value"><?php echo $total_sessions; ?></div>
                        <div class="stat-label">Trainings Joined</div>
                    </div>
                </div>
            </div>

            <div class="chart-card">
                <div class="chart-title"><i data-lucide="pie-chart"></i> Training Focus</div>
                <div style="height: 200px; width: 100%; position: relative;">
                    <canvas id="mixChart"></canvas>
                </div>
            </div>

        </div>

        <div class="table-card">
            <div class="table-header-strip">
                <div class="table-title">Training History Log</div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Training Name</th>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Method</th>
                        <th>Pre Score</th>
                        <th>Post Score</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($history_data) > 0): ?>
                        <?php foreach($history_data as $row): 
                            $typeClass = (stripos($row['jenis'], 'Technical') !== false) ? 'type-tech' : 'type-soft';
                            $methodClass = (stripos($row['method'], 'Online') !== false) ? 'method-online' : 'method-class';
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row['nama_training']); ?></strong></td>
                            <td><?php echo date('d M Y', strtotime($row['date_start'])); ?></td>
                            <td><span class="badge <?php echo $typeClass; ?>"><?php echo htmlspecialchars($row['jenis']); ?></span></td>
                            <td><span class="badge <?php echo $methodClass; ?>"><?php echo htmlspecialchars($row['method']); ?></span></td>
                            <td><?php echo $row['pre']; ?></td>
                            <td><strong style="color:#197B40"><?php echo $row['post']; ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align:center; padding: 25px; color:#999;">No training history found for this employee.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
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
                legend: { position: 'right', labels: { font: { family: 'Poppins', size: 11 } } }
            }
        };

        // Training Mix Pie Chart
        const mixCtx = document.getElementById('mixChart').getContext('2d');
        new Chart(mixCtx, {
            type: 'pie',    
            data: {
                labels: ['Technical', 'Soft Skills'],
                datasets: [{
                    data: [<?php echo $stats['count_tech']; ?>, <?php echo $stats['count_soft']; ?>],
                    backgroundColor: ['#1e88e5', '#f57c00'],
                    borderColor: ['#1e88e5', '#f57c00'],
                    borderWidth: 1
                }]
            },
            options: {
                ...commonOptions,
                cutout: '0%',
            }
        });
    </script>
</body>
</html>