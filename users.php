<?php
session_start();
require 'db_connect.php';

// 1. Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}

// 2. Handle Actions (Approve, Reject, Delete)
$message = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // APPROVE USER
    if (isset($_POST['approve_user'])) {
        $id = $_POST['user_id'];
        $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE user_id = ?");
        $stmt->bind_param("s", $id);
        if($stmt->execute()) {
            $message = "User Approved Successfully";
            $msg_type = "success";
        }
    }
    
    // REJECT USER
    if (isset($_POST['reject_user'])) {
        $id = $_POST['user_id'];
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->bind_param("s", $id);
        if($stmt->execute()) {
            $message = "Request Rejected and Deleted";
            $msg_type = "warning";
        }
    }

    // DELETE EXISTING USER
    if (isset($_POST['delete_user'])) {
        $id = $_POST['user_id'];
        if ($id != $_SESSION['user_id']) {
            $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt->bind_param("s", $id);
            $stmt->execute();
            $message = "User deleted successfully";
            $msg_type = "success";
        } else {
            $message = "You cannot delete your own account!";
            $msg_type = "error";
        }
    }
}

// Navbar Initials
$username = $_SESSION['username'] ?? 'User';
$initials = strtoupper(substr($username, 0, 2));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GGF - User Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="icons/icon.png">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Poppins', sans-serif; }
        body { background-color: #f3f4f7; padding: 0; margin: 0; overflow: hidden; height: 100vh; }
        
        .main-wrapper {
            background-color: #f3f4f7; /* Fixed typo from #1970B40 to #197B40 */
            padding: 20px 40px; 
            height: 100vh; 
            overflow-y: auto;
            width: 100%; 
            position: relative; 
            display: flex; 
            flex-direction: column;
        }

        /* --- NAVBAR --- */
        .navbar {
            background-color: #197B40; height: 70px; border-radius: 0px 0px 50px 50px; 
            display: flex; align-items: center; padding: 0 30px; justify-content: space-between; 
            margin: -20px 0 30px 0; box-shadow: 0 4px 10px rgba(0,0,0,0.1); 
            flex-shrink: 0; position: sticky; top: -20px; z-index: 1000; 
        }
        .logo-section img { height: 40px; }
        .nav-links { display: flex; gap: 30px; align-items: center; }
        .nav-links a { color: white; text-decoration: none; font-size: 14px; font-weight: 600; opacity: 0.8; transition: 0.3s; }
        .nav-links a:hover { opacity: 1; }
        .nav-links a.active { background: white; color: #197B40; padding: 8px 20px; border-radius: 20px; opacity: 1; }
        
        .nav-right { display: flex; align-items: center; gap: 20px; }
        .user-profile { display: flex; align-items: center; gap: 12px; color: white; }
        .avatar-circle { width: 35px; height: 35px; background-color: #FF9A02; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 12px; }
        .btn-signout {
            background-color: #d32f2f; color: white !important; text-decoration: none; font-size: 13px;
            font-weight: 600; padding: 8px 20px; border-radius: 20px; transition: background 0.3s; opacity: 1 !important;
        }
        .btn-signout:hover { background-color: #b71c1c; }

        /* --- CARD STYLES --- */
        .report-card { background: white; border-radius: 20px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.05); margin-bottom: 30px; display: flex; flex-direction: column; flex-shrink: 0; }
        .report-header { background-color: #197B40; padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; color: white; }
        .report-header h3 { font-size: 18px; display: flex; align-items: center; gap: 10px; }
        .header-badge { background: #FF9A02; color: white; padding: 2px 10px; border-radius: 10px; font-size: 12px; margin-left: 10px; }

        /* --- TABLE --- */
        .table-container { padding: 20px 40px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 13px; color: #888; padding: 15px 0; border-bottom: 1px solid #eee; }
        td { padding: 20px 0; font-size: 14px; color: #333; border-bottom: 1px solid #f9f9f9; vertical-align: middle; }
        
        /* Cell Styles to match Reports */
        .user-cell { display: flex; align-items: center; gap: 12px; }
        .icon-box { background: #e8f5e9; color: #197B40; padding: 8px; border-radius: 8px; display: flex; align-items: center; }
        .user-info { display: flex; flex-direction: column; }
        .user-name-text { font-weight: 700; color: #333; }
        .user-sub-text { font-size: 12px; color: #888; }

        .badge { padding: 5px 12px; border-radius: 15px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .badge-admin { background: #fee2e2; color: #991b1b; }
        .badge-user { background: #d1fae5; color: #065f46; }

        /* --- ACTION BUTTONS --- */
        .btn-action {
            padding: 8px 16px; border-radius: 20px; font-size: 12px; font-weight: bold; cursor: pointer; border: none;
            display: inline-flex; align-items: center; gap: 6px; transition: transform 0.2s;
        }
        .btn-action:active { transform: scale(0.95); }
        
        .btn-approve { background-color: #d1fae5; color: #065f46; border: 1px solid transparent; }
        .btn-approve:hover { background-color: #10b981; color: white; }
        
        .btn-reject { background-color: #fee2e2; color: #991b1b; border: 1px solid transparent; margin-left: 5px; }
        .btn-reject:hover { background-color: #ef4444; color: white; }

        .btn-delete {
            background: transparent; color: #999; border: 1px solid #eee; padding: 8px; border-radius: 50%; 
            display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.3s;
        }
        .btn-delete:hover { background-color: #fee2e2; color: #d32f2f; border-color: #fee2e2; }

        /* ALERTS */
        .alert-float {
            position: fixed; bottom: 30px; right: 30px; padding: 15px 25px; border-radius: 12px; 
            box-shadow: 0 5px 20px rgba(0,0,0,0.15); display: flex; align-items: center; gap: 10px; 
            z-index: 2000; animation: slideIn 0.3s ease-out; color: white; font-weight: 600; font-size: 14px;
        }
        .alert-success { background-color: #197B40; }
        .alert-warning { background-color: #f59e0b; }
        .alert-error { background-color: #d32f2f; }
        @keyframes slideIn { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        .empty-state { text-align: center; padding: 40px; color: #999; font-style: italic; }
    </style>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>

    <?php if (!empty($message)): ?>
        <div class="alert-float alert-<?php echo $msg_type; ?>" id="alertBox">
            <i data-lucide="<?php echo $msg_type == 'success' ? 'check-circle' : 'alert-circle'; ?>"></i>
            <span><?php echo $message; ?></span>
        </div>
        <script>
            setTimeout(() => {
                document.getElementById('alertBox').style.opacity = '0';
                setTimeout(() => document.getElementById('alertBox').remove(), 500);
            }, 4000);
        </script>
    <?php endif; ?>

    <div class="main-wrapper">
        <nav class="navbar">
            <div class="logo-section"><img src="GGF White.png" alt="GGF Logo"></div>
            <div class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="reports.php">Reports</a>
                <a href="upload.php">Upload Data</a>
                <a href="users.php" class="active">Users</a>
            </div>
            <div class="nav-right">
                <div class="user-profile"><div class="avatar-circle"><?php echo $initials; ?></div></div>
                <a href="login.html" class="btn-signout">Sign Out</a>
            </div>
        </nav>

        <?php
        // Count Pending
        $pending_count_sql = "SELECT COUNT(*) as count FROM users WHERE status = 'pending'";
        $pending_count = $conn->query($pending_count_sql)->fetch_assoc()['count'];
        
        // Fetch Pending
        $pending_users = $conn->query("SELECT * FROM users WHERE status = 'pending' ORDER BY created_at ASC");
        ?>

        <?php if ($pending_count > 0): ?>
        <div class="report-card">
            <div class="report-header" style="background: #e65100;"> 
                <h3><i data-lucide="user-plus"></i> Pending Requests <span class="header-badge"><?php echo $pending_count; ?></span></h3>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Requested Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $pending_users->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <div class="user-cell">
                                    <div class="icon-box" style="background:#fff3e0; color:#e65100"><i data-lucide="user" style="width:18px;"></i></div>
                                    <span class="user-name-text"><?php echo htmlspecialchars($row['username']); ?></span>
                                </div>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="user_id" value="<?php echo $row['user_id']; ?>">
                                    <button type="submit" name="approve_user" class="btn-action btn-approve">
                                        <i data-lucide="check" style="width:14px"></i> Approve
                                    </button>
                                </form>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Reject this request?');">
                                    <input type="hidden" name="user_id" value="<?php echo $row['user_id']; ?>">
                                    <button type="submit" name="reject_user" class="btn-action btn-reject">
                                        <i data-lucide="x" style="width:14px"></i> Reject
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <div class="report-card">
            <div class="report-header">
                <h3><i data-lucide="users"></i> Active Users</h3>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $active_users = $conn->query("SELECT * FROM users WHERE status = 'active' ORDER BY username ASC");
                        if ($active_users->num_rows > 0):
                            while ($row = $active_users->fetch_assoc()): 
                                $roleClass = ($row['role'] == 'admin') ? 'badge-admin' : 'badge-user';
                        ?>
                        <tr>
                            <td>
                                <div class="user-cell">
                                    <div class="icon-box"><i data-lucide="user" style="width:18px;"></i></div>
                                    <span class="user-name-text"><?php echo htmlspecialchars($row['username']); ?></span>
                                </div>
                            </td>
                            <td><span class="badge <?php echo $roleClass; ?>"><?php echo strtoupper($row['role']); ?></span></td>
                            <td>
                                <?php if ($row['user_id'] != $_SESSION['user_id']): ?>
                                    <form method="POST" onsubmit="return confirm('Delete this user? This action cannot be undone.');">
                                        <input type="hidden" name="user_id" value="<?php echo $row['user_id']; ?>">
                                        <button type="submit" name="delete_user" class="btn-delete" title="Delete User">
                                            <i data-lucide="trash-2" style="width:16px"></i>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span style="font-size:11px; color:#999; font-style:italic;">(You)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="3" class="empty-state">No active users found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>