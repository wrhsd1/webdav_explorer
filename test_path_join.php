<?php
// 测试路径拼接逻辑

function testPathJoin($currentPath, $itemName, $isCollection) {
    $result = rtrim($currentPath, '/') . '/' . $itemName . ($isCollection ? '/' : '');
    echo "当前路径: '$currentPath', 项目名: '$itemName', 是目录: " . ($isCollection ? 'true' : 'false') . "\n";
    echo "结果: '$result'\n\n";
    return $result;
}

// 测试各种情况
testPathJoin('/', 'README.md', false);           // 根目录文件
testPathJoin('/', 'folder', true);               // 根目录文件夹
testPathJoin('/2023/2025', 'README.md', false);  // 子目录文件
testPathJoin('/2023/2025/', 'README.md', false); // 子目录文件（路径以/结尾）
testPathJoin('/2023/2025', 'subfolder', true);   // 子目录文件夹
testPathJoin('/docs', 'test.txt', false);        // 普通路径
?>
