<?php
/**
 * 更新碳记录API接口
 */

require_once '../../config.php';
require_once '../../includes/Database.php';
require_once '../../includes/Carbon.php';

// 设置响应头
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT');
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

// 只允许PUT请求
if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => '只允许PUT请求']);
    exit;
}

// 获取请求数据
$input = json_decode(file_get_contents('php://input'), true);

// 验证必填参数
if (!isset($input['record_id']) || empty($input['record_id'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => '记录ID不能为空']);
    exit;
}

// 验证记录ID必须为数字
if (!is_numeric($input['record_id'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => '记录ID必须为数字']);
    exit;
}

// 验证排放类型（如果提供）
if (isset($input['emission_type'])) {
    $allowedTypes = ['算力', '出行', '购物', '饮食', '生活'];
    if (!in_array($input['emission_type'], $allowedTypes)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => '排放类型无效，允许的类型：' . implode(', ', $allowedTypes)]);
        exit;
    }
}

// 验证算力值（如果提供）
if (isset($input['compute_power'])) {
    if (!is_numeric($input['compute_power'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => '算力值必须为数字']);
        exit;
    }
    
    $computePower = floatval($input['compute_power']);
    if ($computePower <= 0 || $computePower > 10000) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => '算力值必须在0到10000之间']);
        exit;
    }
}

// 验证排放日期（如果提供）
if (isset($input['emission_date']) && !empty($input['emission_date'])) {
    $date = DateTime::createFromFormat('Y-m-d', $input['emission_date']);
    if (!$date || $date->format('Y-m-d') !== $input['emission_date']) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => '排放日期格式无效，应为YYYY-MM-DD']);
        exit;
    }
}

// 验证描述长度（如果提供）
if (isset($input['description']) && strlen($input['description']) > 200) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => '描述长度不能超过200个字符']);
    exit;
}

try {
    // 验证token获取用户信息
    $userData = validateToken();
    $userId = $userData['user_id'];
    $recordId = $input['record_id'];
    
    // 创建Carbon实例
    $carbon = new Carbon();
    $db = Database::getInstance();
    
    // 检查记录是否存在且属于当前用户
    $sql = "SELECT * FROM carbon_emissions WHERE emission_id = ? AND user_id = ?";
    $existingRecord = $db->fetch($sql, [$recordId, $userId]);
    
    if (!$existingRecord) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => '碳记录不存在']);
        exit;
    }
    
    // 准备更新数据
    $updateData = [];
    $updateFields = [];
    
    if (isset($input['emission_type'])) {
        $updateFields[] = "emission_type = ?";
        $updateData[] = $input['emission_type'];
    }
    
    if (isset($input['compute_power'])) {
        $updateFields[] = "compute_power = ?";
        $updateData[] = $input['compute_power'];
        
        // 重新计算实际排放量
        $emissionType = isset($input['emission_type']) ? $input['emission_type'] : $existingRecord['emission_type'];
        $updateFields[] = "emission_amount = ?";
        $updateData[] = $carbon->calculateEmission($input['compute_power'], $emissionType);
    }
    
    if (isset($input['description'])) {
        $updateFields[] = "description = ?";
        $updateData[] = $input['description'];
    }
    
    if (isset($input['emission_date'])) {
        $updateFields[] = "emission_date = ?";
        $updateData[] = $input['emission_date'];
    }
    
    if (isset($input['is_verified'])) {
        $updateFields[] = "is_verified = ?";
        $updateData[] = $input['is_verified'] ? 1 : 0;
    }
    
    if (empty($updateFields)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => '没有需要更新的字段']);
        exit;
    }
    
    // 构建更新SQL
    $updateData[] = $recordId;
    $updateData[] = $userId;
    $sql = "UPDATE carbon_emissions SET " . implode(', ', $updateFields) . " WHERE emission_id = ? AND user_id = ?";
    
    // 执行更新
    $db->execute($sql, $updateData);
    
    // 获取更新后的记录
    $sql = "SELECT * FROM carbon_emissions WHERE emission_id = ? AND user_id = ?";
    $updatedRecord = $db->fetch($sql, [$recordId, $userId]);
    
    // 更新碳账户的碳足迹
    $carbon->updateCarbonFootprint($userId);
    
    echo json_encode([
        'status' => 'success',
        'message' => '碳记录更新成功',
        'data' => $updatedRecord
    ]);
    
} catch (Exception $e) {
    // 记录错误日志
    file_put_contents('../../logs/error.log', date('Y-m-d H:i:s') . ' - 更新碳记录失败: ' . $e->getMessage() . "\n", FILE_APPEND);
    
    // 根据错误类型设置不同的HTTP状态码
    $statusCode = 500;
    $errorMessage = '更新碳记录失败: ' . $e->getMessage();
    
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