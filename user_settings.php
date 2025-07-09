<?php
require_once 'includes/auth.php';
require_once 'includes/user.php';

Auth::requireLogin();

$userManager = new User();
$currentUserId = Auth::getCurrentUserId();
$currentUser = Auth::getCurrentUser();
$message = '';
$error = '';

// 处理表单提交
if ($_POST) {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'change_password':
                    $currentPassword = $_POST['current_password'] ?? '';
                    $newPassword = $_POST['new_password'] ?? '';
                    $confirmPassword = $_POST['confirm_password'] ?? '';
                    
                    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                        $error = '请填写所有密码字段';
                    } elseif ($newPassword !== $confirmPassword) {
                        $error = '新密码确认不匹配';
                    } elseif (strlen($newPassword) < 6) {
                        $error = '新密码长度至少6位';
                    } else {
                        // 验证当前密码
                        if (!Auth::login(Auth::getCurrentUsername(), $currentPassword)) {
                            $error = '当前密码错误';
                        } else {
                            // 重新登录以保持会话
                            Auth::login(Auth::getCurrentUsername(), $currentPassword);
                            
                            if ($userManager->updatePassword($currentUserId, $newPassword)) {
                                $message = '密码修改成功';
                            } else {
                                $error = '密码修改失败';
                            }
                        }
                    }
                    break;
                    
                case 'add_webdav':
                    $accountKey = trim($_POST['account_key'] ?? '');
                    $accountName = trim($_POST['account_name'] ?? '');
                    $host = trim($_POST['host'] ?? '');
                    $username = trim($_POST['username'] ?? '');
                    $password = $_POST['password'] ?? '';
                    $path = trim($_POST['path'] ?? '/');
                    
                    if (empty($accountKey) || empty($accountName) || empty($host) || empty($username)) {
                        $error = '请填写所有必填字段';
                    } else {
                        $userManager->addWebdavConfig($currentUserId, $accountKey, $accountName, $host, $username, $password, $path);
                        $message = 'WebDAV账户添加成功';
                    }
                    break;
                    
                case 'edit_webdav':
                    $configId = $_POST['config_id'] ?? '';
                    $accountName = trim($_POST['account_name'] ?? '');
                    $host = trim($_POST['host'] ?? '');
                    $username = trim($_POST['username'] ?? '');
                    $password = $_POST['password'] ?? '';
                    $path = trim($_POST['path'] ?? '/');
                    
                    if (empty($configId) || empty($accountName) || empty($host) || empty($username)) {
                        $error = '请填写所有必填字段';
                    } else {
                        if ($userManager->updateWebdavConfig($currentUserId, $configId, $accountName, $host, $username, $password, $path)) {
                            $message = 'WebDAV账户更新成功';
                        } else {
                            $error = 'WebDAV账户更新失败';
                        }
                    }
                    break;
                    
                case 'delete_webdav':
                    $configId = $_POST['config_id'] ?? '';
                    if (!empty($configId)) {
                        if ($userManager->deleteWebdavConfig($currentUserId, $configId)) {
                            $message = 'WebDAV账户删除成功';
                        } else {
                            $error = 'WebDAV账户删除失败';
                        }
                    }
                    break;
            }
        }
    } catch (Exception $e) {
        $error = '操作失败: ' . $e->getMessage();
    }
}

// 获取用户的WebDAV配置
$webdavConfigs = $userManager->getUserWebdavConfigs($currentUserId);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>个人设置 - WebDAV管理系统</title>
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
        
        .config-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .config-table th,
        .config-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .config-table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        
        .no-configs {
            text-align: center;
            color: #666;
            padding: 2rem;
            font-style: italic;
        }
        
        .user-info-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .user-info-card h2 {
            border-bottom: 2px solid rgba(255,255,255,0.3);
            color: white;
        }
        
        .user-stat {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        
        /* 模态框样式 */
        .modal {
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
            display: none;
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

        .form-actions {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid #e9ecef;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .config-table {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>个人设置</h1>
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
        
        <!-- 用户信息 -->
        <div class="user-info-card">
            <h2>用户信息</h2>
            <div class="user-stat">
                <span>用户名:</span>
                <span><?php echo htmlspecialchars($currentUser['username']); ?></span>
            </div>
            <div class="user-stat">
                <span>账户类型:</span>
                <span><?php echo $currentUser['is_admin'] ? '管理员' : '普通用户'; ?></span>
            </div>
            <div class="user-stat">
                <span>注册时间:</span>
                <span><?php echo date('Y-m-d H:i', strtotime($currentUser['created_at'])); ?></span>
            </div>
            <div class="user-stat">
                <span>WebDAV账户数量:</span>
                <span><?php echo count($webdavConfigs); ?> 个</span>
            </div>
        </div>
        
        <!-- 修改密码 -->
        <div class="card">
            <h2>修改密码</h2>
            <form method="POST">
                <input type="hidden" name="action" value="change_password">
                <div class="form-group">
                    <label for="current_password">当前密码</label>
                    <input type="password" id="current_password" name="current_password" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="new_password">新密码</label>
                        <input type="password" id="new_password" name="new_password" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">确认新密码</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">修改密码</button>
            </form>
        </div>
        
        <!-- 添加WebDAV账户 -->
        <div class="card">
            <h2>添加WebDAV账户</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_webdav">
                <div class="form-row">
                    <div class="form-group">
                        <label for="account_key">账户标识 *</label>
                        <input type="text" id="account_key" name="account_key" placeholder="如: account1" required>
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
        
        <!-- WebDAV账户管理 -->
        <div class="card">
            <h2>我的WebDAV账户</h2>
            <?php if (empty($webdavConfigs)): ?>
                <div class="no-configs">暂无WebDAV账户配置</div>
            <?php else: ?>
                <table class="config-table">
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
                        <?php foreach ($webdavConfigs as $config): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($config['account_name']); ?></td>
                                <td><?php echo htmlspecialchars($config['host']); ?></td>
                                <td><?php echo htmlspecialchars($config['username']); ?></td>
                                <td><?php echo htmlspecialchars($config['path']); ?></td>
                                <td>
                                    <button onclick="editWebdavConfig(<?php echo $config['id']; ?>, 
                                                                     '<?php echo htmlspecialchars($config['account_name']); ?>', 
                                                                     '<?php echo htmlspecialchars($config['host']); ?>', 
                                                                     '<?php echo htmlspecialchars($config['username']); ?>', 
                                                                     '<?php echo htmlspecialchars($config['path']); ?>')" 
                                            class="btn btn-primary">编辑</button>
                                    <form method="POST" style="display: inline;" 
                                          onsubmit="return confirm('确定要删除这个账户吗？');">
                                        <input type="hidden" name="action" value="delete_webdav">
                                        <input type="hidden" name="config_id" value="<?php echo $config['id']; ?>">
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

    <!-- 编辑WebDAV配置模态框 -->
    <div id="editWebdavModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>编辑WebDAV账户</h3>
                <span class="close" onclick="closeEditWebdavModal()">&times;</span>
            </div>
            
            <form method="POST" id="editWebdavForm">
                <input type="hidden" name="action" value="edit_webdav">
                <input type="hidden" name="config_id" id="edit_config_id">
                
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
                    <button type="button" onclick="closeEditWebdavModal()" class="btn btn-secondary">取消</button>
                    <button type="submit" class="btn btn-primary">保存</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function editWebdavConfig(configId, accountName, host, username, path) {
            document.getElementById('edit_config_id').value = configId;
            document.getElementById('edit_account_name').value = accountName;
            document.getElementById('edit_host').value = host;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_path').value = path;
            document.getElementById('edit_password').value = '';
            
            document.getElementById('editWebdavModal').style.display = 'block';
        }

        function closeEditWebdavModal() {
            document.getElementById('editWebdavModal').style.display = 'none';
        }

        // 点击模态框外部关闭
        window.onclick = function(event) {
            const modal = document.getElementById('editWebdavModal');
            if (event.target === modal) {
                closeEditWebdavModal();
            }
        }

        // ESC键关闭
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeEditWebdavModal();
            }
        });
        
        // 密码确认验证
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (newPassword !== confirmPassword) {
                this.setCustomValidity('密码确认不匹配');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>
