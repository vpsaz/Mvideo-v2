<?php
$config_file = __DIR__ . '/config/config.php';
$conf = include($config_file);

header('Content-Type: application/json');

class DomainValidator {
    public static function validate() {
        if (!isset($_SERVER['HTTP_REFERER']) || empty($_SERVER['HTTP_REFERER'])) {
            return false;
        }
        
        $referer = parse_url($_SERVER['HTTP_REFERER']);
        $currentHost = $_SERVER['HTTP_HOST'];
        $refererHost = $referer['host'] ?? '';
        
        if (isset($referer['port'])) {
            $refererHost .= ':' . $referer['port'];
        }
        
        return $currentHost === $refererHost;
    }
    
    public static function getErrorResponse() {
        return [
            'code' => 403,
            'error' => '请求无效'
        ];
    }
}

if (!DomainValidator::validate()) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(DomainValidator::getErrorResponse(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

header('Access-Control-Allow-Origin: https://' . $_SERVER['HTTP_HOST']);

class M3U8Parser {
    private $config;
    private $maxRetries;
    private $retryDelay;
    
    public function __construct($config, $maxRetries = 3, $retryDelay = 1) {
        $this->config = $config;
        $this->maxRetries = $maxRetries;
        $this->retryDelay = $retryDelay;
    }
    
    public function parse($url) {
        if (empty($this->config['baiapi_key'])) {
            return ['code' => 401, 'error' => 'API密钥未配置'];
        }

        $retryCount = 0;
        $validFile = false;
        $finalResponse = null;

        do {
            $response = $this->makeApiRequest($url);
            
            if ($response['success']) {
                $responseData = $response['data'];
                $finalResponse = $responseData;
                
                if (isset($responseData['file_url']) && !empty($responseData['file_url'])) {
                    if ($this->validateFileUrl($responseData['file_url'])) {
                        $validFile = true;
                        break;
                    } else {
                        error_log("文件URL无效: " . $responseData['file_url']);
                        $retryCount++;
                        
                        if ($retryCount < $this->maxRetries) {
                            sleep($this->retryDelay);
                        }
                    }
                } else {
                    $validFile = true;
                    break;
                }
            } else {
                error_log("API请求失败: " . $response['error']);
                $retryCount++;
                
                if ($retryCount < $this->maxRetries) {
                    sleep($this->retryDelay);
                }
            }
        } while ($retryCount < $this->maxRetries);

        return $this->formatFinalResponse($validFile, $finalResponse, $retryCount);
    }
    
    private function makeApiRequest($url) {
        $apiParams = [
            'url' => $url,
            'type' => 'json',
            'apikey' => $this->config['baiapi_key']
        ];

        $apiUrl = 'https://baiapi.cn/api/m3u8gl?' . http_build_query($apiParams);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response && $httpCode === 200) {
            return [
                'success' => true,
                'data' => json_decode($response, true)
            ];
        } else {
            return [
                'success' => false,
                'error' => "HTTP {$httpCode}: {$error}",
                'httpCode' => $httpCode
            ];
        }
    }
    
    private function validateFileUrl($fileUrl) {
        $ch = curl_init($fileUrl);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY => true,
            CURLOPT_HEADER => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return ($httpCode >= 200 && $httpCode < 300);
    }
    
    private function formatFinalResponse($validFile, $finalResponse, $retryCount) {
        if ($validFile && $finalResponse) {
            return $finalResponse;
        } else {
            $errorMsg = "经过 {$this->maxRetries} 次尝试后，无法获取有效的文件URL";
            if (isset($finalResponse['error'])) {
                $errorMsg .= " - 原始错误: " . $finalResponse['error'];
            }
            
            return [
                'code' => 500, 
                'error' => $errorMsg,
                'details' => [
                    'retry_count' => $retryCount,
                    'original_response' => $finalResponse
                ]
            ];
        }
    }
    
    public function setMaxRetries($maxRetries) {
        $this->maxRetries = $maxRetries;
    }
    
    public function setRetryDelay($retryDelay) {
        $this->retryDelay = $retryDelay;
    }
}

$url = $_GET['url'] ?? '';
if (empty($url)) {
    echo json_encode(['code' => 400, 'error' => '请求无效'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$parser = new M3U8Parser($conf, 3, 1);
$result = $parser->parse($url);

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>