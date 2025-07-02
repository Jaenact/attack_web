<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../src/db/db.php';
require_once '../src/log/log_function.php';

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
writeLog($pdo, $currentUser, "로그아웃", "성공", "계정: $currentUser ($userType)");


header("Location: login.php");
exit;
?>

