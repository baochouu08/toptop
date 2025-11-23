<?php
// Cấu hình Header trả về JSON để web sếp dễ đọc
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *'); // Cho phép mọi web gọi vào (CORS)

// Lấy URL từ tham số ?url=...
$url = $_GET['url'] ?? '';

if (empty($url)) {
    echo json_encode([
        'status' => 'error',
        'msg' => 'Vui long nhap URL TikTok (Vi du: ?url=https://vm.tiktok.com/...)'
    ]);
    exit;
}

// --- BẮT ĐẦU QUY TRÌNH XỬ LÝ ---
$result = get_tiktok_master($url);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);


// ==========================================
// HÀM ĐIỀU PHỐI (Commander)
// ==========================================
function get_tiktok_master($tiktok_url) {
    // ƯU TIÊN 1: SaveTik (Vì nó ổn định hơn)
    $data = api_savetik($tiktok_url);
    if ($data) return $data;

    // ƯU TIÊN 2: LoveTik (Cứu nét)
    $data = api_lovetik($tiktok_url);
    if ($data) return $data;

    // HẾT CÁCH
    return [
        'status' => 'error', 
        'msg' => 'Server ban qua, vui long thu lai sau!'
    ];
}

// ==========================================
// API 1: SAVETIK
// ==========================================
function api_savetik($url) {
    $api = 'https://savetik.io/api/ajaxSearch';
    $post = http_build_query(['q' => $url, 'cursor' => '0', 'page' => '0', 'lang' => 'vi']);
    return send_request($api, $post, 'savetik.io');
}

// ==========================================
// API 2: LOVETIK
// ==========================================
function api_lovetik($url) {
    // Bước 1: Lấy Cookie xịn từ LoveTik (Tránh bị chặn)
    $cookie = get_real_cookie('https://lovetik.app/vi');
    
    $api = 'https://lovetik.app/api/ajaxSearch';
    $post = http_build_query(['q' => $url, 'lang' => 'vi']);
    return send_request($api, $post, 'lovetik.app', $cookie);
}

// ==========================================
// HÀM HỖ TRỢ (Core)
// ==========================================

// Hàm gửi request chuẩn Ninja
function send_request($api_url, $post_data, $host, $cookie = null) {
    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Timeout 10s

    // Fake IP Header (Để lừa server gà)
    $rand_ip = mt_rand(1,255).".".mt_rand(0,255).".".mt_rand(0,255).".".mt_rand(0,255);
    
    // Nếu không có cookie xịn thì fake cookie đểu
    if (!$cookie) {
        $rand_id = bin2hex(random_bytes(16));
        $cookie = "fpestid=$rand_id";
    }
    
    $headers = [
        'content-type: application/x-www-form-urlencoded; charset=UTF-8',
        'origin: https://' . $host,
        'referer: https://' . $host . '/vi',
        'user-agent: Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Mobile Safari/537.36',
        'x-forwarded-for: ' . $rand_ip,
        'x-real-ip: ' . $rand_ip,
        'x-requested-with: XMLHttpRequest',
        "cookie: $cookie"
    ];
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $res = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Nếu lỗi mạng hoặc HTTP lỗi -> Bỏ qua
    if ($http_code != 200 || !$res) return null;

    $json = json_decode($res, true);
    if (isset($json['data'])) {
        // Regex tìm link SnapCDN hoặc TiktokCDN
        preg_match_all('#href="([^"]*(?:snapcdn.app|tiktokcdn.com)[^"]*)"#', $json['data'], $m);
        if (!empty($m[1])) {
            return [
                'status' => 'success',
                'source' => $host,
                'video_url' => $m[1][0], // Link ngon nhất
                'backup_url' => $m[1][1] ?? null
            ];
        }
    }
    return null;
}

// Hàm lấy Cookie thật (Chống chặn LoveTik)
function get_real_cookie($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Mobile Safari/537.36');
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    
    $res = curl_exec($ch);
    $cookie_str = '';
    
    if ($res) {
        preg_match_all('/^Set-Cookie:s*([^;]*)/mi', $res, $matches);
        foreach($matches[1] as $item) {
            $cookie_str .= $item . '; ';
        }
    }
    curl_close($ch);
    return $cookie_str ? $cookie_str : "fpestid=".bin2hex(random_bytes(16));
}
?>
