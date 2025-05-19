<?php
// 회전기 제어: 관리자만 제어 가능, 일반 사용자는 상태만 확인
session_start();
require_once 'includes/db.php';
require_once 'includes/log.php';

// 로그인 체크
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$isAdmin = isset($_SESSION['admin']) && $_SESSION['admin'] === true;

// 장비 상태 초기화
if (!isset($_SESSION['machine_status'])) {
    $_SESSION['machine_status'] = 'off';
}

// 관리자만 제어 가능
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    $rpm = $_POST['rpm'] ?? 0;
    if ($action === 'on' || $action === 'off') {
        $_SESSION['machine_status'] = $action;
        write_log("장비 상태 변경", "상태: $action, RPM: $rpm");
        echo "<script>alert('장비 상태: $action'); location.href='control.php';</script>";
        exit();
    }
}

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
        .view-only {
            opacity: 0.7;
            pointer-events: none;
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
            <li><a href="logs.php">📝 시스템 로그</a></li>
            <li><a href="logout.php">🔓 로그아웃</a></li>
        </ul>
    </div>

    <div class="main">
        <div class="control-box">
            <h2>🔧 회전기 상태 <?= $isAdmin ? '제어' : '확인' ?></h2>

            <div class="status-indicator">
                <?= $isOn ? '✅ 작동 중 (ON)' : '⛔ 정지 상태 (OFF)' ?>
            </div>

            <?php if ($isAdmin): ?>
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
            <?php else: ?>
            <div class="view-only">
                <div class="btn-group">
                    <button style="background-color:#27ae60">ON</button>
                    <button style="background-color:#c0392b">OFF</button>
                </div>
                <div>
                    <label>회전 수 (RPM):</label><br>
                    <input type="number" disabled><br><br>
                    <button disabled>회전수 적용</button>
                </div>
            </div>
            <p style="text-align: center; color: #666; margin-top: 20px;">
                ⚠️ 일반 사용자는 상태 확인만 가능합니다.
            </p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
