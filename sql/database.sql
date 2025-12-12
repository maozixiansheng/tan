-- 碳森林项目数据库初始化脚本
-- 创建时间: 2024-01-01
-- 适配 MySQL 8.0

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;
SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='TRADITIONAL,ALLOW_INVALID_DATES';

-- 创建数据库
CREATE DATABASE IF NOT EXISTS `carbon_forest` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `carbon_forest`;

-- 用户表
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '用户ID',
  `username` varchar(50) NOT NULL COMMENT '用户名',
  `email` varchar(100) NOT NULL COMMENT '邮箱',
  `phone` varchar(20) DEFAULT NULL COMMENT '手机号',
  `password_hash` varchar(64) NOT NULL COMMENT '密码哈希',
  `salt` varchar(32) NOT NULL COMMENT '密码盐值',
  `nickname` varchar(50) DEFAULT NULL COMMENT '昵称',
  `avatar` varchar(255) DEFAULT NULL COMMENT '头像URL',
  `user_type` enum('个人','企业','组织') DEFAULT '个人' COMMENT '用户类型',
  `company_name` varchar(100) DEFAULT NULL COMMENT '企业名称',
  `industry` varchar(50) DEFAULT NULL COMMENT '行业',
  `location` varchar(100) DEFAULT NULL COMMENT '所在地',
  `bio` text COMMENT '个人简介',
  `registration_time` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '注册时间',
  `last_login_time` datetime DEFAULT NULL COMMENT '最后登录时间',
  `status` enum('active','inactive','banned') DEFAULT 'active' COMMENT '状态',
  `is_verified` tinyint(1) DEFAULT '0' COMMENT '是否已验证',
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_phone` (`phone`),
  KEY `idx_registration_time` (`registration_time`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户表';

-- 碳账户表
CREATE TABLE `carbon_accounts` (
  `account_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '账户ID',
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `total_energy` decimal(10,2) DEFAULT '0.00' COMMENT '总碳能量',
  `current_energy` decimal(10,2) DEFAULT '0.00' COMMENT '当前碳能量',
  `level` int(11) DEFAULT '1' COMMENT '等级',
  `experience` int(11) DEFAULT '0' COMMENT '经验值',
  `carbon_footprint` decimal(10,2) DEFAULT '0.00' COMMENT '碳足迹总量',
  `carbon_reduction` decimal(10,2) DEFAULT '0.00' COMMENT '碳减排总量',
  `last_update_time` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '最后更新时间',
  PRIMARY KEY (`account_id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `idx_level` (`level`),
  KEY `idx_total_energy` (`total_energy`),
  CONSTRAINT `fk_carbon_accounts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='碳账户表';

-- 碳排放记录表
CREATE TABLE `carbon_emissions` (
  `emission_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '排放记录ID',
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `emission_type` enum('算力','出行','购物','饮食','生活') NOT NULL COMMENT '排放类型',
  `emission_amount` decimal(10,2) NOT NULL COMMENT '排放量(kg)',
  `description` varchar(255) DEFAULT NULL COMMENT '描述',
  `emission_date` date NOT NULL COMMENT '排放日期',
  `record_time` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '记录时间',
  `is_verified` tinyint(1) DEFAULT '0' COMMENT '是否已验证',
  `verification_notes` text COMMENT '验证备注',
  PRIMARY KEY (`emission_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_emission_date` (`emission_date`),
  KEY `idx_emission_type` (`emission_type`),
  CONSTRAINT `fk_carbon_emissions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='碳排放记录表';

-- 虚拟载体表
CREATE TABLE `carriers` (
  `carrier_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '载体ID',
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `carrier_type` enum('碳汇树','碳汇草','碳汇花') DEFAULT '碳汇树' COMMENT '载体类型',
  `current_stage` int(11) DEFAULT '1' COMMENT '当前阶段',
  `stage_name` varchar(50) DEFAULT '种子' COMMENT '阶段名称',
  `growth_progress` decimal(5,2) DEFAULT '0.00' COMMENT '成长进度(%)',
  `last_watering_time` datetime DEFAULT NULL COMMENT '最后浇水时间',
  `create_time` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`carrier_id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `idx_carrier_type` (`carrier_type`),
  KEY `idx_current_stage` (`current_stage`),
  CONSTRAINT `fk_carriers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='虚拟载体表';

-- 好友关系表
CREATE TABLE `friendships` (
  `friendship_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '关系ID',
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `friend_id` int(11) NOT NULL COMMENT '好友ID',
  `status` enum('pending','accepted','rejected','blocked') DEFAULT 'pending' COMMENT '状态',
  `request_time` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '请求时间',
  `response_time` datetime DEFAULT NULL COMMENT '响应时间',
  `notes` varchar(255) DEFAULT NULL COMMENT '备注',
  PRIMARY KEY (`friendship_id`),
  UNIQUE KEY `unique_friendship` (`user_id`,`friend_id`),
  KEY `idx_friend_id` (`friend_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_friendships_friend` FOREIGN KEY (`friend_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_friendships_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='好友关系表';

-- 任务表
CREATE TABLE `tasks` (
  `task_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '任务ID',
  `task_name` varchar(100) NOT NULL COMMENT '任务名称',
  `task_type` enum('日常','挑战','成就','公益') NOT NULL COMMENT '任务类型',
  `description` text COMMENT '任务描述',
  `energy_reward` int(11) NOT NULL COMMENT '能量奖励',
  `difficulty` enum('简单','中等','困难') DEFAULT '简单' COMMENT '难度',
  `completion_condition` text COMMENT '完成条件',
  `max_completions` int(11) DEFAULT '1' COMMENT '最大完成次数',
  `cooldown_hours` int(11) DEFAULT '24' COMMENT '冷却时间(小时)',
  `is_active` tinyint(1) DEFAULT '1' COMMENT '是否激活',
  `create_time` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`task_id`),
  KEY `idx_task_type` (`task_type`),
  KEY `idx_difficulty` (`difficulty`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='任务表';

-- 用户任务记录表
CREATE TABLE `user_tasks` (
  `user_task_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '用户任务ID',
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `task_id` int(11) NOT NULL COMMENT '任务ID',
  `completion_count` int(11) DEFAULT '0' COMMENT '完成次数',
  `last_completion_time` datetime DEFAULT NULL COMMENT '最后完成时间',
  `status` enum('in_progress','completed','failed') DEFAULT 'in_progress' COMMENT '状态',
  `progress_data` json DEFAULT NULL COMMENT '进度数据',
  `start_time` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '开始时间',
  `update_time` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`user_task_id`),
  UNIQUE KEY `unique_user_task` (`user_id`,`task_id`),
  KEY `idx_task_id` (`task_id`),
  KEY `idx_status` (`status`),
  KEY `idx_last_completion_time` (`last_completion_time`),
  CONSTRAINT `fk_user_tasks_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`task_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_tasks_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户任务记录表';

-- 公益捐赠表
CREATE TABLE `donations` (
  `donation_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '捐赠ID',
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `project_name` varchar(100) NOT NULL COMMENT '项目名称',
  `energy_amount` int(11) NOT NULL COMMENT '捐赠能量',
  `carbon_equivalent` decimal(10,2) DEFAULT '0.00' COMMENT '碳当量(kg)',
  `donation_time` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '捐赠时间',
  `certificate_url` varchar(255) DEFAULT NULL COMMENT '证书URL',
  `status` enum('pending','completed','failed') DEFAULT 'pending' COMMENT '状态',
  `notes` text COMMENT '备注',
  PRIMARY KEY (`donation_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_donation_time` (`donation_time`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_donations_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='公益捐赠表';

-- 能量球表
CREATE TABLE `energy_balls` (
  `ball_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '能量球ID',
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `energy_amount` int(11) NOT NULL COMMENT '能量数量',
  `ball_type` enum('普通','稀有','史诗','传说') DEFAULT '普通' COMMENT '能量球类型',
  `expire_time` datetime NOT NULL COMMENT '过期时间',
  `create_time` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `collect_time` datetime DEFAULT NULL COMMENT '收取时间',
  `status` enum('available','collected','expired') DEFAULT 'available' COMMENT '状态',
  `location_lat` decimal(10,6) DEFAULT NULL COMMENT '位置纬度',
  `location_lng` decimal(10,6) DEFAULT NULL COMMENT '位置经度',
  PRIMARY KEY (`ball_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_expire_time` (`expire_time`),
  KEY `idx_status` (`status`),
  KEY `idx_ball_type` (`ball_type`),
  CONSTRAINT `fk_energy_balls_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='能量球表';

-- 浇水记录表
CREATE TABLE `watering_records` (
  `watering_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '浇水ID',
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `carrier_id` int(11) NOT NULL COMMENT '载体ID',
  `water_amount` int(11) DEFAULT '1' COMMENT '浇水量',
  `watering_time` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '浇水时间',
  `energy_cost` int(11) DEFAULT '10' COMMENT '消耗能量',
  `growth_increase` decimal(5,2) DEFAULT '1.00' COMMENT '成长增加值',
  PRIMARY KEY (`watering_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_carrier_id` (`carrier_id`),
  KEY `idx_watering_time` (`watering_time`),
  CONSTRAINT `fk_watering_records_carrier` FOREIGN KEY (`carrier_id`) REFERENCES `carriers` (`carrier_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_watering_records_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='浇水记录表';

-- 系统日志表
CREATE TABLE `system_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '日志ID',
  `log_type` varchar(50) NOT NULL COMMENT '日志类型',
  `user_id` int(11) DEFAULT NULL COMMENT '用户ID',
  `action` varchar(100) NOT NULL COMMENT '操作',
  `details` text COMMENT '详细信息',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'IP地址',
  `user_agent` text COMMENT '用户代理',
  `log_time` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '记录时间',
  PRIMARY KEY (`log_id`),
  KEY `idx_log_type` (`log_type`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_log_time` (`log_time`),
  CONSTRAINT `fk_system_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统日志表';

-- 全球碳排放数据表
CREATE TABLE `global_carbon_data` (
  `data_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '数据ID',
  `country` varchar(100) NOT NULL COMMENT '国家',
  `year` int(11) NOT NULL COMMENT '年份',
  `total_emissions` decimal(15,2) DEFAULT '0.00' COMMENT '总排放量(百万吨)',
  `per_capita` decimal(10,2) DEFAULT '0.00' COMMENT '人均排放量(吨)',
  `population` bigint(20) DEFAULT '0' COMMENT '人口',
  `gdp` decimal(15,2) DEFAULT '0.00' COMMENT 'GDP(亿美元)',
  `data_source` varchar(255) DEFAULT NULL COMMENT '数据来源',
  `update_time` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`data_id`),
  UNIQUE KEY `unique_country_year` (`country`,`year`),
  KEY `idx_year` (`year`),
  KEY `idx_country` (`country`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='全球碳排放数据表';

-- 插入默认任务数据
INSERT INTO `tasks` (`task_name`, `task_type`, `description`, `energy_reward`, `difficulty`, `completion_condition`, `max_completions`, `cooldown_hours`) VALUES
('每日登录', '日常', '每日登录碳森林应用', 10, '简单', '成功登录系统', 1, 24),
('记录碳排放', '日常', '记录一次碳排放数据', 20, '简单', '添加一条碳排放记录', 5, 24),
('能量球收取', '日常', '收取一个能量球', 15, '简单', '成功收取能量球', 3, 12),
('虚拟载体浇水', '日常', '为虚拟载体浇水一次', 8, '简单', '成功浇水一次', 5, 6),
('添加好友', '日常', '成功添加一位好友', 30, '中等', '好友请求被接受', 1, 24),
('公益捐赠', '日常', '参与公益捐赠活动', 50, '中等', '捐赠一定数量的碳能量', 1, 168),
('碳足迹分析', '挑战', '完成一周碳足迹分析', 100, '困难', '连续7天记录碳排放', 1, 168),
('节能减排达人', '成就', '累计减少碳排放100kg', 200, '困难', '累计碳减排达到100kg', 1, 0),
('环保先锋', '成就', '连续登录30天', 300, '中等', '连续30天登录', 1, 0),
('碳汇大师', '成就', '虚拟载体达到最高阶段', 500, '困难', '载体成长到第4阶段', 1, 0);

-- 插入全球碳排放示例数据
INSERT INTO `global_carbon_data` (`country`, `year`, `total_emissions`, `per_capita`, `population`, `gdp`) VALUES
('中国', 2022, 10667.89, 7.41, 1412000000, 179630.00),
('美国', 2022, 4712.34, 14.24, 331000000, 254620.00),
('印度', 2022, 2684.23, 1.91, 1408000000, 33850.00),
('俄罗斯', 2022, 1674.23, 11.45, 144000000, 18300.00),
('日本', 2022, 1032.45, 8.17, 125000000, 49390.00),
('德国', 2022, 644.12, 7.72, 83000000, 40820.00),
('韩国', 2022, 616.78, 11.98, 51000000, 16600.00),
('伊朗', 2022, 690.23, 8.12, 85000000, 2310.00),
('沙特阿拉伯', 2022, 635.45, 17.95, 35000000, 8330.00),
('加拿大', 2022, 545.67, 14.43, 38000000, 19900.00);

-- 创建视图：用户排行榜
CREATE VIEW `user_leaderboard` AS
SELECT 
    u.user_id,
    u.username,
    u.nickname,
    u.avatar,
    ca.total_energy,
    ca.current_energy,
    ca.level,
    ca.carbon_reduction,
    c.carrier_type,
    c.current_stage,
    c.stage_name,
    RANK() OVER (ORDER BY ca.total_energy DESC) as energy_rank,
    RANK() OVER (ORDER BY ca.carbon_reduction DESC) as reduction_rank
FROM users u
JOIN carbon_accounts ca ON u.user_id = ca.user_id
JOIN carriers c ON u.user_id = c.user_id
WHERE u.status = 'active'
ORDER BY ca.total_energy DESC;

-- 创建存储过程：计算用户碳足迹统计
DELIMITER //
CREATE PROCEDURE `CalculateUserCarbonStats`(IN user_id_param INT)
BEGIN
    SELECT 
        COUNT(*) as total_records,
        SUM(emission_amount) as total_emissions,
        AVG(emission_amount) as avg_emission,
        MAX(emission_amount) as max_emission,
        MIN(emission_amount) as min_emission,
        emission_type,
        COUNT(*) as type_count
    FROM carbon_emissions 
    WHERE user_id = user_id_param 
    GROUP BY emission_type
    ORDER BY total_emissions DESC;
END //
DELIMITER ;

-- 创建触发器：用户注册时自动创建碳账户和虚拟载体
DELIMITER //
CREATE TRIGGER `after_user_insert`
AFTER INSERT ON `users`
FOR EACH ROW
BEGIN
    -- 创建碳账户
    INSERT INTO carbon_accounts (user_id, total_energy, current_energy, level) 
    VALUES (NEW.user_id, 100, 100, 1);
    
    -- 创建虚拟载体
    INSERT INTO carriers (user_id, carrier_type, current_stage, stage_name, growth_progress) 
    VALUES (NEW.user_id, '碳汇树', 1, '种子', 0);
    
    -- 记录系统日志
    INSERT INTO system_logs (log_type, user_id, action, details) 
    VALUES ('user', NEW.user_id, 'register', CONCAT('用户 ', NEW.username, ' 注册成功'));
END //
DELIMITER ;

-- 创建触发器：能量球过期自动更新状态
DELIMITER //
CREATE TRIGGER `update_energy_ball_status`
BEFORE UPDATE ON `energy_balls`
FOR EACH ROW
BEGIN
    IF NEW.expire_time < NOW() AND NEW.status = 'available' THEN
        SET NEW.status = 'expired';
    END IF;
END //
DELIMITER ;

SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;

-- 数据库初始化完成提示
SELECT '碳森林项目数据库初始化完成!' as message;