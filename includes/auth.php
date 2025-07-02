<?php
require_once 'config.php';

session_start();

class Auth {
    public static function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    public static function login($password) {
        $config = Config::getInstance();
        $adminPassword = $config->get('ADMIN_PASSWORD', 'admin123');
        
        if ($password === $adminPassword) {
            $_SESSION['logged_in'] = true;
            return true;
        }
        return false;
    }
    
    public static function logout() {
        session_destroy();
    }
    
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            header('Location: login.php');
            exit;
        }
    }
}
?>
