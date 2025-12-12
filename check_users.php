<?php
/**
 * 检查用户表内容脚本
 */

require_once 'config.php';
require_once 'includes/Database.php';

// 设置错误报告
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    // 获取数据库连接
    $db = Database::getInstance();
    $connection = $db->getConnection();
    
    echo "=== 检查users表内容 ===\n";
    
    // 查询用户表
    $sql = "SELECT user_id, username, email, user_type, registration_time FROM users";
    $stmt = $connection->prepare($sql);
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "用户ID\t用户名\t邮箱\t用户类型\t注册时间\n";
    echo "---\n";
    foreach ($result as $row) {
        echo $row['user_id'] . "\t" . $row['username'] . "\t" . $row['email'] . "\t" . $row['user_type'] . "\t" . $row['registration_time'] . "\n";
    }
    
    echo "\n=== 用户总数: " . count($result) . " ===\n";
    
} catch (Exception $e) {
    echo "❌ 检查用户表失败: " . $e->getMessage() . "\n";
}
?>