<?php
require_once 'config.php';
if (!isLoggedIn() || !isAdmin()) {
    flash('Admin access required', 'danger');
    redirect('dashboard.php');
}

if (isset($_POST['add_proxy'])) {
    $proxy_string = trim($_POST['proxy_string']);
    if ($proxy_string) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO proxies (proxy_string) VALUES (?)");
        $stmt->execute([$proxy_string]);
        flash('Proxy added successfully', 'success');
    } else {
        flash('Invalid proxy string', 'danger');
    }
    redirect('admin.php');
}

if (isset($_POST['toggle_proxy'])) {
    $proxy_id = intval($_POST['proxy_id']);
    $stmt = $pdo->prepare("UPDATE proxies SET is_active = NOT is_active WHERE id = ?");
    $stmt->execute([$proxy_id]);
    flash('Proxy status updated', 'info');
    redirect('admin.php');
}

if (isset($_POST['delete_proxy'])) {
    $proxy_id = intval($_POST['proxy_id']);
    $stmt = $pdo->prepare("DELETE FROM proxies WHERE id = ?");
    $stmt->execute([$proxy_id]);
    flash('Proxy deleted', 'info');
    redirect('admin.php');
}

if (isset($_FILES['proxy_file']) && $_FILES['proxy_file']['error'] == 0) {
    $content = file_get_contents($_FILES['proxy_file']['tmp_name']);
    $lines = explode("\n", $content);
    $count = 0;
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        $stmt = $pdo->prepare("INSERT IGNORE INTO proxies (proxy_string) VALUES (?)");
        $stmt->execute([$line]);
        if ($stmt->rowCount() > 0) $count++;
    }
    flash("Added $count proxies", 'success');
    redirect('admin.php');
}

if (isset($_POST['generate_user_key'])) {
    $user_id = intval($_POST['user_id']);
    $api_key = generateApiKey();
    $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));
    $stmt = $pdo->prepare("INSERT INTO api_keys (api_key, user_id, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$api_key, $user_id, $expires_at]);
    flash('API key generated for user', 'success');
    redirect('admin.php');
}

if (isset($_POST['revoke_user_key'])) {
    $key_id = intval($_POST['key_id']);
    $stmt = $pdo->prepare("UPDATE api_keys SET is_active = 0 WHERE id = ?");
    $stmt->execute([$key_id]);
    flash('API key revoked', 'info');
    redirect('admin.php');
}

$users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();
$proxies = $pdo->query("SELECT * FROM proxies ORDER BY added_at DESC")->fetchAll();
$all_results = $pdo->query("SELECT * FROM check_results ORDER BY checked_at DESC LIMIT 100")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Panel - NetEase Checker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, #2d3436 0%, #1a1a2e 100%);
            color: white;
            padding: 20px;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.7);
            padding: 12px 20px;
            border-radius: 10px;
            margin-bottom: 5px;
            text-decoration: none;
            display: block;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.1);
        }
        .sidebar .nav-link i {
            width: 25px;
        }
        .main-content {
            padding: 30px;
            background: #f5f6fa;
            min-height: 100vh;
        }
        .admin-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        .badge-admin {
            background: #e17055;
        }
        .stat-box {
            background: white;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .stat-box .number {
            font-size: 32px;
            font-weight: bold;
        }
        .stat-box .label {
            color: #666;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-2 sidebar">
                <h4 class="mb-4">
                    <i class="fas fa-crown"></i> Admin Panel
                </h4>
                <nav>
                    <a class="nav-link active" href="#">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#proxyModal">
                        <i class="fas fa-network-wired"></i> Add Proxy
                    </a>
                    <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#uploadProxyModal">
                        <i class="fas fa-upload"></i> Upload Proxies
                    </a>
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                    <a class="nav-link" href="logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </nav>
            </div>
            <div class="col-md-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-crown text-warning"></i> Admin Dashboard</h2>
                    <span class="badge bg-danger badge-admin">
                        <i class="fas fa-user-shield"></i> Administrator
                    </span>
                </div>
                <?php $flash = getFlash(); if ($flash): ?>
                    <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show">
                        <?php echo $flash['message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-box">
                            <div class="number text-primary"><?php echo count($users); ?></div>
                            <div class="label">Total Users</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-box">
                            <div class="number text-success"><?php echo count($all_results); ?></div>
                            <div class="label">Total Checks</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-box">
                            <div class="number text-info"><?php echo count($proxies); ?></div>
                            <div class="label">Proxies</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-box">
                            <div class="number text-warning">
                                <?php 
                                $active = array_filter($proxies, function($p) { return $p['is_active'] == 1; });
                                echo count($active);
                                ?>
                            </div>
                            <div class="label">Active Proxies</div>
                        </div>
                    </div>
                </div>
                <div class="admin-card">
                    <h5><i class="fas fa-users"></i> Users</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Joined</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <?php if ($user['is_admin']): ?>
                                            <span class="badge bg-danger">Admin</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">User</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" name="generate_user_key" class="btn btn-sm btn-success">
                                                <i class="fas fa-key"></i> Gen Key
                                            </button>
                                        </form>
                                        <?php 
                                        $stmt = $pdo->prepare("SELECT * FROM api_keys WHERE user_id = ? AND is_active = 1");
                                        $stmt->execute([$user['id']]);
                                        $user_keys = $stmt->fetchAll();
                                        foreach ($user_keys as $key): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="key_id" value="<?php echo $key['id']; ?>">
                                                <button type="submit" name="revoke_user_key" class="btn btn-sm btn-danger">
                                                    <i class="fas fa-ban"></i> Revoke
                                                </button>
                                            </form>
                                        <?php endforeach; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="admin-card">
                    <h5><i class="fas fa-network-wired"></i> Proxies</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Proxy</th>
                                    <th>Status</th>
                                    <th>Failure Count</th>
                                    <th>Last Used</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($proxies as $proxy): ?>
                                <tr>
                                    <td><code><?php echo htmlspecialchars($proxy['proxy_string']); ?></code></td>
                                    <td>
                                        <?php if ($proxy['is_active']): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $proxy['failure_count']; ?></td>
                                    <td><?php echo $proxy['last_used'] ? date('Y-m-d H:i', strtotime($proxy['last_used'])) : 'Never'; ?></td>
                                    <td>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="proxy_id" value="<?php echo $proxy['id']; ?>">
                                            <button type="submit" name="toggle_proxy" class="btn btn-sm btn-<?php echo $proxy['is_active'] ? 'warning' : 'success'; ?>">
                                                <?php if ($proxy['is_active']): ?>
                                                    <i class="fas fa-pause"></i> Deactivate
                                                <?php else: ?>
                                                    <i class="fas fa-play"></i> Activate
                                                <?php endif; ?>
                                            </button>
                                        </form>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="proxy_id" value="<?php echo $proxy['id']; ?>">
                                            <button type="submit" name="delete_proxy" class="btn btn-sm btn-danger" onclick="return confirm('Delete this proxy?')">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="admin-card">
                    <h5><i class="fas fa-list"></i> All Check Results</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th>Details</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_results as $result): 
                                    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
                                    $stmt->execute([$result['user_id']]);
                                    $user = $stmt->fetch();
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['username'] ?? 'Unknown'); ?></td>
                                    <td><?php echo htmlspecialchars($result['email']); ?></td>
                                    <td>
                                        <?php if ($result['status'] == 'success'): ?>
                                            <span class="badge bg-success">Success</span>
                                        <?php elseif ($result['status'] == 'failed'): ?>
                                            <span class="badge bg-danger">Failed</span>
                                        <?php elseif ($result['status'] == 'invalid'): ?>
                                            <span class="badge bg-warning">Invalid</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Error</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars(substr($result['details'], 0, 50)); ?></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($result['checked_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="proxyModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Add Proxy</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Proxy String</label>
                            <input type="text" class="form-control" name="proxy_string" placeholder="ip:port" required>
                            <small class="text-muted">Format: ip:port (e.g., 192.168.1.1:8080)</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="add_proxy" class="btn btn-primary">Add Proxy</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="modal fade" id="uploadProxyModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-upload"></i> Upload Proxies</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <p>Upload a .txt file with proxies in format: <code>ip:port</code></p>
                        <div class="mb-3">
                            <label class="form-label">Choose file</label>
                            <input type="file" class="form-control" name="proxy_file" accept=".txt" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Upload Proxies</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>