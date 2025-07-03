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
            .preview-modal .modal-content {
                margin: 2% auto;
                padding: 1rem;
                max-width: 95vw;
                max-height: 95vh;
                border-radius: 12px;
            }
            
            .modal-header {
                margin-bottom: 1rem;
                flex-wrap: wrap;
                gap: 0.5rem;
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
            
            .preview-content img {
                max-height: 60vh;
                width: auto;
                height: auto;
            }
            
            .preview-content video {
                max-height: 50vh;
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
            }
            
            .preview-content iframe {
                height: 60vh !important;
                width: 100% !important;
            }
            
            .close {
                font-size: 1.25rem;
                padding: 0.25rem;
                line-height: 1;
            }
        }
        
        /* 超小屏幕优化 */
        @media (max-width: 480px) {
            .preview-modal .modal-content {
                margin: 1% auto;
                padding: 0.75rem;
                max-width: 98vw;
                max-height: 98vh;
            }
            
            .modal-header {
                margin-bottom: 0.75rem;
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
            }
            
            .preview-content iframe {
                height: 55vh !important;
            }
        }
        
        .floating-preview {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1001;
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
        
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .header {
                padding: 0.75rem 1rem;
                flex-direction: column;
                gap: 0.5rem;
                align-items: stretch;
            }
            
            .header h1 {
                font-size: 1.25rem;
                text-align: center;
            }
            
            .header-actions {
                justify-content: center;
                flex-wrap: wrap;
                gap: 0.5rem;
            }
            
            .header-actions span {
                order: -1;
                width: 100%;
                text-align: center;
                font-size: 0.8rem;
            }
            
            .toolbar {
                padding: 1rem;
            }
            
            .toolbar-top {
                flex-direction: column;
                align-items: stretch;
                gap: 0.75rem;
            }
            
            .breadcrumb {
                min-width: auto;
                overflow-x: auto;
                padding-bottom: 0.25rem;
            }
            
            .breadcrumb::-webkit-scrollbar {
                height: 2px;
            }
            
            .breadcrumb::-webkit-scrollbar-track {
                background: #f1f1f1;
                border-radius: 2px;
            }
            
            .breadcrumb::-webkit-scrollbar-thumb {
                background: #888;
                border-radius: 2px;
            }
            
            .search-box {
                min-width: auto;
                width: 100%;
            }
            
            .toolbar-actions {
                flex-wrap: wrap;
                gap: 0.5rem;
                justify-content: center;
            }
            
            .toolbar-actions .btn,
            .toolbar-actions .file-input-label {
                flex: 1;
                min-width: calc(50% - 0.25rem);
                justify-content: center;
                font-size: 0.8rem;
                padding: 0.5rem 0.75rem;
            }
            
            .file-list-header, .file-list-item {
                grid-template-columns: 30px 1fr 60px;
                gap: 0.5rem;
                padding: 0.5rem;
            }
            
            .file-list-header {
                font-size: 0.8rem;
            }
            
            .file-size-cell, .file-type-cell, .file-date-cell {
                display: none;
            }
            
            .file-icon-small {
                font-size: 1.25rem;
            }
            
            .file-name-cell {
                gap: 0.5rem;
                min-width: 0;
            }
            
            .file-actions-cell {
                flex-direction: column;
                gap: 0.25rem;
                align-items: center;
            }
            
            .file-actions-cell .btn {
                padding: 0.25rem;
                font-size: 0.75rem;
                min-width: 32px;
                height: 32px;
                border-radius: 4px;
            }
            
            .bulk-actions {
                padding: 0.75rem;
                flex-wrap: wrap;
                gap: 0.5rem;
            }
            
            .bulk-actions .btn {
                font-size: 0.75rem;
                padding: 0.375rem 0.75rem;
            }
            
            .modal-content {
                margin: 3% auto;
                padding: 1.25rem;
                max-width: 95vw;
            }
            
            .modal-header h3 {
                font-size: 1.1rem;
            }
            
            .form-group input,
            .form-group select,
            .form-group textarea {
                padding: 0.625rem;
                font-size: 0.9rem;
            }
            
            .modal-actions {
                gap: 0.5rem;
                flex-wrap: wrap;
            }
            
            .modal-actions .btn {
                flex: 1;
                min-width: calc(50% - 0.25rem);
                justify-content: center;
            }
        }
        
        /* 超小屏幕优化 */
        @media (max-width: 480px) {
            .header {
                padding: 0.5rem;
            }
            
            .header h1 {
                font-size: 1.1rem;
            }
            
            .container {
                padding: 0.75rem;
            }
            
            .toolbar {
                padding: 0.75rem;
            }
            
            .toolbar-actions .btn,
            .toolbar-actions .file-input-label {
                min-width: 100%;
                margin-bottom: 0.25rem;
            }
            
            .file-list-header, .file-list-item {
                grid-template-columns: 25px 1fr 50px;
                padding: 0.375rem;
            }
            
            .file-icon-small {
                font-size: 1rem;
            }
            
            .file-actions-cell .btn {
                min-width: 28px;
                height: 28px;
                font-size: 0.7rem;
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
                <div class="floating-preview-title" id="floatingTitle">音乐播放中...</div>
                <div class="floating-preview-subtitle" id="floatingSubtitle">点击展开</div>
            </div>
        </div>
        <div class="floating-preview-controls" id="floatingControls" style="display: none;">
            <button class="floating-preview-btn" onclick="toggleMediaPlayPause(event)">⏯️</button>
            <button class="floating-preview-btn" onclick="closePreviewCompletely(event)">✕</button>
        </div>
    </div>

    <script>
        let selectedFiles = new Set();
        let currentPreviewFile = null;
        let currentPreviewType = null;
        let isPreviewMinimized = false;

        function showModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }

        function hideModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            if (modalId === 'previewModal') {
                hideFloatingPreview();
                currentPreviewFile = null;
                currentPreviewType = null;
                isPreviewMinimized = false;
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
            } else if (['mp3', 'wav', 'ogg', 'aac'].includes(ext)) {
                currentPreviewType = 'audio';
                content.innerHTML = `<audio controls style="width: 100%;" autoplay onplay="onMediaPlay()" onpause="onMediaPause()"><source src="${previewUrl}" type="audio/${ext}"></audio>`;
                updateFloatingPreview('🎵', `音乐播放`, name, 'audio');
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
            
            // 添加收起动画类
            modal.classList.add('minimized');
            
            // 延迟显示悬浮按钮，让动画更自然
            setTimeout(() => {
                modal.style.display = 'none';
                showFloatingPreview();
                isPreviewMinimized = true;
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
                modal.style.display = 'block';
                modal.classList.remove('minimized');
                modal.classList.add('restored');
                isPreviewMinimized = false;
                
                // 清除动画类
                setTimeout(() => {
                    modal.classList.remove('restored');
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
            
            // 根据类型设置样式
            floatingPreview.className = `floating-preview ${type}`;
            
            // 对于音视频文件，显示控制按钮
            if (type === 'audio' || type === 'video') {
                floatingControls.style.display = 'flex';
            } else {
                floatingControls.style.display = 'none';
            }
        }

        function showFloatingPreview() {
            const floatingPreview = document.getElementById('floatingPreview');
            floatingPreview.style.display = 'block';
            floatingPreview.classList.add('minimizing');
            
            // 清除动画类
            setTimeout(() => {
                floatingPreview.classList.remove('minimizing');
            }, 600);
        }

        function hideFloatingPreview() {
            const floatingPreview = document.getElementById('floatingPreview');
            floatingPreview.style.display = 'none';
            floatingPreview.classList.remove('minimizing', 'restoring', 'playing');
        }

        function onMediaPlay() {
            const floatingPreview = document.getElementById('floatingPreview');
            floatingPreview.classList.add('playing');
        }

        function onMediaPause() {
            const floatingPreview = document.getElementById('floatingPreview');
            floatingPreview.classList.remove('playing');
        }

        function toggleMediaPlayPause(event) {
            event.stopPropagation();
            const mediaElement = document.querySelector('#previewContent audio, #previewContent video');
            if (mediaElement) {
                if (mediaElement.paused) {
                    mediaElement.play();
                } else {
                    mediaElement.pause();
                }
            }
        }

        function closePreviewCompletely(event) {
            event.stopPropagation();
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
                    modal.style.display = 'none';
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
                            modal.style.display = 'none';
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

        // 初始化
        document.addEventListener('DOMContentLoaded', function() {
            initMobileOptimizations();
        });

        window.addEventListener('resize', handleResize);
        window.addEventListener('orientationchange', function() {
            setTimeout(handleResize, 100);
        });
    </script>
</body>
</html>
