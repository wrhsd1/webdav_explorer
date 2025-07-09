<?php
require_once 'includes/auth.php';

$error = '';
$expired = false;

// 检查是否会话过期
if (isset($_GET['expired'])) {
    $expired = true;
    $error = '会话已过期，请重新登录';
}

// 处理登录
if ($_POST) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = '请输入用户名和密码';
    } else {
        if (Auth::login($username, $password)) {
            header('Location: index.php');
            exit;
        } else {
            $error = '用户名或密码错误';
        }
    }
}

// 如果已经登录，重定向到主页
if (Auth::isLoggedIn()) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 - WebDAV管理系统</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            background: white;
            border-radius: 15px;
            padding: 2.5rem;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
            backdrop-filter: blur(10px);
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .login-header h1 {
            color: #333;
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }
        
        .login-header p {
            color: #666;
            font-size: 0.9rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
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
            padding: 0.875rem;
            border: 2px solid #e1e1e1;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #fafafa;
        }
        
        input:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn {
            width: 100%;
            padding: 0.875rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 1rem;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .alert {
            padding: 0.875rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }
        
        .alert-danger {
            background: #fef2f2;
            color: #dc2626;
            border-left: 4px solid #dc2626;
        }
        
        .alert-warning {
            background: #fffbeb;
            color: #d97706;
            border-left: 4px solid #d97706;
        }
        
        .login-footer {
            text-align: center;
            margin-top: 2rem;
            color: #666;
            font-size: 0.8rem;
        }
        
        .input-group {
            position: relative;
        }
        
        .input-icon {
            position: absolute;
            left: 0.875rem;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            font-size: 1.1rem;
        }
        
        .input-group input {
            padding-left: 2.5rem;
        }
        
        @media (max-width: 480px) {
            .login-container {
                margin: 1rem;
                padding: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>WebDAV管理系统</h1>
            <p>请使用您的账户登录</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert <?php echo $expired ? 'alert-warning' : 'alert-danger'; ?>">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="username">用户名</label>
                <div class="input-group">
                    <span class="input-icon">👤</span>
                    <input type="text" id="username" name="username" 
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
                           placeholder="请输入用户名" required autofocus>
                </div>
            </div>
            
            <div class="form-group">
                <label for="password">密码</label>
                <div class="input-group">
                    <span class="input-icon">🔒</span>
                    <input type="password" id="password" name="password" 
                           placeholder="请输入密码" required>
                </div>
            </div>
            
            <button type="submit" class="btn">登录</button>
        </form>
        
        <div class="login-footer">
            <p>© 2025 WebDAV管理系统</p>
        </div>
    </div>
    
    <script>
        // 回车键提交表单
        document.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.querySelector('form').submit();
            }
        });
        
        // 清除错误消息
        document.querySelectorAll('input').forEach(input => {
            input.addEventListener('input', function() {
                const alert = document.querySelector('.alert');
                if (alert && !alert.classList.contains('alert-warning')) {
                    alert.style.opacity = '0.5';
                }
            });
        });
    </script>
</body>
</html>
