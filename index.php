<?php
/**
 * @author    校长bloG <1213235865@qq.com>
 * @github    https://github.com/vpsaz/Mvideo-v2
 */

header('Content-Type: text/html; charset=utf-8');
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
    if (isset($_SERVER['HTTP_ACCEPT'])) {
        $accept = $_SERVER['HTTP_ACCEPT'];
        if (strpos($accept, 'prefers-color-scheme: dark') !== false) {
            return 'dark';
        }
    }
    return 'light';
}

$initialTheme = getInitialTheme();
$results = [];
$details = [];
$searchTerm = $_GET['wd'] ?? '';
$source = $_GET['y'] ?? '1';
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
        ?>
        <!DOCTYPE html>
        <html lang="zh-CN" data-theme="<?php echo $initialTheme; ?>">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>密码验证 - <?php echo $conf['site_title']; ?></title>
            <link rel="shortcut icon" href="https://pic1.imgdb.cn/item/6812e03558cb8da5c8d5d3c3.png" type="image/x-icon">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            <style>
                :root {
                    --bg-color: #f5f7fa;
                    --text-color: #2d3748;
                    --card-bg: #ffffff;
                    --border-color: #e2e8f0;
                    --accent-color: <?=$conf['m_accent_color'] ?>;
                    --accent-hover: <?=$conf['m_accent_hover'] ?>;
                    --secondary-color: #718096;
                    --hover-color: #edf2f7;
                    --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
                }
                
                [data-theme="dark"] {
                    --bg-color: #121212;
                    --text-color: #e0e0e0;
                    --card-bg: #1e1e1e;
                    --border-color: #333333;
                    --accent-color: <?=$conf['a_accent_color'] ?>;
                    --accent-hover: <?=$conf['a_accent_hover'] ?>;
                    --secondary-color: #aaaaaa;
                    --hover-color: #2a2a2a;
                    --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3);
                }
                
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                    transition: background-color 0.3s, color 0.3s, border-color 0.3s;
                }
                
                body {
                    background-color: var(--bg-color);
                    color: var(--text-color);
                    font-family: 'Segoe UI', 'PingFang SC', 'Microsoft YaHei', sans-serif;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    min-height: 100vh;
                    margin: 0;
                    line-height: 1.6;
                    background-image: url(), url(https://pic1.imgdb.cn/item/6812a7ae58cb8da5c8d5cbab.png);
                    background-position: right bottom, left top;
                    background-repeat: no-repeat, repeat;
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
                
                .theme-toggle {
                    position: absolute;
                    top: 15px;
                    right: 15px;
                    background: var(--bg-color);
                    border: 1px solid var(--border-color);
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
                
                .theme-toggle:hover {
                    background: var(--hover-color);
                    border-color: var(--accent-color);
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
                
                .video-info {
                    background: var(--bg-color);
                    padding: 15px;
                    border-radius: 8px;
                    margin-bottom: 20px;
                    text-align: left;
                    border: 1px solid var(--border-color);
                }
                
                .video-name {
                    font-weight: bold;
                    margin-bottom: 5px;
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
                    color: white;
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
                
                .back-link {
                    display: inline-block;
                    margin-top: 20px;
                    color: var(--secondary-color);
                    text-decoration: none;
                    transition: color 0.3s;
                    font-size: 14px;
                }
                
                .back-link:hover {
                    color: var(--accent-color);
                }
                
                small {
                    font-size: 12px;
                    color: var(--secondary-color);
                }
            </style>
        </head>
        <body>
            <div class="password-container">
                <button class="theme-toggle" id="themeToggle">
                    <i class="fas fa-moon"></i>
                </button>
                
                <div class="password-icon">
                    <i class="fas fa-lock"></i>
                </div>
                <h2 class="password-title">需要密码验证</h2>
                <p class="password-description">此内容受密码保护，请输入观看密码</p>
                
                <div class="video-info">
                    <div class="video-name"><?php echo htmlspecialchars($details['name'] ?? ''); ?></div>
                    <div style="color: var(--secondary-color); font-size: 14px;">
                        分类信息: <span style="color: var(--accent-color);"><?php echo htmlspecialchars($className); ?></span><br>
                        验证状态: <span style="color: var(--accent-color);">全局验证</span><br>
                        <small>输入一次密码后，可观看所有受保护内容</small>
                    </div>
                </div>
                
                <form method="POST" class="password-form">
                    <?php if (isset($selectedId) && isset($source)): ?>
                        <input type="hidden" name="video_id" value="<?php echo $selectedId; ?>">
                        <input type="hidden" name="source" value="<?php echo $source; ?>">
                    <?php endif; ?>
                    <input type="password" name="video_password" class="password-input" placeholder="请输入密码" required>
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
                
                <a href="?" class="back-link">
                    <i class="fas fa-arrow-left"></i> 返回搜索
                </a>
            </div>

            <script>
                function initThemeToggle() {
                    const themeToggle = document.getElementById('themeToggle');
                    const htmlElement = document.documentElement;
                    const themeIcon = themeToggle.querySelector('i');
                    
                    function getInitialTheme() {
                        const cookies = document.cookie.split(';');
                        for (let cookie of cookies) {
                            const [name, value] = cookie.trim().split('=');
                            if (name === 'theme_preference') {
                                return value;
                            }
                        }
                        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                            return 'dark';
                        }
                        return 'light';
                    }
                    
                    const currentTheme = getInitialTheme();
                    htmlElement.setAttribute('data-theme', currentTheme);
                    if (currentTheme === 'dark') {
                        themeIcon.className = 'fas fa-sun';
                    } else {
                        themeIcon.className = 'fas fa-moon';
                    }
                    
                    function toggleTheme() {
                        const currentTheme = htmlElement.getAttribute('data-theme');
                        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
                        
                        htmlElement.setAttribute('data-theme', newTheme);
                        
                        if (newTheme === 'dark') {
                            themeIcon.className = 'fas fa-sun';
                        } else {
                            themeIcon.className = 'fas fa-moon';
                        }
                        
                        document.cookie = `theme_preference=${newTheme}; path=/; max-age=${60*60*24*30}`;
                    }
                    
                    themeToggle.addEventListener('click', toggleTheme);
                    
                    const colorSchemeQuery = window.matchMedia('(prefers-color-scheme: dark)');
                    colorSchemeQuery.addEventListener('change', (e) => {
                        if (!document.cookie.includes('theme_preference')) {
                            const newTheme = e.matches ? 'dark' : 'light';
                            htmlElement.setAttribute('data-theme', newTheme);
                            
                            if (newTheme === 'dark') {
                                themeIcon.className = 'fas fa-sun';
                            } else {
                                themeIcon.className = 'fas fa-moon';
                            }
                        }
                    });
                }
                
                document.addEventListener('DOMContentLoaded', initThemeToggle);
            </script>
        </body>
        </html>
        <?php
        exit;
    }
}
    
    echo "<script>window.currentVideoInfo = {
    vod_id: '$selectedId',
    vod_name: '" . addslashes($details['name'] ?? '') . "',
    vod_pic: '" . addslashes($details['pic'] ?? '') . "',
    source: '$source',
    watch_time: Date.now()
};</script>";
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
    <style>
        :root {
            --bg-color: #f5f7fa;
            --text-color: #2d3748;
            --card-bg: #ffffff;
            --border-color: #e2e8f0;
            --accent-color: <?= $conf['m_accent_color'] ?>;
            --accent-hover: <?= $conf['m_accent_hover'] ?>;
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
        }
        
        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            font-family: 'Segoe UI', 'PingFang SC', 'Microsoft YaHei', sans-serif;
            line-height: 1.6;
            background-image: url(), url(https://pic1.imgdb.cn/item/6812a7ae58cb8da5c8d5cbab.png);
            background-position: right bottom, left top;
            background-repeat: no-repeat, repeat;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 25px;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        .search-section {
            background: var(--card-bg);
            border-radius: 8px;
            padding: 20px;
            box-shadow: var(--shadow);
            margin-bottom: 20px;
        }
        
        .search-title {
            font-size: 20px;
            margin-bottom: 15px;
            color: var(--text-color);
        }
        
        .search-form {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: stretch;
        }
        
        .search-input {
            flex: 1;
            min-width: 200px;
            background: var(--bg-color);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 0 15px;
            color: var(--text-color);
            font-size: 16px;
            height: 44px;
            box-sizing: border-box;
            line-height: 44px;
            min-height: 44px;
        }
        
        .search-input:focus {
            outline: none;
            border-color: var(--accent-color);
        }
        
        .source-select {
            background: var(--bg-color);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 0 35px 0 15px;
            color: var(--text-color);
            font-size: 16px;
            height: 44px;
            box-sizing: border-box;
            min-width: 120px;
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23718096' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 16px;
            cursor: pointer;
            line-height: 44px;
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
            border-radius: 6px;
            padding: 0 20px;
            color: white;
            font-size: 16px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: background 0.2s;
            height: 44px;
            box-sizing: border-box;
            white-space: nowrap;
            min-width: 80px;
            flex-shrink: 0;
        }
        
        .search-btn:hover {
            background: var(--accent-hover);
        }
        
        @media (max-width: 768px) {
            .search-form {
                gap: 8px;
            }
        
            .search-input {
                min-width: 150px;
            }
        
            .source-select {
                min-width: 100px;
            }
        
            .search-btn {
                min-width: 70px;
            }
        }
        
        @media (max-width: 600px) {
            .search-form {
                flex-wrap: wrap;
            }
        
            .search-input {
                flex: 1 0 100%;
                margin-bottom: 8px;
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
            border-radius: 8px;
            padding: 20px;
            box-shadow: var(--shadow);
            flex: 1;
        }
        
        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .results-title {
            font-size: 18px;
            color: var(--text-color);
        }
        
        .results-count {
            color: var(--secondary-color);
            font-size: 14px;
        }
        
        .results-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .result-item {
            background: var(--bg-color);
            border-radius: 8px;
            padding: 16px;
            cursor: pointer;
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }
        
        .result-item:hover {
            background: var(--hover-color);
            transform: translateX(5px);
            border-left-color: var(--accent-color);
        }
        
        .result-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        
        .result-title {
            font-size: 18px;
            color: var(--text-color);
            margin-bottom: 8px;
        }
        
        .result-meta {
            display: flex;
            gap: 15px;
            color: var(--secondary-color);
            font-size: 14px;
            flex-wrap: wrap;
        }
        
        .result-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
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
            color: white;
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
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            height: 500px;
        }
        
        .video-container {
            flex: 1;
            background: var(--card-bg);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: var(--shadow);
            display: flex;
            flex-direction: column;
        }
        
        .video-player {
            flex: 1;
            position: relative;
            background: #000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .video-player video {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        
        .player-controls {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(0, 0, 0, 0.7));
            padding: 15px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .video-player:hover .player-controls {
            opacity: 1;
        }
        
        .progress-bar {
            width: 100%;
            height: 4px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 2px;
            cursor: pointer;
            position: relative;
        }
        
        .progress {
            height: 100%;
            background: var(--accent-color);
            border-radius: 2px;
            width: 0%;
        }
        
        .control-buttons {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .left-controls, .right-controls {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .control-btn {
            background: transparent;
            border: none;
            color: #fff;
            font-size: 16px;
            cursor: pointer;
            transition: color 0.2s;
        }
        
        .control-btn:hover {
            color: var(--accent-color);
        }
        
        .play-btn-large {
            background: var(--accent-color);
            border-radius: 50%;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .play-btn-large:hover {
            background: var(--accent-hover);
        }
        
        .episodes-section {
            flex: 0 0 320px;
            background: var(--card-bg);
            border-radius: 8px;
            padding: 20px;
            box-shadow: var(--shadow);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .section-title {
            font-size: 18px;
            margin-bottom: 15px;
            color: var(--text-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
        }
        
        .episodes-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .episodes-list {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 10px;
            overflow-y: auto;
            padding-right: 5px;
        }
        
        .episode-item {
            background: var(--bg-color);
            border-radius: 6px;
            padding: 12px;
            cursor: pointer;
            transition: all 0.2s;
            border-left: 3px solid transparent;
            flex-shrink: 0;
        }
        
        .episode-item:hover {
            background: var(--hover-color);
        }
        
        .episode-item.active {
            background: var(--hover-color);
            border-left: 3px solid var(--accent-color);
        }
        
        .episode-title {
            font-size: 15px;
            margin-bottom: 5px;
            color: var(--text-color);
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
        }
        
        .video-title {
            font-size: 22px;
            margin-bottom: 10px;
            color: var(--text-color);
        }
        
        .video-meta {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            color: var(--secondary-color);
            font-size: 14px;
            flex-wrap: wrap;
        }
        
        .video-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .video-description {
            margin-bottom: 20px;
            line-height: 1.6;
            color: var(--text-color);
        }
        
        .video-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .action-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--bg-color);
            border: 1px solid var(--border-color);
            color: var(--text-color);
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.2s;
            font-size: 14px;
        }
        
        .action-btn:hover {
            background: var(--hover-color);
        }
        
        .back-btn {
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            color: var(--text-color);
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            margin-bottom: 20px;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        [data-theme="dark"] .back-btn {
            background: #1E1E1E;
        }
        
        .back-btn:hover {
            background: var(--hover-color);
            border-color: var(--accent-color);
            color: var(--accent-color);
            transform: translateX(-5px);
        }
        
        .footer {
            background: var(--card-bg);
            border-radius: 8px;
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
            padding: 60px 20px;
            color: var(--secondary-color);
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            color: var(--border-color);
        }
        
        .empty-state p {
            font-size: 16px;
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
            color: white;
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
        
        .video-info-content {
            display: flex;
            gap: 30px;
            align-items: flex-start;
        }
        
        .video-text {
            flex: 1;
        }
        
        .video-poster {
            flex: 0 0 200px;
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: transform 0.3s ease;
        }
        
        .video-poster:hover {
            transform: translateY(-5px);
        }
        
        .poster-image {
            width: 100%;
            height: 280px;
            object-fit: cover;
            display: block;
        }
        
        .poster-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .video-poster:hover .poster-overlay {
            opacity: 1;
        }
        
        .poster-overlay i {
            color: white;
            font-size: 24px;
            background: var(--accent-color);
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        
        @media (max-width: 1024px) {
            .player-row {
                flex-direction: column;
                height: auto;
            }
        
            .video-container {
                display: block;
                position: relative;
                width: 100%;
                height: 0;
                padding-bottom: 56.25%;
            }
        
            .video-player {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
            }
        
            .video-player video {
                object-fit: contain;
            }
        
            .episodes-section {
                flex: 1;
                max-height: 300px;
                min-height: auto;
            }
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
        
            .result-header {
                flex-direction: column;
                gap: 10px;
            }
        
            .result-footer {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }
        
            .video-title {
                font-size: 20px;
            }
        
            .video-actions {
                flex-wrap: wrap;
            }
        
            .video-info-content {
                flex-direction: column-reverse;
                gap: 20px;
            }
        
            .video-poster {
                flex: 0 0 auto;
                width: 150px;
                align-self: center;
            }
        
            .poster-image {
                height: 200px;
            }
        
            .video-container {
                padding-bottom: 56.25%;
            }
        
            .episodes-section {
                max-height: 250px;
            }
        
            .player-controls {
                padding: 10px;
            }
        
            .control-buttons {
                flex-wrap: wrap;
                gap: 10px;
            }
        
            .left-controls, .right-controls {
                gap: 10px;
            }
        
            .time {
                font-size: 12px;
            }
        }
        
        @media (max-width: 480px) {
            .video-container {
                padding-bottom: 56.25%;
            }
        
            .episodes-section {
                padding: 15px;
                max-height: 200px;
            }
        
            .section-title {
                font-size: 16px;
                margin-bottom: 10px;
            }
        
            .episode-item {
                padding: 8px 10px;
            }
        
            .episode-title {
                font-size: 14px;
            }
        }
        
        .episodes-list::-webkit-scrollbar {
            width: 8px;
        }
        
        .episodes-list::-webkit-scrollbar-track {
            background: transparent;
            border-radius: 4px;
        }
        
        .episodes-list::-webkit-scrollbar-thumb {
            background: var(--secondary-color);
            border-radius: 4px;
            opacity: 0.5;
        }
        
        .episodes-list::-webkit-scrollbar-thumb:hover {
            background: var(--accent-color);
            opacity: 0.8;
        }
        
        .episodes-list {
            scrollbar-width: thin;
            scrollbar-color: var(--secondary-color) transparent;
        }
        
        .main-nav {
            background: var(--card-bg);
            border-bottom: 1px solid var(--border-color);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .nav-container {
            max-width: 1400px;
            margin: 0 auto;
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
        
        .nav-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .theme-toggle-nav,
        .mobile-menu-btn {
            background: var(--bg-color);
            border: 1px solid var(--border-color);
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
            border-color: var(--accent-color);
        }
        
        .mobile-menu-btn {
            display: none;
        }
        
        @media (max-width: 768px) {
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
                padding: 20px;
                border-top: 1px solid var(--border-color);
                transform: translateY(-100%);
                opacity: 0;
                visibility: hidden;
                transition: all 0.3s ease;
                gap: 0;
                margin: 0;
                pointer-events: none;
            }
        
            .nav-menu.active {
                transform: translateY(0);
                opacity: 1;
                visibility: visible;
                pointer-events: auto;
            }
        
            .nav-link {
                padding: 12px 0;
                border-bottom: 1px solid var(--border-color);
                width: 100%;
            }
        
            .nav-link:last-child {
                border-bottom: none;
            }
        
            .nav-menu:not(.active) {
                display: none;
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
        
        .history-actions { 
            display: flex; 
            justify-content: flex-end; 
            margin-bottom: 20px; 
        }
        
        .clear-history-btn { 
            background: var(--accent-color); 
            color: white; 
            border: none; 
            padding: 8px 16px; 
            border-radius: 6px; 
            cursor: pointer; 
            display: flex; 
            align-items: center; 
            gap: 5px; 
        }
        
        .clear-history-btn:hover { 
            background: var(--accent-hover); 
        }
        
        .history-item { 
            background: var(--bg-color); 
            border-radius: 8px; 
            padding: 16px; 
            margin-bottom: 12px; 
            display: flex; 
            gap: 15px; 
            transition: all 0.2s; 
            border-left: 3px solid transparent; 
        }
        
        .history-item:hover { 
            background: var(--hover-color); 
            transform: translateX(5px); 
            border-left-color: var(--accent-color); 
        }
        
        .history-poster { 
            width: 80px; 
            height: 110px; 
            border-radius: 6px; 
            object-fit: cover; 
            flex-shrink: 0; 
        }
        
        .history-content { 
            flex: 1; 
        }
        
        .history-title { 
            font-size: 18px; 
            margin-bottom: 8px; 
            color: var(--text-color); 
        }
        
        .history-meta { 
            display: flex; 
            gap: 15px; 
            color: var(--secondary-color); 
            font-size: 14px; 
            margin-bottom: 8px; 
            flex-wrap: wrap; 
        }
        
        .history-meta span { 
            display: flex; 
            align-items: center; 
            gap: 5px; 
        }
        
        .history-actions-item { 
            display: flex; 
            gap: 10px; 
            margin-top: 10px; 
        }
        
        .continue-btn { 
            background: var(--accent-color); 
            color: white; 
            padding: 6px 12px; 
            border-radius: 4px; 
            text-decoration: none; 
            display: flex; 
            align-items: center; 
            gap: 5px; 
            font-size: 13px; 
            transition: background 0.2s; 
        }
        
        .continue-btn:hover { 
            background: var(--accent-hover); 
        }
        
        .remove-btn { 
            background: transparent; 
            border: 1px solid var(--border-color); 
            color: var(--secondary-color); 
            padding: 6px 12px; 
            border-radius: 4px; 
            cursor: pointer; 
            display: flex; 
            align-items: center; 
            gap: 5px; 
            font-size: 13px; 
            transition: all 0.2s; 
        }
        
        .remove-btn:hover { 
            background: var(--accent-color); 
            color: white; 
            border-color: var(--accent-color); 
        }
        
        @media (max-width: 768px) {
            .history-actions {
        justify-content: center;
        margin-bottom: 15px;
            }
            
            .clear-history-btn {
        padding: 8px 16px;
        font-size: 14px;
            }
            
            .history-item {
        flex-direction: row;
        gap: 0;
        padding: 12px;
        margin-bottom: 10px;
        position: relative;
            }
            
            .history-poster {
        display: none;
            }
            
            .history-content {
        width: 100%;
        padding: 0;
            }
            
            .history-title {
        font-size: 16px;
        margin-bottom: 10px;
        font-weight: 600;
        line-height: 1.4;
            }
            
            .history-meta {
        justify-content: flex-start;
        gap: 15px;
        margin-bottom: 12px;
        flex-wrap: wrap;
            }
            
            .history-meta span {
        font-size: 13px;
        background: var(--card-bg);
        padding: 4px 10px;
        border-radius: 6px;
        border: 1px solid var(--border-color);
            }
            
            .history-actions-item {
        justify-content: flex-start;
        gap: 10px;
            }
            
            .continue-btn,
            .remove-btn {
        padding: 6px 12px;
        font-size: 13px;
            }
        }
        
        @media (max-width: 480px) {
            .history-item {
        padding: 15px;
        border-left: 4px solid var(--accent-color);
            }
            
            .history-title {
        font-size: 15px;
        margin-bottom: 8px;
            }
            
            .history-meta {
        gap: 8px;
        margin-bottom: 10px;
            }
            
            .history-meta span {
        font-size: 12px;
        padding: 3px 8px;
            }
            
            .history-actions-item {
        gap: 8px;
            }
            
            .continue-btn,
            .remove-btn {
        padding: 6px 10px;
        font-size: 12px;
            }
        }
        
        @media (max-width: 360px) {
            .history-item {
        padding: 12px;
            }
            
            .history-title {
        font-size: 14px;
            }
            
            .history-meta {
        flex-direction: column;
        align-items: flex-start;
        gap: 6px;
            }
            
            .history-meta span {
        width: 100%;
        justify-content: center;
            }
            
            .history-actions-item {
        flex-direction: column;
        gap: 6px;
            }
            
            .continue-btn,
            .remove-btn {
        padding: 5px 10px;
        font-size: 12px;
        width: 100%;
            }
        }
    </style>
</head>
<body>
    <nav class="main-nav">
        <div class="nav-container">
            <a href="./" class="nav-brand">
                <b><span class="brand-accent">M</span>video</b>
            </a>
            <div class="nav-menu" id="navMenu">
                <a href="./" class="nav-link <?= !$isHistoryPage ? 'active' : '' ?>">首页</a>
                <a href="?page=history" class="nav-link <?= $isHistoryPage ? 'active' : '' ?>">观看记录</a> <?php if ($passwordVerified): ?> <a href="?logout=1" class="nav-link"><i class="fas fa-sign-out-alt"></i> 退出验证</a> <?php endif; ?>
            </div>
            <div class="nav-actions">
                <button class="theme-toggle-nav" id="themeToggle">
                    <i class="fas fa-moon"></i>
                </button>
                <button class="mobile-menu-btn" id="mobileMenuBtn">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>
    </nav>
    <div class="container"> <?php if ($isHistoryPage): ?> <div class="results-section">
            <div class="results-header">
                <h3 class="results-title">观看记录</h3>
                <div class="results-count" id="historyCount">共 0 条记录</div>
            </div>
            <div class="history-actions">
                <button class="clear-history-btn" id="clearHistoryBtn">
                    <i class="fas fa-trash"></i>
                    <span>清空记录</span>
                </button>
            </div>
            <div class="results-list" id="historyList">
                <div class="empty-state">
                    <i class="fas fa-history"></i>
                    <p>暂无观看记录</p>
                </div>
            </div>
        </div> <?php elseif (empty($selectedId)): ?> <div class="search-section">
            <h2 class="search-title">搜索影片</h2>
            <form method="GET" class="search-form">
                <input type="text" name="wd" class="search-input" value="<?= htmlspecialchars($searchTerm) ?>" placeholder="输入影片名称...">
                <select class="source-select" name="y"> <?php for ($i = 1; $i <= $source_count; $i++): ?> <option value="<?= $i ?>" <?= ($source == (string)$i) ? 'selected' : '' ?>> 片源<?= $i ?> </option> <?php endfor; ?> </select>
                <button type="submit" class="search-btn">
                    <i class="fas fa-search"></i>
                    <span>搜索</span>
                </button>
            </form>
        </div>
        <div class="results-section">
            <div class="results-header">
                <h3 class="results-title">搜索结果</h3>
                <div class="results-count"> <?php if (!empty($searchTerm)): ?> <?php if (!empty($results)): ?> 找到 <?= count($results) ?> 部影片 <?php else: ?> 未找到相关影片 <?php endif; ?> <?php else: ?> 请输入搜索关键词 <?php endif; ?> </div>
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
                </a> <?php endforeach; ?> <?php elseif (!empty($searchTerm)): ?> <div class="empty-state">
                    <i class="fas fa-film"></i>
                    <p>未找到相关影片</p>
                </div> <?php else: ?> <div class="empty-state">
                    <i class="fas fa-search"></i>
                    <p>输入关键词搜索影片</p>
                </div> <?php endif; ?> </div>
        </div> <?php else: ?> <?php if (!empty($details)): ?> <a href="?" class="back-btn">
            <i class="fas fa-arrow-left"></i>
            <span>返回搜索</span>
        </a>
        <div class="player-row">
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
                <h2 class="section-title"> 选集 <span id="episodesCount">共 <?= !empty($details['play_url']) ? count($details['play_url']) : 0 ?> 集</span>
                </h2>
                <div class="episodes-container">
                    <div class="episodes-list" id="episodesList"> <?php if (!empty($details['play_url'])): ?> <?php foreach ($details['play_url'] as $index => $episode): ?> <div class="episode-item">
                            <div class="episode-title"><?= htmlspecialchars($episode['title']) ?></div>
                        </div> <?php endforeach; ?> <?php else: ?> <div class="empty-state">
                            <i class="fas fa-film"></i>
                            <p>暂无播放列表</p>
                        </div> <?php endif; ?> </div>
                </div>
            </div>
        </div>
        <div class="video-info">
            <div class="video-info-content">
                <div class="video-text">
                    <h1 class="video-title" id="videoTitle" data-original-title="<?= htmlspecialchars($details['name'] ?? '') ?>"><?= htmlspecialchars($details['name'] ?? '加载中...') ?></h1>
                    <div class="video-meta">
                        <span id="videoRating"><i class="fas fa-star"></i> <?= htmlspecialchars($details['douban_score'] ?? 'N/A') ?></span>
                        <span id="videoDuration"><i class="fas fa-clock"></i> <?= htmlspecialchars($details['director'] ?? '未知导演') ?></span>
                        <span id="videoDate"><i class="fas fa-calendar"></i> <?= htmlspecialchars($details['pubdate'] ?? '未知日期') ?></span>
                        <span id="videoSource"><i class="fas fa-server"></i> <?= htmlspecialchars($details['area'] ?? '未知地区') ?></span>
                    </div>
                    <p class="video-description" id="videoDescription"><?= htmlspecialchars($details['content'] ?? '正在获取视频信息...') ?></p>
                </div>
                <div class="video-poster">
                    <img src="<?= !empty($details['pic']) ? htmlspecialchars($details['pic']) : 'https://images.unsplash.com/photo-1611162617474-5b21e879e113?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1074&q=80' ?>"
                        alt="<?= htmlspecialchars($details['name'] ?? '影片海报') ?>" class="poster-image">
                    <div class="poster-overlay">
                        <i class="fas fa-expand"></i>
                    </div>
                </div>
            </div>
        </div> <?php if (!empty($details['play_url'])): ?> <script>
            window.episodesData = [
                <?php foreach ($details['play_url'] as $index => $episode): ?>
                {
                    url: "<?= addslashes($episode['link']) ?>",
                    title: "<?= addslashes($episode['title']) ?>",
                    index: <?= $index ?>
                }<?= ($index < count($details['play_url']) - 1) ? ',' : '' ?>
                <?php endforeach; ?>
            ];
            console.log('PHP生成的剧集数据:', window.episodesData);
            window.apiKeyConfigured = <?php echo $apiKeyConfigured ? 'true' : 'false'; ?>;
            console.log('API Key 配置状态:', window.apiKeyConfigured);
        </script><script src="https://baiapi.cn/js-lib/Mvideo_v2/SharedViewing.js"></script> <?php endif; ?> <?php else: ?> <div class="empty-state">
            <i class="fas fa-exclamation-circle"></i>
            <p>无法加载影片详情</p>
            <a href="?" class="back-btn" style="margin-top: 20px;">
                <i class="fas fa-arrow-left"></i>
                <span>返回搜索</span>
            </a>
        </div> <?php endif; ?> <?php endif; ?> <footer class="footer">
            <div class="footer-container">
                <div class="footer-content">
                    <div class="footer-info">
                        <h3 class="footer-brand">
                            <b><a href="./"><span class="brand-accent">M</span>video</a></b>
                        </h3>
                        <p class="footer-desc">
                            <a href="" title="<?= $conf['disclaimers'] ?>">
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
        const WatchHistory = {
            STORAGE_KEY: 'video_watch_history',
            MAX_RECORDS: 50,
        
            getAll() {
                try {
                    const history = localStorage.getItem(this.STORAGE_KEY);
                    return history ? JSON.parse(history) : [];
                } catch (error) {
                    console.error('读取观看记录失败:', error);
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
                    console.error('保存观看记录失败:', error);
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
                    console.error('删除观看记录失败:', error);
                    return false;
                }
            },
        
            clear() {
                try {
                    localStorage.removeItem(this.STORAGE_KEY);
                    return true;
                } catch (error) {
                    console.error('清空观看记录失败:', error);
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
                console.log('恢复条件不满足');
                return null;
            }
            
            const videoHistory = WatchHistory.getVideoHistory(window.currentVideoInfo.vod_id);
            if (!videoHistory) {
                console.log('未找到观看记录');
                return null;
            }
            
            console.log('从观看记录恢复:', videoHistory);
            
            if (videoHistory.current_episode_index !== undefined && 
                videoHistory.current_episode_index >= 0 && 
                videoHistory.current_episode_index < episodes.length) {
                console.log(`从索引恢复: 第${videoHistory.current_episode_index + 1}集`);
                return videoHistory.current_episode_index;
            }
            
            if (videoHistory.current_episode) {
                const historyEpisodeIndex = episodes.findIndex(ep => 
                    ep.title === videoHistory.current_episode
                );
                if (historyEpisodeIndex >= 0) {
                    console.log(`从标题恢复: 第${historyEpisodeIndex + 1}集`);
                    return historyEpisodeIndex;
                }
            }
            
            console.log('无法从记录恢复，使用默认第一集');
            return null;
        }
        
        function renderWatchHistory() {
            const historyList = document.getElementById('historyList');
            const historyCount = document.getElementById('historyCount');
        
            if (!historyList) return;
        
            const history = WatchHistory.getAll();
        
            if (history.length === 0) {
                historyList.innerHTML = `<div class="empty-state"><i class="fas fa-history"></i><p>暂无观看记录</p></div>`;
                historyCount.textContent = '共 0 条记录';
                return;
            }
        
            historyCount.textContent = `共 ${history.length} 条记录`;
        
            historyList.innerHTML = history.map(item => `
                <div class="history-item">
                    <img src="${item.vod_pic || 'https://images.unsplash.com/photo-1611162617474-5b21e879e113?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1074&q=80'}" alt="${item.vod_name}" class="history-poster">
                    <div class="history-content">
                        <h4 class="history-title">${item.vod_name}</h4>
                        <div class="history-meta">
                            <span><i class="fas fa-play-circle"></i> ${item.current_episode || '未记录集数'}</span>
                            <span><i class="fas fa-server"></i> 片源${item.source}</span>
                            <span><i class="fas fa-clock"></i> ${WatchHistory.formatTime(item.watch_time)}</span>
                        </div>
                        <div class="history-actions-item">
                            <a href="?id=${item.vod_id}&y=${item.source}" class="continue-btn"><i class="fas fa-play"></i>继续观看</a>
                            <button class="remove-btn" onclick="removeHistoryItem('${item.vod_id}')"><i class="fas fa-times"></i>删除记录</button>
                        </div>
                    </div>
                </div>
            `).join('');
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
        let isSwitchingEpisode = false;
        
        setInterval(() => {
            if (isSwitchingEpisode) {
                console.warn('状态锁已锁定超过5秒，强制重置');
                isSwitchingEpisode = false;
            }
        }, 5000);
        
        window.resetSwitchLock = function() {
            console.log('手动重置切换状态锁');
            isSwitchingEpisode = false;
            console.log('当前状态锁:', isSwitchingEpisode);
        };
        
        function playM3u8(video, url, art) {
            console.log('使用HLS播放m3u8:', url);
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
                    console.log('HLS视频加载成功');
                    video.play().catch(e => {
                        console.log('自动播放被阻止:', e);
                    });
                });
        
                hls.on(Hls.Events.ERROR, function(event, data) {
                    console.error('HLS错误:', data);
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
            const loadingSpinner = document.getElementById('loadingSpinner');
            const errorMessage = document.getElementById('errorMessage');
            const errorDetails = document.getElementById('errorDetails');
        
            if (loadingSpinner) loadingSpinner.style.display = 'none';
            if (errorDetails) errorDetails.textContent = message;
            if (errorMessage) errorMessage.style.display = 'block';
        
            console.error('播放错误:', message);
        }
        
        function resetPlayerImmediately() {
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
                    if (typeof art.destroy === 'function') {
                        art.destroy(false);
                    }
                } catch (e) {
                    console.warn('销毁旧播放器时出错:', e);
                }
                art = null;
            }
        
            console.log('播放器界面已重置');
        }
        
        function readEpisodesFromDOM() {
            if (window.episodesData && window.episodesData.length > 0) {
                console.log('使用PHP生成的剧集数据，数量:', window.episodesData.length);
                return window.episodesData;
            }
        
            console.log('PHP数据未找到，从DOM读取剧集数据');
            const nodes = document.querySelectorAll('.episode-item');
            const arr = [];
        
            nodes.forEach((node, idx) => {
                try {
                    let title = node.querySelector('.episode-title') ?
                        node.querySelector('.episode-title').textContent.trim() :
                        `第${idx+1}集`;
        
                    let url = '';
        
                    arr.push({
                        url: url,
                        title: title,
                        index: idx
                    });
        
                } catch (e) {
                    console.error('解析剧集数据出错:', e);
                }
            });
        
            console.log('从DOM读取的剧集数据:', arr);
            return arr;
        }
        
        function initAutoNext() {
            if (!art) return;
            
            art.off('video:ended');
            
            art.on('video:ended', () => {
                console.log('视频结束事件触发，当前索引:', currentIndex, '总集数:', episodes.length);
                
                if (currentIndex >= episodes.length - 1) {
                    console.log('已经是最后一集，不自动切换');
                    return;
                }
                
                console.log('视频播放结束，自动切换到下一集');
                switchToNextEpisode(currentIndex + 1);
            });
        }
        
        function switchToNextEpisode(nextIndex) {
            console.log('=== 切换下一集调试 ===');
            console.log('当前索引:', currentIndex, '目标索引:', nextIndex);
            console.log('切换前状态锁:', isSwitchingEpisode);
            
            if (isSwitchingEpisode) {
                console.log('切换被阻止: 正在切换中');
                return;
            }
            
            if (!episodes || nextIndex >= episodes.length) {
                console.log('切换被阻止: 剧集不存在或已是最后一集');
                return;
            }
            
            isSwitchingEpisode = true;
            console.log('开始切换到下一集:', nextIndex);
            
            const nextEpisode = episodes[nextIndex];
            
            const volume = art ? art.volume : 0.7;
            const wasFullscreen = art ? art.fullscreen : false;
            
            if (art) {
                art.notice.show = `正在切换到 ${nextEpisode.title}`;
            }
            
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
            
            setTimeout(() => {
                try {
                    if (art) {
                        art.destroy();
                    }
                } catch (e) {
                    console.warn('销毁播放器时出错:', e);
                }
                
                art = null;
                
                createArtplayerForEpisode(nextIndex);
                
            }, 500);
        }
        
        function scrollEpisodeIntoView(idx) {
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
                    highlightEpisode(idx);
                    scrollEpisodeIntoView(idx);
                    createArtplayerForEpisode(idx);
                });
            });
        }
        
        function updateUIForCurrentIndex(idx) {
            if (!episodes || !episodes[idx]) return;
        
            const ep = episodes[idx];
            const titleEl = document.getElementById('videoTitle');
        
            if (titleEl) {
                const originalTitle = titleEl.getAttribute('data-original-title');
                titleEl.innerHTML = originalTitle ?
                    `${originalTitle} <span style="color: var(--accent-color); font-size: 0.9em; margin-left: 10px;"> ${ep.title}</span>` :
                    ep.title;
            }
        }
        
        function enhancedRetry() {
            const retryBtn = document.getElementById('retryButton');
            if (!retryBtn) return;
        
            retryBtn.onclick = function() {
                console.log('用户点击重试，当前索引:', currentIndex);
                createArtplayerForEpisode(currentIndex);
            };
        }
        
        function initThemeToggle() {
            const themeToggle = document.getElementById('themeToggle');
            const htmlElement = document.documentElement;
            const themeIcon = themeToggle.querySelector('i');
        
            if (themeToggle) {
                const currentTheme = htmlElement.getAttribute('data-theme');
                if (currentTheme === 'dark') {
                    themeIcon.className = 'fas fa-sun';
                } else {
                    themeIcon.className = 'fas fa-moon';
                }
        
                function toggleTheme() {
                    const currentTheme = htmlElement.getAttribute('data-theme');
                    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        
                    htmlElement.setAttribute('data-theme', newTheme);
        
                    if (newTheme === 'dark') {
                        themeIcon.className = 'fas fa-sun';
                    } else {
                        themeIcon.className = 'fas fa-moon';
                    }
        
                    document.cookie = `theme_preference=${newTheme}; path=/; max-age=${60*60*24*30}`;
                }
        
                themeToggle.addEventListener('click', toggleTheme);
        
                const colorSchemeQuery = window.matchMedia('(prefers-color-scheme: dark)');
                colorSchemeQuery.addEventListener('change', (e) => {
                    if (!document.cookie.includes('theme_preference')) {
                        const newTheme = e.matches ? 'dark' : 'light';
                        htmlElement.setAttribute('data-theme', newTheme);
        
                        if (newTheme === 'dark') {
                            themeIcon.className = 'fas fa-sun';
                        } else {
                            themeIcon.className = 'fas fa-moon';
                        }
                    }
                });
            }
        }
        
        function initMobileMenu() {
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const navMenu = document.getElementById('navMenu');
        
            if (mobileMenuBtn && navMenu) {
                mobileMenuBtn.addEventListener('click', function() {
                    const isNowActive = !navMenu.classList.contains('active');
                    navMenu.classList.toggle('active', isNowActive);
                    const icon = mobileMenuBtn.querySelector('i');
                    icon.className = isNowActive ? 'fas fa-times' : 'fas fa-bars';
                });
        
                const navLinks = document.querySelectorAll('.nav-link');
                navLinks.forEach(link => {
                    link.addEventListener('click', function() {
                        navMenu.classList.remove('active');
                        mobileMenuBtn.querySelector('i').className = 'fas fa-bars';
                    });
                });
        
                window.addEventListener('resize', function() {
                    if (window.innerWidth > 768) {
                        navMenu.classList.remove('active');
                        mobileMenuBtn.querySelector('i').className = 'fas fa-bars';
                    }
                });
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            if (window.location.search.includes('page=history')) {
                renderWatchHistory();
                document.getElementById('clearHistoryBtn').addEventListener('click', function() {
                    if (confirm('确定要清空所有观看记录吗？')) {
                        WatchHistory.clear();
                        renderWatchHistory();
                    }
                });
            }
        
            if (window.currentVideoInfo) {
                WatchHistory.add(window.currentVideoInfo);
            }
        
            console.log('=== 播放器初始化开始 ===');
            console.log('API Key 配置状态:', apiKeyConfigured);
        
            initThemeToggle();
            initMobileMenu();
        
            episodes = readEpisodesFromDOM();
            console.log('剧集数据:', episodes);
            console.log('剧集总数:', episodes ? episodes.length : 0);
        
            bindEpisodeClicks();
            enhancedRetry();
        
            if (episodes && episodes.length > 0) {
                console.log(`找到 ${episodes.length} 个剧集，准备播放`);
                
                const historyIndex = restoreFromHistory();
                const startIndex = historyIndex !== null ? historyIndex : 0;
                
                console.log(`从第 ${startIndex + 1} 集开始播放`, 
                    historyIndex !== null ? '(从观看记录恢复)' : '(默认从第一集开始)');
                
                createArtplayerForEpisode(startIndex);
            } else {
                console.error('未找到可播放的剧集');
                showError('未找到可播放的剧集，请返回搜索页面重新搜索');
            }
        
            console.log('=== 播放器初始化完成 ===');
        });
            
        (function() {
            document.getElementById('y-day').textContent =
                Math.floor((new Date() - new Date('<?= $conf['y-day'] ?>')) / 86400000);
        })();
    </script>
</body>
</html>
