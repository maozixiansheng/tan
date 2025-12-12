<?php
/**
 * 碳排放计算类
 */
class Carbon {
    private $db;
    
    /**
     * 构造函数
     */
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * 计算碳排放量
     * @param float $computePower 算力值（TOPS/小时）
     * @param string $emissionType 排放类型（算力/出行/购物/饮食/生活）
     * @return float 碳排放量（kg CO₂）
     */
    public function calculateEmission($computePower, $emissionType) {
        // 1 TOPS/小时 = 0.02kg CO₂
        $baseEmission = $computePower * 0.02;
        
        // 获取场景排放因子
        $emissionTypeFactors = [
            '算力' => 1.0,    // 算力
            '出行' => 0.2,   // 出行
            '购物' => 0.1,   // 购物
            '饮食' => 0.05,  // 饮食
            '生活' => 0.03   // 生活
        ];
        $scenarioFactor = isset($emissionTypeFactors[$emissionType]) ? $emissionTypeFactors[$emissionType] : 1.0;
        
        // 根据场景调整基准排放
        $adjustedEmission = $baseEmission * $scenarioFactor;
        
        return round($adjustedEmission, 2);
    }
    
    /**
     * 计算碳能量
     * @param float $baseEmission 基准碳排放量
     * @param float $actualEmission 实际碳排放量
     * @return float 碳能量
     */
    public function calculateEnergy($baseEmission, $actualEmission) {
        // 碳能量 = (基准碳排放 - 实际碳排放) × 10
        $energy = ($baseEmission - $actualEmission) * 10;
        
        // 确保能量不为负
        return max(0, round($energy, 2));
    }
    
    /**
     * 记录碳排放
     * @param int $userId 用户ID
     * @param array $recordData 记录数据
     * @return int 记录ID
     */
    public function recordEmission($userId, $recordData) {
        try {
            // 计算实际碳排放量
            $actualEmission = $this->calculateEmission($recordData['compute_power'], $recordData['emission_type']);
            
            // 获取该场景的基准碳排放量（假设为行业平均值）
            // 这里简化处理，使用实际排放量的1.2倍作为基准
            $baseEmission = $actualEmission * 1.2;
            
            // 计算获得的碳能量
            $energyGained = $this->calculateEnergy($baseEmission, $actualEmission);
            
            // 准备记录数据
            $data = [
                'user_id' => $userId,
                'emission_type' => $recordData['emission_type'],
                'emission_amount' => $actualEmission,
                'description' => $recordData['description'] ?? null,
                'emission_date' => $recordData['emission_date'] ?? date('Y-m-d'),
                'is_verified' => 0
            ];
            
            // 开始事务
            $this->db->beginTransaction();
            
            // 插入碳排放记录
            $recordId = $this->db->insert('carbon_emissions', $data);
            
            // 如果获得了碳能量，更新用户碳账户
            if ($energyGained > 0) {
                // 更新用户碳账户
                $this->db->query("UPDATE carbon_accounts SET 
                    total_energy = total_energy + ?, 
                    current_energy = current_energy + ?, 
                    carbon_footprint = carbon_footprint + ? 
                    WHERE user_id = ?", 
                    [$energyGained, $energyGained, $actualEmission, $userId]
                );
            } else {
                // 即使没有获得能量，也需要更新碳足迹
                $this->db->query("UPDATE carbon_accounts SET 
                    carbon_footprint = carbon_footprint + ? 
                    WHERE user_id = ?", 
                    [$actualEmission, $userId]
                );
            }
            
            // 提交事务
            $this->db->commit();
            
            return $recordId;
        } catch (Exception $e) {
            // 回滚事务
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * 获取用户碳排放历史
     * @param int $userId 用户ID
     * @param mixed $options 选项数组或限制数量
     * @param int $offset 偏移量
     * @return array 包含'total'和'records'的关联数组
     */
    public function getEmissionHistory($userId, $options = [], $offset = 0) {
        // 兼容旧的参数格式
        if (is_numeric($options)) {
            $limit = $options;
            $options = [];
        } else {
            $limit = isset($options['limit']) ? $options['limit'] : 10;
            $offset = isset($options['offset']) ? $options['offset'] : 0;
        }
        
        // 构建查询条件
        $conditions = ['user_id = ?'];
        $params = [$userId];
        
        // 添加过滤条件 - 按索引友好的顺序排列条件
        // 根据idx_user_date_type索引(user_id, emission_date, emission_type)的顺序排列条件
        if (isset($options['start_date']) && !empty($options['start_date'])) {
            $conditions[] = 'emission_date >= ?';
            $params[] = $options['start_date'];
        }
        
        if (isset($options['end_date']) && !empty($options['end_date'])) {
            $conditions[] = 'emission_date <= ?';
            $params[] = $options['end_date'];
        }
        
        if (isset($options['emission_type']) && !empty($options['emission_type'])) {
            $conditions[] = 'emission_type = ?';
            $params[] = $options['emission_type'];
        }
        
        // 构建WHERE子句
        $whereClause = implode(' AND ', $conditions);
        
        // 使用单个查询同时获取记录和总记录数，避免额外的COUNT查询
        $sql = "SELECT 
                    SQL_CALC_FOUND_ROWS 
                    emission_id, emission_type, emission_amount, description, emission_date, record_time, is_verified 
               FROM carbon_emissions 
               WHERE $whereClause 
               ORDER BY record_time DESC 
               LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $records = $this->db->fetchAll($sql, $params);
        
        // 获取实际匹配的总记录数（不考虑LIMIT和OFFSET）
        $totalResult = $this->db->fetch("SELECT FOUND_ROWS() as count");
        $total = $totalResult['count'] ?? 0;
        
        return ['total' => $total, 'records' => $records];
    }
    
    /**
     * 获取用户碳排放统计
     * @param int $userId 用户ID
     * @param string $period 时间段（day/week/month/year）
     * @return array 碳排放统计
     */
    public function getEmissionStats($userId, $period = 'month') {
        // 使用更高效的时间范围条件，避免使用函数包装字段
        $whereCondition = "user_id = ? AND emission_date >= ?";
        $params = [$userId];
        
        switch ($period) {
            case 'day':
                $params[] = date('Y-m-d');
                break;
            case 'week':
                $params[] = date('Y-m-d', strtotime('monday this week'));
                break;
            case 'month':
                $params[] = date('Y-m-01');
                break;
            case 'year':
                $params[] = date('Y-01-01');
                break;
            default:
                $params[] = date('Y-m-01');
        }
        
        // 优化1: 减少重复代码，使用数组存储查询语句
        $queries = [
            'total' => "SELECT 
                        COUNT(*) as total_records,
                        SUM(emission_amount) as total_emission,
                        AVG(emission_amount) as avg_emission
                   FROM carbon_emissions 
                   WHERE {$whereCondition}",
            'scenario' => "SELECT emission_type, SUM(emission_amount) as emission, COUNT(*) as count 
                   FROM carbon_emissions 
                   WHERE {$whereCondition}
                   GROUP BY emission_type",
            'daily' => "SELECT emission_date as date, SUM(emission_amount) as emission, COUNT(*) as count 
                   FROM carbon_emissions 
                   WHERE user_id = ? AND emission_date >= ?
                   GROUP BY emission_date
                   ORDER BY date"
        ];
        
        // 执行查询
        $totalStats = $this->db->fetch($queries['total'], $params);
        $scenarioStats = $this->db->fetchAll($queries['scenario'], $params);
        
        // 按日期统计（最近7天），使用预计算的日期范围
        $sevenDaysAgo = date('Y-m-d', strtotime('-7 days'));
        $dailyStats = $this->db->fetchAll($queries['daily'], [$userId, $sevenDaysAgo]);
        
        // 获取用户碳账户信息
        $carbonAccount = $this->db->fetch("SELECT total_energy, current_energy, carbon_reduction FROM carbon_accounts WHERE user_id = ?", [$userId]);
        
        return [
            'total_records' => $totalStats['total_records'],
            'total_emission' => $totalStats['total_emission'],
            'avg_emission' => $totalStats['avg_emission'],
            'total_energy' => $carbonAccount['total_energy'],
            'current_energy' => $carbonAccount['current_energy'],
            'carbon_reduction' => $carbonAccount['carbon_reduction'],
            'emission_type_stats' => $scenarioStats,
            'daily_stats' => $dailyStats
        ];
    }
    
    /**
     * 获取全球碳排放数据
     * @param int $limit 限制数量
     * @param string $country 国家（可选）
     * @return array 全球碳排放数据
     */
    public function getGlobalCarbonData($limit = 10, $country = null) {
        $sql = "SELECT * FROM global_carbon_data WHERE 1=1";
        $params = [];
        
        if ($country) {
            $sql .= " AND country = ?";
            $params[] = $country;
        }
        
        $sql .= " ORDER BY year DESC, total_emissions DESC LIMIT ?";
        $params[] = $limit;
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * 更新用户碳足迹总量
     * @param int $userId 用户ID
     * @return bool 更新结果
     */
    public function updateCarbonFootprint($userId) {
        try {
            // 计算用户总碳排放量
            $sql = "SELECT SUM(emission_amount) as total_emission FROM carbon_emissions WHERE user_id = ?";
            $result = $this->db->fetch($sql, [$userId]);
            $totalEmission = $result['total_emission'] ?? 0;
            
            // 更新碳账户
            $sql = "UPDATE carbon_accounts SET carbon_footprint = ? WHERE user_id = ?";
            $this->db->execute($sql, [$totalEmission, $userId]);
            
            return true;
        } catch (Exception $e) {
            // 使用内置的错误记录方法或创建一个
            error_log('更新碳足迹总量失败: ' . $e->getMessage());
            return false;
        }
    }
}