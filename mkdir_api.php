<?php
header('Content-Type: application/json; charset=utf-8');

require_once 'includes/user.php';
require_once 'includes/webdav.php';
require_once 'includes/config.php';

// 处理跨域请求
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/**
 * 记录API访问日志
 */
function logApiAccess($user, $method, $endpoint, $success, $message = '') {
    $logData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'user' => $user ? $user['username'] : 'unknown',
        'method' => $method,
        'endpoint' => $endpoint,
        'success' => $success,
        'message' => $message,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ];
    
    $logFile = __DIR__ . '/data/api_access.log';
    file_put_contents($logFile, json_encode($logData) . "\n", FILE_APPEND | LOCK_EX);
}

/**
 * 返回JSON响应
 */
function jsonResponse($success, $data = null, $message = '', $code = 200, $user = null) {
    global $method, $dirPath;
    
    // 记录访问日志
    if ($user !== false) { // false表示不记录日志
        logApiAccess($user, $method ?? $_SERVER['REQUEST_METHOD'], $dirPath ?? '/', $success, $message);
    }
    
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 验证API密钥并获取用户信息
 */
function authenticateApiKey($apiKey) {
    if (empty($apiKey)) {
        jsonResponse(false, null, 'API密钥不能为空', 401, false);
    }
    
    try {
        $userManager = new User();
        $user = $userManager->getUserByApiKey($apiKey);
        
        if (!$user) {
            jsonResponse(false, null, 'API密钥无效', 401, false);
        }
        
        return $user;
    } catch (Exception $e) {
        jsonResponse(false, null, '认证失败: ' . $e->getMessage(), 500, false);
    }
}

/**
 * 获取用户的WebDAV配置
 */
function getUserWebdavConfig($userId, $accountKey, $user = null) {
    try {
        $userManager = new User();
        $config = $userManager->getUserWebdavConfig($userId, $accountKey);
        
        if (!$config) {
            jsonResponse(false, null, 'WebDAV账户不存在或无权限访问', 404, $user);
        }
        
        return $config;
    } catch (Exception $e) {
        jsonResponse(false, null, '获取WebDAV配置失败: ' . $e->getMessage(), 500, $user);
    }
}

/**
 * 创建多层级目录
 */
function createDirectoriesRecursively($webdav, $path) {
    if (empty($path)) return;
    
    $pathParts = explode('/', trim($path, '/'));
    $currentPath = '';
    
    foreach ($pathParts as $part) {
        if (empty($part)) continue;
        
        $currentPath .= '/' . $part;
        
        try {
            // 尝试列出目录，如果成功说明目录已存在
            $webdav->listDirectory($currentPath);
        } catch (Exception $e) {
            // 目录不存在，尝试创建它
            try {
                $webdav->createDirectory($currentPath);
            } catch (Exception $createError) {
                // 检查是否是目录已存在的错误（HTTP 405 Method Not Allowed 或 409 Conflict）
                $errorMsg = $createError->getMessage();
                if (strpos($errorMsg, '405') !== false || strpos($errorMsg, '409') !== false) {
                    // 目录可能已存在，忽略这个错误
                    continue;
                } else {
                    // 其他错误，重新抛出
                    throw $createError;
                }
            }
        }
    }
}

// 只允许POST方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, null, '只支持POST请求方法', 405, false);
}

// 获取请求参数
$method = $_SERVER['REQUEST_METHOD'];
$apiKey = $_POST['apikey'] ?? '';
$webdavAccount = $_POST['webdav_account'] ?? '';
$dirPath = $_POST['dir_path'] ?? '';
$dirName = $_POST['dir_name'] ?? '';
$recursive = $_POST['recursive'] ?? 'true'; // 是否递归创建父目录

// 参数验证
if (empty($dirPath) && empty($dirName)) {
    jsonResponse(false, null, '请提供目录路径(dir_path)或目录名称(dir_name)', 400, false);
}

// 验证API密钥
$user = authenticateApiKey($apiKey);

// 验证WebDAV账户
$webdavConfig = getUserWebdavConfig($user['id'], $webdavAccount, $user);

// 创建WebDAV客户端
try {
    $webdav = new WebDAVClient(
        $webdavConfig['host'],
        $webdavConfig['username'],
        $webdavConfig['password'],
        $webdavConfig['path']
    );
} catch (Exception $e) {
    jsonResponse(false, null, 'WebDAV连接失败: ' . $e->getMessage(), 500, $user);
}

// 新建文件夹API
try {
    $targetPath = '';
    
    // 处理目录路径
    if (!empty($dirName)) {
        // 如果提供了目录名称，在指定路径下创建
        $basePath = trim($dirPath, '/');
        if (!empty($basePath)) {
            $targetPath = $basePath . '/' . $dirName;
        } else {
            $targetPath = $dirName;
        }
    } else {
        // 如果只提供了路径，直接创建该路径
        $targetPath = trim($dirPath, '/');
    }
    
    if (empty($targetPath)) {
        jsonResponse(false, null, '目录路径不能为空', 400, $user);
    }
    
    // 验证目录名称（不能包含非法字符）
    $invalidChars = ['<', '>', ':', '"', '|', '?', '*'];
    foreach ($invalidChars as $char) {
        if (strpos($targetPath, $char) !== false) {
            jsonResponse(false, null, '目录名称包含非法字符: ' . $char, 400, $user);
        }
    }
    
    // 检查目录是否已存在
    try {
        $webdav->listDirectory($targetPath);
        jsonResponse(false, [
            'dir_path' => $targetPath,
            'exists' => true
        ], '目录已存在', 409, $user);
    } catch (Exception $e) {
        // 目录不存在，继续创建流程
    }
    
    // 创建目录
    if ($recursive === 'true' || $recursive === true) {
        // 递归创建（包括父目录）
        createDirectoriesRecursively($webdav, $targetPath);
        $createMethod = 'recursive';
    } else {
        // 只创建指定目录（要求父目录已存在）
        $webdav->createDirectory($targetPath);
        $createMethod = 'single';
    }
    
    // 验证创建成功
    try {
        $webdav->listDirectory($targetPath);
        $createSuccess = true;
    } catch (Exception $e) {
        $createSuccess = false;
    }
    
    if (!$createSuccess) {
        jsonResponse(false, null, '目录创建失败：无法验证目录是否创建成功', 500, $user);
    }
    
    jsonResponse(true, [
        'dir_path' => $targetPath,
        'dir_name' => basename($targetPath),
        'parent_path' => dirname($targetPath) === '.' ? '/' : dirname($targetPath),
        'webdav_account' => $webdavAccount,
        'create_method' => $createMethod,
        'recursive' => $recursive === 'true' || $recursive === true
    ], '目录创建成功', 201, $user);
    
} catch (Exception $e) {
    jsonResponse(false, [
        'target_path' => $targetPath ?? '',
        'error' => $e->getMessage()
    ], '创建目录失败: ' . $e->getMessage(), 500, $user);
}
?>
