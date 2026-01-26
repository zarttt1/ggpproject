<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GGF Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="public/icons/icon.png">

    <style>
        /* ... I am keeping your original CSS exactly as it was ... */
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Poppins', sans-serif; }
        body { background-color: #197B40; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .main-container { background-color: #ffffff; border-radius: 20px; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2); display: flex; width: 900px; max-width: 95%; overflow: hidden; min-height: 550px; }
        .mascot-section { flex: 1; display: flex; justify-content: center; align-items: center; background-color: #f8f9fa; padding: 20px; position: relative; }
        .mascot-image { max-width: 100%; height: auto; max-height: 400px; object-fit: contain; z-index: 1; }
        .login-section { flex: 1; padding: 50px; display: flex; flex-direction: column; justify-content: center; border-left: 1px solid #e0e0e0; }
        .header-content { text-align: center; margin-bottom: 30px; display: flex; flex-direction: column; align-items: center; }
        .logo-placeholder { width: 140px; height: auto; margin-bottom: 20px; }
        h2 { color: #197b40; margin-bottom: 10px; font-size: 28px; font-weight: 700; }
        p.subtitle { color: #777; font-size: 14px; margin-bottom: 10px; }
        .form-group { margin-bottom: 20px; text-align: left; }
        label { display: block; font-size: 13px; font-weight: 700; color: #333; margin-bottom: 8px; }
        input[type="text"], input[type="password"], input[type="email"] { width: 100%; padding: 14px 20px; border: 1px solid #ccc; border-radius: 50px; outline: none; font-size: 14px; color: #555; transition: border-color 0.3s; }
        input:focus { border-color: #197B40; }
        .btn-signin { position: relative; width: 100%; height: 55px; background: linear-gradient(90deg, #FF9A02 0%, #FED404 100%); color: white; border: none; border-radius: 27.5px; font-size: 18px; font-weight: bold; cursor: pointer; margin-top: 10px; margin-bottom: 25px; display: flex; justify-content: center; align-items: center; overflow: visible; transition: transform 0.2s; box-shadow: 0 4px 10px rgba(255, 154, 2, 0.3); }
        .btn-signin:active { transform: scale(0.98); }
        .btn-signin span { position: relative; z-index: 2; }
        .btn-signin svg { position: absolute; top: -3px; left: -3px; width: calc(100% + 6px); height: calc(100% + 6px); fill: none; pointer-events: none; overflow: visible; }
        .btn-signin rect { width: 100%; height: 100%; rx: 27.5px; ry: 27.5px; stroke: url(#multiColorGradientHTML); stroke-width: 3; stroke-dasharray: 120, 380; stroke-dashoffset: 0; opacity: 0; transition: opacity 0.3s; }
        .btn-signin:hover rect { opacity: 1; animation: snakeMove 2s linear infinite; }
        @keyframes snakeMove { from { stroke-dashoffset: 500; } to { stroke-dashoffset: 0; } }
        .footer-text { font-size: 12px; color: #777; text-align: center; }
        .footer-text a { color: #1a7f5d; text-decoration: none; font-weight: bold; }
        @media (max-width: 768px) { .main-container { flex-direction: column; width: 90%; } .login-section { border-left: none; padding: 30px; } .mascot-image { max-height: 200px; } }
        
        /* New Alert Style */
        .alert { padding: 10px; border-radius: 5px; margin-bottom: 15px; font-size: 14px; text-align: center; }
        .alert-danger { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>

    <div class="main-container">
        
        <div class="mascot-section">
            <img src="public/icons/Pina - Say Hi.png" alt="Illustration" class="mascot-image">
        </div>

        <div class="login-section">
            <div class="header-content">
                <img src="GGF Green.png" alt="GGF Logo" class="logo-placeholder">
                
                <h2>Welcome Back</h2>
                <p class="subtitle">Sign in to access your training dashboard</p>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form action="index.php?action=login" method="POST">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" placeholder="Enter your username" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                </div>

                <button type="submit" class="btn-signin">
                    <span>Sign In</span>
                    <svg>
                        <defs>
                            <linearGradient id="multiColorGradientHTML" x1="0%" y1="0%" x2="100%" y2="0%">
                                <stop offset="0%" stop-color="#197B40" />   
                                <stop offset="100%" stop-color="#14674b" /> 
                            </linearGradient>
                        </defs>
                        <rect x="0" y="0"></rect>
                    </svg>
                </button>
            </form>

            <div class="footer-text">
                Don't have an account? <a href="index.php?action=register">Request Here</a>
            </div>
        </div>
        
    </div>

</body>
</html>