<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GGF - Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        /* 1. Global Reset & Body */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: #117054; /* Dark Brand Green */
            padding: 0;
            margin: 0;
            overflow: hidden;
            height: 100vh;
        }

        /* 2. Main Wrapper (Matches Reports Page) */
        .main-wrapper {
            background-color: #f3f4f7;
            padding: 20px;
            height: 100vh;
            overflow-y: auto;
            width: 100%;
            position: relative;
        }

        /* 3. Navigation Bar */
        .navbar {
            background-color: #197B40;
            height: 70px;
            border-radius: 50px;
            display: flex;
            align-items: center;
            padding: 0 30px;
            justify-content: space-between;
            margin-bottom: 30px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        .logo-section img { height: 40px; }
        .nav-links { display: flex; gap: 30px; align-items: center; }
        .nav-links a { color: white; text-decoration: none; font-size: 14px; font-weight: 600; opacity: 0.8; transition: 0.3s; }
        .nav-links a:hover { opacity: 1; }
        .nav-links a.active { background: white; color: #197B40; padding: 8px 20px; border-radius: 20px; opacity: 1; }
        .user-profile { display: flex; align-items: center; gap: 12px; color: white; }
        .avatar-circle { width: 35px; height: 35px; background-color: #FF9A02; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 12px; }

        /* 4. Dashboard Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
            gap: 15px;
            position: relative;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .icon-orange { background-color: #FFF4E5; color: #FF9A02; }
        .icon-green { background-color: #E8F5E9; color: #197B40; }

        .stat-label { color: #666; font-size: 14px; font-weight: 500; }
        .stat-value { color: #333; font-size: 36px; font-weight: 700; line-height: 1; }

        /* 5. Search & Filters Section */
        .filter-section {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        .filter-header {
            background-color: #197B40;
            padding: 20px 30px;
            color: white;
            font-size: 18px;
            font-weight: 700;
        }

        .filter-body {
            padding: 30px;
        }

        .filter-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr; /* 3 Columns */
            gap: 20px;
            margin-bottom: 20px;
        }

        .input-group label {
            display: block;
            color: #197B40;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .input-group select, 
        .input-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #197B40;
            border-radius: 12px;
            outline: none;
            font-size: 13px;
            color: #333;
            background: white;
        }
        
        .input-group input::placeholder { color: #aaa; }

        /* Button Actions */
        .filter-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 10px;
        }

        .btn-reset {
            padding: 10px 30px;
            border: 1px solid #197B40;
            background: white;
            color: #197B40;
            font-weight: 600;
            border-radius: 50px;
            cursor: pointer;
            transition: 0.2s;
        }
        .btn-reset:hover { background: #f0f0f0; }

        /* Animated Apply Button (Orange) */
        .btn-apply {
            position: relative;
            background: linear-gradient(90deg, #FF9A02 0%, #FED404 100%);
            color: white;
            border: none;
            padding: 10px 30px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            overflow: visible !important;
            z-index: 1;
        }

        .btn-apply span { position: relative; z-index: 5; }

        .btn-apply svg {
            position: absolute;
            top: -2px; left: -2px;
            width: calc(100% + 4px);
            height: calc(100% + 4px);
            overflow: visible !important;
            pointer-events: none;
            z-index: -1;
        }

        .btn-apply rect {
            width: 100%; height: 100%;
            rx: 50px; ry: 50px;
            fill: none;
            stroke: #197B40; /* Dark Green Stroke */
            stroke-width: 3;
            stroke-dasharray: 40, 150;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .btn-apply:hover rect {
            opacity: 1;
            animation: snake 1.5s linear infinite;
        }

        @keyframes snake { from { stroke-dashoffset: 250; } to { stroke-dashoffset: 0; } }

    </style>
</head>
<body>

    <div class="main-wrapper">
        <nav class="navbar">
            <div class="logo-section"><img src="GGF_logo024_putih.png" alt="GGF Logo"></div>
            <div class="nav-links">
                <a href="#" class="active">Dashboard</a>
                <a href="reports.html">Reports</a>
                <a href="upload.php">Upload Data</a>
            </div>
            <div class="user-profile"><div class="avatar-circle">AD</div></div>
        </nav>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon icon-orange">
                    <i data-lucide="clock" style="width:24px;"></i>
                </div>
                <div>
                    <div class="stat-label">Total Learning Hours</div>
                    <div class="stat-value">12,458</div>
                </div>
                </div>

            <div class="stat-card">
                <div class="stat-icon icon-green">
                    <i data-lucide="users" style="width:24px;"></i>
                </div>
                <div>
                    <div class="stat-label">Total Employees</div>
                    <div class="stat-value">1,247</div>
                </div>
                </div>
        </div>

        <div class="filter-section">
            <div class="filter-header">Search & Filters</div>
            <div class="filter-body">
                <div class="filter-row">
                    <div class="input-group">
                        <label>Business Unit</label>
                        <select>
                            <option>Select BU e.g., Bromelain Enzyme...</option>
                            <option>Production</option>
                            <option>Marketing</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <label>Function N-1</label>
                        <select>
                            <option>Select Division e.g., Production...</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <label>Function N-2</label>
                        <select>
                            <option>Select Dept e.g., Quality Assurance...</option>
                        </select>
                    </div>
                </div>

                <div class="filter-row">
                    <div class="input-group">
                        <label>Training Type</label>
                        <select>
                            <option>Select Training Type</option>
                            <option>Technical</option>
                            <option>Soft Skills</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <label>Method</label>
                        <select>
                            <option>Select Method</option>
                            <option>Online</option>
                            <option>Offline</option>
                        </select>
                    </div>
                    
                    <div class="input-group">
                        <label>Training Name</label>
                        <input type="text" placeholder="Search or type training name...">
                    </div>
                </div>

                <div class="filter-actions">
                    <button class="btn-reset">Reset Filters</button>
                    <button class="btn-apply">
                        <span>Apply Filter</span>
                        <svg><rect x="0" y="0"></rect></svg>
                    </button>
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