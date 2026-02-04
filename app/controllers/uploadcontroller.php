<?php
// app/controllers/UploadController.php

require_once __DIR__ . '/../models/Importer.php';

class UploadController {
    private $importer;
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
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
        
        // --- 1. RETRIEVE LOGS (The part that was likely failing) ---
        $uploadMessage = $_SESSION['upload_message'] ?? '';
        $uploadStats = $_SESSION['upload_stats'] ?? null;
        $logs = $_SESSION['upload_logs'] ?? []; // Gets row logs
        
        // Clear them so they don't persist on refresh
        unset($_SESSION['upload_message'], $_SESSION['upload_stats'], $_SESSION['upload_logs']);

        $history = $this->importer->getHistory();
        
        // Load view (variables $logs, $uploadStats will be available inside)
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

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!in_array($ext, ['xlsx', 'csv'])) {
                $_SESSION['upload_message'] = "Invalid file type. Please upload Excel or CSV.";
                header("Location: index.php?action=upload&status=error");
                exit();
            }

            $target = __DIR__ . '/../../uploads/' . uniqid() . '_' . basename($file['name']);
            
            if (move_uploaded_file($file['tmp_name'], $target)) {
                $originalName = basename($file['name']);
                
                // Process
                $result = $this->importer->processFile($target, $ext, $_SESSION['username'] ?? 'Admin', $originalName);
                
                if ($result['status'] === 'success') {
                    $_SESSION['upload_message'] = $result['message'];
                    $_SESSION['upload_stats'] = $result['stats'];
                    
                    // --- 2. SAVE LOGS (The other missing part) ---
                    $_SESSION['upload_logs'] = $result['logs']; 
                    
                    header("Location: index.php?action=upload&status=success");
                } else {
                    $_SESSION['upload_message'] = "Error: " . $result['message'];
                    header("Location: index.php?action=upload&status=error");
                }
                
                if (file_exists($target)) unlink($target);
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
            $_SESSION['upload_message'] = "Template file not found.";
            header("Location: index.php?action=upload&status=error");
            exit();
        }
    }
}
?>