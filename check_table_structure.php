<?php
/**
 * 检查碳记录表结构脚本
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
    
    echo "=== 检查carbon_emissions表结构 ===\n";
    
    // 查询表结构
    $sql = "DESCRIBE carbon_emissions";
    $stmt = $connection->prepare($sql);
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "字段名\t类型\t空值\t键\t默认值\t额外信息\n";
    echo "---\n";
    foreach ($result as $row) {
        echo $row['Field'] . "\t" . $row['Type'] . "\t" . $row['Null'] . "\t" . $row['Key'] . "\t" . $row['Default'] . "\t" . $row['Extra'] . "\n";
    }
    
    echo "\n=== 检查carbon_accounts表结构 ===\n";
    
    // 查询表结构
    $sql = "DESCRIBE carbon_accounts";
    $stmt = $connection->prepare($sql);
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "字段名\t类型\t空值\t键\t默认值\t额外信息\n";
    echo "---\n";
    foreach ($result as $row) {
        echo $row['Field'] . "\t" . $row['Type'] . "\t" . $row['Null'] . "\t" . $row['Key'] . "\t" . $row['Default'] . "\t" . $row['Extra'] . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ 检查表结构失败: " . $e->getMessage() . "\n";
}
?>