<?php
/**
 * 碳记录统计API接口
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
    
    // 创建Carbon和Database实例
    $carbon = new Carbon();
    $db = Database::getInstance();
    
    // 获取今日日期
    $today = date('Y-m-d');
    
    // 计算总碳排放量
    $sql = "SELECT SUM(emission_amount) as total_emission FROM carbon_emissions WHERE user_id = ?";
    $result = $db->fetch($sql, [$userId]);
    $totalEmission = $result['total_emission'] ?? 0;
    
    // 计算今日碳排放量
    $sql = "SELECT SUM(emission_amount) as daily_emission FROM carbon_emissions WHERE user_id = ? AND emission_date = ?";
    $result = $db->fetch($sql, [$userId, $today]);
    $dailyEmission = $result['daily_emission'] ?? 0;
    
    // 计算总记录数
    $sql = "SELECT COUNT(*) as record_count FROM carbon_emissions WHERE user_id = ?";
    $result = $db->fetch($sql, [$userId]);
    $recordCount = $result['record_count'] ?? 0;
    
    // 计算总能量值
    $sql = "SELECT SUM(carbon_energy) as total_energy FROM energy_transactions WHERE user_id = ? AND transaction_type = 'gain'";
    $result = $db->fetch($sql, [$userId]);
    $totalEnergy = $result['total_energy'] ?? 0;
    
    // 获取最近7天的碳排放数据（用于图表）
    $chartLabels = [];
    $chartValues = [];
    
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $chartLabels[] = date('m-d', strtotime($date));
        
        $sql = "SELECT SUM(emission_amount) as amount FROM carbon_emissions WHERE user_id = ? AND emission_date = ?";
        $result = $db->fetch($sql, [$userId, $date]);
        $amount = $result['amount'] ?? 0;
        $chartValues[] = $amount;
    }
    
    // 组装结果
    $result = [
        'total_emission' => floatval($totalEmission),
        'total_energy' => intval($totalEnergy),
        'daily_emission' => floatval($dailyEmission),
        'record_count' => intval($recordCount),
        'chart_data' => [
            'labels' => $chartLabels,
            'values' => $chartValues
        ]
    ];
    
    echo json_encode([
        'status' => 'success',
        'message' => '获取碳记录统计成功',
        'data' => $result
    ]);
    
} catch (Exception $e) {
    // 记录错误日志
    file_put_contents('../../logs/error.log', date('Y-m-d H:i:s') . ' - 获取碳记录统计失败: ' . $e->getMessage() . "\n", FILE_APPEND);
    
    // 返回错误响应
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => '获取碳记录统计失败: ' . $e->getMessage()
    ]);
}
?>