<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once '../src/db/db.php';
require_once '../src/log/log_function.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    // 관리자 인증 (role='admin')
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username=? AND role='admin'");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['admin'] = $username;
        writeLog($pdo, $username, '관리자로그인', '성공', 'admin_login');
        header('Location: index.php');
        exit();
    } else {
        $error = "관리자 계정 또는 비밀번호가 올바르지 않습니다.";
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>관리자 로그인</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://fonts.googleapis.com/css?family=Pretendard:400,600,700&display=swap" rel="stylesheet">
  <style>
    body {
      background: #f7fafd;
      color: #222;
      font-family: 'Pretendard', 'Noto Sans KR', Arial, sans-serif;
      min-height: 100vh;
      margin: 0; padding: 0;
      display: flex; flex-direction: column; align-items: center; justify-content: center;
    }
    .admin-login-card {
      background: #fff;
      border-radius: 18px;
      box-shadow: 0 2px 12px 0 rgba(0,0,0,0.04);
      padding: 38px 32px 32px 32px;
      max-width: 380px;
      width: 100%;
      display: flex;
      flex-direction: column;
      align-items: center;
    }
    .admin-title {
      font-size: 1.5rem;
      font-weight: 700;
      color: #003366;
      margin-bottom: 18px;
      margin-top: 0;
      text-align: center;
    }
    .admin-login-card form {
      width: 100%;
      display: flex;
      flex-direction: column;
      gap: 14px;
    }
    .admin-login-card input {
      width: 100%;
      padding: 12px;
      font-size: 1rem;
      border: 1.5px solid #b3c6e0;
      border-radius: 8px;
      margin-bottom: 2px;
    }
    .admin-login-card button {
      width: 100%;
      padding: 12px;
      background: #003366;
      color: #fff;
      font-size: 1.08rem;
      font-weight: 700;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      margin-top: 8px;
      transition: background 0.18s;
    }
    .admin-login-card button:hover { background: #005BAC; }
    .admin-login-card .error {
      color: #e74c3c;
      font-size: 1rem;
      margin-bottom: 8px;
      text-align: center;
    }
    @media (max-width: 600px) {
      .admin-login-card { max-width: 98vw; padding: 28px 4vw 24px 4vw; }
      .admin-title { font-size: 1.1rem; }
    }
  </style>
</head>
<body>
  <div class="admin-login-card">
    <div class="admin-title">관리자 로그인</div>
    <?php if (!empty($error)) echo '<div class="error">'.$error.'</div>'; ?>
    <form method="post">
      <input type="text" name="username" placeholder="관리자 아이디" required autocomplete="username">
      <input type="password" name="password" placeholder="비밀번호" required autocomplete="current-password">
      <button type="submit">로그인</button>
    </form>
  </div>
</body>
</html> 