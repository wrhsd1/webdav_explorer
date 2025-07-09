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
                    'path' => rtrim($currentPath, '/') . '/' . $itemName . ($isCollection ? '/' : ''),
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
