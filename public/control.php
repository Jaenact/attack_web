<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../src/db/db.php';
require_once '../src/log/log_function.php';
// require_once '../src/db/maintenance_check.php';

// PHPIDS 라이브러리 로딩
require_once __DIR__ . '/../PHPIDS/lib/IDS/Init.php';
require_once __DIR__ . '/../PHPIDS/lib/IDS/Monitor.php';
require_once __DIR__ . '/../PHPIDS/lib/IDS/Report.php';
require_once __DIR__ . '/../PHPIDS/lib/IDS/Filter/Storage.php';
require_once __DIR__ . '/../PHPIDS/lib/IDS/Caching/CacheFactory.php';
require_once __DIR__ . '/../PHPIDS/lib/IDS/Caching/CacheInterface.php';
require_once __DIR__ . '/../PHPIDS/lib/IDS/Caching/FileCache.php';
require_once __DIR__ . '/../PHPIDS/lib/IDS/Filter.php';
require_once __DIR__ . '/../PHPIDS/lib/IDS/Event.php';
require_once __DIR__ . '/../PHPIDS/lib/IDS/Converter.php';

use IDS\Init;
use IDS\Monitor;

// 로그인 체크
if (!isset($_SESSION['admin']) && !isset($_SESSION['guest'])) {
    header('Location: login.php');
    exit();
}

// admin만 제어 가능하도록 설정
$isAdmin = isset($_SESSION['admin']);
$currentUser = $isAdmin ? $_SESSION['admin'] : $_SESSION['guest'];

// DB에서 장비 상태 읽기
$row = $pdo->query("SELECT status, rpm FROM machine_status WHERE id=1")->fetch();
$isOn = $row ? $row['status'] === 'on' : false;
$currentRpm = $row ? $row['rpm'] : 0;

// admin만 POST 요청 처리 가능
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    $rpm = $_POST['rpm'] ?? $currentRpm;
    // --- PHPIDS 공격 탐지: 입력값만 별도 검사 ---
    try {
        $request = [
            'POST' => [
                'action' => $action,
                'rpm' => $rpm
            ]
        ];
        $init = Init::init(__DIR__ . '/../PHPIDS/lib/IDS/Config/Config.ini.php');
        $ids = new Monitor($init);
        $result = $ids->run($request);
        if (!$result->isEmpty()) {
            $impact = $result->getImpact();
            $logMessage = 'PHPIDS 제어 입력값 공격 감지! 임팩트: ' . $impact . ', 상세: ' . print_r($result, true);
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            $stmt = $pdo->prepare('INSERT INTO logs (username, action, log_message, ip_address) VALUES (?, ?, ?, ?)');
            $stmt->execute([$currentUser, '공격감지', $logMessage, $ip]);
        }
    } catch (Exception $e) {
        // PHPIDS 오류 시 로그 기록
        $logMessage = 'PHPIDS 오류: ' . $e->getMessage();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $stmt = $pdo->prepare('INSERT INTO logs (username, action, log_message, ip_address) VALUES (?, ?, ?, ?)');
        $stmt->execute([$currentUser, 'PHPIDS오류', $logMessage, $ip]);
    }
    if ($action === 'on' || $action === 'off') {
        $pdo->prepare("UPDATE machine_status SET status=?, rpm=? WHERE id=1")->execute([$action, $rpm]);
        $logMessage = "장비 제어 - 상태변경: ".$row['status']." → ".$action;
        if ($rpm != $row['rpm']) {
            $logMessage .= ", 회전수변경: ".$row['rpm']." → ".$rpm." RPM";
        } else {
            $logMessage .= ", 회전수: ".$rpm." RPM (변경없음)";
        }
        writeLog($pdo, $currentUser, '장비제어', '성공', $logMessage);
        echo "<script>alert('장비 상태: $action, 회전수: $rpm RPM'); location.href='control.php';</script>";
        exit();
    }
    // 회전수만 적용
    if ($action === 'apply_rpm') {
        $pdo->prepare("UPDATE machine_status SET rpm=? WHERE id=1")->execute([$rpm]);
        $logMessage = "장비 제어 - 회전수변경: ".$row['rpm']." → ".$rpm." RPM (상태: ".$row['status'].")";
        writeLog($pdo, $currentUser, '장비제어', '성공', $logMessage);
        echo "<script>alert('회전수 적용: $rpm RPM'); location.href='control.php';</script>";
        exit();
    }
} 
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>PLC 제어</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="style.css">
  <link href="https://fonts.googleapis.com/css?family=Noto+Sans+KR:400,700&display=swap" rel="stylesheet">
  <style>
    body { background: #F5F7FA; color: #222; font-family: 'Noto Sans KR', 'Nanum Gothic', sans-serif; min-height: 100vh; display: flex; flex-direction: column; }
    .header { display: flex; align-items: center; justify-content: space-between; background: #005BAC; color: #fff; padding: 0 32px; height: 64px; }
    .logo { display: flex; align-items: center; font-weight: bold; font-size: 1.3rem; letter-spacing: 1px; text-decoration: none; color: #fff; }
    .logo svg { margin-right: 8px; }
    .main-nav ul { display: flex; gap: 32px; list-style: none; }
    .main-nav a { color: #fff; text-decoration: none; font-weight: 500; padding: 8px 0; border-bottom: 2px solid transparent; transition: border 0.2s; display: flex; align-items: center; gap: 6px; }
    .main-nav a[aria-current="page"], .main-nav a:hover { border-bottom: 2px solid #FFB300; }
    .main-content { max-width: 900px; margin: 40px auto 0 auto; background: #fff; border-radius: 8px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); padding: 40px 32px; flex: 1 0 auto; display: flex; gap: 40px; min-height: 340px; }
    .control-status-panel { flex: 0 0 340px; max-width: 340px; border-right: 1px solid #E0E6EF; padding-right: 32px; display: flex; flex-direction: column; gap: 18px; }
    .control-status-panel .user-info { background: #e3f2fd; color: #005BAC; border-radius: 6px; padding: 10px 18px; font-size: 1.05rem; border: 1px solid #2196f3; display: flex; align-items: center; gap: 8px; }
    .status-indicator { font-size: 1.1rem; font-weight: bold; border-radius: 6px; padding: 12px 0; color: #fff; background: <?= $isOn ? '#2ecc71' : '#e74c3c' ?>; display: flex; align-items: center; justify-content: center; gap: 8px; }
    .rpm-value { font-size: 2.2rem; font-weight: bold; color: #495057; margin: 12px 0; }
    .control-action-panel { flex: 1 1 0; padding-left: 32px; display: flex; flex-direction: column; gap: 24px; }
    .control-action-panel form { display: flex; flex-direction: column; gap: 18px; background: #f8f9fa; border-radius: 18px; box-shadow: 0 2px 12px rgba(60,139,188,0.08); padding: 32px 28px 24px 28px; max-width: 400px; }
    .control-action-panel label { font-weight: 600; color: #005BAC; margin-bottom: 2px; margin-top: 8px; }
    .control-action-panel input[type="number"] { border-radius: 12px; border: 1.5px solid #cfd8dc; padding: 14px 16px; font-size: 1rem; background: #fff; transition: border 0.2s; margin-top: 2px; }
    .control-action-panel button { border-radius: 18px; background: linear-gradient(90deg, #3C8DBC 60%, #005BAC 100%); color: #fff; font-weight: 600; font-size: 1.1rem; padding: 14px 0; border: none; margin-top: 8px; box-shadow: 0 2px 8px rgba(60,139,188,0.08); transition: background 0.2s; cursor: pointer; }
    .control-action-panel button:hover { background: linear-gradient(90deg, #005BAC 60%, #3C8DBC 100%); }
    .control-action-panel .btn-group { display: flex; gap: 12px; }
    .control-action-panel .toggle-switch {
      display: flex;
      width: 100%;
      max-width: 420px;
      min-width: 320px;
      margin: 0 auto 24px auto;
      background: #f0f4fa;
      border-radius: 36px;
      box-shadow: 0 2px 8px rgba(60,139,188,0.08);
      overflow: hidden;
      height: 72px;
      align-items: center;
      justify-content: center;
    }
    .toggle-switch button {
      flex: 1 1 0;
      height: 72px;
      font-size: 1.6rem;
      font-weight: 700;
      border: none;
      outline: none;
      background: none;
      color: #3C8DBC;
      transition: background 0.18s, color 0.18s;
      cursor: pointer;
      border-radius: 0;
      position: relative;
      z-index: 1;
      padding: 0 36px 0 36px;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .toggle-switch button.on-active {
      background: linear-gradient(90deg, #43e97b 60%, #38b86c 100%);
      color: #fff;
      border-radius: 32px 0 0 32px;
      box-shadow: 0 2px 8px rgba(67,233,123,0.18);
      position: relative;
    }
    .toggle-switch button.off-active {
      background: linear-gradient(90deg, #ff7675 60%, #e74c3c 100%);
      color: #fff;
      border-radius: 0 32px 32px 0;
      box-shadow: 0 2px 8px rgba(231,76,60,0.18);
      position: relative;
    }
    .toggle-switch .circle-indicator {
      display: inline-block;
      width: 28px;
      height: 28px;
      border-radius: 50%;
      margin-right: 16px;
      vertical-align: middle;
      box-shadow: 0 0 8px rgba(0,0,0,0.08);
      border: 2.5px solid #fff;
      background: linear-gradient(135deg, #43e97b 60%, #38b86c 100%);
      transition: background 0.2s;
    }
    .toggle-switch button.off-active .circle-indicator {
      background: linear-gradient(135deg, #ff7675 60%, #e74c3c 100%);
    }
    .toggle-switch button:focus,
    .toggle-switch button:active {
      outline: none;
      background: inherit !important;
      color: inherit;
      box-shadow: none;
    }
    .toggle-switch button:hover {
      background: inherit !important;
      color: inherit !important;
      box-shadow: none !important;
    }
    @media (max-width: 900px) {
      .main-content { flex-direction: column; gap: 18px; padding: 24px 8px; }
      .control-status-panel, .control-action-panel { max-width: none; padding: 0; border: none; }
    }
    @media (max-width: 600px) {
      .control-action-panel .toggle-switch {
        max-width: 99vw;
        min-width: 0;
        height: 54px;
      }
      .toggle-switch button {
        font-size: 1.1rem;
        height: 54px;
        padding: 0 10px;
      }
      .toggle-switch .circle-indicator { width: 16px; height: 16px; margin-right: 6px; }
    }
  </style>
</head>
<body>
  <header class="header" role="banner">
    <a href="index.php" class="logo" aria-label="홈으로">
      <svg width="28" height="28" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><rect width="24" height="24" rx="6" fill="#fff" fill-opacity="0.18"/><path fill="#005BAC" d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8v-10h-8v10zm0-18v6h8V3h-8z"/></svg>
      PLC Rotator System
    </a>
    <nav class="main-nav" aria-label="주요 메뉴">
      <ul style="display:flex;align-items:center;gap:32px;justify-content:center;">
        <li><a href="index.php"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8v-10h-8v10zm0-18v6h8V3h-8z" fill="#fff"/></svg>대시보드</a></li>
        <li><a href="control.php" aria-current="page"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 2a10 10 0 100 20 10 10 0 000-20zm1 17.93V20h-2v-.07A8.001 8.001 0 014.07 13H4v-2h.07A8.001 8.001 0 0111 4.07V4h2v.07A8.001 8.001 0 0119.93 11H20v2h-.07A8.001 8.001 0 0113 19.93z" fill="#fff"/></svg>제어</a></li>
        <li><a href="faults.php"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm0-4h-2V7h2v8z" fill="#fff"/></svg>고장</a></li>
        <?php if ($isAdmin): ?>
        <li><a href="logs.php"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 6v18h18V6H3zm16 16H5V8h14v14zm-7-2h2v-2h-2v2zm0-4h2v-4h-2v4z" fill="#fff"/></svg>로그</a></li>
        <?php endif; ?>
        <li><a href="logout.php"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M16 13v-2H7V8l-5 4 5 4v-3h9zm3-10H5c-1.1 0-2 .9-2 2v6h2V5h14v14H5v-6H3v6c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z" fill="#fff"/></svg>로그아웃</a></li>
      </ul>
    </nav>
  </header>
  <main id="main-content" class="main-content" tabindex="-1">
    <div class="control-status-panel">
      <div class="user-info">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 12c2.7 0 8 1.34 8 4v2H4v-2c0-2.66 5.3-4 8-4zm0-2a4 4 0 100-8 4 4 0 000 8z" fill="#2196f3"/></svg>
        현재 사용자: <?= htmlspecialchars($currentUser) ?> <?= $isAdmin ? ' (관리자)' : ' (일반 사용자)' ?>
      </div>
      <div class="status-indicator">
        <?= $isOn ? '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10" fill="#2ecc71"/><path d="M10 16l-4-4 1.41-1.41L10 13.17l6.59-6.59L18 8l-8 8z" fill="#fff"/></svg> 작동 중 (ON)' : '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10" fill="#e74c3c"/><path d="M15 9l-6 6M9 9l6 6" stroke="#fff" stroke-width="2"/></svg> 정지 상태 (OFF)' ?>
      </div>
      <div style="margin-top:18px;">
        <div style="font-size:1.1rem; color:#888;">현재 회전수</div>
        <div class="rpm-value"><?= number_format($currentRpm) ?> RPM</div>
      </div>
    </div>
    <div class="control-action-panel">
      <?php if ($isAdmin): ?>
      <form method="post" action="control.php">
        <div class="toggle-switch">
          <button name="action" value="on" type="submit" class="<?= $isOn ? 'on-active' : '' ?>">
            <span class="circle-indicator"></span>ON
          </button>
          <button name="action" value="off" type="submit" class="<?= !$isOn ? 'off-active' : '' ?>">
            <span class="circle-indicator"></span>OFF
          </button>
        </div>
      </form>
      <form method="post" action="control.php">
        <label for="rpm">회전 수 설정 (RPM):</label>
        <input type="number" id="rpm" name="rpm" min="0" max="10000" value="<?= $currentRpm ?>">
        <button name="action" value="apply_rpm" type="submit">회전수 적용</button>
      </form>
      <?php else: ?>
      <div style="background:#f5f5f5;padding:24px;border-radius:8px;text-align:center;color:#666;font-size:1.1rem;margin-top:32px;">
        현재 장치 상태만 확인 가능합니다.<br>장치 제어는 관리자 권한이 필요합니다.
      </div>
      <?php endif; ?>
    </div>
  </main>
  <footer class="footer" role="contentinfo">
    <div>가천대학교 CPS |  <a href="#" style="color:#FFB300; text-decoration:underline;">이용약관</a> | <a href="#" style="color:#FFB300; text-decoration:underline;">개인정보처리방침</a> | 고객센터: 1234-5678</div>
    <div style="margin-top:8px;">© 2025 PLC Control</div>
  </footer>
</body>
</html>
