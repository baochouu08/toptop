<?php

function get_lovetik_app($tiktok_url) {
    $api_url = 'https://lovetik.app/api/ajaxSearch';
    
    $post_data = http_build_query([
        'q' => $tiktok_url,
        'lang' => 'vi'
    ]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $headers = [
        'authority: lovetik.app',
        'accept: */*',
        'content-type: application/x-www-form-urlencoded; charset=UTF-8',
        'origin: https://lovetik.app',
        'referer: https://lovetik.app/vi',
        'user-agent: Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Mobile Safari/537.36',
        'x-requested-with: XMLHttpRequest'
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        return ['error' => 'Curl Error: ' . curl_error($ch)];
    }
    curl_close($ch);

    if ($response) {
        $json = json_decode($response, true);
        
        if (isset($json['status']) && $json['status'] == 'ok' && isset($json['data'])) {
            $html = $json['data'];
            
            // --- FIX LỖI REGEX Ở ĐÂY ---
            // Dùng dấu ~ để không bị xung đột với dấu / trong https://
            preg_match_all('~href="(https?://[^"]+)"[^>]*class="tik-button-dl~', $html, $matches);

            if (!empty($matches[1])) {
                return [
                    'source' => 'lovetik.app',
                    'status' => 'success',
                    'video_nowatermark' => $matches[1][0], 
                    'video_backup' => $matches[1][1] ?? null,
                    'audio' => end($matches[1])
                ];
            }
        } else {
            return ['error' => 'API Response Failed', 'raw' => $response];
        }
    }

    return ['error' => 'Empty Response'];
}

// TEST
$url = "https://vm.tiktok.com/ZSH3oFDfjpB4u-X6gWQ/";
print_r(get_lovetik_app($url));

?>