<?php
// app/controllers/UserController.php

require_once __DIR__ . '/../models/User.php';

class UserController {
    private $userModel;
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->userModel = new User($pdo);
    }

    private function checkAdmin() {
        if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
            header("Location: index.php?action=dashboard");
            exit();
        }
    }

    public function index() {
        $this->checkAdmin();

        if (isset($_GET['do']) && isset($_GET['id'])) {
            $id = $_GET['id'];
            if ($_GET['do'] === 'approve') {
                $stmt = $this->pdo->prepare("UPDATE users SET status = 'active' WHERE user_id = ?");
                $stmt->execute([$id]);
            } elseif ($_GET['do'] === 'reject') {
                $stmt = $this->pdo->prepare("DELETE FROM users WHERE user_id = ?");
                $stmt->execute([$id]);
            }
            header("Location: index.php?action=users");
            exit();
        }

        $stmtPending = $this->pdo->query("SELECT * FROM users WHERE status = 'pending' ORDER BY created_at DESC");
        $pendingUsers = $stmtPending->fetchAll();

        $stmtActive = $this->pdo->query("SELECT * FROM users WHERE status = 'active' ORDER BY username ASC");
        $activeUsers = $stmtActive->fetchAll();

        require 'app/views/users.php';
    }
}
?>