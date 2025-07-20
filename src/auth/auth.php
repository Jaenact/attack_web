<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/../log/log_function.php';

$username = $_POST['username'];
$password = $_POST['password'];

$sql = "SELECT * FROM users WHERE username = '$username' AND is_active = 1";
$result = $pdo->query($sql);
$user = $result->fetch();

if ($user) {
    $passwordValid = password_verify($password, $user['password']);

    if ($passwordValid) {
        if ($user['role'] === 'admin') {
            $_SESSION['admin'] = $user['username'];
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = 'admin';
            writeLog($pdo, $username, "로그인", "성공", "관리자 로그인 성공 - 계정: $username");

            echo "<script>alert('관리자로 로그인되었습니다.'); location.href='index.php';</script>";
            exit();
        } else {
            $_SESSION['guest'] = $user['username'];
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = 'guest';
            writeLog($pdo, $username, "로그인", "성공", "게스트 로그인 성공 - 계정: $username");

            echo "<script>alert('게스트로 로그인되었습니다.'); location.href='index.php';</script>";
            exit();
        }
    }
}

// 로그인 실패
writeLog($pdo, $username, "로그인", "실패", "시도계정: $username, 원인: 잘못된 비밀번호 또는 존재하지 않는 계정");
echo "<script>alert('로그인 실패: 아이디 또는 비밀번호가 잘못되었습니다.'); history.back();</script>";
exit();
?>
