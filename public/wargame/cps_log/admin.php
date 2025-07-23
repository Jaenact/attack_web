<?php
session_start();
if (!isset($_SESSION["auth"]) || $_SESSION["auth"] !== true) {
    header("Location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>연구실 컴퓨터</title>
  <link rel="stylesheet" href="../style.css">
</head>
<body>
  <h2>✅ 로그인 성공</h2>
  <p>FLAG: <strong>CPS{Y2hhbGxlbmdlIDog7J247JqU64uIIOuMgO2ZlCDsgrDslYjrnbTrj5kg7JaR7J6QIOyYgOyDgeuLpQ==}</strong></p>
</body>
<div style="margin-top: 40px; text-align: center;">
  <a href="http://210.102.178.92:9999/wargame/ctf.php" style="
    display: inline-block;
    padding: 12px 24px;
    background-color: #00ffc3;
    color: #000;
    font-weight: bold;
    text-decoration: none;
    border-radius: 8px;
    box-shadow: 0 0 15px #00ffc388;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
  " onmouseover="this.style.transform='scale(1.05)'; this.style.boxShadow='0 0 25px #00ffc3aa';"
     onmouseout="this.style.transform='scale(1)'; this.style.boxShadow='0 0 15px #00ffc388';">
    ← 문제 목록으로 돌아가기
  </a>
</div>
</html>
