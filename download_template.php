<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Tangkap data format file yang dikirim dari form
    $download_format = isset($_POST['download_format']) ? $_POST['download_format'] : 'xlsx';

    // Tentukan nama file yang ingin diunduh berdasarkan format yang dipilih
    $template_file = "uploads/template." . $download_format; // Menambahkan format yang dipilih ke nama file

    // Cek apakah file ada di server
    if (file_exists($template_file)) {
        // Tentukan header agar browser tahu ini adalah file untuk diunduh
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="template.' . $download_format . '"'); // Menambahkan format file ke nama file unduhan
        header('Content-Length: ' . filesize($template_file));

        // Baca dan kirim file ke browser
        readfile($template_file);
        exit;
    } else {
        echo "File tidak ditemukan.";
    }
} else {
    echo "Tidak ada data yang dikirim.";
}
?>
