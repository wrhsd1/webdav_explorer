#!/bin/bash

# PHP WebDAV 管理系统安装脚本

echo "==================================="
echo "PHP WebDAV 管理系统安装向导"
echo "==================================="

# 检查PHP环境
echo "检查PHP环境..."
if ! command -v php &> /dev/null; then
    echo "错误: 未找到PHP，请先安装PHP 7.0+"
    exit 1
fi

PHP_VERSION=$(php -r "echo PHP_VERSION;")
echo "PHP版本: $PHP_VERSION"

# 检查必需扩展
echo "检查PHP扩展..."
php -m | grep -q curl || { echo "错误: 缺少curl扩展"; exit 1; }
php -m | grep -q dom || { echo "错误: 缺少dom扩展"; exit 1; }
echo "✓ PHP扩展检查通过"

# 创建配置文件
if [ ! -f .env ]; then
    echo "创建配置文件..."
    cp .env.example .env
    echo "✓ 配置文件已创建: .env"
    echo "请编辑 .env 文件设置您的配置"
else
    echo "⚠ 配置文件已存在，跳过创建"
fi

# 设置文件权限
echo "设置文件权限..."
chmod 600 .env 2>/dev/null || echo "⚠ 无法设置.env权限，请手动设置为600"
chmod 644 *.php 2>/dev/null || echo "⚠ 无法设置PHP文件权限"
chmod 644 .htaccess 2>/dev/null || echo "⚠ 无法设置.htaccess权限"

# 检查Web服务器配置
echo "检查Web服务器配置..."
if [ -f .htaccess ]; then
    echo "✓ .htaccess文件存在"
    echo "请确保您的Web服务器支持.htaccess并启用了mod_rewrite"
else
    echo "⚠ .htaccess文件不存在"
fi

echo ""
echo "==================================="
echo "安装完成！"
echo "==================================="
echo ""
echo "下一步操作："
echo "1. 编辑 .env 文件设置管理员密码和WebDAV账户"
echo "2. 确保Web服务器支持.htaccess"
echo "3. 访问您的网站开始使用"
echo ""
echo "安全提醒："
echo "- 请设置强密码"
echo "- 建议使用HTTPS"
echo "- 定期更新密码"
echo ""
echo "如需帮助，请查看 README.md 和 SECURITY.md"
