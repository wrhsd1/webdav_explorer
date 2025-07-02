<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/webdav.php';

Auth::requireLogin();

$config = Config::getInstance();
$accounts = $config->getAccounts();

// 获取参数
$accountKey = $_GET['account'] ?? '';
$filePath = $_GET['path'] ?? '';

if (empty($accountKey) || empty($filePath) || !isset($accounts[$accountKey])) {
    http_response_code(404);
    exit('文件不存在');
}

$account = $accounts[$accountKey];

try {
    $webdav = new WebDAVClient(
        $account['host'],
        $account['username'],
        $account['password'],
        $account['path']
    );
    
    // 生成中转链接
    $transferUrl = $config->get('DIRECT_LINK_DOMAIN', 'http://localhost') . 
                   '/transfer.php?account=' . urlencode($accountKey) . 
                   '&path=' . urlencode($filePath);
    
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>文件直链 - <?php echo htmlspecialchars($fileName); ?></title>
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
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 2rem;
            }
            
            .container {
                background: white;
                border-radius: 10px;
                padding: 2rem;
                box-shadow: 0 10px 25px rgba(0,0,0,0.1);
                max-width: 600px;
                width: 100%;
            }
            
            .header {
                text-align: center;
                margin-bottom: 2rem;
            }
            
            .header h1 {
                color: #333;
                margin-bottom: 0.5rem;
            }
            
            .file-name {
                color: #666;
                word-break: break-all;
            }
            
            .link-section {
                margin-bottom: 2rem;
            }
            
            .link-section h3 {
                color: #333;
                margin-bottom: 1rem;
            }
            
            .link-input {
                display: flex;
                gap: 0.5rem;
                margin-bottom: 1rem;
            }
            
            .link-input input {
                flex: 1;
                padding: 0.75rem;
                border: 2px solid #e1e1e1;
                border-radius: 5px;
                font-size: 1rem;
                background: #f8f9fa;
            }
            
            .btn {
                padding: 0.75rem 1rem;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                text-decoration: none;
                font-size: 1rem;
                transition: all 0.2s;
                display: inline-block;
                text-align: center;
            }
            
            .btn-primary {
                background: #007bff;
                color: white;
            }
            
            .btn-success {
                background: #28a745;
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
            
            .actions {
                display: flex;
                gap: 1rem;
                justify-content: center;
                margin-top: 2rem;
            }
            
            .note {
                background: #e3f2fd;
                color: #1565c0;
                padding: 1rem;
                border-radius: 5px;
                margin-top: 1rem;
                font-size: 0.9rem;
            }
            
            @media (max-width: 768px) {
                .actions {
                    flex-direction: column;
                }
                
                .link-input {
                    flex-direction: column;
                }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>文件直链</h1>
                <div class="file-name"><?php echo htmlspecialchars($fileName); ?></div>
            </div>
            
            <div class="link-section">
                <h3>下载链接</h3>
                <div class="link-input">
                    <input type="text" id="transferUrl" value="<?php echo htmlspecialchars($transferUrl); ?>" readonly>
                    <button onclick="copyToClipboard('transferUrl')" class="btn btn-primary">复制</button>
                </div>
                <div class="note">
                    通过本站中转的下载链接，兼容性好，支持在线预览和下载。
                </div>
            </div>
            
            <div class="actions">
                <a href="<?php echo htmlspecialchars($transferUrl); ?>" class="btn btn-success">预览/下载</a>
                <a href="filemanager.php?account=<?php echo urlencode($accountKey); ?>&path=<?php echo urlencode(dirname($filePath)); ?>" 
                   class="btn btn-secondary">返回文件管理器</a>
            </div>
        </div>

        <script>
            function copyToClipboard(elementId) {
                const input = document.getElementById(elementId);
                input.select();
                input.setSelectionRange(0, 99999);
                document.execCommand('copy');
                
                const btn = input.nextElementSibling;
                const originalText = btn.textContent;
                btn.textContent = '已复制!';
                btn.style.background = '#28a745';
                
                setTimeout(() => {
                    btn.textContent = originalText;
                    btn.style.background = '';
                }, 2000);
            }
        </script>
    </body>
    </html>
    <?php
    
} catch (Exception $e) {
    http_response_code(500);
    echo '生成直链失败: ' . $e->getMessage();
}
?>
