# 碳森林项目文件目录结构

```
carbon_forest/
├── index.php                    # 主入口文件
├── .htaccess                   # Apache伪静态配置
├── config.php                  # 配置文件
├── database.sql                # 数据库建表SQL
├── README.md                   # 项目说明文档
├── assets/                     # 静态资源目录
│   ├── css/                    # 样式文件
│   │   ├── style.css           # 主样式文件
│   │   ├── bootstrap.min.css   # Bootstrap样式
│   │   └── animate.css         # 动画样式
│   ├── js/                     # JavaScript文件
│   │   ├── main.js             # 主逻辑文件
│   │   ├── carbon-calc.js      # 碳排放计算
│   │   ├── energy-anim.js      # 能量动画
│   │   └── utils.js            # 工具函数
│   ├── images/                 # 图片资源
│   │   ├── avatars/            # 用户头像
│   │   ├── trees/              # 虚拟载体图片
│   │   ├── energy-balls/       # 能量球图片
│   │   └── icons/              # 图标
│   └── fonts/                  # 字体文件
├── includes/                   # PHP类文件
│   ├── Database.php            # 数据库连接类
│   ├── User.php                # 用户操作类
│   ├── Carbon.php              # 碳排放计算类
│   ├── Energy.php              # 能量管理类
│   ├── Social.php              # 社交功能类
│   ├── Carrier.php             # 虚拟载体类
│   └── Donation.php            # 公益捐赠类
├── api/                        # API接口目录
│   ├── user_api.php            # 用户相关接口
│   ├── carbon_api.php          # 碳排放接口
│   ├── energy_api.php          # 能量管理接口
│   ├── social_api.php          # 社交功能接口
│   ├── carrier_api.php         # 虚拟载体接口
│   └── donation_api.php        # 公益捐赠接口
├── pages/                      # 页面文件
│   ├── login.php               # 登录页面
│   ├── register.php            # 注册页面
│   ├── carbon_stat.php         # 碳排放统计页面
│   ├── friends_forest.php      # 好友林页面
│   ├── tasks.php               # 任务中心页面
│   ├── donation.php            # 公益捐赠页面
│   └── ranking.php             # 排行榜页面
├── uploads/                    # 上传文件目录
│   ├── certificates/           # 捐赠证书
│   └── reports/                # 报告文件
└── logs/                       # 日志目录
    ├── error.log               # 错误日志
    └── carbon.log              # 碳排放日志
```