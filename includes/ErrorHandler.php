<?php
/**
 * 错误处理类
 * 提供详细的错误信息和调试功能
 */

class ErrorHandler {
    private static $debugMode = true;
    
    /**
     * 设置调试模式
     */
    public static function setDebugMode($enabled) {
        self::$debugMode = $enabled;
    }
    
    /**
     * 检查是否处于调试模式
     */
    public static function isDebugMode() {
        return self::$debugMode;
    }
    
    /**
     * 记录详细错误信息
     */
    public static function logError($message, $context = [], $userId = null) {
        $errorInfo = [
            'timestamp' => date('Y-m-d H:i:s'),
            'message' => $message,
            'context' => $context,
            'user_id' => $userId,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown'
        ];
        
        // 记录到系统日志
        logSystem('ERROR', 'ErrorHandler', json_encode($errorInfo, JSON_UNESCAPED_UNICODE), $userId);
        
        return $errorInfo;
    }
    
    /**
     * 生成详细的错误响应
     */
    public static function createErrorResponse($message, $errorInfo = [], $statusCode = 500) {
        http_response_code($statusCode);
        
        $response = [
            'status' => 'error',
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // 调试模式下添加详细信息
        if (self::$debugMode && !empty($errorInfo)) {
            $response['debug'] = $errorInfo;
        }
        
        return $response;
    }
    
    /**
     * 处理数据库错误
     */
    public static function handleDatabaseError($exception, $sql = null, $params = null, $userId = null) {
        $errorInfo = [
            'error_message' => $exception->getMessage(),
            'error_code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'sql_query' => $sql,
            'sql_params' => $params
        ];
        
        // 记录错误
        $loggedError = self::logError('数据库操作失败', $errorInfo, $userId);
        
        // 生成用户友好的错误消息
        $userMessage = '数据库操作失败';
        if (strpos($exception->getMessage(), 'Unknown column') !== false) {
            $userMessage = '数据库字段错误，请联系管理员';
        } elseif (strpos($exception->getMessage(), 'Table') !== false && strpos($exception->getMessage(), 'doesn\'t exist') !== false) {
            $userMessage = '数据库表不存在，请联系管理员';
        }
        
        return self::createErrorResponse($userMessage, $loggedError, 500);
    }
    
    /**
     * 处理API错误
     */
    public static function handleApiError($message, $exception = null, $userId = null) {
        $errorInfo = [];
        
        if ($exception) {
            $errorInfo = [
                'error_message' => $exception->getMessage(),
                'error_code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => self::$debugMode ? $exception->getTrace() : []
            ];
        }
        
        // 记录错误
        $loggedError = self::logError($message, $errorInfo, $userId);
        
        return self::createErrorResponse($message, $loggedError, 500);
    }
    
    /**
     * 验证数据库字段是否存在
     */
    public static function validateDatabaseFields($table, $fields, $db = null) {
        if (!self::$debugMode) {
            return true; // 生产环境跳过验证
        }
        
        try {
            // 使用传入的数据库连接，如果没有则创建新连接
            if ($db === null) {
                $db = Database::getInstance();
            }
            
            // 获取表结构
            $sql = "DESCRIBE {$table}";
            $result = $db->query($sql);
            
            $tableFields = [];
            foreach ($result as $row) {
                $tableFields[] = $row['Field'];
            }
            
            $missingFields = [];
            foreach ($fields as $field) {
                if (!in_array($field, $tableFields)) {
                    $missingFields[] = $field;
                }
            }
            
            if (!empty($missingFields)) {
                $errorMsg = "表 {$table} 中缺少字段: " . implode(', ', $missingFields);
                self::logError('数据库字段验证失败', [
                    'table' => $table,
                    'fields' => $fields,
                    'error' => $errorMsg
                ]);
                throw new Exception($errorMsg);
            }
            
            return true;
        } catch (Exception $e) {
            self::logError('数据库字段验证失败', [
                'table' => $table,
                'fields' => $fields,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * 检查数据库表是否存在
     */
    public static function checkTableExists($table) {
        try {
            $db = Database::getInstance();
            $sql = "SHOW TABLES LIKE '{$table}'";
            $result = $db->querySingle($sql);
            
            return !empty($result);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * 生成数据库健康检查报告
     */
    public static function generateDatabaseHealthReport() {
        if (!self::$debugMode) {
            return ['status' => 'ok', 'message' => '调试模式已禁用'];
        }
        
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'tables' => [],
            'issues' => []
        ];
        
        $requiredTables = [
            'users', 'carbon_accounts', 'carriers', 'energy_balls', 
            'watering_records', 'carbon_emissions'
        ];
        
        foreach ($requiredTables as $table) {
            $exists = self::checkTableExists($table);
            $report['tables'][$table] = $exists ? '存在' : '缺失';
            
            if (!$exists) {
                $report['issues'][] = "表 {$table} 不存在";
            }
        }
        
        return $report;
    }
}

// 设置错误处理函数
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    $errorInfo = [
        'error_number' => $errno,
        'error_string' => $errstr,
        'error_file' => $errfile,
        'error_line' => $errline
    ];
    
    ErrorHandler::logError('PHP错误', $errorInfo);
    
    // 调试模式下显示详细错误
    if (ErrorHandler::isDebugMode()) {
        echo json_encode(ErrorHandler::createErrorResponse($errstr, $errorInfo), JSON_UNESCAPED_UNICODE);
        exit;
    }
});

// 设置异常处理函数
set_exception_handler(function($exception) {
    $errorInfo = [
        'error_message' => $exception->getMessage(),
        'error_code' => $exception->getCode(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => ErrorHandler::isDebugMode() ? $exception->getTrace() : []
    ];
    
    ErrorHandler::logError('未捕获异常', $errorInfo);
    
    // 调试模式下显示详细错误
    if (ErrorHandler::isDebugMode()) {
        echo json_encode(ErrorHandler::createErrorResponse($exception->getMessage(), $errorInfo), JSON_UNESCAPED_UNICODE);
        exit;
    }
});

?>