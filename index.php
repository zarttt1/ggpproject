<?php
// index.php

session_start();

require_once 'db_connect.php';

require_once 'app/controllers/AuthController.php';

$action = $_GET['action'] ?? 'show_login';

$auth = new AuthController($pdo);

switch ($action) {
    case 'login':
        $error = $auth->login();
        if ($error) {
            require 'app/views/login.php';
        }
        break;

    case 'logout':
        $auth->logout();
        break;

    case 'show_login':
    default:
        if (isset($_SESSION['user_id'])) {
            header("Location: dashboard.php");
            exit();
        }
        
        require 'app/views/login.php';
        break;
}
?>