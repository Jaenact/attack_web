<?php session_start(); ?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>PLC 관리자 로그인</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .login-page {
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
      background: #dfe6e9;
    }
    .login-container {
      background: white;
      padding: 40px;
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
      border-radius: 8px;
      text-align: center;
    }
    .login-container h2 {
      margin-bottom: 20px;
    }
    .login-container input {
      width: 100%;
      margin-bottom: 15px;
    }
    .login-container button {
      width: 100%;
    }
  </style>
</head>
<body class="login-page">
  <div class="login-container">
    <h2>🔐 로그인</h2>
    <form action="auth.php" method="post">
      <input type="text" name="username" placeholder="아이디" required>
      <input type="password" name="password" placeholder="비밀번호" required>
      <button type="submit">로그인</button>
    </form>
      <form action="regist.php" method="get">
          <button>회원가입</button>
      </form>
  </div>
</body>
</html>
