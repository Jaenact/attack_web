<?php
session_start();
require_once '../src/db/db.php';
require_once '../src/log/log_function.php';
// require_once '../src/db/maintenance_check.php';
// maintenanceRedirectIfNeeded();

// PHPIDS 라이브러리 로딩 (선택적)
$phpids_available = false;
if (file_exists(__DIR__ . '/../PHPIDS/lib/IDS/Init.php')) {
    try {
        require_once __DIR__ . '/../PHPIDS/lib/IDS/Init.php';
        require_once __DIR__ . '/../PHPIDS/lib/IDS/Monitor.php';
        require_once __DIR__ . '/../PHPIDS/lib/IDS/Report.php';
        require_once __DIR__ . '/../PHPIDS/lib/IDS/Filter/Storage.php';
        require_once __DIR__ . '/../PHPIDS/lib/IDS/Caching/CacheFactory.php';
        require_once __DIR__ . '/../PHPIDS/lib/IDS/Caching/CacheInterface.php';
        require_once __DIR__ . '/../PHPIDS/lib/IDS/Caching/FileCache.php';
        require_once __DIR__ . '/../PHPIDS/lib/IDS/Filter.php';
        require_once __DIR__ . '/../PHPIDS/lib/IDS/Event.php';
        require_once __DIR__ . '/../PHPIDS/lib/IDS/Converter.php';
        $phpids_available = true;
    } catch (Exception $e) {
        // PHPIDS 로딩 실패 시 무시
        $phpids_available = false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'];
    $r_password = $_POST['r_password'];
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $profile_img = null;
    
    // 필수 필드 검증
    if (empty($username) || empty($password)) {
        echo "<script>alert('아이디와 비밀번호는 필수입니다.'); history.back();</script>";
        exit();
    }

    // --- PHPIDS 공격 탐지: 입력값만 별도 검사 ---
    if ($phpids_available) {
        try {
            $request = [
                'POST' => [
                    'username' => $username,
                    'name' => $name,
                    'phone' => $phone
                ]
            ];
            $init = \IDS\Init::init(__DIR__ . '/../PHPIDS/lib/IDS/Config/Config.ini.php');
            $ids = new \IDS\Monitor($init);
            $result = $ids->run($request);
            if (!$result->isEmpty()) {
                $userInput = [
                    'username' => $username,
                    'name' => $name,
                    'phone' => $phone
                ];
                $logMessage = format_phpids_event($result, '회원가입', $userInput);
                $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
                $stmt = $pdo->prepare('INSERT INTO logs (username, action, log_message, ip_address) VALUES (?, ?, ?, ?)');
                $stmt->execute([$username, '공격감지', $logMessage, $ip]);
            }
        } catch (Exception $e) {
            // PHPIDS 오류 시 로그 기록
            $logMessage = 'PHPIDS 오류: ' . $e->getMessage();
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            $stmt = $pdo->prepare('INSERT INTO logs (username, action, log_message, ip_address) VALUES (?, ?, ?, ?)');
            $stmt->execute([$username, 'PHPIDS오류', $logMessage, $ip]);
        }
    }

    if ($password !== $r_password) {
        echo "<script>alert('비밀번호가 일치하지 않습니다.'); history.back();</script>";
        exit();
    }

    $sql_1 = "SELECT COUNT(*) FROM users WHERE username = :username";
    $check = $pdo->prepare($sql_1);
    $check->execute(['username' => $username]);
    if ($check->fetchColumn() > 0) {
        echo "<script>alert('이미 존재하는 아이디입니다.'); history.back();</script>";
        exit();
    }

    // 프로필 이미지 업로드
    if (isset($_FILES['profile_img']) && $_FILES['profile_img']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/profile/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $origin_name = basename($_FILES['profile_img']['name']);
        $ext = pathinfo($origin_name, PATHINFO_EXTENSION);
        $new_name = uniqid('profile_') . '.' . $ext;
        $target = $upload_dir . $new_name;
        if (move_uploaded_file($_FILES['profile_img']['tmp_name'], $target)) {
            $profile_img = $new_name;
        }
    }

    $sql = "INSERT INTO users (username, password, role, name, phone, profile_img) VALUES (:username, :password, 'guest', :name, :phone, :profile_img)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'username' => $username,
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'name' => $name,
        'phone' => $phone,
        'profile_img' => $profile_img
    ]);

    echo "<script>alert('회원가입 완료! 로그인 해주세요.'); location.href='login.php';</script>";
    writeLog($pdo, $username, '회원가입', '성공');
    exit();
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>회원가입</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css?family=Noto+Sans+KR:400,700&display=swap" rel="stylesheet">
    <style>
        body { background: #F5F7FA; color: #222; font-family: 'Noto Sans KR', 'Nanum Gothic', sans-serif; min-height: 100vh; display: flex; flex-direction: column; }
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
    <h2><svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 2a10 10 0 100 20 10 10 0 000-20zm1 17.93V20h-2v-.07A8.001 8.001 0 014.07 13H4v-2h.07A8.001 8.001 0 0111 4.07V4h2v.07A8.001 8.001 0 0119.93 11H20v2h-.07A8.001 8.001 0 0113 19.93z" fill="#005BAC"/></svg>회원가입</h2>
    <form method="post" action="make_account.php" enctype="multipart/form-data" class="card" style="max-width:400px;margin:40px auto 0 auto;">
        <h2 class="card-title">회원가입</h2>
        <label for="username">아이디 <span style="color: #ff4757; font-weight: bold;">*</span></label>
        <input type="text" name="username" id="username" class="input" required>
        <label for="password">비밀번호 <span style="color: #ff4757; font-weight: bold;">*</span></label>
        <input type="password" name="password" id="password" class="input" required>
        <label for="r_password">비밀번호 확인 <span style="color: #ff4757; font-weight: bold;">*</span></label>
        <input type="password" name="r_password" id="r_password" class="input" required>
        <label for="name">이름 (선택사항)</label>
        <input type="text" name="name" id="name" class="input" placeholder="이름을 입력하세요">
        <label for="phone">연락처 (선택사항)</label>
        <input type="text" name="phone" id="phone" class="input" placeholder="연락처를 입력하세요">
        <label for="profile_img">프로필 이미지</label>
        <input type="file" name="profile_img" id="profile_img" class="input" accept="image/*">
        <button type="submit" class="btn-primary" style="width:100%;margin-top:18px;">가입하기</button>
    </form>
    <a href="login.php">이미 계정이 있나요? 로그인</a>
  </main>
  <footer class="footer" role="contentinfo" style="background:#222; color:#fff; text-align:center; padding:24px 0 16px 0; font-size:0.95rem; margin-top:48px; flex-shrink:0;">
    <div>가천대학교 CPS |  <a href="#" style="color:#FFB300; text-decoration:underline;">이용약관</a> | <a href="#" style="color:#FFB300; text-decoration:underline;">개인정보처리방침</a> | 고객센터: 1234-5678</div>
    <div style="margin-top:8px;">© 2025 PLC Control</div>
  </footer>
</body>
</html>
