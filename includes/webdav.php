<?php
require_once 'config.php';

class WebDAVClient {
    private $host;
    private $username;
    private $password;
    private $basePath;
    
    public function __construct($host, $username, $password, $basePath = '/') {
        $this->host = rtrim($host, '/');
        $this->username = $username;
        $this->password = $password;
        $this->basePath = $basePath;
    }
    
    private function makeRequest($method, $path, $data = null, $headers = []) {
        // 构造完整的WebDAV URL
        $cleanHost = rtrim($this->host, '/');
        $cleanBasePath = rtrim($this->basePath, '/');
        $cleanPath = ltrim($path, '/');
        
        // 对路径进行URL编码，但保持路径分隔符
        if ($cleanPath !== '') {
            $pathParts = explode('/', $cleanPath);
            $encodedParts = array_map('rawurlencode', $pathParts);
            $cleanPath = implode('/', $encodedParts);
        }
        
        // 构造完整URL: host + basePath + requestPath
        if ($cleanPath === '') {
            $url = $cleanHost . $cleanBasePath . '/';
        } else {
            $url = $cleanHost . $cleanBasePath . '/' . $cleanPath;
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_VERBOSE, false); // 可以设置为true进行调试
        
        $defaultHeaders = [
            'User-Agent: WebDAV Client',
            'Accept: */*'
        ];
        
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($defaultHeaders, $headers));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("cURL Error: " . $error);
        }
        
        return [
            'code' => $httpCode,
            'body' => $response
        ];
    }
    
    public function listDirectory($path = '/') {
        $response = $this->makeRequest('PROPFIND', $path, null, [
            'Depth: 1',
            'Content-Type: text/xml'
        ]);
        
        if ($response['code'] !== 207) {
            throw new Exception("Failed to list directory: HTTP " . $response['code']);
        }
        
        return $this->parseDirectoryListing($response['body'], $path);
    }
    
    private function parseDirectoryListing($xml, $currentPath) {
        $items = [];
        
        try {
            $doc = new DOMDocument();
            $doc->loadXML($xml);
            
            $xpath = new DOMXPath($doc);
            $xpath->registerNamespace('d', 'DAV:');
            
            $responses = $xpath->query('//d:response');
            
            // 从服务器响应中找到实际的基础路径
            // 通过分析第一个目录响应来确定服务器的路径结构
            $serverBasePath = '';
            foreach ($responses as $response) {
                $href = $xpath->query('.//d:href', $response)->item(0);
                if (!$href) continue;
                
                $hrefPath = rtrim(urldecode($href->textContent), '/');
                $prop = $xpath->query('.//d:propstat[d:status[contains(text(), "200")]]/d:prop', $response)->item(0);
                
                if ($prop) {
                    $isCollection = $xpath->query('.//d:resourcetype/d:collection', $prop)->length > 0;
                    // 如果这是一个目录，并且路径以我们配置的basePath结尾，那这就是我们的基础路径
                    $configBasePath = rtrim($this->basePath, '/');
                    if ($isCollection && (substr($hrefPath, -strlen($configBasePath)) === $configBasePath)) {
                        $serverBasePath = $hrefPath;
                        break;
                    }
                }
            }
            
            // 如果没找到，尝试其他方法
            if (empty($serverBasePath)) {
                // 假设第一个是当前目录
                $firstHref = $xpath->query('//d:response[1]//d:href')->item(0);
                if ($firstHref) {
                    $serverBasePath = rtrim(urldecode($firstHref->textContent), '/');
                }
            }
            
            foreach ($responses as $response) {
                $href = $xpath->query('.//d:href', $response)->item(0);
                if (!$href) continue;
                
                $hrefPath = urldecode($href->textContent);
                $hrefPath = rtrim($hrefPath, '/');
                
                // 跳过当前目录本身
                if ($hrefPath === $serverBasePath) {
                    continue;
                }
                
                // 检查是否是当前目录的直接子项
                $isDirectChild = false;
                $itemName = '';
                
                if (strpos($hrefPath, $serverBasePath . '/') === 0) {
                    $relativePath = substr($hrefPath, strlen($serverBasePath . '/'));
                    // 检查是否是直接子项（不包含额外的斜杠）
                    if (strpos($relativePath, '/') === false) {
                        $isDirectChild = true;
                        $itemName = $relativePath;
                    }
                }
                
                if (!$isDirectChild) {
                    continue;
                }
                
                $propstat = $xpath->query('.//d:propstat[d:status[contains(text(), "200")]]', $response)->item(0);
                if (!$propstat) continue;
                
                $prop = $xpath->query('.//d:prop', $propstat)->item(0);
                if (!$prop) continue;
                
                $isCollection = $xpath->query('.//d:resourcetype/d:collection', $prop)->length > 0;
                $contentLength = $xpath->query('.//d:getcontentlength', $prop)->item(0);
                $lastModified = $xpath->query('.//d:getlastmodified', $prop)->item(0);
                
                $items[] = [
                    'name' => $itemName,
                    'path' => $currentPath . $itemName . ($isCollection ? '/' : ''),
                    'is_dir' => $isCollection,
                    'size' => $contentLength ? (int)$contentLength->textContent : 0,
                    'modified' => $lastModified ? $lastModified->textContent : '',
                    'type' => $isCollection ? 'directory' : $this->getFileType($itemName)
                ];
            }
        } catch (Exception $e) {
            throw new Exception("Failed to parse directory listing: " . $e->getMessage());
        }
        
        return $items;
    }
    
    private function getFileType($filename) {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        $types = [
            'jpg' => 'image', 'jpeg' => 'image', 'png' => 'image', 'gif' => 'image', 'webp' => 'image',
            'mp4' => 'video', 'avi' => 'video', 'mov' => 'video', 'wmv' => 'video', 'mkv' => 'video',
            'mp3' => 'audio', 'wav' => 'audio', 'flac' => 'audio', 'aac' => 'audio',
            'pdf' => 'document', 'doc' => 'document', 'docx' => 'document', 'xls' => 'document', 'xlsx' => 'document',
            'txt' => 'text', 'md' => 'text', 'php' => 'code', 'js' => 'code', 'css' => 'code', 'html' => 'code',
            'zip' => 'archive', 'rar' => 'archive', '7z' => 'archive', 'tar' => 'archive', 'gz' => 'archive'
        ];
        
        return isset($types[$ext]) ? $types[$ext] : 'file';
    }
    
    public function downloadFile($path) {
        $response = $this->makeRequest('GET', $path);
        
        if ($response['code'] !== 200) {
            throw new Exception("Failed to download file: HTTP " . $response['code']);
        }
        
        return $response['body'];
    }
    
    public function uploadFile($path, $content) {
        $response = $this->makeRequest('PUT', $path, $content);
        
        if ($response['code'] !== 201 && $response['code'] !== 204) {
            throw new Exception("Failed to upload file: HTTP " . $response['code']);
        }
        
        return true;
    }
    
    public function createDirectory($path) {
        $response = $this->makeRequest('MKCOL', $path);
        
        if ($response['code'] !== 201) {
            throw new Exception("Failed to create directory: HTTP " . $response['code']);
        }
        
        return true;
    }
    
    public function deleteItem($path) {
        $response = $this->makeRequest('DELETE', $path);
        
        if ($response['code'] !== 204 && $response['code'] !== 200) {
            throw new Exception("Failed to delete item: HTTP " . $response['code']);
        }
        
        return true;
    }
    
    public function moveItem($fromPath, $toPath) {
        $response = $this->makeRequest('MOVE', $fromPath, null, [
            'Destination: ' . $this->host . $this->basePath . ltrim($toPath, '/')
        ]);
        
        if ($response['code'] !== 201 && $response['code'] !== 204) {
            throw new Exception("Failed to move item: HTTP " . $response['code']);
        }
        
        return true;
    }
    
    public function copyItem($fromPath, $toPath) {
        $response = $this->makeRequest('COPY', $fromPath, null, [
            'Destination: ' . $this->host . $this->basePath . ltrim($toPath, '/')
        ]);
        
        if ($response['code'] !== 201 && $response['code'] !== 204) {
            throw new Exception("Failed to copy item: HTTP " . $response['code']);
        }
        
        return true;
    }
    
    public function getStorageInfo($path = '/') {
        // 尝试多种方法获取存储信息
        $methods = [
            'standard' => 'getStorageInfoStandard',
            'apache' => 'getStorageInfoApache', 
            'nginx' => 'getStorageInfoNginx',
            'owncloud' => 'getStorageInfoOwnCloud',
            'estimate' => 'getStorageInfoEstimate'
        ];
        
        $allResults = [];
        $bestResult = null;
        
        foreach ($methods as $methodName => $methodFunc) {
            try {
                $result = $this->$methodFunc($path);
                $allResults[$methodName] = $result;
                
                // 如果找到有效的配额信息，优先使用
                if ($result['supported'] && ($result['quota_total'] > 0 || $result['quota_used'] > 0 || $result['quota_available'] > 0)) {
                    $bestResult = $result;
                    $bestResult['method'] = $methodName;
                    break;
                }
                
                // 保存第一个支持的结果作为备选
                if (!$bestResult && $result['supported']) {
                    $bestResult = $result;
                    $bestResult['method'] = $methodName;
                }
                
            } catch (Exception $e) {
                $allResults[$methodName] = [
                    'supported' => false,
                    'message' => $e->getMessage()
                ];
            }
        }
        
        if (!$bestResult) {
            return [
                'quota_available' => null,
                'quota_used' => null,
                'quota_total' => null,
                'supported' => false,
                'message' => 'WebDAV服务器不支持任何已知的配额查询方法',
                'debug_info' => $allResults
            ];
        }
        
        $bestResult['debug_info'] = $allResults;
        return $bestResult;
    }
    
    private function getStorageInfoStandard($path = '/') {
        // 标准WebDAV配额属性
        $propfindBody = '<?xml version="1.0" encoding="utf-8"?>
<D:propfind xmlns:D="DAV:">
    <D:prop>
        <D:quota-available-bytes/>
        <D:quota-used-bytes/>
        <D:quota-maximum-bytes/>
    </D:prop>
</D:propfind>';

        return $this->executeStorageQuery($path, $propfindBody, 'standard');
    }
    
    private function getStorageInfoApache($path = '/') {
        // Apache mod_dav_fs配额属性
        $propfindBody = '<?xml version="1.0" encoding="utf-8"?>
<D:propfind xmlns:D="DAV:" xmlns:A="http://apache.org/dav/props/">
    <D:prop>
        <A:quota-available-bytes/>
        <A:quota-used-bytes/>
        <D:quota-available-bytes/>
        <D:quota-used-bytes/>
    </D:prop>
</D:propfind>';

        return $this->executeStorageQuery($path, $propfindBody, 'apache');
    }
    
    private function getStorageInfoNginx($path = '/') {
        // Nginx WebDAV扩展属性
        $propfindBody = '<?xml version="1.0" encoding="utf-8"?>
<D:propfind xmlns:D="DAV:" xmlns:N="http://nginx.org/dav/props/">
    <D:prop>
        <N:quota-available/>
        <N:quota-used/>
        <D:getcontentlength/>
    </D:prop>
</D:propfind>';

        return $this->executeStorageQuery($path, $propfindBody, 'nginx');
    }
    
    private function getStorageInfoOwnCloud($path = '/') {
        // OwnCloud/NextCloud配额属性
        $propfindBody = '<?xml version="1.0" encoding="utf-8"?>
<D:propfind xmlns:D="DAV:" xmlns:OC="http://owncloud.org/ns">
    <D:prop>
        <OC:quota-available-bytes/>
        <OC:quota-used-bytes/>
        <D:quota-available-bytes/>
        <D:quota-used-bytes/>
    </D:prop>
</D:propfind>';

        return $this->executeStorageQuery($path, $propfindBody, 'owncloud');
    }
    
    private function getStorageInfoEstimate($path = '/') {
        // 通过列举文件估算使用空间
        try {
            $items = $this->listDirectory($path);
            $totalSize = 0;
            $fileCount = 0;
            
            foreach ($items as $item) {
                if (!$item['is_dir'] && isset($item['size'])) {
                    $totalSize += $item['size'];
                    $fileCount++;
                }
            }
            
            return [
                'quota_available' => null,
                'quota_used' => $totalSize,
                'quota_total' => null,
                'supported' => true,
                'message' => "通过文件列表估算使用空间 ({$fileCount} 个文件)",
                'method' => 'estimate'
            ];
            
        } catch (Exception $e) {
            return [
                'quota_available' => null,
                'quota_used' => null,
                'quota_total' => null,
                'supported' => false,
                'message' => '文件列表估算失败: ' . $e->getMessage()
            ];
        }
    }
    
    private function executeStorageQuery($path, $propfindBody, $method) {
        $response = $this->makeRequest('PROPFIND', $path, $propfindBody, [
            'Depth: 0',
            'Content-Type: application/xml; charset=utf-8'
        ]);
        
        if ($response['code'] !== 207) {
            return [
                'quota_available' => null,
                'quota_used' => null,
                'quota_total' => null,
                'supported' => false,
                'message' => "方法 {$method} 不支持 (HTTP {$response['code']})"
            ];
        }
        
        $result = $this->parseStorageInfo($response['body']);
        $result['method'] = $method;
        return $result;
    }
    
    private function parseStorageInfo($xml) {
        try {
            $doc = new DOMDocument();
            @$doc->loadXML($xml);
            
            $xpath = new DOMXPath($doc);
            $xpath->registerNamespace('D', 'DAV:');
            $xpath->registerNamespace('A', 'http://apache.org/dav/props/');
            $xpath->registerNamespace('N', 'http://nginx.org/dav/props/');
            $xpath->registerNamespace('OC', 'http://owncloud.org/ns');
            
            // 尝试各种配额属性
            $quotaPatterns = [
                'available' => [
                    '//D:quota-available-bytes',
                    '//A:quota-available-bytes',
                    '//N:quota-available',
                    '//OC:quota-available-bytes'
                ],
                'used' => [
                    '//D:quota-used-bytes',
                    '//A:quota-used-bytes', 
                    '//N:quota-used',
                    '//OC:quota-used-bytes'
                ],
                'total' => [
                    '//D:quota-maximum-bytes',
                    '//D:quota-total-bytes',
                    '//A:quota-maximum-bytes',
                    '//OC:quota-total-bytes'
                ]
            ];
            
            $available = null;
            $used = null;
            $maximum = null;
            
            // 查找可用配额
            foreach ($quotaPatterns['available'] as $pattern) {
                $nodes = $xpath->query($pattern);
                if ($nodes->length > 0) {
                    $value = trim($nodes->item(0)->textContent);
                    if ($value !== '' && $value !== '-1' && is_numeric($value)) {
                        $available = (int)$value;
                        break;
                    }
                }
            }
            
            // 查找已用配额
            foreach ($quotaPatterns['used'] as $pattern) {
                $nodes = $xpath->query($pattern);
                if ($nodes->length > 0) {
                    $value = trim($nodes->item(0)->textContent);
                    if ($value !== '' && $value !== '-1' && is_numeric($value)) {
                        $used = (int)$value;
                        break;
                    }
                }
            }
            
            // 查找总配额
            foreach ($quotaPatterns['total'] as $pattern) {
                $nodes = $xpath->query($pattern);
                if ($nodes->length > 0) {
                    $value = trim($nodes->item(0)->textContent);
                    if ($value !== '' && $value !== '-1' && is_numeric($value)) {
                        $maximum = (int)$value;
                        break;
                    }
                }
            }
            
            // 如果没有找到最大配额，尝试从可用+已用计算
            if ($maximum === null && $available !== null && $used !== null && ($available > 0 || $used > 0)) {
                $maximum = $available + $used;
            }
            
            // 检查是否至少找到了一些有用的信息
            $hasValidData = ($available !== null && $available > 0) || 
                          ($used !== null && $used > 0) || 
                          ($maximum !== null && $maximum > 0);
            
            if (!$hasValidData) {
                // 尝试查找任何数值属性作为参考
                $allNumbers = $xpath->query('//*[text()[normalize-space(.) and . > 0]]');
                $foundNumbers = [];
                
                foreach ($allNumbers as $node) {
                    $value = trim($node->textContent);
                    if (is_numeric($value) && $value > 0) {
                        $foundNumbers[] = [
                            'name' => $node->nodeName,
                            'value' => $value,
                            'path' => $node->getNodePath()
                        ];
                    }
                }
                
                return [
                    'quota_available' => $available,
                    'quota_used' => $used,
                    'quota_total' => $maximum,
                    'supported' => false,
                    'message' => '未找到有效的配额信息，但服务器响应了PROPFIND请求',
                    'debug_numbers' => $foundNumbers,
                    'raw_xml' => $xml
                ];
            }
            
            return [
                'quota_available' => $available,
                'quota_used' => $used,
                'quota_total' => $maximum,
                'supported' => true,
                'message' => 'WebDAV配额信息获取成功'
            ];
            
        } catch (Exception $e) {
            return [
                'quota_available' => null,
                'quota_used' => null,
                'quota_total' => null,
                'supported' => false,
                'message' => '解析配额信息失败: ' . $e->getMessage(),
                'raw_xml' => $xml
            ];
        }
    }
    
    public function getFileUrl($path) {
        // 返回相对于basePath的完整WebDAV路径，用于中转下载
        $cleanPath = ltrim($path, '/');
        $cleanBasePath = rtrim($this->basePath, '/');
        
        if (!empty($cleanBasePath) && strpos($cleanPath, ltrim($cleanBasePath, '/')) === 0) {
            return '/' . $cleanPath;
        } else {
            return $cleanBasePath . '/' . $cleanPath;
        }
    }
}
?>
