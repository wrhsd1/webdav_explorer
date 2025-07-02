<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/webdav.php';

Auth::requireLogin();

$config = Config::getInstance();
$accounts = $config->getAccounts();

// 获取参数
$accountKey = $_GET['account'] ?? '';
$filePath = $_GET['path'] ?? '';

if (empty($accountKey) || empty($filePath) || !isset($accounts[$accountKey])) {
    http_response_code(404);
    exit('文件不存在');
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
