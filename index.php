<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';

Auth::requireLogin();

$config = Config::getInstance();
$accounts = $config->getAccounts();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WebDAV管理系统</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f7fa;
            min-height: 100vh;
        }
        
        .header {
            background: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            color: #333;
        }
        
        .header-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-1px);
            opacity: 0.9;
        }
        
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .account-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }
        
        .account-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        
        .account-card:hover {
            transform: translateY(-2px);
        }
        
        .account-name {
            font-size: 1.2rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .account-info {
            color: #666;
            margin-bottom: 1rem;
        }
        
        .account-info div {
            margin-bottom: 0.25rem;
        }
        
        .account-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .welcome {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .welcome h2 {
            color: #333;
            margin-bottom: 1rem;
        }
        
        .welcome p {
            color: #666;
            margin-bottom: 2rem;
        }
        
        .no-accounts {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            text-align: center;
            margin-top: 2rem;
        }
        
        .no-accounts h3 {
            color: #666;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>WebDAV管理系统</h1>
        <div class="header-actions">
            <a href="admin.php" class="btn btn-primary">后台管理</a>
            <a href="logout.php" class="btn btn-secondary">退出登录</a>
        </div>
    </div>
    
    <div class="container">
        <div class="welcome">
            <h2>欢迎使用WebDAV管理系统</h2>
            <p>选择一个WebDAV账户开始管理您的文件</p>
        </div>
        
        <?php if (empty($accounts)): ?>
            <div class="no-accounts">
                <h3>暂无WebDAV账户</h3>
                <p>请前往后台管理添加WebDAV账户配置</p>
                <a href="admin.php" class="btn btn-primary">前往后台管理</a>
            </div>
        <?php else: ?>
            <div class="account-grid">
                <?php foreach ($accounts as $account): ?>
                    <div class="account-card">
                        <div class="account-name"><?php echo htmlspecialchars($account['name']); ?></div>
                        <div class="account-info">
                            <div><strong>服务器:</strong> <?php echo htmlspecialchars($account['host']); ?></div>
                            <div><strong>用户名:</strong> <?php echo htmlspecialchars($account['username']); ?></div>
                            <div><strong>路径:</strong> <?php echo htmlspecialchars($account['path']); ?></div>
                        </div>
                        <div class="account-actions">
                            <a href="filemanager.php?account=<?php echo urlencode($account['key']); ?>" class="btn btn-primary">打开文件管理器</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
