<?php
/**
 * 用户信息API接口
 */

require_once '../../config.php';
require_once '../../includes/Database.php';
require_once '../../includes/UserManager.php';

// 设置响应头
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT');
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

// 根据请求方法处理
switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        // 获取用户信息
        try {
            $userManager = new UserManager();
            
            // 获取用户基本信息
            $userInfo = $userManager->getUserInfo($userId);
            
            // 获取碳账户信息作为能量信息
            $db = Database::getInstance();
            $carbonAccount = $db->querySingle("SELECT * FROM carbon_accounts WHERE user_id = ?", [$userId]);
            
            if (!$carbonAccount) {
                throw new Exception('用户碳账户不存在');
            }
            
            $energyInfo = [
                'total_energy' => $carbonAccount['total_energy'],
                'current_energy' => $carbonAccount['current_energy'],
                'level' => $carbonAccount['level'],
                'experience' => $carbonAccount['experience'],
                'carbon_footprint' => $carbonAccount['carbon_footprint'],
                'carbon_reduction' => $carbonAccount['carbon_reduction']
            ];
            
            // 获取载体信息
            require_once '../../includes/Carrier.php';
            $carrier = new Carrier();
            $carrierInfo = $carrier->getCarrierInfo($userId);
            
            // 合并返回数据
            $response = [
                'status' => 'success',
                'user' => $userInfo,
                'energy' => $energyInfo,
                'carrier' => $carrierInfo
            ];
            
            echo json_encode($response);
            
        } catch (Exception $e) {
            logSystem('获取用户信息失败: ' . $e->getMessage(), 'error');
            echo json_encode(['status' => 'error', 'message' => '获取用户信息失败']);
        }
        break;
        
    case 'PUT':
        // 更新用户信息
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            $userManager = new UserManager();
            
            // 允许更新的字段
            $allowedFields = ['nickname', 'avatar', 'bio', 'location', 'user_type'];
            $updateData = [];
            
            foreach ($allowedFields as $field) {
                if (isset($input[$field])) {
                    $updateData[$field] = $input[$field];
                }
            }
            
            if (empty($updateData)) {
                echo json_encode(['status' => 'error', 'message' => '没有可更新的数据']);
                exit;
            }
            
            // 更新用户信息
            $result = $userManager->updateUserInfo($userId, $updateData);
            
            if ($result) {
                echo json_encode(['status' => 'success', 'message' => '用户信息更新成功']);
            } else {
                echo json_encode(['status' => 'error', 'message' => '用户信息更新失败']);
            }
            
        } catch (Exception $e) {
            logSystem('更新用户信息失败: ' . $e->getMessage(), 'error');
            echo json_encode(['status' => 'error', 'message' => '更新用户信息失败']);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => '不支持的请求方法']);
        break;
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