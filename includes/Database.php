<?php
/**
 * 数据库操作类
 * 适配MySQL 8.0和宝塔面板环境
 */
class Database {
    private $connection;
    private static $instance = null;
    
    /**
     * 私有构造函数，防止直接实例化
     */
    private function __construct() {
        $this->connect();
    }
    
    /**
     * 获取单例实例
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 建立数据库连接
     */
    private function connect() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ]);
            
            // 数据库连接成功
        } catch (PDOException $e) {
            throw new Exception('数据库连接失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 获取数据库连接
     */
    public function getConnection() {
        return $this->connection;
    }
    
    /**
     * 执行查询并返回所有结果
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception('数据库查询失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 执行查询并返回单行结果
     */
    public function querySingle($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch();
        } catch (PDOException $e) {
            throw new Exception('数据库查询失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 执行插入、更新、删除操作
     */
    public function execute($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            throw new Exception('数据库操作失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 获取最后插入的ID
     */
    public function getLastInsertId() {
        return $this->connection->lastInsertId();
    }
    
    /**
     * 开始事务
     */
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    /**
     * 提交事务
     */
    public function commit() {
        return $this->connection->commit();
    }
    
    /**
     * 回滚事务
     */
    public function rollback() {
        return $this->connection->rollback();
    }
    
    /**
     * 检查是否在事务中
     */
    public function inTransaction() {
        return $this->connection->inTransaction();
    }
    
    /**
     * 检查表是否存在
     */
    public function tableExists($tableName) {
        $sql = "SHOW TABLES LIKE ?";
        $result = $this->query($sql, [$tableName]);
        return count($result) > 0;
    }
    
    /**
     * 获取表结构信息
     */
    public function getTableInfo($tableName) {
        $sql = "DESCRIBE `$tableName`";
        return $this->query($sql);
    }
    
    /**
     * 防止克隆
     */
    private function __clone() {}
    
    /**
     * 防止反序列化
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
    
    /**
     * 插入记录（兼容旧方法）
     */
    public function insert($table, $data) {
        $fields = array_keys($data);
        $values = array_values($data);
        $placeholders = str_repeat('?,', count($fields) - 1) . '?';
        
        $sql = "INSERT INTO `$table` (`" . implode('`, `', $fields) . "`) VALUES ($placeholders)";
        
        $this->execute($sql, $values);
        return $this->getLastInsertId();
    }
    
    /**
     * 更新记录（兼容旧方法）
     */
    public function update($table, $data, $where) {
        $setParts = [];
        $params = [];
        
        foreach ($data as $field => $value) {
            $setParts[] = "`$field` = ?";
            $params[] = $value;
        }
        
        $whereParts = [];
        foreach ($where as $field => $value) {
            $whereParts[] = "`$field` = ?";
            $params[] = $value;
        }
        
        $sql = "UPDATE `$table` SET " . implode(', ', $setParts) . " WHERE " . implode(' AND ', $whereParts);
        
        return $this->execute($sql, $params);
    }
    
    /**
     * 删除记录（兼容旧方法）
     */
    public function delete($table, $where) {
        $whereParts = [];
        $params = [];
        
        foreach ($where as $field => $value) {
            $whereParts[] = "`$field` = ?";
            $params[] = $value;
        }
        
        $sql = "DELETE FROM `$table` WHERE " . implode(' AND ', $whereParts);
        
        return $this->execute($sql, $params);
    }
    
    /**
     * 获取单条记录（兼容旧方法）
     */
    public function fetch($sql, $params = []) {
        return $this->querySingle($sql, $params);
    }
    
    /**
     * 获取多条记录（兼容旧方法）
     */
    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params);
    }
    
    /**
     * 获取记录总数（兼容旧方法）
     */
    public function count($sql, $params = []) {
        $result = $this->querySingle($sql, $params);
        return $result ? intval(current($result)) : 0;
    }
    
    /**
     * 关闭数据库连接
     */
    public function close() {
        $this->connection = null;
    }
}