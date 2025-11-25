import fetch from 'node-fetch';
import randomUseragent from 'random-useragent';

export default async function handler(req, res) {
  // --- 1. BẢO MẬT (Chỉ cho phép Web của sếp gọi) ---
  const allowedOrigins = ['https://snaptik.business', 'http://localhost:3000']; // Thay bằng domain thật của sếp
  const origin = req.headers.origin;
  
  if (allowedOrigins.includes(origin)) {
    res.setHeader('Access-Control-Allow-Origin', origin);
  } else {
    // Mở tạm cho mọi người test (Sau này sếp nên đóng lại bằng cách xóa dòng dưới)
    res.setHeader('Access-Control-Allow-Origin', '*');
  }
  
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type');

  // Xử lý preflight request của trình duyệt
  if (req.method === 'OPTIONS') {
    res.status(200).end();
    return;
  }

  // --- 2. LẤY URL TỪ REQUEST ---
  // Chấp nhận cả GET (?url=...) và POST body
  let tiktokUrl = '';
  if (req.method === 'GET') {
    tiktokUrl = req.query.url;
  } else if (req.method === 'POST') {
    const body = req.body;
    tiktokUrl = body.url || body.q;
  }

  if (!tiktokUrl) {
    return res.status(400).json({ error: 'Thiếu URL TikTok (tham số ?url=)' });
  }

  try {
    // --- 3. FAKE REQUEST SANG SAVETIK ---
    const apiUrl = 'https://savetik.io/api/ajaxSearch';
    const userAgent = randomUseragent.getRandom(); // Random UA mỗi lần gọi

    const params = new URLSearchParams();
    params.append('q', tiktokUrl);
    params.append('lang', 'en');

    const response = await fetch(apiUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'User-Agent': userAgent,
        'Referer': 'https://savetik.io/',
        'Origin': 'https://savetik.io',
        'Accept': '*/*',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: params
    });

    const data = await response.json();

    // --- 4. TRẢ KẾT QUẢ VỀ ---
    return res.status(200).json(data);

  } catch (error) {
    console.error('Lỗi Proxy:', error);
    return res.status(500).json({ 
      error: 'Lỗi khi gọi sang Savetik', 
      details: error.message 
    });
  }
}    return ['status' => 'error', 'msg' => 'Server TikTok ban het roi!'];
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
