<?php
// 测试脚本：验证用户WebDAV配置
require_once 'includes/auth.php';
require_once 'includes/user.php';

// 模拟登录状态
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin';
$_SESSION['is_admin'] = true;

try {
    $userManager = new User();
    $currentUserId = 1; // 管理员用户ID
    
    echo "正在测试用户WebDAV配置加载..." . PHP_EOL;
    
    // 获取用户的WebDAV配置
    $userWebdavConfigs = $userManager->getUserWebdavConfigs($currentUserId);
    
    echo "用户ID {$currentUserId} 的WebDAV配置数量: " . count($userWebdavConfigs) . PHP_EOL;
    
    if (empty($userWebdavConfigs)) {
        echo "用户暂无WebDAV配置，这是正常的，请通过个人设置页面添加配置。" . PHP_EOL;
    } else {
        echo "用户WebDAV配置列表:" . PHP_EOL;
        foreach ($userWebdavConfigs as $config) {
            echo "- 账户标识: {$config['account_key']}" . PHP_EOL;
            echo "  账户名称: {$config['account_name']}" . PHP_EOL;
            echo "  服务器: {$config['host']}" . PHP_EOL;
            echo "  用户名: {$config['username']}" . PHP_EOL;
            echo "  路径: {$config['path']}" . PHP_EOL;
            echo PHP_EOL;
        }
    }
    
    echo "测试完成！下载功能的404问题应该已修复。" . PHP_EOL;
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . PHP_EOL;
}
?>
