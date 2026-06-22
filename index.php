<?php
require_once 'config.php';
if (isLoggedIn()) {
    redirect('dashboard.php');
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>NetEase Account Checker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card {
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 420px;
        }
        .card-header {
            background: transparent;
            border-bottom: none;
            text-align: center;
            padding: 30px 30px 0;
        }
        .card-header i {
            font-size: 60px;
            color: #667eea;
        }
        .card-header h2 {
            color: #333;
            margin-top: 10px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            width: 100%;
            padding: 12px;
            font-weight: bold;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        .btn-outline-primary {
            width: 100%;
            padding: 12px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="card-header">
            <i class="fas fa-gamepad"></i>
            <h2>NetEase Checker</h2>
        </div>
        <div class="card-body p-4">
            <?php $flash = getFlash(); if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show">
                    <?php echo $flash['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <a href="login.php" class="btn btn-primary mb-3">
                <i class="fas fa-sign-in-alt"></i> Login
            </a>
            
            <a href="register.php" class="btn btn-outline-primary">
                <i class="fas fa-user-plus"></i> Create Account
            </a>
            
            <hr>
            <div class="text-center text-muted">
                <small>Secure account checking with API support</small>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>