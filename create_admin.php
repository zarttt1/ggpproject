<?php
require 'db_connect.php'; // Connect to database

// --- CONFIGURATION ---
$new_username = 'admin';
$new_password = 'Admin123'; // <--- Set your desired password here
// ---------------------

// 1. Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 2. Check if admin already exists
$check = $conn->prepare("SELECT username FROM users WHERE username = ?");
$check->bind_param("s", $new_username);
$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {
    echo "<h3>Error: User '$new_username' already exists!</h3>";
} else {
    // 3. Hash the password
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    // 4. Insert the new Admin User
    // We use UUID() for the user_id since your table expects it
    $sql = "INSERT INTO users (user_id, username, password, role, status) VALUES (UUID(), ?, ?, 'admin', 'active')";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("ss", $new_username, $hashed_password);
        
        if ($stmt->execute()) {
            echo "<div style='font-family: Arial; color: green; border: 1px solid green; padding: 20px; max-width: 400px;'>";
            echo "<h3>✅ Admin Account Created!</h3>";
            echo "<p><strong>Username:</strong> $new_username</p>";
            echo "<p><strong>Password:</strong> $new_password</p>";
            echo "<p><a href='login.php'>Click here to Login</a></p>";
            echo "</div>";
            echo "<p style='color: red;'><strong>IMPORTANT:</strong> Please delete this file (create_admin.php) from your server now.</p>";
        } else {
            echo "Error executing query: " . $stmt->error;
        }
    } else {
        echo "Error preparing statement: " . $conn->error;
    }
}

$conn->close();
?>