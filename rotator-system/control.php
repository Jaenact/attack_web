<?php
session_start();
require_once 'includes/db.php';
require_once 'log_function.php';

if (!isset($_SESSION['admin']) && !isset($_SESSION['guest'])) {
  header("Location: login.php");
  exit();
}



if(isset($_SESSION['admin'])) { 
  if (!isset($_SESSION['machine_status'])) {
    $_SESSION['machine_status'] = 'off';
  }


  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'];

    if ($action === 'on' || $action === 'off') {
     $_SESSION['machine_status'] = $action;
     $rpm = $_POST['rpm'] ?? 0;

      if (isset($_SESSION['admin'])) {
       $username = $_SESSION['admin'];
       writeLog($pdo, $username, "장비 상태 변경: $action, 회전수: $rpm");
      }
      
      echo "<script>alert('장비 상태: $action'); location.href='control.php';</script>";
      exit();
    }
  }
} 


if (!isset($_SESSION['machine_status'])) {
  $_SESSION['machine_status'] = 'off';
}
// 기본 상태 설정
$isOn = $_SESSION['machine_status'] === 'on';

?>

<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>회전기 제어</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .control-box {
      background: white;
      padding: 30px;
      border-radius: 10px;
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
      max-width: 400px;
    }
    .control-box h2 {
      margin-bottom: 20px;
    }
    .status-indicator {
      padding: 10px;
      margin-bottom: 15px;
      border-radius: 5px;
      text-align: center;
      color: white;
      font-weight: bold;
      background-color: <?= $isOn ? '#2ecc71' : '#e74c3c' ?>;
    }
    .btn-group {
      display: flex;
      gap: 10px;
      margin-bottom: 20px;
    }
    .btn-group button {
      flex: 1;
    }
    input[type="number"]:disabled {
      background-color: #eee;
    }
  </style>
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
  <div class="main">
    <div class="control-box">
      <h2>🔧 회전기 상태 제어</h2>

      <div class="status-indicator">
        <?= $isOn ? '✅ 작동 중 (ON)' : '⛔ 정지 상태 (OFF)' ?>
      </div>

      <form method="post" action="control.php">
        <div class="btn-group">
          <button name="action" value="on" style="background-color:#27ae60">ON</button>
          <button name="action" value="off" style="background-color:#c0392b">OFF</button>
        </div>
      </form>

      <form method="post" action="control.php">
        <label>회전 수 (RPM):</label><br>
        <input type="number" name="rpm" min="0" max="10000" <?= $isOn ? '' : 'disabled' ?>><br><br>
        <button name="action" value="<?= $isOn ? 'on' : 'disabled' ?>" <?= $isOn ? '' : 'disabled' ?>>회전수 적용</button>
      </form>
    </div>
  </div>
</body>
</html>
