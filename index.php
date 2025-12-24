<?php
// Jika diperlukan, bisa menambahkan logika PHP untuk autentikasi dan kontrol sesi di sini
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="styles.css"> <!-- Menghubungkan file CSS eksternal -->
</head>
<body>

<div class="container">
    <div class="sidebar">
        <div class="logo">
            <img alt="Logo" src="https://static.readdy.ai/image/cb30a5cdf1c776c36393009d4d155f31/343e2aacadea87bc32e4b56fcafc1264.png">
        </div>
        <nav>
            <ul>
                <li><a href="/dashboard"><i class="ri-dashboard-line"></i><span>Dashboard</span></a></li>
                <li><a href="/upload"><i class="ri-upload-cloud-line"></i><span>Upload</span></a></li>
                <li><a href="/report"><i class="ri-file-list-3-line"></i><span>Report</span></a></li>
            </ul>
        </nav>
        <div class="logout">
            <button><i class="ri-logout-box-line"></i><span>Logout</span></button>
        </div>
    </div>

    <div class="content">
        <header class="header">
            <div class="flex items-center justify-between">
                <h1>Dashboard</h1>
                <div class="user-info">
                    <div class="avatar">AD</div>
                    <div>
                        <p class="name">Admin User</p>
                        <p class="role">Administrator</p>
                    </div>
                </div>
            </div>
        </header>

        <div class="cards">
            <div class="card">
                <div class="icon"><i class="ri-time-line"></i></div>
                <span class="text-green-500">+12.5%</span>
                <h3>Total Learning Hours</h3>
                <p>12,458</p>
            </div>
            <div class="card">
                <div class="icon"><i class="ri-group-line"></i></div>
                <span class="text-green-500">+8.2%</span>
                <h3>Total Trainee</h3>
                <p>1,247</p>
            </div>
        </div>

        <div class="filters">
            <h2>Data Filtering</h2>
            <div class="grid grid-cols-3 gap-6">
                <!-- Filtering Form (with select inputs) goes here -->
                <select>
                    <option>Select BU</option>
                    <option>PT Great Giant Pineapple</option>
                    <option>PT Umas Jaya Agrotama</option>
                    <option>PT Bromelain Enzyme</option>
                    <option>PT Setia Karya Transport</option>
                    <option>PT Great Giant Livestock</option>
                    <option>PT Inbio Tani Nusantara</option>
                    <option>PT Umas Jaya Gunung Katun</option>
                    <option>PT Nusantara Segar Abadi</option>
                    <option>PT Sewu Segar Nusantara</option>
                    <option>PT Sewu Segar Primatama</option>
                    <option>Great Giant Foods JPN</option>
                    <option>Great Giant Foods SG and CN</option>
                    <option>Great Giant Foods USA</option>
                    <option>PT Sewu Sentral Primatama</option>
                    <option>PT Sewu Primatama Indonesia</option>
                </select>
                <!-- Add other filter selects here -->
            </div>
            <div class="grid grid-cols-3 gap-6">
                <!-- Filtering Form (with select inputs) goes here -->
                <select>
                    <option>Select BU</option>
                    <option>PT Great Giant Pineapple</option>
                    <option>PT Umas Jaya Agrotama</option>
                    <option>PT Bromelain Enzyme</option>
                    <option>PT Setia Karya Transport</option>
                    <option>PT Great Giant Livestock</option>
                    <option>PT Inbio Tani Nusantara</option>
                    <option>PT Umas Jaya Gunung Katun</option>
                    <option>PT Nusantara Segar Abadi</option>
                    <option>PT Sewu Segar Nusantara</option>
                    <option>PT Sewu Segar Primatama</option>
                    <option>Great Giant Foods JPN</option>
                    <option>Great Giant Foods SG and CN</option>
                    <option>Great Giant Foods USA</option>
                    <option>PT Sewu Sentral Primatama</option>
                    <option>PT Sewu Primatama Indonesia</option>
                </select>
                <!-- Add other filter selects here -->
            </div>
            <div class="btn-group">
                <button class="reset">Reset Filters</button>
                <button class="apply">Apply Filter</button>
            </div>
        </div>
    </div>
</div>

</body>
</html>
