<?php
session_start();
require_once 'includes/db.php';


if (!isset($_SESSION['admin'])) {
  echo "<script>alert('권한이 없습니다. 강제 로그아웃 합니다.'); location.href='login.php'</script>";
  exit();
}



function writeLog($pdo, $username, $action) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $sql = "INSERT INTO logs (username, action, ip_address) VALUES (:username, :action, :ip)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'username' => $username,
        'action' => $action,
        'ip' => $ip
    ]);
}


$sql = "SELECT * FROM logs ORDER BY created_at DESC";
$stmt = $pdo->query($sql);
$logs = $stmt->fetchAll();

foreach ($logs as $log) {
    echo "<p>[{$log['created_at']}] {$log['username']} - {$log['action']} ({$log['ip_address']})</p>";
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
      <li><a href="logs.php">📋 로그</a></li>
      <li><a href="logout.php">🔓 로그아웃</a></li>
    </ul>
  </div>
</body>
</html>