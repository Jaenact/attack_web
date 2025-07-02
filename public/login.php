<?php session_start(); ?>
<?php
require_once '../src/db/db.php';
$now = date('Y-m-d H:i:s');
$stmt = $pdo->prepare("SELECT * FROM maintenance WHERE is_active=1 AND start_at <= :now AND end_at >= :now LIMIT 1");
$stmt->execute(['now' => $now]);
if ($stmt->fetch()) {
    header('Location: maintenance.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>PLC 로그인</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="style.css">
  <link href="https://fonts.googleapis.com/css?family=Noto+Sans+KR:400,700&display=swap" rel="stylesheet">
  <style>
    body {
      background: #F5F7FA;
      color: #222;
      font-family: 'Noto Sans KR', 'Nanum Gothic', sans-serif;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }
    .header { display: flex; align-items: center; justify-content: center; background: #005BAC; color: #fff; padding: 0 32px; height: 64px; }
    .logo { display: flex; align-items: center; font-weight: bold; font-size: 1.3rem; letter-spacing: 1px; text-decoration: none; color: #fff; }
    .logo svg { margin-right: 8px; }
    .main-content {
      width: 90vw;
      max-width: 900px;
      margin: 40px auto 0 auto;
      background: #fff;
      border-radius: 8px;
      box-shadow: 0 2px 12px rgba(0,0,0,0.06);
      padding: 40px 60px 48px 60px;
      flex: 1 0 auto;
      min-height: 340px;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }
    .main-content h2 { font-size: 1.7rem; font-weight: 700; color: #005BAC; margin-bottom: 24px; display: flex; align-items: center; gap: 8px; }
    .main-content form { display: flex; flex-direction: column; gap: 16px; }
    .main-content input { width: 100%; padding: 12px; font-size: 16px; border: 1px solid #ccc; border-radius: 6px; }
    .main-content button { width: 100%; padding: 12px; background: #005BAC; color: white; font-size: 16px; border: none; border-radius: 6px; cursor: pointer; margin-top: 8px; }
    .main-content button:hover { background: #337ab7; }
    .main-content a { display: block; text-align: center; margin-top: 18px; color: #005BAC; text-decoration: underline; }
    @media (max-width: 700px) {
      .main-content { width: 98vw; max-width: 98vw; padding: 18px 4vw; }
    }
  </style>
</head>
<body>
  <header class="header" role="banner">
    <a href="index.php" class="logo" aria-label="홈으로">
      <svg width="28" height="28" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><rect width="24" height="24" rx="6" fill="#fff" fill-opacity="0.18"/><path fill="#005BAC" d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8v-10h-8v10zm0-18v6h8V3h-8z"/></svg>
      PLC Rotator System
    </a>
  </header>
  <main id="main-content" class="main-content" tabindex="-1">
    <h2><svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 2a10 10 0 100 20 10 10 0 000-20zm1 17.93V20h-2v-.07A8.001 8.001 0 014.07 13H4v-2h.07A8.001 8.001 0 0111 4.07V4h2v.07A8.001 8.001 0 0119.93 11H20v2h-.07A8.001 8.001 0 0113 19.93z" fill="#005BAC"/></svg>로그인</h2>
    <form action="auth.php" method="post">
      <input type="text" name="username" placeholder="아이디" required>
      <input type="password" name="password" placeholder="비밀번호" required>
      <button type="submit">로그인</button>
    </form>
    <a href="make_account.php">회원가입</a>
  </main>
  <footer class="footer" role="contentinfo" style="background:#222; color:#fff; text-align:center; padding:24px 0 16px 0; font-size:0.95rem; margin-top:48px; flex-shrink:0;">
    <div>가천대학교 CPS |  <a href="#" style="color:#FFB300; text-decoration:underline;">이용약관</a> | <a href="#" style="color:#FFB300; text-decoration:underline;">개인정보처리방침</a> | 고객센터: 1234-5678</div>
    <div style="margin-top:8px;">© 2025 PLC Control</div>
  </footer>
  <?php
  // require_once '../src/db/maintenance_check.php';
  // $maintenance = isMaintenanceActive();
  // if ($maintenance && !isset($_SESSION['admin'])) {
  //   header('Location: maintenance.php');
  //   exit();
  // }
  ?>
</body>
</html>
