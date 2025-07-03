<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/webdav.php';

Auth::requireLogin();

$config = Config::getInstance();
$accounts = $config->getAccounts();

// 获取当前账户
$currentAccountKey = $_GET['account'] ?? '';
if (empty($currentAccountKey) || !isset($accounts[$currentAccountKey])) {
    header('Location: index.php');
    exit;
}

$currentAccount = $accounts[$currentAccountKey];
$currentPath = $_GET['path'] ?? '/';
$currentPath = '/' . trim($currentPath, '/');
if ($currentPath !== '/') {
    $currentPath .= '/';
}

$webdav = new WebDAVClient(
    $currentAccount['host'],
    $currentAccount['username'],
    $currentAccount['password'],
    $currentAccount['path']
);

$message = '';
$error = '';

// 处理操作
if ($_POST) {
    try {
        switch ($_POST['action']) {
            case 'upload':
                if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
                    $fileName = $_FILES['file']['name'];
                    $content = file_get_contents($_FILES['file']['tmp_name']);
                    $uploadPath = rtrim($currentPath, '/') . '/' . $fileName;
                    
                    $webdav->uploadFile($uploadPath, $content);
                    $message = "文件 {$fileName} 上传成功";
                }
                break;
                
            case 'create_folder':
                $folderName = $_POST['folder_name'] ?? '';
                if (!empty($folderName)) {
                    $folderPath = rtrim($currentPath, '/') . '/' . $folderName;
                    $webdav->createDirectory($folderPath);
                    $message = "文件夹 {$folderName} 创建成功";
                }
                break;
                
            case 'delete':
                $deletePath = $_POST['path'] ?? '';
                if (!empty($deletePath)) {
                    $webdav->deleteItem($deletePath);
                    $message = "删除成功";
                }
                break;
                
            case 'rename':
                $oldPath = $_POST['old_path'] ?? '';
                $newName = $_POST['new_name'] ?? '';
                if (!empty($oldPath) && !empty($newName)) {
                    $pathDir = dirname($oldPath);
                    $newPath = ($pathDir === '/' ? '/' : $pathDir . '/') . $newName;
                    $webdav->moveItem($oldPath, $newPath);
                    $message = "重命名成功";
                }
                break;
                
            case 'copy':
                $sourcePath = $_POST['source_path'] ?? '';
                $targetPath = $_POST['target_path'] ?? '';
                if (!empty($sourcePath) && !empty($targetPath)) {
                    $webdav->copyItem($sourcePath, $targetPath);
                    $message = "复制成功";
                }
                break;
                
            case 'move':
                $sourcePath = $_POST['source_path'] ?? '';
                $targetPath = $_POST['target_path'] ?? '';
                if (!empty($sourcePath) && !empty($targetPath)) {
                    $webdav->moveItem($sourcePath, $targetPath);
                    $message = "移动成功";
                }
                break;
                
            case 'bulk_delete':
                $paths = $_POST['selected_paths'] ?? [];
                if (!empty($paths)) {
                    foreach ($paths as $path) {
                        $webdav->deleteItem($path);
                    }
                    $message = "批量删除成功";
                }
                break;
        }
        
        // 重定向避免重复提交
        header('Location: ?account=' . urlencode($currentAccountKey) . '&path=' . urlencode($currentPath) . '&msg=' . urlencode($message));
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// 从URL获取消息
if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
}

// 获取文件列表
try {
    $items = $webdav->listDirectory($currentPath);
    
    // 排序：文件夹在前，然后按名称排序
    usort($items, function($a, $b) {
        if ($a['is_dir'] !== $b['is_dir']) {
            return $b['is_dir'] - $a['is_dir'];
        }
        return strcasecmp($a['name'], $b['name']);
    });
} catch (Exception $e) {
    $error = $e->getMessage();
    $items = [];
}

// 获取存储空间信息
$storageInfo = null;
try {
    $storageInfo = $webdav->getStorageInfo($currentPath);
} catch (Exception $e) {
    // 忽略存储信息获取错误
}

// 构建面包屑导航
$pathParts = array_filter(explode('/', $currentPath));
$breadcrumbs = [];
$breadcrumbPath = '';
$breadcrumbs[] = ['name' => '根目录', 'path' => '/'];
foreach ($pathParts as $part) {
    $breadcrumbPath .= '/' . $part;
    $breadcrumbs[] = ['name' => $part, 'path' => $breadcrumbPath];
}

// 文件大小格式化函数
function formatFileSize($bytes) {
    if ($bytes == 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
}

// 格式化时间
function formatDateTime($dateString) {
    if (empty($dateString)) return '-';
    try {
        $date = new DateTime($dateString);
        return $date->format('Y-m-d H:i');
    } catch (Exception $e) {
        return '-';
    }
}

// 获取文件扩展名
function getFileExtension($filename) {
    return strtoupper(pathinfo($filename, PATHINFO_EXTENSION));
}

// 判断文件是否可预览
function isPreviewable($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $previewableTypes = [
        'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'],
        'text' => ['txt', 'md', 'html', 'css', 'js', 'json', 'xml', 'csv'],
        'code' => ['php', 'py', 'java', 'cpp', 'c', 'h', 'sql', 'yaml', 'yml'],
        'pdf' => ['pdf'],
        'video' => ['mp4', 'webm', 'ogg'],
        'audio' => ['mp3', 'wav', 'ogg', 'aac']
    ];
    
    foreach ($previewableTypes as $type => $extensions) {
        if (in_array($ext, $extensions)) {
            return $type;
        }
    }
    return false;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>文件管理器 - <?php echo htmlspecialchars($currentAccount['name']); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8fafc;
            min-height: 100vh;
            line-height: 1.5;
        }
        
        .header {
            background: white;
            padding: 1rem 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header h1 {
            color: #1a202c;
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .header-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .storage-info {
            background: #e6fffa;
            border: 1px solid #38b2ac;
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            margin: 1rem 0;
            font-size: 0.875rem;
        }
        
        .storage-bar {
            background: #e2e8f0;
            border-radius: 1rem;
            height: 0.5rem;
            margin: 0.5rem 0;
            overflow: hidden;
        }
        
        .storage-bar-fill {
            background: linear-gradient(90deg, #48bb78, #38a169);
            height: 100%;
            transition: width 0.3s ease;
            border-radius: 1rem;
        }
        
        .storage-bar-fill.warning {
            background: linear-gradient(90deg, #ed8936, #dd6b20);
        }
        
        .storage-bar-fill.danger {
            background: linear-gradient(90deg, #f56565, #e53e3e);
        }
        
        .storage-details {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 0.5rem;
            font-size: 0.75rem;
            color: #4a5568;
        }
        
        .storage-info.unavailable {
            background: #fed7d7;
            border-color: #fc8181;
            color: #742a2a;
        }
        
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary { background: #3182ce; color: white; }
        .btn-secondary { background: #718096; color: white; }
        .btn-success { background: #38a169; color: white; }
        .btn-danger { background: #e53e3e; color: white; }
        .btn-warning { background: #d69e2e; color: white; }
        .btn-info { background: #3182ce; color: white; }
        
        .btn:hover {
            transform: translateY(-1px);
            filter: brightness(110%);
        }
        
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .toolbar {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .toolbar-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex: 1;
            min-width: 200px;
        }
        
        .breadcrumb a {
            color: #3182ce;
            text-decoration: none;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            transition: background-color 0.2s;
        }
        
        .breadcrumb a:hover {
            background: #ebf8ff;
        }
        
        .breadcrumb span {
            color: #4a5568;
        }
        
        .view-controls {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .search-box {
            display: flex;
            align-items: center;
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 0.5rem;
            min-width: 250px;
        }
        
        .search-box input {
            border: none;
            background: transparent;
            outline: none;
            flex: 1;
            padding: 0.25rem;
            font-size: 0.875rem;
        }
        
        .toolbar-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
        }
        
        .file-input {
            position: absolute;
            left: -9999px;
        }
        
        .file-input-label {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: #38a169;
            color: white;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .file-input-label:hover {
            background: #2f855a;
            transform: translateY(-1px);
        }
        
        .alert {
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 6px;
            border-left: 4px solid;
        }
        
        .alert-success {
            background: #f0fff4;
            color: #22543d;
            border-left-color: #38a169;
        }
        
        .alert-danger {
            background: #fed7d7;
            color: #742a2a;
            border-left-color: #e53e3e;
        }
        
        .file-list {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .file-list-header {
            background: #f7fafc;
            padding: 1rem;
            border-bottom: 1px solid #e2e8f0;
            display: grid;
            grid-template-columns: 40px auto 120px 100px 140px 200px;
            gap: 1rem;
            align-items: center;
            font-weight: 600;
            color: #4a5568;
            font-size: 0.875rem;
        }
        
        .file-list-item {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #f7fafc;
            display: grid;
            grid-template-columns: 40px auto 120px 100px 140px 200px;
            gap: 1rem;
            align-items: center;
            transition: background-color 0.2s;
        }
        
        .file-list-item:hover {
            background: #f7fafc;
        }
        
        .file-list-item:last-child {
            border-bottom: none;
        }
        
        .file-checkbox {
            margin: 0;
        }
        
        .file-icon-small {
            font-size: 1.5rem;
            text-align: center;
        }
        
        .file-name-cell {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            min-width: 0;
        }
        
        .file-name-text {
            font-weight: 500;
            color: #2d3748;
            text-overflow: ellipsis;
            overflow: hidden;
            white-space: nowrap;
            cursor: pointer;
        }
        
        .file-name-text:hover {
            color: #3182ce;
        }
        
        .file-size-cell {
            color: #718096;
            font-size: 0.875rem;
            text-align: right;
        }
        
        .file-type-cell {
            color: #718096;
            font-size: 0.875rem;
            text-transform: uppercase;
            font-weight: 500;
        }
        
        .file-date-cell {
            color: #718096;
            font-size: 0.875rem;
        }
        
        .file-actions-cell {
            display: flex;
            gap: 0.25rem;
            justify-content: flex-end;
        }
        
        .bulk-actions {
            background: #bee3f8;
            padding: 1rem;
            border-bottom: 1px solid #90cdf4;
            display: none;
            align-items: center;
            gap: 1rem;
        }
        
        .bulk-actions.show {
            display: flex;
        }
        
        .empty-folder {
            text-align: center;
            padding: 4rem 2rem;
            color: #718096;
        }
        
        .empty-folder h3 {
            font-size: 1.25rem;
            margin-bottom: 0.5rem;
            color: #4a5568;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 2rem;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .modal-header h3 {
            color: #2d3748;
            font-size: 1.25rem;
            font-weight: 600;
        }
        
        .close {
            color: #a0aec0;
            font-size: 1.5rem;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.2s;
        }
        
        .close:hover {
            color: #4a5568;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #4a5568;
        }
        
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            outline: none;
            transition: border-color 0.2s;
        }
        
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            border-color: #3182ce;
            box-shadow: 0 0 0 3px rgba(49, 130, 206, 0.1);
        }
        
        .modal-actions {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
            margin-top: 1.5rem;
        }
        
        .preview-modal .modal-content {
            max-width: 90vw;
            max-height: 90vh;
            overflow: auto;
        }
        
        .preview-content {
            text-align: center;
        }
        
        .preview-content img {
            max-width: 100%;
            max-height: 70vh;
            border-radius: 6px;
        }
        
        .preview-content video, .preview-content audio {
            max-width: 100%;
            border-radius: 6px;
        }
        
        .preview-content pre {
            text-align: left;
            background: #f7fafc;
            padding: 1rem;
            border-radius: 6px;
            overflow: auto;
            max-height: 60vh;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .file-list-header, .file-list-item {
                grid-template-columns: auto 120px 100px;
                gap: 0.5rem;
            }
            
            .file-size-cell, .file-type-cell, .file-date-cell {
                display: none;
            }
            
            .toolbar-top {
                flex-direction: column;
                align-items: stretch;
            }
            
            .view-controls {
                justify-content: space-between;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📁 文件管理器</h1>
        <div class="header-actions">
            <span>当前账户: <strong><?php echo htmlspecialchars($currentAccount['name']); ?></strong></span>
            <a href="index.php" class="btn btn-secondary">返回首页</a>
        </div>
    </div>

    <div class="container">
        <?php if ($storageInfo): ?>
            <?php if ($storageInfo['supported'] && ($storageInfo['quota_total'] > 0 || $storageInfo['quota_used'] > 0)): ?>
                <div class="storage-info">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <span>💾</span>
                            <strong>存储空间</strong>
                            <?php if (isset($storageInfo['method'])): ?>
                                <span style="font-size: 0.75rem; color: #718096;">(<?php echo htmlspecialchars($storageInfo['method']); ?>)</span>
                            <?php endif; ?>
                        </div>
                        <button id="refreshStorageBtn" onclick="refreshStorageInfo()" 
                                style="background: none; border: 1px solid #38b2ac; color: #38b2ac; padding: 0.25rem 0.5rem; border-radius: 0.25rem; cursor: pointer; font-size: 0.75rem;">
                            🔄 刷新
                        </button>
                    </div>
                    
                    <?php if ($storageInfo['quota_total']): ?>
                        <?php 
                            $usedPercent = $storageInfo['quota_used'] && $storageInfo['quota_total'] 
                                ? ($storageInfo['quota_used'] / $storageInfo['quota_total']) * 100 
                                : 0;
                            $barClass = '';
                            if ($usedPercent >= 90) $barClass = 'danger';
                            elseif ($usedPercent >= 75) $barClass = 'warning';
                        ?>
                        
                        <div class="storage-bar">
                            <div class="storage-bar-fill <?php echo $barClass; ?>" 
                                 style="width: <?php echo min($usedPercent, 100); ?>%"></div>
                        </div>
                        
                        <div class="storage-details">
                            <span>
                                已使用: <?php echo formatFileSize($storageInfo['quota_used'] ?? 0); ?>
                                / <?php echo formatFileSize($storageInfo['quota_total']); ?>
                            </span>
                            <span>
                                可用: <?php echo formatFileSize($storageInfo['quota_available'] ?? 0); ?>
                                (<?php echo number_format(100 - $usedPercent, 1); ?>%)
                            </span>
                        </div>
                    <?php else: ?>
                        <div style="margin-top: 0.5rem; color: #4a5568;">
                            <?php if ($storageInfo['quota_used']): ?>
                                已使用: <?php echo formatFileSize($storageInfo['quota_used']); ?>
                            <?php endif; ?>
                            <?php if ($storageInfo['quota_available']): ?>
                                可用: <?php echo formatFileSize($storageInfo['quota_available']); ?>
                            <?php endif; ?>
                            <?php if (!$storageInfo['quota_used'] && !$storageInfo['quota_available']): ?>
                                存储配额信息部分可用
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php elseif ($storageInfo['supported'] && isset($storageInfo['method']) && $storageInfo['method'] === 'estimate'): ?>
                <div class="storage-info" style="background: #fff5e6; border-color: #ed8936;">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <span>📊</span>
                            <strong>存储估算</strong>
                            <span style="font-size: 0.75rem; color: #718096;">(文件扫描)</span>
                        </div>
                        <button id="refreshStorageBtn" onclick="refreshStorageInfo()" 
                                style="background: none; border: 1px solid #ed8936; color: #ed8936; padding: 0.25rem 0.5rem; border-radius: 0.25rem; cursor: pointer; font-size: 0.75rem;">
                            🔄 刷新
                        </button>
                    </div>
                    <div style="margin-top: 0.5rem; color: #4a5568;">
                        <?php if ($storageInfo['quota_used']): ?>
                            当前目录使用: <?php echo formatFileSize($storageInfo['quota_used']); ?>
                        <?php endif; ?>
                        <div style="font-size: 0.75rem; color: #718096; margin-top: 0.25rem;">
                            <?php echo htmlspecialchars($storageInfo['message']); ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="storage-info unavailable">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <span>⚠️</span>
                        <span><?php echo htmlspecialchars($storageInfo['message']); ?></span>
                        <button onclick="document.querySelector('.storage-info').style.display='none'" 
                                style="background: none; border: none; color: #742a2a; cursor: pointer; margin-left: auto;">
                            ✕
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="toolbar">
            <div class="toolbar-top">
                <div class="breadcrumb">
                    <?php foreach ($breadcrumbs as $i => $crumb): ?>
                        <?php if ($i > 0): ?><span>/</span><?php endif; ?>
                        <?php if ($crumb['path'] === $currentPath): ?>
                            <span><?php echo htmlspecialchars($crumb['name']); ?></span>
                        <?php else: ?>
                            <a href="?account=<?php echo urlencode($currentAccountKey); ?>&path=<?php echo urlencode($crumb['path']); ?>">
                                <?php echo htmlspecialchars($crumb['name']); ?>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                
                <div class="view-controls">
                    <div class="search-box">
                        <input type="text" id="searchInput" placeholder="搜索文件和文件夹..." onkeyup="filterFiles()">
                        <span>🔍</span>
                    </div>
                </div>
            </div>
            
            <div class="toolbar-actions">
                <form class="file-input-wrapper" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload">
                    <input type="file" id="file-upload" name="file" class="file-input" onchange="this.form.submit()">
                    <label for="file-upload" class="file-input-label">
                        📤 上传文件
                    </label>
                </form>
                
                <button onclick="showModal('createFolderModal')" class="btn btn-primary">
                    📁 新建文件夹
                </button>
                
                <button onclick="showModal('uploadUrlModal')" class="btn btn-info">
                    🔗 URL上传
                </button>
                
                <button onclick="toggleSelectAll()" class="btn btn-secondary" id="selectAllBtn">
                    ☑️ 全选
                </button>
                
                <button onclick="showBulkActions()" class="btn btn-warning" id="bulkActionsBtn" style="display: none;">
                    🔧 批量操作
                </button>
            </div>
        </div>
        
        <?php if (empty($items)): ?>
            <div class="empty-folder">
                <h3>📂 空文件夹</h3>
                <p>这个文件夹还没有任何内容</p>
            </div>
        <?php else: ?>
            <div class="file-list">
                <div class="bulk-actions" id="bulkActions">
                    <span id="selectedCount">0</span> 个文件已选择
                    <button onclick="bulkDelete()" class="btn btn-danger btn-sm">删除选中</button>
                    <button onclick="bulkDownload()" class="btn btn-success btn-sm">下载选中</button>
                    <button onclick="clearSelection()" class="btn btn-secondary btn-sm">取消选择</button>
                </div>
                
                <div class="file-list-header">
                    <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                    <div>名称</div>
                    <div>大小</div>
                    <div>类型</div>
                    <div>修改时间</div>
                    <div>操作</div>
                </div>
                
                <?php foreach ($items as $item): ?>
                    <div class="file-list-item" data-name="<?php echo strtolower(htmlspecialchars($item['name'])); ?>">
                        <input type="checkbox" class="file-checkbox" value="<?php echo htmlspecialchars($item['path']); ?>" onchange="updateSelection()">
                        
                        <div class="file-name-cell">
                            <span class="file-icon-small">
                                <?php
                                $icons = [
                                    'directory' => '📁',
                                    'image' => '🖼️',
                                    'video' => '🎬',
                                    'audio' => '🎵',
                                    'document' => '📄',
                                    'archive' => '📦',
                                    'code' => '💻',
                                    'text' => '📝',
                                    'file' => '📄'
                                ];
                                echo $icons[$item['type']] ?? '📄';
                                ?>
                            </span>
                            <?php if ($item['is_dir']): ?>
                                <a href="?account=<?php echo urlencode($currentAccountKey); ?>&path=<?php echo urlencode($item['path']); ?>" 
                                   class="file-name-text">
                                    <?php echo htmlspecialchars($item['name']); ?>
                                </a>
                            <?php else: ?>
                                <?php if (isPreviewable($item['name'])): ?>
                                    <span class="file-name-text" 
                                          data-path="<?php echo htmlspecialchars($item['path']); ?>" 
                                          data-name="<?php echo htmlspecialchars($item['name']); ?>"
                                          onclick="previewFile(this.dataset.path, this.dataset.name)">
                                        <?php echo htmlspecialchars($item['name']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="file-name-text">
                                        <?php echo htmlspecialchars($item['name']); ?>
                                    </span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        
                        <div class="file-size-cell">
                            <?php echo $item['is_dir'] ? '-' : formatFileSize($item['size']); ?>
                        </div>
                        
                        <div class="file-type-cell">
                            <?php echo $item['is_dir'] ? 'FOLDER' : getFileExtension($item['name']); ?>
                        </div>
                        
                        <div class="file-date-cell">
                            <?php echo formatDateTime($item['modified']); ?>
                        </div>
                        
                        <div class="file-actions-cell">
                            <?php if (!$item['is_dir']): ?>
                                <?php if (isPreviewable($item['name'])): ?>
                                    <button data-path="<?php echo htmlspecialchars($item['path']); ?>" 
                                            data-name="<?php echo htmlspecialchars($item['name']); ?>"
                                            onclick="previewFile(this.dataset.path, this.dataset.name)" 
                                            class="btn btn-info btn-sm" title="预览">👁️</button>
                                <?php endif; ?>
                                <a href="download.php?account=<?php echo urlencode($currentAccountKey); ?>&path=<?php echo urlencode($item['path']); ?>" 
                                   class="btn btn-success btn-sm" title="下载">⬇️</a>
                                <a href="direct.php?account=<?php echo urlencode($currentAccountKey); ?>&path=<?php echo urlencode($item['path']); ?>" 
                                   class="btn btn-warning btn-sm" target="_blank" title="直链">🔗</a>
                            <?php endif; ?>
                            <button data-path="<?php echo htmlspecialchars($item['path']); ?>" 
                                    data-name="<?php echo htmlspecialchars($item['name']); ?>"
                                    onclick="showRenameModal(this.dataset.path, this.dataset.name)" 
                                    class="btn btn-secondary btn-sm" title="重命名">✏️</button>
                            <button data-path="<?php echo htmlspecialchars($item['path']); ?>" 
                                    data-name="<?php echo htmlspecialchars($item['name']); ?>"
                                    onclick="deleteItem(this.dataset.path, this.dataset.name)" 
                                    class="btn btn-danger btn-sm" title="删除">🗑️</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- 新建文件夹模态框 -->
    <div id="createFolderModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>新建文件夹</h3>
                <span class="close" onclick="hideModal('createFolderModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create_folder">
                <div class="form-group">
                    <label for="folder_name">文件夹名称</label>
                    <input type="text" id="folder_name" name="folder_name" required>
                </div>
                <div class="modal-actions">
                    <button type="button" onclick="hideModal('createFolderModal')" class="btn btn-secondary">取消</button>
                    <button type="submit" class="btn btn-primary">创建</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 重命名模态框 -->
    <div id="renameModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>重命名</h3>
                <span class="close" onclick="hideModal('renameModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="rename">
                <input type="hidden" id="rename_old_path" name="old_path">
                <div class="form-group">
                    <label for="new_name">新名称</label>
                    <input type="text" id="new_name" name="new_name" required>
                </div>
                <div class="modal-actions">
                    <button type="button" onclick="hideModal('renameModal')" class="btn btn-secondary">取消</button>
                    <button type="submit" class="btn btn-primary">重命名</button>
                </div>
            </form>
        </div>
    </div>

    <!-- URL上传模态框 -->
    <div id="uploadUrlModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>从URL上传文件</h3>
                <span class="close" onclick="hideModal('uploadUrlModal')">&times;</span>
            </div>
            <div class="form-group">
                <label for="upload_url">文件URL</label>
                <input type="url" id="upload_url" placeholder="https://example.com/file.jpg">
            </div>
            <div class="form-group">
                <label for="upload_filename">保存文件名（可选）</label>
                <input type="text" id="upload_filename" placeholder="留空则使用原文件名">
            </div>
            <div class="modal-actions">
                <button type="button" onclick="hideModal('uploadUrlModal')" class="btn btn-secondary">取消</button>
                <button type="button" onclick="uploadFromUrl()" class="btn btn-primary">上传</button>
            </div>
        </div>
    </div>

    <!-- 预览模态框 -->
    <div id="previewModal" class="modal preview-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="previewTitle">文件预览</h3>
                <span class="close" onclick="hideModal('previewModal')">&times;</span>
            </div>
            <div id="previewContent" class="preview-content">
                <!-- 预览内容将在这里动态加载 -->
            </div>
        </div>
    </div>

    <script>
        let selectedFiles = new Set();

        function showModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }

        function hideModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function showRenameModal(path, name) {
            document.getElementById('rename_old_path').value = path;
            document.getElementById('new_name').value = name;
            showModal('renameModal');
        }

        function deleteItem(path, name) {
            if (confirm('确定要删除 "' + name + '" 吗？此操作不可撤销。')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="path" value="${path}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function filterFiles() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const items = document.querySelectorAll('.file-list-item');
            
            items.forEach(item => {
                const fileName = item.dataset.name;
                if (fileName.includes(searchTerm)) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        function toggleSelectAll() {
            const selectAllCheckbox = document.getElementById('selectAll');
            const fileCheckboxes = document.querySelectorAll('.file-checkbox');
            
            fileCheckboxes.forEach(checkbox => {
                checkbox.checked = selectAllCheckbox.checked;
            });
            
            updateSelection();
        }

        function updateSelection() {
            const checkboxes = document.querySelectorAll('.file-checkbox:checked');
            selectedFiles = new Set(Array.from(checkboxes).map(cb => cb.value));
            
            const count = selectedFiles.size;
            document.getElementById('selectedCount').textContent = count;
            
            const bulkActions = document.getElementById('bulkActions');
            const bulkActionsBtn = document.getElementById('bulkActionsBtn');
            
            if (count > 0) {
                bulkActions.classList.add('show');
                bulkActionsBtn.style.display = 'inline-block';
            } else {
                bulkActions.classList.remove('show');
                bulkActionsBtn.style.display = 'none';
            }
        }

        function clearSelection() {
            document.querySelectorAll('.file-checkbox').forEach(cb => cb.checked = false);
            document.getElementById('selectAll').checked = false;
            updateSelection();
        }

        function bulkDelete() {
            if (selectedFiles.size === 0) return;
            
            if (confirm(`确定要删除选中的 ${selectedFiles.size} 个文件吗？此操作不可撤销。`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                
                let html = '<input type="hidden" name="action" value="bulk_delete">';
                selectedFiles.forEach(path => {
                    html += `<input type="hidden" name="selected_paths[]" value="${path}">`;
                });
                
                form.innerHTML = html;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function bulkDownload() {
            if (selectedFiles.size === 0) return;
            
            selectedFiles.forEach(path => {
                const link = document.createElement('a');
                link.href = `download.php?account=<?php echo urlencode($currentAccountKey); ?>&path=${encodeURIComponent(path)}`;
                link.download = '';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            });
        }

        function previewFile(path, name) {
            document.getElementById('previewTitle').textContent = name;
            const content = document.getElementById('previewContent');
            
            // 显示加载状态
            content.innerHTML = '<p>正在加载预览...</p>';
            showModal('previewModal');
            
            // 根据文件类型生成预览
            const ext = name.split('.').pop().toLowerCase();
            const previewUrl = `transfer.php?account=<?php echo urlencode($currentAccountKey); ?>&path=${encodeURIComponent(path)}`;
            
            if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'].includes(ext)) {
                content.innerHTML = `<img src="${previewUrl}" alt="${name}" style="max-width: 100%; max-height: 70vh;">`;
            } else if (['mp4', 'webm', 'ogg'].includes(ext)) {
                content.innerHTML = `<video controls style="max-width: 100%; max-height: 70vh;"><source src="${previewUrl}" type="video/${ext}"></video>`;
            } else if (['mp3', 'wav', 'ogg', 'aac'].includes(ext)) {
                content.innerHTML = `<audio controls style="width: 100%;"><source src="${previewUrl}" type="audio/${ext}"></audio>`;
            } else if (['txt', 'md', 'html', 'css', 'js', 'json', 'xml', 'csv', 'php', 'py', 'java', 'cpp', 'c', 'h', 'sql', 'yaml', 'yml'].includes(ext)) {
                // 文本文件预览
                fetch(previewUrl)
                    .then(response => response.text())
                    .then(text => {
                        content.innerHTML = `<pre style="text-align: left; max-height: 60vh; overflow: auto; background: #f7fafc; padding: 1rem; border-radius: 6px;">${escapeHtml(text)}</pre>`;
                    })
                    .catch(error => {
                        content.innerHTML = '<p>预览失败：无法加载文件内容</p>';
                    });
            } else if (ext === 'pdf') {
                content.innerHTML = `<iframe src="${previewUrl}" style="width: 100%; height: 70vh; border: none;"></iframe>`;
            } else {
                content.innerHTML = '<p>此文件类型暂不支持预览</p>';
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function uploadFromUrl() {
            const url = document.getElementById('upload_url').value;
            const filename = document.getElementById('upload_filename').value;
            
            if (!url) {
                alert('请输入文件URL');
                return;
            }
            
            // 这里可以实现URL上传功能
            alert('URL上传功能需要后端支持，暂未实现');
            hideModal('uploadUrlModal');
        }

        // 点击模态框外部关闭
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }

        // 键盘快捷键
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey || e.metaKey) {
                switch(e.key) {
                    case 'a':
                        e.preventDefault();
                        document.getElementById('selectAll').checked = true;
                        toggleSelectAll();
                        break;
                    case 'f':
                        e.preventDefault();
                        document.getElementById('searchInput').focus();
                        break;
                }
            }
            
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal').forEach(modal => {
                    modal.style.display = 'none';
                });
            }
        });

        // 存储信息刷新功能
        function refreshStorageInfo() {
            const storageInfo = document.querySelector('.storage-info');
            if (!storageInfo) return;
            
            const refreshBtn = document.getElementById('refreshStorageBtn');
            if (refreshBtn) {
                refreshBtn.textContent = '刷新中...';
                refreshBtn.disabled = true;
            }
            
            const accountKey = new URLSearchParams(window.location.search).get('account');
            const currentPath = new URLSearchParams(window.location.search).get('path') || '/';
            
            fetch(`api/storage_info.php?account=${encodeURIComponent(accountKey)}&path=${encodeURIComponent(currentPath)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data.supported) {
                        updateStorageDisplay(data.data);
                    } else {
                        showStorageError(data.message || '无法获取存储信息');
                    }
                })
                .catch(error => {
                    console.error('刷新存储信息失败:', error);
                    showStorageError('刷新存储信息失败');
                })
                .finally(() => {
                    if (refreshBtn) {
                        refreshBtn.textContent = '🔄 刷新';
                        refreshBtn.disabled = false;
                    }
                });
        }
        
        function updateStorageDisplay(storageData) {
            const storageInfo = document.querySelector('.storage-info');
            if (!storageInfo || !storageData.quota_total) return;
            
            const usagePercent = storageData.usage_percent || 0;
            let barClass = '';
            if (usagePercent >= 90) barClass = 'danger';
            else if (usagePercent >= 75) barClass = 'warning';
            
            const storageBar = storageInfo.querySelector('.storage-bar-fill');
            if (storageBar) {
                storageBar.style.width = Math.min(usagePercent, 100) + '%';
                storageBar.className = `storage-bar-fill ${barClass}`;
            }
            
            const storageDetails = storageInfo.querySelector('.storage-details');
            if (storageDetails) {
                storageDetails.innerHTML = `
                    <span>
                        已使用: ${storageData.quota_used_formatted || '0 B'} 
                        / ${storageData.quota_total_formatted || 'N/A'}
                    </span>
                    <span>
                        可用: ${storageData.quota_available_formatted || '0 B'} 
                        (${(100 - usagePercent).toFixed(1)}%)
                    </span>
                `;
            }
        }
        
        function showStorageError(message) {
            const storageInfo = document.querySelector('.storage-info');
            if (storageInfo) {
                storageInfo.className = 'storage-info unavailable';
                storageInfo.innerHTML = `
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <span>⚠️</span>
                        <span>${escapeHtml(message)}</span>
                    </div>
                `;
            }
        }
        
        // 页面加载完成后设置定时刷新
        document.addEventListener('DOMContentLoaded', function() {
            // 每5分钟自动刷新一次存储信息
            setInterval(refreshStorageInfo, 5 * 60 * 1000);
        });
    </script>
</body>
</html>
