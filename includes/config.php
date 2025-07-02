<?php
class Config {
    private static $instance = null;
    private $config = [];
    
    private function __construct() {
        $this->loadConfig();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function loadConfig() {
        $envFile = __DIR__ . '/../.env';
        if (!file_exists($envFile)) {
            throw new Exception('.env file not found');
        }
        
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '#') === 0 || strpos($line, '=') === false) {
                continue;
            }
            
            list($key, $value) = explode('=', $line, 2);
            $this->config[trim($key)] = trim($value);
        }
    }
    
    public function get($key, $default = null) {
        return isset($this->config[$key]) ? $this->config[$key] : $default;
    }
    
    public function set($key, $value) {
        $this->config[$key] = $value;
    }
    
    public function getAccounts() {
        $accountsStr = $this->get('WEBDAV_ACCOUNTS', '');
        if (empty($accountsStr)) {
            return [];
        }
        
        $accountKeys = explode(',', $accountsStr);
        $accounts = [];
        
        foreach ($accountKeys as $key) {
            $key = trim($key);
            $upperKey = strtoupper($key); // 转换为大写来匹配配置key
            $account = [
                'key' => $key,
                'name' => $this->get("WEBDAV_{$upperKey}_NAME", $key),
                'host' => $this->get("WEBDAV_{$upperKey}_HOST", ''),
                'username' => $this->get("WEBDAV_{$upperKey}_USERNAME", ''),
                'password' => $this->get("WEBDAV_{$upperKey}_PASSWORD", ''),
                'path' => $this->get("WEBDAV_{$upperKey}_PATH", '/')
            ];
            
            if (!empty($account['host'])) {
                $accounts[$key] = $account;
            }
        }
        
        return $accounts;
    }
    
    public function saveConfig() {
        $envFile = __DIR__ . '/../.env';
        $content = '';
        
        // 添加注释
        $content .= "# 登录密码\n";
        $content .= "ADMIN_PASSWORD=" . $this->get('ADMIN_PASSWORD', 'admin123') . "\n\n";
        
        // 账户列表
        $accounts = [];
        foreach ($this->config as $key => $value) {
            if (strpos($key, 'WEBDAV_') === 0 && strpos($key, '_NAME') !== false) {
                $accountKey = str_replace('WEBDAV_', '', str_replace('_NAME', '', $key));
                $accounts[] = strtolower($accountKey);
            }
        }
        
        if (!empty($accounts)) {
            $content .= "# WebDAV账户配置 (支持多账户，用逗号分隔)\n";
            $content .= "WEBDAV_ACCOUNTS=" . implode(',', $accounts) . "\n\n";
            
            foreach ($accounts as $account) {
                $upperAccount = strtoupper($account);
                $content .= "# {$account}配置\n";
                $content .= "WEBDAV_{$upperAccount}_NAME=" . $this->get("WEBDAV_{$upperAccount}_NAME", '') . "\n";
                $content .= "WEBDAV_{$upperAccount}_HOST=" . $this->get("WEBDAV_{$upperAccount}_HOST", '') . "\n";
                $content .= "WEBDAV_{$upperAccount}_USERNAME=" . $this->get("WEBDAV_{$upperAccount}_USERNAME", '') . "\n";
                $content .= "WEBDAV_{$upperAccount}_PASSWORD=" . $this->get("WEBDAV_{$upperAccount}_PASSWORD", '') . "\n";
                $content .= "WEBDAV_{$upperAccount}_PATH=" . $this->get("WEBDAV_{$upperAccount}_PATH", '/') . "\n\n";
            }
        }
        
        $content .= "# 直链域名配置\n";
        $content .= "DIRECT_LINK_DOMAIN=" . $this->get('DIRECT_LINK_DOMAIN', 'http://localhost') . "\n";
        
        file_put_contents($envFile, $content);
        $this->loadConfig(); // 重新加载配置
    }
}
?>
