<?php
require_once 'includes/auth.php';
require_once 'includes/user.php';

Auth::requireAdmin();

$userManager = new User();
$message = '';
$error = '';

// 处理表单提交
if ($_POST) {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'create_user':
                    $username = trim($_POST['username'] ?? '');
                    $password = $_POST['password'] ?? '';
                    $isAdmin = isset($_POST['is_admin']) && $_POST['is_admin'] == '1';
                    
                    if (empty($username) || empty($password)) {
                        $error = '请填写用户名和密码';
                    } elseif (strlen($password) < 6) {
                        $error = '密码长度至少6位';
                    } elseif ($userManager->usernameExists($username)) {
                        $error = '用户名已存在';
                    } else {
                        $userId = $userManager->createUser($username, $password, $isAdmin);
                        $message = "用户 '{$username}' 创建成功";
                    }
                    break;
                    
                case 'reset_password':
                    $userId = $_POST['user_id'] ?? '';
                    $newPassword = $_POST['new_password'] ?? '';
                    
                    if (empty($userId) || empty($newPassword)) {
                        $error = '请填写所有字段';
                    } elseif (strlen($newPassword) < 6) {
                        $error = '密码长度至少6位';
                    } else {
                        if ($userManager->updatePassword($userId, $newPassword)) {
                            $message = '密码重置成功';
                        } else {
                            $error = '密码重置失败';
                        }
                    }
                    break;
                    
                case 'delete_user':
                    $userId = $_POST['user_id'] ?? '';
                    if (!empty($userId)) {
                        if ($userManager->deleteUser($userId)) {
                            $message = '用户删除成功';
                        } else {
                            $error = '用户删除失败（不能删除管理员账户）';
                        }
                    }
                    break;
            }
        }
    } catch (Exception $e) {
        $error = '操作失败: ' . $e->getMessage();
    }
}

// 获取所有用户
$users = $userManager->getAllUsers();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户管理 - WebDAV管理系统</title>
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
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .admin-badge {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.7rem;
            font-weight: 600;
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
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 10px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
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
        input[type="password"] {
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
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.5rem;
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
        
        .users-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .users-table th,
        .users-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .users-table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        
        .user-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .badge-admin {
            background: #ffeaa7;
            color: #fdcb6e;
        }
        
        .badge-user {
            background: #dfe6e9;
            color: #636e72;
        }
        
        .no-users {
            text-align: center;
            color: #666;
            padding: 2rem;
            font-style: italic;
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
            margin: 10% auto;
            padding: 2rem;
            border-radius: 8px;
            width: 90%;
            max-width: 400px;
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
            
            .users-table {
                font-size: 0.9rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>
            👥 用户管理
            <span class="admin-badge">管理员面板</span>
        </h1>
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
        
        <!-- 统计信息 -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($users); ?></div>
                <div class="stat-label">总用户数</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count(array_filter($users, function($u) { return $u['is_admin']; })); ?></div>
                <div class="stat-label">管理员数量</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count(array_filter($users, function($u) { return !$u['is_admin']; })); ?></div>
                <div class="stat-label">普通用户数量</div>
            </div>
        </div>
        
        <!-- 创建用户 -->
        <div class="card">
            <h2>创建新用户</h2>
            <form method="POST">
                <input type="hidden" name="action" value="create_user">
                <div class="form-row">
                    <div class="form-group">
                        <label for="username">用户名 *</label>
                        <input type="text" id="username" name="username" placeholder="请输入用户名" required>
                    </div>
                    <div class="form-group">
                        <label for="password">密码 *</label>
                        <input type="password" id="password" name="password" placeholder="请输入密码（至少6位）" required>
                    </div>
                </div>
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="is_admin" name="is_admin" value="1">
                        <label for="is_admin">设为管理员</label>
                    </div>
                </div>
                <button type="submit" class="btn btn-success">创建用户</button>
            </form>
        </div>
        
        <!-- 用户列表 -->
        <div class="card">
            <h2>用户列表</h2>
            <?php if (empty($users)): ?>
                <div class="no-users">暂无用户</div>
            <?php else: ?>
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>用户名</th>
                            <th>类型</th>
                            <th>注册时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td>
                                    <span class="user-badge <?php echo $user['is_admin'] ? 'badge-admin' : 'badge-user'; ?>">
                                        <?php echo $user['is_admin'] ? '管理员' : '普通用户'; ?>
                                    </span>
                                </td>
                                <td><?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <button onclick="resetPassword(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')" 
                                            class="btn btn-warning">重置密码</button>
                                    <?php if (!$user['is_admin']): ?>
                                        <form method="POST" style="display: inline;" 
                                              onsubmit="return confirm('确定要删除用户 <?php echo htmlspecialchars($user['username']); ?> 吗？这将删除该用户的所有数据！');">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" class="btn btn-danger">删除</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- 重置密码模态框 -->
    <div id="resetPasswordModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>重置用户密码</h3>
                <span class="close" onclick="closeResetPasswordModal()">&times;</span>
            </div>
            
            <form method="POST" id="resetPasswordForm">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" id="reset_user_id">
                
                <div class="form-group">
                    <label for="reset_username">用户名</label>
                    <input type="text" id="reset_username" readonly style="background: #f8f9fa;">
                </div>
                
                <div class="form-group">
                    <label for="new_password">新密码</label>
                    <input type="password" id="new_password" name="new_password" placeholder="请输入新密码（至少6位）" required>
                </div>
                
                <div class="form-actions">
                    <button type="button" onclick="closeResetPasswordModal()" class="btn btn-secondary">取消</button>
                    <button type="submit" class="btn btn-warning">重置密码</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function resetPassword(userId, username) {
            document.getElementById('reset_user_id').value = userId;
            document.getElementById('reset_username').value = username;
            document.getElementById('new_password').value = '';
            
            document.getElementById('resetPasswordModal').style.display = 'block';
        }

        function closeResetPasswordModal() {
            document.getElementById('resetPasswordModal').style.display = 'none';
        }

        // 点击模态框外部关闭
        window.onclick = function(event) {
            const modal = document.getElementById('resetPasswordModal');
            if (event.target === modal) {
                closeResetPasswordModal();
            }
        }

        // ESC键关闭
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeResetPasswordModal();
            }
        });
        
        // 自动生成强密码功能
        function generatePassword() {
            const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            let password = '';
            for (let i = 0; i < 8; i++) {
                password += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            return password;
        }
        
        // 为密码字段添加生成按钮
        document.querySelectorAll('input[type="password"]').forEach(input => {
            if (input.id === 'new_password' || input.id === 'password') {
                const generateBtn = document.createElement('button');
                generateBtn.type = 'button';
                generateBtn.textContent = '生成';
                generateBtn.className = 'btn btn-secondary';
                generateBtn.style.marginLeft = '0.5rem';
                generateBtn.style.padding = '0.5rem';
                generateBtn.onclick = function() {
                    input.value = generatePassword();
                };
                input.parentNode.appendChild(generateBtn);
            }
        });
    </script>
</body>
</html>
