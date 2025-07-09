<?php
session_start();

require_once 'includes/user.php';
require_once 'includes/webdav.php';

// 获取参数
$accountKey = $_GET['account'] ?? '';
$filePath = $_GET['path'] ?? '';

if (empty($accountKey) || empty($filePath)) {
    http_response_code(404);
    exit('参数错误');
}

// 检查用户会话
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('需要登录才能访问文件');
}

$userManager = new User();
$currentUserId = $_SESSION['user_id'];

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

if (!isset($accounts[$accountKey])) {
    http_response_code(404);
    exit('账户不存在或无权限访问');
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
    
    // 检测文件类型
    $fileName = basename($filePath);
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    $mimeTypes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'bmp' => 'image/bmp',
        'svg' => 'image/svg+xml',
        'mp4' => 'video/mp4',
        'avi' => 'video/x-msvideo',
        'mov' => 'video/quicktime',
        'wmv' => 'video/x-ms-wmv',
        'mkv' => 'video/x-matroska',
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav',
        'flac' => 'audio/flac',
        'aac' => 'audio/aac',
        'ogg' => 'audio/ogg',
        'pdf' => 'application/pdf',
        'txt' => 'text/plain',
        'html' => 'text/html',
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'xml' => 'application/xml',
        'zip' => 'application/zip',
        'rar' => 'application/x-rar-compressed',
        '7z' => 'application/x-7z-compressed',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation'
    ];
    
    $mimeType = isset($mimeTypes[$ext]) ? $mimeTypes[$ext] : 'application/octet-stream';
    
    // 设置响应头
    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . strlen($content));
    header('Cache-Control: public, max-age=3600');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    
    // 对于可以在浏览器中显示的文件类型，设置为inline
    $inlineTypes = ['image/', 'text/', 'application/pdf', 'video/', 'audio/'];
    $isInline = false;
    foreach ($inlineTypes as $type) {
        if (strpos($mimeType, $type) === 0) {
            $isInline = true;
            break;
        }
    }
    
    if ($isInline) {
        header('Content-Disposition: inline; filename="' . $fileName . '"');
    } else {
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
    }
    
    echo $content;
    
} catch (Exception $e) {
    http_response_code(500);
    echo '文件传输失败: ' . $e->getMessage();
}
?>
