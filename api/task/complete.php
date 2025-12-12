<?php
/**
 * 完成任务API接口
 */

require_once '../../config.php';
require_once '../../includes/Database.php';
require_once '../../includes/TaskManager.php';

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
if (!isset($input['task_id']) || empty($input['task_id'])) {
    echo json_encode(['status' => 'error', 'message' => '任务ID不能为空']);
    exit;
}

try {
    // 创建任务管理器实例
    $taskManager = new TaskManager();
    
    // 完成任务
    $result = $taskManager->executeTask($userId, $input['task_id']);
    
    // executeTask成功执行，返回成功响应
    echo json_encode([
        'status' => 'success',
        'message' => '任务完成成功',
        'energy_reward' => $result['energy_reward'],
        'task_name' => $result['task_name']
    ]);
    
} catch (Exception $e) {
    // 记录详细的错误日志
    $errorDetails = [
        'error_message' => $e->getMessage(),
        'error_code' => $e->getCode(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
        'user_id' => $userId,
        'task_id' => $input['task_id']
    ];
    
    logSystem('task_complete_error', $userId, '完成任务失败', json_encode($errorDetails, JSON_UNESCAPED_UNICODE));
    
    // 返回详细的错误响应
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => '完成任务失败: ' . $e->getMessage(),
        'debug_info' => [
            'error_code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'user_id' => $userId,
            'task_id' => $input['task_id']
        ]
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