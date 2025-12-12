<?php
/**
 * 碳排放计算API接口
 */

require_once '../../config.php';
require_once '../../includes/Database.php';
require_once '../../includes/EnergyManager.php';

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

// 只允许POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => '只允许POST请求']);
    exit;
}

// 获取请求数据
$input = json_decode(file_get_contents('php://input'), true);

// 验证必填字段
if (!isset($input['activity_type']) || empty($input['activity_type'])) {
    echo json_encode(['status' => 'error', 'message' => '活动类型不能为空']);
    exit;
}

if (!isset($input['activity_value']) || !is_numeric($input['activity_value'])) {
    echo json_encode(['status' => 'error', 'message' => '活动值必须为数字']);
    exit;
}

try {
    // 创建能量管理器实例
    $energyManager = new EnergyManager();
    
    // 计算碳排放
    $result = $energyManager->calculateCarbonEnergy($userId, $input['activity_type'], $input['activity_value']);
    
    echo json_encode([
        'status' => 'success',
        'carbon_emission' => $result['carbon_emission'],
        'energy_gained' => $result['energy_gained'],
        'activity_type' => $input['activity_type'],
        'activity_value' => $input['activity_value']
    ]);
    
} catch (Exception $e) {
    // 记录错误日志
    logSystem('碳排放计算失败: ' . $e->getMessage(), 'error');
    
    // 返回错误响应
    echo json_encode([
        'status' => 'error',
        'message' => '碳排放计算失败'
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