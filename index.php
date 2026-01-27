<?php
// index.php

session_start();
require_once 'db_connect.php';
require_once 'app/helpers.php';

$action = $_GET['action'] ?? 'show_login';

switch ($action) {
    // --- AUTHENTICATION ---
    case 'login': 
    case 'logout': 
    case 'register':
        require_once 'app/controllers/AuthController.php';
        $auth = new AuthController($pdo);
        
        if ($action === 'login') {
            $error = $auth->login(); 
            if ($error) require 'app/views/login.php'; 
        } elseif ($action === 'logout') {
            $auth->logout();
        } elseif ($action === 'register') {
            $auth->register();
        }
        break;
    
    case 'show_login': 
        if (isset($_SESSION['user_id'])) { 
            header("Location: index.php?action=dashboard"); 
            exit(); 
        } 
        require 'app/views/login.php'; 
        break;

    // --- DASHBOARD ---
    case 'dashboard': 
    case 'filter_options': 
    case 'dashboard_search':
        require_once 'app/controllers/DashboardController.php';
        $dashboard = new DashboardController($pdo);

        if ($action === 'dashboard') $dashboard->index();
        elseif ($action === 'filter_options') $dashboard->getFilterOptions();
        elseif ($action === 'dashboard_search') $dashboard->search();
        break;

    // --- UPLOADS ---
    case 'upload': 
    case 'upload_file': 
    case 'download_template':
        require_once 'app/controllers/UploadController.php';
        $upload = new UploadController($pdo);

        if ($action === 'upload') $upload->index();
        elseif ($action === 'upload_file') $upload->upload();
        elseif ($action === 'download_template') $upload->downloadTemplate();
        break;

    // --- REPORTS ---
    case 'reports': 
    case 'report_search': 
    case 'details': 
    case 'details_search':
    case 'export_session':
        require_once 'app/controllers/ReportController.php';
        $report = new ReportController($pdo);

        if ($action === 'reports') $report->index();
        elseif ($action === 'report_search') $report->search();
        elseif ($action === 'details') $report->details();
        elseif ($action === 'details_search') $report->detailsSearch();
        elseif ($action === 'export_session') $report->exportSession();
        break;

    // --- EMPLOYEES ---
    case 'employees': 
    case 'employee_search': 
    case 'employee_filter_options': 
    case 'employee_history': 
    case 'employee_history_search':
    case 'export_employee':
        require_once 'app/controllers/EmployeeController.php';
        $employee = new EmployeeController($pdo);

        if ($action === 'employees') $employee->index();
        elseif ($action === 'employee_search') $employee->search();
        elseif ($action === 'employee_filter_options') $employee->filterOptions();
        elseif ($action === 'employee_history') $employee->history();
        elseif ($action === 'employee_history_search') $employee->historySearch();
        elseif ($action === 'export_employee') $employee->exportHistory();
        break;

    // --- USERS ---
    case 'users':
        require_once 'app/controllers/UserController.php';
        $userCtrl = new UserController($pdo);
        $userCtrl->index(); 
        break;

    // --- DEFAULT ---
    default: 
        header("Location: index.php?action=show_login"); 
        exit();
}
?>