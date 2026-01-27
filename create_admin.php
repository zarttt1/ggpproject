<?php
// create_admin.php
require 'db_connect.php';

$stmt = $pdo->prepare("SELECT username FROM users WHERE username = ?");
$stmt->execute([$new_username]);

if ($stmt->rowCount() > 0) {
    echo "User exists.";
} else {
    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
    $sql = "INSERT INTO users (user_id, username, password, role, status) VALUES (UUID(), ?, ?, 'admin', 'active')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$new_username, $hashed]);
    echo "Admin created.";
}
?>