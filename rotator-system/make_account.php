<?php
session_start();
require_once 'includes/db.php';


if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $r_password = $_POST['password'];

    if ($password !== $r_password) {
        echo "<script>alert('비밀번호가 일치하지 않습니다.'); history.back();</script>";
        exit();
    }

    $sql_1 = "SELECT COUNT(*) FROM guests WHERE username = :username";
    $check = $pdo->prepare($sql_1);
    $check -> execute(['username'=> $username]);
    if ($check->fetchColumn() > 0 ) {
        echo "이미 존재하는 아이디입니다.";
    }

    $sql = "INSERT INTO guests (username, password) VALUES(:username, :password)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'username' => $username,
        'password' => password_hash($password, PASSWORD_DEFAULT)]);
    }   

?>


<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>회원가입</title>
  <style>
    body {
      font-family: sans-serif;
      padding: 40px;
      background-color: #f2f2f2;
    }
    .container {
      background: white;
      padding: 30px;
      max-width: 400px;
      margin: auto;
      border-radius: 8px;
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }
    input, button {
      width: 100%;
      padding: 10px;
      margin-top: 10px;
      font-size: 16px;
    }
    button {
      background: #4CAF50;
      color: white;
      border: none;
      cursor: pointer;
    }
    button:hover {
      background: #45a049;
    }
  </style>
</head>
<body>
  <div class="container">
    <h2>회원가입</h2>
    <form action="make_account.php" method="post">
      <input type="text" name="username" placeholder="아이디" required>
      <input type="password" name="password" placeholder="비밀번호" required>
      <input type="password" name="password" placeholder="비밀번호 확인하기" required>
      <button type="submit">가입하기</button>
    </form>
    <a href="login.php">이미 계정이 있나요? 로그인</a>
  </div>
</body>
</html>
