<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/log.php';

function isLoggedIn() {
    return isset($_SESSION['username']);
}

function isAdmin() {
    return isset($_SESSION['admin']) && $_SESSION['admin'] === true;
}

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

// 관리자 로그인 시도
$sql_admin = "SELECT * FROM admins WHERE username = :username";
$stmt = $pdo->prepare($sql_admin);
$stmt->execute(['username' => $username]);
$admin = $stmt->fetch();

if ($admin && password_verify($password, $admin['password'])) {
    $_SESSION['username'] = $admin['username'];
    $_SESSION['user_id'] = $admin['id'];
    $_SESSION['admin'] = true;
    write_log("로그인 성공", "관리자: $username");
    header("Location: index.php");
    exit();
}

// 일반 사용자 로그인 시도
$sql_user = "SELECT * FROM users WHERE username = :username";
$stmt = $pdo->prepare($sql_user);
$stmt->execute(['username' => $username]);
$user = $stmt->fetch();

if ($user && password_verify($password, $user['password'])) {
    $_SESSION['username'] = $user['username'];
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['admin'] = false;
    write_log("로그인 성공", "사용자: $username");
    header("Location: index.php");
    exit();
}

write_log("로그인 실패", "사용자명: $username");
header("Location: login.php?error=아이디나 비밀번호가 잘못되었습니다");
exit();
