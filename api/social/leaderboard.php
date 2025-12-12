<?php
/**
 * 排行榜API接口
 */

require_once '../../config.php';
require_once '../../includes/Database.php';
require_once '../../includes/Social.php';

// 设置响应头
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
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

// 只允许GET请求
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => '只允许GET请求']);
    exit;
}

try {
    // 创建社交管理器实例
    $socialManager = new Social();
    
    // 获取排行榜类型
    $type = $_GET['type'] ?? 'energy'; // energy, level, friends
    $limit = $_GET['limit'] ?? 10;
    
    // 获取排行榜数据
    $leaderboard = $socialManager->getLeaderboard($type, $limit);
    
    // 获取用户排名
    $userRank = $socialManager->getUserRank($userId, $type);
    
    echo json_encode([
        'status' => 'success',
        'leaderboard' => $leaderboard,
        'user_rank' => $userRank,
        'type' => $type
    ]);
    
} catch (Exception $e) {
    // 记录错误日志
    logSystem('获取排行榜失败: ' . $e->getMessage(), 'error');
    
    // 返回错误响应
    echo json_encode([
        'status' => 'error',
        'message' => '获取排行榜失败'
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