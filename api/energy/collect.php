<?php
/**
 * 收取能量球API接口
 */

require_once '../../config.php';
require_once '../../includes/Database.php';
require_once '../../includes/EnergyManager.php';
require_once '../../includes/ErrorHandler.php';

// 设置响应头
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// 验证token
try {
    $token = getBearerToken();
    $userData = validateJWT($token);
    $userId = $userData['user_id'];
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => '身份验证失败']);
    exit;
}

// 允许GET和POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => '只允许GET和POST请求']);
    exit;
}

// 获取请求数据
$input = json_decode(file_get_contents('php://input'), true);

// 验证必填字段
if (!isset($input['energy_ball_id']) || empty($input['energy_ball_id'])) {
    echo json_encode(['status' => 'error', 'message' => '能量球ID不能为空']);
    exit;
}

try {
    // 创建能量管理器实例
    $energyManager = new EnergyManager();
    
    // 调试信息
    $debugInfo = [];
    $debugInfo[] = '开始收取能量球，用户ID: ' . $userId;
    $debugInfo[] = '能量球ID: ' . $input['energy_ball_id'];
    
    // 收取能量球
    $result = $energyManager->collectEnergyBall($userId, $input['energy_ball_id']);
    
    $debugInfo[] = '能量球收取成功，获得能量: ' . $result['energy_amount'];
    if ($result['carrier_upgraded']) {
        $debugInfo[] = '载体升级成功';
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => '能量球收取成功',
        'energy_gained' => $result['energy_amount'],
        'carrier_upgraded' => $result['carrier_upgraded'],
        'debug' => $debugInfo
    ]);
    
} catch (Exception $e) {
    // 使用错误处理器记录和返回详细错误信息
    $errorResponse = ErrorHandler::handleApiError('收取能量球失败', $e, $userId);
    
    // 添加额外的调试信息
    if (ErrorHandler::isDebugMode()) {
        $errorResponse['debug']['additional_info'] = [
            'api_endpoint' => 'collect.php',
            'request_method' => $_SERVER['REQUEST_METHOD'],
            'request_time' => date('Y-m-d H:i:s'),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'energy_ball_id' => $input['energy_ball_id'] ?? 'unknown'
        ];
    }
    
    echo json_encode($errorResponse, JSON_UNESCAPED_UNICODE);
}

/**
 * 从请求头获取Bearer token
 */
function getBearerToken() {
    $headers = null;
    if (isset($_SERVER['Authorization'])) {
        $headers = trim($_SERVER['Authorization']);
    } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers = trim($_SERVER['HTTP_AUTHORIZATION']);
    } elseif (function_exists('apache_request_headers')) {
        $requestHeaders = apache_request_headers();
        if (isset($requestHeaders['Authorization'])) {
            $headers = trim($requestHeaders['Authorization']);
        }
    }
    
    if (!empty($headers) && preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
        return $matches[1];
    }
    
    throw new Exception('Token not found');
}

/**
 * 验证JWT token
 */
function validateJWT($token) {
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        throw new Exception('Invalid token format');
    }
    
    list($base64Header, $base64Payload, $base64Signature) = $parts;
    
    // 验证签名
    $signature = base64_decode($base64Signature);
    $validSignature = hash_hmac('sha256', $base64Header . "." . $base64Payload, JWT_SECRET, true);
    
    if (!hash_equals($signature, $validSignature)) {
        throw new Exception('Invalid signature');
    }
    
    // 解码payload
    $payload = json_decode(base64_decode($base64Payload), true);
    
    // 验证过期时间
    if (isset($payload['exp']) && $payload['exp'] < time()) {
        throw new Exception('Token expired');
    }
    
    return $payload;
}
?>