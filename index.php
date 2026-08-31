<?php
/**
 * @author    校长bloG <1213235865@qq.com>
 * @github    https://github.com/vpsaz/Mvideo-v2
 */

$u=strtolower($_SERVER['HTTP_USER_AGENT']??'');
if(strpos($u,'micromessenger')!==false||(strpos($u,'qq/')!==false&&!strpos($u,'mqqbrowser/'))){
    header('Location:https://feishu.doubao.com/docx/MDCAdmHDDoyC6DxVwqocj80Anpc');exit;
}

header('Content-Type: text/html; charset=utf-8');
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => !empty($_SERVER['HTTPS'])
]);
session_start(); 
$config_file = __DIR__ . '/config/config.php';
$conf = include($config_file);
$source_count = isset($conf['source_count']) ? intval($conf['source_count']) : 1;
$apiKeyConfigured = !empty($conf['baiapi_key']);

function requiresPassword($className) {
    global $conf;
    $protectedKeywords = $conf['protected_keywords'] ?? [];
    
    if (empty($className)) {
        return false;
    }
    
    $className = strtolower($className);
    
    foreach ($protectedKeywords as $keyword) {
        if (strpos($className, strtolower($keyword)) !== false) {
            return true;
        }
    }
    return false;
}

if (!isset($_SESSION['global_password_verified'])) {
    $_SESSION['global_password_verified'] = false;
}

if (isset($_POST['video_password'])) {
    $inputPassword = trim($_POST['video_password']);
    $correctPassword = $conf['video_password'] ?? '';
    
    if ($inputPassword === $correctPassword) {
        $_SESSION['global_password_verified'] = true;
        $_SESSION['password_verified_time'] = time();
        
        if (isset($_POST['video_id']) && isset($_POST['source'])) {
            $redirectUrl = "?id=" . $_POST['video_id'] . "&y=" . $_POST['source'];
            header("Location: $redirectUrl");
            exit;
        } else {
            header("Location: ?");
            exit;
        }
    } else {
        $passwordError = "密码错误！";
    }
}

$passwordVerified = isset($_SESSION['global_password_verified']) && $_SESSION['global_password_verified'] === true;

$verificationTimeoutHours = intval($conf['verification_timeout'] ?? '2');
$verificationTimeout = $verificationTimeoutHours * 60 * 60;

if ($passwordVerified && isset($_SESSION['password_verified_time'])) {
    if (time() - $_SESSION['password_verified_time'] > $verificationTimeout) {
        $_SESSION['global_password_verified'] = false;
        $passwordVerified = false;
        error_log("验证状态已过期，配置超时时间: {$verificationTimeoutHours}小时");
    }
}

if (isset($_GET['logout'])) {
    $_SESSION['global_password_verified'] = false;
    unset($_SESSION['password_verified_time']);
    header("Location: ?");
    exit;
}

function getInitialTheme()
{
    if (isset($_COOKIE['theme_preference'])) {
        return $_COOKIE['theme_preference'] === 'dark' ? 'dark' : 'light';
    }
    return 'dark';
}

$initialTheme = getInitialTheme();
$showPasswordModal = false;
$results = [];
$details = [];
$searchTerm = $_GET['wd'] ?? '';
$source = $_GET['y'] ?? '1';
$source = preg_match('/^\d+$/', $source) ? $source : '1';
$selectedId = $_GET['id'] ?? '';
$isHistoryPage = isset($_GET['page']) && $_GET['page'] === 'history';

if (!empty($searchTerm)) {
    $results = searchMovies($searchTerm, $source);
}

if (!empty($selectedId)) {
	    $details = getMovieDetails($selectedId, $source);
	    
	    if (!empty($details)) {
	        $className = $details['class'] ?? $details['type_name'] ?? '';
	        
	        if (requiresPassword($className) && !$passwordVerified) {
	            $showPasswordModal = true;
	        }

	        echo "<script>window.currentVideoInfo = {
    vod_id: " . json_encode($selectedId) . ",
    vod_name: " . json_encode($details['name'] ?? '') . ",
    vod_pic: " . json_encode($details['pic'] ?? '') . ",
    source: " . json_encode($source) . ",
    watch_time: Date.now()
};
window.episodesFirstTitle = " . json_encode(!empty($details['play_url']) ? ($details['play_url'][0]['title'] ?? '第01集') : '') . ";</script>";
	    }
	}

function searchMovies($wd, $y)
{
    $url = "https://baiapi.cn/api/ysss/?wd=" . urlencode($wd) . "&y=" . $y;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($response && $httpCode === 200) {
        $data = json_decode($response, true);
        return $data['list'] ?? [];
    }
    return [];
}

function getMovieDetails($id, $y)
{
    $url = "https://baiapi.cn/api/ysss/?id=" . $id . "&y=" . $y;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($response && $httpCode === 200) {
        return json_decode($response, true);
    }
    return [];
}

function formatDate($dateString)
{
    if (!$dateString) return '未知日期';
    $date = strtotime($dateString);
    if ($date === false) return $dateString;
    return date('Y-m-d', $date);
}
?>
<!DOCTYPE html>
<html lang="zh-CN" data-theme="<?php echo $initialTheme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $conf['site_title'] ?></title>
    <meta name="description" content="<?= $conf['site_description'] ?>">
    <meta name="keywords" content="<?= $conf['site_keywords'] ?>">
    <link rel="shortcut icon" href="https://pic1.imgdb.cn/item/6812e03558cb8da5c8d5d3c3.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://baiapi.cn/js-lib/Mvideo/hls.min.js"></script>
    <script src="https://baiapi.cn/js-lib/Mvideo/artplayer.min.js"></script>
    <script src="https://baiapi.cn/js-lib/Mvideo/artplayer-plugin-ads.min.js"></script>
    <script src="https://baiapi.cn/js-lib/Mvideo_v2/player.js"></script>
    <script>
        (function () {
            try {
                if (localStorage.getItem('video_sidebar_collapsed') === '1' && window.innerWidth > 1024) {
                    document.documentElement.setAttribute('data-sidebar-collapsed', '1');
                }
            } catch (e) {}
        })();
    </script>
    <style>
        :root {
            --bg-color: #f5f7fa;
            --text-color: #2d3748;
            --card-bg: #ffffff;
            --border-color: #e2e8f0;
            --accent-color: <?= $conf['m_accent_color'] ?>;
            --accent-hover: <?= $conf['m_accent_hover'] ?>;
            --accent-text: <?= $conf['m_accent_text'] ?? '#fff' ?>;
            --secondary-color: #718096;
            --hover-color: #edf2f7;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --success-color: #38a169;
            --warning-color: #d69e2e;
        }
        
        [data-theme="dark"] {
            --bg-color: #121212;
            --text-color: #e0e0e0;
            --card-bg: #1e1e1e;
            --border-color: #333333;
            --accent-color: <?= $conf['a_accent_color'] ?>;
            --accent-hover: <?= $conf['a_accent_hover'] ?>;
            --accent-text: <?= $conf['a_accent_text'] ?? '#fff' ?>;
            --secondary-color: #aaaaaa;
            --hover-color: #2a2a2a;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3);
            --success-color: #2ecc71;
            --warning-color: #f39c12;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            transition: background-color 0.3s, color 0.3s, border-color 0.3s;
            scrollbar-width: thin;
            scrollbar-color: var(--border-color) transparent;
        }
        
        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            font-family: 'Segoe UI', 'PingFang SC', 'Microsoft YaHei', sans-serif;
            line-height: 1.6;
            background-image: url(), url(https://pic1.imgdb.cn/item/6812a7ae58cb8da5c8d5cbab.png);
            background-position: right bottom, left top;
            background-repeat: no-repeat, repeat;
            overflow-x: hidden;
        }

        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--border-color);
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--accent-color);
        }

        body.playing-mode {
            overflow: hidden;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 25px;
            display: flex;
            flex-direction: column;
        }

        .container.playing .footer {
            display: none;
        }

        .container.search-home .footer {
            display: none;
        }

        .container.search-home {
            min-height: calc(100vh - 110px);
            min-height: calc(100dvh - 110px);
            padding: 25px;
        }

        .search-section {
            background: transparent;
            border-radius: 0;
            padding: 0;
            box-shadow: none;
            margin-bottom: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            flex: 1;
        }

        .search-section.has-results {
            flex: none;
            background: var(--card-bg);
            border-radius: 12px;
            padding: 16px 20px;
            box-shadow: var(--shadow);
            margin-bottom: 16px;
            min-height: auto;
            align-items: stretch;
            justify-content: flex-start;
        }

        .search-logo {
            text-align: center;
            white-space: nowrap;
            margin-bottom: 20px;
        }

        .search-logo i {
            font-size: 48px;
            color: var(--accent-color);
            margin-bottom: 10px;
            display: block;
        }

        [data-theme="dark"] .search-logo i {
            filter: drop-shadow(0 4px 12px color-mix(in srgb, var(--accent-color) 30%, transparent));
        }

        .search-logo h1 {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-color);
            letter-spacing: -0.5px;
        }

        .search-section.has-results .search-logo {
            display: none;
        }

        .search-form {
            position: relative;
            display: flex;
            gap: 10px;
            flex-wrap: nowrap;
            align-items: stretch;
            width: 100%;
            max-width: 580px;
        }

        .search-section.has-results .search-form {
            max-width: 100%;
        }

        .search-input {
            flex: 1;
            min-width: 0;
            background: var(--card-bg);
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 0 20px;
            color: var(--text-color);
            font-size: 16px;
            height: 48px;
            box-sizing: border-box;
            line-height: 48px;
            min-height: 48px;
            transition: border-color 0.25s, box-shadow 0.25s;
        }

        .search-section.has-results .search-input {
            height: 42px;
            line-height: 42px;
            min-height: 42px;
            padding: 0 15px;
            background: var(--bg-color);
        }

        .search-input:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent-color) 12%, transparent);
        }

        [data-theme="dark"] .search-input:focus {
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent-color) 15%, transparent);
        }

        .search-section.has-results .search-input:focus {
            box-shadow: none;
        }

        .source-select {
            background-color: var(--card-bg);
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 0 40px 0 18px;
            color: var(--text-color);
            font-size: 15px;
            height: 48px;
            box-sizing: border-box;
            min-width: 110px;
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23718096' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 14px center;
            background-size: 14px;
            cursor: pointer;
            line-height: 48px;
            transition: border-color 0.25s;
        }

        .search-section.has-results .source-select {
            height: 42px;
            line-height: 42px;
            padding: 0 35px 0 15px;
            background-color: var(--bg-color);
            background-position: right 12px center;
        }

        [data-theme="dark"] .source-select {
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23aaaaaa' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
        }

        .source-select:focus {
            outline: none;
            border-color: var(--accent-color);
        }

        .search-btn {
            background: var(--accent-color);
            border: none;
            border-radius: 12px;
            padding: 0 24px;
            color: var(--accent-text);
            font-size: 15px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: background 0.2s, transform 0.15s;
            height: 48px;
            box-sizing: border-box;
            white-space: nowrap;
            min-width: 90px;
            flex-shrink: 0;
        }

        .search-section.has-results .search-btn {
            height: 42px;
            min-width: 70px;
            padding: 0 18px;
        }

        .search-btn:hover {
            background: var(--accent-hover);
            transform: translateY(-1px);
        }

        .search-btn:active {
            transform: translateY(0);
        }

        .search-section.has-results .search-btn:hover {
            transform: none;
        }

        @media (max-width: 600px) {
            .search-logo i {
                font-size: 44px;
            }

            .search-logo h1 {
                font-size: 22px;
            }

            .search-form {
                flex-wrap: wrap;
            }

            .search-input {
                flex: 1 0 100%;
            }

            .source-select {
                flex: 1;
                min-width: auto;
            }

            .search-btn {
                flex-shrink: 0;
            }
        }

        @media (max-width: 480px) {
            .search-form {
                flex-direction: column;
                gap: 10px;
            }

            .search-input,
            .source-select,
            .search-btn {
                width: 100%;
                min-width: auto;
                margin-bottom: 0;
            }

            .source-select {
                flex: none;
            }
        }
        
        .results-section {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--shadow);
            flex: 1;
        }

        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border-color);
        }

        .results-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-color);
        }

        .results-count {
            color: var(--secondary-color);
            font-size: 13px;
        }

        .results-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .result-item {
            background: var(--bg-color);
            border-radius: 12px;
            padding: 14px 16px;
            cursor: pointer;
            transition: all 0.2s ease;
            border-left: 3px solid transparent;
        }

        .result-item:hover {
            background: var(--hover-color);
            transform: translateX(4px);
            border-left-color: var(--accent-color);
        }

        .result-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .result-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 6px;
        }

        .result-meta {
            display: flex;
            gap: 14px;
            color: var(--secondary-color);
            font-size: 13px;
            flex-wrap: wrap;
        }

        .result-meta span {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .result-meta span i {
            font-size: 12px;
            opacity: 0.7;
        }

        .result-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .result-tags {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .tag {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 4px;
            padding: 4px 8px;
            font-size: 12px;
            color: var(--secondary-color);
        }

        .play-btn {
            background: var(--accent-color);
            color: var(--accent-text);
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: background 0.2s;
            text-decoration: none;
        }

        .play-btn:hover {
            background: var(--accent-hover);
        }

        .player-row {
            position: fixed;
            top: 61px;
            right: 0;
            bottom: 0;
            left: 0;
            overflow: hidden;
            z-index: 100;
            background: var(--bg-color);
        }

        .sidebar-toggle {
            position: absolute;
            top: 50%;
            right: 360px;
            z-index: 99999;
            background: var(--card-bg);
            border-radius: 20px 0 0 20px;
            height: 46px;
            line-height: 46px;
            width: 24px;
            color: var(--text-color);
            padding-left: 6px;
            margin-top: -23px;
            cursor: pointer;
            transition: right 0.25s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sidebar-toggle:hover {
            color: var(--accent-color);
        }

        [data-sidebar-collapsed] .player-row .sidebar-toggle {
            right: 0;
        }

        [data-sidebar-collapsed] .player-row .video-container {
            margin-right: 0;
        }

        [data-sidebar-collapsed] .player-row .episodes-section {
            transform: translateX(100%);
            visibility: hidden;
            pointer-events: none;
        }

        .video-container {
            background: #000;
            position: relative;
            box-sizing: border-box;
            height: 100%;
            margin-right: 360px;
            transition: margin-right 0.25s ease;
        }

        .video-player {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .video-player video {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        
        .episodes-section {
            height: 100%;
            position: absolute;
            top: 0;
            right: 0;
            width: 360px;
            background: var(--card-bg);
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            overflow-x: hidden;
            padding-right: 0;
            transition: transform 0.25s ease, visibility 0s;
        }

        .episodes-section::-webkit-scrollbar {
            width: 6px;
            right: 0;
        }

        .episodes-section::-webkit-scrollbar-track {
            background: transparent;
            margin-right: 0;
        }

        .episodes-section::-webkit-scrollbar-thumb {
            background: var(--border-color);
            border-radius: 3px;
        }

        .episodes-section::-webkit-scrollbar-thumb:hover {
            background: var(--accent-color);
        }

        .sidebar-header {
            padding: 15px 20px 15px 20px;
            border-bottom: 1px solid var(--border-color);
            flex-shrink: 0;
        }

        .sidebar-title {
            display: flex;
            align-items: baseline;
            font-size: 18px;
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 8px;
            line-height: 1.4;
            padding-right: 6px;
            min-width: 0;
        }

        .sidebar-title .title-text {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            flex-shrink: 1;
            min-width: 0;
        }

        .sidebar-title .episode-tag {
            flex-shrink: 0;
            white-space: nowrap;
        }

        .sidebar-desc-wrapper {
            position: relative;
            margin-top: 10px;
        }

        .sidebar-desc {
            font-size: 13px;
            color: var(--secondary-color);
            line-height: 1.6;
            padding-right: 6px;
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 3;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .sidebar-desc.expanded {
            -webkit-line-clamp: unset;
        }

        .desc-toggle-btn {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            padding: 4px 8px;
            margin-top: 6px;
            background: var(--hover-color);
            border: none;
            border-radius: 4px;
            color: var(--secondary-color);
            font-size: 11px;
            opacity: 0.7;
            cursor: pointer;
            transition: all 0.2s;
        }

        .desc-toggle-btn:hover {
            opacity: 1;
            color: var(--accent-color);
            background: var(--hover-color);
        }

        .desc-toggle-btn i {
            font-size: 9px;
            transition: transform 0.3s;
        }

        .desc-toggle-btn.expanded i {
            transform: rotate(180deg);
        }

        .episodes-header {
            padding: 16px 20px 6px;
            flex-shrink: 0;
        }

        .section-title {
            font-size: 12px;
            font-weight: 600;
            color: var(--secondary-color);
            letter-spacing: 1.5px;
            text-transform: uppercase;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
        }

        .section-title span {
            font-size: 12px;
            color: var(--secondary-color);
            font-weight: 400;
            letter-spacing: 0;
            text-transform: none;
        }

        .episodes-container {
            padding: 15px 20px;
            flex: 1;
        }

        .episodes-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
            gap: 8px;
            scrollbar-width: thin;
            scrollbar-color: var(--border-color) transparent;
        }

        .episode-item {
            background: var(--bg-color);
            border-radius: 6px;
            padding: 12px 10px;
            cursor: pointer;
            transition: all 0.2s ease;
            text-align: center;
            border: none;
            position: relative;
            overflow: hidden;
        }

        .episode-item:hover {
            background: var(--hover-color);
        }

        @keyframes pulse-glow {
            0%, 100% { box-shadow: 0 0 0 0 color-mix(in srgb, var(--accent-color) 20%, transparent); }
            50% { box-shadow: 0 0 0 4px color-mix(in srgb, var(--accent-color) 0%, transparent); }
        }

        .episode-item.active {
            background: color-mix(in srgb, var(--accent-color) 10%, var(--bg-color));
            animation: pulse-glow 2s ease-in-out infinite;
        }

        .episode-item.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            height: 24px;
            width: 3px;
            background: var(--accent-color);
            border-radius: 0 2px 2px 0;
            transform: translateY(-50%);
            animation: bar-play 1.2s ease-in-out infinite;
        }

        @keyframes bar-play {
            0%, 100% { height: 14px; }
            50% { height: 28px; }
        }

        .episode-title {
            font-size: 13px;
            color: var(--text-color);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            transition: color 0.2s;
        }

        .episode-item.active .episode-title {
            color: var(--accent-color);
            font-weight: 600;
        }
        
        .episode-meta {
            font-size: 13px;
            color: var(--secondary-color);
            display: flex;
            justify-content: space-between;
        }
        
        .video-info {
            background: var(--card-bg);
            border-radius: 8px;
            padding: 20px;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
        }
        
        .footer {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--shadow);
            margin-top: 20px;
        }
        
        .footer-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .footer-content {
            display: flex;
            flex-direction: column;
            gap: 20px;
            padding: 5px 0;
        }
        
        .footer-info {
            text-align: center;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .footer-brand {
            font-size: 24px;
            margin: 0;
        }
        
        .footer-brand a {
            color: var(--text-color);
            text-decoration: none;
        }
        
        .brand-accent {
            color: var(--accent-color);
        }
        
        .footer-desc {
            color: var(--secondary-color);
            font-size: 15px;
            margin: 5px 0;
        }
        
        .footer-desc a {
            color: var(--secondary-color);
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .footer-desc a:hover {
            color: var(--accent-color);
        }
        
        .footer-stats {
            color: var(--secondary-color);
            font-size: 12px;
        }
        
        .footer-contact {
            margin-top: 8px !important;
        }
        
        .footer-link {
            color: var(--accent-color);
            text-decoration: none;
            transition: opacity 0.3s ease;
        }
        
        .footer-link:hover {
            opacity: 0.8;
            text-decoration: underline;
        }
        
        .footer-logo {
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .logo-image {
            width: 96px;
            height: 96px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid var(--border-color);
        }
        
        @media (min-width: 768px) {
            .footer-content {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
                gap: 40px;
            }
        
            .footer-info {
                text-align: left;
                flex: 1;
                gap: 5px;
            }
        
            .footer-logo {
                width: 96px;
                flex-shrink: 0;
            }
        }
        
        @media (max-width: 767px) {
            .footer-content {
                padding: 10px 0;
            }
        
            .footer-info {
                gap: 5px;
            }
        
            .footer-brand {
                font-size: 30px;
            }
        
            .footer-logo {
                display: none;
            }
        }
        
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: var(--secondary-color);
        }
        
        .empty-state i {
            font-size: 40px;
            margin-bottom: 12px;
            color: var(--border-color);
            display: block;
        }
        
        .empty-state p {
            font-size: 15px;
        }
        
        .loading-spinner {
            display: none;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 60px;
            height: 60px;
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: var(--accent-color);
            animation: spin 1s ease-in-out infinite;
            z-index: 10;
        }
        
        @keyframes spin {
            to { transform: translate(-50%, -50%) rotate(360deg); }
        }
        
        .error-message {
            display: none;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            max-width: 80%;
            z-index: 10;
        }
        
        .error-message h3 {
            color: var(--accent-color);
            margin-bottom: 10px;
        }
        
        .error-message p {
            margin-bottom: 15px;
        }
        
        .retry-btn {
            background: var(--accent-color);
            color: var(--accent-text);
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: background 0.2s;
        }

        .retry-btn:hover {
            background: var(--accent-hover);
        }

        .password-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 99999;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .password-container {
            background: var(--card-bg);
            padding: 20px;
            border-radius: 12px;
            box-shadow: var(--shadow);
            text-align: center;
            max-width: 400px;
            width: 90%;
            border: 1px solid var(--border-color);
            position: relative;
        }

        .password-icon {
            font-size: 48px;
            color: var(--accent-color);
            margin-bottom: 20px;
        }

        .password-title {
            font-size: 24px;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .password-description {
            color: var(--secondary-color);
            margin-bottom: 25px;
            font-size: 16px;
        }

        .password-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .password-input {
            padding: 12px 15px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 16px;
            background: var(--bg-color);
            color: var(--text-color);
            transition: border-color 0.3s;
        }

        .password-input:focus {
            outline: none;
            border-color: var(--accent-color);
        }

        .password-submit {
            background: var(--accent-color);
            color: var(--accent-text);
            border: none;
            padding: 12px;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.2s;
            font-weight: 500;
        }

        .password-submit:hover {
            background: var(--accent-hover);
        }

        .password-error {
            color: var(--accent-color);
            margin-top: 10px;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        .error-page {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: calc(100vh - 120px);
            min-height: calc(100dvh - 120px);
            padding: 40px 20px;
        }

        .error-content {
            text-align: center;
            max-width: 480px;
            background: var(--card-bg);
            border-radius: 16px;
            padding: 40px 32px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
        }

        .error-content i.error-icon {
            font-size: 48px;
            color: var(--accent-color);
            margin-bottom: 16px;
            display: block;
        }

        .error-content h2 {
            font-size: 20px;
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 12px;
        }

        .error-content p {
            color: var(--secondary-color);
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 24px;
        }

        .error-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
        }

        .retry-link,
        .home-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
            text-decoration: none;
        }

        .retry-link {
            background: var(--accent-color);
            color: var(--accent-text);
        }

        .retry-link:hover {
            background: var(--accent-hover);
        }

        .home-link {
            background: var(--bg-color);
            color: var(--text-color);
            border: none;
        }

        .home-link:hover {
            background: var(--hover-color);
        }

        @media (max-width: 480px) {
            .error-content {
                padding: 30px 20px;
            }

            .error-actions {
                flex-direction: column;
            }

            .retry-link,
            .home-link {
                justify-content: center;
            }
        }

        @media (max-width: 1024px) {
            .container.playing {
                padding: 0;
                max-width: 100%;
            }

            body.playing-mode {
                overflow: auto;
            }

            .sidebar-toggle {
                display: none;
            }

            [data-sidebar-collapsed] .player-row .episodes-section {
                transform: none;
                visibility: visible;
                pointer-events: auto;
            }

            [data-sidebar-collapsed] .player-row .video-container {
                margin-right: 0;
            }

            .player-row {
                position: relative;
                top: 0;
                height: auto;
                display: flex;
                flex-direction: column;
                min-height: calc(100dvh - 61px);
                background: var(--bg-color);
            }

            .video-container {
                margin-right: 0;
                position: relative;
                aspect-ratio: 16 / 9;
                height: auto;
                background: #000;
                border-radius: 0;
                flex-shrink: 0;
            }

            .video-player {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
            }

            .episodes-section {
                position: relative;
                top: 0;
                right: 0;
                width: 100%;
                flex: 1;
                border-radius: 0;
            }

            .sidebar-header {
                padding: 15px;
            }

            .episodes-header {
                padding: 16px 15px 6px;
            }

            .episodes-container {
                padding: 15px;
                display: flex;
                flex-direction: column;
            }

            .episodes-list {
                flex: 1;
                align-content: flex-start;
            }
        }

        .main-nav {
            background: var(--card-bg);
            border-bottom: 1px solid var(--border-color);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .nav-container {
            width: 100%;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 60px;
        }
        
        .nav-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            font-size: 24px;
            font-weight: 600;
            color: var(--text-color);
        }
        
        .nav-brand b {
            font-weight: 700;
        }
        
        .nav-brand i {
            font-size: 20px;
        }
        
        .nav-menu {
            display: flex;
            align-items: center;
            gap: 30px;
            margin-left: auto;
            margin-right: 30px;
        }
        
        .nav-link {
            color: var(--text-color);
            text-decoration: none;
            font-size: 14px;
            padding: 8px 0;
            position: relative;
        }
        
        .nav-link:hover {
            color: var(--accent-color);
        }
        
        .nav-link.active {
            color: var(--accent-color);
        }

        .nav-link svg.nav-icon {
            width: 18px;
            height: 18px;
            vertical-align: middle;
            margin-top: -2px;
        }
        
        .nav-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .theme-toggle-nav,
        .mobile-menu-btn {
            background: var(--bg-color);
            border: none;
            border-radius: 6px;
            width: 36px;
            height: 36px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-color);
            transition: all 0.2s;
        }
        
        .theme-toggle-nav:hover,
        .mobile-menu-btn:hover {
            background: var(--hover-color);
        }
        
        .mobile-menu-btn {
            display: none;
        }
        
        @media (max-width: 768px) {
            .search-form {
                max-width: 100%;
            }

            .source-select {
                min-width: 100px;
            }

            .container {
                padding: 15px;
            }

            .container.playing {
                padding: 0;
            }

            .sidebar-title {
                font-size: 16px;
            }

            .sidebar-desc {
                font-size: 12px;
            }

            .episodes-list {
                grid-template-columns: repeat(auto-fill, minmax(70px, 1fr));
                gap: 6px;
            }

            .episode-item {
                padding: 11px 6px;
            }

            .episode-title {
                font-size: 12px;
            }

            .mobile-menu-btn {
                display: flex;
            }

            .nav-menu {
                position: fixed;
                top: 60px;
                left: 0;
                right: 0;
                background: var(--card-bg);
                flex-direction: column;
                padding: 0;
                border-top: 1px solid var(--border-color);
                transform: translateY(-100%);
                opacity: 0;
                visibility: hidden;
                transition: transform 0.3s ease, opacity 0.3s ease;
                gap: 0;
                margin: 0;
                pointer-events: none;
                z-index: 999;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            }

            .nav-menu.active {
                transform: translateY(0);
                opacity: 1;
                visibility: visible;
                pointer-events: auto;
            }

            .nav-link {
                padding: 15px 20px;
                border-bottom: 1px solid var(--border-color);
                width: 100%;
                display: flex;
                align-items: center;
                gap: 12px;
                font-size: 15px;
                transition: all 0.2s ease;
                background: var(--card-bg);
            }

            .nav-link:first-child {
                border-top: none;
            }

            .nav-link:hover {
                background: var(--hover-color);
                color: var(--accent-color);
                padding-left: 25px;
            }

            .nav-link.active {
                background: var(--hover-color);
                color: var(--accent-color);
                border-left: 3px solid var(--accent-color);
            }

            .nav-link i,
            .nav-link svg.nav-icon {
                width: 18px;
                height: 18px;
                vertical-align: middle;
                color: var(--secondary-color);
                transition: color 0.2s;
            }

            .nav-link:hover i,
            .nav-link:hover svg.nav-icon,
            .nav-link.active i,
            .nav-link.active svg.nav-icon {
                color: var(--accent-color);
            }

            .nav-menu:not(.active) {
                display: none;
            }

            .history-header {
                margin-bottom: 12px;
            }

            .history-header-right {
                gap: 8px;
            }

            .history-count {
                display: none;
            }

            .history-item {
                padding: 10px 12px;
                gap: 12px;
            }

            .history-item-poster {
                width: 60px;
                height: 80px;
                border-radius: 6px;
            }

            .history-item-name {
                font-size: 14px;
            }

            .history-item-episode {
                font-size: 12px;
            }
        }
        
        @media (min-width: 769px) {
            .mobile-menu-btn {
                display: none !important;
            }
        
            .nav-menu {
                display: flex !important;
            }
        }

        .history-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            padding: 0 4px;
        }

        .history-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-color);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .history-title i {
            color: var(--accent-color);
            font-size: 16px;
        }

        .history-header-right {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .history-count {
            color: var(--secondary-color);
            font-size: 12px;
        }

        .clear-history-btn {
            background: transparent;
            color: var(--secondary-color);
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            transition: none;
        }

        .clear-history-btn:hover {
            background: var(--hover-color);
            color: var(--accent-color);
        }

        [data-theme="dark"] .clear-history-btn:hover {
            color: var(--accent-color);
        }

        .history-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .history-item {
            display: flex;
            align-items: center;
            gap: 16px;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 12px 16px;
            cursor: pointer;
            transition: none;
            animation: historyFadeIn 0.35s ease both;
        }

        .history-item:hover {
            background: var(--hover-color);
            border-color: var(--accent-color);
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        }

        .history-item * {
            transition: none !important;
        }

        [data-theme="dark"] .history-item:hover {
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.2);
        }

        @keyframes historyFadeIn {
            from {
                opacity: 0;
                transform: translateY(12px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .history-item:nth-child(1)  { animation-delay: 0.00s; }
        .history-item:nth-child(2)  { animation-delay: 0.03s; }
        .history-item:nth-child(3)  { animation-delay: 0.06s; }
        .history-item:nth-child(4)  { animation-delay: 0.09s; }
        .history-item:nth-child(5)  { animation-delay: 0.12s; }
        .history-item:nth-child(6)  { animation-delay: 0.15s; }
        .history-item:nth-child(7)  { animation-delay: 0.18s; }
        .history-item:nth-child(8)  { animation-delay: 0.21s; }
        .history-item:nth-child(9)  { animation-delay: 0.24s; }
        .history-item:nth-child(10) { animation-delay: 0.27s; }
        .history-item:nth-child(n+11) { animation-delay: 0.30s; }

        .history-item-poster {
            width: 72px;
            height: 96px;
            border-radius: 8px;
            overflow: hidden;
            flex-shrink: 0;
            background: var(--bg-color);
            position: relative;
        }

        .history-item-poster img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: transform 0.3s ease;
        }

        .history-item:hover .history-item-poster img {
            transform: scale(1.05);
        }

        .history-item-source {
            position: absolute;
            bottom: 4px;
            left: 4px;
            background: rgba(0, 0, 0, 0.72);
            color: #fff;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 4px;
            line-height: 1.3;
            backdrop-filter: blur(4px);
        }

        .history-item-info {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .history-item-name {
            font-size: 15px;
            font-weight: 600;
            color: var(--text-color);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .history-item-episode {
            font-size: 13px;
            color: var(--secondary-color);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .history-item-episode i {
            color: var(--accent-color);
            font-size: 12px;
        }

        .history-item-episode span {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .history-item-time {
            font-size: 12px;
            color: var(--secondary-color);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .history-item-time i {
            font-size: 11px;
            opacity: 0.6;
        }

        .history-item-actions {
            display: flex;
            gap: 8px;
            flex-shrink: 0;
        }

        .history-action-btn {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            border: none;
            background: var(--bg-color);
            color: var(--secondary-color);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            transition: none;
        }

        .history-action-btn.play-btn:hover {
            background: var(--accent-color);
            color: var(--accent-text);
        }

        .history-action-btn.delete-btn:hover {
            background: var(--accent-color);
            color: var(--accent-text);
        }

        [data-theme="dark"] .history-action-btn.delete-btn:hover {
            background: var(--accent-color);
        }

        .history-empty {
            text-align: center;
            padding: 60px 20px;
            background: var(--card-bg);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            animation: historyFadeIn 0.35s ease both;
        }

        .history-empty-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--bg-color);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }

        .history-empty-icon i {
            font-size: 32px;
            color: var(--border-color);
        }

        .history-empty h3 {
            font-size: 18px;
            color: var(--text-color);
            margin-bottom: 8px;
            font-weight: 600;
        }

        .history-empty p {
            color: var(--secondary-color);
            font-size: 14px;
        }

        @media (max-width: 480px) {
            .history-item {
                flex-wrap: wrap;
                gap: 10px;
                padding: 10px;
            }

            .history-item-info {
                flex: 1;
                min-width: 0;
            }

            .history-item-actions {
                width: 100%;
                justify-content: flex-end;
                padding-top: 8px;
                border-top: 1px solid var(--border-color);
                margin-top: 2px;
            }
        }
    </style>
</head>
<body<?php echo (!empty($details) && !$isHistoryPage && !empty($selectedId)) ? ' class="playing-mode"' : ''; ?>>
    <?php if (!empty($showPasswordModal)): ?>
    <div class="password-modal">
        <div class="password-container">
            <div class="password-icon">
                <i class="fas fa-lock"></i>
            </div>
            <h2 class="password-title">需要密码验证</h2>
            <p class="password-description">此内容受密码保护，请输入观看密码</p>

            <div class="video-info" style="background: var(--bg-color); padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: left; border: 1px solid var(--border-color);">
                <div style="font-weight: bold; margin-bottom: 5px; font-size: 16px;"><?php echo htmlspecialchars($details['name'] ?? ''); ?></div>
                <div style="color: var(--secondary-color); font-size: 14px;">
                    分类信息: <span style="color: var(--accent-color);"><?php echo htmlspecialchars($className); ?></span><br>
                    验证状态: <span style="color: var(--accent-color);">全局验证</span><br>
                    <small style="font-size: 12px; color: var(--secondary-color);">输入一次密码后，可观看所有受保护内容</small>
                </div>
            </div>

            <form method="POST" class="password-form">
                <?php if (isset($selectedId) && isset($source)): ?>
                    <input type="hidden" name="video_id" value="<?php echo htmlspecialchars($selectedId, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="source" value="<?php echo htmlspecialchars($source, ENT_QUOTES, 'UTF-8'); ?>">
                <?php endif; ?>
                <input type="password" name="video_password" class="password-input" placeholder="请输入密码" required autofocus>
                <button type="submit" class="password-submit">验证密码</button>
            </form>

            <?php if (isset($passwordError)): ?>
                <div class="password-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $passwordError; ?>
                </div>
            <?php endif; ?>

            <div style="margin-top: 15px; font-size: 14px; color: var(--secondary-color);">
                <i class="fas fa-info-circle"></i> 验证成功后，<?= $conf['verification_timeout'] ?>小时内可观看所有受保护内容
            </div>
        </div>
    </div>
    <?php endif; ?>
    <div id="dailyNoticeModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
        <div style="background: var(--card-bg); border-radius: 12px; box-shadow: var(--shadow); max-width: 500px; width: 90%; padding: 25px; border: 1px solid var(--border-color); position: relative;">
            <h3 style="font-size: 20px; margin-bottom: 15px; color: var(--accent-color); display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-bullhorn"></i> 每日必看
            </h3>
            <div style="margin-bottom: 20px; line-height: 1.6; color: var(--text-color);"><?= $conf['disclaimers'] ?></div>
            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button id="closeNoticeBtn" style="background: var(--accent-color); color: var(--accent-text); border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; opacity: 0.5; pointer-events: none; transition: all 0.2s;"> 关闭 (<span id="countdown">5</span>s) </button>
            </div>
        </div>
    </div>
    <nav class="main-nav">
        <div class="nav-container">
            <a href="./" class="nav-brand">
                <b><span class="brand-accent">M</span>video</b>
            </a>
            <div class="nav-menu" id="navMenu">
                <a href="./" class="nav-link <?= !$isHistoryPage ? 'active' : '' ?>"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="nav-icon"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg> 首页</a>
                <a href="?page=history" class="nav-link <?= $isHistoryPage ? 'active' : '' ?>"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="nav-icon"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> 观看记录</a>
                <a href="javascript:void(0)" class="nav-link" id="customPlayBtn"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="nav-icon"><circle cx="12" cy="12" r="10"/><polygon points="10 8 16 12 10 16 10 8"/></svg> 自由播放</a> <?php if ($passwordVerified): ?> <a href="?logout=1" class="nav-link"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="nav-icon"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg> 退出验证</a> <?php endif; ?>
            </div>
            <div class="nav-actions">
                <button class="theme-toggle-nav" id="themeToggle">
                    <span id="themeIcon"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg></span>
                </button>
                <button class="mobile-menu-btn" id="mobileMenuBtn">
                    <span id="menuIcon"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg></span>
                </button>
            </div>
        </div>
    </nav>
    <div id="customPlayModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; padding: 20px;">
        <div style="background: var(--card-bg); border-radius: 12px; box-shadow: var(--shadow); max-width: 500px; width: 90%; padding: 25px; border: 1px solid var(--border-color); position: relative;">
            <button id="closeCustomModal"
                style="position: absolute; top: 15px; right: 15px; background: transparent; border: none; color: var(--text-color); font-size: 20px; cursor: pointer; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; transition: all 0.2s; border-radius: 6px; z-index: 10;">
                <i class="fas fa-times"></i>
            </button>
            <h3 style="font-size: 20px; margin-bottom: 20px; color: var(--text-color); display: flex; align-items: center; gap: 10px; padding-right: 40px;">
                自定义
            </h3>
            <div style="display: flex; gap: 10px; align-items: stretch; flex-wrap: wrap;">
                <input type="text" id="customPlayUrl" placeholder="请输入视频播放地址（支持m3u8/mp4等）" class="search-input" style="flex: 1;">
                <button id="submitCustomPlay" class="search-btn" style="flex-shrink: 0;">播放</button>
            </div>
        </div>
    </div>
    <div class="container <?php echo (!empty($details) && !$isHistoryPage && !empty($selectedId)) ? 'playing' : ''; ?> <?php echo (empty($selectedId) && !$isHistoryPage && empty($searchTerm)) ? 'search-home' : ''; ?>"> <?php if ($isHistoryPage): ?> <div class="history-section">
            <div class="history-header">
                <h3 class="history-title"><i class="fas fa-history"></i> 观看记录</h3>
                <div class="history-header-right">
                    <span class="history-count" id="historyCount">共 0 条记录</span>
                    <button class="clear-history-btn" id="clearHistoryBtn">
                        <i class="fas fa-trash-can"></i>
                        <span>清空记录</span>
                    </button>
                </div>
            </div>
            <div class="history-list" id="historyList">
                <div class="history-empty">
                    <div class="history-empty-icon">
                        <i class="fas fa-clock-rotate-left"></i>
                    </div>
                    <h3>暂无观看记录</h3>
                    <p>开始观看视频后将自动记录</p>
                </div>
            </div>
        </div> <?php elseif (empty($selectedId)): ?> <div class="search-section <?= !empty($searchTerm) ? 'has-results' : '' ?>">
            <div class="search-logo">
                <i class="fas fa-film"></i>
                <h1>搜索影片</h1>
            </div>
            <form method="GET" class="search-form">
                <input type="text" name="wd" class="search-input" value="<?= htmlspecialchars($searchTerm) ?>" placeholder="输入影片名称..." autofocus>
                <select class="source-select" name="y"> <?php for ($i = 1; $i <= $source_count; $i++): ?> <option value="<?= $i ?>" <?= ($source == (string)$i) ? 'selected' : '' ?>> 片源<?= $i ?> </option> <?php endfor; ?> </select>
                <button type="submit" class="search-btn">
                    <i class="fas fa-search"></i>
                    <span>搜索</span>
                </button>
            </form>
        </div>
        <?php if (!empty($searchTerm)): ?> <div class="results-section">
            <div class="results-header">
                <h3 class="results-title">搜索结果</h3>
                <div class="results-count"> <?php if (!empty($results)): ?> 找到 <?= count($results) ?> 部影片 <?php else: ?> 未找到相关影片 <?php endif; ?> </div>
            </div> <?php if ($passwordVerified): ?> <div style="background: var(--success-color); color: white; padding: 10px 15px; border-radius: 6px; margin-bottom: 15px;">
                <i class="fas fa-shield-alt"></i> 您已通过密码验证！
            </div> <?php endif; ?> <div class="results-list" id="resultsList"> <?php if (!empty($results)): ?> <?php foreach ($results as $item): ?> <a href="?id=<?= $item['vod_id'] ?>&y=<?= $source ?>" class="result-item-link" style="display: block; text-decoration: none; color: inherit;">
                    <div class="result-item">
                        <div class="result-header">
                            <div>
                                <h4 class="result-title"><?= htmlspecialchars($item['vod_name']) ?></h4>
                                <div class="result-meta">
                                    <span><i class="fas fa-tag"></i> <?= htmlspecialchars($item['type_name']) ?></span>
                                    <span><i class="fas fa-clock"></i> <?= formatDate($item['vod_time']) ?></span>
                                    <span><i class="fas fa-comment"></i> <?= htmlspecialchars($item['vod_remarks'] ?? '无备注') ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </a> <?php endforeach; ?> <?php else: ?> <div class="empty-state">
                    <i class="fas fa-film"></i>
                    <p>未找到相关影片</p>
                </div> <?php endif; ?> </div>
        </div> <?php endif; ?> <?php else: ?> <?php if (!empty($details) && !empty($details['play_url']) && empty($showPasswordModal)): ?> <div class="player-row">
            <div class="sidebar-toggle" id="sidebarToggle">
                <i class="fas fa-chevron-right"></i>
            </div>
            <div class="video-container">
                <div class="video-player">
                    <div id="player" style="width:100%;height:100%;"></div>
                    <div class="loading-spinner" id="loadingSpinner" style="display:none"></div>
                    <div class="error-message" id="errorMessage" style="display:none">
                        <h3><i class="fas fa-exclamation-circle"></i> 加载失败</h3>
                        <p id="errorDetails">视频加载失败，请检查网络或切换源。</p>
                        <button class="retry-btn" id="retryButton">重试</button>
                    </div>
                </div>
            </div>
            <div class="episodes-section">
                <div class="sidebar-header">
                    <h1 class="sidebar-title" id="videoTitle" data-original-title="<?= htmlspecialchars($details['name'] ?? '') ?>"><span class="title-text"><?= htmlspecialchars($details['name'] ?? '加载中...') ?></span></h1>
                    <div class="sidebar-desc-wrapper">
                        <div class="sidebar-desc" id="videoDescription"><?= htmlspecialchars($details['content'] ?? '正在获取视频信息...') ?></div>
                        <button class="desc-toggle-btn" id="descToggleBtn">
                            <span class="toggle-text">展开</span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                    </div>
                </div>
                <div class="episodes-header">
                    <h2 class="section-title"><span style="display: inline-flex; align-items: center; gap: 4px; line-height: 1;"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0; display: block;"><line x1="9" y1="6" x2="21" y2="6"/><line x1="9" y1="12" x2="21" y2="12"/><line x1="9" y1="18" x2="21" y2="18"/><circle cx="4" cy="6" r="1.5" fill="currentColor" stroke="none"/><circle cx="4" cy="12" r="1.5" fill="currentColor" stroke="none"/><circle cx="4" cy="18" r="1.5" fill="currentColor" stroke="none"/></svg>选集</span> <span id="episodesCount">共 <?= !empty($details['play_url']) ? count($details['play_url']) : 0 ?> 集</span></h2>
                </div>
                <div class="episodes-container">
                    <div class="episodes-list" id="episodesList"> <?php if (!empty($details['play_url'])): ?> <?php foreach ($details['play_url'] as $index => $episode): ?> <div class="episode-item">
                            <div class="episode-title"><?= htmlspecialchars($episode['title']) ?></div>
                        </div> <?php endforeach; ?> <?php else: ?> <div class="empty-state">
                            <i class="fas fa-film"></i>
                            <p>暂无播放列表</p>
                        </div> <?php endif; ?> </div>
                </div>
            </div>
        </div> <?php if (!empty($details['play_url'])): ?> <script>
            window.episodesData = <?= json_encode(array_map(function($index, $episode) {
                return [
                    'url' => $episode['link'] ?? '',
                    'title' => $episode['title'] ?? '',
                    'index' => $index
                ];
            }, array_keys($details['play_url']), $details['play_url']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?>;
            window.apiKeyConfigured = <?php echo $apiKeyConfigured ? 'true' : 'false'; ?>;
        </script>
        <script src="https://baiapi.cn/js-lib/Mvideo_v2/SharedViewing.js"></script> <?php endif; ?> <?php else: ?>
        <div class="error-page">
            <div class="error-content">
                <i class="fas fa-exclamation-triangle error-icon"></i>
                <h2>视频加载失败</h2>
                <p><?php
                    if (empty($details)) echo '无法获取视频信息，请检查网络或稍后重试';
                    elseif (empty($details['play_url'])) echo '该视频暂无可用播放源，请尝试切换片源或稍后再试';
                    else echo '视频数据异常，请刷新页面重试';
                ?></p>
                <div class="error-actions">
                    <a href="./" class="home-link"><i class="fas fa-home"></i> 返回首页</a>
                </div>
            </div>
        </div> <?php endif; ?> <?php endif; ?> <footer class="footer">
            <div class="footer-container">
                <div class="footer-content">
                    <div class="footer-info">
                        <h3 class="footer-brand">
                            <b><a href="./"><span class="brand-accent">M</span>video</a></b>
                        </h3>
                        <p class="footer-desc">
                            <a href="" title="让找片更简单，把时间留给精彩内容">
                                <b>让找片更简单，把时间留给精彩内容</b>
                            </a>
                        </p>
                        <div class="footer-stats">
                            <p>本站已稳定运行 <span id="y-day">0</span> 天</p>
                            <p class="footer-contact">Tencent 交流群：<a href="<?= $conf['qq_group'] ?>" class="footer-link" target="_blank"><?= $conf['qq_group_number'] ?></a></p>
                        </div>
                    </div>
                    <div class="footer-logo">
                        <img src="https://q2.qlogo.cn/headimg_dl?dst_uin=<?= $conf['qq'] ?>&amp;spec=640" alt="站长的帅气头像" class="logo-image">
                    </div>
                </div>
            </div>
        </footer>
    </div>
    <script>
        (function() {
            document.addEventListener('DOMContentLoaded', function() {
                const modal = document.getElementById('dailyNoticeModal');
                const closeBtn = document.getElementById('closeNoticeBtn');
                const countdownEl = document.getElementById('countdown');
                
                if (!modal || !closeBtn || !countdownEl) {
                    return;
                }
                
                function checkShowNotice() {
                    const today = new Date().toISOString().split('T')[0];
                    const closedDate = localStorage.getItem('dailyNoticeClosed');
                    
                    if (closedDate === today) return false;
                    
                    modal.style.display = 'flex';
                    return true;
                }
                
                function startCountdown() {
                    let seconds = 5;
                    countdownEl.textContent = seconds;
                    
                    const timer = setInterval(() => {
                        seconds--;
                        countdownEl.textContent = seconds;
                        
                        if (seconds <= 0) {
                            clearInterval(timer);
                            closeBtn.style.opacity = '1';
                            closeBtn.style.pointerEvents = 'auto';
                            closeBtn.innerHTML = '关闭';
                        }
                    }, 1000);
                }
                
                function closeNotice() {
                    const today = new Date().toISOString().split('T')[0];
                    localStorage.setItem('dailyNoticeClosed', today);
                    modal.style.display = 'none';
                }
                
                if (checkShowNotice()) {
                    startCountdown();
                }
                
                closeBtn.addEventListener('click', closeNotice);
            });
        })();
    </script>
    <script>
        const WatchHistory = {
            STORAGE_KEY: 'video_watch_history',
            MAX_RECORDS: 50,
        
            getAll() {
                try {
                    const history = localStorage.getItem(this.STORAGE_KEY);
                    return history ? JSON.parse(history) : [];
                } catch (error) {
                    return [];
                }
            },
        
            add(videoInfo) {
                try {
                    let history = this.getAll();
                    
                    const existingIndex = history.findIndex(item => item.vod_id === videoInfo.vod_id);
                    
                    if (existingIndex >= 0) {
                        history[existingIndex] = {
                            ...history[existingIndex],
                            ...videoInfo,
                            watch_time: Date.now()
                        };
                    } else {
                        history.unshift({
                            ...videoInfo,
                            watch_time: Date.now()
                        });
                    }
                    
                    history = history.slice(0, this.MAX_RECORDS);
                    localStorage.setItem(this.STORAGE_KEY, JSON.stringify(history));
                    return true;
                } catch (error) {
                    return false;
                }
            },
        
            remove(vodId) {
                try {
                    let history = this.getAll();
                    history = history.filter(item => item.vod_id !== vodId);
                    localStorage.setItem(this.STORAGE_KEY, JSON.stringify(history));
                    return true;
                } catch (error) {
                    return false;
                }
            },
        
            clear() {
                try {
                    localStorage.removeItem(this.STORAGE_KEY);
                    return true;
                } catch (error) {
                    return false;
                }
            },
        
            formatTime(timestamp) {
                const now = Date.now();
                const diff = now - timestamp;
                const minutes = Math.floor(diff / 60000);
                const hours = Math.floor(diff / 3600000);
                const days = Math.floor(diff / 86400000);
        
                if (minutes < 1) return '刚刚';
                if (minutes < 60) return `${minutes}分钟前`;
                if (hours < 24) return `${hours}小时前`;
                if (days < 7) return `${days}天前`;
        
                return new Date(timestamp).toLocaleDateString();
            },
        
            getVideoHistory(vodId) {
                const history = this.getAll();
                return history.find(item => item.vod_id === vodId);
            }
        };
        
        function restoreFromHistory() {
            if (!window.currentVideoInfo || !episodes || episodes.length === 0) {
                return null;
            }
            
            const videoHistory = WatchHistory.getVideoHistory(window.currentVideoInfo.vod_id);
            if (!videoHistory) {
                return null;
            }
            
            
            if (videoHistory.current_episode_index !== undefined && 
                videoHistory.current_episode_index >= 0 && 
                videoHistory.current_episode_index < episodes.length) {
                return videoHistory.current_episode_index;
            }
            
            if (videoHistory.current_episode) {
                const historyEpisodeIndex = episodes.findIndex(ep => 
                    ep.title === videoHistory.current_episode
                );
                if (historyEpisodeIndex >= 0) {
                    return historyEpisodeIndex;
                }
            }
            
            return null;
        }
        
        function renderWatchHistory() {
            const historyList = document.getElementById('historyList');
            const historyCount = document.getElementById('historyCount');

            if (!historyList) return;

            const history = WatchHistory.getAll();

            if (history.length === 0) {
                historyList.innerHTML = `<div class="history-empty">
                    <div class="history-empty-icon">
                        <i class="fas fa-clock-rotate-left"></i>
                    </div>
                    <h3>暂无观看记录</h3>
                    <p>开始观看视频后将自动记录</p>
                </div>`;
                historyCount.textContent = '共 0 条记录';
                return;
            }

            historyCount.textContent = `共 ${history.length} 条记录`;

            historyList.innerHTML = history.map((item, index) => {
                const picUrl = (item.vod_pic || 'https://pic1.imgdb.cn/item/6812e03558cb8da5c8d5d3c3.png').replace(/'/g, "%27");
                const safeName = item.vod_name ? item.vod_name.replace(/'/g, "\\'").replace(/"/g, '&quot;') : '';
                const vodIdEnc = encodeURIComponent(item.vod_id);
                const sourceEnc = encodeURIComponent(item.source);
                const animDelay = index < 10 ? (index * 0.03).toFixed(2) : '0.30';

                return `
                <div class="history-item" style="animation-delay:${animDelay}s" onclick="window.location.href='?id=${vodIdEnc}&y=${sourceEnc}'">
                    <div class="history-item-poster">
                        <img src="${picUrl}" alt="${safeName}" loading="lazy">
                        <span class="history-item-source">片源${item.source}</span>
                    </div>
                    <div class="history-item-info">
                        <div class="history-item-name" title="${safeName}">${item.vod_name || '未知视频'}</div>
                        <div class="history-item-episode">
                            <i class="fas fa-play-circle"></i>
                            <span>${item.current_episode || '未记录集数'}</span>
                        </div>
                        <div class="history-item-time">
                            <i class="far fa-clock"></i>
                            ${WatchHistory.formatTime(item.watch_time)}
                        </div>
                    </div>
                    <div class="history-item-actions">
                        <button class="history-action-btn play-btn" title="继续播放"
                            onclick="event.stopPropagation(); window.location.href='?id=${vodIdEnc}&y=${sourceEnc}'">
                            <i class="fas fa-play"></i>
                        </button>
                        <button class="history-action-btn delete-btn" title="删除记录"
                            onclick="event.stopPropagation(); removeHistoryItem('${vodIdEnc}')">
                            <i class="fas fa-trash-can"></i>
                        </button>
                    </div>
                </div>`;
            }).join('');
        }
        
        function removeHistoryItem(vodId) {
            if (confirm('确定要删除这条观看记录吗？')) {
                WatchHistory.remove(vodId);
                renderWatchHistory();
            }
        }
        
        let episodes = null;
        let art = null;
        let currentIndex = 0;
        let currentRequestController = null;
        let apiKeyConfigured = window.apiKeyConfigured || false;
        let switchGeneration = 0;
        let switchErrorHandler = null;
        let playerReady = false;
        
        function playM3u8(video, url, art) {
            if (window.Hls && Hls.isSupported()) {
                if (art.hls) art.hls.destroy();
                const hls = new Hls({
                    enableWorker: true,
                    lowLatencyMode: true,
                    backBufferLength: 90
                });
                hls.loadSource(url);
                hls.attachMedia(video);
                art.hls = hls;
        
                hls.on(Hls.Events.MANIFEST_PARSED, function() {
                    video.play().catch(e => {
                    });
                });
        
                hls.on(Hls.Events.ERROR, function(event, data) {
                    if (data.fatal) {
                        switch (data.type) {
                            case Hls.ErrorTypes.NETWORK_ERROR:
                                hls.startLoad();
                                break;
                            case Hls.ErrorTypes.MEDIA_ERROR:
                                hls.recoverMediaError();
                                break;
                            default:
                                art.notice.show = 'HLS播放错误';
                                hls.destroy();
                                break;
                        }
                    }
                });
        
                art.on('destroy', () => hls.destroy());
            } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
                video.src = url;
            } else {
                art.notice.show = '不支持的播放格式 m3u8';
            }
        }
        
        function showError(message) {
            if (playerReady && message.includes('初始化失败')) {
                return;
            }

            const loadingSpinner = document.getElementById('loadingSpinner');
            const errorMessage = document.getElementById('errorMessage');
            const errorDetails = document.getElementById('errorDetails');

            clearLoadTimeout();

            if (loadingSpinner) loadingSpinner.style.display = 'none';
            if (errorDetails) errorDetails.textContent = message;
            if (errorMessage) errorMessage.style.display = 'block';

        }

        let playerLoadTimeout = null;
        function startLoadTimeout(seconds = 15) {
            if (playerLoadTimeout) {
                clearTimeout(playerLoadTimeout);
            }

            const loadingSpinner = document.getElementById('loadingSpinner');
            if (loadingSpinner) loadingSpinner.style.display = 'block';

            playerLoadTimeout = setTimeout(() => {
                showError('视频加载超时，请检查网络连接或尝试切换其他源');
            }, seconds * 1000);
        }

        function clearLoadTimeout() {
            if (playerLoadTimeout) {
                clearTimeout(playerLoadTimeout);
                playerLoadTimeout = null;
            }
            const loadingSpinner = document.getElementById('loadingSpinner');
            if (loadingSpinner) loadingSpinner.style.display = 'none';
        }

        function initPlayerWithTimeout(episodeIndex, retryOnFail = false) {
            startLoadTimeout(30);

            if (typeof createArtplayerForEpisode === 'undefined') {
                let jsRetryCount = 0;
                const jsMaxRetries = 40;
                const jsRetryInterval = setInterval(() => {
                    jsRetryCount++;
                    if (typeof createArtplayerForEpisode !== 'undefined') {
                        clearInterval(jsRetryInterval);
                        createArtplayerForEpisode(episodeIndex);
                        monitorPlayerInitialization();
                    } else if (jsRetryCount >= jsMaxRetries) {
                        clearInterval(jsRetryInterval);
                        showError('播放器组件加载失败，请刷新页面重试');
                    }
                }, 250);
                return;
            }

            createArtplayerForEpisode(episodeIndex);
            monitorPlayerInitialization();
        }

        function monitorPlayerInitialization() {
            let checkCount = 0;
            const maxChecks = 100;
            const checkInterval = setInterval(() => {
                checkCount++;

                if (art) {
                    clearInterval(checkInterval);
                    setupPlayerErrorHandling();
                } else if (checkCount >= maxChecks) {
                    clearInterval(checkInterval);
                    if (!playerReady) {
                        showError('播放器初始化失败，请检查网络或刷新页面重试');
                    } else {
                    }
                }
            }, 300);
        }

        function setupPlayerErrorHandling() {
            if (!art) return;

            art.on('error', (error) => {
                showError('视频播放出错，请尝试重试或切换其他剧集');
            });

            art.on('video:canplay', () => {
                clearLoadTimeout();
                playerReady = true;
                const errorMessage = document.getElementById('errorMessage');
                if (errorMessage) errorMessage.style.display = 'none';
            });

            art.on('play', () => {
                clearLoadTimeout();
                playerReady = true;
                const errorMessage = document.getElementById('errorMessage');
                if (errorMessage) errorMessage.style.display = 'none';
            });

            art.on('ready', () => {
                clearLoadTimeout();
                playerReady = true;
                const errorMessage = document.getElementById('errorMessage');
                if (errorMessage) errorMessage.style.display = 'none';
            });

            const sidebarToggle = document.getElementById('sidebarToggle');
            const handleFullscreen = (state) => {
                if (sidebarToggle) sidebarToggle.style.display = state ? 'none' : '';
            };
            art.on('fullscreen', handleFullscreen);
            art.on('fullscreenWeb', handleFullscreen);
        }

        document.addEventListener('DOMContentLoaded', function() {
            const styleEl = document.createElement('style');
            styleEl.id = 'player-controls-responsive';
            document.head.appendChild(styleEl);

            function adjust() {
                const container = document.querySelector('.video-container');
                if (!container) return;
                const w = container.offsetWidth;
                if (w <= 450) styleEl.textContent = `.video-container .art-controls { zoom: 0.65 !important; }`;
                else if (w <= 600) styleEl.textContent = `.video-container .art-controls { zoom: 0.75 !important; }`;
                else if (w <= 700) styleEl.textContent = `.video-container .art-controls { zoom: 0.85 !important; }`;
                else styleEl.textContent = '';
            }

            const videoContainer = document.querySelector('.video-container');
            const playerEl = document.getElementById('player');
            if (!videoContainer || !playerEl) return;

            new ResizeObserver(adjust).observe(videoContainer);
            new MutationObserver(adjust).observe(playerEl, { childList: true, subtree: true });
        });

        function resetPlayerImmediately() {
            clearLoadTimeout();

            const container = document.querySelector('#player');
            if (!container) return;

            container.innerHTML = '';

            const loadingSpinner = document.getElementById('loadingSpinner');
            const errorMessage = document.getElementById('errorMessage');

            if (loadingSpinner) loadingSpinner.style.display = 'block';
            if (errorMessage) errorMessage.style.display = 'none';

            if (art) {
                try {
                    art.off('ready');
                    art.off('play');
                    art.off('pause');
                    art.off('error');
                    art.off('video:ended');
                    art.off('video:canplay');
                    if (typeof art.destroy === 'function') {
                        art.destroy(false);
                    }
                } catch (e) {
                }
                art = null;
            }
        
        }
        
        function readEpisodesFromDOM() {
            if (window.episodesData && window.episodesData.length > 0) {
        return window.episodesData;
            }
            
            return [];
        }
        
        function initAutoNext() {
            if (!art) return;
            
            art.off('video:ended');
            
            art.on('video:ended', () => {
                
                if (currentIndex >= episodes.length - 1) {
                    return;
                }
                
                switchToNextEpisode(currentIndex + 1);
            });
        }
        
        function switchToNextEpisode(nextIndex) {
            if (!episodes || nextIndex >= episodes.length) return;
            
            const nextEpisode = episodes[nextIndex];
            
            if (window.currentVideoInfo && episodes && episodes[nextIndex]) {
                const nextEpisodeInfo = {
                    vod_id: window.currentVideoInfo.vod_id,
                    vod_name: window.currentVideoInfo.vod_name,
                    vod_pic: window.currentVideoInfo.vod_pic,
                    source: window.currentVideoInfo.source,
                    current_episode: nextEpisode.title,
                    current_episode_index: nextIndex,
                    watch_time: Date.now()
                };
                WatchHistory.add(nextEpisodeInfo);
            }
            
            switchEpisodeWithoutDestroy(nextIndex);
        }
        
        async function switchEpisodeWithoutDestroy(nextIndex) {
            if (!episodes || !episodes[nextIndex]) return;
            
            const gen = ++switchGeneration;
            const ep = episodes[nextIndex];

            if (art) art.notice.show = `正在切换到 ${ep.title}`;
            currentIndex = nextIndex;
            highlightEpisode(nextIndex);
            scrollEpisodeIntoView(nextIndex);
            updateUIForCurrentIndex(nextIndex);
            
            let finalUrl = ep.url;
            
            if (finalUrl && finalUrl.includes('.m3u8') && apiKeyConfigured) {
                try {
                    const params = new URLSearchParams({ url: finalUrl, type: 'json' });
                    const response = await fetch(`./api-proxy.php?${params}`);
                    if (gen !== switchGeneration) {
                        return;
                    }
                    if (response.ok) {
                        const data = await response.json();
                        if (data.code === 200 && data.file_url) {
                            finalUrl = data.file_url;
                        }
                    }
                } catch (e) {
                }
            }

            if (gen !== switchGeneration) return;
            
            if (art && finalUrl) {
                if (finalUrl.includes('.m3u8') && art.hls) {
                    art.hls.loadSource(finalUrl);
                    art.hls.attachMedia(art.video);
                    art.video.load();
                    art.once('video:canplay', () => {
                        if (gen !== switchGeneration) return;
                        clearLoadTimeout();
                        art.play().catch(() => {});
                    });
                } else {
                    art.switchUrl(finalUrl, ep.title);
                    art.once('video:canplay', () => {
                        if (gen !== switchGeneration) return;
                        clearLoadTimeout();
                        art.play().catch(() => {});
                    });
                }
                if (switchErrorHandler) art.off('error', switchErrorHandler);
                switchErrorHandler = () => {
                    if (gen !== switchGeneration) return;
                    showError('视频切换失败，请尝试其他剧集');
                };
                art.once('error', switchErrorHandler);
            } else {
                initPlayerWithTimeout(nextIndex);
            }
        }
        
        function scrollEpisodeIntoView(idx) {
            // 侧边栏收起时列表不可见，跳过滚动，避免 scrollIntoView 触发外层视口/页面滚动
            if (document.documentElement.hasAttribute('data-sidebar-collapsed')) return;
            const items = document.querySelectorAll('.episode-item');
            if (items && items[idx]) {
                items[idx].scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
            }
        }
        
        function highlightEpisode(idx) {
            const items = document.querySelectorAll('.episode-item');
            items.forEach((it, i) => {
                if (i === idx) {
                    it.classList.add('active');
                } else {
                    it.classList.remove('active');
                }
            });
        }
        
        function bindEpisodeClicks() {
            const items = document.querySelectorAll('.episode-item');

            items.forEach((item, idx) => {
                item.addEventListener('click', function() {
                    if (idx === currentIndex && art) return;

                    const errorMessage = document.getElementById('errorMessage');
                    if (errorMessage) errorMessage.style.display = 'none';

                    if (art) {
                        switchEpisodeWithoutDestroy(idx);
                    } else {
                        initPlayerWithTimeout(idx);
                    }
                });
            });
        }
        
        function escapeHtml(str) {
            return String(str).replace(/[&<>"']/g, function(c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
            });
        }

        function updateUIForCurrentIndex(idx) {
            if (!episodes || !episodes[idx]) return;
        
            const ep = episodes[idx];
            const titleEl = document.getElementById('videoTitle');
        
            if (titleEl) {
                const originalTitle = titleEl.getAttribute('data-original-title');
                const fullText = originalTitle ?
                    `${originalTitle} ${ep.title}` :
                    ep.title;
                titleEl.innerHTML = originalTitle ?
                    `<span class="title-text">${escapeHtml(originalTitle)}</span><span class="episode-tag" style="color: var(--accent-color); font-size: 0.8em; margin-left: 6px;">${escapeHtml(ep.title)}</span>` :
                    escapeHtml(ep.title);
                titleEl.setAttribute('title', fullText);
            }
        }
        
        function enhancedRetry() {
            const retryBtn = document.getElementById('retryButton');
            if (!retryBtn) return;

            retryBtn.onclick = function() {

                const errorMessage = document.getElementById('errorMessage');
                if (errorMessage) errorMessage.style.display = 'none';

                initPlayerWithTimeout(currentIndex);
            };
        }
        
        function initThemeToggle() {
            const themeToggle = document.getElementById('themeToggle');
            const htmlElement = document.documentElement;
            const themeIcon = document.getElementById('themeIcon');
        
            const moonSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>';
            const sunSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>';
        
            if (themeToggle && themeIcon) {
                const currentTheme = htmlElement.getAttribute('data-theme');
                themeIcon.innerHTML = currentTheme === 'dark' ? sunSvg : moonSvg;
        
                function toggleTheme() {
                    const currentTheme = htmlElement.getAttribute('data-theme');
                    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        
                    htmlElement.setAttribute('data-theme', newTheme);
                    themeIcon.innerHTML = newTheme === 'dark' ? sunSvg : moonSvg;
        
                    document.cookie = `theme_preference=${newTheme}; path=/; max-age=${60*60*24*30}`;
                }
        
                themeToggle.addEventListener('click', toggleTheme);
        
                const colorSchemeQuery = window.matchMedia('(prefers-color-scheme: dark)');
                colorSchemeQuery.addEventListener('change', (e) => {
                    if (!document.cookie.includes('theme_preference')) {
                        const newTheme = e.matches ? 'dark' : 'light';
                        htmlElement.setAttribute('data-theme', newTheme);
                        themeIcon.innerHTML = newTheme === 'dark' ? sunSvg : moonSvg;
                    }
                });
            }
        }
        
        function initMobileMenu() {
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const navMenu = document.getElementById('navMenu');
            const menuIcon = document.getElementById('menuIcon');
        
            const barsSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>';
            const timesSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';

            if (mobileMenuBtn && navMenu && menuIcon) {
                mobileMenuBtn.addEventListener('click', function() {
                    const isNowActive = !navMenu.classList.contains('active');
                    navMenu.classList.toggle('active', isNowActive);
                    menuIcon.innerHTML = isNowActive ? timesSvg : barsSvg;
                });

                const navLinks = document.querySelectorAll('.nav-link');
                navLinks.forEach(link => {
                    link.addEventListener('click', function() {
                        navMenu.classList.remove('active');
                        menuIcon.innerHTML = barsSvg;
                    });
                });

                window.addEventListener('resize', function() {
                    if (window.innerWidth > 768) {
                        navMenu.classList.remove('active');
                        menuIcon.innerHTML = barsSvg;
                    }
                });
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.getElementById('sidebarToggle');
            const playerRow = document.querySelector('.player-row');
            const SIDEBAR_KEY = 'video_sidebar_collapsed';
            const htmlEl = document.documentElement;

            if (sidebarToggle && playerRow) {
                const isMobile = window.innerWidth <= 1024;

                if (localStorage.getItem(SIDEBAR_KEY) === '1' && !isMobile) {
                    htmlEl.setAttribute('data-sidebar-collapsed', '1');
                    const icon = sidebarToggle.querySelector('i');
                    icon.className = 'fas fa-chevron-left';
                }

                sidebarToggle.addEventListener('click', function() {
                    const collapsed = htmlEl.hasAttribute('data-sidebar-collapsed');
                    if (collapsed) {
                        htmlEl.removeAttribute('data-sidebar-collapsed');
                    } else {
                        htmlEl.setAttribute('data-sidebar-collapsed', '1');
                    }
                    const icon = sidebarToggle.querySelector('i');
                    icon.className = collapsed ? 'fas fa-chevron-right' : 'fas fa-chevron-left';
                    localStorage.setItem(SIDEBAR_KEY, collapsed ? '0' : '1');
                });
            }

            const descToggleBtn = document.getElementById('descToggleBtn');
            const videoDescription = document.getElementById('videoDescription');

            if (descToggleBtn && videoDescription) {
                if (videoDescription.scrollHeight <= videoDescription.clientHeight) {
                    descToggleBtn.style.display = 'none';
                }

                descToggleBtn.addEventListener('click', function() {
                    const isExpanded = videoDescription.classList.toggle('expanded');
                    descToggleBtn.classList.toggle('expanded');
                    descToggleBtn.querySelector('.toggle-text').textContent = isExpanded ? '收起' : '展开';
                });
            }

            if (window.location.search.includes('page=history')) {
                renderWatchHistory();
                document.getElementById('clearHistoryBtn').addEventListener('click', function() {
                    if (confirm('确定要清空所有观看记录吗？')) {
                        WatchHistory.clear();
                        renderWatchHistory();
                    }
                });
            }
        
        
            initThemeToggle();
            initMobileMenu();
        
            <?php if (!empty($showPasswordModal)): ?>
            return;
            <?php endif; ?>
        
            episodes = readEpisodesFromDOM();
        
            bindEpisodeClicks();
            enhancedRetry();
        
            if (episodes && episodes.length > 0) {

                const historyIndex = restoreFromHistory();
                const startIndex = historyIndex !== null ? historyIndex : 0;


                if (window.currentVideoInfo && episodes[startIndex]) {
                    window.currentVideoInfo.current_episode = episodes[startIndex].title;
                    window.currentVideoInfo.current_episode_index = startIndex;
                    WatchHistory.add(window.currentVideoInfo);
                }

                initPlayerWithTimeout(startIndex, true);
            } else {
                showError('未找到可播放的剧集，请尝试其他剧集或刷新页面');
            }
        
        });
            
        (function() {
            document.getElementById('y-day').textContent =
                Math.floor((new Date() - new Date('<?= $conf['y-day'] ?>')) / 86400000);
        })();
        
        document.addEventListener('DOMContentLoaded', function() {
            const [customPlayBtn, customPlayModal, closeCustomModal, submitCustomPlay, customPlayUrl] = [
        'customPlayBtn', 'customPlayModal', 'closeCustomModal', 'submitCustomPlay', 'customPlayUrl'
            ].map(id => document.getElementById(id));
        
            if (!customPlayBtn || !customPlayModal) {
        return;
            }
        
            customPlayBtn.addEventListener('click', () => {
        customPlayModal.style.display = 'flex';
        customPlayUrl?.focus();
            });
        
            const closeModal = () => {
        customPlayModal.style.display = 'none';
        customPlayUrl.value = '';
            };
        
            if (closeCustomModal) {
        closeCustomModal.addEventListener('click', closeModal);
        const accentColor = getComputedStyle(document.documentElement).getPropertyValue('--accent-color');
        const hoverColor = getComputedStyle(document.documentElement).getPropertyValue('--hover-color');
        const textColor = getComputedStyle(document.documentElement).getPropertyValue('--text-color');
        
        closeCustomModal.addEventListener('mouseover', () => {
            closeCustomModal.style.color = accentColor;
            closeCustomModal.style.background = hoverColor;
        });
        closeCustomModal.addEventListener('mouseout', () => {
            closeCustomModal.style.color = textColor;
            closeCustomModal.style.background = 'transparent';
        });
            }
        
            if (submitCustomPlay && customPlayUrl) {
        const isValidUrl = (url) => {
            try {
                const parsedUrl = new URL(url);
                return ['http:', 'https:'].includes(parsedUrl.protocol);
            } catch (e) {
                return false;
            }
        };
        
        const isValidVideoFormat = (url) => /\.(m3u8|mp4)(\?.*)?$/i.test(url);
        
        submitCustomPlay.addEventListener('click', () => {
            const url = customPlayUrl.value.trim();
            
            if (!url) return alert('请输入有效的视频播放地址！'), customPlayUrl.focus();
            if (!isValidUrl(url)) return alert('请输入有效的HTTP/HTTPS链接！'), customPlayUrl.focus(), customPlayUrl.select();
            if (!isValidVideoFormat(url)) return alert('仅支持M3U8或MP4格式的视频链接！'), customPlayUrl.focus(), customPlayUrl.select();
        
            const targetUrl = `./api-player.php?url=${encodeURIComponent(url)}`;
            window.open(targetUrl, '_blank');
            closeModal();
        });
        
        const accentHover = getComputedStyle(document.documentElement).getPropertyValue('--accent-hover');
        const accentColor = getComputedStyle(document.documentElement).getPropertyValue('--accent-color');
        submitCustomPlay.addEventListener('mouseover', () => submitCustomPlay.style.background = accentHover);
        submitCustomPlay.addEventListener('mouseout', () => submitCustomPlay.style.background = accentColor);
        
        customPlayUrl.addEventListener('keydown', (e) => e.key === 'Enter' && submitCustomPlay.click());
            }
        
            document.addEventListener('keydown', (e) => e.key === 'Escape' && customPlayModal.style.display === 'flex' && closeModal());
        });
        
    </script>
</body>
</html>
