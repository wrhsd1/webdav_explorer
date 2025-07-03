# PHP WebDAV 管理系统

一个基于PHP的WebDAV文件管理系统，支持多账户管理、文件预览、批量操作等功能。

## 功能特性

### 🔐 安全特性
- 管理员登录认证
- 会话管理（30分钟过期）
- .htaccess文件保护
- 敏感文件访问控制
- URL编码安全处理

### 📁 文件管理
- 现代化列表视图界面
- 文件/文件夹的创建、删除、重命名
- 批量操作支持
- 文件上传（支持中文文件名）
- 文件下载和直链生成
- 智能文件类型识别

### 👁️ 预览功能
- 图片预览（JPG, PNG, GIF, WebP等）
- 文本文件预览（TXT, MD, HTML, CSS, JS等）
- 代码文件预览（PHP, Python, Java等）
- 视频/音频预览（MP4, MP3等）
- PDF文件预览

### 🎛️ 管理功能
- 多WebDAV账户管理
- 账户信息编辑
- 密码管理
- 配置文件管理

### 🔍 用户体验
- 响应式设计
- 面包屑导航
- 实时搜索过滤
- 键盘快捷键支持
- 现代化UI界面

## 安装部署

### 1. 环境要求
- PHP 7.0+
- cURL扩展
- DOM扩展
- 支持.htaccess的Web服务器

### 2. 安装步骤

1. **下载代码**
   ```bash
   git clone https://github.com/your-repo/php-webdav-manager.git
   cd php-webdav-manager
   ```

2. **配置环境**
   ```bash
   cp .env.example .env
   chmod 600 .env
   ```

3. **编辑配置文件**
   编辑 `.env` 文件，设置：
   - 管理员密码
   - WebDAV账户信息
   - 直链域名

4. **设置文件权限**
   ```bash
   chmod 644 *.php
   chmod 644 .htaccess
   chmod 755 includes/
   chmod 644 includes/*.php
   ```

5. **访问系统**
   打开浏览器访问部署的URL，使用管理员密码登录。

### 3. 配置示例

`.env` 文件配置示例：
```env
# 管理员密码
ADMIN_PASSWORD=your_strong_password

# WebDAV账户配置
WEBDAV_ACCOUNTS=main,backup

# 主账户
WEBDAV_MAIN_NAME=我的云盘
WEBDAV_MAIN_HOST=https://webdav.example.com
WEBDAV_MAIN_USERNAME=username
WEBDAV_MAIN_PASSWORD=password
WEBDAV_MAIN_PATH=/files

# 直链域名
DIRECT_LINK_DOMAIN=https://yourdomain.com
```

## 使用说明

### 登录系统
1. 访问系统首页
2. 点击"登录"
3. 输入管理员密码

### 管理WebDAV账户
1. 进入"后台管理"
2. 添加/编辑WebDAV账户信息
3. 设置服务器地址、用户名、密码等

### 文件管理
1. 选择WebDAV账户
2. 浏览文件和文件夹
3. 上传、下载、删除、重命名文件
4. 使用搜索功能快速定位文件

### 文件预览
- 点击可预览文件的文件名或预览按钮
- 支持图片、文本、代码、视频等格式
- 大文件建议下载后查看

## 安全说明

### 安全措施
- 所有敏感文件通过.htaccess保护
- 会话过期自动登出
- WebDAV连接加密传输
- 文件路径严格验证

### 安全建议
- 使用强密码
- 启用HTTPS传输
- 定期更新密码
- 监控访问日志
- 设置适当的文件权限

详细安全信息请查看 [SECURITY.md](SECURITY.md)

## 技术架构

### 目录结构
```
/
├── .htaccess                # 主保护文件
├── .env                     # 配置文件
├── .env.example            # 配置示例
├── index.php               # 系统首页
├── login.php               # 登录页面
├── logout.php              # 登出处理
├── admin.php               # 后台管理
├── filemanager.php         # 文件管理
├── download.php            # 文件下载
├── direct.php              # 直链生成
├── transfer.php            # 文件中转
├── includes/               # 核心类库
│   ├── .htaccess          # 目录保护
│   ├── auth.php           # 认证管理
│   ├── config.php         # 配置管理
│   └── webdav.php         # WebDAV客户端
└── templates/              # 模板文件
    └── .htaccess          # 目录保护
```

### 核心类库
- **Auth**: 用户认证和会话管理
- **Config**: 配置文件读写和账户管理
- **WebDAVClient**: WebDAV协议客户端实现

## 开发说明

### 代码规范
- PSR-4 自动加载
- 面向对象设计
- 异常处理机制
- 安全编码实践

### 扩展开发
系统采用模块化设计，可以方便地扩展功能：
- 添加新的文件预览类型
- 集成其他云存储服务
- 增加用户权限管理
- 添加操作日志记录

## 常见问题

### Q: 无法访问.env文件？
A: 这是正常的，.htaccess已经保护了该文件，确保配置安全。

### Q: 文件上传失败？
A: 检查PHP上传限制、WebDAV服务器配置和网络连接。

### Q: 中文文件名显示异常？
A: 系统已处理中文文件名编码，如有问题请检查服务器字符集配置。

### Q: 预览功能不工作？
A: 确保transfer.php可访问，检查WebDAV服务器CORS设置。

## 许可证

MIT License

## 贡献

欢迎提交Issue和Pull Request！

## 更新日志

### v1.0.0
- 初始版本发布
- 支持多WebDAV账户管理
- 实现文件管理功能
- 添加文件预览功能
- 完善安全防护机制
