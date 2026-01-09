<?php
session_start();
require 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // Prepare SQL to prevent SQL Injection
    $stmt = $conn->prepare("SELECT user_id, username, password, role, status FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Verify Password Hash
        if (password_verify($password, $user['password'])) {
            // Check Account Status
            if ($user['status'] === 'active') {
                // Login Success
                $_SESSION['user_id'] = $user['user_id']; // Stores UUID
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                
                header("Location: dashboard.php");
                exit();
            } else {
                // Account pending or rejected
                $error = "Your account is " . $user['status'] . ". Please contact admin.";
            }
        } else {
            $error = "Invalid password.";
        }
    } else {
        $error = "User not found.";
    }
    
    // Redirect back to login with error (simple implementation)
    // Ideally, pass error via session or GET parameter
    echo "<script>alert('$error'); window.location.href='login.php';</script>";
}
?>