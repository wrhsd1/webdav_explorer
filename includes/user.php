<?php
require_once 'database.php';

class User {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getPDO();
    }
    
    /**
     * 创建新用户
     */
    public function createUser($username, $password, $isAdmin = false) {
        try {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $this->db->prepare("INSERT INTO users (username, password, is_admin) VALUES (?, ?, ?)");
            $stmt->execute([$username, $hashedPassword, $isAdmin ? 1 : 0]);
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            throw new Exception('创建用户失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 验证用户登录
     */
    public function verifyUser($username, $password) {
        try {
            $stmt = $this->db->prepare("SELECT id, username, password, is_admin FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                return [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'is_admin' => (bool)$user['is_admin']
                ];
            }
            return false;
        } catch (PDOException $e) {
            throw new Exception('登录验证失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 获取用户信息
     */
    public function getUserById($userId) {
        try {
            $stmt = $this->db->prepare("SELECT id, username, is_admin, created_at FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception('获取用户信息失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 获取所有用户
     */
    public function getAllUsers() {
        try {
            $stmt = $this->db->prepare("SELECT id, username, is_admin, created_at FROM users ORDER BY created_at DESC");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception('获取用户列表失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 更新用户密码
     */
    public function updatePassword($userId, $newPassword) {
        try {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $this->db->prepare("UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$hashedPassword, $userId]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            throw new Exception('更新密码失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 删除用户
     */
    public function deleteUser($userId) {
        try {
            $this->db->beginTransaction();
            
            // 删除用户的WebDAV配置
            $stmt = $this->db->prepare("DELETE FROM user_webdav_configs WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            // 删除用户的书签
            $stmt = $this->db->prepare("DELETE FROM bookmarks WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            // 删除用户
            $stmt = $this->db->prepare("DELETE FROM users WHERE id = ? AND is_admin = 0");
            $stmt->execute([$userId]);
            
            $this->db->commit();
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            $this->db->rollBack();
            throw new Exception('删除用户失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 检查用户名是否存在
     */
    public function usernameExists($username, $excludeUserId = null) {
        try {
            $sql = "SELECT COUNT(*) FROM users WHERE username = ?";
            $params = [$username];
            
            if ($excludeUserId) {
                $sql .= " AND id != ?";
                $params[] = $excludeUserId;
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            throw new Exception('检查用户名失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 获取用户的WebDAV配置
     */
    public function getUserWebdavConfigs($userId) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM user_webdav_configs WHERE user_id = ? ORDER BY created_at DESC");
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception('获取WebDAV配置失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 添加WebDAV配置
     */
    public function addWebdavConfig($userId, $accountKey, $accountName, $host, $username, $password, $path = '/') {
        try {
            $stmt = $this->db->prepare("INSERT INTO user_webdav_configs (user_id, account_key, account_name, host, username, password, path) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $accountKey, $accountName, $host, $username, $password, $path]);
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { // UNIQUE constraint violation
                throw new Exception('账户标识已存在');
            }
            throw new Exception('添加WebDAV配置失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 更新WebDAV配置
     */
    public function updateWebdavConfig($userId, $configId, $accountName, $host, $username, $password, $path = '/') {
        try {
            $sql = "UPDATE user_webdav_configs SET account_name = ?, host = ?, username = ?, path = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?";
            $params = [$accountName, $host, $username, $path, $configId, $userId];
            
            if (!empty($password)) {
                $sql = "UPDATE user_webdav_configs SET account_name = ?, host = ?, username = ?, password = ?, path = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?";
                $params = [$accountName, $host, $username, $password, $path, $configId, $userId];
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            throw new Exception('更新WebDAV配置失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 删除WebDAV配置
     */
    public function deleteWebdavConfig($userId, $configId) {
        try {
            $stmt = $this->db->prepare("DELETE FROM user_webdav_configs WHERE id = ? AND user_id = ?");
            $stmt->execute([$configId, $userId]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            throw new Exception('删除WebDAV配置失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 获取用户的单个WebDAV配置
     */
    public function getUserWebdavConfig($userId, $accountKey) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM user_webdav_configs WHERE user_id = ? AND account_key = ?");
            $stmt->execute([$userId, $accountKey]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception('获取WebDAV配置失败: ' . $e->getMessage());
        }
    }
}
?>
