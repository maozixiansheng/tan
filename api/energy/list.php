<?php
/**
 * 能量球列表API接口
 */

require_once '../../config.php';
require_once '../../includes/Database.php';
require_once '../../includes/UserManager.php';
require_once '../../includes/EnergyManager.php';
require_once '../../includes/ErrorHandler.php';

// 设置响应头
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// 处理预检请求
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// 验证token
try {
    $token = getBearerToken();
    if (!$token) {
        throw new Exception('Token不能为空');
    }
    
    // 调试：输出token信息到响应
    $debugInfo = [];
    $debugInfo[] = 'Received token: ' . $token;
    
    $userData = validateJWT($token);
    $userId = $userData['user_id'];
    
    // 调试：输出验证成功信息
    $debugInfo[] = 'Token验证成功，用户ID: ' . $userId;
    
} catch (Exception $e) {
    // 调试：输出验证失败信息
    $debugInfo[] = 'Token验证失败: ' . $e->getMessage();
    
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => '身份验证失败: ' . $e->getMessage(), 'debug' => $debugInfo]);
    exit;
}

// 创建能量管理器实例
$energyManager = new EnergyManager();

try {
    // 调试：输出数据库连接信息
    $debugInfo[] = '开始获取能量球列表，用户ID: ' . $userId;
    
    // 获取用户的能量球列表
    $energyBalls = $energyManager->getUserEnergyBalls($userId);
    
    // 调试：输出查询结果
    $debugInfo[] = '能量球查询完成，结果数量: ' . count($energyBalls);
    
    // 如果用户没有能量球，自动生成一些
    if (empty($energyBalls)) {
        $debugInfo[] = '用户没有能量球，开始生成3个新能量球';
        
        // 生成3个能量球（使用浇水功能自动生成）
        try {
            $wateringResult = $energyManager->waterCarrier($userId);
            $debugInfo[] = '浇水成功，生成能量球数量: ' . $wateringResult['balls_generated'];
        } catch (Exception $e) {
            $debugInfo[] = '浇水失败，无法自动生成能量球: ' . $e->getMessage();
        }
        
        $debugInfo[] = '能量球生成完成，重新获取列表';
        
        // 重新获取能量球列表
        $energyBalls = $energyManager->getUserEnergyBalls($userId);
        
        $debugInfo[] = '重新获取能量球列表，数量: ' . count($energyBalls);
    }
    
    echo json_encode([
        'status' => 'success',
        'energy_balls' => $energyBalls,
        'count' => count($energyBalls),
        'debug' => $debugInfo
    ]);
    
} catch (Exception $e) {
    // 使用错误处理器记录和返回详细错误信息
    $errorResponse = ErrorHandler::handleApiError('获取能量球列表失败', $e, $userId);
    
    // 添加额外的调试信息
    if (ErrorHandler::isDebugMode()) {
        $errorResponse['debug']['additional_info'] = [
            'api_endpoint' => 'list.php',
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
    
    // 验证签名 - 修复签名验证逻辑
    $expectedSignature = hash_hmac('sha256', $base64Header . "." . $base64Payload, JWT_SECRET, true);
    $expectedBase64Signature = base64_encode($expectedSignature);
    
    if (!hash_equals($base64Signature, $expectedBase64Signature)) {
        throw new Exception('Token签名无效');
    }
    
    $payload = json_decode(base64_decode($base64Payload), true);
    
    // 验证过期时间
    if (isset($payload['exp']) && $payload['exp'] < time()) {
        throw new Exception('Token已过期');
    }
    
    return $payload;
}
?>