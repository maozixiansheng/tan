# 数据库优化建议

## 1. 索引优化

### 1.1 已有的索引
- `users`: `username`, `email`, `idx_phone`, `idx_registration_time`, `idx_status`
- `carbon_accounts`: `user_id`, `idx_level`, `idx_total_energy`
- `carbon_emissions`: `idx_user_id`, `idx_emission_date`, `idx_emission_type`
- `carriers`: `user_id`, `idx_carrier_type`, `idx_current_stage`
- `friendships`: `unique_friendship`, `idx_friend_id`, `idx_status`
- `tasks`: `idx_task_type`, `idx_difficulty`, `idx_is_active`
- `user_tasks`: `unique_user_task`, `idx_task_id`, `idx_status`, `idx_last_completion_time`
- `donations`: `idx_user_id`, `idx_donation_time`, `idx_status`
- `energy_balls`: `idx_user_id`, `idx_expire_time`, `idx_status`, `idx_ball_type`
- `watering_records`: `idx_user_id`, `idx_carrier_id`, `idx_watering_time`
- `system_logs`: `idx_log_type`, `idx_user_id`, `idx_log_time`
- `global_carbon_data`: `unique_country_year`, `idx_year`, `idx_country`

### 1.2 建议添加的索引

```sql
-- 为carbon_emissions表添加复合索引，优化按用户和日期查询
ALTER TABLE carbon_emissions ADD INDEX idx_user_date (user_id, emission_date);

-- 为energy_balls表添加复合索引，优化按用户和状态查询
ALTER TABLE energy_balls ADD INDEX idx_user_status (user_id, status);

-- 为friendships表添加复合索引，优化按用户和状态查询
ALTER TABLE friendships ADD INDEX idx_user_friend (user_id, friend_id, status);

-- 为system_logs表添加复合索引，优化按日志类型和时间查询
ALTER TABLE system_logs ADD INDEX idx_type_time (log_type, log_time);

-- 为watering_records表添加复合索引，优化按载体和时间查询
ALTER TABLE watering_records ADD INDEX idx_carrier_time (carrier_id, watering_time);
```

## 2. 表结构优化

### 2.1 用户表 (users)
- 建议将`company_name`和`industry`字段移到一个单独的`user_profiles`表中，因为这些字段只有企业用户才需要
- 优化数据类型：将`is_verified`从`tinyint(1)`改为`bit(1)`，更节省空间

### 2.2 碳账户表 (carbon_accounts)
- 考虑将`level`和`experience`字段移到一个单独的`user_levels`表中，因为这些字段与碳能量计算关系不大

### 2.3 能量球表 (energy_balls)
- 优化数据类型：将`energy_amount`从`int(11)`改为`smallint(5)`，如果能量球数量不会超过32767
- 考虑为地理位置字段添加空间索引，优化位置相关查询

### 2.4 系统日志表 (system_logs)
- 考虑对日志表进行分区，按`log_time`字段分区，优化历史日志查询性能

## 3. 存储过程和触发器优化

### 3.1 CalculateUserCarbonStats存储过程
- 建议添加`LIMIT`子句，避免返回过多数据
- 考虑添加缓存机制，减少重复计算

### 3.2 after_user_insert触发器
- 建议添加事务控制，确保碳账户和虚拟载体的创建要么都成功，要么都失败

### 3.3 update_energy_ball_status触发器
- 考虑改为定时任务，批量更新过期的能量球状态，减少触发器的性能开销

## 4. 数据存储优化

### 4.1 分区表
- 对大型表如`carbon_emissions`、`system_logs`和`energy_balls`进行分区，提高查询性能

### 4.2 归档策略
- 为过期数据（如超过一年的日志）制定归档策略，减少主表数据量

### 4.3 缓存策略
- 对频繁查询的数据（如排行榜、用户统计信息）添加缓存，减少数据库访问

## 5. 查询优化建议

### 5.1 避免使用SELECT *
- 始终明确指定需要查询的字段，减少数据传输和I/O开销

### 5.2 使用JOIN代替子查询
- 在大多数情况下，JOIN查询比子查询性能更好

### 5.3 合理使用索引
- 确保查询条件中的字段都有索引支持
- 避免在索引列上使用函数或计算

### 5.4 批量操作
- 对于大量数据的插入或更新，使用批量操作减少网络开销和事务开销

## 6. 性能监控和维护

### 6.1 定期运行OPTIMIZE TABLE
- 定期优化表，回收碎片空间

### 6.2 监控慢查询
- 启用慢查询日志，识别并优化性能差的查询

### 6.3 定期备份
- 确保有完善的备份策略，防止数据丢失

以上优化建议需要根据实际使用情况进行评估和实施，建议在非高峰时段进行修改，并进行充分的测试确保不会影响系统功能。