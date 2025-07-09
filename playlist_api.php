<?php
require_once 'includes/auth.php';
require_once 'includes/user.php';
require_once 'includes/webdav.php';

Auth::requireLogin();

header('Content-Type: application/json');

try {
    $userManager = new User();
    $currentUserId = Auth::getCurrentUserId();
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    $accountKey = $_GET['account'] ?? $_POST['account'] ?? '';
    
    if (empty($accountKey)) {
        throw new Exception('账户参数不能为空');
    }
    
    // 获取用户的WebDAV配置
    $userWebdavConfigs = $userManager->getUserWebdavConfigs($currentUserId);
    $account = null;
    foreach ($userWebdavConfigs as $config) {
        if ($config['account_key'] === $accountKey) {
            $account = $config;
            break;
        }
    }
    
    if (!$account) {
        throw new Exception('账户配置不存在');
    }
    
    $webdav = new WebDAVClient(
        $account['host'],
        $account['username'],
        $account['password'],
        $account['path']
    );
    
    $playlistDir = '/web_PLAYLIST';
    
    switch ($action) {
        case 'save_playlist':
            $playlistName = $_POST['name'] ?? '';
            $tracks = json_decode($_POST['tracks'] ?? '[]', true);
            
            if (empty($playlistName)) {
                throw new Exception('播放列表名称不能为空');
            }
            
            if (empty($tracks) || !is_array($tracks)) {
                throw new Exception('播放列表不能为空');
            }
            
            // 确保播放列表目录存在
            try {
                $webdav->listDirectory($playlistDir);
            } catch (Exception $e) {
                // 目录不存在，创建它
                $webdav->createDirectory($playlistDir);
            }
            
            // 准备播放列表数据
            $beijingTime = new DateTime('now', new DateTimeZone('Asia/Shanghai'));
            $playlistData = [
                'name' => $playlistName,
                'tracks' => $tracks,
                'created_at' => $beijingTime->format('Y-m-d H:i:s'),
                'account' => $accountKey,
                'version' => '1.0'
            ];
            
            // 生成文件名（确保安全）
            $safeFileName = preg_replace('/[^\w\-_\(\)]/', '_', $playlistName);
            $safeFileName = trim($safeFileName, '_');
            if (empty($safeFileName)) {
                $safeFileName = 'playlist';
            }
            $fileName = $safeFileName . '.json';
            $filePath = $playlistDir . '/' . $fileName;
            
            // 检查文件是否已存在，如果存在则添加序号
            $counter = 1;
            $originalFileName = $fileName;
            while (true) {
                try {
                    $webdav->downloadFile($filePath);
                    // 文件存在，尝试下一个序号
                    $fileName = pathinfo($originalFileName, PATHINFO_FILENAME) . '_' . $counter . '.json';
                    $filePath = $playlistDir . '/' . $fileName;
                    $counter++;
                } catch (Exception $e) {
                    // 文件不存在，可以使用这个名称
                    break;
                }
            }
            
            // 保存播放列表文件
            $jsonContent = json_encode($playlistData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $webdav->uploadFile($filePath, $jsonContent);
            
            echo json_encode([
                'success' => true,
                'message' => '播放列表保存成功',
                'filename' => $fileName,
                'path' => $filePath
            ]);
            break;
            
        case 'list_playlists':
            $playlists = [];
            
            try {
                $items = $webdav->listDirectory($playlistDir);
                
                foreach ($items as $item) {
                    if (!$item['is_dir'] && pathinfo($item['name'], PATHINFO_EXTENSION) === 'json') {
                        try {
                            $content = $webdav->downloadFile($item['path']);
                            $data = json_decode($content, true);
                            
                            if ($data && isset($data['name']) && isset($data['tracks'])) {
                                $playlists[] = [
                                    'filename' => $item['name'],
                                    'path' => $item['path'],
                                    'name' => $data['name'],
                                    'track_count' => count($data['tracks']),
                                    'created_at' => $data['created_at'] ?? '未知',
                                    'size' => $item['size'],
                                    'modified' => $item['modified']
                                ];
                            }
                        } catch (Exception $e) {
                            // 跳过无法解析的文件
                            continue;
                        }
                    }
                }
                
                // 按创建时间排序
                usort($playlists, function($a, $b) {
                    return strtotime($b['created_at']) - strtotime($a['created_at']);
                });
                
            } catch (Exception $e) {
                // 目录不存在或其他错误，返回空列表
            }
            
            echo json_encode([
                'success' => true,
                'playlists' => $playlists
            ]);
            break;
            
        case 'load_playlist':
            $filePath = $_POST['path'] ?? '';
            
            if (empty($filePath)) {
                throw new Exception('文件路径不能为空');
            }
            
            // 安全检查：确保路径在播放列表目录中
            if (strpos($filePath, $playlistDir . '/') !== 0) {
                throw new Exception('无效的文件路径');
            }
            
            $content = $webdav->downloadFile($filePath);
            $data = json_decode($content, true);
            
            if (!$data || !isset($data['tracks'])) {
                throw new Exception('播放列表文件格式无效');
            }
            
            echo json_encode([
                'success' => true,
                'playlist' => $data
            ]);
            break;
            
        case 'delete_playlist':
            $filePath = $_POST['path'] ?? '';
            
            if (empty($filePath)) {
                throw new Exception('文件路径不能为空');
            }
            
            // 安全检查：确保路径在播放列表目录中
            if (strpos($filePath, $playlistDir . '/') !== 0) {
                throw new Exception('无效的文件路径');
            }
            
            $webdav->deleteItem($filePath);
            
            echo json_encode([
                'success' => true,
                'message' => '播放列表删除成功'
            ]);
            break;
            
        case 'rename_playlist':
            $filePath = $_POST['path'] ?? '';
            $newName = $_POST['new_name'] ?? '';
            
            if (empty($filePath) || empty($newName)) {
                throw new Exception('文件路径和新名称不能为空');
            }
            
            // 安全检查：确保路径在播放列表目录中
            if (strpos($filePath, $playlistDir . '/') !== 0) {
                throw new Exception('无效的文件路径');
            }
            
            // 读取现有数据并更新名称
            $content = $webdav->downloadFile($filePath);
            $data = json_decode($content, true);
            
            if (!$data) {
                throw new Exception('播放列表文件格式无效');
            }
            
            $data['name'] = $newName;
            $beijingTime = new DateTime('now', new DateTimeZone('Asia/Shanghai'));
            $data['updated_at'] = $beijingTime->format('Y-m-d H:i:s');
            
            // 生成新文件名
            $safeFileName = preg_replace('/[^\w\-_\(\)]/', '_', $newName);
            $safeFileName = trim($safeFileName, '_');
            if (empty($safeFileName)) {
                $safeFileName = 'playlist';
            }
            $newFileName = $safeFileName . '.json';
            $newFilePath = $playlistDir . '/' . $newFileName;
            
            // 如果新路径与原路径不同，需要移动文件
            if ($newFilePath !== $filePath) {
                // 检查新文件名是否已存在
                try {
                    $webdav->downloadFile($newFilePath);
                    throw new Exception('该名称的播放列表已存在');
                } catch (Exception $e) {
                    if (strpos($e->getMessage(), '该名称的播放列表已存在') === 0) {
                        throw $e;
                    }
                    // 文件不存在，可以使用
                }
                
                // 保存到新位置
                $jsonContent = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                $webdav->uploadFile($newFilePath, $jsonContent);
                
                // 删除旧文件
                $webdav->deleteItem($filePath);
            } else {
                // 同样的文件名，只是更新内容
                $jsonContent = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                $webdav->uploadFile($filePath, $jsonContent);
            }
            
            echo json_encode([
                'success' => true,
                'message' => '播放列表重命名成功',
                'new_path' => $newFilePath
            ]);
            break;
            
        default:
            throw new Exception('未知的操作');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
