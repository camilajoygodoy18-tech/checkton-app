<?php
require_once 'config.php';

$headers = getallheaders();
$api_key = $headers['X-API-Key'] ?? '';

if (empty($api_key)) {
    http_response_code(401);
    echo json_encode(['error' => 'API key required']);
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM api_keys WHERE api_key = ? AND is_active = 1");
$stmt->execute([$api_key]);
$key_data = $stmt->fetch();

if (!$key_data) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid or inactive API key']);
    exit();
}

if ($key_data['expires_at'] && strtotime($key_data['expires_at']) < time()) {
    $stmt = $pdo->prepare("UPDATE api_keys SET is_active = 0 WHERE id = ?");
    $stmt->execute([$key_data['id']]);
    http_response_code(401);
    echo json_encode(['error' => 'API key expired']);
    exit();
}

$stmt = $pdo->prepare("UPDATE api_keys SET last_used = NOW() WHERE id = ?");
$stmt->execute([$key_data['id']]);

$user_id = $key_data['user_id'];
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

if ($method == 'POST') {
    $action = $_GET['action'] ?? '';
    
    if ($action == 'check') {
        $email = $input['email'] ?? '';
        $password = $input['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            echo json_encode(['error' => 'Email and password required']);
            exit();
        }
        
        $result = checkAccount($email, $password, $user_id, $pdo);
        echo json_encode($result);
        
    } elseif ($action == 'bulk') {
        $accounts = $input['accounts'] ?? [];
        
        if (empty($accounts) || !is_array($accounts)) {
            echo json_encode(['error' => 'Accounts array required']);
            exit();
        }
        
        $results = [];
        $count = 0;
        
        foreach ($accounts as $account) {
            if ($count >= 50) break;
            if (strpos($account, ':') !== false) {
                list($email, $password) = explode(':', $account, 2);
                $email = trim($email);
                $password = trim($password);
                $result = checkAccount($email, $password, $user_id, $pdo);
                $results[] = $result;
                $count++;
            }
        }
        
        echo json_encode(['results' => $results, 'count' => $count]);
        
    } else {
        echo json_encode(['error' => 'Invalid action. Use ?action=check or ?action=bulk']);
    }
    
} else {
    echo json_encode(['error' => 'Only POST method allowed']);
}
?>