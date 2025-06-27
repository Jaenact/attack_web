<?php
session_start();
require_once 'includes/db.php';
require_once 'log_function.php';

$username = $_POST['username'];
$password = $_POST['password'];

// 1. 먼저 admin 테이블에서 확인
$sql_admin = "SELECT * FROM admins WHERE username = :username";
$stmt_admin = $pdo->prepare($sql_admin);
$stmt_admin->execute(['username' => $username]);
$user_admin = $stmt_admin->fetch();

if ($user_admin && password_verify($password, $user_admin['password'])) {
    $_SESSION['admin'] = $user_admin['username'];
    writeLog($pdo, $username, "관리자 로그인 성공 - 계정: $username");
    header("Location: index.php");
    exit();
}

// 2. admin 실패 시 guest 테이블에서 확인
$sql_guest = "SELECT * FROM guests WHERE username = :username";
$stmt_guest = $pdo->prepare($sql_guest);
$stmt_guest->execute(['username' => $username]);
$user_guest = $stmt_guest->fetch();

if ($user_guest && password_verify($password, $user_guest['password'])) {
    $_SESSION['guest'] = $user_guest['username'];
    writeLog($pdo, $username, "게스트 로그인 성공 - 계정: $username");
    header("Location: index.php");
    exit();
}

// 3. 둘 다 실패한 경우
writeLog($pdo, $username, "로그인 실패 - 시도계정: $username, 원인: 잘못된 비밀번호 또는 존재하지 않는 계정");
echo "<script>alert('로그인 실패: 아이디 또는 비밀번호가 잘못되었습니다.'); history.back();</script>";
exit();
?>
