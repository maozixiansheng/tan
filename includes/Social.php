<?php
/**
 * 社交互动管理类
 */
class Social {
    private $db;
    
    /**
     * 构造函数
     */
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * 添加好友
     * @param int $userId 用户ID
     * @param int $friendId 好友ID
     * @return bool 是否成功
     */
    public function addFriend($userId, $friendId) {
        try {
            // 检查是否是自己
            if ($userId == $friendId) {
                throw new Exception("不能添加自己为好友");
            }
            
            // 检查好友是否存在
            $friend = $this->db->fetch("SELECT id FROM users WHERE id = ?", [$friendId]);
            if (!$friend) {
                throw new Exception("用户不存在");
            }
            
            // 检查是否已经是好友
            $friendship = $this->db->fetch("
                SELECT * FROM friendships 
                WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)
            ", [$userId, $friendId, $friendId, $userId]);
            
            if ($friendship) {
                if ($friendship['status'] == 'accepted') {
                    throw new Exception("已经是好友");
                } else {
                    throw new Exception("好友请求已发送");
                }
            }
            
            // 发送好友请求
            $data = [
                'user_id' => $userId,
                'friend_id' => $friendId,
                'status' => 'pending'
            ];
            
            $this->db->insert('friendships', $data);
            
            return true;
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * 接受好友请求
     * @param int $userId 用户ID
     * @param int $friendId 好友ID
     * @return bool 是否成功
     */
    public function acceptFriendRequest($userId, $friendId) {
        try {
            // 检查好友请求是否存在
            $friendship = $this->db->fetch("
                SELECT * FROM friendships 
                WHERE user_id = ? AND friend_id = ? AND status = 'pending'
            ", [$friendId, $userId]);
            
            if (!$friendship) {
                throw new Exception("好友请求不存在");
            }
            
            // 接受好友请求
            $this->db->update('friendships', 
                ['status' => 'accepted'], 
                ['id' => $friendship['id']]
            );
            
            return true;
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * 拒绝好友请求
     * @param int $userId 用户ID
     * @param int $friendId 好友ID
     * @return bool 是否成功
     */
    public function rejectFriendRequest($userId, $friendId) {
        try {
            // 检查好友请求是否存在
            $friendship = $this->db->fetch("
                SELECT * FROM friendships 
                WHERE user_id = ? AND friend_id = ? AND status = 'pending'
            ", [$friendId, $userId]);
            
            if (!$friendship) {
                throw new Exception("好友请求不存在");
            }
            
            // 删除好友请求
            $this->db->delete('friendships', ['id' => $friendship['id']]);
            
            return true;
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * 删除好友
     * @param int $userId 用户ID
     * @param int $friendId 好友ID
     * @return bool 是否成功
     */
    public function deleteFriend($userId, $friendId) {
        try {
            // 检查好友关系是否存在
            $friendship = $this->db->fetch("
                SELECT * FROM friendships 
                WHERE ((user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?))
                AND status = 'accepted'
            ", [$userId, $friendId, $friendId, $userId]);
            
            if (!$friendship) {
                throw new Exception("好友关系不存在");
            }
            
            // 删除好友关系
            $this->db->delete('friendships', ['id' => $friendship['id']]);
            
            return true;
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * 获取好友列表
     * @param int $userId 用户ID
     * @return array 好友列表
     */
    public function getFriendList($userId) {
        $sql = "
            SELECT u.id, u.username, u.avatar, u.user_type, 
                   ca.total_energy, ca.carrier_stage, ca.carrier_type
            FROM friendships f
            JOIN users u ON (f.user_id = u.id OR f.friend_id = u.id)
            JOIN carbon_accounts ca ON u.id = ca.user_id
            WHERE (f.user_id = ? OR f.friend_id = ?) 
            AND u.id != ?
            AND f.status = 'accepted'
            ORDER BY u.username
        ";
        
        return $this->db->fetchAll($sql, [$userId, $userId, $userId]);
    }
    
    /**
     * 获取好友请求列表
     * @param int $userId 用户ID
     * @return array 好友请求列表
     */
    public function getFriendRequests($userId) {
        $sql = "
            SELECT u.id, u.username, u.avatar, u.user_type, f.create_time
            FROM friendships f
            JOIN users u ON f.user_id = u.id
            WHERE f.friend_id = ? AND f.status = 'pending'
            ORDER BY f.create_time DESC
        ";
        
        return $this->db->fetchAll($sql, [$userId]);
    }
    
    /**
     * 搜索用户
     * @param int $userId 当前用户ID
     * @param string $keyword 搜索关键词
     * @return array 用户列表
     */
    public function searchUsers($userId, $keyword) {
        $sql = "
            SELECT u.id, u.username, u.avatar, u.user_type
            FROM users u
            WHERE u.id != ?
            AND (u.username LIKE ? OR u.email LIKE ?)
            LIMIT 20
        ";
        
        $likeKeyword = "%{$keyword}%";
        return $this->db->fetchAll($sql, [$userId, $likeKeyword, $likeKeyword]);
    }
    
    /**
     * 获取排行榜
     * @param string $type 排行类型（energy/carrier）
     * @param string $userType 用户类型（可选）
     * @param int $limit 限制数量
     * @return array 排行榜
     */
    public function getRanking($type = 'energy', $userType = null, $limit = 10) {
        $sql = "
            SELECT u.id, u.username, u.avatar, u.user_type, 
                   ca.total_energy, ca.carrier_stage, ca.carrier_type
            FROM users u
            JOIN carbon_accounts ca ON u.id = ca.user_id
            WHERE 1=1
        ";
        
        $params = [];
        
        if ($userType) {
            $sql .= " AND u.user_type = ?";
            $params[] = $userType;
        }
        
        if ($type == 'energy') {
            $sql .= " ORDER BY ca.total_energy DESC";
        } else {
            $sql .= " ORDER BY ca.carrier_stage DESC, ca.total_energy DESC";
        }
        
        $sql .= " LIMIT ?";
        $params[] = $limit;
        
        return $this->db->fetchAll($sql, $params);
    }
}