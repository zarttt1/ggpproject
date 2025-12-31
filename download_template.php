<?php
// Tentukan nama file yang ingin diunduh
$template_file = "uploads/template.xlsx"; // Pastikan file ini ada di folder uploads

// Cek apakah file ada di server
if (file_exists($template_file)) {
    // Set header untuk memberitahukan browser bahwa ini adalah file yang bisa diunduh
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="template.xlsx"'); // Ganti dengan nama file yang sesuai
    header('Content-Length: ' . filesize($template_file));
    
    // Baca dan kirim file ke browser
    readfile($template_file);
    exit;
} else {
    echo "File tidak ditemukan.";
}
?>
