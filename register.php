<?php
require 'db_connect.php';
session_start();

// Check if the user is already logged in
if (isset($_SESSION['user_id'])) {
    // Redirect to the dashboard (or index.php)
    header("Location: dashboard.php");
    exit();
}
$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // 1. Check if username exists
    $check = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
    $check->bind_param("s", $username);
    $check->execute();
    
    if ($check->get_result()->num_rows > 0) {
        $message = "<div style='color: #d32f2f; background: #fee2e2; padding: 10px; border-radius: 5px; margin-bottom: 15px; font-size: 13px;'>Username already exists!</div>";
    } else {
        // 2. Hash Password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // 3. Insert with status = 'pending'
        // Role defaults to 'user'. Admin can upgrade them later if needed.
        $stmt = $conn->prepare("INSERT INTO users (user_id, username, password, role, status) VALUES (UUID(), ?, ?, 'user', 'pending')");
        $stmt->bind_param("ss", $username, $hashed_password);

        if ($stmt->execute()) {
            $message = "<div style='color: #065f46; background: #d1fae5; padding: 10px; border-radius: 5px; margin-bottom: 15px; font-size: 13px;'>Request sent! Waiting for Admin approval. <a href='login.php'>Back to Login</a></div>";
        } else {
            $message = "<div style='color: #d32f2f; background: #fee2e2; padding: 10px; border-radius: 5px; margin-bottom: 15px; font-size: 13px;'>Error submitting request.</div>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GGF - Request Account</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="icons/icon.png">

    <style>
        body { margin: 0; padding: 0; font-family: 'Poppins', sans-serif; background: #197b40 no-repeat center center fixed; background-size: cover; height: 100vh; display: flex; align-items: center; justify-content: center; }
        .overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.4); z-index: 1; }
        .login-card { background: rgba(255, 255, 255, 0.95); padding: 40px; border-radius: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); width: 100%; max-width: 450px; z-index: 2; position: relative; }
        .logo-container { text-align: center; margin-bottom: 20px; }
        .logo-container img { height: 50px; }
        h2 { text-align: center; color: #197B40; margin-bottom: 5px; font-size: 22px; }
        p.subtitle { text-align: center; color: #666; font-size: 13px; margin-bottom: 25px; margin-top: 0; }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 12px; color: #333; margin-bottom: 5px; font-weight: 600; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; box-sizing: border-box; }
        .form-control:focus { border-color: #197B40; outline: none; }
        
        .row { display: flex; gap: 10px; }
        .col { flex: 1; }

        .btn-submit { width: 100%; background-color: #197B40; color: white; border: none; padding: 12px; border-radius: 25px; font-size: 14px; font-weight: 600; cursor: pointer; transition: background 0.3s; margin-top: 10px; }
        .btn-submit:hover { background-color: #145a32; }
        
        .footer-link { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
        .footer-link a { color: #197B40; text-decoration: none; font-weight: 600; }
        .footer-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="overlay"></div>
    <div class="login-card">
        <div class="logo-container">
            <img src="GGF Green.png" alt="GGF Logo">
        </div>
        <h2>Request Account</h2>
        <p class="subtitle">Fill in your details to request access</p>

        <?php echo $message; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" class="form-control" placeholder="Create a username" required>
            </div>

            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" class="form-control" placeholder="Create a password" required>
            </div>

            <button type="submit" class="btn-submit">Submit Request</button>
        </form>

        <div class="footer-link">
            Already have an account? <a href="login.php">Login here</a>
        </div>
    </div>
</body>
</html>