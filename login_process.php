<?php
// login_process.php
session_start();

require 'db_connect.php'; // Optional: if you want to verify against DB later

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // CHANGED: Get 'username' instead of 'email'
    $username = $_POST['username'];
    $password = $_POST['password'];

    // CHANGED: Check against username "admin" instead of email
    if ($username === "admin" && $password === "admin123") {
        $_SESSION['user_id'] = 1;
        $_SESSION['user_name'] = "Admin User";
        
        // Redirect to dashboard
        header("Location: dashboard.php"); 
        exit();
    } else {
        header("Location: login.html?error=Invalid Credentials");
        exit();
    }
} else {
    header("Location: login.html");
    exit();
}
?>