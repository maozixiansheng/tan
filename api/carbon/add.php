<?php
/**
 * 添加碳排放记录API接口
 */

require_once '../../config.php';
require_once '../../includes/Database.php';
require_once '../../includes/Carbon.php';

// 设置响应头
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// 验证token
function validateToken() {
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
    
    if (empty($headers) || !preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
        throw new Exception('Token not found');
    }
    
    $token = $matches[1];
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

// 只允许POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => '只允许POST请求']);
    exit;
}

// 获取请求数据
$input = json_decode(file_get_contents('php://input'), true);

// 验证必填字段
if (!isset($input['emission_type']) || empty($input['emission_type'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => '排放类型不能为空']);
    exit;
}

// 验证排放类型是否在允许的枚举值范围内
$allowedTypes = ['算力', '出行', '购物', '饮食', '生活'];
if (!in_array($input['emission_type'], $allowedTypes)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => '排放类型无效，允许的类型：' . implode(', ', $allowedTypes)]);
    exit;
}

if (!isset($input['compute_power']) || !is_numeric($input['compute_power'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => '算力值必须为数字']);
    exit;
}

// 验证算力值的数值范围
$computePower = floatval($input['compute_power']);
if ($computePower <= 0 || $computePower > 10000) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => '算力值必须在0到10000之间']);
    exit;
}

// 验证排放日期格式
if (isset($input['emission_date']) && !empty($input['emission_date'])) {
    $date = DateTime::createFromFormat('Y-m-d', $input['emission_date']);
    if (!$date || $date->format('Y-m-d') !== $input['emission_date']) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => '排放日期格式无效，应为YYYY-MM-DD']);
        exit;
    }
}

// 验证描述长度
if (isset($input['description']) && strlen($input['description']) > 200) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => '描述长度不能超过200个字符']);
    exit;
}

try {
    // 验证token获取用户信息
    $userData = validateToken();
    $userId = $userData['user_id'];
    
    // 准备记录数据
    $recordData = [
        'emission_type' => $input['emission_type'],
        'compute_power' => $input['compute_power'],
        'description' => $input['description'] ?? null,
        'emission_date' => $input['emission_date'] ?? date('Y-m-d')
    ];
    
    // 创建Carbon实例
    $carbon = new Carbon();
    
    // 记录碳排放
    $recordId = $carbon->recordEmission($userId, $recordData);
    
    // 获取计算结果
    $actualEmission = $carbon->calculateEmission($recordData['compute_power'], $recordData['emission_type']);
    $baseEmission = $actualEmission * 1.2;
    $energyGained = $carbon->calculateEnergy($baseEmission, $actualEmission);
    
    echo json_encode([
        'status' => 'success',
        'message' => '碳排放记录添加成功',
        'record_id' => $recordId,
        'emission_type' => $recordData['emission_type'],
        'emission_amount' => $actualEmission,
        'emission_date' => $recordData['emission_date'],
        'energy_gained' => $energyGained,
        'description' => $recordData['description']
    ]);
    
} catch (Exception $e) {
    // 记录错误日志
    file_put_contents('../../logs/error.log', date('Y-m-d H:i:s') . ' - 碳排放记录添加失败: ' . $e->getMessage() . "\n", FILE_APPEND);
    
    // 根据错误类型设置不同的HTTP状态码
    $statusCode = 500;
    $errorMessage = '碳排放记录添加失败: ' . $e->getMessage();
    
    // Token相关错误使用401状态码
    if (strpos($e->getMessage(), 'Token') !== false) {
        $statusCode = 401;
    }
    
    // 返回错误响应
    http_response_code($statusCode);
    echo json_encode([
        'status' => 'error',
        'message' => $errorMessage
    ]);
}
?>