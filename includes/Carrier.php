<?php
/**
 * 虚拟载体管理类
 */
require_once __DIR__ . '/../config.php';

class Carrier {
    // 常量定义
    const MAX_STAGE = 4; // 虚拟载体最大阶段
    private $db;
    
    /**
     * 构造函数
     */
    public function __construct($db = null) {
        // 使用传入的数据库连接，如果没有则创建新连接
        if ($db === null) {
            $this->db = Database::getInstance();
        } else {
            $this->db = $db;
        }
    }
    
    /**
     * 获取用户虚拟载体信息
     * @param int $userId 用户ID
     * @return array 载体信息
     */
    public function getCarrierInfo($userId) {
        // 获取用户碳账户信息，只选择必要字段
        $carbonAccount = $this->db->querySingle("SELECT current_energy FROM carbon_accounts WHERE user_id = ?", [$userId]);
        if (!$carbonAccount) {
            throw new Exception("用户碳账户不存在");
        }
        
        // 获取载体详细信息，只选择必要字段
        $carrier = $this->db->querySingle("SELECT carrier_type, current_stage, stage_name FROM carriers WHERE user_id = ?", [$userId]);
        if (!$carrier) {
            throw new Exception("虚拟载体信息不存在");
        }
        
        $currentStage = $carrier['current_stage'];
        $nextStage = $currentStage + 1;
        $nextCarrier = null;
        $energyRequired = 0;
        
        // 获取下一阶段载体信息和所需能量（如果有下一阶段）
        if ($nextStage <= self::MAX_STAGE) {
            // 创建下一阶段的载体信息（基于当前载体类型和下一阶段）
            $nextCarrier = [
                'carrier_type' => $carrier['carrier_type'],
                'current_stage' => $nextStage,
                'stage_name' => $this->getStageName($nextStage)
            ];
            
            // 获取下一阶段所需能量
            $energyRequired = STAGE_ENERGY_REQUIREMENTS[$nextStage];
        }
        
        // 获取当前阶段的最大能量限制
        $maxEnergy = STAGE_MAX_ENERGY[$currentStage] ?? 0;
        
        // 限制当前能量不超过最大限制
        $currentEnergy = min($carbonAccount['current_energy'], $maxEnergy);
        
        // 计算是否可以升级（当前能量达到当前阶段上限）
        $canUpgrade = $nextCarrier && $currentEnergy >= $maxEnergy;
        
        // 添加详细调试日志
        $debugLog = "用户ID: {$userId}, " .
                   "当前阶段: {$currentStage} ({$carrier['stage_name']}), " .
                   "当前阶段最大能量: {$maxEnergy}, " .
                   "碳账户实际能量: {$carbonAccount['current_energy']}, " .
                   "阶段内可用能量: {$currentEnergy}, " .
                   "能量是否达到阶段上限: " . ($currentEnergy >= $maxEnergy ? '是' : '否') . ", " .
                   "下一阶段: " . ($nextCarrier ? $nextStage . ' (' . $nextCarrier['stage_name'] . ')' : '无') . ", " .
                   "升级所需能量: {$energyRequired}, " .
                   "是否可升级: " . ($canUpgrade ? '是' : '否');
        
        // 记录调试日志
        logSystem('DEBUG', '载体信息查询详细', $debugLog, $userId);
        
        // 生成载体图片URL的辅助方法
        $generateImageUrl = function($carrierType, $stage) {
            return '../assets/images/carrier_' . 
                   strtolower(str_replace('碳汇', '', $carrierType)) . 
                   '_' . ($stage - 1) . '.png';
        };
        
        return [
            'current' => [
                'stage' => $currentStage,
                'type' => $carrier['carrier_type'],
                'name' => $carrier['stage_name'],
                'image_url' => $generateImageUrl($carrier['carrier_type'], $currentStage),
                'description' => '虚拟载体描述',
                'max_energy' => $maxEnergy
            ],
            'next' => $nextCarrier ? [
                'stage' => $nextStage,
                'type' => $nextCarrier['carrier_type'],
                'name' => $nextCarrier['stage_name'],
                'image_url' => $generateImageUrl($nextCarrier['carrier_type'], $nextStage),
                'description' => '下一阶段载体描述',
                'energy_required' => $energyRequired
            ] : null,
            'available_energy' => $currentEnergy,
            'can_upgrade' => $canUpgrade
        ];
    }
    
    /**
     * 升级虚拟载体
     * @param int $userId 用户ID
     * @return array 升级结果
     */
    public function upgradeCarrier($userId) {
        try {
            // 获取用户碳账户信息，只选择必要字段
            $carbonAccount = $this->db->querySingle("SELECT current_energy, total_energy_used FROM carbon_accounts WHERE user_id = ?", [$userId]);
            if (!$carbonAccount) {
                throw new Exception("用户碳账户不存在");
            }
            
            // 获取载体当前信息，只选择必要字段
            $carrier = $this->db->querySingle("SELECT current_stage FROM carriers WHERE user_id = ?", [$userId]);
            if (!$carrier) {
                throw new Exception("虚拟载体信息不存在");
            }
            
            // 检查是否可以升级
            $currentStage = $carrier['current_stage'];
            $nextStage = $currentStage + 1;
            
            if ($nextStage > self::MAX_STAGE) {
                throw new Exception("虚拟载体已达到最高阶段");
            }
            
            // 获取下一阶段所需能量
            $energyCost = STAGE_ENERGY_REQUIREMENTS[$nextStage];
            
            // 检查用户能量是否足够
            if ($carbonAccount['current_energy'] < $energyCost) {
                throw new Exception("升级所需能量不足");
            }
            
            // 检查是否已经在事务中
            $isTransactionOwner = !$this->db->inTransaction();
            
            // 如果不在事务中，则开始一个新事务
            if ($isTransactionOwner) {
                $this->db->beginTransaction();
            }
            
            // 更新载体阶段信息
            $stageName = $this->getStageName($nextStage);
            $this->db->execute("UPDATE carriers SET current_stage = ?, stage_name = ?, growth_progress = 0 WHERE user_id = ?", [$nextStage, $stageName, $userId]);
            
            // 扣除用户能量并更新总消耗
            $this->db->execute("UPDATE carbon_accounts SET current_energy = current_energy - ?, total_energy_used = total_energy_used + ? WHERE user_id = ?", [$energyCost, $energyCost, $userId]);
            
            // 如果是当前方法开始的事务，则提交
            if ($isTransactionOwner) {
                $this->db->commit();
            }
            
            // 计算剩余能量
            $remainingEnergy = $carbonAccount['current_energy'] - $energyCost;
            
            // 记录升级日志
            logSystem('INFO', '虚拟载体升级', 
                "用户 {$userId} 的虚拟载体从阶段 {$currentStage} 升级到阶段 {$nextStage}，消耗了 {$energyCost} 能量", 
                $userId);
                
            return [
                'success' => true,
                'message' => "虚拟载体升级成功",
                'new_stage' => $nextStage,
                'new_stage_name' => $stageName,
                'energy_cost' => $energyCost,
                'energy_used' => $energyCost,
                'remaining_energy' => $remainingEnergy
            ];
        } catch (Exception $e) {
            // 回滚事务
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            
            // 记录错误日志
            logSystem('ERROR', '虚拟载体升级失败', 
                "用户 {$userId} 的虚拟载体升级失败: {$e->getMessage()}", 
                $userId);
                
            throw new Exception('虚拟载体升级失败: ' . $e->getMessage());
        }
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
            4 => '森林'
        ];
        return $stageNames[$stage] ?? '未知阶段';
    }
    
    /**
     * 获取所有可用的虚拟载体列表
     * @return array 载体列表
     */
    public function getCarrierList() {
        try {
            // 避免使用SELECT *，只选择必要字段
            $carriers = $this->db->query("SELECT id, carrier_type, current_stage, carrier_name, image_path, description, energy_required FROM carriers ORDER BY current_stage, carrier_type");
            
            $result = [];
            foreach ($carriers as $carrier) {
                $result[] = [
                    'id' => $carrier['id'],
                    'stage' => $carrier['current_stage'],
                    'type' => $carrier['carrier_type'],
                    'name' => $carrier['carrier_name'],
                    'image_url' => $carrier['image_path'],
                    'description' => $carrier['description'],
                    'energy_required' => $carrier['energy_required']
                ];
            }
            
            // 记录查询日志
            logSystem('INFO', '载体列表查询', "查询所有可用的虚拟载体列表", null);
            
            return $result;
        } catch (Exception $e) {
            // 记录错误日志
            logSystem('ERROR', '载体列表查询失败', "错误: " . $e->getMessage(), null);
            
            throw $e;
        }
    }
    
    /**
     * 更改用户虚拟载体类型
     * @param int $userId 用户ID
     * @param string $carrierType 新的载体类型
     * @return bool 是否成功
     */
    public function changeCarrierType($userId, $carrierType) {
        try {
            // 检查载体类型是否有效
            $validTypes = ['tree', 'animal', 'building'];
            if (!in_array($carrierType, $validTypes)) {
                throw new Exception("无效的载体类型");
            }
            
            // 开始事务
            $this->db->beginTransaction();
            
            // 更新用户碳账户的载体类型
            $this->db->execute("UPDATE carbon_accounts SET carrier_type = ? WHERE user_id = ?", [$carrierType, $userId]);
            
            // 提交事务
            $this->db->commit();
            
            // 记录操作日志
            logSystem('INFO', '载体类型更改', 
                "用户 {$userId} 更改载体类型为 {$carrierType}", 
                $userId);
            
            return true;
        } catch (Exception $e) {
            // 回滚事务
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            
            // 记录错误日志
            logSystem('ERROR', '载体类型更改失败', 
                "用户 {$userId} 更改载体类型失败: {$e->getMessage()}", 
                $userId);
                
            throw $e;
        }
    }
}