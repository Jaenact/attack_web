<?php session_start();
if (!isset($_SESSION['admin'])) {
  header("Location: login.php");
  exit();
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>PLC 대시보드</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="sidebar">
    <h3>PLC 제어</h3>
    <ul>
      <li><a href="index.php">🏠 대시보드</a></li>
      <li><a href="control.php">⚙ 회전기 제어</a></li>
      <li><a href="faults.php">🚨 고장 게시판</a></li>
      <li><a href="logout.php">🔓 로그아웃</a></li>
    </ul>
  </div>

  <div class="main">
    <h2>📊 시스템 개요</h2>
    <p>환영합니다, <strong><?= $_SESSION['admin'] ?></strong>님!</p>
    <p>좌측 메뉴를 통해 제어 기능을 이용하거나 시스템을 확인할 수 있습니다.</p>
  </div>
</body>
</html>
