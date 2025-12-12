<?php
/**
 * 碳森林项目配置文件
 * 适配宝塔面板环境
 */

// 错误报告设置
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 时区设置
date_default_timezone_set('Asia/Shanghai');

// 数据库配置
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'szm2004120');  // 宝塔面板默认密码
define('DB_NAME', 'carbon_forest');
define('DB_CHARSET', 'utf8mb4');

// 项目路径配置
define('ROOT_PATH', dirname(__FILE__));
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('CERTIFICATE_PATH', UPLOAD_PATH . '/certificates');
define('REPORT_PATH', UPLOAD_PATH . '/reports');
define('LOG_PATH', ROOT_PATH . '/logs');

// 网站URL配置（根据实际部署环境修改）
if (isset($_SERVER['HTTP_HOST']) && isset($_SERVER['SCRIPT_NAME'])) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $base_path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    define('SITE_URL', $protocol . $host . $base_path);
} else {
    // 命令行环境下的默认值
    define('SITE_URL', 'http://localhost/carbon_forest');
}

// 安全配置
define('SALT_LENGTH', 32);
define('SESSION_TIMEOUT', 3600); // 1小时
define('JWT_SECRET', 'carbon_forest_jwt_secret_2024'); // JWT密钥

// 碳能量配置
define('ENERGY_PER_KG_REDUCTION', 10); // 每kg减排获得10能量
define('ENERGY_BALL_EXPIRE_HOURS', 24); // 能量球过期时间(小时)
define('OVERFLOW_ENERGY_RATE', 0.5); // 溢出能量比例

// 虚拟载体成长配置
define('STAGE_ENERGY_REQUIREMENTS', [
    1 => 0,      // 种子
    2 => 100,    // 树苗
    3 => 1000,   // 大树
    4 => 5000    // 森林
]);

// 每个阶段的最大能量储存限制
define('STAGE_MAX_ENERGY', [
    1 => 100,    // 种子阶段最多储存100能量
    2 => 1000,   // 幼苗阶段最多储存1000能量
    3 => 5000,   // 大树阶段最多储存5000能量
    4 => 10000   // 森林阶段最多储存10000能量
]);

// 碳排放计算系数 (kg CO₂)
define('CARBON_FACTORS', [
    '算力' => 0.02,      // 1TOPS/小时=0.02kg CO₂
    '出行' => 0.2,       // 每单位活动值
    '购物' => 0.1,       // 每单位活动值
    '饮食' => 0.05,      // 每单位活动值
    '生活' => 0.03       // 每单位活动值
]);

// 基准排放倍数 (行业平均)
define('BASELINE_MULTIPLIER', 1.2);

// 文件上传配置
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);

// 日志配置
define('LOG_LEVEL', 'DEBUG'); // DEBUG, INFO, WARN, ERROR

/**
 * 自动加载类文件
 */
function autoloadClasses($className) {
    $classFile = ROOT_PATH . '/includes/' . $className . '.php';
    if (file_exists($classFile)) {
        require_once $classFile;
    }
}

spl_autoload_register('autoloadClasses');

/**
 * 记录系统日志
 */
function logSystem($type, $action, $details = '', $userId = null) {
    $logFile = LOG_PATH . '/system.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    $logEntry = sprintf(
        "[%s] %s - %s - User:%s - IP:%s - %s - %s\n",
        $timestamp,
        $type,
        $action,
        $userId ?? 'guest',
        $ip,
        $userAgent,
        $details
    );
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * 生成随机盐值
 */
function generateSalt($length = SALT_LENGTH) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * 密码加密（md5+盐值）
 */
function encryptPassword($password, $salt) {
    return md5($password . $salt);
}

/**
 * 验证密码
 */
function verifyPassword($password, $hash, $salt) {
    return encryptPassword($password, $salt) === $hash;
}

/**
 * 返回JSON响应
 */
function jsonResponse($success, $data = [], $message = '') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 检查用户是否登录
 */
function checkLogin() {
    session_start();
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['login_time'])) {
        return false;
    }
    
    // 检查会话是否过期
    if (time() - $_SESSION['login_time'] > SESSION_TIMEOUT) {
        session_destroy();
        return false;
    }
    
    // 更新最后活动时间
    $_SESSION['last_activity'] = time();
    return true;
}

/**
 * 获取客户端IP
 */
function getClientIP() {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

/**
 * 安全过滤输入
 */
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    
    $input = trim($input);
    $input = stripslashes($input);
    $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    return $input;
}

/**
 * 创建目录（如果不存在）
 */
function createDirectory($path) {
    if (!file_exists($path)) {
        mkdir($path, 0755, true);
    }
}

// 创建必要的目录
createDirectory(UPLOAD_PATH);
createDirectory(CERTIFICATE_PATH);
createDirectory(REPORT_PATH);
createDirectory(LOG_PATH);

// 初始化会话（仅在Web请求时）
if (isset($_SERVER['REQUEST_METHOD']) && session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 设置跨域头（开发环境，仅在Web请求时）
if (isset($_SERVER['REQUEST_METHOD'])) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        exit(0);
    }
}

?>