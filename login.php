<?php
require_once 'includes/auth.php';

// 处理登录
if ($_POST) {
    $password = $_POST['password'] ?? '';
    if (Auth::login($password)) {
        header('Location: index.php');
        exit;
    } else {
        $error = '密码错误，请重试';
    }
}

// 如果已经登录，重定向到主页
if (Auth::isLoggedIn()) {
    header('Location: index.php');
    exit;
}

include 'templates/login.html';
?>
