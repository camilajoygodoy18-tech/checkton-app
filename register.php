<?php
require_once 'config.php';
if (isLoggedIn()) {
    redirect('dashboard.php');
}
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    if ($password !== $confirm) {
        flash('Passwords do not match', 'danger');
    } elseif (strlen($password) < 6) {
        flash('Password must be at least 6 characters', 'danger');
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            flash('Username already exists', 'danger');
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                flash('Email already registered', 'danger');
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
                $stmt->execute([$username, $email, $hashed]);
                flash('Registration successful! Please login.', 'success');
                redirect('login.php');
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Register - NetEase Checker</title>
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
            <i class="fas fa-user-plus"></i>
            <h3>Register</h3>
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
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" name="email" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" class="form-control" name="password" required minlength="6">
                    <small class="text-muted">Minimum 6 characters</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirm Password</label>
                    <input type="password" class="form-control" name="confirm_password" required>
                </div>
                <button type="submit" class="btn btn-primary">Register</button>
            </form>
            <p class="text-center mt-3">
                <a href="login.php">Already have an account? Login</a>
            </p>
            <p class="text-center mt-2">
                <a href="index.php"><i class="fas fa-arrow-left"></i> Back</a>
            </p>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>