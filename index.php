<?php
require_once 'includes/auth.php';
require_once 'includes/user.php';
require_once 'includes/bookmark.php';

Auth::requireLogin();

$userManager = new User();
$bookmarkManager = new Bookmark();
$currentUserId = Auth::getCurrentUserId();
$currentUser = Auth::getCurrentUser();

// 获取当前用户的WebDAV配置
$userWebdavConfigs = $userManager->getUserWebdavConfigs($currentUserId);

// 获取书签统计
try {
    $bookmarkStats = $bookmarkManager->getBookmarkStats($currentUserId);
} catch (Exception $e) {
    $bookmarkStats = ['total' => 0, 'accounts' => 0, 'latest' => null];
}
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
        
        .header-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .user-info {
            color: #666;
            font-size: 0.9rem;
        }
        
        .admin-badge {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
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
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            position: relative;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-bookmark {
            background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%);
            color: white;
            font-weight: 500;
        }
        
        .btn:hover {
            transform: translateY(-1px);
            opacity: 0.9;
        }
        
        .bookmark-count {
            background: rgba(255,255,255,0.3);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-left: 0.25rem;
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
        
        /* 书签面板样式 */
        .bookmarks-panel {
            position: fixed;
            top: 0;
            right: -400px;
            width: 400px;
            height: 100vh;
            background: linear-gradient(180deg, #f8fafc 0%, #e2e8f0 100%);
            box-shadow: -5px 0 20px rgba(0,0,0,0.1);
            z-index: 1000;
            transition: right 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            display: flex;
            flex-direction: column;
            border-left: 1px solid #e2e8f0;
        }
        
        .bookmarks-panel.open {
            right: 0;
        }
        
        .bookmarks-header {
            background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%);
            color: white;
            padding: 1.5rem;
            text-align: center;
            position: relative;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .bookmarks-header h3 {
            margin: 0;
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: white;
        }
        
        .bookmarks-header .subtitle {
            opacity: 0.8;
            font-size: 0.9rem;
        }
        
        .bookmarks-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 1.2rem;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .bookmarks-close:hover {
            background: rgba(255,255,255,0.3);
            transform: scale(1.1);
        }
        
        .bookmarks-controls {
            background: white;
            padding: 1rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .bookmarks-search {
            display: flex;
            align-items: center;
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 0.5rem;
        }
        
        .bookmarks-search input {
            border: none;
            background: transparent;
            outline: none;
            flex: 1;
            padding: 0.25rem;
            font-size: 0.875rem;
        }
        
        .bookmarks-list {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
            background: white;
        }
        
        .bookmark-item {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 0.75rem;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .bookmark-item:hover {
            background: #e2e8f0;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .bookmark-info {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
        }
        
        .bookmark-icon {
            font-size: 2rem;
            min-width: 3rem;
            text-align: center;
            opacity: 0.8;
        }
        
        .bookmark-details {
            flex: 1;
            min-width: 0;
        }
        
        .bookmark-name {
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 0.25rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .bookmark-path {
            font-size: 0.8rem;
            opacity: 0.7;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            margin-bottom: 0.25rem;
        }
        
        .bookmark-description {
            font-size: 0.75rem;
            opacity: 0.6;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .bookmark-meta {
            font-size: 0.7rem;
            opacity: 0.5;
            margin-top: 0.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .bookmark-account {
            background: rgba(0,0,0,0.1);
            padding: 0.125rem 0.375rem;
            border-radius: 10px;
            font-weight: 500;
        }
        
        .bookmark-actions {
            display: flex;
            gap: 0.5rem;
            opacity: 0;
            transition: opacity 0.2s;
            position: absolute;
            top: 0.75rem;
            right: 0.75rem;
        }
        
        .bookmark-item:hover .bookmark-actions {
            opacity: 1;
        }
        
        .bookmark-action-btn {
            background: rgba(0,0,0,0.1);
            border: none;
            border-radius: 6px;
            padding: 0.375rem;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.8rem;
            color: inherit;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .bookmark-action-btn:hover {
            background: rgba(0,0,0,0.2);
            transform: scale(1.1);
        }
        
        .bookmarks-empty {
            text-align: center;
            padding: 3rem 1rem;
            color: #718096;
        }
        
        .bookmarks-empty-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .bookmarks-empty h4 {
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
            color: #4a5568;
        }
        
        .bookmarks-empty p {
            font-size: 0.9rem;
            line-height: 1.5;
        }
        
        .bookmarks-stats {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            text-align: center;
        }
        
        .bookmarks-stats-title {
            font-size: 0.8rem;
            opacity: 0.8;
            margin-bottom: 0.5rem;
        }
        
        .bookmarks-stats-number {
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        /* 响应式设计 */
        @media (max-width: 768px) {
            .bookmarks-panel {
                width: 100vw;
                right: -100vw;
            }
            
            .header-actions {
                flex-wrap: wrap;
                gap: 0.5rem;
            }
            
            .btn {
                font-size: 0.8rem;
                padding: 0.4rem 0.8rem;
            }
            
            body.bookmarks-open {
                overflow: hidden;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>WebDAV管理系统</h1>
        <div class="header-info">
            <span class="user-info">欢迎，<?php echo htmlspecialchars($currentUser['username']); ?></span>
            <?php if (Auth::isAdmin()): ?>
                <span class="admin-badge">管理员</span>
            <?php endif; ?>
        </div>
        <div class="header-actions">
            <button onclick="showBookmarksPanel()" class="btn btn-bookmark" id="bookmarksBtn">
                📚 书签列表
                <?php if ($bookmarkStats['total'] > 0): ?>
                    <span class="bookmark-count"><?php echo $bookmarkStats['total']; ?></span>
                <?php endif; ?>
            </button>
            <a href="user_settings.php" class="btn btn-primary">个人设置</a>
            <?php if (Auth::isAdmin()): ?>
                <a href="super_admin.php" class="btn btn-warning">用户管理</a>
            <?php endif; ?>
            <a href="logout.php" class="btn btn-secondary">退出登录</a>
        </div>
    </div>
    
    <div class="container">
        <div class="welcome">
            <h2>欢迎使用WebDAV管理系统</h2>
            <p>选择一个WebDAV账户开始管理您的文件</p>
        </div>
        
        <?php if (empty($userWebdavConfigs)): ?>
            <div class="no-accounts">
                <h3>暂无WebDAV账户</h3>
                <p>请前往个人设置添加WebDAV账户配置</p>
                <a href="user_settings.php" class="btn btn-primary">前往个人设置</a>
            </div>
        <?php else: ?>
            <div class="account-grid">
                <?php foreach ($userWebdavConfigs as $config): ?>
                    <div class="account-card">
                        <div class="account-name"><?php echo htmlspecialchars($config['account_name']); ?></div>
                        <div class="account-info">
                            <div><strong>服务器:</strong> <?php echo htmlspecialchars($config['host']); ?></div>
                            <div><strong>用户名:</strong> <?php echo htmlspecialchars($config['username']); ?></div>
                            <div><strong>路径:</strong> <?php echo htmlspecialchars($config['path']); ?></div>
                        </div>
                        <div class="account-actions">
                            <a href="filemanager.php?account=<?php echo urlencode($config['account_key']); ?>" class="btn btn-primary">打开文件管理器</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- 书签面板 -->
    <div class="bookmarks-panel" id="bookmarksPanel">
        <div class="bookmarks-header">
            <h3>📚 书签管理</h3>
            <div class="subtitle">快速访问收藏的路径</div>
            <button class="bookmarks-close" onclick="closeBookmarks()">×</button>
        </div>
        
        <div class="bookmarks-controls">
            <div class="bookmarks-search">
                <input type="text" id="bookmarksSearch" placeholder="搜索书签..." onkeyup="filterBookmarks()">
                <span>🔍</span>
            </div>
        </div>
        
        <div class="bookmarks-list" id="bookmarksList">
            <div class="bookmarks-loading">正在加载书签...</div>
        </div>
    </div>

    <script>
        // 书签管理类
        class BookmarkManager {
            constructor() {
                this.bookmarks = [];
                this.userWebdavConfigs = <?php echo json_encode($userWebdavConfigs); ?>;
                this.init();
            }

            init() {
                this.loadBookmarks();
            }

            async loadBookmarks() {
                try {
                    const response = await fetch('bookmark_api.php?action=get_all');
                    const data = await response.json();
                    
                    if (data.success) {
                        this.bookmarks = data.bookmarks;
                        this.updateBookmarksUI();
                    } else {
                        throw new Error(data.message || '获取书签失败');
                    }
                } catch (error) {
                    console.error('加载书签失败:', error);
                    this.showBookmarksEmpty('获取书签失败，请刷新重试');
                }
            }

            updateBookmarksUI() {
                const listElement = document.getElementById('bookmarksList');
                if (!listElement) return;

                if (this.bookmarks.length === 0) {
                    this.showBookmarksEmpty();
                    return;
                }

                // 生成书签统计
                const stats = this.generateStats();
                
                // 分组书签
                const groupedBookmarks = this.groupBookmarksByAccount();

                let html = '';
                
                // 添加统计卡片
                html += `
                    <div class="bookmarks-stats">
                        <div class="bookmarks-stats-title">总书签数量</div>
                        <div class="bookmarks-stats-number">${stats.total}</div>
                    </div>
                `;

                // 渲染各账户的书签
                Object.keys(groupedBookmarks).forEach(accountKey => {
                    const accountBookmarks = groupedBookmarks[accountKey];
                    if (accountBookmarks.length === 0) return;

                    // 账户名称
                    const accountData = this.getAccountInfo(accountKey);
                    html += `<div style="margin: 1.5rem 0 0.75rem 0; padding: 0.5rem 0; border-bottom: 1px solid #e2e8f0; font-weight: 600; color: #4a5568; font-size: 0.9rem;">${accountData.name}</div>`;

                    accountBookmarks.forEach(bookmark => {
                        const timeAgo = this.timeAgo(bookmark.created_at);

                        html += `
                            <div class="bookmark-item" onclick="bookmarkManager.navigateToBookmark('${bookmark.account_key}', '${bookmark.path}')">
                                <div class="bookmark-info">
                                    <div class="bookmark-icon">⭐</div>
                                    <div class="bookmark-details">
                                        <div class="bookmark-name" title="${bookmark.name}">${bookmark.name}</div>
                                        <div class="bookmark-path" title="${bookmark.path}">${bookmark.path}</div>
                                        ${bookmark.description ? `<div class="bookmark-description">${bookmark.description}</div>` : ''}
                                        <div class="bookmark-meta">
                                            <span class="bookmark-account">${accountData.name}</span>
                                            <span>${timeAgo}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="bookmark-actions">
                                    <button class="bookmark-action-btn" onclick="event.stopPropagation(); bookmarkManager.deleteBookmark(${bookmark.id}, '${bookmark.name}')" title="删除">🗑️</button>
                                </div>
                            </div>
                        `;
                    });
                });

                listElement.innerHTML = html;
            }

            generateStats() {
                const accountsCount = new Set(this.bookmarks.map(b => b.account_key)).size;
                return {
                    total: this.bookmarks.length,
                    accounts: accountsCount
                };
            }

            groupBookmarksByAccount() {
                const grouped = {};
                this.bookmarks.forEach(bookmark => {
                    if (!grouped[bookmark.account_key]) {
                        grouped[bookmark.account_key] = [];
                    }
                    grouped[bookmark.account_key].push(bookmark);
                });

                // 排序：按创建时间倒序
                Object.keys(grouped).forEach(accountKey => {
                    grouped[accountKey].sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
                });

                return grouped;
            }

            getAccountInfo(accountKey) {
                const config = this.userWebdavConfigs.find(c => c.account_key === accountKey);
                return config ? { name: config.account_name } : { name: accountKey };
            }

            timeAgo(dateString) {
                const now = new Date();
                const date = new Date(dateString);
                const diff = now - date;
                const minutes = Math.floor(diff / 60000);
                const hours = Math.floor(diff / 3600000);
                const days = Math.floor(diff / 86400000);

                if (minutes < 1) return '刚刚';
                if (minutes < 60) return `${minutes}分钟前`;
                if (hours < 24) return `${hours}小时前`;
                if (days < 30) return `${days}天前`;
                return date.toLocaleDateString();
            }

            showBookmarksEmpty(message = null) {
                const listElement = document.getElementById('bookmarksList');
                if (!listElement) return;

                const defaultMessage = `
                    <div class="bookmarks-empty">
                        <div class="bookmarks-empty-icon">📚</div>
                        <h4>还没有书签</h4>
                        <p>在文件管理器中浏览到想要收藏的路径，点击"⭐ 添加书签"按钮即可添加书签</p>
                    </div>
                `;

                const errorMessage = `
                    <div class="bookmarks-empty">
                        <div class="bookmarks-empty-icon">⚠️</div>
                        <h4>加载失败</h4>
                        <p>${message}</p>
                    </div>
                `;

                listElement.innerHTML = message ? errorMessage : defaultMessage;
            }

            async navigateToBookmark(accountKey, path) {
                // 构建跳转URL到文件管理器
                const url = `filemanager.php?account=${encodeURIComponent(accountKey)}&path=${encodeURIComponent(path)}`;
                
                this.showMessage('正在跳转...');
                window.location.href = url;
            }

            async deleteBookmark(bookmarkId, bookmarkName) {
                if (!confirm(`确定要删除书签"${bookmarkName}"吗？`)) return;

                try {
                    const formData = new FormData();
                    formData.append('action', 'delete_bookmark');
                    formData.append('bookmark_id', bookmarkId);

                    const response = await fetch('bookmark_api.php', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();
                    
                    if (data.success) {
                        this.showMessage(`书签"${bookmarkName}"已删除`);
                        this.loadBookmarks(); // 重新加载书签列表
                        
                        // 更新头部书签计数
                        this.updateBookmarkCount();
                    } else {
                        throw new Error(data.message || '删除失败');
                    }
                } catch (error) {
                    console.error('删除书签失败:', error);
                    this.showMessage('删除失败：' + error.message);
                }
            }

            async updateBookmarkCount() {
                try {
                    const response = await fetch('bookmark_api.php?action=get_stats');
                    const data = await response.json();
                    
                    if (data.success) {
                        const countElement = document.querySelector('.bookmark-count');
                        const total = data.stats.total;
                        
                        if (total > 0) {
                            if (countElement) {
                                countElement.textContent = total;
                            } else {
                                // 如果计数元素不存在，创建一个
                                const bookmarksBtn = document.getElementById('bookmarksBtn');
                                const newCount = document.createElement('span');
                                newCount.className = 'bookmark-count';
                                newCount.textContent = total;
                                bookmarksBtn.appendChild(newCount);
                            }
                        } else {
                            if (countElement) {
                                countElement.remove();
                            }
                        }
                    }
                } catch (error) {
                    console.error('更新书签计数失败:', error);
                }
            }

            openBookmarks() {
                const panel = document.getElementById('bookmarksPanel');
                if (panel) {
                    panel.classList.add('open');
                    
                    // 移动端时禁止背景滚动
                    if (window.innerWidth <= 768) {
                        document.body.classList.add('bookmarks-open');
                    }
                    
                    // 加载最新书签
                    this.loadBookmarks();
                }
            }

            closeBookmarks() {
                const panel = document.getElementById('bookmarksPanel');
                if (panel) {
                    panel.classList.remove('open');
                    
                    // 移动端时恢复背景滚动
                    if (window.innerWidth <= 768) {
                        document.body.classList.remove('bookmarks-open');
                    }
                }
            }

            filterBookmarks() {
                const searchTerm = document.getElementById('bookmarksSearch').value.toLowerCase();
                const bookmarkItems = document.querySelectorAll('.bookmark-item');
                
                bookmarkItems.forEach(item => {
                    const name = item.querySelector('.bookmark-name').textContent.toLowerCase();
                    const path = item.querySelector('.bookmark-path').textContent.toLowerCase();
                    const description = item.querySelector('.bookmark-description');
                    const desc = description ? description.textContent.toLowerCase() : '';
                    
                    if (name.includes(searchTerm) || path.includes(searchTerm) || desc.includes(searchTerm)) {
                        item.style.display = '';
                    } else {
                        item.style.display = 'none';
                    }
                });
            }

            showMessage(message) {
                // 创建临时消息提示
                const messageDiv = document.createElement('div');
                messageDiv.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%);
                    color: white;
                    padding: 1rem 1.5rem;
                    border-radius: 25px;
                    box-shadow: 0 8px 32px rgba(0,0,0,0.3);
                    z-index: 10000;
                    font-size: 0.9rem;
                    font-weight: 500;
                    max-width: 300px;
                    word-wrap: break-word;
                    backdrop-filter: blur(20px);
                    border: 1px solid rgba(255,255,255,0.2);
                    animation: slideInRight 0.3s ease-out;
                `;
                
                messageDiv.textContent = message;
                document.body.appendChild(messageDiv);

                // 3秒后移除
                setTimeout(() => {
                    if (messageDiv.parentNode) {
                        messageDiv.style.animation = 'slideOutRight 0.3s ease-in';
                        setTimeout(() => {
                            messageDiv.parentNode.removeChild(messageDiv);
                        }, 300);
                    }
                }, 3000);
            }
        }

        // 初始化书签管理器
        const bookmarkManager = new BookmarkManager();

        // 全局函数
        function showBookmarksPanel() {
            bookmarkManager.openBookmarks();
        }

        function closeBookmarks() {
            bookmarkManager.closeBookmarks();
        }

        function filterBookmarks() {
            bookmarkManager.filterBookmarks();
        }

        // 键盘快捷键
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey || e.metaKey) {
                switch(e.key) {
                    case 'b':
                        e.preventDefault();
                        showBookmarksPanel();
                        break;
                }
            }
            
            if (e.key === 'Escape') {
                const bookmarksPanel = document.getElementById('bookmarksPanel');
                if (bookmarksPanel && bookmarksPanel.classList.contains('open')) {
                    closeBookmarks();
                    return;
                }
            }
        });

        // 添加CSS动画
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideInRight {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            
            @keyframes slideOutRight {
                from {
                    transform: translateX(0);
                    opacity: 1;
                }
                to {
                    transform: translateX(100%);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>
