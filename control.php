<?php
session_start();
require_once 'includes/db.php';
require_once 'log_function.php';

// 로그인 체크
if (!isset($_SESSION['admin']) && !isset($_SESSION['guest'])) {
    header('Location: login.php');
    exit();
}

// admin만 제어 가능하도록 설정
$isAdmin = isset($_SESSION['admin']);
$currentUser = $isAdmin ? $_SESSION['admin'] : $_SESSION['guest'];

if (!isset($_SESSION['machine_status'])) {
    $_SESSION['machine_status'] = 'off';
}

// admin만 POST 요청 처리 가능
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];

    if ($action === 'on' || $action === 'off') {
        $previous_status = $_SESSION['machine_status'];
        $_SESSION['machine_status'] = $action;
        $rpm = $_POST['rpm'] ?? 0;
        $previous_rpm = $_SESSION['current_rpm'] ?? 0;

        // 현재 RPM 저장
        $_SESSION['current_rpm'] = $rpm;

        // 자세한 제어 로그 메시지 생성
        $logMessage = "장비 제어 - 상태변경: $previous_status → $action";
        if ($rpm != $previous_rpm) {
            $logMessage .= ", 회전수변경: $previous_rpm → $rpm RPM";
        } else {
            $logMessage .= ", 회전수: $rpm RPM (변경없음)";
        }

        writeLog($pdo, $currentUser, $logMessage);
        
        echo "<script>alert('장비 상태: $action'); location.href='control.php';</script>";
        exit();
    }
} 

// 기본 상태 설정
$isOn = $_SESSION['machine_status'] === 'on';
$currentRpm = $_SESSION['current_rpm'] ?? 0;

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
    .rpm-display {
      background: #f8f9fa;
      padding: 15px;
      border-radius: 5px;
      margin-bottom: 15px;
      text-align: center;
      border: 1px solid #dee2e6;
    }
    .rpm-value {
      font-size: 24px;
      font-weight: bold;
      color: #495057;
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
    .user-info {
      background: #e3f2fd;
      padding: 10px;
      border-radius: 5px;
      margin-bottom: 15px;
      text-align: center;
      border: 1px solid #2196f3;
    }
    .admin-controls {
      border-top: 2px solid #27ae60;
      padding-top: 20px;
      margin-top: 20px;
    }
    .view-only {
      background: #f5f5f5;
      padding: 15px;
      border-radius: 5px;
      text-align: center;
      color: #666;
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

      <div class="user-info">
        👤 현재 사용자: <?= htmlspecialchars($currentUser) ?> 
        <?= $isAdmin ? ' (관리자)' : ' (일반 사용자)' ?>
      </div>

      <div class="status-indicator">
        <?= $isOn ? '✅ 작동 중 (ON)' : '⛔ 정지 상태 (OFF)' ?>
      </div>

      <div class="rpm-display">
        <div>현재 회전수</div>
        <div class="rpm-value"><?= number_format($currentRpm) ?> RPM</div>
      </div>

      <?php if ($isAdmin): ?>
        <!-- 관리자 전용 제어 영역 -->
        <div class="admin-controls">
          <h3>🔧 관리자 제어</h3>
          
          <form method="post" action="control.php">
            <div class="btn-group">
              <button name="action" value="on" style="background-color:#27ae60">ON</button>
              <button name="action" value="off" style="background-color:#c0392b">OFF</button>
            </div>
          </form>

          <form method="post" action="control.php">
            <label>회전 수 설정 (RPM):</label><br>
            <input type="number" name="rpm" min="0" max="10000" value="<?= $currentRpm ?>" <?= $isOn ? '' : 'disabled' ?>><br><br>
            <button name="action" value="<?= $isOn ? 'on' : 'off' ?>" <?= $isOn ? '' : 'disabled' ?>>회전수 적용</button>
          </form>
        </div>
      <?php else: ?>
        <!-- 일반 사용자용 읽기 전용 영역 -->
        <div class="view-only">
          <p>📋 현재 장치 상태만 확인 가능합니다.</p>
          <p>장치 제어는 관리자 권한이 필요합니다.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
