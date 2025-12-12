<?php
require_once 'Database.php';

class UserManager {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * 用户注册
     */
    public function register($username, $password, $email, $userType = '个人', $nickname = '', $carrierType = 'tree') {
        // 输入验证
        if (strlen($username) < 4 || strlen($username) > 20) {
            throw new Exception('用户名长度必须在4-20位之间');
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            throw new Exception('用户名只能包含字母、数字和下划线');
        }
        if (strlen($password) < 8) {
            throw new Exception('密码长度不能少于8位');
        }
        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/', $password)) {
            throw new Exception('密码必须包含大小写字母和数字');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('邮箱格式不正确');
        }

        // 检查用户名是否已存在
        $existingUser = $this->db->querySingle("SELECT * FROM users WHERE username = ?", [$username]);
        if ($existingUser) {
            throw new Exception('用户名已被注册');
        }

        // 检查邮箱是否已存在
        $existingEmail = $this->db->querySingle("SELECT * FROM users WHERE email = ?", [$email]);
        if ($existingEmail) {
            throw new Exception('邮箱已被注册');
        }

        // 密码加密（使用md5+盐值，与数据库设计一致）
        $salt = generateSalt();
        $passwordHash = encryptPassword($password, $salt);
        
        // 生成用户数据
        $userId = $this->db->insert('users', [
            'username' => $username,
            'password_hash' => $passwordHash,
            'salt' => $salt,
            'email' => $email,
            'user_type' => $userType,
            'nickname' => $nickname ?: $username,
            'status' => 'active',
            'registration_time' => date('Y-m-d H:i:s')
        ]);

        if (!$userId) {
            throw new Exception('注册失败，请重试');
        }

        return $userId;
    }

    /**
     * 用户登录
     */
    public function login($loginName, $password, $remember = false) {
        // 验证输入
        if (empty($loginName) || empty($password)) {
            throw new Exception('登录名和密码不能为空');
        }

        // 防暴力破解 - 记录尝试次数
        $attemptKey = 'login_attempt_' . md5($loginName . $_SERVER['REMOTE_ADDR']);
        $attempts = $_SESSION[$attemptKey] ?? 0;
        $lastAttemptTime = $_SESSION[$attemptKey . '_time'] ?? 0;

        // 10分钟内超过5次尝试则锁定
        if ($attempts >= 5 && (time() - $lastAttemptTime) < 600) {
            throw new Exception('登录尝试次数过多，请10分钟后再试');
        }
        // 超过10分钟重置计数
        if ($attempts >= 5 && (time() - $lastAttemptTime) >= 600) {
            unset($_SESSION[$attemptKey]);
            unset($_SESSION[$attemptKey . '_time']);
            $attempts = 0;
        }

        try {
            // 支持用户名/邮箱/手机号登录
            $sql = "SELECT * FROM users WHERE 
                    (username = ? OR email = ? OR phone = ?) 
                    AND status = 'active' LIMIT 1";
            $user = $this->db->querySingle($sql, [
                $loginName,
                $loginName,
                $loginName
            ]);

            if (!$user) {
                throw new Exception('登录名或密码错误');
            }

            // 验证密码（使用md5+盐值，与数据库设计一致）
            if (!verifyPassword($password, $user['password_hash'], $user['salt'])) {
                throw new Exception('登录名或密码错误');
            }

            // 登录成功 - 重置尝试次数
            unset($_SESSION[$attemptKey]);
            unset($_SESSION[$attemptKey . '_time']);

            return $user;
        } catch (Exception $e) {
            // 登录失败 - 增加尝试次数
            $_SESSION[$attemptKey] = $attempts + 1;
            $_SESSION[$attemptKey . '_time'] = time();
            throw $e;
        }
    }

    /**
     * 获取用户信息
     */
    public function getUserInfo($userId) {
        return $this->db->querySingle("SELECT * FROM users WHERE user_id = ?", [$userId]);
    }

    /**
     * 获取用户载体信息
     */
    public function getUserCarrierInfo($userId) {
        return $this->db->querySingle("SELECT * FROM carriers WHERE user_id = ?", [$userId]);
    }
    
    /**
     * 更新用户信息
     */
    public function updateUserInfo($userId, $updateData) {
        // 验证用户存在
        $user = $this->getUserInfo($userId);
        if (!$user) {
            throw new Exception('用户不存在');
        }
        
        // 允许更新的字段
        $allowedFields = ['nickname', 'avatar', 'bio', 'location', 'user_type'];
        $validData = [];
        
        foreach ($updateData as $field => $value) {
            if (in_array($field, $allowedFields)) {
                $validData[$field] = $value;
            }
        }
        
        if (empty($validData)) {
            throw new Exception('没有有效的更新字段');
        }
        
        // 更新用户信息
        return $this->db->update('users', $validData, ['user_id' => $userId]);
    }
}

?>