<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GGF - Request Account</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="public/icons/icon.png">
    <style>
        body { margin: 0; padding: 0; font-family: 'Poppins', sans-serif; background: #197b40; height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-card { background: rgba(255, 255, 255, 0.95); padding: 40px; border-radius: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); width: 100%; max-width: 450px; text-align: center; }
        .logo-container img { height: 50px; margin-bottom: 20px; }
        h2 { color: #197B40; margin-bottom: 5px; font-size: 22px; }
        p.subtitle { color: #666; font-size: 13px; margin-bottom: 25px; margin-top: 0; }
        .form-group { margin-bottom: 15px; text-align: left; }
        .form-group label { display: block; font-size: 12px; color: #333; margin-bottom: 5px; font-weight: 600; }
        input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; box-sizing: border-box; outline: none; transition: 0.3s; }
        input:focus { border-color: #197B40; }
        .btn-submit { width: 100%; background-color: #197B40; color: white; border: none; padding: 12px; border-radius: 25px; font-size: 14px; font-weight: 600; cursor: pointer; transition: background 0.3s; margin-top: 10px; }
        .btn-submit:hover { background-color: #145a32; }
        .footer-link { margin-top: 20px; font-size: 12px; color: #666; }
        .footer-link a { color: #197B40; text-decoration: none; font-weight: 600; }
        .alert { padding: 10px; border-radius: 5px; margin-bottom: 15px; font-size: 13px; text-align: left; }
        .alert-error { color: #d32f2f; background: #fee2e2; border: 1px solid #fca5a5; }
        .alert-success { color: #065f46; background: #d1fae5; border: 1px solid #6ee7b7; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="logo-container">
            <img src="public/GGF Green.png" alt="GGF Logo">
        </div>
        <h2>Request Account</h2>
        <p class="subtitle">Fill in your details to request access</p>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <?= $success ?> <br>
                <a href="index.php?action=show_login" style="font-weight:bold; color: inherit;">Back to Login</a>
            </div>
        <?php else: ?>
            <form method="POST" action="index.php?action=register">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" placeholder="Create a username" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Create a password" required>
                </div>
                <button type="submit" class="btn-submit">Submit Request</button>
            </form>
        <?php endif; ?>

        <div class="footer-link">
            Already have an account? <a href="index.php?action=show_login">Login here</a>
        </div>
    </div>
</body>
</html>