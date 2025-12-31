<?php
// Database connection details
$servername = "localhost"; // or your database server address
$username = "root"; // your database username
$password = "Admin123"; // your database password
$dbname = "trainingc"; // your database name

// Create a connection to the database
$conn = new mysqli($servername, $username, $password, $dbname);

// Check if the connection was successful
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set the allowed file extensions and the upload directory
$allowed_extensions = ['xlsx', 'csv'];
$upload_dir = 'uploads/'; // Make sure this directory is writable

// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Check if a file is uploaded
    if (isset($_FILES['fileToUpload'])) {

        $file_name = $_FILES['fileToUpload']['name'];
        $file_tmp = $_FILES['fileToUpload']['tmp_name'];
        $file_size = $_FILES['fileToUpload']['size'];
        $file_error = $_FILES['fileToUpload']['error'];

        // Extract file extension
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        // Validate file size (max 10MB)
        if ($file_size > 10 * 1024 * 1024) {
            echo "File size exceeds the maximum limit of 10MB.";
            exit;
        }

        // Check if the file extension is allowed
        if (!in_array($file_ext, $allowed_extensions)) {
            echo "Invalid file type. Only .xlsx and .csv files are allowed.";
            exit;
        }

        // Check for any upload errors
        if ($file_error !== 0) {
            echo "Error uploading file.";
            exit;
        }

        // Generate a unique name for the file
        $new_file_name = uniqid('', true) . '.' . $file_ext;

        // Move the uploaded file to the designated directory
        if (move_uploaded_file($file_tmp, $upload_dir . $new_file_name)) {
            // File uploaded successfully
            echo "File uploaded successfully.";

            // Example: Insert file upload details into the database
            $uploaded_by = 'Admin User'; // Change this based on the logged-in user
            $status = 'Success'; // You can set status dynamically based on your logic (e.g., success or failed)
            $rows_processed = 245; // You can set this dynamically based on the file's content

            $stmt = $conn->prepare("INSERT INTO file_uploads (filename, uploaded_by, status, rows_processed) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sssi", $new_file_name, $uploaded_by, $status, $rows_processed);

            // Execute the query and check for success
            if ($stmt->execute()) {
                echo "File details saved to the database.";
            } else {
                echo "Error saving file details to the database.";
            }

            $stmt->close();
        } else {
            echo "Error moving the file.";
        }
    } else {
        echo "No file uploaded.";
    }
} else {
    echo "Invalid request method.";
}

// Close the database connection
$conn->close();
?>
