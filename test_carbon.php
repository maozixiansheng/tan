<?php
/**
 * 碳记录功能API测试脚本
 */

require_once 'config.php';
require_once 'includes/Database.php';
require_once 'includes/Carbon.php';

// 设置错误报告
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 模拟测试用户
$testUserId = 7; // 使用实际存在的用户ID

// 测试结果数组
$testResults = [];

// 测试Carbon类的功能
function testCarbonClass() {
    global $testResults, $testUserId;
    
    echo "=== 测试Carbon类功能 ===\n";
    
    try {
        // 创建Carbon实例
        $carbon = new Carbon();
        $testResults[] = ['test' => '创建Carbon实例', 'status' => 'success', 'message' => 'Carbon实例创建成功'];
        echo "✅ Carbon实例创建成功\n";
        
        // 测试calculateEmission方法
        $emission = $carbon->calculateEmission(100, '算力');
        $testResults[] = ['test' => '测试calculateEmission方法', 'status' => 'success', 'message' => "计算成功: $emission kg CO₂"];
        echo "✅ calculateEmission方法测试成功: $emission kg CO₂\n";
        
        // 测试calculateEnergy方法
        $energy = $carbon->calculateEnergy(100, 80);
        $testResults[] = ['test' => '测试calculateEnergy方法', 'status' => 'success', 'message' => "计算成功: $energy 能量"];
        echo "✅ calculateEnergy方法测试成功: $energy 能量\n";
        
        // 测试recordEmission方法
        $recordData = [
            'emission_type' => '算力',
            'compute_power' => 100,
            'description' => '测试记录',
            'emission_date' => date('Y-m-d')
        ];
        $recordId = $carbon->recordEmission($testUserId, $recordData);
        $testResults[] = ['test' => '测试recordEmission方法', 'status' => 'success', 'message' => "记录成功，ID: $recordId"];
        echo "✅ recordEmission方法测试成功，记录ID: $recordId\n";
        
        // 测试getEmissionHistory方法
        $history = $carbon->getEmissionHistory($testUserId, ['limit' => 10, 'offset' => 0]);
        $testResults[] = ['test' => '测试getEmissionHistory方法', 'status' => 'success', 'message' => "获取到 {$history['total']} 条记录"];
        echo "✅ getEmissionHistory方法测试成功，获取到 {$history['total']} 条记录\n";
        
        // 测试getEmissionStats方法
        $stats = $carbon->getEmissionStats($testUserId);
        $testResults[] = ['test' => '测试getEmissionStats方法', 'status' => 'success', 'message' => "获取到统计数据"];
        echo "✅ getEmissionStats方法测试成功\n";
        
        // 测试updateCarbonFootprint方法
        $result = $carbon->updateCarbonFootprint($testUserId);
        $testResults[] = ['test' => '测试updateCarbonFootprint方法', 'status' => 'success', 'message' => "更新成功"];
        echo "✅ updateCarbonFootprint方法测试成功\n";
        
    } catch (Exception $e) {
        $testResults[] = ['test' => '测试Carbon类', 'status' => 'error', 'message' => $e->getMessage()];
        echo "❌ Carbon类测试失败: " . $e->getMessage() . "\n";
    }
}

// 测试数据库连接
function testDatabaseConnection() {
    global $testResults;
    
    echo "\n=== 测试数据库连接 ===\n";
    
    try {
        // 直接测试Carbon类的实例化，会间接测试数据库连接
        $carbon = new Carbon();
        $testResults[] = ['test' => '数据库连接', 'status' => 'success', 'message' => '数据库连接成功'];
        echo "✅ 数据库连接成功\n";
    } catch (Exception $e) {
        $testResults[] = ['test' => '数据库连接', 'status' => 'error', 'message' => $e->getMessage()];
        echo "❌ 数据库连接失败: " . $e->getMessage() . "\n";
    }
}

// 测试碳记录API接口
function testCarbonAPI() {
    global $testResults;
    
    echo "\n=== 测试碳记录API接口 ===\n";
    
    // 注意：这些API测试需要实际的HTTP请求，这里只做基本测试
    
    // 测试API文件是否存在
    $apiFiles = [
        'api/carbon/add.php',
        'api/carbon/list.php',
        'api/carbon/get.php',
        'api/carbon/update.php',
        'api/carbon/delete.php',
        'api/carbon/stats.php'
    ];
    
    foreach ($apiFiles as $apiFile) {
        if (file_exists($apiFile)) {
            $testResults[] = ['test' => "检查API文件: $apiFile", 'status' => 'success', 'message' => '文件存在'];
            echo "✅ API文件 $apiFile 存在\n";
        } else {
            $testResults[] = ['test' => "检查API文件: $apiFile", 'status' => 'error', 'message' => '文件不存在'];
            echo "❌ API文件 $apiFile 不存在\n";
        }
    }
}

// 测试前端页面
function testFrontendPages() {
    global $testResults;
    
    echo "\n=== 测试前端页面 ===\n";
    
    $pages = [
        'pages/emission.html'
    ];
    
    foreach ($pages as $page) {
        if (file_exists($page)) {
            $testResults[] = ['test' => "检查前端页面: $page", 'status' => 'success', 'message' => '页面存在'];
            echo "✅ 前端页面 $page 存在\n";
        } else {
            $testResults[] = ['test' => "检查前端页面: $page", 'status' => 'error', 'message' => '页面不存在'];
            echo "❌ 前端页面 $page 不存在\n";
        }
    }
}

// 执行所有测试
function runAllTests() {
    global $testResults;
    
    echo "\n\n=== 开始碳记录功能测试 ===\n\n";
    
    // 执行测试
    testDatabaseConnection();
    testCarbonClass();
    testCarbonAPI();
    testFrontendPages();
    
    // 输出测试结果
    echo "\n\n=== 测试结果汇总 ===\n";
    
    $totalTests = count($testResults);
    $passedTests = array_filter($testResults, function($result) { return $result['status'] === 'success'; });
    $failedTests = array_filter($testResults, function($result) { return $result['status'] === 'error'; });
    
    echo "总计测试: $totalTests\n";
    echo "通过测试: " . count($passedTests) . "\n";
    echo "失败测试: " . count($failedTests) . "\n";
    
    if (count($failedTests) > 0) {
        echo "\n❌ 失败的测试:\n";
        foreach ($failedTests as $test) {
            echo "   - " . $test['test'] . ": " . $test['message'] . "\n";
        }
    } else {
        echo "\n✅ 所有测试都通过了！\n";
    }
    
    echo "\n=== 测试完成 ===\n";
}

// 运行测试
runAllTests();
?>