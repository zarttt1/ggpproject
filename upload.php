<?php
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
            echo "File uploaded successfully.";
            // Optionally: store the file details in a database or log the upload
        } else {
            echo "Error moving the file.";
        }
    } else {
        echo "No file uploaded.";
    }
} else {
    echo "Invalid request method.";
}
?>
