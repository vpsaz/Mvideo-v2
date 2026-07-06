<?php
/**
 * @author    校长bloG <1213235865@qq.com>
 * @github    https://github.com/vpsaz/Mvideo-v2
 */

$config_file = __DIR__ . '/config/config.php';
$conf = include($config_file);

header('Content-Type: application/json; charset=utf-8');

$currentDomain = $_SERVER['HTTP_HOST'] ?? '';
$isAllowed = false;

if (!empty($_SERVER['HTTP_ORIGIN'])) {
    $origin = parse_url($_SERVER['HTTP_ORIGIN'], PHP_URL_HOST);
    if ($origin === $currentDomain) {
        $isAllowed = true;
        header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
        header('Access-Control-Allow-Methods: POST, GET');
        header('Access-Control-Allow-Credentials: true');
    }
} elseif (!empty($_SERVER['HTTP_REFERER'])) {
    $refererHost = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
    if ($refererHost === $currentDomain) {
        $isAllowed = true;
    }
}

if (!$isAllowed) {
    $redirectUrl = './'; 
    header("Location: {$redirectUrl}");
    exit;
}

$url = $_POST['url'] ?? $_GET['url'] ?? '';

if (empty($url)) {
    echo json_encode(['code' => 404, 'msg' => "请输入URL"], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$decoded_url = urldecode($url);

if (!preg_match('/^https?:\/\//i', $decoded_url)) {
    echo json_encode(['code' => 404, 'msg' => "请输入正确的URL"], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$video_extensions = ['m3u8', 'mp4', 'm3u'];
$path = parse_url($decoded_url, PHP_URL_PATH);
$has_video_extension = false;

if ($path) {
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $has_video_extension = in_array($extension, $video_extensions);
}

if (!$has_video_extension) {
    echo json_encode(['code' => 404, 'msg' => "仅支持m3u8和mp4格式"], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$safe_url = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');

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

        $apiUrl = 'https://baiapi.cn/api/m3u8af?' . http_build_query($apiParams);

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

$parser = new M3U8Parser($conf, 3, 1);
$result = $parser->parse($decoded_url);

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>