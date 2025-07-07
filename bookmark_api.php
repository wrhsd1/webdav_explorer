<?php
require_once 'includes/auth.php';
require_once 'includes/bookmark.php';

Auth::requireLogin();

header('Content-Type: application/json');

try {
    $bookmarkManager = new Bookmark();
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'get_all':
            $bookmarks = $bookmarkManager->getAllBookmarks();
            echo json_encode(['success' => true, 'bookmarks' => $bookmarks]);
            break;
            
        case 'get_by_account':
            $accountKey = $_GET['account'] ?? '';
            if (empty($accountKey)) {
                throw new Exception('账户参数不能为空');
            }
            $bookmarks = $bookmarkManager->getBookmarksByAccount($accountKey);
            echo json_encode(['success' => true, 'bookmarks' => $bookmarks]);
            break;
            
        case 'add_bookmark':
            $name = $_POST['name'] ?? '';
            $accountKey = $_POST['account'] ?? '';
            $path = $_POST['path'] ?? '';
            $description = $_POST['description'] ?? '';
            
            if (empty($name) || empty($accountKey) || empty($path)) {
                throw new Exception('书签名称、账户和路径不能为空');
            }
            
            $id = $bookmarkManager->addBookmark($name, $accountKey, $path, $description);
            echo json_encode(['success' => true, 'id' => $id, 'message' => '书签添加成功']);
            break;
            
        case 'delete_bookmark':
            $id = $_POST['bookmark_id'] ?? '';
            if (empty($id)) {
                throw new Exception('书签ID不能为空');
            }
            
            $success = $bookmarkManager->deleteBookmark($id);
            if ($success) {
                echo json_encode(['success' => true, 'message' => '书签删除成功']);
            } else {
                throw new Exception('书签删除失败');
            }
            break;
            
        case 'update_bookmark':
            $id = $_POST['bookmark_id'] ?? '';
            $name = $_POST['name'] ?? '';
            $description = $_POST['description'] ?? '';
            
            if (empty($id) || empty($name)) {
                throw new Exception('书签ID和名称不能为空');
            }
            
            $success = $bookmarkManager->updateBookmark($id, $name, $description);
            if ($success) {
                echo json_encode(['success' => true, 'message' => '书签更新成功']);
            } else {
                throw new Exception('书签更新失败');
            }
            break;
            
        case 'search':
            $keyword = $_GET['keyword'] ?? '';
            if (empty($keyword)) {
                throw new Exception('搜索关键词不能为空');
            }
            
            $bookmarks = $bookmarkManager->searchBookmarks($keyword);
            echo json_encode(['success' => true, 'bookmarks' => $bookmarks]);
            break;
            
        case 'get_stats':
            $stats = $bookmarkManager->getBookmarkStats();
            echo json_encode(['success' => true, 'stats' => $stats]);
            break;
            
        default:
            throw new Exception('未知的操作');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
