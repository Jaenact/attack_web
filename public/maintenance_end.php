<?php
// 점검 종료 안내 페이지
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>점검 종료 안내 | PLC Rotator System</title>
  <meta name="viewport" content="width=1280">
  <link href="https://fonts.googleapis.com/css?family=Pretendard:400,600,700&display=swap" rel="stylesheet">
  <style>
    body { background: #f9f9f9; color: #212121; font-family: 'Pretendard', 'Noto Sans KR', Arial, sans-serif; margin: 0; padding: 0; min-height: 100vh; display: flex; flex-direction: column; }
    .container { max-width: 480px; margin: 0 auto; padding: 0 20px; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 100vh; }
    .end-icon { width: 80px; height: 80px; margin-bottom: 32px; display: flex; align-items: center; justify-content: center; background: #43e97b; border-radius: 50%; }
    .end-icon svg { width: 48px; height: 48px; color: #fff; }
    h1 { font-size: 2.1rem; font-weight: 700; color: #43e97b; margin-bottom: 18px; margin-top: 0; letter-spacing: -0.01em; }
    .desc { font-size: 1.13rem; color: #333; margin-bottom: 32px; text-align: center; line-height: 1.6; }
    .btn-login { background: #005BAC; color: #fff; border: none; border-radius: 8px; padding: 12px 32px; font-size: 1.08rem; font-weight: 600; cursor: pointer; margin-top: 18px; }
    .btn-login:hover { background: #337ab7; }
    @media (max-width: 600px) { .container { max-width: 98vw; padding: 0 4vw; } h1 { font-size: 1.4rem; } }
  </style>
</head>
<body>
  <div class="container">
    <div class="end-icon">
      <svg fill="none" viewBox="0 0 48 48"><circle cx="24" cy="24" r="24" fill="#43e97b"/><path d="M16 24l8 8 8-16" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </div>
    <h1>점검이 종료되었습니다</h1>
    <div class="desc">
      기다려주셔서 감사합니다.<br>
      이제 정상적으로 서비스를 이용하실 수 있습니다.<br>
      <span style="color:#888;font-size:1rem;">아래 버튼을 눌러 로그인 페이지로 이동해 주세요.</span>
    </div>
    <button class="btn-login" onclick="window.location.href='login.php'">로그인 페이지 이동</button>
  </div>
</body>
</html> 