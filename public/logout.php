<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../src/db/db.php';
require_once '../src/log/log_function.php';

// 로그아웃 전에 사용자 정보 저장
$currentUser = $_SESSION['admin'] ?? $_SESSION['guest'] ?? 'Unknown';
$userRole = $_SESSION['user_role'] ?? 'Unknown';

$_SESSION = [];
session_unset();

if (ini_get("session.use_cookies")) {
  $params = session_get_cookie_params();
  setcookie(session_name(), '', time() - 42000,
    $params["path"], $params["domain"],
    $params["secure"], $params["httponly"]
  );
}

session_destroy();

// 로그아웃 로그 기록
writeLog($pdo, $currentUser, "로그아웃", "성공", "계정: $currentUser (역할: $userRole)");
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>로그아웃</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="assets/css/main.css">
  <meta http-equiv="refresh" content="2;url=login.php">
  <style>body { background: #f9f9f9; }</style>
</head>
<body>
  <main class="main-content centered" style="min-height:60vh;">
    <div class="card text-center" style="max-width:400px; margin:0 auto;">
      <h2>로그아웃 되었습니다</h2>
      <p>잠시 후 로그인 페이지로 이동합니다.</p>
      <a href="login.php" class="btn btn-primary" style="margin-top:18px;">로그인 페이지로 이동</a>
    </div>
  </main>

</body>
</html>

