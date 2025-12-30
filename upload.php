<?php
// Koneksi ke database dengan username 'root' dan password 'Admin123'
$pdo = new PDO("mysql:host=localhost;dbname=trainingc", "root", "Admin123");

// Set error mode agar dapat menangani kesalahan dengan jelas
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];

    // Validasi ekstensi file
    $allowed_extensions = ['xls', 'xlsx'];
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);

    if (in_array($file_extension, $allowed_extensions)) {
        $target_directory = "uploads/"; // Folder tujuan penyimpanan file
        $target_file = $target_directory . basename($file["name"]);

        // Pastikan file berhasil di-upload
        if (move_uploaded_file($file["tmp_name"], $target_file)) {
            // Simpan informasi upload ke database
            $file_name = $file['name'];
            $user_name = 'Admin User'; // Ambil dari session jika login
            $status = 'Success';
            $rows_processed = 245; // Contoh jumlah data yang diproses (sesuaikan dengan hasil)

            $sql = "INSERT INTO uploads (file_name, uploaded_by, status, rows_processed, upload_time)
                    VALUES (:file_name, :uploaded_by, :status, :rows_processed, NOW())";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':file_name' => $file_name,
                ':uploaded_by' => $user_name,
                ':status' => $status,
                ':rows_processed' => $rows_processed
            ]);

            echo "File successfully uploaded!";
        } else {
            echo "Sorry, there was an error uploading your file.";
        }
    } else {
        echo "Invalid file type. Please upload an Excel file (.xlsx, .xls).";
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Data - GGF</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: "Poppins", sans-serif;
            background-color: #f3f4f7;
            margin: 0;
            padding: 0;
        }
        .navbar {
            background-color: #117054;
            padding: 20px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .navbar .logo {
            font-weight: bold;
            font-size: 24px;
        }
        .navbar .user-info {
            display: flex;
            align-items: center;
        }
        .navbar .user-info .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #fba919;
            margin-right: 10px;
        }
        .navbar .user-info p {
            margin: 0;
        }
        .container {
            padding: 30px;
        }
        .upload-container {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }
        .upload-container h2 {
            margin-bottom: 20px;
            font-size: 1.5rem;
        }
        .upload-container p {
            margin-bottom: 10px;
        }
        .download-template {
            display: inline-block;
            padding: 10px 20px;
            background-color: #117054;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .upload-section {
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #e0f0e9;
            padding: 40px;
            border-radius: 10px;
            border: 2px dashed #117054;
        }
        .upload-section input[type="file"] {
            display: none;
        }
        .upload-section .upload-box {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            cursor: pointer;
            color: #117054;
        }
        .upload-section .upload-box .icon {
            font-size: 3rem;
            margin-bottom: 10px;
        }
        .upload-section .upload-box p {
            margin-bottom: 5px;
        }
        .upload-btn {
            background-color: #117054;
            padding: 10px 30px;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 10px;
        }
        .recent-uploads {
            margin-top: 30px;
        }
        .recent-uploads table {
            width: 100%;
            border-collapse: collapse;
        }
        .recent-uploads th,
        .recent-uploads td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .recent-uploads th {
            background-color: #117054;
            color: white;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="logo">GGF</div>
        <div class="user-info">
            <div class="avatar"></div>
            <p>Admin User</p>
        </div>
    </div>

    <div class="container">
        <div class="upload-container">
            <h2>Before you upload</h2>
            <p>
                To ensure data accuracy, please use the standardized Excel template.
                Do not change the column headers.
            </p>
            <a href="#" class="download-template">Download Excel Template</a>

            <form action="file.php" method="post" enctype="multipart/form-data">
                <div class="upload-section">
                    <label for="file-upload" class="upload-box">
                        <div class="icon">📁</div>
                        <p>Drag & drop your file here or click to browse</p>
                        <input type="file" id="file-upload" name="file" accept=".xlsx,.xls" />
                    </label>
                </div>
                <button type="submit" class="upload-btn" name="submit">Upload Data</button>
            </form>
        </div>

        <div class="recent-uploads">
            <h3>Recent Uploads</h3>
            <table>
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Filename</th>
                        <th>Uploaded By</th>
                        <th>Status</th>
                        <th>Rows Processed</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Data Upload dari PHP -->
                    <?php
                    // Koneksi dan ambil data
                    $pdo = new PDO("mysql:host=localhost;dbname=ggf_database", "username", "password");
                    $sql = "SELECT file_name, uploaded_by, status, rows_processed, upload_time FROM uploads ORDER BY upload_time DESC";
                    $stmt = $pdo->query($sql);
                    $uploads = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($uploads as $upload) {
                        echo "<tr>
                                <td>{$upload['upload_time']}</td>
                                <td>{$upload['file_name']}</td>
                                <td>{$upload['uploaded_by']}</td>
                                <td>{$upload['status']}</td>
                                <td>{$upload['rows_processed']}</td>
                              </tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
