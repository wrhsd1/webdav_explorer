<?php
header('Content-Type: application/json; charset=utf-8');

require_once 'includes/user.php';
require_once 'includes/webdav.php';
require_once 'includes/config.php';

// 处理跨域请求
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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
    global $method, $filePath;
    
    // 记录访问日志
    if ($user !== false) { // false表示不记录日志
        logApiAccess($user, $method ?? $_SERVER['REQUEST_METHOD'], $filePath ?? '/', $success, $message);
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
 * 生成直链URL
 */
function generateDirectLink($accountKey, $filePath) {
    $config = Config::getInstance();
    $domain = $config->get('DIRECT_LINK_DOMAIN', 'http://localhost');
    $domain = rtrim($domain, '/');
    
    return $domain . '/transfer.php?account=' . urlencode($accountKey) . '&path=' . urlencode($filePath);
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

/**
 * 从URL下载文件内容
 */
function downloadFromUrl($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_USERAGENT, 'WebDAV API Client');
    
    $content = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        throw new Exception("下载失败: " . $error);
    }
    
    if ($httpCode !== 200) {
        throw new Exception("下载失败: HTTP " . $httpCode);
    }
    
    return $content;
}

// 获取请求参数
$method = $_SERVER['REQUEST_METHOD'];
$apiKey = $_GET['apikey'] ?? $_POST['apikey'] ?? '';
$webdavAccount = $_GET['webdav_account'] ?? $_POST['webdav_account'] ?? '';
$filePath = $_GET['file_path'] ?? $_POST['file_path'] ?? '/';

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

if ($method === 'POST') {
    // 上传API
    try {
        $uploadedFile = null;
        $fileName = '';
        $fileContent = '';
        
        // 检查是否有上传的文件
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $uploadedFile = $_FILES['file'];
            $fileName = $uploadedFile['name'];
            $fileContent = file_get_contents($uploadedFile['tmp_name']);
        }
        // 检查是否有文件URL
        elseif (!empty($_POST['file_url'])) {
            $fileUrl = $_POST['file_url'];
            $fileName = basename(parse_url($fileUrl, PHP_URL_PATH));
            
            if (empty($fileName)) {
                $fileName = 'downloaded_file_' . date('YmdHis');
            }
            
            $fileContent = downloadFromUrl($fileUrl);
        } else {
            jsonResponse(false, null, '请提供要上传的文件或文件URL', 400, $user);
        }
        
        // 处理文件路径
        $targetPath = trim($filePath, '/');
        $fullFilePath = '';
        
        // 判断用户提供的路径是目录还是完整文件路径
        if (substr($filePath, -1) === '/' || empty($targetPath)) {
            // 用户提供的是目录路径，使用原始文件名
            if (!empty($targetPath)) {
                $fullFilePath = $targetPath . '/' . $fileName;
            } else {
                $fullFilePath = $fileName;
            }
            $dirToCreate = $targetPath;
        } else {
            // 用户提供的可能是完整文件路径
            $pathInfo = pathinfo($targetPath);
            $dirPath = $pathInfo['dirname'];
            $providedFileName = $pathInfo['basename'];
            
            // 如果提供的文件名有扩展名，使用提供的文件名，否则作为目录处理
            if (strpos($providedFileName, '.') !== false) {
                $fileName = $providedFileName;
                $fullFilePath = $targetPath;
                $dirToCreate = ($dirPath !== '.' && $dirPath !== '') ? $dirPath : '';
            } else {
                // 没有扩展名，当作目录处理
                $fullFilePath = $targetPath . '/' . $fileName;
                $dirToCreate = $targetPath;
            }
        }
        
        // 创建目录结构（如果需要）
        if (!empty($dirToCreate)) {
            createDirectoriesRecursively($webdav, $dirToCreate);
        }
        
        // 上传文件
        try {
            $webdav->uploadFile($fullFilePath, $fileContent);
        } catch (Exception $uploadError) {
            // 如果上传失败，记录详细错误信息
            $errorDetails = [
                'target_path' => $targetPath,
                'full_file_path' => $fullFilePath,
                'file_name' => $fileName,
                'dir_to_create' => $dirToCreate ?? 'none',
                'original_file_path' => $filePath,
                'error' => $uploadError->getMessage()
            ];
            
            jsonResponse(false, $errorDetails, '上传失败: ' . $uploadError->getMessage(), 500, $user);
        }
        
        // 生成直链
        $directLink = generateDirectLink($webdavAccount, $fullFilePath);
        
        jsonResponse(true, [
            'file_path' => $fullFilePath,
            'file_name' => $fileName,
            'file_size' => strlen($fileContent),
            'direct_link' => $directLink,
            'webdav_account' => $webdavAccount
        ], '文件上传成功', 200, $user);
        
    } catch (Exception $e) {
        jsonResponse(false, null, '上传失败: ' . $e->getMessage(), 500, $user);
    }
    
} elseif ($method === 'GET') {
    // 下载/列表API
    try {
        $items = $webdav->listDirectory($filePath);
        
        // 处理返回数据，添加直链信息
        $result = [];
        foreach ($items as $item) {
            $itemData = [
                'name' => $item['name'],
                'path' => $item['path'],
                'is_directory' => $item['is_dir'],
                'type' => $item['is_dir'] ? 'directory' : 'file',
                'size' => $item['size'],
                'modified' => $item['modified'],
                'file_type' => $item['type'] ?? 'unknown'
            ];
            
            // 如果是文件，添加直链
            if (!$item['is_dir']) {
                $itemData['direct_link'] = generateDirectLink($webdavAccount, $item['path']);
            }
            
            $result[] = $itemData;
        }
        
        jsonResponse(true, [
            'path' => $filePath,
            'webdav_account' => $webdavAccount,
            'items' => $result,
            'total_count' => count($result),
            'file_count' => count(array_filter($result, function($item) { return !$item['is_directory']; })),
            'directory_count' => count(array_filter($result, function($item) { return $item['is_directory']; }))
        ], '获取文件列表成功', 200, $user);
        
    } catch (Exception $e) {
        jsonResponse(false, null, '获取文件列表失败: ' . $e->getMessage(), 500, $user);
    }
    
} else {
    jsonResponse(false, null, '不支持的请求方法', 405, $user);
}
?>
