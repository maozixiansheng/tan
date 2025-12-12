<?php
/**
 * 获取载体状态API接口
 */

require_once '../../config.php';
require_once '../../includes/Database.php';
require_once '../../includes/Carrier.php';

// 设置响应头
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
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

try {
    // 创建载体实例
    $carrier = new Carrier();
    
    // 获取用户载体信息
    $carrierInfo = $carrier->getCarrierInfo($userId);
    
    // 转换数据结构以匹配前端期望的格式
    $carrierData = [
        'carrier_id' => 1, // 默认载体ID
        'carrier_name' => $carrierInfo['current']['name'],
        'carrier_level' => $carrierInfo['current']['stage'],
        'current_energy' => $carrierInfo['available_energy'],
        'current_max_energy' => $carrierInfo['current']['max_energy'] ?? 100,
        'next_level_energy' => $carrierInfo['next'] ? $carrierInfo['next']['energy_required'] : 0,
        'can_upgrade' => $carrierInfo['can_upgrade'],
        'last_updated' => date('Y-m-d H:i:s'),
        // 添加next对象，包含下一阶段信息
        'next' => $carrierInfo['next'] ? [
            'energy_required' => $carrierInfo['next']['energy_required']
        ] : null
    ];
    
    echo json_encode($carrierData);
    
} catch (Exception $e) {
    // 记录错误日志
    logSystem('获取载体状态失败: ' . $e->getMessage(), 'error');
    
    // 返回错误响应
    echo json_encode([
        'status' => 'error',
        'message' => '获取载体状态失败'
    ]);
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