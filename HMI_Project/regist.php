<?php
// 회원가입 페이지
session_start();
require_once 'includes/db.php';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>회원가입</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .register-page {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background: #dfe6e9;
        }
        .register-container {
            background: white;
            padding: 40px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            border-radius: 8px;
            text-align: center;
        }
        .register-container h2 {
            margin-bottom: 20px;
        }
        .register-container input {
            width: 100%;
            margin-bottom: 15px;
        }
        .register-container button {
            width: 100%;
        }
    </style>
</head>
<body class="register-page">
<div class="register-container">
    <h2>📝 회원가입</h2>
    <!-- 회원가입 폼 -->
    <form method="POST">
        <input type="text" name="username" placeholder="아이디" required><br>
        <input type="password" name="password" placeholder="비밀번호" required><br>
        <button type="submit">가입하기</button>
    </form>
    <form action="login.php" method="get">
        <button type="submit" style="margin-top:10px; background:#b2bec3; color:#222;">로그인으로 돌아가기</button>
    </form>
    <?php
    // 회원가입 처리
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $username = $_POST['username'];
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (:username, :password)");
            $stmt->execute(['username' => $username, 'password' => $password]);
            echo "<p style='color:green;'>회원가입 성공!</p>";
        } catch (PDOException $e) {
            echo "<p style='color:red;'>이미 존재하는 사용자입니다.</p>";
        }
    }
    ?>
</div>
</body>
</html>