const fetch = require('node-fetch');
const randomUseragent = require('random-useragent');

// Phải bọc tất cả trong module.exports như này mới đúng chuẩn Vercel
module.exports = async (req, res) => {
  
  // --- CẤU HÌNH CORS ---
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type');

  if (req.method === 'OPTIONS') {
    return res.status(200).end();
  }

  // --- LẤY URL TIKTOK ---
  let tiktokUrl = req.query.url;
  
  // Nếu request là POST body
  if (req.body) {
      // Vercel đôi khi parse body ra object rồi, đôi khi là string
      let body = req.body;
      if (typeof body === 'string') {
          try { body = JSON.parse(body); } catch(e) {}
      }
      if (body.url || body.q) {
          tiktokUrl = body.url || body.q;
      }
  }

  if (!tiktokUrl) {
    return res.status(400).json({ error: 'Thiếu URL (tham số ?url=)' });
  }

  try {
    const apiUrl = 'https://savetik.io/api/ajaxSearch';
    const userAgent = randomUseragent.getRandom();
    
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
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: params
    });

    const data = await response.json();
    return res.status(200).json(data);

  } catch (error) {
    console.error(error);
    return res.status(500).json({ error: error.message });
  }
};
