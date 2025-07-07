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
            flex-wrap: wrap;
        }
          .breadcrumb a,
        .breadcrumb-link {
            color: #3182ce;
            text-decoration: none;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            transition: background-color 0.2s;
            white-space: nowrap;
            word-break: keep-all;
        }

        .breadcrumb a:hover,
        .breadcrumb-link:hover {
            background: #ebf8ff;
        }

        .breadcrumb span,
        .breadcrumb-current {
            color: #4a5568;
            white-space: nowrap;
            word-break: keep-all;
            padding: 0.25rem 0.5rem;
        }
        
        .breadcrumb-separator {
            color: #a0aec0;
            padding: 0 0.25rem;
            font-size: 0.9rem;
        }
        
        .breadcrumb-current {
            font-weight: 600;
            background: rgba(49, 130, 206, 0.1);
            border-radius: 4px;
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
            position: relative;
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
            cursor: pointer;
            display: block;
            min-width: 0;
            flex: 1;
        }
        
        .file-name-text:hover {
            color: #3182ce;
        }
        
        .file-name-scroll {
            overflow: hidden;
            white-space: nowrap;
            position: relative;
        }
        
        .file-name-scroll .file-name-inner {
            display: inline-block;
            padding-right: 20px;
            animation: none;
            transition: animation 0.3s ease;
        }
        
        .file-name-scroll:hover .file-name-inner {
            animation: scrollText 3s linear infinite;
        }
        
        @keyframes scrollText {
            0% { transform: translateX(0); }
            50% { transform: translateX(calc(-100% + 100px)); }
            100% { transform: translateX(0); }
        }
        
        /* 移动端滚动优化 */
        @media (max-width: 768px) {
            .file-name-scroll .file-name-inner {
                animation: mobileScrollText 4s linear infinite;
                animation-play-state: paused;
            }
            
            .file-name-scroll:active .file-name-inner,
            .file-name-scroll:focus .file-name-inner {
                animation-play-state: running;
            }
            
            @keyframes mobileScrollText {
                0%, 20% { transform: translateX(0); }
                40%, 60% { transform: translateX(calc(-100% + 80px)); }
                80%, 100% { transform: translateX(0); }
            }
            
            /* 触摸优化 */
            .file-list-item {
                touch-action: manipulation;
                -webkit-tap-highlight-color: rgba(0,0,0,0.1);
            }
            
            .file-list-item:active {
                background: #e2e8f0;
                transform: scale(0.98);
                transition: all 0.1s ease;
            }
            
            .btn {
                touch-action: manipulation;
                -webkit-tap-highlight-color: transparent;
                user-select: none;
            }
            
            .btn:active {
                transform: translateY(0) scale(0.95);
            }
            
            /* 长按选择提示 */
            .file-list-item::after {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(59, 130, 246, 0.1);
                opacity: 0;
                transition: opacity 0.3s ease;
                pointer-events: none;
            }
            
            .file-list-item.selecting::after {
                opacity: 1;
            }
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
            position: relative;
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
            transition: all 0.3s ease;
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
        
        .preview-controls {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        /* 移动端预览优化 */
        @media (max-width: 768px) {
            .modal.modal-show {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 1000;
                display: flex !important;
                align-items: center;
                justify-content: center;
                padding: 1rem;
                box-sizing: border-box;
            }
            
            .modal-content {
                margin: 0;
                width: 100%;
                max-width: 100%;
                max-height: 90vh;
                border-radius: 16px;
                overflow-y: auto;
                box-sizing: border-box;
                position: relative;
                padding: 1.5rem;
            }
            
            .preview-modal .modal-content {
                display: flex;
                flex-direction: column;
                height: auto;
                max-height: 90vh;
            }
            
            .modal-header {
                margin-bottom: 1rem;
                flex-wrap: wrap;
                gap: 0.5rem;
                flex-shrink: 0;
            }
            
            .modal-header h3 {
                font-size: 1rem;
                flex: 1;
                min-width: 0;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            
            .preview-controls {
                flex-shrink: 0;
                gap: 0.25rem;
            }
            
            .preview-controls .btn {
                padding: 0.375rem 0.75rem;
                font-size: 0.75rem;
            }
            
            .preview-content {
                flex: 1;
                display: flex;
                align-items: center;
                justify-content: center;
                overflow: auto;
                min-height: 0;
                text-align: center;
            }
            
            .preview-content img {
                max-height: 60vh;
                max-width: 100%;
                width: auto;
                height: auto;
                object-fit: contain;
            }
            
            .preview-content video {
                max-height: 50vh;
                max-width: 100%;
                width: 100%;
            }
            
            .preview-content audio {
                width: 100%;
                min-height: 48px;
            }
            
            .preview-content pre {
                padding: 0.75rem;
                font-size: 0.8rem;
                max-height: 50vh;
                line-height: 1.4;
                text-align: left;
                width: 100%;
                box-sizing: border-box;
            }
            
            .preview-content iframe {
                height: 60vh !important;
                width: 100% !important;
                max-width: 100%;
            }
            
            .close {
                font-size: 1.25rem;
                padding: 0.25rem;
                line-height: 1;
            }
        }
        
        /* 超小屏幕优化 */
        @media (max-width: 480px) {
            .modal.modal-show {
                padding: 0.5rem;
            }
            
            .preview-modal .modal-content {
                padding: 0.75rem;
                border-radius: 12px;
            }
            
            .modal-header {
                margin-bottom: 0.75rem;
                flex-wrap: wrap;
            }
            
            .modal-header h3 {
                font-size: 0.9rem;
            }
            
            .preview-controls .btn {
                padding: 0.25rem 0.5rem;
                font-size: 0.7rem;
            }
            
            .preview-content img {
                max-height: 55vh;
            }
            
            .preview-content video {
                max-height: 45vh;
            }
            
            .preview-content pre {
                font-size: 0.75rem;
                max-height: 45vh;
                padding: 0.5rem;
            }
            
            .preview-content iframe {
                height: 55vh !important;
            }
        }
        
        .floating-preview {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 10001;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 25px;
            padding: 12px 18px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            backdrop-filter: blur(20px);
            max-width: 320px;
            border: 1px solid rgba(255,255,255,0.1);
            overflow: hidden;
        }
        
        .floating-preview:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 15px 40px rgba(0,0,0,0.4);
            border-color: rgba(255,255,255,0.2);
        }
        
        .floating-preview::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, rgba(255,255,255,0.1) 0%, transparent 50%);
            border-radius: 25px;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .floating-preview:hover::before {
            opacity: 1;
        }
        
        .floating-preview.persistent {
            animation: persistentBreathe 3s ease-in-out infinite;
        }
        
        @keyframes persistentBreathe {
            0%, 100% { 
                box-shadow: 0 8px 32px rgba(0,0,0,0.3), 0 0 0 0 rgba(102, 126, 234, 0.4);
            }
            50% { 
                box-shadow: 0 8px 32px rgba(0,0,0,0.3), 0 0 0 8px rgba(102, 126, 234, 0.1);
            }
        }
        
        /* 全局媒体播放器样式 */
        #globalMediaPlayer {
            position: fixed !important;
            top: -1000px !important;
            left: -1000px !important;
            width: 1px !important;
            height: 1px !important;
            opacity: 0 !important;
            pointer-events: none !important;
            z-index: -1 !important;
        }
        
        .floating-preview-progress {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: rgba(255,255,255,0.2);
            border-radius: 0 0 25px 25px;
            overflow: hidden;
        }
        
        .floating-preview-progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #4facfe 0%, #00f2fe 100%);
            width: 0%;
            transition: width 0.3s ease;
            border-radius: 0 0 25px 25px;
        }
        
        .floating-preview.audio .floating-preview-progress-bar {
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        }
        
        .floating-preview.video .floating-preview-progress-bar {
            background: linear-gradient(90deg, #f093fb 0%, #f5576c 100%);
        }
        
        .floating-preview:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 15px 40px rgba(0,0,0,0.4);
            border-color: rgba(255,255,255,0.2);
        }
        
        .floating-preview-icon {
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            position: relative;
        }
        
        .floating-preview-icon span {
            font-size: 1.5rem;
            min-width: 30px;
            text-align: center;
            animation: iconPulse 2s ease-in-out infinite;
        }
        
        .floating-preview-title {
            font-size: 0.9rem;
            font-weight: 500;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            opacity: 0.95;
        }
        
        .floating-preview-subtitle {
            font-size: 0.75rem;
            opacity: 0.7;
            margin-top: 2px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .floating-preview-controls {
            display: flex;
            gap: 8px;
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        
        .floating-preview-btn {
            background: rgba(255,255,255,0.2);
            border: none;
            border-radius: 12px;
            padding: 4px 8px;
            color: white;
            font-size: 0.7rem;
            cursor: pointer;
            transition: all 0.2s ease;
            backdrop-filter: blur(10px);
        }
        
        .floating-preview-btn:hover {
            background: rgba(255,255,255,0.3);
            transform: scale(1.05);
        }
        
        .floating-preview.audio {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .floating-preview.video {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        .floating-preview.image {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        
        .floating-preview.minimizing {
            animation: minimizeToFloat 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }
        
        .floating-preview.restoring {
            animation: restoreFromFloat 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }
        
        .preview-modal.minimized {
            animation: fadeOutModal 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }
        
        .preview-modal.restored {
            animation: fadeInModal 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }
        
        @keyframes minimizeToFloat {
            0% {
                transform: scale(0.3) translate(200%, 300%);
                opacity: 0;
                border-radius: 8px;
            }
            50% {
                transform: scale(0.8) translate(50%, 100%);
                opacity: 0.7;
            }
            100% {
                transform: scale(1) translate(0, 0);
                opacity: 1;
                border-radius: 25px;
            }
        }
        
        @keyframes restoreFromFloat {
            0% {
                transform: scale(1) translate(0, 0);
                opacity: 1;
                border-radius: 25px;
            }
            50% {
                transform: scale(1.2) translate(-30%, -150%);
                opacity: 0.8;
            }
            100% {
                transform: scale(0.1) translate(-500%, -800%);
                opacity: 0;
                border-radius: 8px;
            }
        }
        
        @keyframes fadeOutModal {
            0% {
                opacity: 1;
                visibility: visible;
                transform: scale(1);
            }
            100% {
                opacity: 0;
                visibility: hidden;
                transform: scale(0.95);
            }
        }
        
        @keyframes fadeInModal {
            0% {
                opacity: 0;
                visibility: hidden;
                transform: scale(0.95);
            }
            100% {
                opacity: 1;
                visibility: visible;
                transform: scale(1);
            }
        }
        
        @keyframes iconPulse {
            0%, 100% {
                transform: scale(1);
                opacity: 1;
            }
            50% {
                transform: scale(1.1);
                opacity: 0.8;
            }
        }
        
        .floating-preview.playing .floating-preview-icon span {
            animation: iconPulse 1.5s ease-in-out infinite;
        }
        
        .floating-preview:not(.playing) .floating-preview-icon span {
            animation: none;
        }
        
        /* 响应式调整 */
        @media (max-width: 768px) {
            .floating-preview {
                bottom: 15px;
                right: 15px;
                padding: 10px 14px;
                max-width: 280px;
                border-radius: 20px;
            }
            
            .floating-preview-icon {
                gap: 10px;
            }
            
            .floating-preview-icon span {
                font-size: 1.25rem;
                min-width: 25px;
            }
            
            .floating-preview-title {
                font-size: 0.8rem;
            }
            
            .floating-preview-subtitle {
                font-size: 0.7rem;
            }
            
            .floating-preview-controls {
                margin-top: 6px;
                padding-top: 6px;
                gap: 6px;
            }
            
            .floating-preview-btn {
                padding: 3px 6px;
                font-size: 0.6rem;
                border-radius: 8px;
            }
        }
        
        /* 超小屏幕悬浮预览优化 */
        @media (max-width: 480px) {
            .floating-preview {
                bottom: 10px;
                right: 10px;
                padding: 8px 12px;
                max-width: 220px;
                border-radius: 18px;
            }
            
            .floating-preview-icon span {
                font-size: 1rem;
                min-width: 20px;
            }
            
            .floating-preview-title {
                font-size: 0.75rem;
            }
            
            .floating-preview-subtitle {
                font-size: 0.65rem;
            }
        }
        
        /* 移动端卡片式布局 */
        .mobile-card-layout {
            display: none;
        }
        
        .file-card {
            background: white;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 0.75rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 1px solid #f0f0f0;
            position: relative;
            transition: all 0.3s ease;
        }
        
        .file-card:active {
            transform: scale(0.98);
            box-shadow: 0 1px 4px rgba(0,0,0,0.12);
        }
        
        .file-card.selected {
            border-color: #3182ce;
            background: #f0f8ff;
        }
        
        .file-card-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.5rem;
        }
        
        .file-card-icon {
            font-size: 2rem;
            min-width: 3rem;
            text-align: center;
        }
        
        .file-card-info {
            flex: 1;
            min-width: 0;
        }
        
        .file-card-name {
            font-weight: 600;
            color: #2d3748;
            font-size: 1rem;
            line-height: 1.3;
            margin-bottom: 0.25rem;
            word-break: break-word;
        }
        
        .file-card-meta {
            display: flex;
            gap: 1rem;
            color: #718096;
            font-size: 0.8rem;
            flex-wrap: wrap;
        }
        
        .file-card-select {
            position: absolute;
            top: 0.75rem;
            right: 0.75rem;
            width: 24px;
            height: 24px;
            border-radius: 6px;
            border: 2px solid #e2e8f0;
            background: white;
            cursor: pointer;
            transition: all 0.2s;
            display: none; /* 默认隐藏 */
        }
        
        .file-card-select:checked {
            background: #3182ce;
            border-color: #3182ce;
        }
        
        .file-card-select:checked::after {
            content: '✓';
            color: white;
            font-size: 0.75rem;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }
        
        .file-card-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.75rem;
            flex-wrap: wrap;
        }
        
        .file-card-actions .btn {
            flex: 1;
            min-width: 0;
            font-size: 0.8rem;
            padding: 0.5rem 0.75rem;
            border-radius: 8px;
        }
        
        /* 底部固定操作栏 */
        .mobile-bottom-bar {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            border-top: 1px solid #e2e8f0;
            padding: 0.75rem 1rem;
            z-index: 200;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
        }
        
        .mobile-bottom-bar.show {
            display: block;
        }
        
        .mobile-bottom-actions {
            display: flex;
            gap: 0.5rem;
            justify-content: space-around;
        }
        
        .mobile-bottom-actions .btn {
            flex: 1;
            padding: 0.75rem 0.5rem;
            font-size: 0.8rem;
            border-radius: 8px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.25rem;
            min-height: 3rem;
        }
        
        .mobile-bottom-actions .btn span {
            font-size: 1.2rem;
        }
        

        
        /* 快速操作浮动按钮 */
        .mobile-fab {
            position: fixed;
            bottom: 5rem;
            right: 1rem;
            width: 3.5rem;
            height: 3.5rem;
            border-radius: 50%;
            background: #3182ce;
            color: white;
            border: none;
            font-size: 1.5rem;
            box-shadow: 0 4px 12px rgba(49, 130, 206, 0.4);
            cursor: pointer;
            z-index: 150;
            transition: all 0.3s ease;
            display: none;
        }
        
        .mobile-fab:active {
            transform: scale(0.95);
        }
        
        .mobile-fab.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        @media (max-width: 768px) {
            body {
                padding-bottom: 5rem; /* 为底部操作栏留空间 */
            }
            
            .container {
                padding: 0.75rem;
            }
            
            .header {
                padding: 1rem;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border-radius: 0 0 1rem 1rem;
                margin-bottom: 1rem;
            }
            
            .header h1 {
                font-size: 1.3rem;
                text-align: center;
                color: white;
            }
            
            .header-actions {
                justify-content: center;
                margin-top: 0.75rem;
            }
            
            .header-actions span {
                background: rgba(255,255,255,0.2);
                padding: 0.5rem 1rem;
                border-radius: 20px;
                font-size: 0.85rem;
                backdrop-filter: blur(10px);
            }
            
            .header-actions .btn {
                background: rgba(255,255,255,0.2);
                color: white;
                border: 1px solid rgba(255,255,255,0.3);
                backdrop-filter: blur(10px);
                margin-top: 0.5rem;
            }
            
            .toolbar {
                background: white;
                border-radius: 12px;
                padding: 1rem;
                margin-bottom: 1rem;
                box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            }
            
            .toolbar-top {
                flex-direction: column;
                gap: 1rem;
            }
            
            .breadcrumb {
                background: #f8fafc;
                padding: 0.75rem;
                border-radius: 8px;
                overflow: visible;
                white-space: normal;
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                gap: 0.5rem;
                line-height: 1.5;
                max-width: 100%;
                word-break: break-word;
            }
            
            .breadcrumb a,
            .breadcrumb-link {
                color: #3182ce;
                text-decoration: none;
                padding: 0.375rem 0.75rem;
                border-radius: 6px;
                transition: all 0.2s;
                white-space: nowrap;
                word-break: keep-all;
                background: rgba(255,255,255,0.7);
                border: 1px solid rgba(255,255,255,0.3);
                font-size: 0.85rem;
                max-width: 150px;
                overflow: hidden;
                text-overflow: ellipsis;
                display: inline-block;
            }
            
            .breadcrumb a:hover,
            .breadcrumb-link:hover {
                background: rgba(255,255,255,0.9);
                border-color: rgba(49, 130, 206, 0.3);
                max-width: none;
                overflow: visible;
                text-overflow: unset;
            }
            
            .breadcrumb span,
            .breadcrumb-current {
                color: #4a5568;
                white-space: nowrap;
                padding: 0.375rem 0.75rem;
                font-size: 0.85rem;
                max-width: 150px;
                overflow: hidden;
                text-overflow: ellipsis;
                display: inline-block;
            }
            
            .breadcrumb-current {
                font-weight: 600;
                background: rgba(49, 130, 206, 0.2);
                border-radius: 6px;
                border: 1px solid rgba(49, 130, 206, 0.3);
            }
            
            .breadcrumb-separator {
                color: #a0aec0;
                font-size: 0.8rem;
                margin: 0 0.25rem;
                padding: 0;
                display: inline-block;
                flex-shrink: 0;
            }
            
            .search-box {
                border-radius: 12px;
                padding: 0.75rem;
                background: #f8fafc;
                border: 2px solid #e2e8f0;
                transition: border-color 0.2s;
            }
            
            .search-box:focus-within {
                border-color: #3182ce;
                background: white;
            }
            
            .search-box input {
                font-size: 1rem;
            }
            
            /* 隐藏桌面版文件列表，显示移动版卡片 */
            .file-list {
                display: none;
            }
            
            .mobile-card-layout {
                display: block;
            }
            
            .toolbar-actions {
                display: none; /* 隐藏工具栏操作，使用底部栏 */
            }
            
            .bulk-actions {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                background: white;
                border-top: 1px solid #e2e8f0;
                padding: 1rem;
                box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
                z-index: 200;
                border-radius: 1rem 1rem 0 0;
            }
            
            .bulk-actions .btn {
                font-size: 0.9rem;
                padding: 0.75rem 1rem;
                border-radius: 8px;
                margin: 0 0.25rem;
            }
            
            .mobile-fab.show {
                display: flex;
            }
            
            .mobile-bottom-bar.show {
                display: block;
            }
            

            
            .modal-header h3 {
                font-size: 1.2rem;
                color: #2d3748;
            }
            
            .form-group input,
            .form-group select,
            .form-group textarea {
                padding: 1rem;
                font-size: 1rem;
                border-radius: 8px;
                border: 2px solid #e2e8f0;
            }
            
            .form-group input:focus,
            .form-group select:focus,
            .form-group textarea:focus {
                border-color: #3182ce;
            }
            
            .modal-actions {
                gap: 0.75rem;
                margin-top: 2rem;
            }
            
            .modal-actions .btn {
                flex: 1;
                padding: 1rem;
                font-size: 1rem;
                border-radius: 8px;
                font-weight: 600;
            }
        }
        
        /* 超小屏幕优化 */
        @media (max-width: 480px) {
            .header {
                padding: 0.75rem;
                margin-bottom: 0.75rem;
            }
            
            .header h1 {
                font-size: 1.1rem;
            }
            
            .container {
                padding: 0.5rem;
            }
            
            .toolbar {
                padding: 0.75rem;
                margin-bottom: 0.75rem;
            }
            
            .breadcrumb {
                padding: 0.5rem;
                font-size: 0.85rem;
            }
            
            .breadcrumb a,
            .breadcrumb-link {
                padding: 0.25rem 0.5rem;
                font-size: 0.8rem;
                max-width: 120px;
                border-radius: 4px;
            }
            
            .breadcrumb span,
            .breadcrumb-current {
                padding: 0.25rem 0.5rem;
                font-size: 0.8rem;
                max-width: 120px;
            }
            
            .breadcrumb-separator {
                font-size: 0.7rem;
                margin: 0 0.125rem;
            }
            
            /* 对于超长路径，只显示最后几个层级 */
            .breadcrumb.has-overflow .breadcrumb-item:nth-child(-n+6) {
                display: none;
            }
            
            .breadcrumb.has-overflow .breadcrumb-item:nth-last-child(-n+3) {
                display: inline-block;
            }
            
            /* 添加省略号提示 */
            .breadcrumb::before {
                content: "...";
                color: #a0aec0;
                font-size: 0.8rem;
                margin-right: 0.5rem;
                display: none;
            }
            
            .breadcrumb.has-overflow::before {
                display: inline-block;
            }
            
            .search-box {
                padding: 0.625rem;
            }
            
            .file-card {
                padding: 0.75rem;
                margin-bottom: 0.5rem;
                border-radius: 10px;
            }
            
            .file-card-icon {
                font-size: 1.75rem;
                min-width: 2.5rem;
            }
            
            .file-card-name {
                font-size: 0.95rem;
            }
            
            .file-card-meta {
                font-size: 0.75rem;
            }
            
            .file-card-actions .btn {
                padding: 0.5rem;
                font-size: 0.75rem;
            }
            
            .mobile-fab {
                width: 3rem;
                height: 3rem;
                font-size: 1.25rem;
                bottom: 4.5rem;
            }
            
            .mobile-bottom-bar {
                padding: 0.5rem;
            }
            
            .mobile-bottom-actions .btn {
                padding: 0.5rem 0.25rem;
                font-size: 0.7rem;
                min-height: 2.5rem;
            }
            
            .mobile-bottom-actions .btn span {
                font-size: 1rem;
            }
            
            .modal-content {
                padding: 1rem;
                border-radius: 12px;
            }
            
            .modal-header h3 {
                font-size: 1.1rem;
            }
            
            .form-group input,
            .form-group select,
            .form-group textarea {
                padding: 0.75rem;
                font-size: 0.95rem;
            }
            
            .modal-actions .btn {
                padding: 0.75rem;
                font-size: 0.9rem;
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
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="toolbar">
            <div class="toolbar-top">
                <div class="breadcrumb" id="breadcrumbNav">
                    <?php 
                    $breadcrumbCount = count($breadcrumbs);
                    $hasOverflow = $breadcrumbCount > 4; // 超过4级则认为可能溢出
                    if ($hasOverflow): ?>
                        <script>document.getElementById('breadcrumbNav').classList.add('has-overflow');</script>
                    <?php endif; ?>
                    
                    <?php foreach ($breadcrumbs as $i => $crumb): ?>
                        <?php if ($i > 0): ?><span class="breadcrumb-separator breadcrumb-item">/</span><?php endif; ?>
                        <?php if ($crumb['path'] === $currentPath): ?>
                            <span class="breadcrumb-current breadcrumb-item" title="<?php echo htmlspecialchars($crumb['name']); ?>">
                                <?php echo htmlspecialchars($crumb['name']); ?>
                            </span>
                        <?php else: ?>
                            <a href="?account=<?php echo urlencode($currentAccountKey); ?>&path=<?php echo urlencode($crumb['path']); ?>" 
                               class="breadcrumb-link breadcrumb-item" 
                               title="<?php echo htmlspecialchars($crumb['name']); ?>">
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
                                <div class="file-name-scroll">
                                    <a href="?account=<?php echo urlencode($currentAccountKey); ?>&path=<?php echo urlencode($item['path']); ?>" 
                                       class="file-name-text">
                                        <span class="file-name-inner"><?php echo htmlspecialchars($item['name']); ?></span>
                                    </a>
                                </div>
                            <?php else: ?>
                                <?php if (isPreviewable($item['name'])): ?>
                                    <div class="file-name-scroll">
                                        <span class="file-name-text" 
                                              data-path="<?php echo htmlspecialchars($item['path']); ?>" 
                                              data-name="<?php echo htmlspecialchars($item['name']); ?>"
                                              onclick="previewFile(this.dataset.path, this.dataset.name)">
                                            <span class="file-name-inner"><?php echo htmlspecialchars($item['name']); ?></span>
                                        </span>
                                    </div>
                                <?php else: ?>
                                    <div class="file-name-scroll">
                                        <span class="file-name-text">
                                            <span class="file-name-inner"><?php echo htmlspecialchars($item['name']); ?></span>
                                        </span>
                                    </div>
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
            
            <!-- 移动端卡片式布局 -->
            <div class="mobile-card-layout">
                <?php foreach ($items as $item): ?>
                    <div class="file-card" data-name="<?php echo strtolower(htmlspecialchars($item['name'])); ?>" data-path="<?php echo htmlspecialchars($item['path']); ?>">
                        <input type="checkbox" class="file-card-select" value="<?php echo htmlspecialchars($item['path']); ?>" onchange="updateMobileSelection(this)">
                        
                        <div class="file-card-header">
                            <div class="file-card-icon">
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
                            </div>
                            <div class="file-card-info">
                                <div class="file-card-name">
                                    <?php if ($item['is_dir']): ?>
                                        <a href="?account=<?php echo urlencode($currentAccountKey); ?>&path=<?php echo urlencode($item['path']); ?>" style="color: inherit; text-decoration: none;">
                                            <?php echo htmlspecialchars($item['name']); ?>
                                        </a>
                                    <?php else: ?>
                                        <?php if (isPreviewable($item['name'])): ?>
                                            <span onclick="previewFile('<?php echo htmlspecialchars($item['path']); ?>', '<?php echo htmlspecialchars($item['name']); ?>')" style="cursor: pointer;">
                                                <?php echo htmlspecialchars($item['name']); ?>
                                            </span>
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($item['name']); ?>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="file-card-meta">
                                    <?php if (!$item['is_dir']): ?>
                                        <span>📊 <?php echo formatFileSize($item['size']); ?></span>
                                    <?php endif; ?>
                                    <span>📅 <?php echo formatDateTime($item['modified']); ?></span>
                                    <?php if (!$item['is_dir']): ?>
                                        <span>📎 <?php echo getFileExtension($item['name']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="file-card-actions">
                            <?php if ($item['is_dir']): ?>
                                <a href="?account=<?php echo urlencode($currentAccountKey); ?>&path=<?php echo urlencode($item['path']); ?>" 
                                   class="btn btn-primary">
                                    <span>📂</span> 打开
                                </a>
                            <?php else: ?>
                                <?php if (isPreviewable($item['name'])): ?>
                                    <button onclick="previewFile('<?php echo htmlspecialchars($item['path']); ?>', '<?php echo htmlspecialchars($item['name']); ?>')" 
                                            class="btn btn-info">
                                        <span>👁️</span> 预览
                                    </button>
                                <?php endif; ?>
                                <a href="download.php?account=<?php echo urlencode($currentAccountKey); ?>&path=<?php echo urlencode($item['path']); ?>" 
                                   class="btn btn-success">
                                    <span>⬇️</span> 下载
                                </a>
                                <a href="direct.php?account=<?php echo urlencode($currentAccountKey); ?>&path=<?php echo urlencode($item['path']); ?>" 
                                   class="btn btn-warning" target="_blank">
                                    <span>🔗</span> 直链
                                </a>
                            <?php endif; ?>
                            <button onclick="showRenameModal('<?php echo htmlspecialchars($item['path']); ?>', '<?php echo htmlspecialchars($item['name']); ?>')" 
                                    class="btn btn-secondary">
                                <span>✏️</span> 重命名
                            </button>
                            <button onclick="deleteItem('<?php echo htmlspecialchars($item['path']); ?>', '<?php echo htmlspecialchars($item['name']); ?>')" 
                                    class="btn btn-danger">
                                <span>🗑️</span> 删除
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- 移动端底部操作栏 -->
    <div class="mobile-bottom-bar" id="mobileBottomBar">
        <div class="mobile-bottom-actions">
            <label for="mobile-file-upload" class="btn btn-success">
                <span>📤</span>
                <small>上传</small>
            </label>
            <input type="file" id="mobile-file-upload" style="display: none;" onchange="handleMobileUpload(this)">
            
            <button onclick="showModal('createFolderModal')" class="btn btn-primary">
                <span>📁</span>
                <small>新建</small>
            </button>
            
            <button onclick="showModal('uploadUrlModal')" class="btn btn-info">
                <span>🔗</span>
                <small>URL</small>
            </button>
            
            <button onclick="toggleMobileSelectMode()" class="btn btn-secondary" id="mobileSelectBtn">
                <span>☑️</span>
                <small>选择</small>
            </button>
        </div>
    </div>

    <!-- 移动端快速操作浮动按钮 -->
    <button class="mobile-fab" id="mobileFab" onclick="scrollToTop()">
        ⬆️
    </button>

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
        <div class="modal-content" id="previewModalContent">
            <div class="modal-header">
                <h3 id="previewTitle">文件预览</h3>
                <div class="preview-controls">
                    <button class="btn btn-secondary btn-sm" onclick="togglePreviewMinimize()" id="minimizeBtn">
                        🗕 收起
                    </button>
                    <span class="close" onclick="hideModal('previewModal')">&times;</span>
                </div>
            </div>
            <div id="previewContent" class="preview-content">
                <!-- 预览内容将在这里动态加载 -->
            </div>
        </div>
    </div>

    <!-- 悬浮预览按钮 -->
    <div id="floatingPreview" class="floating-preview" style="display: none;">
        <div class="floating-preview-icon" onclick="restorePreview()">
            <span id="floatingIcon">🎵</span>
            <div>
                <div class="floating-preview-title" id="floatingTitle">预览中...</div>
                <div class="floating-preview-subtitle" id="floatingSubtitle">点击展开</div>
            </div>
        </div>
        <div class="floating-preview-controls" id="floatingControls" style="display: none;">
            <button class="floating-preview-btn" onclick="toggleGlobalMediaPlayPause(event)" title="播放/暂停">⏯️</button>
            <button class="floating-preview-btn" onclick="openOriginalFile(event)" title="查看原文件">📁</button>
            <button class="floating-preview-btn" onclick="closePreviewCompletely(event)" title="关闭">✕</button>
        </div>
        <div class="floating-preview-progress" id="floatingProgress" style="display: none;">
            <div class="floating-preview-progress-bar" id="floatingProgressBar"></div>
        </div>
    </div>

    <!-- 全局隐藏的媒体播放器 -->
    <div id="globalMediaPlayer" style="position: fixed; top: -1000px; left: -1000px; pointer-events: none; opacity: 0;">
        <audio id="globalAudioPlayer" onplay="onGlobalMediaPlay()" onpause="onGlobalMediaPause()" onended="onGlobalMediaEnded()" ontimeupdate="onGlobalMediaProgress()"></audio>
        <video id="globalVideoPlayer" onplay="onGlobalMediaPlay()" onpause="onGlobalMediaPause()" onended="onGlobalMediaEnded()" ontimeupdate="onGlobalMediaProgress()"></video>
    </div>

    <script>
        let selectedFiles = new Set();
        let currentPreviewFile = null;
        let currentPreviewType = null;
        let isPreviewMinimized = false;

        // 持久化预览状态的管理
        class PersistentPreview {
            constructor() {
                this.storageKey = 'webdav_floating_preview';
                this.mediaStateKey = 'webdav_media_state';
                this.init();
            }

            init() {
                // 页面加载时恢复悬浮预览状态
                this.restoreFloatingPreview();
                
                // 监听页面卸载事件，保存状态
                window.addEventListener('beforeunload', () => {
                    this.saveFloatingPreview();
                    this.saveMediaState();
                });
                
                // 监听存储变化（其他标签页的更新）
                window.addEventListener('storage', (e) => {
                    if (e.key === this.storageKey) {
                        this.restoreFloatingPreview();
                    }
                });
            }

            saveMediaState() {
                if (isPreviewMinimized && currentPreviewFile && (currentPreviewType === 'audio' || currentPreviewType === 'video')) {
                    const player = this.getGlobalPlayer();
                    if (player && !player.paused) {
                        const mediaState = {
                            file: currentPreviewFile,
                            type: currentPreviewType,
                            currentTime: player.currentTime,
                            duration: player.duration,
                            isPlaying: !player.paused,
                            timestamp: Date.now(),
                            account: '<?php echo urlencode($currentAccountKey); ?>'
                        };
                        localStorage.setItem(this.mediaStateKey, JSON.stringify(mediaState));
                    }
                }
            }

            restoreMediaState() {
                try {
                    const data = localStorage.getItem(this.mediaStateKey);
                    if (!data) return false;

                    const mediaState = JSON.parse(data);
                    
                    // 检查数据是否过期（10分钟）
                    if (Date.now() - mediaState.timestamp > 600000) {
                        localStorage.removeItem(this.mediaStateKey);
                        return false;
                    }

                    // 检查账户是否匹配
                    if (mediaState.account !== '<?php echo urlencode($currentAccountKey); ?>') {
                        return false;
                    }

                    // 检查是否是同一个文件
                    if (currentPreviewFile && 
                        currentPreviewFile.path === mediaState.file.path && 
                        (currentPreviewType === 'audio' || currentPreviewType === 'video')) {
                        
                        const player = this.getGlobalPlayer();
                        if (player) {
                            player.currentTime = mediaState.currentTime;
                            if (mediaState.isPlaying) {
                                player.play();
                            }
                            return true;
                        }
                    }
                } catch (e) {
                    console.error('恢复媒体状态失败:', e);
                    localStorage.removeItem(this.mediaStateKey);
                }
                return false;
            }

            getGlobalPlayer() {
                if (currentPreviewType === 'audio') {
                    return document.getElementById('globalAudioPlayer');
                } else if (currentPreviewType === 'video') {
                    return document.getElementById('globalVideoPlayer');
                }
                return null;
            }

            saveFloatingPreview() {
                if (isPreviewMinimized && currentPreviewFile) {
                    const previewData = {
                        file: currentPreviewFile,
                        type: currentPreviewType,
                        isMinimized: true,
                        timestamp: Date.now(),
                        account: '<?php echo urlencode($currentAccountKey); ?>'
                    };
                    localStorage.setItem(this.storageKey, JSON.stringify(previewData));
                } else {
                    localStorage.removeItem(this.storageKey);
                }
            }

            restoreFloatingPreview() {
                try {
                    const data = localStorage.getItem(this.storageKey);
                    if (!data) return;

                    const previewData = JSON.parse(data);
                    
                    // 检查数据是否过期（1小时）
                    if (Date.now() - previewData.timestamp > 3600000) {
                        localStorage.removeItem(this.storageKey);
                        return;
                    }

                    // 检查账户是否匹配
                    if (previewData.account !== '<?php echo urlencode($currentAccountKey); ?>') {
                        return;
                    }

                    // 恢复悬浮预览状态
                    if (previewData.isMinimized && previewData.file) {
                        currentPreviewFile = previewData.file;
                        currentPreviewType = previewData.type;
                        isPreviewMinimized = true;
                        
                        // 重新生成悬浮预览内容
                        this.recreateFloatingPreview(previewData);
                        
                        // 对于媒体文件，初始化全局播放器
                        if (currentPreviewType === 'audio' || currentPreviewType === 'video') {
                            this.initGlobalPlayer();
                        }
                    }
                } catch (e) {
                    console.error('恢复悬浮预览失败:', e);
                    localStorage.removeItem(this.storageKey);
                }
            }

            initGlobalPlayer() {
                const player = this.getGlobalPlayer();
                if (player && currentPreviewFile) {
                    const previewUrl = `transfer.php?account=<?php echo urlencode($currentAccountKey); ?>&path=${encodeURIComponent(currentPreviewFile.path)}`;
                    player.src = previewUrl;
                    
                    // 尝试恢复播放状态
                    player.addEventListener('loadeddata', () => {
                        this.restoreMediaState();
                    }, { once: true });
                    
                    player.load();
                }
            }

            recreateFloatingPreview(previewData) {
                const { file, type } = previewData;
                
                // 根据文件类型设置图标和标题
                let icon, title;
                switch (type) {
                    case 'audio':
                        icon = '🎵';
                        title = '音乐播放';
                        break;
                    case 'video':
                        icon = '🎬';
                        title = '视频播放';
                        break;
                    case 'image':
                        icon = '🖼️';
                        title = '图片预览';
                        break;
                    case 'text':
                        icon = '📝';
                        title = '文本预览';
                        break;
                    case 'pdf':
                        icon = '📄';
                        title = 'PDF预览';
                        break;
                    default:
                        icon = '📄';
                        title = '文件预览';
                }

                updateFloatingPreview(icon, title, file.name, type);
                showFloatingPreview();
                
                // 添加持久化标识
                const floatingPreview = document.getElementById('floatingPreview');
                floatingPreview.classList.add('persistent');
                
                // 添加恢复提示
                this.showRestoreHint();
            }

            showRestoreHint() {
                const floatingPreview = document.getElementById('floatingPreview');
                if (floatingPreview) {
                    // 添加一个临时的提示元素
                    const hint = document.createElement('div');
                    hint.style.cssText = `
                        position: absolute;
                        top: -30px;
                        left: 50%;
                        transform: translateX(-50%);
                        background: rgba(0,0,0,0.8);
                        color: white;
                        padding: 4px 8px;
                        border-radius: 12px;
                        font-size: 0.7rem;
                        white-space: nowrap;
                        pointer-events: none;
                        z-index: 10002;
                    `;
                    hint.textContent = '已恢复预览状态';
                    floatingPreview.appendChild(hint);
                    
                    // 3秒后移除提示
                    setTimeout(() => {
                        if (hint.parentNode) {
                            hint.parentNode.removeChild(hint);
                        }
                    }, 3000);
                }
            }

            clearPreview() {
                localStorage.removeItem(this.storageKey);
                localStorage.removeItem(this.mediaStateKey);
            }
        }

        // 初始化持久化预览管理器
        const persistentPreview = new PersistentPreview();

        function showModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.style.display = 'block';
            // 为移动端添加特殊类名以启用flex布局
            if (window.innerWidth <= 768) {
                modal.classList.add('modal-show');
            }
        }

        function hideModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.style.display = 'none';
            modal.classList.remove('modal-show');
            if (modalId === 'previewModal') {
                hideFloatingPreview();
                currentPreviewFile = null;
                currentPreviewType = null;
                isPreviewMinimized = false;
                
                // 清除持久化状态
                persistentPreview.clearPreview();
            }
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
            currentPreviewFile = { path, name };
            document.getElementById('previewTitle').textContent = name;
            const content = document.getElementById('previewContent');
            
            // 重置收起状态
            isPreviewMinimized = false;
            hideFloatingPreview();
            
            // 显示加载状态
            content.innerHTML = '<p>正在加载预览...</p>';
            showModal('previewModal');
            
            // 根据文件类型生成预览
            const ext = name.split('.').pop().toLowerCase();
            const previewUrl = `transfer.php?account=<?php echo urlencode($currentAccountKey); ?>&path=${encodeURIComponent(path)}`;
            
            if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'].includes(ext)) {
                currentPreviewType = 'image';
                content.innerHTML = `<img src="${previewUrl}" alt="${name}" style="max-width: 100%; max-height: 70vh;">`;
                updateFloatingPreview('🖼️', `图片预览`, name, 'image');
            } else if (['mp4', 'webm', 'ogg'].includes(ext)) {
                currentPreviewType = 'video';
                content.innerHTML = `<video controls style="max-width: 100%; max-height: 70vh;" onplay="onMediaPlay()" onpause="onMediaPause()"><source src="${previewUrl}" type="video/${ext}"></video>`;
                updateFloatingPreview('🎬', `视频播放`, name, 'video');
                
                // 初始化全局视频播放器
                initGlobalMediaPlayer(previewUrl, 'video');
            } else if (['mp3', 'wav', 'ogg', 'aac'].includes(ext)) {
                currentPreviewType = 'audio';
                content.innerHTML = `<audio controls style="width: 100%;" autoplay onplay="onMediaPlay()" onpause="onMediaPause()"><source src="${previewUrl}" type="audio/${ext}"></audio>`;
                updateFloatingPreview('🎵', `音乐播放`, name, 'audio');
                
                // 初始化全局音频播放器
                initGlobalMediaPlayer(previewUrl, 'audio');
            } else if (['txt', 'md', 'html', 'css', 'js', 'json', 'xml', 'csv', 'php', 'py', 'java', 'cpp', 'c', 'h', 'sql', 'yaml', 'yml'].includes(ext)) {
                currentPreviewType = 'text';
                // 文本文件预览
                fetch(previewUrl)
                    .then(response => response.text())
                    .then(text => {
                        content.innerHTML = `<pre style="text-align: left; max-height: 60vh; overflow: auto; background: #f7fafc; padding: 1rem; border-radius: 6px;">${escapeHtml(text)}</pre>`;
                    })
                    .catch(error => {
                        content.innerHTML = '<p>预览失败：无法加载文件内容</p>';
                    });
                updateFloatingPreview('📝', `文本预览`, name, 'text');
            } else if (ext === 'pdf') {
                currentPreviewType = 'pdf';
                content.innerHTML = `<iframe src="${previewUrl}" style="width: 100%; height: 70vh; border: none;"></iframe>`;
                updateFloatingPreview('📄', `PDF预览`, name, 'pdf');
            } else {
                currentPreviewType = 'other';
                content.innerHTML = '<p>此文件类型暂不支持预览</p>';
                updateFloatingPreview('📄', `预览`, name, 'other');
            }
        }

        function initGlobalMediaPlayer(url, type) {
            const globalPlayer = persistentPreview.getGlobalPlayer();
            if (globalPlayer) {
                globalPlayer.src = url;
                globalPlayer.load();
                
                // 同步播放状态
                const modalPlayer = document.querySelector(`#previewContent ${type}`);
                if (modalPlayer) {
                    // 当模态框播放器开始播放时，同步到全局播放器
                    modalPlayer.addEventListener('play', () => {
                        globalPlayer.currentTime = modalPlayer.currentTime;
                        globalPlayer.play();
                    });
                    
                    modalPlayer.addEventListener('pause', () => {
                        globalPlayer.pause();
                    });
                    
                    modalPlayer.addEventListener('timeupdate', () => {
                        if (Math.abs(globalPlayer.currentTime - modalPlayer.currentTime) > 1) {
                            globalPlayer.currentTime = modalPlayer.currentTime;
                        }
                    });
                    
                    // 当全局播放器状态变化时，同步到模态框播放器
                    globalPlayer.addEventListener('play', () => {
                        if (modalPlayer.paused) {
                            modalPlayer.currentTime = globalPlayer.currentTime;
                            modalPlayer.play();
                        }
                    });
                    
                    globalPlayer.addEventListener('pause', () => {
                        if (!modalPlayer.paused) {
                            modalPlayer.pause();
                        }
                    });
                    
                    globalPlayer.addEventListener('timeupdate', () => {
                        if (Math.abs(modalPlayer.currentTime - globalPlayer.currentTime) > 1) {
                            modalPlayer.currentTime = globalPlayer.currentTime;
                        }
                    });
                }
            }
        }

        function togglePreviewMinimize() {
            if (isPreviewMinimized) {
                restorePreview();
            } else {
                minimizePreview();
            }
        }

        function minimizePreview() {
            if (!currentPreviewFile) return;
            
            const modal = document.getElementById('previewModal');
            const floatingPreview = document.getElementById('floatingPreview');
            
            console.log('Minimizing preview...'); // 调试用
            
            // 对于媒体文件，确保全局播放器已同步
            if (currentPreviewType === 'audio' || currentPreviewType === 'video') {
                const modalPlayer = document.querySelector(`#previewContent ${currentPreviewType}`);
                const globalPlayer = persistentPreview.getGlobalPlayer();
                
                if (modalPlayer && globalPlayer) {
                    globalPlayer.currentTime = modalPlayer.currentTime;
                    if (!modalPlayer.paused) {
                        globalPlayer.play();
                    }
                }
            }
            
            // 立即移除移动端显示类，避免冲突
            modal.classList.remove('modal-show');
            
            // 添加收起动画类
            modal.classList.add('minimized');
            
            // 延迟显示悬浮按钮，让动画更自然
            setTimeout(() => {
                modal.style.display = 'none';
                modal.classList.remove('minimized'); // 清理动画类
                showFloatingPreview();
                isPreviewMinimized = true;
                
                // 保存悬浮预览状态
                persistentPreview.saveFloatingPreview();
                persistentPreview.saveMediaState();
                
                console.log('Preview minimized, floating preview shown'); // 调试用
            }, 400);
        }

        function restorePreview() {
            if (!currentPreviewFile) return;
            
            const modal = document.getElementById('previewModal');
            const floatingPreview = document.getElementById('floatingPreview');
            
            // 添加恢复动画类
            floatingPreview.classList.add('restoring');
            
            setTimeout(() => {
                hideFloatingPreview();
                
                // 重新加载预览内容
                previewFile(currentPreviewFile.path, currentPreviewFile.name);
                
                // 清除持久化状态
                persistentPreview.clearPreview();
                
                // 清除动画类
                setTimeout(() => {
                    floatingPreview.classList.remove('restoring');
                }, 400);
            }, 300);
        }

        function updateFloatingPreview(icon, title, filename, type) {
            document.getElementById('floatingIcon').textContent = icon;
            document.getElementById('floatingTitle').textContent = title;
            document.getElementById('floatingSubtitle').textContent = filename;
            
            const floatingPreview = document.getElementById('floatingPreview');
            const floatingControls = document.getElementById('floatingControls');
            
            // 保存当前的状态类名（如 minimizing, restoring, playing 等）
            const currentClasses = Array.from(floatingPreview.classList).filter(cls => 
                cls !== 'floating-preview' && !['audio', 'video', 'image', 'text', 'pdf', 'other'].includes(cls)
            );
            
            // 根据类型设置样式，但保留状态类名
            floatingPreview.className = `floating-preview ${type}`;
            currentClasses.forEach(cls => floatingPreview.classList.add(cls));
            
            // 根据文件类型显示不同的控制按钮
            floatingControls.style.display = 'flex';
            
            // 更新控制按钮
            const playBtn = floatingControls.querySelector('[onclick*="toggleGlobalMediaPlayPause"]');
            const originalBtn = floatingControls.querySelector('[onclick*="openOriginalFile"]');
            const closeBtn = floatingControls.querySelector('[onclick*="closePreviewCompletely"]');
            const floatingProgress = document.getElementById('floatingProgress');
            
            if (type === 'audio' || type === 'video') {
                playBtn.style.display = 'inline-block';
                playBtn.title = '播放/暂停';
                floatingProgress.style.display = 'block'; // 显示进度条
            } else {
                playBtn.style.display = 'none';
                floatingProgress.style.display = 'none'; // 隐藏进度条
            }
            
            // 所有类型都显示查看原文件按钮
            originalBtn.style.display = 'inline-block';
            closeBtn.style.display = 'inline-block';
        }

        function showFloatingPreview() {
            const floatingPreview = document.getElementById('floatingPreview');
            
            console.log('Showing floating preview...'); // 调试用
            
            // 确保悬浮框处于正确的初始状态
            floatingPreview.style.display = 'block';
            
            // 添加收起动画，如果不存在的话
            if (!floatingPreview.classList.contains('minimizing')) {
                floatingPreview.classList.add('minimizing');
            }
            
            console.log('Floating preview display set to block'); // 调试用
            
            // 清除动画类
            setTimeout(() => {
                floatingPreview.classList.remove('minimizing');
                console.log('Floating preview animation completed'); // 调试用
            }, 600);
        }

        function hideFloatingPreview() {
            const floatingPreview = document.getElementById('floatingPreview');
            const floatingProgress = document.getElementById('floatingProgress');
            
            floatingPreview.style.display = 'none';
            floatingPreview.classList.remove('minimizing', 'restoring', 'playing', 'audio', 'video', 'image', 'text', 'pdf', 'other');
            
            if (floatingProgress) {
                floatingProgress.style.display = 'none';
            }
            
            // 停止全局播放器
            const globalPlayer = persistentPreview.getGlobalPlayer();
            if (globalPlayer) {
                globalPlayer.pause();
                globalPlayer.src = '';
            }
            
            // 清除持久化状态
            if (isPreviewMinimized) {
                persistentPreview.clearPreview();
                isPreviewMinimized = false;
            }
        }

        function onMediaPlay() {
            const floatingPreview = document.getElementById('floatingPreview');
            floatingPreview.classList.add('playing');
        }

        function onMediaPause() {
            const floatingPreview = document.getElementById('floatingPreview');
            floatingPreview.classList.remove('playing');
        }

        function onGlobalMediaPlay() {
            const floatingPreview = document.getElementById('floatingPreview');
            floatingPreview.classList.add('playing');
        }

        function onGlobalMediaPause() {
            const floatingPreview = document.getElementById('floatingPreview');
            floatingPreview.classList.remove('playing');
        }

        function onGlobalMediaEnded() {
            const floatingPreview = document.getElementById('floatingPreview');
            floatingPreview.classList.remove('playing');
        }

        function onGlobalMediaProgress() {
            const globalPlayer = persistentPreview.getGlobalPlayer();
            if (globalPlayer && globalPlayer.duration) {
                const progress = (globalPlayer.currentTime / globalPlayer.duration) * 100;
                const progressBar = document.getElementById('floatingProgressBar');
                if (progressBar) {
                    progressBar.style.width = progress + '%';
                }
                
                // 更新悬浮预览的副标题，显示播放进度
                const subtitle = document.getElementById('floatingSubtitle');
                if (subtitle && isPreviewMinimized) {
                    const currentTime = formatTime(globalPlayer.currentTime);
                    const totalTime = formatTime(globalPlayer.duration);
                    subtitle.textContent = `${currentTime} / ${totalTime}`;
                }
            }
        }

        function formatTime(seconds) {
            if (isNaN(seconds)) return '0:00';
            const mins = Math.floor(seconds / 60);
            const secs = Math.floor(seconds % 60);
            return `${mins}:${secs.toString().padStart(2, '0')}`;
        }

        function toggleGlobalMediaPlayPause(event) {
            event.stopPropagation();
            
            const globalPlayer = persistentPreview.getGlobalPlayer();
            if (globalPlayer) {
                if (globalPlayer.paused) {
                    globalPlayer.play();
                } else {
                    globalPlayer.pause();
                }
            } else {
                // 如果没有全局播放器，恢复预览
                restorePreview();
            }
        }

        function openOriginalFile(event) {
            event.stopPropagation();
            
            if (!currentPreviewFile) return;
            
            // 计算文件所在目录
            const filePath = currentPreviewFile.path;
            const directory = filePath.substring(0, filePath.lastIndexOf('/')) || '/';
            
            // 保存当前播放状态
            if (currentPreviewType === 'audio' || currentPreviewType === 'video') {
                persistentPreview.saveMediaState();
            }
            
            // 跳转到文件所在目录
            const url = `?account=<?php echo urlencode($currentAccountKey); ?>&path=${encodeURIComponent(directory)}`;
            window.location.href = url;
        }

        function closePreviewCompletely(event) {
            event.stopPropagation();
            
            // 清除持久化状态
            persistentPreview.clearPreview();
            
            hideModal('previewModal');
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
                if (event.target === modal && modal.id !== 'previewModal') {
                    hideModal(modal.id);
                }
            });
            
            // 预览模态框需要特殊处理，避免误关闭正在播放的媒体
            const previewModal = document.getElementById('previewModal');
            if (event.target === previewModal && !isPreviewMinimized) {
                if (currentPreviewType === 'audio' || currentPreviewType === 'video') {
                    // 对于音视频文件，提示用户可以收起而不是关闭
                    if (confirm('是否要收起预览？音视频将继续播放。点击"确定"收起，点击"取消"完全关闭。')) {
                        minimizePreview();
                    } else {
                        hideModal('previewModal');
                    }
                } else {
                    hideModal('previewModal');
                }
            }
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
                if (isPreviewMinimized) {
                    // 如果预览已收起，ESC键恢复预览
                    restorePreview();
                } else {
                    // 普通情况下关闭所有模态框
                    document.querySelectorAll('.modal').forEach(modal => {
                        if (modal.id === 'previewModal' && (currentPreviewType === 'audio' || currentPreviewType === 'video')) {
                            // 音视频文件按ESC收起而不是关闭
                            minimizePreview();
                        } else {
                            hideModal(modal.id);
                        }
                    });
                }
            }
            
            // 空格键控制音视频播放/暂停
            if (e.key === ' ' && (currentPreviewType === 'audio' || currentPreviewType === 'video')) {
                e.preventDefault();
                const mediaElement = document.querySelector('#previewContent audio, #previewContent video');
                if (mediaElement) {
                    if (mediaElement.paused) {
                        mediaElement.play();
                    } else {
                        mediaElement.pause();
                    }
                }
            }
        });

        // 移动端优化功能
        function initMobileOptimizations() {
            // 检测是否为移动设备
            const isMobile = window.innerWidth <= 768;
            
            if (isMobile) {
                // 为文件名添加触摸滚动效果
                const fileNameScrolls = document.querySelectorAll('.file-name-scroll');
                fileNameScrolls.forEach(scroll => {
                    let touchStartTime = 0;
                    
                    scroll.addEventListener('touchstart', function(e) {
                        touchStartTime = Date.now();
                        const inner = scroll.querySelector('.file-name-inner');
                        if (inner) {
                            inner.style.animationPlayState = 'running';
                        }
                    });
                    
                    scroll.addEventListener('touchend', function(e) {
                        const touchDuration = Date.now() - touchStartTime;
                        const inner = scroll.querySelector('.file-name-inner');
                        
                        if (inner) {
                            // 如果触摸时间短，认为是点击，停止动画
                            if (touchDuration < 200) {
                                inner.style.animationPlayState = 'paused';
                            } else {
                                // 长按则继续滚动一段时间
                                setTimeout(() => {
                                    inner.style.animationPlayState = 'paused';
                                }, 2000);
                            }
                        }
                    });
                });
                
                // 优化预览模态框的触摸体验
                const previewModal = document.getElementById('previewModal');
                if (previewModal) {
                    let startY = 0;
                    let currentY = 0;
                    let isDragging = false;
                    
                    previewModal.addEventListener('touchstart', function(e) {
                        if (e.target === previewModal) {
                            startY = e.touches[0].clientY;
                            isDragging = true;
                        }
                    });
                    
                    previewModal.addEventListener('touchmove', function(e) {
                        if (isDragging && e.target === previewModal) {
                            currentY = e.touches[0].clientY;
                            const deltaY = currentY - startY;
                            
                            // 向下滑动超过50px时给出视觉反馈
                            if (deltaY > 50) {
                                previewModal.style.transform = `translateY(${deltaY * 0.3}px)`;
                                previewModal.style.opacity = Math.max(0.5, 1 - deltaY / 300);
                            }
                        }
                    });
                    
                    previewModal.addEventListener('touchend', function(e) {
                        if (isDragging && e.target === previewModal) {
                            const deltaY = currentY - startY;
                            
                            // 向下滑动超过100px时收起预览
                            if (deltaY > 100) {
                                if (currentPreviewType === 'audio' || currentPreviewType === 'video') {
                                    minimizePreview();
                                } else {
                                    hideModal('previewModal');
                                }
                            }
                            
                            // 重置样式
                            previewModal.style.transform = '';
                            previewModal.style.opacity = '';
                            isDragging = false;
                        }
                    });
                }
                
                // 添加触摸友好的选择模式
                let longPressTimer = null;
                let isLongPress = false;
                let targetItem = null;
                
                document.addEventListener('touchstart', function(e) {
                    const item = e.target.closest('.file-list-item');
                    if (item) {
                        targetItem = item;
                        isLongPress = false;
                        longPressTimer = setTimeout(() => {
                            isLongPress = true;
                            targetItem.classList.add('selecting');
                            
                            // 长按选择文件
                            const checkbox = targetItem.querySelector('.file-checkbox');
                            if (checkbox) {
                                checkbox.checked = !checkbox.checked;
                                updateSelection();
                                // 添加触觉反馈（如果支持）
                                if (navigator.vibrate) {
                                    navigator.vibrate(50);
                                }
                            }
                            
                            // 移除选择样式
                            setTimeout(() => {
                                targetItem.classList.remove('selecting');
                            }, 200);
                        }, 500);
                    }
                });
                
                document.addEventListener('touchend', function(e) {
                    if (longPressTimer) {
                        clearTimeout(longPressTimer);
                    }
                    if (targetItem) {
                        targetItem.classList.remove('selecting');
                        targetItem = null;
                    }
                });
                
                document.addEventListener('touchmove', function(e) {
                    if (longPressTimer) {
                        clearTimeout(longPressTimer);
                    }
                    if (targetItem) {
                        targetItem.classList.remove('selecting');
                    }
                });
            }
        }

        // 响应式处理
        function handleResize() {
            const isMobile = window.innerWidth <= 768;
            
            // 移动端时调整悬浮预览位置
            if (isMobile && isPreviewMinimized) {
                const floatingPreview = document.getElementById('floatingPreview');
                if (floatingPreview) {
                    floatingPreview.style.bottom = '10px';
                    floatingPreview.style.right = '10px';
                }
            }
        }

        // 移动端选择模式
        let isMobileSelectMode = false;
        function toggleMobileSelectMode() {
            isMobileSelectMode = !isMobileSelectMode;
            const selectBtn = document.getElementById('mobileSelectBtn');
            const cards = document.querySelectorAll('.file-card');
            
            if (isMobileSelectMode) {
                selectBtn.classList.add('btn-warning');
                selectBtn.innerHTML = '<span>✕</span><small>取消</small>';
                cards.forEach(card => {
                    card.style.paddingTop = '3rem'; // 为选择框留出空间
                    const checkbox = card.querySelector('.file-card-select');
                    checkbox.style.display = 'block';
                });
                
                // 显示批量操作栏
                const bulkActions = document.getElementById('bulkActions');
                if (bulkActions) {
                    bulkActions.classList.add('show');
                }
            } else {
                selectBtn.classList.remove('btn-warning');
                selectBtn.innerHTML = '<span>☑️</span><small>选择</small>';
                cards.forEach(card => {
                    card.style.paddingTop = '1rem';
                    const checkbox = card.querySelector('.file-card-select');
                    checkbox.style.display = 'none';
                    checkbox.checked = false;
                });
                
                // 隐藏批量操作栏
                clearSelection();
            }
        }
        
        // 更新移动端选择状态
        function updateMobileSelection(checkbox) {
            const card = checkbox.closest('.file-card');
            if (checkbox.checked) {
                card.classList.add('selected');
                selectedFiles.add(checkbox.value);
            } else {
                card.classList.remove('selected');
                selectedFiles.delete(checkbox.value);
            }
            updateSelection();
        }
        
        // 处理移动端文件上传
        function handleMobileUpload(input) {
            if (input.files.length > 0) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.enctype = 'multipart/form-data';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'upload';
                
                const fileInput = document.createElement('input');
                fileInput.type = 'file';
                fileInput.name = 'file';
                fileInput.files = input.files;
                
                form.appendChild(actionInput);
                form.appendChild(fileInput);
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // 滚动到顶部
        function scrollToTop() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }
        

        
        // 长按选择功能
        function initLongPressSelect() {
            const cards = document.querySelectorAll('.file-card');
            let longPressTimer;
            let targetCard = null;
            
            cards.forEach(card => {
                card.addEventListener('touchstart', function(e) {
                    if (isMobileSelectMode) return;
                    
                    targetCard = card;
                    card.classList.add('selecting');
                    
                    longPressTimer = setTimeout(() => {
                        // 触发选择模式
                        if (!isMobileSelectMode) {
                            toggleMobileSelectMode();
                        }
                        
                        // 选中当前项
                        const checkbox = card.querySelector('.file-card-select');
                        checkbox.checked = true;
                        updateMobileSelection(checkbox);
                        
                        // 触觉反馈
                        if (navigator.vibrate) {
                            navigator.vibrate(50);
                        }
                        
                        card.classList.remove('selecting');
                    }, 500);
                }, { passive: true });
                
                card.addEventListener('touchend', function(e) {
                    if (longPressTimer) {
                        clearTimeout(longPressTimer);
                    }
                    if (targetCard) {
                        targetCard.classList.remove('selecting');
                        targetCard = null;
                    }
                }, { passive: true });
                
                card.addEventListener('touchmove', function(e) {
                    if (longPressTimer) {
                        clearTimeout(longPressTimer);
                    }
                    if (targetCard) {
                        targetCard.classList.remove('selecting');
                    }
                }, { passive: true });
            });
        }
        
        // 增强的移动端初始化
        function initMobileOptimizations() {
            // 检测移动设备
            const isMobile = window.innerWidth <= 768;
            
            if (isMobile) {
                // 显示底部操作栏
                document.getElementById('mobileBottomBar').classList.add('show');
                
                // 初始化滚动监听，显示返回顶部按钮
                let scrollTimer;
                window.addEventListener('scroll', function() {
                    const fab = document.getElementById('mobileFab');
                    if (window.scrollY > 300) {
                        fab.classList.add('show');
                    } else {
                        fab.classList.remove('show');
                    }
                    
                    // 滚动时隐藏底部栏，停止时显示
                    const bottomBar = document.getElementById('mobileBottomBar');
                    bottomBar.style.transform = 'translateY(100%)';
                    clearTimeout(scrollTimer);
                    scrollTimer = setTimeout(() => {
                        bottomBar.style.transform = 'translateY(0)';
                    }, 150);
                });
                
                // 初始化长按选择
                initLongPressSelect();
            }
        }
        
        // 增强搜索功能 - 支持移动端卡片
        function filterFiles() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const desktopItems = document.querySelectorAll('.file-list-item');
            const mobileCards = document.querySelectorAll('.file-card');
            
            // 过滤桌面版列表
            desktopItems.forEach(item => {
                const fileName = item.dataset.name;
                if (fileName.includes(searchTerm)) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
            
            // 过滤移动版卡片
            mobileCards.forEach(card => {
                const fileName = card.dataset.name;
                if (fileName.includes(searchTerm)) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        // 响应式处理
        function handleResize() {
            const isMobile = window.innerWidth <= 768;
            const bottomBar = document.getElementById('mobileBottomBar');
            const fab = document.getElementById('mobileFab');
            
            if (isMobile) {
                bottomBar.classList.add('show');
                if (window.scrollY > 300) {
                    fab.classList.add('show');
                }
                
                // 重新初始化移动端功能
                setTimeout(() => {
                    initLongPressSelect();
                }, 100);
            } else {
                bottomBar.classList.remove('show');
                fab.classList.remove('show');
                
                // 退出选择模式
                if (isMobileSelectMode) {
                    toggleMobileSelectMode();
                }
            }
            
            // 处理显示中的模态框的类名
            const visibleModals = document.querySelectorAll('.modal[style*="display: block"], .modal[style*="display:block"]');
            visibleModals.forEach(modal => {
                if (isMobile) {
                    modal.classList.add('modal-show');
                } else {
                    modal.classList.remove('modal-show');
                }
            });
            
            // 移动端时调整悬浮预览位置
            if (isMobile && isPreviewMinimized) {
                const floatingPreview = document.getElementById('floatingPreview');
                if (floatingPreview) {
                    floatingPreview.style.bottom = '10px';
                    floatingPreview.style.right = '10px';
                }
            }
            
            // 更新面包屑显示
            if (typeof updateBreadcrumbVisibility === 'function') {
                updateBreadcrumbVisibility();
            }
        }

        // 初始化
        document.addEventListener('DOMContentLoaded', function() {
            // 确保所有模态框在页面加载时都是隐藏的
            document.querySelectorAll('.modal').forEach(modal => {
                modal.classList.remove('modal-show');
            });
            
            initMobileOptimizations();
            initResponsiveBreadcrumb();
            
            // 为底部操作栏添加平滑过渡
            const bottomBar = document.getElementById('mobileBottomBar');
            if (bottomBar) {
                bottomBar.style.transition = 'transform 0.3s ease';
            }
        });

        // 响应式面包屑导航
        let updateBreadcrumbVisibility; // 声明为全局变量
        
        function initResponsiveBreadcrumb() {
            const breadcrumb = document.getElementById('breadcrumbNav');
            if (!breadcrumb) return;

            updateBreadcrumbVisibility = function() {
                const isMobile = window.innerWidth <= 768;
                const isSmallMobile = window.innerWidth <= 480;
                const items = breadcrumb.querySelectorAll('.breadcrumb-item');
                
                if (isSmallMobile && items.length > 6) {
                    // 超小屏幕：只显示最后3个项目
                    items.forEach((item, index) => {
                        if (index < items.length - 6) {
                            item.style.display = 'none';
                        } else {
                            item.style.display = 'inline-block';
                        }
                    });
                    breadcrumb.classList.add('has-overflow');
                } else if (isMobile && items.length > 8) {
                    // 普通移动屏幕：只显示最后4个项目
                    items.forEach((item, index) => {
                        if (index < items.length - 8) {
                            item.style.display = 'none';
                        } else {
                            item.style.display = 'inline-block';
                        }
                    });
                    breadcrumb.classList.add('has-overflow');
                } else {
                    // 桌面或路径不长：显示所有项目
                    items.forEach(item => {
                        item.style.display = 'inline-block';
                    });
                    breadcrumb.classList.remove('has-overflow');
                }
            };

            // 初始化和窗口大小变化时更新
            updateBreadcrumbVisibility();
            window.addEventListener('resize', updateBreadcrumbVisibility);
            window.addEventListener('orientationchange', function() {
                setTimeout(updateBreadcrumbVisibility, 100);
            });
        }

        window.addEventListener('resize', handleResize);
        window.addEventListener('orientationchange', function() {
            setTimeout(handleResize, 100);
        });
    </script>
</body>
</html>
