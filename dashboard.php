<?php
require_once 'config.php';
if (!isLoggedIn()) {
    redirect('login.php');
}
$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success,
    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
    SUM(CASE WHEN status = 'invalid' THEN 1 ELSE 0 END) as invalid,
    SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as errors
    FROM check_results WHERE user_id = ?");
$stmt->execute([$user_id]);
$stats = $stmt->fetch();

$stmt = $pdo->prepare("SELECT * FROM check_results WHERE user_id = ? ORDER BY checked_at DESC LIMIT 50");
$stmt->execute([$user_id]);
$results = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM api_keys WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$api_keys = $stmt->fetchAll();

if (isset($_POST['generate_key'])) {
    $api_key = generateApiKey();
    $expires_in = intval($_POST['expires_in'] ?? 30);
    $expires_at = date('Y-m-d H:i:s', strtotime("+$expires_in days"));
    $stmt = $pdo->prepare("INSERT INTO api_keys (api_key, user_id, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$api_key, $user_id, $expires_at]);
    flash('API key generated successfully!', 'success');
    redirect('dashboard.php');
}

if (isset($_POST['revoke_key'])) {
    $key_id = intval($_POST['key_id']);
    $stmt = $pdo->prepare("UPDATE api_keys SET is_active = 0 WHERE id = ? AND user_id = ?");
    $stmt->execute([$key_id, $user_id]);
    flash('API key revoked', 'info');
    redirect('dashboard.php');
}

if (isset($_FILES['accounts_file']) && $_FILES['accounts_file']['error'] == 0) {
    $content = file_get_contents($_FILES['accounts_file']['tmp_name']);
    $lines = explode("\n", $content);
    $count = 0;
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        if (strpos($line, ':') !== false) {
            list($email, $password) = explode(':', $line, 2);
            $email = trim($email);
            $password = trim($password);
            checkAccount($email, $password, $user_id, $pdo);
            $count++;
            usleep(500000);
        }
    }
    flash("Processed $count accounts", 'success');
    redirect('dashboard.php');
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard - NetEase Checker</title>
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
            transition: all 0.3s;
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
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
        }
        .stat-icon {
            font-size: 32px;
            margin-bottom: 10px;
        }
        .stat-number {
            font-size: 28px;
            font-weight: bold;
        }
        .success { color: #00b894; }
        .failed { color: #e17055; }
        .invalid { color: #fdcb6e; }
        .error { color: #d63031; }
        .btn-upload {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
        }
        .btn-upload:hover {
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-2 sidebar">
                <h4 class="mb-4">
                    <i class="fas fa-gamepad"></i> Checker
                </h4>
                <nav>
                    <a class="nav-link active" href="#">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#uploadModal">
                        <i class="fas fa-upload"></i> Upload Accounts
                    </a>
                    <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#apiModal">
                        <i class="fas fa-key"></i> API Keys
                    </a>
                    <?php if (isAdmin()): ?>
                    <a class="nav-link" href="admin.php">
                        <i class="fas fa-crown"></i> Admin Panel
                    </a>
                    <?php endif; ?>
                    <a class="nav-link" href="logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </nav>
            </div>
            <div class="col-md-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h2>
                    <span class="badge bg-primary">
                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
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
                        <div class="stat-card text-center">
                            <div class="stat-icon success"><i class="fas fa-check-circle"></i></div>
                            <div class="stat-number success"><?php echo $stats['success'] ?? 0; ?></div>
                            <div class="text-muted">Working</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card text-center">
                            <div class="stat-icon failed"><i class="fas fa-times-circle"></i></div>
                            <div class="stat-number failed"><?php echo $stats['failed'] ?? 0; ?></div>
                            <div class="text-muted">Failed</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card text-center">
                            <div class="stat-icon invalid"><i class="fas fa-exclamation-triangle"></i></div>
                            <div class="stat-number invalid"><?php echo $stats['invalid'] ?? 0; ?></div>
                            <div class="text-muted">Invalid</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card text-center">
                            <div class="stat-icon error"><i class="fas fa-bug"></i></div>
                            <div class="stat-number error"><?php echo $stats['errors'] ?? 0; ?></div>
                            <div class="text-muted">Errors</div>
                        </div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-history"></i> Recent Results</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Email</th>
                                        <th>Status</th>
                                        <th>Details</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($results as $result): ?>
                                    <tr>
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
    </div>
    <div class="modal fade" id="uploadModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-upload"></i> Upload Accounts</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <p>Upload a .txt file with accounts in format: <code>email:password</code></p>
                        <div class="mb-3">
                            <label class="form-label">Choose file</label>
                            <input type="file" class="form-control" name="accounts_file" accept=".txt" required>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> Max 1000 accounts per upload
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-upload">Upload & Check</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="modal fade" id="apiModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-key"></i> API Keys</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" class="mb-3">
                        <div class="row">
                            <div class="col-md-8">
                                <input type="number" class="form-control" name="expires_in" value="30" placeholder="Days to expire">
                            </div>
                            <div class="col-md-4">
                                <button type="submit" name="generate_key" class="btn btn-primary w-100">Generate Key</button>
                            </div>
                        </div>
                    </form>
                    <?php if ($api_keys): ?>
                        <h6>Your API Keys</h6>
                        <div class="list-group">
                            <?php foreach ($api_keys as $key): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <code><?php echo substr($key['api_key'], 0, 16); ?>...</code>
                                            <span class="badge <?php echo $key['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                                                <?php echo $key['is_active'] ? 'Active' : 'Revoked'; ?>
                                            </span>
                                        </div>
                                        <div>
                                            <small class="text-muted">Expires: <?php echo date('Y-m-d', strtotime($key['expires_at'])); ?></small>
                                            <?php if ($key['is_active']): ?>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="key_id" value="<?php echo $key['id']; ?>">
                                                    <button type="submit" name="revoke_key" class="btn btn-sm btn-danger">Revoke</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No API keys generated yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>