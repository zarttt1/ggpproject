<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GGF - User Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="public/icons/icon.png">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Poppins', sans-serif; }
        body { background-color: #117054; padding: 0; margin: 0; min-height: 100vh; overflow-y: auto; }
        .main-wrapper { background-color: #f3f4f7; padding: 20px 40px; min-height: 100vh; width: 100%; position: relative; }
        
        .navbar { background-color: #197B40; height: 70px; border-radius: 0px 0px 25px 25px; display: flex; align-items: center; padding: 0 30px; justify-content: space-between; margin: -20px 0 30px 0; box-shadow: 0 4px 10px rgba(0,0,0,0.1); flex-shrink: 0; position: sticky; top: -20px; z-index: 1000; }
        .logo-section img { height: 40px; }
        .nav-links { display: flex; gap: 30px; align-items: center; }
        .nav-links a { color: white; text-decoration: none; font-size: 14px; font-weight: 600; opacity: 0.8; transition: 0.3s; }
        .nav-links a:hover { opacity: 1; }
        .nav-links a.active { background: white; color: #197B40; padding: 8px 20px; border-radius: 20px; opacity: 1; }
        .nav-right { display: flex; align-items: center; gap: 20px; }
        .user-profile { display: flex; align-items: center; gap: 12px; color: white; }
        .avatar-circle { width: 35px; height: 35px; background-color: #FF9A02; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 12px; }
        .btn-signout { background-color: #d32f2f; color: white !important; text-decoration: none; font-size: 13px; font-weight: 600; padding: 8px 20px; border-radius: 20px; transition: background 0.3s; opacity: 1 !important; }
        .btn-signout:hover { background-color: #b71c1c; }

        h2 { margin-bottom: 20px; color: #333; font-size: 20px; border-left: 5px solid #197B40; padding-left: 15px; }
        
        .table-card { background: white; border-radius: 15px; padding: 25px; box-shadow: 0 5px 20px rgba(0,0,0,0.03); margin-bottom: 40px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { text-align: left; padding: 15px; font-size: 13px; color: #888; border-bottom: 2px solid #eee; }
        td { padding: 15px; font-size: 14px; color: #333; border-bottom: 1px solid #f9f9f9; vertical-align: middle; }
        
        .badge { padding: 5px 12px; border-radius: 15px; font-size: 11px; font-weight: 600; }
        .badge-pending { background: #fff3e0; color: #ef6c00; }
        .badge-active { background: #e8f5e9; color: #2e7d32; }
        
        .btn-action { padding: 6px 14px; border-radius: 6px; font-size: 12px; font-weight: 600; text-decoration: none; margin-right: 5px; display: inline-block; }
        .btn-approve { background: #dcfce7; color: #15803d; }
        .btn-reject { background: #fee2e2; color: #991b1b; }
        .btn-approve:hover { background: #bbf7d0; }
        .btn-reject:hover { background: #fecaca; }
    </style>
</head>
<body>
    <div class="main-wrapper">
        <nav class="navbar">
            <div class="logo-section"><img src="public/GGF White.png" alt="GGF Logo"></div>
            <div class="nav-links">
                <a href="index.php?action=dashboard">Dashboard</a>
                <a href="index.php?action=reports">Trainings</a>
                <a href="index.php?action=employees">Employees</a>
                <a href="index.php?action=upload">Upload Data</a>
                <a href="index.php?action=users" class="active">Users</a>
            </div>
            <div class="nav-right">
                <div class="user-profile"><div class="avatar-circle"><?php echo strtoupper(substr($_SESSION['username'] ?? 'User', 0, 2)); ?></div></div>
                <a href="index.php?action=logout" class="btn-signout">Sign Out</a>
            </div>
        </nav>

        <?php if (count($pendingUsers) > 0): ?>
        <div class="table-card" style="border: 2px solid #FF9A02;">
            <h2 style="border-color: #FF9A02;">Pending Requests</h2>
            <table>
                <thead><tr><th>Username</th><th>Requested At</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($pendingUsers as $u): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
                        <td><?= $u['created_at'] ?></td>
                        <td><span class="badge badge-pending">Pending</span></td>
                        <td>
                            <a href="index.php?action=users&do=approve&id=<?= $u['user_id'] ?>" class="btn-action btn-approve">Approve</a>
                            <a href="index.php?action=users&do=reject&id=<?= $u['user_id'] ?>" class="btn-action btn-reject" onclick="return confirm('Reject this user?')">Reject</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <div class="table-card">
            <h2>Active Users</h2>
            <table>
                <thead><tr><th>Username</th><th>Role</th><th>Joined At</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($activeUsers as $u): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
                        <td><?= ucfirst($u['role']) ?></td>
                        <td><?= $u['created_at'] ?></td>
                        <td><span class="badge badge-active">Active</span></td>
                        <td>
                            <?php if($u['role'] !== 'admin'): ?>
                                <a href="index.php?action=users&do=reject&id=<?= $u['user_id'] ?>" class="btn-action btn-reject" onclick="return confirm('Delete this user?')">Remove</a>
                            <?php else: ?>
                                <span style="color:#aaa; font-size:12px;">Protected</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>