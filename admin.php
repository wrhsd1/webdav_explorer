<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';

Auth::requireLogin();

$config = Config::getInstance();
$message = '';
$error = '';

// 处理表单提交
if ($_POST) {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'update_password':
                    $newPassword = $_POST['admin_password'] ?? '';
                    if (!empty($newPassword)) {
                        $config->set('ADMIN_PASSWORD', $newPassword);
                        $config->saveConfig();
                        $message = '管理员密码已更新';
                    } else {
                        $error = '密码不能为空';
                    }
                    break;
                    
                case 'add_account':
                    $accountKey = strtoupper($_POST['account_key'] ?? '');
                    $accountName = $_POST['account_name'] ?? '';
                    $host = $_POST['host'] ?? '';
                    $username = $_POST['username'] ?? '';
                    $password = $_POST['password'] ?? '';
                    $path = $_POST['path'] ?? '/';
                    
                    if (empty($accountKey) || empty($accountName) || empty($host) || empty($username)) {
                        $error = '请填写所有必填字段';
                    } else {
                        // 更新账户列表 - 统一使用小写存储在WEBDAV_ACCOUNTS中
                        $accounts = $config->get('WEBDAV_ACCOUNTS', '');
                        $accountsList = $accounts ? explode(',', $accounts) : [];
                        $lowerAccountKey = strtolower($accountKey);
                        if (!in_array($lowerAccountKey, $accountsList)) {
                            $accountsList[] = $lowerAccountKey;
                            $config->set('WEBDAV_ACCOUNTS', implode(',', $accountsList));
                        }
                        
                        // 设置账户配置 - 使用大写的key存储具体配置
                        $config->set("WEBDAV_{$accountKey}_NAME", $accountName);
                        $config->set("WEBDAV_{$accountKey}_HOST", $host);
                        $config->set("WEBDAV_{$accountKey}_USERNAME", $username);
                        $config->set("WEBDAV_{$accountKey}_PASSWORD", $password);
                        $config->set("WEBDAV_{$accountKey}_PATH", $path);
                        
                        $config->saveConfig();
                        $message = '账户已添加';
                    }
                    break;
                    
                case 'edit_account':
                    $accountKey = strtoupper($_POST['account_key'] ?? '');
                    $accountName = $_POST['account_name'] ?? '';
                    $host = $_POST['host'] ?? '';
                    $username = $_POST['username'] ?? '';
                    $password = $_POST['password'] ?? '';
                    $path = $_POST['path'] ?? '/';
                    
                    if (empty($accountKey) || empty($accountName) || empty($host) || empty($username)) {
                        $error = '请填写所有必填字段';
                    } else {
                        // 更新账户配置
                        $config->set("WEBDAV_{$accountKey}_NAME", $accountName);
                        $config->set("WEBDAV_{$accountKey}_HOST", $host);
                        $config->set("WEBDAV_{$accountKey}_USERNAME", $username);
                        if (!empty($password)) {
                            $config->set("WEBDAV_{$accountKey}_PASSWORD", $password);
                        }
                        $config->set("WEBDAV_{$accountKey}_PATH", $path);
                        
                        $config->saveConfig();
                        $message = '账户已更新';
                    }
                    break;
                    
                case 'delete_account':
                    $accountKey = $_POST['account_key'] ?? '';
                    if (!empty($accountKey)) {
                        // 从账户列表中移除
                        $accounts = $config->get('WEBDAV_ACCOUNTS', '');
                        $accountsList = $accounts ? explode(',', $accounts) : [];
                        $accountsList = array_filter($accountsList, function($key) use ($accountKey) {
                            return strtolower($key) !== strtolower($accountKey);
                        });
                        $config->set('WEBDAV_ACCOUNTS', implode(',', $accountsList));
                        
                        $config->saveConfig();
                        $message = '账户已删除';
                    }
                    break;
            }
        }
    } catch (Exception $e) {
        $error = '操作失败: ' . $e->getMessage();
    }
}

$accounts = $config->getAccounts();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>后台管理 - WebDAV管理系统</title>
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
            display: inline-block;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-success {
            background: #28a745;
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
        
        .card {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .card h2 {
            color: #333;
            margin-bottom: 1rem;
            border-bottom: 2px solid #eee;
            padding-bottom: 0.5rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
        }
        
        input[type="text"], 
        input[type="password"], 
        input[type="url"] {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e1e1e1;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        input:focus {
            outline: none;
            border-color: #007bff;
        }
        
        .alert {
            padding: 0.75rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        .accounts-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .accounts-table th,
        .accounts-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .accounts-table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        
        .no-accounts {
            text-align: center;
            color: #666;
            padding: 2rem;
            font-style: italic;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .accounts-table {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>后台管理</h1>
        <div class="header-actions">
            <a href="index.php" class="btn btn-secondary">返回首页</a>
            <a href="logout.php" class="btn btn-danger">退出登录</a>
        </div>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <!-- 管理员密码设置 -->
        <div class="card">
            <h2>管理员密码设置</h2>
            <form method="POST">
                <input type="hidden" name="action" value="update_password">
                <div class="form-group">
                    <label for="admin_password">新密码</label>
                    <input type="password" id="admin_password" name="admin_password" placeholder="输入新的管理员密码">
                </div>
                <button type="submit" class="btn btn-primary">更新密码</button>
            </form>
        </div>
        
        <!-- 添加WebDAV账户 -->
        <div class="card">
            <h2>添加WebDAV账户</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_account">
                <div class="form-row">
                    <div class="form-group">
                        <label for="account_key">账户标识 *</label>
                        <input type="text" id="account_key" name="account_key" placeholder="如: ACCOUNT1" required>
                    </div>
                    <div class="form-group">
                        <label for="account_name">账户名称 *</label>
                        <input type="text" id="account_name" name="account_name" placeholder="如: 我的WebDAV" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="host">服务器地址 *</label>
                        <input type="url" id="host" name="host" placeholder="https://webdav.example.com" required>
                    </div>
                    <div class="form-group">
                        <label for="username">用户名 *</label>
                        <input type="text" id="username" name="username" placeholder="WebDAV用户名" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="password">密码</label>
                        <input type="password" id="password" name="password" placeholder="WebDAV密码">
                    </div>
                    <div class="form-group">
                        <label for="path">根路径</label>
                        <input type="text" id="path" name="path" placeholder="/" value="/">
                    </div>
                </div>
                <button type="submit" class="btn btn-success">添加账户</button>
            </form>
        </div>
        
        <!-- 现有账户管理 -->
        <div class="card">
            <h2>现有WebDAV账户</h2>
            <?php if (empty($accounts)): ?>
                <div class="no-accounts">暂无WebDAV账户</div>
            <?php else: ?>
                <table class="accounts-table">
                    <thead>
                        <tr>
                            <th>账户名称</th>
                            <th>服务器地址</th>
                            <th>用户名</th>
                            <th>根路径</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($accounts as $account): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($account['name']); ?></td>
                                <td><?php echo htmlspecialchars($account['host']); ?></td>
                                <td><?php echo htmlspecialchars($account['username']); ?></td>
                                <td><?php echo htmlspecialchars($account['path']); ?></td>
                                <td>
                                    <button onclick="editAccount('<?php echo htmlspecialchars($account['key']); ?>', 
                                                                 '<?php echo htmlspecialchars($account['name']); ?>', 
                                                                 '<?php echo htmlspecialchars($account['host']); ?>', 
                                                                 '<?php echo htmlspecialchars($account['username']); ?>', 
                                                                 '<?php echo htmlspecialchars($account['path']); ?>')" 
                                            class="btn btn-primary">编辑</button>
                                    <form method="POST" style="display: inline;" 
                                          onsubmit="return confirm('确定要删除这个账户吗？');">
                                        <input type="hidden" name="action" value="delete_account">
                                        <input type="hidden" name="account_key" value="<?php echo htmlspecialchars($account['key']); ?>">
                                        <button type="submit" class="btn btn-danger">删除</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- 编辑账户模态框 -->
    <div id="editModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>编辑账户</h3>
                <span class="close" onclick="closeEditModal()">&times;</span>
            </div>
            
            <form method="POST" id="editForm">
                <input type="hidden" name="action" value="edit_account">
                <input type="hidden" name="account_key" id="edit_account_key">
                
                <div class="form-group">
                    <label for="edit_account_name">账户名称</label>
                    <input type="text" id="edit_account_name" name="account_name" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_host">服务器地址</label>
                    <input type="url" id="edit_host" name="host" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_username">用户名</label>
                    <input type="text" id="edit_username" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_password">密码（留空则不修改）</label>
                    <input type="password" id="edit_password" name="password">
                </div>
                
                <div class="form-group">
                    <label for="edit_path">根路径</label>
                    <input type="text" id="edit_path" name="path" value="/" required>
                </div>
                
                <div class="form-actions">
                    <button type="button" onclick="closeEditModal()" class="btn btn-secondary">取消</button>
                    <button type="submit" class="btn btn-primary">保存</button>
                </div>
            </form>
        </div>
    </div>

    <style>
        .modal {
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
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 1rem;
        }

        .modal-header h3 {
            color: #2d3748;
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
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

        .form-actions {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid #e9ecef;
        }
    </style>

    <script>
        function editAccount(key, name, host, username, path) {
            document.getElementById('edit_account_key').value = key;
            document.getElementById('edit_account_name').value = name;
            document.getElementById('edit_host').value = host;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_path').value = path;
            document.getElementById('edit_password').value = ''; // 清空密码字段
            
            document.getElementById('editModal').style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        // 点击模态框外部关闭
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target === modal) {
                closeEditModal();
            }
        }

        // ESC键关闭
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeEditModal();
            }
        });
    </script>
</body>
</html>
