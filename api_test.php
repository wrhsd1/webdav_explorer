<?php
require_once 'includes/auth.php';

Auth::requireLogin();

$currentUser = Auth::getCurrentUser();
$currentApiKey = $currentUser['username'] . '_' . $currentUser['api_key'];

// 获取用户的WebDAV配置
require_once 'includes/user.php';
$userManager = new User();
$webdavConfigs = $userManager->getUserWebdavConfigs(Auth::getCurrentUserId());
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API测试 - WebDAV管理系统</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f7fa;
            line-height: 1.6;
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
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-1px);
            opacity: 0.9;
        }
        
        .container {
            max-width: 1000px;
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
        input[type="file"], 
        select,
        textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e1e1e1;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: #007bff;
        }
        
        .response-area {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
            font-family: monospace;
            font-size: 0.9rem;
            white-space: pre-wrap;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .tabs {
            display: flex;
            margin-bottom: 1rem;
            border-bottom: 2px solid #eee;
        }
        
        .tab {
            padding: 1rem 2rem;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.2s;
        }
        
        .tab.active {
            color: #007bff;
            border-bottom-color: #007bff;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .api-info {
            background: #e3f2fd;
            border: 1px solid #2196f3;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .loading {
            display: none;
            color: #007bff;
            font-style: italic;
        }
        
        .file-list {
            font-family: monospace;
            font-size: 0.9rem;
        }
        
        .file-item {
            display: flex;
            align-items: center;
            padding: 0.5rem;
            border-bottom: 1px solid #e9ecef;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .file-item:hover {
            background-color: #f1f3f4;
        }
        
        .file-item:last-child {
            border-bottom: none;
        }
        
        .file-icon {
            margin-right: 0.75rem;
            font-size: 1.2rem;
            width: 1.5rem;
            text-align: center;
        }
        
        .file-name {
            flex: 1;
            font-weight: 500;
        }
        
        .file-info {
            color: #666;
            font-size: 0.8rem;
            margin-left: 1rem;
        }
        
        .breadcrumb {
            background: #e9ecef;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            font-family: monospace;
        }
        
        .breadcrumb a {
            color: #007bff;
            text-decoration: none;
            cursor: pointer;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>API测试工具</h1>
        <div>
            <a href="api_docs.php" class="btn btn-secondary">API文档</a>
            <a href="index.php" class="btn btn-primary">返回首页</a>
        </div>
    </div>
    
    <div class="container">
        <div class="api-info">
            <h4 style="color: #1976d2; margin-bottom: 0.5rem;">🔑 当前API密钥</h4>
            <p style="font-family: monospace; color: #424242; margin: 0;"><?php echo htmlspecialchars($currentApiKey); ?></p>
        </div>
        
        <div class="card">
            <h2>API测试</h2>
            
            <div class="tabs">
                <div class="tab active" onclick="switchTab('list')">📥 列表浏览</div>
                <div class="tab" onclick="switchTab('mkdir')">📁 新建文件夹</div>
                <div class="tab" onclick="switchTab('upload')">📤 上传文件</div>
            </div>
            
            <!-- 列表浏览 -->
            <div id="list" class="tab-content active">
                <form id="listForm" onsubmit="testList(event)">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="list_account">WebDAV账户</label>
                            <select id="list_account" name="webdav_account" required>
                                <option value="">选择账户</option>
                                <?php foreach ($webdavConfigs as $config): ?>
                                    <option value="<?php echo htmlspecialchars($config['account_key']); ?>">
                                        <?php echo htmlspecialchars($config['account_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="list_path">目录路径</label>
                            <input type="text" id="list_path" name="file_path" placeholder="/" value="/">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-success">🔍 浏览目录</button>
                    <button type="button" class="btn btn-secondary" onclick="refreshCurrentDirectory()">🔄 刷新</button>
                    <span class="loading" id="list_loading">加载中...</span>
                </form>
                
                <div class="response-area" id="list_response">点击"浏览目录"查看文件列表</div>
                
                <!-- 文件列表展示 -->
                <div id="file_list_container" style="display: none; margin-top: 1rem;">
                    <h4 style="color: #333; margin-bottom: 1rem;">📂 目录内容</h4>
                    <div class="file-list" id="file_list" style="background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px; padding: 1rem;">
                        <!-- 文件列表将在这里动态生成 -->
                    </div>
                </div>
            </div>
            
            <!-- 新建文件夹 -->
            <div id="mkdir" class="tab-content">
                <form id="mkdirForm" onsubmit="testMkdir(event)">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="mkdir_account">WebDAV账户</label>
                            <select id="mkdir_account" name="webdav_account" required>
                                <option value="">选择账户</option>
                                <?php foreach ($webdavConfigs as $config): ?>
                                    <option value="<?php echo htmlspecialchars($config['account_key']); ?>">
                                        <?php echo htmlspecialchars($config['account_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="mkdir_path">父目录路径</label>
                            <input type="text" id="mkdir_path" name="dir_path" placeholder="/" value="/">
                            <small style="color: #666; font-size: 0.8rem; margin-top: 0.25rem; display: block;">
                                💡 在此目录下创建新文件夹
                            </small>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="mkdir_name">文件夹名称</label>
                            <input type="text" id="mkdir_name" name="dir_name" placeholder="新文件夹" required>
                            <small style="color: #666; font-size: 0.8rem; margin-top: 0.25rem; display: block;">
                                ⚠️ 不能包含字符：< > : " | ? *
                            </small>
                        </div>
                        <div class="form-group">
                            <label for="mkdir_recursive">创建方式</label>
                            <select id="mkdir_recursive" name="recursive">
                                <option value="true">递归创建（自动创建父目录）</option>
                                <option value="false">仅创建指定目录</option>
                            </select>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-success">📁 创建文件夹</button>
                    <button type="button" class="btn btn-secondary" onclick="fillCurrentPath('mkdir')">📍 使用当前路径</button>
                    <span class="loading" id="mkdir_loading">创建中...</span>
                </form>
                
                <div class="response-area" id="mkdir_response">点击"创建文件夹"查看响应结果</div>
            </div>
            <!-- 上传文件 -->
            <div id="upload" class="tab-content">
                <form id="uploadForm" onsubmit="testUpload(event)">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="upload_account">WebDAV账户</label>
                            <select id="upload_account" name="webdav_account" required>
                                <option value="">选择账户</option>
                                <?php foreach ($webdavConfigs as $config): ?>
                                    <option value="<?php echo htmlspecialchars($config['account_key']); ?>">
                                        <?php echo htmlspecialchars($config['account_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="upload_path">目标路径</label>
                            <input type="text" id="upload_path" name="file_path" placeholder="/uploads/" value="/uploads/">
                            <small style="color: #666; font-size: 0.8rem; margin-top: 0.25rem; display: block;">
                                💡 路径说明：<br>
                                • 以 / 结尾：作为目录，如 /test/ → 上传到 test/文件名<br>
                                • 不以 / 结尾且无扩展名：作为目录，如 /test → 上传到 test/文件名<br>
                                • 不以 / 结尾且有扩展名：作为完整文件路径，如 /test/file.pdf
                            </small>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>上传方式</label>
                        <div style="display: flex; gap: 1rem; margin-bottom: 1rem;">
                            <label style="display: flex; align-items: center; gap: 0.5rem;">
                                <input type="radio" name="upload_type" value="file" checked onchange="toggleUploadType()">
                                文件上传
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.5rem;">
                                <input type="radio" name="upload_type" value="url" onchange="toggleUploadType()">
                                URL下载
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group" id="file_upload_group">
                        <label for="upload_file">选择文件</label>
                        <input type="file" id="upload_file" name="file">
                    </div>
                    
                    <div class="form-group" id="url_upload_group" style="display: none;">
                        <label for="upload_url">文件URL</label>
                        <input type="text" id="upload_url" name="file_url" placeholder="https://example.com/file.jpg">
                    </div>
                    
                    <button type="submit" class="btn btn-success">📤 上传文件</button>
                    <button type="button" class="btn btn-secondary" onclick="fillCurrentPath('upload')">📍 使用当前路径</button>
                    <span class="loading" id="upload_loading">上传中...</span>
                </form>
                
                <div class="response-area" id="upload_response">点击"上传文件"查看响应结果</div>
            </div>
        </div>
    </div>

    <script>
        const API_KEY = '<?php echo htmlspecialchars($currentApiKey); ?>';
        let currentPath = '/';
        let currentAccount = '';
        
        function switchTab(tabName) {
            // 移除所有活动状态
            document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            // 激活当前标签
            event.target.classList.add('active');
            document.getElementById(tabName).classList.add('active');
        }
        
        function toggleUploadType() {
            const fileGroup = document.getElementById('file_upload_group');
            const urlGroup = document.getElementById('url_upload_group');
            const uploadType = document.querySelector('input[name="upload_type"]:checked').value;
            
            if (uploadType === 'file') {
                fileGroup.style.display = 'block';
                urlGroup.style.display = 'none';
            } else {
                fileGroup.style.display = 'none';
                urlGroup.style.display = 'block';
            }
        }
        
        function fillCurrentPath(target) {
            if (!currentPath || !currentAccount) {
                alert('请先使用📥 列表浏览功能，浏览一个目录以获取当前路径');
                return;
            }
            
            if (target === 'mkdir') {
                document.getElementById('mkdir_path').value = currentPath;
                document.getElementById('mkdir_account').value = currentAccount;
            } else if (target === 'upload') {
                document.getElementById('upload_path').value = currentPath;
                document.getElementById('upload_account').value = currentAccount;
            }
        }
        
        function refreshCurrentDirectory() {
            if (!currentAccount || !currentPath) {
                alert('请先选择账户和路径');
                return;
            }
            
            document.getElementById('list_account').value = currentAccount;
            document.getElementById('list_path').value = currentPath;
            document.getElementById('listForm').dispatchEvent(new Event('submit'));
        }
        
        function navigateToPath(path) {
            document.getElementById('list_path').value = path;
            document.getElementById('listForm').dispatchEvent(new Event('submit'));
        }
        
        function getFileIcon(item) {
            if (item.is_directory) {
                return '📁';
            }
            
            const type = item.file_type || 'file';
            const iconMap = {
                'image': '🖼️',
                'video': '🎬',
                'audio': '🎵',
                'document': '📄',
                'text': '📝',
                'code': '💻',
                'archive': '🗜️'
            };
            
            return iconMap[type] || '📄';
        }
        
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
        }
        
        function displayFileList(data) {
            const container = document.getElementById('file_list_container');
            const fileList = document.getElementById('file_list');
            
            if (!data.success || !data.data.items) {
                container.style.display = 'none';
                return;
            }
            
            const items = data.data.items;
            const path = data.data.path;
            
            // 更新当前路径和账户
            currentPath = path;
            currentAccount = document.getElementById('list_account').value;
            
            // 生成面包屑导航
            let breadcrumb = '<div class="breadcrumb">';
            breadcrumb += '<span>📍 当前位置: </span>';
            
            const pathParts = path.split('/').filter(part => part !== '');
            let currentBreadcrumbPath = '/';
            
            breadcrumb += `<a onclick="navigateToPath('/')">/</a>`;
            
            for (let i = 0; i < pathParts.length; i++) {
                currentBreadcrumbPath += pathParts[i] + '/';
                breadcrumb += ` / <a onclick="navigateToPath('${currentBreadcrumbPath}')">${pathParts[i]}</a>`;
            }
            
            breadcrumb += '</div>';
            
            // 生成文件列表
            let html = breadcrumb;
            
            if (items.length === 0) {
                html += '<div style="text-align: center; color: #666; padding: 2rem;">📭 此目录为空</div>';
            } else {
                // 先显示目录，后显示文件
                const directories = items.filter(item => item.is_directory);
                const files = items.filter(item => !item.is_directory);
                
                [...directories, ...files].forEach(item => {
                    const icon = getFileIcon(item);
                    const name = item.name;
                    const size = item.is_directory ? '' : formatFileSize(item.size);
                    const modified = item.modified ? new Date(item.modified).toLocaleString() : '';
                    
                    html += `
                        <div class="file-item" ${item.is_directory ? `onclick="navigateToPath('${item.path}')"` : ''}>
                            <span class="file-icon">${icon}</span>
                            <span class="file-name">${name}</span>
                            <span class="file-info">
                                ${size}
                                ${modified ? `• ${modified}` : ''}
                            </span>
                        </div>
                    `;
                });
            }
            
            html += `
                <div style="margin-top: 1rem; padding: 0.5rem; background: #e9ecef; border-radius: 5px; font-size: 0.9rem; color: #666;">
                    📊 统计: ${data.data.directory_count} 个文件夹, ${data.data.file_count} 个文件
                </div>
            `;
            
            fileList.innerHTML = html;
            container.style.display = 'block';
        }
        
        async function testList(event) {
            event.preventDefault();
            
            const loading = document.getElementById('list_loading');
            const response = document.getElementById('list_response');
            const form = document.getElementById('listForm');
            
            loading.style.display = 'inline';
            response.textContent = '正在加载...';
            
            try {
                const params = new URLSearchParams({
                    apikey: API_KEY,
                    webdav_account: form.webdav_account.value,
                    file_path: form.file_path.value
                });
                
                const res = await fetch('/api.php?' + params.toString());
                const data = await res.json();
                response.textContent = JSON.stringify(data, null, 2);
                
                if (data.success) {
                    response.style.background = '#d4edda';
                    response.style.color = '#155724';
                    displayFileList(data);
                } else {
                    response.style.background = '#f8d7da';
                    response.style.color = '#721c24';
                    document.getElementById('file_list_container').style.display = 'none';
                }
                
            } catch (error) {
                response.textContent = '错误: ' + error.message;
                response.style.background = '#f8d7da';
                response.style.color = '#721c24';
                document.getElementById('file_list_container').style.display = 'none';
            } finally {
                loading.style.display = 'none';
            }
        }
        
        async function testMkdir(event) {
            event.preventDefault();
            
            const loading = document.getElementById('mkdir_loading');
            const response = document.getElementById('mkdir_response');
            const form = document.getElementById('mkdirForm');
            
            loading.style.display = 'inline';
            response.textContent = '正在创建...';
            
            try {
                const formData = new FormData();
                formData.append('apikey', API_KEY);
                formData.append('webdav_account', form.webdav_account.value);
                formData.append('dir_path', form.dir_path.value);
                formData.append('dir_name', form.dir_name.value);
                formData.append('recursive', form.recursive.value);
                
                const res = await fetch('/mkdir_api.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await res.json();
                response.textContent = JSON.stringify(data, null, 2);
                
                if (data.success) {
                    response.style.background = '#d4edda';
                    response.style.color = '#155724';
                    
                    // 如果创建成功，自动刷新当前目录（如果在列表页面）
                    if (currentAccount === form.webdav_account.value && 
                        currentPath === form.dir_path.value) {
                        setTimeout(() => {
                            refreshCurrentDirectory();
                        }, 1000);
                    }
                    
                    // 清空文件夹名称输入框
                    form.dir_name.value = '';
                } else {
                    response.style.background = '#f8d7da';
                    response.style.color = '#721c24';
                }
                
            } catch (error) {
                response.textContent = '错误: ' + error.message;
                response.style.background = '#f8d7da';
                response.style.color = '#721c24';
            } finally {
                loading.style.display = 'none';
            }
        }
        
        async function testUpload(event) {
            event.preventDefault();
            
            const loading = document.getElementById('upload_loading');
            const response = document.getElementById('upload_response');
            const form = document.getElementById('uploadForm');
            
            loading.style.display = 'inline';
            response.textContent = '正在上传...';
            
            try {
                const formData = new FormData();
                formData.append('apikey', API_KEY);
                formData.append('webdav_account', form.webdav_account.value);
                formData.append('file_path', form.file_path.value);
                
                const uploadType = document.querySelector('input[name="upload_type"]:checked').value;
                
                if (uploadType === 'file') {
                    if (!form.file.files[0]) {
                        throw new Error('请选择要上传的文件');
                    }
                    formData.append('file', form.file.files[0]);
                } else {
                    if (!form.file_url.value) {
                        throw new Error('请输入文件URL');
                    }
                    formData.append('file_url', form.file_url.value);
                }
                
                const res = await fetch('/api.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await res.json();
                response.textContent = JSON.stringify(data, null, 2);
                
                if (data.success) {
                    response.style.background = '#d4edda';
                    response.style.color = '#155724';
                    
                    // 如果上传成功，自动刷新当前目录（如果路径匹配）
                    if (currentAccount === form.webdav_account.value) {
                        const uploadPath = form.file_path.value;
                        const uploadDir = uploadPath.endsWith('/') ? uploadPath : uploadPath.substring(0, uploadPath.lastIndexOf('/') + 1);
                        
                        if (currentPath === uploadDir || currentPath === uploadDir.slice(0, -1)) {
                            setTimeout(() => {
                                refreshCurrentDirectory();
                            }, 1000);
                        }
                    }
                } else {
                    response.style.background = '#f8d7da';
                    response.style.color = '#721c24';
                }
                
            } catch (error) {
                response.textContent = '错误: ' + error.message;
                response.style.background = '#f8d7da';
                response.style.color = '#721c24';
            } finally {
                loading.style.display = 'none';
            }
        }
    </script>
</body>
</html>
