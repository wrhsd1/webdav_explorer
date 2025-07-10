<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API文档 - WebDAV管理系统</title>
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
        
        .btn-secondary {
            background: #6c757d;
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
        
        .card h3 {
            color: #555;
            margin-top: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .endpoint {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .method {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            font-weight: bold;
            font-size: 0.8rem;
            margin-right: 0.5rem;
        }
        
        .method.post {
            background: #28a745;
            color: white;
        }
        
        .method.get {
            background: #007bff;
            color: white;
        }
        
        .endpoint-url {
            font-family: monospace;
            background: #e9ecef;
            padding: 0.5rem;
            border-radius: 4px;
            margin: 0.5rem 0;
        }
        
        .params {
            margin-top: 1rem;
        }
        
        .param {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 0.75rem;
            margin-bottom: 0.5rem;
        }
        
        .param-name {
            font-weight: 600;
            color: #495057;
        }
        
        .param-type {
            display: inline-block;
            background: #6c757d;
            color: white;
            padding: 0.125rem 0.375rem;
            border-radius: 3px;
            font-size: 0.7rem;
            margin-left: 0.5rem;
        }
        
        .param-required {
            display: inline-block;
            background: #dc3545;
            color: white;
            padding: 0.125rem 0.375rem;
            border-radius: 3px;
            font-size: 0.7rem;
            margin-left: 0.25rem;
        }
        
        .param-desc {
            margin-top: 0.5rem;
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .example {
            background: #f8f9fa;
            border-left: 4px solid #007bff;
            padding: 1rem;
            margin: 1rem 0;
        }
        
        .example h4 {
            color: #007bff;
            margin-bottom: 0.5rem;
        }
        
        .code {
            background: #2d3748;
            color: #e2e8f0;
            padding: 1rem;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            overflow-x: auto;
            white-space: pre-wrap;
        }
        
        .response {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 1rem;
            margin-top: 1rem;
        }
        
        .response h4 {
            color: #28a745;
            margin-bottom: 0.5rem;
        }
        
        .error-codes {
            margin-top: 1rem;
        }
        
        .error-code {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem;
            border-bottom: 1px solid #eee;
        }
        
        .error-code:last-child {
            border-bottom: none;
        }
        
        .status-code {
            font-weight: 600;
            color: #dc3545;
        }
        
        .toc {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .toc h2 {
            border-bottom: 2px solid rgba(255,255,255,0.3);
            color: white;
        }
        
        .toc ul {
            list-style: none;
            margin-top: 1rem;
        }
        
        .toc li {
            margin-bottom: 0.5rem;
        }
        
        .toc a {
            color: rgba(255,255,255,0.9);
            text-decoration: none;
            transition: color 0.2s;
        }
        
        .toc a:hover {
            color: white;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>WebDAV API 文档</h1>
        <a href="index.php" class="btn btn-secondary">返回首页</a>
    </div>
    
    <div class="container">
        <!-- 目录 -->
        <div class="toc">
            <h2>📋 目录</h2>
            <ul>
                <li><a href="#overview">🔍 概述</a></li>
                <li><a href="#authentication">🔐 认证方式</a></li>
                <li><a href="#upload-api">📤 上传API</a></li>
                <li><a href="#download-api">📥 下载/列表API</a></li>
                <li><a href="#mkdir-api">📁 新建文件夹API</a></li>
                <li><a href="#error-codes">⚠️ 错误代码</a></li>
                <li><a href="#examples">💡 使用示例</a></li>
            </ul>
        </div>
        
        <!-- 概述 -->
        <div class="card" id="overview">
            <h2>🔍 概述</h2>
            <p>WebDAV API 提供了程序化访问和管理您的WebDAV文件的功能。通过API，您可以：</p>
            <ul style="margin: 1rem 0 0 2rem;">
                <li>上传文件到WebDAV服务器</li>
                <li>从URL下载文件并上传到WebDAV</li>
                <li>获取目录和文件列表</li>
                <li>创建新的文件夹</li>
                <li>获取文件的直链地址</li>
            </ul>
            
            <div style="background: #e3f2fd; border: 1px solid #2196f3; border-radius: 8px; padding: 1rem; margin: 1rem 0;">
                <h4 style="color: #1976d2; margin-bottom: 0.5rem;">🧪 快速测试</h4>
                <p style="margin: 0; color: #424242;">
                    想要快速体验API功能？访问 
                    <a href="api_test.php" style="color: #1976d2; text-decoration: none; font-weight: 500;">🧪 API测试工具</a>
                    ，无需编写代码即可测试所有API功能：
                </p>
                <ul style="margin: 0.5rem 0 0 2rem; color: #424242;">
                    <li>📥 列表浏览：可视化目录导航</li>
                    <li>📁 新建文件夹：快速创建目录结构</li>
                    <li>📤 文件上传：支持本地文件和URL下载</li>
                    <li>🔄 智能刷新：操作后自动更新显示</li>
                </ul>
            </div>
            
            <h3>API接口列表</h3>
            <ul style="margin: 1rem 0 0 2rem;">
                <li><strong>GET /api.php</strong> - 获取文件列表</li>
                <li><strong>POST /api.php</strong> - 上传文件</li>
                <li><strong>POST /mkdir_api.php</strong> - 新建文件夹</li>
            </ul>
            
            <h3>基础信息</h3>
            <ul style="margin: 1rem 0 0 2rem;">
                <li><strong>返回格式:</strong> JSON</li>
                <li><strong>编码:</strong> UTF-8</li>
                <li><strong>认证方式:</strong> API密钥</li>
            </ul>
        </div>
        
        <!-- 认证方式 -->
        <div class="card" id="authentication">
            <h2>🔐 认证方式</h2>
            <p>所有API请求都需要提供有效的API密钥进行认证。</p>
            
            <h3>API密钥格式</h3>
            <div class="code">用户名_密钥后缀</div>
            
            <h3>获取API密钥</h3>
            <ol style="margin: 1rem 0 0 2rem;">
                <li>登录系统后前往 "个人设置" 页面</li>
                <li>在 "API密钥管理" 部分查看或修改您的API密钥</li>
                <li>点击 "复制完整密钥" 按钮复制完整的API密钥</li>
            </ol>
            
            <h3>使用方式</h3>
            <p>在所有API请求中包含 <code>apikey</code> 参数：</p>
            <div class="code">apikey=your_username_your_api_key_suffix</div>
        </div>
        
        <!-- 上传API -->
        <div class="card" id="upload-api">
            <h2>📤 上传API</h2>
            
            <div class="endpoint">
                <span class="method post">POST</span>
                <span style="font-weight: 600;">/api.php</span>
                <div class="endpoint-url">POST /api.php</div>
                
                <p><strong>功能:</strong> 上传文件到WebDAV服务器，支持直接文件上传和从URL下载上传两种方式。</p>
                
                <div class="params">
                    <h4>请求参数</h4>
                    
                    <div class="param">
                        <span class="param-name">apikey</span>
                        <span class="param-type">string</span>
                        <span class="param-required">必填</span>
                        <div class="param-desc">您的API密钥，格式为 "用户名_密钥后缀"</div>
                    </div>
                    
                    <div class="param">
                        <span class="param-name">webdav_account</span>
                        <span class="param-type">string</span>
                        <span class="param-required">必填</span>
                        <div class="param-desc">WebDAV账户标识，在个人设置中配置的账户key</div>
                    </div>
                    
                    <div class="param">
                        <span class="param-name">file_path</span>
                        <span class="param-type">string</span>
                        <span class="param-required">必填</span>
                        <div class="param-desc">
                            目标路径，支持多层级路径。路径格式说明：<br>
                            • 以 <code>/</code> 结尾：作为目录处理，如 <code>/folder/</code> → 上传到 <code>folder/文件名</code><br>
                            • 不以 <code>/</code> 结尾且无扩展名：作为目录处理，如 <code>/folder</code> → 上传到 <code>folder/文件名</code><br>
                            • 不以 <code>/</code> 结尾且有扩展名：作为完整文件路径，如 <code>/folder/myfile.pdf</code> → 上传为 <code>folder/myfile.pdf</code>
                        </div>
                    </div>
                    
                    <div class="param">
                        <span class="param-name">file</span>
                        <span class="param-type">file</span>
                        <span style="background: #17a2b8; color: white; padding: 0.125rem 0.375rem; border-radius: 3px; font-size: 0.7rem; margin-left: 0.25rem;">二选一</span>
                        <div class="param-desc">要上传的文件 (multipart/form-data)</div>
                    </div>
                    
                    <div class="param">
                        <span class="param-name">file_url</span>
                        <span class="param-type">string</span>
                        <span style="background: #17a2b8; color: white; padding: 0.125rem 0.375rem; border-radius: 3px; font-size: 0.7rem; margin-left: 0.25rem;">二选一</span>
                        <div class="param-desc">要下载的文件URL地址</div>
                    </div>
                </div>
            </div>
            
            <div class="response">
                <h4>成功响应示例</h4>
                <div class="code">{
    "success": true,
    "message": "文件上传成功",
    "data": {
        "file_path": "documents/report.pdf",
        "file_name": "report.pdf",
        "file_size": 2048576,
        "direct_link": "https://yourdomain.com/transfer.php?account=account1&path=documents%2Freport.pdf",
        "webdav_account": "account1"
    },
    "timestamp": "2025-01-01 12:00:00"
}</div>
            </div>
        </div>
        
        <!-- 下载/列表API -->
        <div class="card" id="download-api">
            <h2>📥 下载/列表API</h2>
            
            <div class="endpoint">
                <span class="method get">GET</span>
                <span style="font-weight: 600;">/api.php</span>
                <div class="endpoint-url">GET /api.php</div>
                
                <p><strong>功能:</strong> 获取指定路径下的文件和文件夹列表，包含直链信息。</p>
                
                <div class="params">
                    <h4>请求参数</h4>
                    
                    <div class="param">
                        <span class="param-name">apikey</span>
                        <span class="param-type">string</span>
                        <span class="param-required">必填</span>
                        <div class="param-desc">您的API密钥，格式为 "用户名_密钥后缀"</div>
                    </div>
                    
                    <div class="param">
                        <span class="param-name">webdav_account</span>
                        <span class="param-type">string</span>
                        <span class="param-required">必填</span>
                        <div class="param-desc">WebDAV账户标识</div>
                    </div>
                    
                    <div class="param">
                        <span class="param-name">file_path</span>
                        <span class="param-type">string</span>
                        <span style="background: #ffc107; color: #212529; padding: 0.125rem 0.375rem; border-radius: 3px; font-size: 0.7rem; margin-left: 0.25rem;">可选</span>
                        <div class="param-desc">要列出的目录路径，默认为根目录 "/"</div>
                    </div>
                </div>
            </div>
            
            <div class="response">
                <h4>成功响应示例</h4>
                <div class="code">{
    "success": true,
    "message": "获取文件列表成功",
    "data": {
        "path": "/documents/",
        "webdav_account": "account1",
        "items": [
            {
                "name": "subfolder",
                "path": "/documents/subfolder/",
                "is_directory": true,
                "type": "directory",
                "size": 0,
                "modified": "2025-01-01T10:30:00Z",
                "file_type": "directory"
            },
            {
                "name": "report.pdf",
                "path": "/documents/report.pdf",
                "is_directory": false,
                "type": "file",
                "size": 2048576,
                "modified": "2025-01-01T09:15:00Z",
                "file_type": "document",
                "direct_link": "https://yourdomain.com/transfer.php?account=account1&path=%2Fdocuments%2Freport.pdf"
            }
        ],
        "total_count": 2,
        "file_count": 1,
        "directory_count": 1
    },
    "timestamp": "2025-01-01 12:00:00"
}</div>
            </div>
        </div>
        
        <!-- 新建文件夹API -->
        <div class="card" id="mkdir-api">
            <h2>📁 新建文件夹API</h2>
            
            <div class="endpoint">
                <span class="method post">POST</span>
                <span style="font-weight: 600;">/mkdir_api.php</span>
                <div class="endpoint-url">POST /mkdir_api.php</div>
                
                <p><strong>功能:</strong> 在WebDAV存储中创建新的文件夹，支持递归创建多级目录。</p>
                
                <div class="params">
                    <h4>请求参数</h4>
                    
                    <div class="param">
                        <span class="param-name">apikey</span>
                        <span class="param-type">string</span>
                        <span class="param-required">必填</span>
                        <div class="param-desc">您的API密钥，格式为 "用户名_密钥后缀"</div>
                    </div>
                    
                    <div class="param">
                        <span class="param-name">webdav_account</span>
                        <span class="param-type">string</span>
                        <span class="param-required">必填</span>
                        <div class="param-desc">WebDAV账户标识符</div>
                    </div>
                    
                    <div class="param">
                        <span class="param-name">dir_path</span>
                        <span class="param-type">string</span>
                        <span class="param-required">可选*</span>
                        <div class="param-desc">父目录路径或完整目录路径。默认为根目录 "/"</div>
                    </div>
                    
                    <div class="param">
                        <span class="param-name">dir_name</span>
                        <span class="param-type">string</span>
                        <span class="param-required">可选*</span>
                        <div class="param-desc">要创建的文件夹名称。*dir_path和dir_name至少需要提供一个</div>
                    </div>
                    
                    <div class="param">
                        <span class="param-name">recursive</span>
                        <span class="param-type">string</span>
                        <span class="param-required">可选</span>
                        <div class="param-desc">是否递归创建父目录。可选值: "true", "false"。默认为 "true"</div>
                    </div>
                </div>
                
                <div class="response">
                    <h4>成功响应 (201 Created)</h4>
                    <div class="code">{
    "success": true,
    "message": "目录创建成功",
    "data": {
        "dir_path": "/documents/new_project",
        "dir_name": "new_project",
        "parent_path": "/documents",
        "webdav_account": "account1",
        "create_method": "recursive",
        "recursive": true
    },
    "timestamp": "2025-07-10 15:30:45"
}</div>
                </div>
                
                <div class="response">
                    <h4>目录已存在 (409 Conflict)</h4>
                    <div class="code">{
    "success": false,
    "message": "目录已存在",
    "data": {
        "dir_path": "/documents/existing_folder",
        "exists": true
    },
    "timestamp": "2025-07-10 15:30:45"
}</div>
                </div>
            </div>
        </div>
        
        <!-- 错误代码 -->
        <div class="card" id="error-codes">
            <h2>⚠️ 错误代码</h2>
            
            <div class="error-codes">
                <div class="error-code">
                    <span class="status-code">400</span>
                    <span>请求参数错误或缺失</span>
                </div>
                <div class="error-code">
                    <span class="status-code">401</span>
                    <span>API密钥无效或缺失</span>
                </div>
                <div class="error-code">
                    <span class="status-code">404</span>
                    <span>WebDAV账户不存在或文件/目录未找到</span>
                </div>
                <div class="error-code">
                    <span class="status-code">405</span>
                    <span>请求方法不支持</span>
                </div>
                <div class="error-code">
                    <span class="status-code">500</span>
                    <span>服务器内部错误</span>
                </div>
            </div>
            
            <h3>错误响应格式</h3>
            <div class="code">{
    "success": false,
    "message": "错误描述信息",
    "data": null,
    "timestamp": "2025-01-01 12:00:00"
}</div>
        </div>
        
        <!-- 使用示例 -->
        <div class="card" id="examples">
            <h2>💡 使用示例</h2>
            
            <div class="example">
                <h4>上传文件到目录 (cURL)</h4>
                <div class="code">curl -X POST "https://yourdomain.com/api.php" \
     -F "apikey=myuser_abc123456" \
     -F "webdav_account=account1" \
     -F "file_path=/documents/" \
     -F "file=@/local/path/to/file.pdf"</div>
            </div>
            
            <div class="example">
                <h4>上传文件并指定文件名 (cURL)</h4>
                <div class="code">curl -X POST "https://yourdomain.com/api.php" \
     -F "apikey=myuser_abc123456" \
     -F "webdav_account=account1" \
     -F "file_path=/documents/report.pdf" \
     -F "file=@/local/path/to/file.pdf"</div>
            </div>
            
            <div class="example">
                <h4>从URL上传文件 (cURL)</h4>
                <div class="code">curl -X POST "https://yourdomain.com/api.php" \
     -d "apikey=myuser_abc123456" \
     -d "webdav_account=account1" \
     -d "file_path=/downloads/" \
     -d "file_url=https://example.com/image.jpg"</div>
            </div>
            
            <div class="example">
                <h4>获取文件列表 (cURL)</h4>
                <div class="code">curl "https://yourdomain.com/api.php?apikey=myuser_abc123456&webdav_account=account1&file_path=/documents/"</div>
            </div>
            
            <div class="example">
                <h4>新建文件夹 (cURL)</h4>
                <div class="code">curl -X POST "https://yourdomain.com/mkdir_api.php" \
     -d "apikey=myuser_abc123456" \
     -d "webdav_account=account1" \
     -d "dir_path=/documents" \
     -d "dir_name=new_project"</div>
            </div>
            
            <div class="example">
                <h4>新建文件夹 (cURL)</h4>
                <div class="code">curl -X POST "https://yourdomain.com/mkdir_api.php" \
     -d "apikey=myuser_abc123456" \
     -d "webdav_account=account1" \
     -d "dir_path=/documents" \
     -d "dir_name=new_project"</div>
            </div>
            
            <div class="example">
                <h4>递归创建多级目录 (cURL)</h4>
                <div class="code">curl -X POST "https://yourdomain.com/mkdir_api.php" \
     -d "apikey=myuser_abc123456" \
     -d "webdav_account=account1" \
     -d "dir_path=/projects/2025/webapp" \
     -d "recursive=true"</div>
            </div>
            
            <div class="example">
                <h4>JavaScript 示例</h4>
                <div class="code">// 上传文件
const formData = new FormData();
formData.append('apikey', 'myuser_abc123456');
formData.append('webdav_account', 'account1');
formData.append('file_path', '/uploads/');
formData.append('file', fileInput.files[0]);

fetch('/api.php', {
    method: 'POST',
    body: formData
})
.then(response => response.json())
.then(data => {
    if (data.success) {
        console.log('上传成功:', data.data.direct_link);
    } else {
        console.error('上传失败:', data.message);
    }
});

// 获取文件列表
fetch('/api.php?apikey=myuser_abc123456&webdav_account=account1&file_path=/')
.then(response => response.json())
.then(data => {
    if (data.success) {
        data.data.items.forEach(item => {
            console.log(item.name, item.is_directory ? '(目录)' : '(文件)');
        });
    }
});

// 新建文件夹
const mkdirData = new FormData();
mkdirData.append('apikey', 'myuser_abc123456');
mkdirData.append('webdav_account', 'account1');
mkdirData.append('dir_path', '/documents');
mkdirData.append('dir_name', 'new_folder');

fetch('/mkdir_api.php', {
    method: 'POST',
    body: mkdirData
})
.then(response => response.json())
.then(data => {
    if (data.success) {
        console.log('文件夹创建成功:', data.data.dir_path);
    } else {
        console.error('创建失败:', data.message);
    }
});</div>
            </div>
            
            <div class="example">
                <h4>Python 示例</h4>
                <div class="code">import requests

# 上传文件
with open('local_file.pdf', 'rb') as f:
    files = {'file': f}
    data = {
        'apikey': 'myuser_abc123456',
        'webdav_account': 'account1',
        'file_path': '/uploads/'
    }
    
    response = requests.post('https://yourdomain.com/api.php', 
                           files=files, data=data)
    result = response.json()
    
    if result['success']:
        print(f"上传成功: {result['data']['direct_link']}")
    else:
        print(f"上传失败: {result['message']}")

# 获取文件列表
params = {
    'apikey': 'myuser_abc123456',
    'webdav_account': 'account1',
    'file_path': '/'
}

response = requests.get('https://yourdomain.com/api.php', params=params)
result = response.json()

if result['success']:
    for item in result['data']['items']:
        print(f"{item['name']} - {'目录' if item['is_directory'] else '文件'}")
</div>
            </div>
        </div>
    </div>
</body>
</html>
