<?php
/**
 * 用户登录API接口
 */

// 确保会话已启动
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config.php';
require_once '../../includes/Database.php';
require_once '../../includes/UserManager.php';

/**
 * 从请求头获取Bearer token
 */
function getBearerToken() {
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        if (preg_match('/Bearer\s(.*)/', $headers['Authorization'], $matches)) {
            return $matches[1];
        }
    }
    return null;
}

/**
 * 验证JWT token
 */
function validateJWT($token) {
    if (!$token) {
        throw new Exception('Token不能为空');
    }
    
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        throw new Exception('Token格式错误');
    }
    
    list($base64Header, $base64Payload, $base64Signature) = $parts;
    
    // 验证签名
    $signature = base64_decode($base64Signature);
    $expectedSignature = hash_hmac('sha256', $base64Header . "." . $base64Payload, JWT_SECRET, true);
    
    if (!hash_equals($signature, $expectedSignature)) {
        throw new Exception('Token签名无效');
    }
    
    $payload = json_decode(base64_decode($base64Payload), true);
    
    // 验证过期时间
    if (isset($payload['exp']) && $payload['exp'] < time()) {
        throw new Exception('Token已过期');
    }
    
    return $payload;
}

// 设置响应头
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true'); // 如需携带cookie
// 只允许POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => '只允许POST请求']);
    exit;
}

// 获取请求数据
$input = json_decode(file_get_contents('php://input'), true);

// 验证必填字段
if (!isset($input['username']) || empty($input['username'])) {
    echo json_encode(['status' => 'error', 'message' => '用户名不能为空']);
    exit;
}

if (!isset($input['password']) || empty($input['password'])) {
    echo json_encode(['status' => 'error', 'message' => '密码不能为空']);
    exit;
}

try {
    // 创建用户管理器实例
    $userManager = new UserManager();
    
    // 用户登录
    $userInfo = $userManager->login($input['username'], $input['password']);
    
    // 生成JWT token
    $token = generateJWT($userInfo['user_id'], $userInfo['username']);
    
    // 返回成功响应
    echo json_encode([
        'status' => 'success',
        'message' => '登录成功',
        'token' => $token,
        'user' => $userInfo
    ]);
    
} catch (Exception $e) {
    // 返回错误响应
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

/**
 * 生成JWT token
 */
function generateJWT($userId, $username) {
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $payload = json_encode([
        'user_id' => $userId,
        'username' => $username,
        'iat' => time(),
        'exp' => time() + (7 * 24 * 60 * 60) // 7天过期
    ]);
    
    $base64Header = base64_encode($header);
    $base64Payload = base64_encode($payload);
    
    $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, JWT_SECRET, true);
    $base64Signature = base64_encode($signature);
    
    return $base64Header . "." . $base64Payload . "." . $base64Signature;
}
?>