<?php
// index.php

session_start();
require_once 'db_connect.php';
require_once 'app/helpers.php';

require_once 'app/controllers/AuthController.php';
require_once 'app/controllers/DashboardController.php';
require_once 'app/controllers/UploadController.php';
require_once 'app/controllers/ReportController.php';

$auth = new AuthController($pdo);
$dashboard = new DashboardController($pdo);
$upload = new UploadController($pdo);
$report = new ReportController($pdo);

$action = $_GET['action'] ?? 'show_login';

switch ($action) {
    case 'login': $error = $auth->login(); if ($error) require 'app/views/login.php'; break;
    case 'logout': $auth->logout(); break;
    case 'show_login': if (isset($_SESSION['user_id'])) { header("Location: index.php?action=dashboard"); exit(); } require 'app/views/login.php'; break;

    case 'dashboard': $dashboard->index(); break;
    case 'filter_options': $dashboard->getFilterOptions(); break;
    case 'dashboard_search': $dashboard->search(); break;

    case 'upload': $upload->index(); break;
    case 'upload_file': $upload->upload(); break;
    case 'download_template': $upload->downloadTemplate(); break;

    case 'reports': 
        $report->index(); 
        break;
    case 'report_search': 
        $report->search(); 
        break;

    case 'details':
        $report->details();
        break;

    case 'details_search':
        $report->detailsSearch();
        break;

    default: header("Location: index.php?action=show_login"); exit();
}
?>