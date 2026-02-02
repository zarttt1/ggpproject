<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GGF - Employee Directory</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="public/icons/icon.png">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Poppins', sans-serif; }
        
        body { background-color: #117054; padding: 0; margin: 0; min-height: 100vh; overflow-y: auto; }
        .main-wrapper { background-color: #f3f4f7; padding: 20px 40px; min-height: 100vh; width: 100%; position: relative; display: flex; flex-direction: column; transition: transform 0.3s, border-radius 0.3s; }
        .drawer-open .main-wrapper { transform: scale(0.85) translateX(24px); border-radius: 35px; pointer-events: auto; box-shadow: -20px 0 40px rgba(0,0,0,0.2); overflow: hidden; }

        .navbar {
            background-color: #197B40; height: 70px; border-radius: 0px 0px 25px 25px; 
            display: flex; align-items: center; padding: 0 30px; justify-content: space-between; 
            margin: -20px 0 30px 0; box-shadow: 0 4px 10px rgba(0,0,0,0.1); 
            flex-shrink: 0; position: sticky; top: -20px; z-index: 1000; 
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

        .table-card { background: white; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.03); overflow: hidden; margin-bottom: 40px; margin-top: 20px; display: flex; flex-direction: column; flex-grow: 1;}
        .table-header-strip { background-color: #197b40; color: white; padding: 15px 25px; display: flex; justify-content: space-between; align-items: center; }
        .table-title { font-weight: 600; font-size: 16px; }
        .table-actions { display: flex; gap: 12px; align-items: center; }
        
        .search-box { background-color: white; border-radius: 50px; height: 35px; width: 250px; display: flex; align-items: center; padding: 0 15px; }
        .search-box img { width: 16px; height: 16px; margin-right: 8px; }
        .search-box input { border: none; background: transparent; outline: none; height: 100%; flex: 1; padding-left: 10px; font-size: 13px; color: #333; }
        
        .btn-action-small { height: 35px; padding: 0 15px; border: none; border-radius: 50px; background: white; color: #197B40; font-weight: 600; font-size: 13px; cursor: pointer; display: flex; align-items: center; gap: 6px; text-decoration: none; transition: 0.2s; }
        .btn-action-small:hover { background-color: #f0fdf4; }

        .table-responsive { flex-grow: 1; overflow-y: auto; }
        table { width: 100%; border-collapse: collapse; }
        
        th { 
            text-align: left; padding: 15px 25px; font-size: 12px; color: #888; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;
            border-bottom: 1px solid #eee; position: sticky; top: 0; background: white; z-index: 10; 
        }
        
        td { padding: 15px 25px; font-size: 13px; color: #333; border-bottom: 1px solid #f9f9f9; vertical-align: middle; }
        td:not(:first-child) { white-space: nowrap; }

        .user-cell { display: flex; align-items: center; gap: 12px; }
        .user-avatar { width: 35px; height: 35px; border-radius: 50%; background-color: #197B40; color: white; font-size: 11px; display: flex; align-items: center; justify-content: center; font-weight: bold; }
        .text-subtle { color: #666; font-size: 13px; }

        .participation-badge { padding: 4px 10px; border-radius: 50px; font-size: 11px; font-weight: 700; display: inline-block; min-width: 30px; text-align: center; }
        .badge-high { background-color: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        .badge-med { background-color: #fff3e0; color: #ef6c00; border: 1px solid #ffe0b2; }
        .badge-low { background-color: #f5f5f5; color: #757575; border: 1px solid #e0e0e0; }
        
        .btn-view { position: relative; background: linear-gradient(90deg, #FF9A02 0%, #FED404 100%); color: white; border: none; padding: 10px 14px; border-radius: 25px; font-size: 12px; font-weight: bold; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 5px; overflow: visible; transition: transform 0.2s; white-space: nowrap; }
        .btn-view:active { transform: scale(0.98); }
        .btn-view span { position: relative; z-index: 2; }
        .btn-view svg { position: absolute; top: -2px; left: -2px; width: calc(100% + 4px); height: calc(100% + 4px); fill: none; pointer-events: none; overflow: visible; }
        .btn-view rect { width: 100%; height: 100%; rx: 25px; ry: 25px; stroke: url(#multiColorGradient); stroke-width: 2; stroke-dasharray: 120, 380; stroke-dashoffset: 0; opacity: 0; transition: opacity 0.3s; }
        .btn-view:hover rect { opacity: 1; animation: snakeMove 2s linear infinite; }
        @keyframes snakeMove { from { stroke-dashoffset: 500; } to { stroke-dashoffset: 0; } }

        .pagination-container { padding: 20px 25px; display: flex; justify-content: space-between; align-items: center; font-size: 13px; color: #666; border-top: 1px solid #f9f9f9; }
        .pagination-controls { display: flex; align-items: center; gap: 8px; }
        .page-num { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 8px; cursor: pointer; text-decoration: none; color: #4a4a4a; font-weight: 500; }
        .page-num.active { background-color: #197B40; color: white; }
        .btn-next { display: flex; align-items: center; gap: 5px; color: #4a4a4a; text-decoration: none; cursor: pointer; }

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
        .btn-apply { position: relative; background: #197B40; color: white; border: none; padding: 12px 24px; border-radius: 25px; flex: 1; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: background 0.2s; overflow: visible; }
        .btn-apply svg { position: absolute; top: -2px; left: -2px; width: calc(100% + 4px); height: calc(100% + 4px); fill: none; pointer-events: none; overflow: visible; }
        .btn-apply rect { width: 100%; height: 100%; rx: 25px; ry: 25px; stroke: #FF9A02; stroke-width: 3; stroke-dasharray: 120, 380; stroke-dashoffset: 0; opacity: 0; transition: opacity 0.3s; }
        .btn-apply:hover { background: #145a32; }
        .btn-apply:hover rect { opacity: 1; animation: snakeMove 2s linear infinite; }
        
        @media (max-width: 1024px) {
            .navbar { margin: -20px -20px 20px -20px; padding-left: 30px; padding-right: 30px; }
        }
        @media (max-width: 768px) {
            .navbar { margin: -15px -15px 15px -15px; padding: 10px 20px; height: auto; flex-wrap: wrap; gap: 10px; border-radius: 0 0 20px 20px; }
            .table-header-strip { flex-direction: column; gap: 15px; align-items: stretch; }
            .table-actions { flex-direction: column; width: 100%; }
            .search-box { width: 100%; }
            .btn-action-small { width: 100%; justify-content: center; }
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
            <div class="logo-section"><img src="public/GGF White.png" alt="GGF Logo"></div>
            <div class="nav-links">
                <a href="index.php?action=dashboard">Dashboard</a>
                <a href="index.php?action=reports">Trainings</a>
                <a href="index.php?action=employees" class="active">Employees</a>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <a href="index.php?action=upload">Upload Data</a>
                <?php endif; ?>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <a href="index.php?action=users">Users</a>
                <?php endif; ?>
            </div>
            <div class="nav-right">
                <div class="user-profile"><div class="avatar-circle"><?php echo strtoupper(substr($_SESSION['username'] ?? 'User', 0, 2)); ?></div></div>
                <a href="index.php?action=logout" class="btn-signout">Sign Out</a>
            </div>
        </nav>

        <div class="table-card">
            <div class="table-header-strip">
                <div class="table-title">Employee List</div>
                <div class="table-actions">
                    <div class="search-box">
                        <img src="public/icons/search.ico" style="width: 26px; height: 26px; transform: scale(1.8); margin-right: 4px;" alt="Search">
                        <input type="text" id="searchInput" placeholder="Search by Name or Index..." value="<?php echo htmlspecialchars($filters['search']); ?>">
                    </div>
                    
                    <button class="btn-action-small" onclick="toggleDrawer()">
                        <img src="public/icons/filter.ico" style="width: 26px; height: 26px; transform: scale(1.8); margin-right: 4px;" alt="Filter">
                        Filter
                    </button>
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
                            <th style="text-align:center;">Participation</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <?php echo $this->renderRows($data['data']); ?>
                    </tbody>
                </table>
            </div>

            <div class="pagination-container" id="paginationContainer">
                <?php echo $this->renderPagination($data); ?>
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
                <select id="filterBU" onchange="updateFilterDropdowns('bu')">
                    <option value="All BUs">All BUs</option>
                    <?php foreach($bu_opts as $r): ?>
                        <option value="<?php echo htmlspecialchars($r); ?>" <?php if($filters['bu'] == $r) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($r); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label>Function N-1</label>
                <select id="filterFn1" onchange="updateFilterDropdowns('fn1')">
                    <option value="All Func N-1">All Func N-1</option>
                    <?php foreach($fn1_opts as $r): ?>
                        <option value="<?php echo htmlspecialchars($r); ?>" <?php if($filters['fn1'] == $r) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($r); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label>Function N-2</label>
                <select id="filterFn2">
                    <option value="All Func N-2">All Func N-2</option>
                    <?php foreach($fn2_opts as $r): ?>
                        <option value="<?php echo htmlspecialchars($r); ?>" <?php if($filters['fn2'] == $r) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($r); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="drawer-footer">
            <button class="btn-reset" onclick="window.location.href='index.php?action=employees'">Reset</button>
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

        function updateFilterDropdowns(trigger) {
            const bu = document.getElementById('filterBU').value;
            const fn1 = document.getElementById('filterFn1').value;

            fetch(`index.php?action=employee_filter_options&bu=${encodeURIComponent(bu)}&fn1=${encodeURIComponent(fn1)}`)
                .then(res => res.json())
                .then(data => {
                    if (trigger === 'bu') {
                        const fn1Select = document.getElementById('filterFn1');
                        const currentVal = fn1Select.value;
                        fn1Select.innerHTML = '<option value="All Func N-1">All Func N-1</option>';
                        data.fn1.forEach(opt => {
                            const option = document.createElement('option');
                            option.value = opt;
                            option.textContent = opt;
                            if (opt === currentVal) option.selected = true;
                            fn1Select.appendChild(option);
                        });
                    }

                    const fn2Select = document.getElementById('filterFn2');
                    const currentFn2Val = fn2Select.value;
                    fn2Select.innerHTML = '<option value="All Func N-2">All Func N-2</option>';
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

        function applyFilters() {
            const search = document.getElementById('searchInput').value;
            const bu = document.getElementById('filterBU').value;
            const fn1 = document.getElementById('filterFn1').value;
            const fn2 = document.getElementById('filterFn2').value;

            const params = new URLSearchParams();
            params.set('action', 'employees');
            if(search) params.set('search', search);
            if(bu !== 'All BUs') params.set('bu', bu);
            if(fn1 !== 'All Func N-1') params.set('fn1', fn1);
            if(fn2 !== 'All Func N-2') params.set('fn2', fn2);

            window.location.search = params.toString();
        }

        const searchInput = document.getElementById('searchInput');
        const tableBody = document.getElementById('tableBody');
        const paginationContainer = document.getElementById('paginationContainer');

        const currentBU = "<?php echo htmlspecialchars($filters['bu']); ?>";
        const currentFn1 = "<?php echo htmlspecialchars($filters['fn1']); ?>";
        const currentFn2 = "<?php echo htmlspecialchars($filters['fn2']); ?>";

        function changePage(page) {
            const query = searchInput.value;
            fetchData(query, page);
        }

        function fetchData(query, page = 1) {
            let url = `index.php?action=employee_search&ajax_search=${encodeURIComponent(query)}&page=${page}`;
            if(currentBU !== 'All BUs') url += `&bu=${encodeURIComponent(currentBU)}`;
            if(currentFn1 !== 'All Func N-1') url += `&fn1=${encodeURIComponent(currentFn1)}`;
            if(currentFn2 !== 'All Func N-2') url += `&fn2=${encodeURIComponent(currentFn2)}`;

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