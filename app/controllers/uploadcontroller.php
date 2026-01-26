<?php
// app/controllers/UploadController.php

require_once __DIR__ . '/../models/Importer.php';

class UploadController {
    private $importer;

    public function __construct($pdo) {
        $this->importer = new Importer($pdo);
    }

    private function checkAdmin() {
        if (!isset($_SESSION['user_id'])) {
            header("Location: index.php?action=show_login");
            exit();
        }
        if (($_SESSION['role'] ?? '') !== 'admin') {
            die("Access Denied: You must be an admin to view this page.");
        }
    }

    public function index() {
        $this->checkAdmin();
        $history = $this->importer->getHistory();
        require 'app/views/upload.php';
    }

    public function upload() {
        $this->checkAdmin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['fileToUpload'])) {
            $file = $_FILES['fileToUpload'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $_SESSION['upload_message'] = "File upload error code: " . $file['error'];
                header("Location: index.php?action=upload&status=error");
                exit();
            }

            $uploadDir = __DIR__ . '/../../uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $target = $uploadDir . uniqid() . '_' . basename($file['name']);

            if (!in_array($ext, ['csv', 'xlsx'])) {
                $_SESSION['upload_message'] = "Invalid file type. Only .csv and .xlsx allowed.";
                header("Location: index.php?action=upload&status=error");
                exit();
            }

            if (move_uploaded_file($file['tmp_name'], $target)) {
                $result = $this->importer->processFile($target, $ext, $_SESSION['username'] ?? 'Admin');
                if ($result['status'] === 'success') {
                    $_SESSION['upload_message'] = $result['message'];
                    $_SESSION['upload_stats'] = $result['stats'];
                    header("Location: index.php?action=upload&status=success");
                } else {
                    $_SESSION['upload_message'] = "Error: " . $result['message'];
                    header("Location: index.php?action=upload&status=error");
                }
            } else {
                $_SESSION['upload_message'] = "Failed to move uploaded file.";
                header("Location: index.php?action=upload&status=error");
            }
            exit();
        }
    }

    public function downloadTemplate() {
        $this->checkAdmin();
        
        $file = __DIR__ . '/../../public/template.xlsx';

        if (file_exists($file)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="template.xlsx"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($file));
            readfile($file);
            exit;
        } else {
            $_SESSION['upload_message'] = "Template file (public/template.xlsx) not found on server.";
            header("Location: index.php?action=upload&status=error");
            exit();
        }
    }
}
?>