<?php
require_once 'database.php';

class Bookmark {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getPDO();
    }
    
    /**
     * 添加书签
     */
    public function addBookmark($userId, $name, $accountKey, $path, $description = '') {
        // 检查是否已存在相同的书签
        if ($this->bookmarkExists($userId, $accountKey, $path)) {
            throw new Exception('该路径的书签已存在');
        }
        
        $sql = "INSERT INTO bookmarks (user_id, name, account_key, path, description) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        
        try {
            $stmt->execute([$userId, $name, $accountKey, $path, $description]);
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            throw new Exception('添加书签失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 删除书签
     */
    public function deleteBookmark($userId, $id) {
        $sql = "DELETE FROM bookmarks WHERE id = ? AND user_id = ?";
        $stmt = $this->db->prepare($sql);
        
        try {
            $stmt->execute([$id, $userId]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            throw new Exception('删除书签失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 更新书签
     */
    public function updateBookmark($userId, $id, $name, $description = '') {
        $sql = "UPDATE bookmarks SET name = ?, description = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?";
        $stmt = $this->db->prepare($sql);
        
        try {
            $stmt->execute([$name, $description, $id, $userId]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            throw new Exception('更新书签失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 获取用户的所有书签
     */
    public function getAllBookmarks($userId) {
        $sql = "SELECT * FROM bookmarks WHERE user_id = ? ORDER BY created_at DESC";
        $stmt = $this->db->prepare($sql);
        
        try {
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception('获取书签失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 根据账户获取用户书签
     */
    public function getBookmarksByAccount($userId, $accountKey) {
        $sql = "SELECT * FROM bookmarks WHERE user_id = ? AND account_key = ? ORDER BY created_at DESC";
        $stmt = $this->db->prepare($sql);
        
        try {
            $stmt->execute([$userId, $accountKey]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception('获取书签失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 根据ID获取书签
     */
    public function getBookmarkById($userId, $id) {
        $sql = "SELECT * FROM bookmarks WHERE id = ? AND user_id = ?";
        $stmt = $this->db->prepare($sql);
        
        try {
            $stmt->execute([$id, $userId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception('获取书签失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 检查书签是否存在
     */
    public function bookmarkExists($userId, $accountKey, $path) {
        $sql = "SELECT COUNT(*) FROM bookmarks WHERE user_id = ? AND account_key = ? AND path = ?";
        $stmt = $this->db->prepare($sql);
        
        try {
            $stmt->execute([$userId, $accountKey, $path]);
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * 获取用户书签统计
     */
    public function getBookmarkStats($userId) {
        $sql = "SELECT 
                    COUNT(*) as total,
                    COUNT(DISTINCT account_key) as accounts,
                    MAX(created_at) as latest
                FROM bookmarks WHERE user_id = ?";
        $stmt = $this->db->prepare($sql);
        
        try {
            $stmt->execute([$userId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return ['total' => 0, 'accounts' => 0, 'latest' => null];
        }
    }
    
    /**
     * 搜索用户书签
     */
    public function searchBookmarks($userId, $keyword) {
        $sql = "SELECT * FROM bookmarks 
                WHERE user_id = ? AND (name LIKE ? OR description LIKE ? OR path LIKE ?)
                ORDER BY created_at DESC";
        $stmt = $this->db->prepare($sql);
        $searchTerm = '%' . $keyword . '%';
        
        try {
            $stmt->execute([$userId, $searchTerm, $searchTerm, $searchTerm]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception('搜索书签失败: ' . $e->getMessage());
        }
    }
}
?>
