<?php
/**
 * 任务管理类
 * 处理日常任务、进阶任务、社交任务等
 */

class TaskManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * 获取用户可执行的任务列表
     */
    public function getAvailableTasks($userId) {
        // 获取用户信息
        $userSql = "SELECT level FROM carbon_accounts WHERE user_id = ?";
        $user = $this->db->querySingle($userSql, [$userId]);
        
        if (!$user) {
            throw new Exception('用户不存在');
        }
        
        $userLevel = $user['level'];
        
        // 获取所有任务
        $sql = "SELECT * FROM tasks WHERE is_active = 1 ORDER BY task_type, task_id";
        $tasks = $this->db->query($sql);
        
        // 获取用户已完成的任务记录
        $userTaskSql = "SELECT task_id, status, last_completion_time FROM user_tasks WHERE user_id = ?";
        $userTasks = $this->db->query($userTaskSql, [$userId]);
        
        $userTaskMap = [];
        foreach ($userTasks as $userTask) {
            $userTaskMap[$userTask['task_id']] = $userTask;
        }
        
        // 处理任务状态和冷却时间
        $now = time();
        foreach ($tasks as &$task) {
            $taskId = $task['task_id'];
            
            if (isset($userTaskMap[$taskId])) {
                $userTask = $userTaskMap[$taskId];
                $task['user_status'] = $userTask['status'];
                $task['completed_at'] = $userTask['last_completion_time'];
                
                // 检查冷却时间
                if ($task['cooldown_hours'] > 0 && $userTask['status'] === 'completed') {
                    $lastCompleted = strtotime($userTask['last_completion_time']);
                    $cooldownSeconds = $task['cooldown_hours'] * 3600;
                    $nextAvailable = $lastCompleted + $cooldownSeconds;
                    
                    if ($now < $nextAvailable) {
                        $task['cooldown_remaining'] = $nextAvailable - $now;
                        $task['available'] = false;
                    } else {
                        $task['available'] = true;
                    }
                } else {
                    $task['available'] = ($userTask['status'] !== 'completed' || $task['max_completions'] > 1);
                }
                
                // 检查完成次数限制
                if ($task['max_completions'] > 0) {
                    $completionCountSql = "SELECT COUNT(*) as count FROM user_tasks 
                                          WHERE user_id = ? AND task_id = ? AND status = 'completed'";
                    $completionCount = $this->db->querySingle($completionCountSql, [$userId, $taskId]);
                    $task['completion_count'] = $completionCount['count'];
                    
                    if ($completionCount['count'] >= $task['max_completions']) {
                        $task['available'] = false;
                    }
                }
            } else {
                $task['user_status'] = '未开始';
                $task['available'] = true;
                $task['completion_count'] = 0;
            }
        }
        
        return $tasks;
    }
    
    /**
     * 执行任务
     */
    public function executeTask($userId, $taskId) {
        try {
            $this->db->beginTransaction();
            
            // 获取任务信息
            $taskSql = "SELECT * FROM tasks WHERE task_id = ? AND is_active = 1";
            $task = $this->db->querySingle($taskSql, [$taskId]);
            
            if (!$task) {
                throw new Exception('任务不存在或已禁用');
            }
            
            // 检查用户等级要求（暂时移除等级检查，因为数据库中没有required_level字段）
            // $userLevelSql = "SELECT level FROM carbon_accounts WHERE user_id = ?";
            // $userLevel = $this->db->querySingle($userLevelSql, [$userId]);
            // 
            // if (!$userLevel || $userLevel['level'] < $task['required_level']) {
            //     throw new Exception('用户等级不足，无法执行该任务');
            // }
            
            // 检查任务是否可执行
            $canExecute = $this->checkTaskAvailability($userId, $task);
            if (!$canExecute['available']) {
                throw new Exception($canExecute['reason']);
            }
            
            // 记录任务执行
            $userTaskData = [
                'user_id' => $userId,
                'task_id' => $taskId,
                'status' => 'completed',
                'last_completion_time' => date('Y-m-d H:i:s'),
                'completion_count' => 1
            ];
            
            $this->db->insert('user_tasks', $userTaskData);
            
            // 发放任务奖励（能量）
            if ($task['energy_reward'] > 0) {
                $this->awardTaskEnergy($userId, $task['energy_reward'], $task['task_name']);
            }
            
            $this->db->commit();
            
            logSystem('INFO', 'Task completed', "用户 {$userId} 完成任务: {$task['task_name']}, 奖励能量: {$task['energy_reward']}", $userId);
            
            return [
                'success' => true,
                'task_name' => $task['task_name'],
                'energy_reward' => $task['energy_reward'],
                'message' => '任务完成成功'
            ];
        } catch (Exception $e) {
            $this->db->rollback();
            logSystem('ERROR', 'Task execution failed', $e->getMessage(), $userId);
            throw new Exception('任务执行失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 检查任务可用性
     */
    private function checkTaskAvailability($userId, $task) {
        $now = time();
        
        // 检查完成次数限制
        if ($task['max_completions'] > 0) {
            $completionCountSql = "SELECT COUNT(*) as count FROM user_tasks 
                                  WHERE user_id = ? AND task_id = ? AND status = 'completed'";
            $completionCount = $this->db->querySingle($completionCountSql, [$userId, $task['task_id']]);
            
            if ($completionCount['count'] >= $task['max_completions']) {
                return ['available' => false, 'reason' => '任务已完成次数已达上限'];
            }
        }
        
        // 检查冷却时间
        if ($task['cooldown_hours'] > 0) {
            $lastCompletionSql = "SELECT last_completion_time FROM user_tasks 
                                 WHERE user_id = ? AND task_id = ? AND status = 'completed' 
                                 ORDER BY last_completion_time DESC LIMIT 1";
            $lastCompletion = $this->db->querySingle($lastCompletionSql, [$userId, $task['task_id']]);
            
            if ($lastCompletion) {
                $lastCompleted = strtotime($lastCompletion['last_completion_time']);
                $cooldownSeconds = $task['cooldown_hours'] * 3600;
                $nextAvailable = $lastCompleted + $cooldownSeconds;
                
                if ($now < $nextAvailable) {
                    $remaining = $nextAvailable - $now;
                    $hours = floor($remaining / 3600);
                    $minutes = floor(($remaining % 3600) / 60);
                    
                    return [
                        'available' => false, 
                        'reason' => "任务冷却中，{$hours}小时{$minutes}分钟后可再次执行"
                    ];
                }
            }
        }
        
        return ['available' => true, 'reason' => ''];
    }
    
    /**
     * 发放任务奖励能量
     */
    private function awardTaskEnergy($userId, $energyAmount, $taskName) {
        // 更新碳账户
        $updateSql = "UPDATE carbon_accounts 
                      SET current_energy = current_energy + ?, 
                          total_energy = total_energy + ?,
                          last_update_time = NOW() 
                      WHERE user_id = ?";
        $this->db->execute($updateSql, [$energyAmount, $energyAmount, $userId]);
        
        // 生成能量球
        $ballData = [
            'user_id' => $userId,
            'energy_amount' => $energyAmount,
            'ball_type' => '普通',
            'location_lat' => rand(10, 90) / 100.0,
            'location_lng' => rand(10, 90) / 100.0,
            'expire_time' => date('Y-m-d H:i:s', strtotime('+' . ENERGY_BALL_EXPIRE_HOURS . ' hours')),
            'status' => 'available'
        ];
        
        $this->db->insert('energy_balls', $ballData);
        
        // 更新虚拟载体成长进度（而不是能量，因为carriers表没有current_energy字段）
        $carrierSql = "UPDATE carriers 
                       SET growth_progress = growth_progress + ?, 
                           update_time = NOW() 
                       WHERE user_id = ? AND carrier_type = '碳汇树' 
                       ORDER BY create_time DESC LIMIT 1";
        $this->db->execute($carrierSql, [min($energyAmount / 100, 0.5), $userId]);
    }
    
    /**
     * 获取用户任务统计
     */
    public function getUserTaskStats($userId) {
        $stats = [];
        
        // 获取总任务数
        $totalTasksSql = "SELECT COUNT(*) as count FROM tasks WHERE is_active = 1";
        $totalTasks = $this->db->querySingle($totalTasksSql);
        $stats['total_tasks'] = $totalTasks['count'];
        
        // 获取已完成任务数
        $completedTasksSql = "SELECT COUNT(DISTINCT task_id) as count FROM user_tasks 
                             WHERE user_id = ? AND status = 'completed'";
        $completedTasks = $this->db->querySingle($completedTasksSql, [$userId]);
        $stats['completed_tasks'] = $completedTasks['count'];
        
        // 获取今日完成任务数
        $todayTasksSql = "SELECT COUNT(*) as count FROM user_tasks 
                         WHERE user_id = ? AND status = 'completed' AND DATE(last_completion_time) = CURDATE()";
        $todayTasks = $this->db->querySingle($todayTasksSql, [$userId]);
        $stats['today_tasks'] = $todayTasks['count'];
        
        // 获取任务类型统计
        $typeStatsSql = "SELECT t.task_type, COUNT(ut.user_task_id) as completed_count 
                        FROM tasks t 
                        LEFT JOIN user_tasks ut ON t.task_id = ut.task_id AND ut.user_id = ? AND ut.status = 'completed' 
                        WHERE t.is_active = 1 
                        GROUP BY t.task_type";
        $typeStats = $this->db->query($typeStatsSql, [$userId]);
        $stats['type_stats'] = $typeStats;
        
        // 获取累计获得能量
        $totalEnergySql = "SELECT SUM(t.energy_reward) as total_energy 
                          FROM user_tasks ut 
                          JOIN tasks t ON ut.task_id = t.task_id 
                          WHERE ut.user_id = ? AND ut.status = 'completed'";
        $totalEnergy = $this->db->querySingle($totalEnergySql, [$userId]);
        $stats['total_energy_from_tasks'] = $totalEnergy['total_energy'] ?? 0;
        
        return $stats;
    }
    
    /**
     * 处理每日重置任务
     */
    public function processDailyResetTasks() {
        // 获取需要每日重置的任务
        $dailyTasksSql = "SELECT id FROM tasks WHERE task_type = '日常任务' AND cooldown_hours = 24";
        $dailyTasks = $this->db->query($dailyTasksSql);
        
        if (empty($dailyTasks)) {
            return 0;
        }
        
        $resetCount = 0;
        
        foreach ($dailyTasks as $task) {
            try {
                // 重置超过24小时的任务记录（标记为过期）
                $resetSql = "UPDATE user_tasks SET status = '已过期' 
                            WHERE task_id = ? AND status = '已完成' 
                            AND TIMESTAMPDIFF(HOUR, completed_at, NOW()) >= 24";
                
                $affectedRows = $this->db->execute($resetSql, [$task['id']]);
                $resetCount += $affectedRows;
                
                logSystem('INFO', 'Daily task reset', "任务 {$task['id']} 重置完成，影响记录数: {$affectedRows}");
            } catch (Exception $e) {
                logSystem('ERROR', 'Daily task reset failed', $e->getMessage());
            }
        }
        
        return $resetCount;
    }
    
    /**
     * 获取连续签到信息
     */
    public function getCheckinInfo($userId) {
        // 获取签到任务ID（假设签到任务的task_name包含'签到'）
        $checkinTaskSql = "SELECT id FROM tasks WHERE task_name LIKE '%签到%' AND status = '启用' LIMIT 1";
        $checkinTask = $this->db->querySingle($checkinTaskSql);
        
        if (!$checkinTask) {
            return ['has_checkin_task' => false];
        }
        
        $taskId = $checkinTask['id'];
        
        // 获取今日是否已签到
        $todayCheckinSql = "SELECT id FROM user_tasks 
                           WHERE user_id = ? AND task_id = ? AND status = '已完成' 
                           AND DATE(completed_at) = CURDATE()";
        $todayCheckin = $this->db->querySingle($todayCheckinSql, [$userId, $taskId]);
        
        // 获取连续签到天数
        $consecutiveSql = "SELECT COUNT(*) as days 
                          FROM (
                              SELECT DISTINCT DATE(completed_at) as checkin_date 
                              FROM user_tasks 
                              WHERE user_id = ? AND task_id = ? AND status = '已完成' 
                              ORDER BY checkin_date DESC 
                              LIMIT 7
                          ) as recent_checkins";
        $consecutiveDays = $this->db->querySingle($consecutiveSql, [$userId, $taskId]);
        
        return [
            'has_checkin_task' => true,
            'task_id' => $taskId,
            'checked_in_today' => !empty($todayCheckin),
            'consecutive_days' => $consecutiveDays['days'] ?? 0
        ];
    }
    
    /**
     * 自动完成某些任务（如连续签到奖励）
     */
    public function autoCompleteTasks($userId) {
        $autoCompleted = [];
        
        // 检查连续签到奖励
        $checkinInfo = $this->getCheckinInfo($userId);
        
        if ($checkinInfo['has_checkin_task'] && $checkinInfo['consecutive_days'] >= 7) {
            // 查找连续签到奖励任务
            $rewardTaskSql = "SELECT id, task_name, energy_reward FROM tasks 
                             WHERE task_name LIKE '%连续签到%' AND status = '启用' LIMIT 1";
            $rewardTask = $this->db->querySingle($rewardTaskSql);
            
            if ($rewardTask) {
                // 检查是否已领取过本周的奖励
                $thisWeekStart = date('Y-m-d', strtotime('monday this week'));
                $rewardCheckSql = "SELECT id FROM user_tasks 
                                 WHERE user_id = ? AND task_id = ? AND status = '已完成' 
                                 AND completed_at >= ?";
                $existingReward = $this->db->querySingle($rewardCheckSql, [$userId, $rewardTask['id'], $thisWeekStart]);
                
                if (!$existingReward) {
                    try {
                        $this->executeTask($userId, $rewardTask['id']);
                        $autoCompleted[] = $rewardTask['task_name'];
                    } catch (Exception $e) {
                        logSystem('ERROR', 'Auto complete task failed', $e->getMessage(), $userId);
                    }
                }
            }
        }
        
        return $autoCompleted;
    }
}

?>