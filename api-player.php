<?php
/**
 * @author    校长bloG <1213235865@qq.com>
 * @github    https://github.com/vpsaz/Mvideo-v2
 */

header("Content-Type: text/html; charset=utf-8");

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
}
elseif (!empty($_SERVER['HTTP_REFERER'])) {
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

$config_file = __DIR__ . '/config/config.php';
$conf = include($config_file);

$url = $_POST['url'] ?? $_GET['url'] ?? '';

if (empty($url)) {
    header('Content-type: application/json;charset=utf-8');
    echo json_encode(['code' => 404, 'msg' => "请输入URL"], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$decoded_url = urldecode($url);

if (!preg_match('/^https?:\/\//i', $decoded_url)) {
    header('Content-type: application/json;charset=utf-8');
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
    header('Content-type: application/json;charset=utf-8');
    echo json_encode(['code' => 404, 'msg' => "仅支持m3u8和mp4格式"], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$safe_url = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $conf['site_title']; ?> - 自定义</title>
    <meta name="description" content="<?= $conf['site_description'] ?>">
    <meta name="keywords" content="<?= $conf['site_keywords'] ?>">
    <link rel="shortcut icon" href="https://pic1.imgdb.cn/item/6812e03558cb8da5c8d5d3c3.png" type="image/x-icon">
    <script src="https://baiapi.cn/js-lib/Mvideo/hls.min.js"></script>
    <script src="https://baiapi.cn/js-lib/Mvideo/artplayer.min.js"></script>
    <script src="https://baiapi.cn/js-lib/Mvideo/artplayer-plugin-ads.min.js"></script>
    <style>
        html, body {
            width: 100%;
            height: 100%;
            margin: 0;
            padding: 0;
            overflow: hidden;
            background-color: #000;
        }
        
        #video {
            width: 100%;
            height: 100%;
            position: fixed;
            top: 0;
            left: 0;
        }
    </style>
</head>
<body>
    <div id="video"></div>
    <script>
        const originalUrl = '<?php echo $safe_url; ?>';
        
        function isM3U8Url(url) {
            const lowerUrl = url.toLowerCase();
            return lowerUrl.includes('.m3u8') || lowerUrl.includes('.m3u');
        }
        
        async function initVideoPlayer() {
            try {
                let finalUrl = originalUrl;
                const isM3U8 = isM3U8Url(originalUrl);
                
                console.log('原始URL:', originalUrl);
                console.log('是否为m3u8链接:', isM3U8);
                
                window.videoUrl = finalUrl;
                console.log('最终播放URL:', window.videoUrl);
                
                loadPlayerScript();
                
            } catch (error) {
                console.error('初始化播放器过程中出错:', error);
                window.videoUrl = originalUrl;
                loadPlayerScript();
            }
        }
        
        function loadPlayerScript() {
            const script = document.createElement('script');
            script.src = 'https://baiapi.cn/js-lib/XzPlayer/player.js';
            script.onload = function() {
                console.log('播放器脚本加载成功');
            };
            script.onerror = function() {
                console.error('播放器脚本加载失败');
                alert('播放器加载失败，请刷新页面重试');
            };
            document.body.appendChild(script);
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            initVideoPlayer();
        });
        
        if (document.readyState === 'complete' || document.readyState === 'interactive') {
            setTimeout(initVideoPlayer, 100);
        }
    </script>
</body>
</html>