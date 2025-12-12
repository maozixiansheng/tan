<?php
/**
 * 获取用户碳记录列表API接口
 */

require_once '../../config.php';
require_once '../../includes/Database.php';
require_once '../../includes/Carbon.php';

// 设置响应头
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
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

// 只允许GET请求
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => '只允许GET请求']);
    exit;
}

try {
    // 验证token获取用户信息
    $userData = validateToken();
    $userId = $userData['user_id'];
    
    // 获取查询参数
    $page = $_GET['page'] ?? 1;
    $limit = $_GET['limit'] ?? 10;
    $emissionType = $_GET['emission_type'] ?? null;
    $startDate = $_GET['start_date'] ?? null;
    $endDate = $_GET['end_date'] ?? null;
    
    // 验证分页参数
    if (!is_numeric($page) || $page < 1) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => '页码必须为大于等于1的数字']);
        exit;
    }
    
    if (!is_numeric($limit) || $limit < 1 || $limit > 100) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => '每页记录数必须为1-100之间的数字']);
        exit;
    }
    
    // 验证排放类型
    if ($emissionType) {
        $allowedTypes = ['算力', '出行', '购物', '饮食', '生活'];
        if (!in_array($emissionType, $allowedTypes)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => '排放类型无效，允许的类型：' . implode(', ', $allowedTypes)]);
            exit;
        }
    }
    
    // 验证日期格式
    if ($startDate) {
        $date = DateTime::createFromFormat('Y-m-d', $startDate);
        if (!$date || $date->format('Y-m-d') !== $startDate) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => '开始日期格式无效，应为YYYY-MM-DD']);
            exit;
        }
    }
    
    if ($endDate) {
        $date = DateTime::createFromFormat('Y-m-d', $endDate);
        if (!$date || $date->format('Y-m-d') !== $endDate) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => '结束日期格式无效，应为YYYY-MM-DD']);
            exit;
        }
    }
    
    // 验证日期范围
    if ($startDate && $endDate && strtotime($startDate) > strtotime($endDate)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => '开始日期不能晚于结束日期']);
        exit;
    }
    
    // 计算分页
    $offset = ($page - 1) * $limit;
    
    // 准备查询条件
    $filters = [
        'emission_type' => $emissionType,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'limit' => $limit,
        'offset' => $offset
    ];
    
    // 创建Carbon实例
    $carbon = new Carbon();
    
    // 获取排放历史
    $emissionHistory = $carbon->getEmissionHistory($userId, $filters);
    
    // 获取总记录数
    $total = $emissionHistory['total'];
    $records = $emissionHistory['records'];
    
    // 获取每条记录的能量值
    foreach ($records as &$record) {
        $baseEmission = $record['emission_amount'] * 1.2;
        $record['energy_gained'] = $carbon->calculateEnergy($baseEmission, $record['emission_amount']);
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => '获取碳记录列表成功',
        'data' => [
            'total' => $total,
            'page' => (int)$page,
            'limit' => (int)$limit,
            'records' => $records
        ]
    ]);
    
} catch (Exception $e) {
    // 记录错误日志
    file_put_contents('../../logs/error.log', date('Y-m-d H:i:s') . ' - 获取碳记录列表失败: ' . $e->getMessage() . "\n", FILE_APPEND);
    
    // 根据错误类型设置不同的HTTP状态码
    $statusCode = 500;
    $errorMessage = '获取碳记录列表失败: ' . $e->getMessage();
    
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