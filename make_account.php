<?php
session_start();
require_once 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $r_password = $_POST['r_password'];

    if ($password !== $r_password) {
        echo "<script>alert('비밀번호가 일치하지 않습니다.'); history.back();</script>";
        exit();
    }

    $sql_1 = "SELECT COUNT(*) FROM guests WHERE username = :username";
    $check = $pdo->prepare($sql_1);
    $check->execute(['username' => $username]);
    if ($check->fetchColumn() > 0) {
        echo "<script>alert('이미 존재하는 아이디입니다.'); history.back();</script>";
        exit();
    }

    $sql = "INSERT INTO guests (username, password) VALUES (:username, :password)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'username' => $username,
        'password' => password_hash($password, PASSWORD_DEFAULT)
    ]);

    echo "<script>alert('회원가입 완료! 로그인 해주세요.'); location.href='login.php';</script>";
    exit();
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>회원가입</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            font-family: sans-serif;
            background-color: #f2f2f2;
            padding: 40px;
        }
        .register-box {
            background: white;
            padding: 30px;
            max-width: 400px;
            margin: auto;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .register-box h2 {
            margin-bottom: 20px;
            font-size: 22px;
        }
        .register-box input {
            width: 100%;
            padding: 10px;
            margin-top: 10px;
            font-size: 16px;
            border: 1px solid #ccc;
            border-radius: 6px;
        }
        .register-box button {
            width: 100%;
            padding: 10px;
            margin-top: 20px;
            background: #3C8DBC; /* 파란 계열 (기존 로그인 버튼 등과 유사) */
            color: white;
            font-size: 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        .register-box button:hover {
            background: #337ab7;
        }
        .register-box a {
            display: block;
            text-align: center;
            margin-top: 15px;
            color: #333;
            text-decoration: none;
        }
    </style>
</head>
<body>
<div class="register-box">
    <h2>📝 회원가입</h2>
    <form method="post" action="make_account.php">
        <input type="text" name="username" placeholder="아이디" required>
        <input type="password" name="password" placeholder="비밀번호" required>
        <input type="password" name="r_password" placeholder="비밀번호 확인" required>
        <button type="submit">가입하기</button>
    </form>
    <a href="login.php">이미 계정이 있나요? 로그인</a>
</div>
</body>
</html>
