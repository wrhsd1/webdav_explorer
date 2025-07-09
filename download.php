<?php
require_once 'includes/auth.php';
require_once 'includes/user.php';
require_once 'includes/webdav.php';

Auth::requireLogin();

$userManager = new User();
$currentUserId = Auth::getCurrentUserId();

// 获取用户的WebDAV配置
$userWebdavConfigs = $userManager->getUserWebdavConfigs($currentUserId);

// 将配置转换为关联数组
$accounts = [];
foreach ($userWebdavConfigs as $config) {
    $accounts[$config['account_key']] = [
        'key' => $config['account_key'],
        'name' => $config['account_name'],
        'host' => $config['host'],
        'username' => $config['username'],
        'password' => $config['password'],
        'path' => $config['path']
    ];
}

// 获取参数
$accountKey = $_GET['account'] ?? '';
$filePath = $_GET['path'] ?? '';

if (empty($accountKey) || empty($filePath) || !isset($accounts[$accountKey])) {
    http_response_code(404);
    exit('文件不存在或无权限访问');
}

$account = $accounts[$accountKey];

try {
    $webdav = new WebDAVClient(
        $account['host'],
        $account['username'],
        $account['password'],
        $account['path']
    );
    
    // 下载文件
    $content = $webdav->downloadFile($filePath);
    
    // 设置下载头
    $fileName = basename($filePath);
    $fileSize = strlen($content);
    
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . $fileSize);
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    
    echo $content;
    
} catch (Exception $e) {
    http_response_code(500);
    echo '下载失败: ' . $e->getMessage();
}
?>
