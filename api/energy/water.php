<?php
/**
 * 浇水API接口
 */

// 设置根路径
$rootPath = dirname(dirname(dirname(__FILE__)));

// 引入配置文件
require_once $rootPath . '/config.php';
require_once $rootPath . '/includes/Database.php';
require_once $rootPath . '/includes/EnergyManager.php';
require_once $rootPath . '/includes/ErrorHandler.php';

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

try {
    // 创建能量管理器实例
    $energyManager = new EnergyManager();
    
    // 调试信息
    $debugInfo = [];
    $debugInfo[] = '开始浇水，用户ID: ' . $userId;
    
    // 执行浇水操作
    $result = $energyManager->waterCarrier($userId);
    
    $debugInfo[] = '浇水成功，生成能量球数量: ' . $result['balls_generated'];
    $debugInfo[] = '消耗能量: ' . $result['energy_cost'];
    
    echo json_encode([
        'status' => 'success',
        'message' => '浇水成功！' . $result['balls_generated'] . '个能量球已生成',
        'balls_generated' => $result['balls_generated'],
        'energy_cost' => $result['energy_cost'],
        'debug' => $debugInfo
    ]);
    
} catch (Exception $e) {
    // 使用错误处理器记录和返回详细错误信息
    $errorResponse = ErrorHandler::handleApiError('浇水失败', $e, $userId);
    
    // 添加额外的调试信息
    if (ErrorHandler::isDebugMode()) {
        $errorResponse['debug']['additional_info'] = [
            'api_endpoint' => 'water.php',
            'request_method' => $_SERVER['REQUEST_METHOD'],
            'request_time' => date('Y-m-d H:i:s'),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
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