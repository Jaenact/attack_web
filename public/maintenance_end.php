<?php
// 점검 종료 안내 페이지
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>점검 종료 안내 | PLC Rotator System</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://fonts.googleapis.com/css?family=Pretendard:400,600,700&display=swap" rel="stylesheet">
  <style>
    body {
      background: #f7fafd;
      color: #222;
      font-family: 'Pretendard', 'Noto Sans KR', Arial, sans-serif;
      margin: 0; padding: 0;
      min-height: 100vh;
      display: flex; flex-direction: column;
      justify-content: center;
      align-items: center;
    }
    .brand {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-top: 48px;
      margin-bottom: 32px;
      user-select: none;
    }
    .brand-logo {
      width: 44px; height: 44px;
      background: #43e97b;
      border-radius: 12px;
      display: flex; align-items: center; justify-content: center;
    }
    .brand-logo svg {
      width: 28px; height: 28px; color: #fff;
    }
    .brand-name {
      font-size: 1.45rem;
      font-weight: 700;
      color: #43e97b;
      letter-spacing: -0.01em;
    }
    .main-card {
      background: #fff;
      border-radius: 18px;
      box-shadow: 0 2px 12px 0 rgba(0,0,0,0.04);
      padding: 38px 32px 32px 32px;
      max-width: 420px;
      width: 100%;
      display: flex;
      flex-direction: column;
      align-items: center;
      margin-bottom: 24px;
    }
    .end-icon {
      width: 70px; height: 70px;
      background: #e6f9f0;
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      margin-bottom: 18px;
    }
    .end-icon svg {
      width: 38px; height: 38px; color: #43e97b;
    }
    .main-title {
      font-size: 1.5rem;
      font-weight: 700;
      color: #43e97b;
      margin-bottom: 12px;
      margin-top: 0;
      text-align: center;
    }
    .main-desc {
      font-size: 1.08rem;
      color: #444;
      margin-bottom: 18px;
      text-align: center;
      line-height: 1.7;
    }
    .notice {
      font-size: 0.98rem;
      color: #888;
      text-align: center;
      margin-top: 18px;
      margin-bottom: 0;
    }
    .btn-login {
      background: #43e97b;
      color: #fff;
      border: none;
      border-radius: 10px;
      padding: 14px 0;
      font-size: 1.08rem;
      font-weight: 700;
      cursor: pointer;
      width: 100%;
      margin-top: 18px;
      box-shadow: 0 1px 6px rgba(67,233,123,0.08);
      transition: background 0.18s;
    }
    .btn-login:hover { background: #2ecc71; }
    @media (max-width: 600px) {
      .main-card { max-width: 98vw; padding: 28px 4vw 24px 4vw; }
      .brand { margin-top: 24px; margin-bottom: 18px; }
      .brand-logo { width: 36px; height: 36px; }
      .brand-name { font-size: 1.1rem; }
      .main-title { font-size: 1.1rem; }
      .main-desc { font-size: 0.98rem; }
    }
  </style>
</head>
<body>
  <div class="brand">
    <span class="brand-name">PLC Rotator System</span>
  </div>
  <div class="main-card">
    <div class="end-icon">
      <svg fill="none" viewBox="0 0 38 38"><circle cx="19" cy="19" r="19" fill="#43e97b"/><path d="M12 19l7 7 7-14" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </div>
    <div class="main-title">점검 종료</div>
    <div class="main-desc">
      기다려주셔서 감사합니다.<br>
      이제 정상적으로 서비스를 이용하실 수 있습니다.<br>
    </div>
    <div class="notice">
      아래 버튼을 눌러 로그인 페이지로 이동해 주세요.
    </div>
    <button class="btn-login" onclick="window.location.href='login.php'">로그인 페이지 이동</button>
  </div>
</body>
</html> 