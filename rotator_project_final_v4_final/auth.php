<?php
session_start();
require_once 'includes/db.php';

$username = $_POST['username'];
$password = $_POST['password'];

$sql = "SELECT * FROM admins WHERE username = :username";
$stmt = $pdo->prepare($sql);
$stmt->execute(['username' => $username]);
$user = $stmt->fetch();

if ($user && password_verify($password, $user['password'])) {
    $_SESSION['admin'] = $user['username'];
    header("Location: index.php");
    exit();
} else {
    echo "<script>alert('로그인 실패: 아이디 또는 비밀번호가 잘못되었습니다.'); history.back();</script>";
    exit();
}
?>
