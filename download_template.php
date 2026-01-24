<?php
// api/download_template.php

// 1. Define the actual file name on the server
// Ensure "template.xlsx" is inside the "api" folder
$server_filename = 'template.xlsx'; 

// 2. Build the absolute path using __DIR__
// __DIR__ guarantees we look in the same folder as this script
$file_path = __DIR__ . '/' . $server_filename;

// 3. Process the Download
if (file_exists($file_path)) {
    // Clear output buffer to prevent file corruption
    if (ob_get_level()) ob_end_clean();

    // Determine the name the user will see when downloading
    $download_name = 'GGF_Import_Template.xlsx';

    // Headers
    header('Content-Description: File Transfer');
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $download_name . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file_path));

    // Send file
    readfile($file_path);
    exit;
} else {
    // Debugging: This tells you exactly where Vercel is looking
    echo "Error: File not found.<br>";
    echo "System looked for: " . $file_path;
}
?>