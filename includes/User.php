<?php
/**
 * 用户管理类
 */
class User {
    private $db;
    
    /**
     * 构造函数
     */
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * 生成随机盐值
     * @param int $length 盐值长度
     * @return string 盐值
     */
    private function generateSalt($length = SALT_LENGTH) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $salt = '';
        for ($i = 0; $i < $length; $i++) {
            $salt .= $chars[rand(0, strlen($chars) - 1)];
        }
        return $salt;
    }
    
    /**
     * 密码加密
     * @param string $password 原始密码
     * @param string $salt 盐值
     * @return string 加密后的密码
     */
    private function encryptPassword($password, $salt) {
        return md5($password . $salt);
    }
    
    /**
     * 用户注册
     * @param array $userData 用户数据
     * @return int 用户ID
     */
    public function register($userData) {
        try {
            // 检查用户名是否已存在
            $existingUser = $this->db->fetch("SELECT id FROM users WHERE username = ?", [$userData['username']]);
            if ($existingUser) {
                throw new Exception("用户名已存在");
            }
            
            // 检查邮箱是否已存在
            $existingEmail = $this->db->fetch("SELECT id FROM users WHERE email = ?", [$userData['email']]);
            if ($existingEmail) {
                throw new Exception("邮箱已被注册");
            }
            
            // 检查手机号是否已存在（如果提供）
            if (!empty($userData['phone'])) {
                $existingPhone = $this->db->fetch("SELECT id FROM users WHERE phone = ?", [$userData['phone']]);
                if ($existingPhone) {
                    throw new Exception("手机号已被注册");
                }
            }
            
            // 生成盐值和加密密码
            $salt = $this->generateSalt();
            $encryptedPassword = $this->encryptPassword($userData['password'], $salt);
            
            // 准备用户数据
            $data = [
                'username' => $userData['username'],
                'password' => $encryptedPassword,
                'email' => $userData['email'],
                'phone' => $userData['phone'] ?? null,
                'avatar' => $userData['avatar'] ?? 'default_avatar.png',
                'user_type' => $userData['user_type'] ?? 'personal',
                'salt' => $salt
            ];
            
            // 开始事务
            $this->db->beginTransaction();
            
            // 插入用户数据
            $userId = $this->db->insert('users', $data);
            
            // 创建碳账户
            $carbonAccountData = [
                'user_id' => $userId,
                'total_energy' => 0,
                'available_energy' => 0,
                'carrier_stage' => 1,
                'carrier_type' => $userData['carrier_type'] ?? 'tree'
            ];
            $this->db->insert('carbon_accounts', $carbonAccountData);
            
            // 提交事务
            $this->db->commit();
            
            // 记录注册日志
            logSystem('INFO', '用户注册', "新用户注册成功: {$userData['username']}", $userId);
            
            return $userId;
        } catch (Exception $e) {
            // 回滚事务
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            
            // 记录错误日志
            logSystem('ERROR', '用户注册失败', "错误信息: {$e->getMessage()}", null);
            
            throw $e;
        }
    }
    
    /**
     * 用户登录
     * @param string $username 用户名/邮箱/手机号
     * @param string $password 密码
     * @return array 用户信息
     */
    public function login($username, $password) {
        try {
            // 根据输入判断是用户名、邮箱还是手机号
            $field = is_numeric($username) && strlen($username) >= 10 ? 'phone' : (filter_var($username, FILTER_VALIDATE_EMAIL) ? 'email' : 'username');
            
            // 查询用户信息
            $user = $this->db->fetch("SELECT * FROM users WHERE $field = ?", [$username]);
            
            if (!$user) {
                throw new Exception("用户名或密码错误");
            }
            
            // 验证密码
            if ($this->encryptPassword($password, $user['salt']) !== $user['password']) {
                throw new Exception("用户名或密码错误");
            }
            
            // 更新最后登录时间
            $this->db->update('users', ['last_login' => date('Y-m-d H:i:s')], ['id' => $user['id']]);
            
            // 获取用户碳账户信息
            $carbonAccount = $this->db->fetch("SELECT total_energy, available_energy, carrier_stage, carrier_type FROM carbon_accounts WHERE user_id = ?", [$user['id']]);
            
            // 返回用户信息（不包含敏感数据）
            $userInfo = [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'phone' => $user['phone'],
                'avatar' => $user['avatar'],
                'user_type' => $user['user_type'],
                'create_time' => $user['create_time'],
                'last_login' => date('Y-m-d H:i:s'),
                'total_energy' => $carbonAccount['total_energy'],
                'available_energy' => $carbonAccount['available_energy'],
                'carrier_stage' => $carbonAccount['carrier_stage'],
                'carrier_type' => $carbonAccount['carrier_type']
            ];
            
            // 记录登录日志
            logSystem('INFO', '用户登录', "用户 {$user['username']} 登录成功", $user['id']);
            
            return $userInfo;
        } catch (Exception $e) {
            // 记录登录失败日志
            logSystem('WARN', '用户登录失败', "用户 {$username} 登录失败: {$e->getMessage()}", null);
            
            throw new Exception("用户名或密码错误");
        }
    }
    
    /**
     * 获取用户信息
     * @param int $userId 用户ID
     * @return array 用户信息
     */
    public function getUserInfo($userId) {
        try {
            // 查询用户基本信息（只选择必要字段）
            $user = $this->db->fetch(
                "SELECT id, username, email, phone, avatar, user_type, create_time, last_login 
                 FROM users WHERE id = ?", 
                [$userId]
            );
            
            if (!$user) {
                throw new Exception("用户不存在");
            }
            
            // 获取用户碳账户信息（只选择必要字段）
            $carbonAccount = $this->db->fetch(
                "SELECT total_energy, available_energy, carrier_stage, carrier_type 
                 FROM carbon_accounts WHERE user_id = ?", 
                [$userId]
            );
            
            // 返回用户信息（不包含敏感数据）
            $userInfo = [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'phone' => $user['phone'],
                'avatar' => $user['avatar'],
                'user_type' => $user['user_type'],
                'create_time' => $user['create_time'],
                'last_login' => $user['last_login'],
                'total_energy' => $carbonAccount['total_energy'],
                'available_energy' => $carbonAccount['available_energy'],
                'carrier_stage' => $carbonAccount['carrier_stage'],
                'carrier_type' => $carbonAccount['carrier_type']
            ];
            
            // 记录查询日志
            logSystem('INFO', '用户信息查询', "查询用户 {$userId} 的详细信息", $userId);
            
            return $userInfo;
        } catch (Exception $e) {
            // 记录错误日志
            logSystem('ERROR', '用户信息查询失败', "用户 {$userId} 信息查询失败: {$e->getMessage()}", $userId);
            
            throw new Exception("获取用户信息失败: {$e->getMessage()}");
        }
    }
    
    /**
     * 更新用户信息
     * @param int $userId 用户ID
     * @param array $userData 用户数据
     * @return bool 是否成功
     */
    public function updateUserInfo($userId, $userData) {
        try {
            // 检查用户是否存在
            $user = $this->db->fetch("SELECT * FROM users WHERE id = ?", [$userId]);
            if (!$user) {
                throw new Exception("用户不存在");
            }
            
            // 准备更新数据
            $updateData = [];
            
            // 更新邮箱（如果提供）
            if (isset($userData['email']) && $userData['email'] !== $user['email']) {
                // 检查邮箱是否已被其他用户使用
                $existingEmail = $this->db->fetch("SELECT id FROM users WHERE email = ? AND id != ?", [$userData['email'], $userId]);
                if ($existingEmail) {
                    throw new Exception("邮箱已被其他用户注册");
                }
                $updateData['email'] = $userData['email'];
            }
            
            // 更新手机号（如果提供）
            if (isset($userData['phone']) && $userData['phone'] !== $user['phone']) {
                // 检查手机号是否已被其他用户使用
                if (!empty($userData['phone'])) {
                    $existingPhone = $this->db->fetch("SELECT id FROM users WHERE phone = ? AND id != ?", [$userData['phone'], $userId]);
                    if ($existingPhone) {
                        throw new Exception("手机号已被其他用户注册");
                    }
                }
                $updateData['phone'] = $userData['phone'];
            }
            
            // 更新用户类型（如果提供）
            if (isset($userData['user_type'])) {
                $updateData['user_type'] = $userData['user_type'];
            }
            
            // 如果有数据需要更新
            if (!empty($updateData)) {
                $this->db->update('users', $updateData, ['id' => $userId]);
            }
            
            // 更新密码（如果提供）
            if (isset($userData['password']) && !empty($userData['password'])) {
                $salt = $this->generateSalt();
                $encryptedPassword = $this->encryptPassword($userData['password'], $salt);
                $this->db->update('users', ['password' => $encryptedPassword, 'salt' => $salt], ['id' => $userId]);
            }
            
            // 更新头像（如果提供）
            if (isset($userData['avatar'])) {
                $this->db->update('users', ['avatar' => $userData['avatar']], ['id' => $userId]);
            }
            
            return true;
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * 上传头像
     * @param int $userId 用户ID
     * @param array $file 文件信息
     * @return string 头像URL
     */
    public function uploadAvatar($userId, $file) {
        try {
            // 检查文件是否有效
            if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
                throw new Exception("无效的文件");
            }
            
            // 检查文件大小
            if ($file['size'] > MAX_UPLOAD_SIZE) {
                throw new Exception("文件大小超过限制");
            }
            
            // 检查文件类型
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($file['type'], $allowedTypes)) {
                throw new Exception("只支持JPG、PNG、GIF格式的图片");
            }
            
            // 创建目标目录
            $targetDir = UPLOAD_PATH . '/avatars';
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }
            
            // 生成文件名
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $fileName = 'avatar_' . $userId . '_' . time() . '.' . $extension;
            $targetPath = $targetDir . '/' . $fileName;
            
            // 移动文件
            if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                throw new Exception("文件上传失败");
            }
            
            // 更新用户头像
            $avatarUrl = 'avatars/' . $fileName;
            $this->db->update('users', ['avatar' => $avatarUrl], ['id' => $userId]);
            
            return $avatarUrl;
        } catch (Exception $e) {
            throw $e;
        }
    }
}