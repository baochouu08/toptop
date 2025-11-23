<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$url = $_GET['url'] ?? '';

if (empty($url)) {
    echo json_encode(['status' => 'error', 'msg' => 'Vui long nhap URL']);
    exit;
}

// --- ROUTER ---
if (strpos($url, 'tiktok.com') !== false) {
    $result = get_tiktok_master($url);
} elseif (strpos($url, 'instagram.com') !== false) {
    $result = get_instagram_master($url);
} else {
    $result = ['status' => 'error', 'msg' => 'Chi ho tro TikTok & Instagram'];
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);


// ==========================================
// CONTROLLERS
// ==========================================
function get_tiktok_master($tiktok_url) {
    // 1. SaveTik
    $data = api_general_scraper($tiktok_url, 'https://savetik.io/api/ajaxSearch', 'savetik.io');
    if ($data) return $data;

    // 2. LoveTik
    $cookie = get_real_cookie('https://lovetik.app/vi');
    $data = api_general_scraper($tiktok_url, 'https://lovetik.app/api/ajaxSearch', 'lovetik.app', $cookie);
    if ($data) return $data;

    return ['status' => 'error', 'msg' => 'Server TikTok ban het roi!'];
}

function get_instagram_master($ig_url) {
    $data = api_general_scraper($ig_url, 'https://saveig.app/api/ajaxSearch', 'saveig.app');
    if ($data) {
        $data['platform'] = 'instagram';
        return $data;
    }
    return ['status' => 'error', 'msg' => 'Instagram Server Ban!'];
}

// ==========================================
// CORE SCRAPER (Đã Fix Regex Thumbnail)
// ==========================================
function api_general_scraper($target_url, $api_endpoint, $host, $cookie = null) {
    $post_params = ['q' => $target_url, 'lang' => 'vi'];
    if (strpos($host, 'savetik') !== false) {
        $post_params['cursor'] = '0'; $post_params['page'] = '0';
    }
    if (strpos($host, 'saveig') !== false) {
        $post_params['t'] = 'media'; $post_params['lang'] = 'en';
    }

    $ch = curl_init($api_endpoint);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $rand_ip = mt_rand(1,255).".".mt_rand(0,255).".".mt_rand(0,255).".".mt_rand(0,255);
    if (!$cookie) $cookie = "fpestid=".bin2hex(random_bytes(16));
    
    $headers = [
        'content-type: application/x-www-form-urlencoded; charset=UTF-8',
        'origin: https://' . $host,
        'referer: https://' . $host . '/',
        'user-agent: Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Mobile Safari/537.36',
        'x-forwarded-for: ' . $rand_ip,
        'x-requested-with: XMLHttpRequest',
        "cookie: $cookie"
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $res = curl_exec($ch);
    curl_close($ch);

    if (!$res) return null;
    $json = json_decode($res, true);

    if (isset($json['data'])) {
        $html = $json['data'];
        $result = ['status' => 'success', 'source' => $host];

        // 1. VIDEO URL (Lấy link snapcdn hoặc tiktokcdn)
        preg_match('#href="([^"]*(?:snapcdn|fbcdn|instagram)[^"]*)"#', $html, $m_video);
        if (empty($m_video[1])) {
            // Backup: Lấy bất kỳ link nào có class download
            preg_match('#href="([^"]+)"[^>]*class="[^"]*download[^"]*"#', $html, $m_video);
        }
        if (empty($m_video[1])) return null; // Không có video thì hủy
        $result['video_url'] = str_replace('&amp;', '&', $m_video[1]);


        // 2. THUMBNAIL (Fix: Thử nhiều kiểu Regex)
        // Kiểu 1: Tìm trong div image-tik (thường gặp nhất)
        preg_match('#<div class="image-tik">.*?<img src="([^"]+)"#s', $html, $m_thumb);
        
        // Kiểu 2: Tìm thẻ img bất kỳ có src là tiktokcdn
        if (empty($m_thumb[1])) {
            preg_match('#<img[^>]+src="([^"]*tiktokcdn[^"]*)"#', $html, $m_thumb);
        }
        // Kiểu 3: Lấy đại thẻ img đầu tiên tìm thấy
        if (empty($m_thumb[1])) {
            preg_match('#<img[^>]+src="([^"]+)"#', $html, $m_thumb);
        }
        $result['cover'] = isset($m_thumb[1]) ? str_replace('&amp;', '&', $m_thumb[1]) : '';


        // 3. DESC / TITLE (Fix: Thử nhiều kiểu)
        // Kiểu 1: Thẻ h3
        preg_match('#<h3>(.*?)</h3>#s', $html, $m_desc);
        
        // Kiểu 2: Thẻ p trong content
        if (empty($m_desc[1])) {
            preg_match('#<div class="content">.*?<p>(.*?)</p>#s', $html, $m_desc);
        }
        // Kiểu 3: Lấy từ alt của ảnh
        if (empty($m_desc[1])) {
             preg_match('#<img[^>]+alt="([^"]+)"#', $html, $m_desc);
        }
        
        $desc_raw = $m_desc[1] ?? '';
        $result['desc'] = trim(html_entity_decode(strip_tags($desc_raw)));

        return $result;
    }
    return null;
}

function get_real_cookie($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Mobile Safari/537.36');
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $res = curl_exec($ch);
    $cookie = '';
    if ($res) {
        preg_match_all('/^Set-Cookie:s*([^;]*)/mi', $res, $matches);
        foreach($matches[1] as $item) $cookie .= $item . '; ';
    }
    curl_close($ch);
    return $cookie ? $cookie : "fpestid=".bin2hex(random_bytes(16));
}
?>
