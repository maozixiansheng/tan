<?php
/**
 * 碳能量管理类
 */
class Energy {
    private $db;
    
    /**
     * 构造函数
     */
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * 获取用户能量信息
     * @param int $userId 用户ID
     * @return array 能量信息
     */
    public function getEnergyInfo($userId) {
        $carbonAccount = $this->db->fetch("SELECT * FROM carbon_accounts WHERE user_id = ?", [$userId]);
        if (!$carbonAccount) {
            throw new Exception("用户碳账户不存在");
        }
        
        return [
            'total_energy' => $carbonAccount['total_energy'],
            'available_energy' => $carbonAccount['available_energy'],
            'carrier_stage' => $carbonAccount['carrier_stage'],
            'carrier_type' => $carbonAccount['carrier_type']
        ];
    }
    
    /**
     * 收取碳能量
     * @param int $userId 用户ID
     * @param float $amount 收取数量
     * @return bool 是否成功
     */
    public function collectEnergy($userId, $amount) {
        try {
            // 检查用户能量是否足够
            $energyInfo = $this->getEnergyInfo($userId);
            if ($energyInfo['available_energy'] < $amount) {
                throw new Exception("可用能量不足");
            }
            
            // 开始事务
            $this->db->beginTransaction();
            
            // 更新用户可用能量
            $this->db->query("UPDATE carbon_accounts SET 
                available_energy = available_energy - ? 
                WHERE user_id = ?", 
                [$amount, $userId]
            );
            
            // 记录能量交易
            $energyData = [
                'user_id' => $userId,
                'type' => 'spend',
                'amount' => $amount,
                'source' => 'energy_collection',
                'description' => "收取碳能量"
            ];
            $this->db->insert('energy_transactions', $energyData);
            
            // 提交事务
            $this->db->commit();
            
            return true;
        } catch (Exception $e) {
            // 回滚事务
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * 转移能量（浇水）
     * @param int $fromUserId 发送用户ID
     * @param int $toUserId 接收用户ID
     * @param float $amount 转移数量
     * @return bool 是否成功
     */
    public function transferEnergy($fromUserId, $toUserId, $amount) {
        try {
            // 检查是否是自己给自己浇水
            if ($fromUserId == $toUserId) {
                throw new Exception("不能给自己浇水");
            }
            
            // 检查发送用户能量是否足够
            $fromEnergyInfo = $this->getEnergyInfo($fromUserId);
            if ($fromEnergyInfo['available_energy'] < $amount) {
                throw new Exception("可用能量不足");
            }
            
            // 检查每日浇水次数限制
            $today = date('Y-m-d');
            $waterCount = $this->db->count("
                SELECT COUNT(*) FROM energy_transactions 
                WHERE user_id = ? AND source = 'watering' AND DATE(transaction_time) = ?
            ", [$fromUserId, $today]);
            
            if ($waterCount >= MAX_WATER_TIMES_PER_DAY) {
                throw new Exception("今日浇水次数已达上限");
            }
            
            // 开始事务
            $this->db->beginTransaction();
            
            // 减少发送用户的能量
            $this->db->query("UPDATE carbon_accounts SET 
                available_energy = available_energy - ? 
                WHERE user_id = ?", 
                [$amount, $fromUserId]
            );
            
            // 增加接收用户的能量
            $this->db->query("UPDATE carbon_accounts SET 
                total_energy = total_energy + ?, 
                available_energy = available_energy + ? 
                WHERE user_id = ?", 
                [$amount, $amount, $toUserId]
            );
            
            // 记录发送用户的能量交易
            $fromEnergyData = [
                'user_id' => $fromUserId,
                'type' => 'spend',
                'amount' => $amount,
                'source' => 'watering',
                'description' => "给用户ID:{$toUserId}浇水"
            ];
            $this->db->insert('energy_transactions', $fromEnergyData);
            
            // 记录接收用户的能量交易
            $toEnergyData = [
                'user_id' => $toUserId,
                'type' => 'gain',
                'amount' => $amount,
                'source' => 'watering_received',
                'description' => "收到用户ID:{$fromUserId}的浇水"
            ];
            $this->db->insert('energy_transactions', $toEnergyData);
            
            // 提交事务
            $this->db->commit();
            
            return true;
        } catch (Exception $e) {
            // 回滚事务
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * 获取能量交易记录
     * @param int $userId 用户ID
     * @param int $limit 限制数量
     * @param int $offset 偏移量
     * @return array 交易记录
     */
    public function getEnergyTransactions($userId, $limit = 10, $offset = 0) {
        // 避免使用SELECT *，只选择必要字段
        $sql = "SELECT transaction_id, type, amount, source, description, transaction_time 
               FROM energy_transactions 
               WHERE user_id = ? 
               ORDER BY transaction_time DESC 
               LIMIT ? OFFSET ?";
        return $this->db->fetchAll($sql, [$userId, $limit, $offset]);
    }
    
    /**
     * 检查并处理溢出能量
     * @param int $userId 用户ID
     * @return float 溢出能量数量
     */
    public function checkOverflowEnergy($userId) {
        // 获取用户24小时前的能量记录
        $twentyFourHoursAgo = date('Y-m-d H:i:s', strtotime('-24 hours'));
        
        // 使用fetch而不是count来获取sum值，提高效率
        $result = $this->db->fetch("SELECT SUM(amount) as total_overflow FROM energy_transactions 
            WHERE user_id = ? AND type = 'gain' AND transaction_time < ?
            AND source != 'watering_received'
        ", [$userId, $twentyFourHoursAgo]);
        
        return $result['total_overflow'] ? $result['total_overflow'] : 0;
    }
    
    /**
     * 帮好友收取溢出能量
     * @param int $helperUserId 帮助者用户ID
     * @param int $ownerUserId 能量所有者用户ID
     * @return float 收取的能量数量
     */
    public function helpCollectOverflowEnergy($helperUserId, $ownerUserId) {
        try {
            // 检查是否是自己帮自己收能量
            if ($helperUserId == $ownerUserId) {
                throw new Exception("不能帮自己收能量");
            }
            
            // 检查是否是好友关系，优化查询条件
            $friendship = $this->db->fetch("SELECT 1 FROM friendships 
                WHERE (user_id = ? AND friend_id = ? AND status = 'accepted') 
                OR (user_id = ? AND friend_id = ? AND status = 'accepted')
            ", [$helperUserId, $ownerUserId, $ownerUserId, $helperUserId]);
            
            if (!$friendship) {
                throw new Exception("只能帮好友收能量");
            }
            
            // 计算溢出能量
            $overflowEnergy = $this->checkOverflowEnergy($ownerUserId);
            if ($overflowEnergy <= 0) {
                throw new Exception("没有可收取的溢出能量");
            }
            
            // 收取比例：帮助者获得30%，所有者保留70%
            $helperGain = round($overflowEnergy * 0.3, 2);
            $ownerKeep = round($overflowEnergy * 0.7, 2);
            
            // 开始事务
            $this->db->beginTransaction();
            
            // 优化所有者能量更新，合并为一个UPDATE语句
            $this->db->query("UPDATE carbon_accounts SET 
                available_energy = available_energy - ? + ? 
                WHERE user_id = ?", 
                [$overflowEnergy, $ownerKeep, $ownerUserId]
            );
            
            // 增加帮助者的能量
            $this->db->query("UPDATE carbon_accounts SET 
                total_energy = total_energy + ?, 
                available_energy = available_energy + ? 
                WHERE user_id = ?", 
                [$helperGain, $helperGain, $helperUserId]
            );
            
            // 记录所有者的能量交易
            $ownerEnergyData = [
                'user_id' => $ownerUserId,
                'type' => 'spend',
                'amount' => $overflowEnergy,
                'source' => 'energy_overflow',
                'description' => "溢出能量被用户ID:{$helperUserId}收取"
            ];
            $this->db->insert('energy_transactions', $ownerEnergyData);
            
            // 记录所有者保留能量的交易
            $ownerKeepData = [
                'user_id' => $ownerUserId,
                'type' => 'gain',
                'amount' => $ownerKeep,
                'source' => 'energy_overflow_keep',
                'description' => "溢出能量保留部分"
            ];
            $this->db->insert('energy_transactions', $ownerKeepData);
            
            // 记录帮助者的能量交易
            $helperEnergyData = [
                'user_id' => $helperUserId,
                'type' => 'gain',
                'amount' => $helperGain,
                'source' => 'help_collect',
                'description' => "帮用户ID:{$ownerUserId}收取溢出能量"
            ];
            $this->db->insert('energy_transactions', $helperEnergyData);
            
            // 提交事务
            $this->db->commit();
            
            return $helperGain;
        } catch (Exception $e) {
            // 回滚事务
            $this->db->rollback();
            throw $e;
        }
    }
}