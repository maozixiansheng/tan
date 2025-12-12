<?php
/**
 * 能量管理类
 * 处理用户能量、能量球、浇水等操作
 */

require_once 'ErrorHandler.php';
require_once __DIR__ . '/../config.php';

class EnergyManager {
    private $db;
    
    // 常量定义
    const DEFAULT_CARRIER_ID = 1;
    const ENERGY_BALL_EXPIRE_HOURS = 24; // 能量球过期时间（小时）
    const MAX_ENERGY_BALLS = 5; // 最大能量球数量
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * 计算碳排放和碳能量
     */
    public function calculateCarbonEnergy($userId, $activityType, $activityValue, $deviceTops = null) {
        // 验证输入
        if (!isset(CARBON_FACTORS[$activityType])) {
            throw new Exception('不支持的碳排放类型');
        }
        
        if ($activityValue <= 0) {
            throw new Exception('活动值必须大于0');
        }
        
        // 计算实际碳排放
        $actualEmission = $activityValue * CARBON_FACTORS[$activityType];
        
        // 如果是算力类型，使用设备TOPS计算
        if ($activityType === '算力' && $deviceTops !== null) {
            $actualEmission = $deviceTops * $activityValue * CARBON_FACTORS[$activityType];
        }
        
        // 计算基准碳排放（行业平均）
        $baselineEmission = $actualEmission * BASELINE_MULTIPLIER;
        
        // 计算碳减排量
        $carbonReduction = max(0, $baselineEmission - $actualEmission);
        
        // 计算获得的碳能量
        $energyGained = $carbonReduction * ENERGY_PER_KG_REDUCTION;
        
        // 记录碳排放
        $recordData = [
            'user_id' => $userId,
            'emission_type' => $activityType,
            'emission_amount' => $actualEmission,
            'description' => "{$activityType}活动，值：{$activityValue}",
            'emission_date' => date('Y-m-d'),
            'record_time' => date('Y-m-d H:i:s'),
            'is_verified' => 1
        ];
        
        try {
            $this->db->beginTransaction();
            
            // 插入碳排放记录
            $this->db->insert('carbon_emissions', $recordData);
            
            // 更新碳账户
            $this->updateCarbonAccount($userId, $carbonReduction, $energyGained);
            
            // 生成能量球（使用加权随机算法，只生成5、10、15三种值）
            $energyBallAmount = $this->getWeightedEnergyAmount();
            $this->generateEnergyBall($userId, $energyBallAmount);
            
            $this->db->commit();
            
            logSystem('INFO', 'Carbon energy calculated', 
                "用户 {$userId} 碳排放计算: 类型={$activityType}, 值={$activityValue}, 能量={$energyGained}", 
                $userId);
            
            return [
                'actual_emission' => round($actualEmission, 2),
                'baseline_emission' => round($baselineEmission, 2),
                'carbon_reduction' => round($carbonReduction, 2),
                'energy_gained' => round($energyGained, 2)
            ];
        } catch (Exception $e) {
            $this->db->rollback();
            logSystem('ERROR', 'Carbon calculation failed', $e->getMessage(), $userId);
            throw new Exception('碳排放计算失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 更新用户碳账户
     * @return bool 是否发生了载体升级
     */
    private function updateCarbonAccount($userId, $carbonReduction, $energyGained) {
        try {
            // 验证数据库字段
            ErrorHandler::validateDatabaseFields('carbon_accounts', ['user_id', 'carbon_reduction', 'current_energy', 'total_energy', 'last_update_time'], $this->db);
            
            // 获取用户当前能量和载体信息
            $userEnergy = $this->getUserEnergy($userId);
            $maxEnergy = $this->getUserMaxEnergy($userId);
            
            // 计算实际可增加的能量（不超过最大限制）
            $actualEnergyIncrease = min($energyGained, $maxEnergy - $userEnergy);
            
            if ($actualEnergyIncrease <= 0) {
                // 能量已达上限，记录日志但不增加能量
                logSystem('INFO', 'User energy limit reached', "用户 {$userId} 当前阶段能量已达上限 {$maxEnergy}，不再增加能量", $userId);
                $actualEnergyIncrease = 0;
            }
            
            $sql = "UPDATE carbon_accounts 
                    SET carbon_reduction = carbon_reduction + ?, 
                        current_energy = current_energy + ?, 
                        total_energy = total_energy + ?, 
                        last_update_time = NOW() 
                    WHERE user_id = ?";
            
            $this->db->execute($sql, [$carbonReduction, $actualEnergyIncrease, $actualEnergyIncrease, $userId]);
            
            // 检查是否需要升级（用户等级和载体）
            $this->checkLevelUp($userId);
            $carrierUpgraded = $this->checkCarrierUpgrade($userId);
            
            return $carrierUpgraded;
            
        } catch (Exception $e) {
            // 记录详细错误信息
            $errorInfo = ErrorHandler::handleDatabaseError($e, $sql ?? null, [$carbonReduction, $energyGained, $energyGained, $userId], $userId);
            
            throw new Exception('碳账户更新失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 检查用户等级升级
     */
    private function checkLevelUp($userId) {
        $sql = "SELECT level, total_energy FROM carbon_accounts WHERE user_id = ?";
        $account = $this->db->querySingle($sql, [$userId]);
        
        if (!$account) return;
        
        $currentLevel = $account['level'];
        $totalEnergy = $account['total_energy'];
        
        // 定义等级升级阈值（可根据需要调整）
        $levelThresholds = [
            1 => 0,      // 初始等级
            2 => 100,    // 升级到2级需要100能量
            3 => 500,    // 升级到3级需要500能量
            4 => 2000,   // 升级到4级需要2000能量
            5 => 5000    // 升级到5级需要5000能量
        ];
        
        $newLevel = $currentLevel;
        foreach ($levelThresholds as $level => $threshold) {
            if ($totalEnergy >= $threshold && $level > $currentLevel) {
                $newLevel = $level;
            }
        }
        
        if ($newLevel > $currentLevel) {
            $this->db->update('carbon_accounts', 
                ['level' => $newLevel, 'last_update_time' => date('Y-m-d H:i:s')], 
                ['user_id' => $userId]);
            
            logSystem('INFO', 'User level up', "用户 {$userId} 升级到 {$newLevel} 级", $userId);
        }
    }
    
    /**
     * 检查载体是否需要升级
     * @return bool 是否发生了升级
     */
    private function checkCarrierUpgrade($userId) {
        try {
            // 引入Carrier类
            require_once __DIR__ . '/Carrier.php';
            
            // 传入当前数据库连接实例，确保事务在同一连接上执行
            $carrierObj = new Carrier($this->db);
            
            // 获取载体信息
            $carrierInfo = $carrierObj->getCarrierInfo($userId);
            
            // 检查是否可以升级
            if ($carrierInfo['can_upgrade']) {
                // 执行升级
                $upgradeResult = $carrierObj->upgradeCarrier($userId);
                
                logSystem('INFO', 'Carrier auto-upgraded', "用户 {$userId} 的载体自动升级成功: " . json_encode($upgradeResult), $userId);
                return true; // 发生了升级
            }
        } catch (Exception $e) {
            logSystem('DEBUG', 'Carrier upgrade check failed', "用户 {$userId} 载体升级检查失败: " . $e->getMessage(), $userId);
            // 不抛出异常，避免影响能量更新
        }
        return false; // 未发生升级
    }
    
    /**
     * 生成能量球
     */
    private function generateEnergyBall($userId, $energyAmount) {
        try {
            // 检查用户当前能量球数量是否超过限制（最多5个）
            $countSql = "SELECT COUNT(*) as count FROM energy_balls 
                         WHERE user_id = ? AND status = 'available' AND expire_time > NOW()";
            $countResult = $this->db->querySingle($countSql, [$userId]);
            
            if ($countResult['count'] >= self::MAX_ENERGY_BALLS) {
                throw new Exception('能量球数量已达上限（最多' . self::MAX_ENERGY_BALLS . '个）');
            }
            
            $ballData = [
                'user_id' => $userId,
                'energy_amount' => $energyAmount,
                'location_lat' => rand(10, 90) / 100.0, // 随机纬度（小数格式）
                'location_lng' => rand(10, 90) / 100.0, // 随机经度（小数格式）
                'create_time' => date('Y-m-d H:i:s'),
                'expire_time' => date('Y-m-d H:i:s', strtotime('+' . self::ENERGY_BALL_EXPIRE_HOURS . ' hours')),
                'status' => 'available'
            ];
            
            $this->db->insert('energy_balls', $ballData);
            
            logSystem('INFO', 'Energy ball generated', "用户 {$userId} 生成能量球: {$energyAmount} 能量", $userId);
            
        } catch (Exception $e) {
            logSystem('ERROR', 'Energy ball generation failed', $e->getMessage(), $userId);
            throw new Exception('生成能量球失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 收取能量球
     * @return array 包含能量值和升级状态的数组
     */
    public function collectEnergyBall($userId, $ballId) {
        try {
            $this->db->beginTransaction();
            
            // 验证数据库字段
            ErrorHandler::validateDatabaseFields('energy_balls', ['ball_id', 'user_id', 'energy_amount', 'status', 'expire_time', 'collect_time'], $this->db);
            ErrorHandler::validateDatabaseFields('carbon_accounts', ['user_id', 'carbon_reduction', 'current_energy', 'total_energy', 'last_update_time'], $this->db);
            
            // 检查能量球是否存在且可收取
            $sql = "SELECT * FROM energy_balls WHERE ball_id = ? AND user_id = ? AND status = 'available' AND expire_time > NOW()";
            $ball = $this->db->querySingle($sql, [$ballId, $userId]);
            
            if (!$ball) {
                throw new Exception('能量球不存在或已过期');
            }
            
            // 更新能量球状态
            $this->db->update('energy_balls', 
                ['status' => 'collected', 'collect_time' => date('Y-m-d H:i:s')], 
                ['ball_id' => $ballId]);
            
            // 更新用户能量并检查是否升级
            $carrierUpgraded = $this->updateCarbonAccount($userId, 0, $ball['energy_amount']);
            
            $this->db->commit();
            
            logSystem('INFO', 'Energy ball collected', "用户 {$userId} 收取能量球: {$ball['energy_amount']} 能量", $userId);
            
            return [
                'energy_amount' => $ball['energy_amount'],
                'carrier_upgraded' => $carrierUpgraded
            ];
        } catch (Exception $e) {
            // 只有在事务中才回滚
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            
            // 记录详细错误信息
            $errorInfo = ErrorHandler::handleDatabaseError($e, $sql ?? null, [$ballId, $userId], $userId);
            
            logSystem('ERROR', 'Energy collection failed', $e->getMessage(), $userId);
            throw new Exception('能量收取失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 收取所有可收取的能量球
     */
    public function collectAllEnergyBalls($userId) {
        try {
            $this->db->beginTransaction();
            
            // 验证数据库字段
            ErrorHandler::validateDatabaseFields('energy_balls', ['ball_id', 'user_id', 'energy_amount', 'status', 'expire_time', 'collect_time'], $this->db);
            
            // 获取所有可收取的能量球
            $sql = "SELECT ball_id, energy_amount FROM energy_balls 
                    WHERE user_id = ? AND status = 'available' AND expire_time > NOW()";
            $balls = $this->db->query($sql, [$userId]);
            
            if (empty($balls)) {
                throw new Exception('没有可收取的能量球');
            }
            
            $totalEnergy = 0;
            $ballIds = [];
            
            foreach ($balls as $ball) {
                $totalEnergy += $ball['energy_amount'];
                $ballIds[] = $ball['ball_id'];
            }
            
            // 批量更新能量球状态
            $placeholders = str_repeat('?,', count($ballIds) - 1) . '?'; 
            $updateSql = "UPDATE energy_balls SET status = 'collected', collect_time = NOW() 
                          WHERE ball_id IN ($placeholders)";
            $this->db->execute($updateSql, $ballIds);
            
            // 更新用户能量
            $this->updateCarbonAccount($userId, 0, $totalEnergy);
            
            $this->db->commit();
            
            logSystem('INFO', 'All energy balls collected', "用户 {$userId} 批量收取能量: {$totalEnergy} 能量", $userId);
            
            return ['count' => count($balls), 'total_energy' => $totalEnergy];
        } catch (Exception $e) {
            // 只有在事务中才回滚
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            
            // 记录详细错误信息
            $errorInfo = ErrorHandler::handleDatabaseError($e, $sql ?? null, [$userId], $userId);
            
            logSystem('ERROR', 'Bulk energy collection failed', $e->getMessage(), $userId);
            throw new Exception('批量收取能量失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 获取用户当前最大能量限制
     */
    private function getUserMaxEnergy($userId) {
        // 获取用户当前载体阶段
        $carrierSql = "SELECT current_stage FROM carriers 
                       WHERE user_id = ? AND carrier_type = '碳汇树' 
                       ORDER BY create_time DESC LIMIT 1";
        $carrier = $this->db->querySingle($carrierSql, [$userId]);
        
        if ($carrier) {
            $currentStage = $carrier['current_stage'];
            return STAGE_MAX_ENERGY[$currentStage] ?? 10000; // 默认上限10000
        }
        
        // 默认返回无限制
        return 10000;
    }
    
    /**
     * 获取阶段名称
     * @param int $stage 阶段编号
     * @return string 阶段名称
     */
    private function getStageName($stage) {
        static $stageNames = [
            1 => '种子',
            2 => '小树苗', 
            3 => '大树',
            4 => '参天大树',
            5 => '古树'
        ];
        return $stageNames[$stage] ?? '未知阶段';
    }
    
    /**
     * 获取用户能量球列表
     */
    public function getUserEnergyBalls($userId) {
        // 先处理过期能量球
        $this->processExpiredEnergyBalls();
        
        // 获取用户当前可收取的能量球（最多5个）
        $sql = "SELECT ball_id, user_id, energy_amount, location_lat, location_lng, create_time, expire_time, status 
                FROM energy_balls 
                WHERE user_id = ? AND status = 'available' AND expire_time > NOW() 
                ORDER BY create_time DESC 
                LIMIT " . self::MAX_ENERGY_BALLS;
        
        return $this->db->query($sql, [$userId]);
    }
    
    /**
     * 为用户生成新的能量球（公开方法）
     */
    public function generateNewEnergyBall($userId, $energyAmount = null) {
        try {
            // 如果未指定能量值，使用加权随机算法生成（只生成5、10、15三种值）
            if ($energyAmount === null) {
                $energyAmount = $this->getWeightedEnergyAmount();
            }
            $this->generateEnergyBall($userId, $energyAmount);
        } catch (Exception $e) {
            logSystem('ERROR', 'Generate new energy ball failed', $e->getMessage(), $userId);
            throw new Exception('生成能量球失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 处理过期能量球（变为溢出能量）
     */
    public function processExpiredEnergyBalls() {
        $sql = "SELECT ball_id, user_id, energy_amount FROM energy_balls 
                WHERE status = 'available' AND expire_time <= NOW()";
        
        $expiredBalls = $this->db->query($sql);
        
        if (empty($expiredBalls)) {
            return 0;
        }
        
        $processedCount = 0;
        
        foreach ($expiredBalls as $ball) {
            try {
                $this->db->beginTransaction();
                
                // 计算溢出能量（按比例减少）
                $overflowEnergy = $ball['energy_amount'] * OVERFLOW_ENERGY_RATE;
                
                // 更新能量球状态为过期
                $this->db->update('energy_balls', 
                    ['status' => 'expired', 'overflow_energy' => $overflowEnergy], 
                    ['ball_id' => $ball['ball_id']]);
                
                // 记录溢出能量（好友可以收取）
                $overflowData = [
                    'user_id' => $ball['user_id'],
                    'original_ball_id' => $ball['ball_id'],
                    'energy_amount' => $overflowEnergy,
                    'created_at' => date('Y-m-d H:i:s')
                ];
                $this->db->insert('overflow_energy', $overflowData);
                
                $this->db->commit();
                $processedCount++;
                
                logSystem('INFO', 'Energy ball expired', "能量球过期处理: 用户 {$ball['user_id']}, 溢出能量 {$overflowEnergy}");
            } catch (Exception $e) {
                $this->db->rollback();
                logSystem('ERROR', 'Expired energy processing failed', $e->getMessage());
            }
        }
        
        return $processedCount;
    }
    
    /**
     * 浇水功能
     */
    public function waterCarrier($userId) {
        try {
            $this->db->beginTransaction();
            
            // 移除浇水的能量消耗限制
            $energyCost = 0;
            
            // 检查用户当前能量球数量
            $countSql = "SELECT COUNT(*) as count FROM energy_balls 
                         WHERE user_id = ? AND status = 'available' AND expire_time > NOW()";
            $countResult = $this->db->querySingle($countSql, [$userId]);
            
            $ballsGenerated = 0;
            
            // 如果能量球数量少于最大限制，生成1-3个能量球
            if ($countResult['count'] < self::MAX_ENERGY_BALLS) {
                $ballsToGenerate = min(3, self::MAX_ENERGY_BALLS - $countResult['count']);
                
                for ($i = 0; $i < $ballsToGenerate; $i++) {
                    try {
                        // 使用加权随机算法：5的概率最大，最高15
                        $energyAmount = $this->getWeightedEnergyAmount();
                        $this->generateEnergyBall($userId, $energyAmount);
                        $ballsGenerated++;
                    } catch (Exception $e) {
                        // 如果生成失败（比如达到上限），继续处理
                        continue;
                    }
                }
            }
            
            // 获取用户的实际载体ID
            $carrierSql = "SELECT carrier_id FROM carriers 
                           WHERE user_id = ? AND carrier_type = '碳汇树' 
                           ORDER BY create_time DESC LIMIT 1";
            $carrier = $this->db->querySingle($carrierSql, [$userId]);
            
            // 记录浇水
            $wateringData = [
                'user_id' => $userId,
                'carrier_id' => $carrier ? $carrier['carrier_id'] : self::DEFAULT_CARRIER_ID, // 使用实际载体ID，如果没有则使用默认值
                'water_amount' => 1,
                'watering_time' => date('Y-m-d H:i:s'),
                'energy_cost' => $energyCost,
                'growth_increase' => 1.00
            ];
            $this->db->insert('watering_records', $wateringData);
            
            $this->db->commit();
            
            logSystem('INFO', 'Carrier watered', "用户 {$userId} 浇水（无能量消耗），生成 {$ballsGenerated} 个能量球", $userId);
            
            return ['balls_generated' => $ballsGenerated, 'energy_cost' => $energyCost];
            
        } catch (Exception $e) {
            $this->db->rollback();
            logSystem('ERROR', 'Watering failed', $e->getMessage(), $userId);
            throw new Exception('浇水失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 获取用户当前能量
     */
    public function getUserEnergy($userId) {
        try {
            $sql = "SELECT current_energy FROM carbon_accounts WHERE user_id = ?";
            $result = $this->db->querySingle($sql, [$userId]);
            
            if (!$result) {
                return 0;
            }
            
            return $result['current_energy'];
        } catch (Exception $e) {
            logSystem('ERROR', 'Failed to get user energy', $e->getMessage(), $userId);
            return 0;
        }
    }
    
    /**
     * 获取加权随机能量值（只生成5、10、15三种值，5的概率最大）
     */
    private function getWeightedEnergyAmount() {
        // 定义能量值和对应的权重（5的概率最大，10和15的概率较小）
        $energyWeights = [
            5 => 60,   // 60%概率
            10 => 30,  // 30%概率  
            15 => 10   // 10%概率
        ];
        
        // 计算总权重
        $totalWeight = array_sum($energyWeights);
        
        // 生成随机数
        $random = mt_rand(1, $totalWeight);
        
        // 根据权重选择能量值
        $currentWeight = 0;
        foreach ($energyWeights as $energy => $weight) {
            $currentWeight += $weight;
            if ($random <= $currentWeight) {
                return $energy;
            }
        }
        
        // 默认返回5
        return 5;
    }
    
    /**
     * 获取用户能量统计
     */
    public function getUserEnergyStats($userId) {
        try {
            $stats = [];
            
            // 获取碳账户信息
            $accountSql = "SELECT current_energy, total_energy, level, carbon_reduction FROM carbon_accounts WHERE user_id = ?";
            $account = $this->db->querySingle($accountSql, [$userId]);
            $stats['account'] = $account;
            
            // 获取虚拟载体信息
            $carrierSql = "SELECT current_stage, stage_name, growth_progress FROM carriers 
                           WHERE user_id = ? AND carrier_type = '碳汇树' 
                           ORDER BY create_time DESC LIMIT 1";
            $carrier = $this->db->querySingle($carrierSql, [$userId]);
            $stats['carrier'] = $carrier;
            
            // 获取可收取能量球数量
            $ballSql = "SELECT COUNT(*) as count, SUM(energy_amount) as total 
                        FROM energy_balls 
                        WHERE user_id = ? AND status = 'available' AND expire_time > NOW()";
            $balls = $this->db->querySingle($ballSql, [$userId]);
            $stats['available_balls'] = $balls;
            
            // 获取今日获得的能量（从能量球收取记录和任务完成记录计算）
            $todayEnergy = 0;
            
            // 从能量球收取记录获取今日能量
            $ballEnergySql = "SELECT SUM(energy_amount) as today_energy 
                              FROM energy_balls 
                              WHERE user_id = ? AND status = 'collected' AND DATE(collect_time) = CURDATE()";
            $ballEnergy = $this->db->querySingle($ballEnergySql, [$userId]);
            $todayEnergy += $ballEnergy['today_energy'] ?? 0;
            
            // 从任务完成记录获取今日能量
            $taskEnergySql = "SELECT SUM(t.energy_reward) as today_energy 
                              FROM user_tasks ut 
                              JOIN tasks t ON ut.task_id = t.task_id 
                              WHERE ut.user_id = ? AND ut.status = 'completed' AND DATE(ut.last_completion_time) = CURDATE()";
            $taskEnergy = $this->db->querySingle($taskEnergySql, [$userId]);
            $todayEnergy += $taskEnergy['today_energy'] ?? 0;
            
            $stats['today_energy'] = $todayEnergy;
            
            return $stats;
        } catch (Exception $e) {
            logSystem('ERROR', 'Failed to get user energy stats', $e->getMessage(), $userId);
            return [
                'account' => null,
                'carrier' => null,
                'available_balls' => null,
                'today_energy' => 0
            ];
        }
    }
}

?>