<?php
require_once 'user.php';

session_start();

class Auth {
    private static $userManager;
    
    private static function getUserManager() {
        if (self::$userManager === null) {
            self::$userManager = new User();
        }
        return self::$userManager;
    }
    
    /**
     * 检查用户是否已登录
     */
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']) && isset($_SESSION['username']);
    }
    
    /**
     * 检查是否是管理员
     */
    public static function isAdmin() {
        return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
    }
    
    /**
     * 用户登录
     */
    public static function login($username, $password) {
        try {
            $userManager = self::getUserManager();
            $user = $userManager->verifyUser($username, $password);
            
            if ($user) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['is_admin'] = $user['is_admin'];
                $_SESSION['login_time'] = time();
                
                // 更新最后登录时间
                session_regenerate_id(true);
                
                return true;
            }
            return false;
        } catch (Exception $e) {
            error_log('Login error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 用户登出
     */
    public static function logout() {
        session_destroy();
        session_start();
        session_regenerate_id(true);
    }
    
    /**
     * 要求用户登录
     */
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            header('Location: login.php');
            exit;
        }
        
        // 检查会话过期（30分钟）
        if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > 1800) {
            self::logout();
            header('Location: login.php?expired=1');
            exit;
        }
        
        // 更新最后活动时间
        $_SESSION['login_time'] = time();
    }
    
    /**
     * 要求管理员权限
     */
    public static function requireAdmin() {
        self::requireLogin();
        if (!self::isAdmin()) {
            header('Location: index.php');
            exit;
        }
    }
    
    /**
     * 获取当前用户ID
     */
    public static function getCurrentUserId() {
        return $_SESSION['user_id'] ?? null;
    }
    
    /**
     * 获取当前用户名
     */
    public static function getCurrentUsername() {
        return $_SESSION['username'] ?? null;
    }
    
    /**
     * 获取当前用户信息
     */
    public static function getCurrentUser() {
        if (!self::isLoggedIn()) {
            return null;
        }
        
        try {
            $userManager = self::getUserManager();
            return $userManager->getUserById(self::getCurrentUserId());
        } catch (Exception $e) {
            error_log('Get current user error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 检查用户权限（自己的数据或管理员）
     */
    public static function canAccessUser($userId) {
        return self::isAdmin() || self::getCurrentUserId() == $userId;
    }
}
?>
