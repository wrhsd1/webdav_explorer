<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/webdav.php';

Auth::requireLogin();

header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'data' => null, 'message' => ''];

try {
    $accountKey = $_GET['account'] ?? '';
    if (empty($accountKey)) {
        throw new Exception('账户参数缺失');
    }
    
    $config = Config::getInstance();
    $accounts = $config->getAccounts();
    
    if (!isset($accounts[$accountKey])) {
        throw new Exception('账户不存在');
    }
    
    $account = $accounts[$accountKey];
    $path = $_GET['path'] ?? '/';
    
    $webdav = new WebDAVClient(
        $account['host'],
        $account['username'],
        $account['password'],
        $account['path']
    );
    
    $storageInfo = $webdav->getStorageInfo($path);
    
    // 格式化存储信息
    if ($storageInfo['supported']) {
        $storageInfo['quota_total_formatted'] = $storageInfo['quota_total'] ? formatFileSize($storageInfo['quota_total']) : null;
        $storageInfo['quota_used_formatted'] = $storageInfo['quota_used'] ? formatFileSize($storageInfo['quota_used']) : null;
        $storageInfo['quota_available_formatted'] = $storageInfo['quota_available'] ? formatFileSize($storageInfo['quota_available']) : null;
        
        if ($storageInfo['quota_total'] && $storageInfo['quota_used']) {
            $storageInfo['usage_percent'] = ($storageInfo['quota_used'] / $storageInfo['quota_total']) * 100;
        } else {
            $storageInfo['usage_percent'] = 0;
        }
    }
    
    $response['success'] = true;
    $response['data'] = $storageInfo;
    $response['message'] = '存储信息获取成功';
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

function formatFileSize($bytes) {
    if ($bytes === null) return null;
    if ($bytes === 0) return '0 B';
    
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
