<?php

session_start();
require_once 'includes/db.php';
require_once 'log_function.php';

// 로그아웃 전에 사용자 정보 저장
$currentUser = $_SESSION['admin'] ?? $_SESSION['guest'] ?? 'Unknown';
$userType = isset($_SESSION['admin']) ? '관리자' : (isset($_SESSION['guest']) ? '게스트' : 'Unknown');

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
writeLog($pdo, $currentUser, "로그아웃 - 계정: $currentUser ($userType)");


header("Location: login.php");
exit;
?>

