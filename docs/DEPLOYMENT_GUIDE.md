# 碳森林项目部署指南

## 环境要求

### 服务器环境
- PHP 7.4+ (推荐PHP 8.0+)
- MySQL 5.7+ (推荐MySQL 8.0+)
- Apache/Nginx Web服务器
- 支持URL重写(.htaccess)

### 宝塔面板环境
- PHP版本：7.4或8.0
- MySQL版本：5.7或8.0
- Web服务器：Apache或Nginx

## 部署步骤

### 1. 上传项目文件
将项目文件上传到Web服务器目录，例如：
- `/www/wwwroot/carbon_forest/` (宝塔面板)
- `/var/www/html/carbon_forest/` (Linux)

### 2. 配置数据库

#### 方法一：使用phpMyAdmin
1. 登录宝塔面板，打开phpMyAdmin
2. 创建新数据库：`carbon_forest`
3. 选择字符集：`utf8mb4_unicode_ci`
4. 导入SQL文件：执行 `database.sql`

#### 方法二：命令行导入
```bash
mysql -u root -p carbon_forest < database.sql
```

### 3. 配置数据库连接
编辑 `config.php` 文件，修改数据库配置：
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '你的数据库密码');
define('DB_NAME', 'carbon_forest');
```

### 4. 配置Web服务器

#### Apache配置
确保启用以下模块：
- mod_rewrite
- mod_headers

#### Nginx配置
在站点配置中添加：
```nginx
location /carbon_forest/ {
    try_files $uri $uri/ /carbon_forest/index.php?$query_string;
}

location ~ \.php$ {
    include fastcgi_params;
    fastcgi_pass unix:/tmp/php-cgi-74.sock;
    fastcgi_index index.php;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
}
```

### 5. 设置文件权限
```bash
# 设置上传目录权限
chmod 755 uploads/
chmod 755 logs/
chmod 644 config.php
```

### 6. 测试部署
1. 访问项目首页：`http://你的域名/carbon_forest/`
2. 测试注册功能
3. 测试登录功能
4. 测试碳排放计算

## 常见问题解决

### 1. 数据库连接失败
- 检查数据库用户名密码是否正确
- 检查数据库是否已创建
- 检查数据库服务是否启动

### 2. URL重写失败
- 检查Apache的mod_rewrite是否启用
- 检查.htaccess文件是否生效
- 检查Nginx配置是否正确

### 3. 文件上传失败
- 检查uploads目录权限
- 检查PHP上传文件大小限制
- 检查磁盘空间

### 4. 跨域请求失败
- 检查.htaccess中的CORS配置
- 检查浏览器安全策略

## 性能优化建议

### 1. 数据库优化
- 为常用查询字段添加索引
- 定期清理过期数据
- 使用数据库连接池

### 2. 缓存优化
- 启用OPcache
- 使用Redis缓存热点数据
- 静态资源使用CDN

### 3. 代码优化
- 压缩CSS/JS文件
- 启用Gzip压缩
- 优化图片资源

## 安全配置

### 1. 文件安全
- 禁止直接访问config.php
- 限制上传文件类型
- 定期备份数据库

### 2. 数据安全
- 使用HTTPS加密传输
- 定期更新JWT密钥
- 实施输入验证和过滤

### 3. 访问控制
- 限制API调用频率
- 实施用户权限控制
- 记录操作日志

## 监控和维护

### 1. 系统监控
- 监控服务器资源使用情况
- 监控数据库性能
- 监控API响应时间

### 2. 日志管理
- 定期检查系统日志
- 分析错误日志
- 清理过期日志文件

### 3. 数据备份
- 定期备份数据库
- 备份重要配置文件
- 测试备份恢复流程

## 更新和升级

### 1. 代码更新
- 备份当前版本
- 上传新版本文件
- 执行数据库迁移脚本
- 测试新功能

### 2. 数据库迁移
- 备份现有数据
- 执行迁移SQL
- 验证数据完整性

## 技术支持

如遇部署问题，请检查：
1. 错误日志：`logs/system.log`
2. PHP错误日志
3. Web服务器错误日志
4. 数据库错误日志

联系技术支持时，请提供：
- 错误日志内容
- 服务器环境信息
- 复现步骤

---

**注意：部署完成后，请立即修改默认密码和密钥配置，确保系统安全。**