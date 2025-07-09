<?php
class Database {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        $dbPath = __DIR__ . '/../data/bookmarks.db';
        
        // 确保数据目录存在
        $dataDir = dirname($dbPath);
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
        
        try {
            $this->pdo = new PDO('sqlite:' . $dbPath);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->initTables();
        } catch (PDOException $e) {
            throw new Exception('Database connection failed: ' . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function initTables() {
        // 用户表
        $sql = "CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            is_admin INTEGER DEFAULT 0,
            api_key TEXT DEFAULT '112233',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )";
        $this->pdo->exec($sql);
        
        // 检查并添加api_key字段（兼容已有数据库）
        try {
            $this->pdo->exec("ALTER TABLE users ADD COLUMN api_key TEXT DEFAULT '112233'");
        } catch (PDOException $e) {
            // 字段已存在，忽略错误
        }
        
        // 用户WebDAV配置表
        $sql = "CREATE TABLE IF NOT EXISTS user_webdav_configs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            account_key TEXT NOT NULL,
            account_name TEXT NOT NULL,
            host TEXT NOT NULL,
            username TEXT NOT NULL,
            password TEXT NOT NULL,
            path TEXT DEFAULT '/',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id),
            UNIQUE(user_id, account_key)
        )";
        $this->pdo->exec($sql);
        
        // 书签表 - 添加用户关联
        $sql = "CREATE TABLE IF NOT EXISTS bookmarks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            account_key TEXT NOT NULL,
            path TEXT NOT NULL,
            description TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )";
        $this->pdo->exec($sql);
        
        // 创建索引
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_users_username ON users(username)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_user_webdav_user_id ON user_webdav_configs(user_id)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_bookmarks_user_id ON bookmarks(user_id)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_bookmarks_account_key ON bookmarks(account_key)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_bookmarks_created_at ON bookmarks(created_at)");
        
        // 创建默认超级管理员账户
        $this->createDefaultAdmin();
    }
    
    private function createDefaultAdmin() {
        try {
            // 检查是否已存在管理员
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE is_admin = 1");
            $stmt->execute();
            $adminCount = $stmt->fetchColumn();
            
            if ($adminCount == 0) {
                // 创建默认管理员账户
                $defaultPassword = password_hash('admin123', PASSWORD_DEFAULT);
                $stmt = $this->pdo->prepare("INSERT INTO users (username, password, is_admin) VALUES (?, ?, 1)");
                $stmt->execute(['admin', $defaultPassword]);
            }
        } catch (PDOException $e) {
            // 如果表不存在或其他错误，忽略
        }
    }
    
    public function getPDO() {
        return $this->pdo;
    }
}
?>
