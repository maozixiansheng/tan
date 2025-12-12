<?php
/**
 * 用户注册API接口
 */

require_once '../../config.php';
require_once '../../includes/Database.php';
require_once '../../includes/UserManager.php';

// 设置响应头
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

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

if (!isset($input['email']) || empty($input['email'])) {
    echo json_encode(['status' => 'error', 'message' => '邮箱不能为空']);
    exit;
}

if (!isset($input['password']) || empty($input['password'])) {
    echo json_encode(['status' => 'error', 'message' => '密码不能为空']);
    exit;
}

// 验证邮箱格式
if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => '邮箱格式不正确']);
    exit;
}

// 验证密码长度
if (strlen($input['password']) < 8) {
    echo json_encode(['status' => 'error', 'message' => '密码长度不能少于8位']);
    exit;
}

// 验证密码复杂度
if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/', $input['password'])) {
    echo json_encode(['status' => 'error', 'message' => '密码必须包含大小写字母和数字']);
    exit;
}

try {
    // 创建用户管理器实例
    $userManager = new UserManager();
    
    // 准备用户数据
    $username = trim($input['username']);
    $email = trim($input['email']);
    $password = $input['password'];
    
    // 转换用户类型为数据库中的中文值
    $userTypeMap = [
        'personal' => '个人',
        'enterprise' => '企业',
        'institution' => '组织'
    ];
    $userType = $userTypeMap[$input['user_type'] ?? 'personal'] ?? '个人';
    
    // 转换载体类型为数据库中的中文值
    $carrierTypeMap = [
        'tree' => '碳汇树',
        'pagoda' => '碳汇草'  // 根据数据库表结构，双碳宝塔对应碳汇草
    ];
    $carrierType = $carrierTypeMap[$input['carrier_type'] ?? 'tree'] ?? '碳汇树';
    
    // 注册用户
    $userId = $userManager->register($username, $password, $email, $userType);
    
    // 生成JWT token
    $token = generateJWT($userId, $username);
    
    // 返回成功响应
    echo json_encode([
        'status' => 'success',
        'message' => '注册成功',
        'user_id' => $userId,
        'token' => $token
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