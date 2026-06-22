<?php
require_once 'config.php';
if (isLoggedIn()) {
    redirect('dashboard.php');
}
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['is_admin'] = $user['is_admin'];
        $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);
        flash('Login successful!', 'success');
        redirect('dashboard.php');
    } else {
        flash('Invalid username or password', 'danger');
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login - NetEase Checker</title>
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
            font-size: 50px;
            color: #667eea;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            width: 100%;
            padding: 12px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="card-header">
            <i class="fas fa-user-circle"></i>
            <h3>Login</h3>
        </div>
        <div class="card-body p-4">
            <?php $flash = getFlash(); if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type']; ?>"><?php echo $flash['message']; ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" class="form-control" name="username" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" class="form-control" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary">Login</button>
            </form>
            <p class="text-center mt-3">
                <a href="register.php">Create an account</a>
            </p>
            <p class="text-center mt-2">
                <a href="index.php"><i class="fas fa-arrow-left"></i> Back</a>
            </p>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>