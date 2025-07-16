<?php
// [대시보드 메인] - 관리자/게스트 로그인 후 접근 가능. 시스템 주요 현황, 통계, 공지사항 관리 기능 제공
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// 세션이 시작되어 있지 않으면 시작
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../src/db/db.php';
require_once '../src/log/log_function.php';

// [관리자용 대시보드 통계 집계]
if (isset($_SESSION['admin'])) {
  // 최근 7일간 일별 로그 수 집계 (운영 현황 추이 시각화용)
  $log_counts_by_date = [];
  $dates = [];
  for ($i = 6; $i >= 0; $i--) {
      $date = date('Y-m-d', strtotime("-$i days"));
      $dates[] = $date;
      $stmt = $pdo->prepare("SELECT COUNT(*) FROM logs WHERE DATE(created_at) = :date");
      $stmt->execute(['date' => $date]);
      $log_counts_by_date[] = (int)$stmt->fetchColumn();
  }
  // 로그 유형별(로그인, 고장, 제어 등) 집계 (운영 패턴 분석)
  $type_labels = ['로그인 성공','로그인 실패','로그아웃','고장 접수','고장 수정','고장 삭제','장비 제어','기타'];
  $type_keys = [
      '로그인 성공' => '%로그인 성공%',
      '로그인 실패' => '%로그인 실패%',
      '로그아웃' => '%로그아웃%',
      '고장 접수' => '%고장 접수%',
      '고장 수정' => '%고장 수정%',
      '고장 삭제' => '%고장 삭제%',
      '장비 제어' => '%장비 제어%',
      '기타' => '%'
  ];
  $type_counts = [];
  foreach ($type_labels as $label) {
      if ($label === '기타') {
          $stmt = $pdo->prepare("SELECT COUNT(*) FROM logs WHERE ".
              implode(' AND ', array_map(function($k){return "log_message NOT LIKE '$k'";}, array_slice($type_keys,0,-1)))
          );
          $stmt->execute();
          $type_counts[] = (int)$stmt->fetchColumn();
      } else {
          $stmt = $pdo->prepare("SELECT COUNT(*) FROM logs WHERE log_message LIKE :kw");
          $stmt->execute(['kw' => $type_keys[$label]]);
          $type_counts[] = (int)$stmt->fetchColumn();
      }
  }
  // 사용자별 로그 수(상위 5명, 시스템 사용량 많은 사용자 파악)
  $stmt = $pdo->query("SELECT username, COUNT(*) as cnt FROM logs GROUP BY username ORDER BY cnt DESC LIMIT 5");
  $user_rows = $stmt->fetchAll();
  $user_labels = array_column($user_rows, 'username');
  $user_counts = array_column($user_rows, 'cnt');
  // 고장 현황(전체, 미처리, 오늘 접수)
  $total_faults = $pdo->query("SELECT COUNT(*) FROM faults")->fetchColumn();
  $pending_faults = $pdo->query("SELECT COUNT(*) FROM faults WHERE status IN ('진행', '미처리')")->fetchColumn();
  $today_faults = $pdo->prepare("SELECT COUNT(*) FROM faults WHERE DATE(created_at) = :today");
  $today_faults->execute(['today' => date('Y-m-d')]);
  $today_faults = $today_faults->fetchColumn();
  // 유지보수 모드 여부 확인 (시스템 점검 중 표시)
  $row = $pdo->query("SELECT is_active FROM maintenance ORDER BY id DESC LIMIT 1")->fetch();
  $is_maintenance = $row && $row['is_active'] == 1;
  
  // PHPIDS 보안 로그 통계
  $total_security_events = $pdo->query("SELECT COUNT(*) FROM logs WHERE (log_message LIKE '%공격감지%' OR log_message LIKE '%PHPIDS%')")->fetchColumn();
  $today_security_events = $pdo->prepare("SELECT COUNT(*) FROM logs WHERE (log_message LIKE '%공격감지%' OR log_message LIKE '%PHPIDS%') AND DATE(created_at) = :today");
  $today_security_events->execute(['today' => date('Y-m-d')]);
  $today_security_events = $today_security_events->fetchColumn();
  
  // 높은 임팩트 보안 이벤트 수 (임팩트 20 이상)
  $high_impact_events = $pdo->query("SELECT COUNT(*) FROM logs WHERE (log_message LIKE '%공격감지%' OR log_message LIKE '%PHPIDS%') AND log_message LIKE '%임팩트: 2%'")->fetchColumn();
}

// --- 게스트/일반 사용자 개인화 정보 쿼리 ---
if (isset($_SESSION['guest'])) {
    $my_username = $_SESSION['guest'];
    // 내 user_id 조회
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$my_username]);
    $user = $stmt->fetch();
    $my_user_id = $user ? $user['id'] : null;
    // 내가 쓴 고장 제보 최근 5개 (user_id 기준)
    $my_faults = [];
    if ($my_user_id) {
        $stmt = $pdo->prepare("SELECT * FROM faults WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
        $stmt->execute([$my_user_id]);
        $my_faults = $stmt->fetchAll();
    }
    // 내가 쓴 취약점 제보 최근 5개
    $stmt = $pdo->prepare("SELECT * FROM vulnerability_reports WHERE reported_by = ? ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$my_username]);
    $my_vul_reports = $stmt->fetchAll();
    // 내 최근 알림(전체 대상 포함)
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE target = ? OR target = 'all' ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$my_username]);
    $my_notifications = $stmt->fetchAll();
    // 내 최근 활동 로그
    $stmt = $pdo->prepare("SELECT * FROM logs WHERE username = ? ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$my_username]);
    $my_logs = $stmt->fetchAll();
}

// [공지사항 등록/수정/삭제] - 관리자만 가능. 공지사항 관리 및 로그 기록
if (isset($_SESSION['admin'])) {
    // 점검 시작 처리
    if (isset($_POST['set_maintenance'], $_POST['duration'])) {
        $duration = (int)$_POST['duration'];
        $start = date('Y-m-d H:i:s');
        $end = date('Y-m-d H:i:s', strtotime("+$duration minutes"));
        $username = $_SESSION['admin'] ?? '';
        // 기존 점검 기록이 있으면 비활성화
        $pdo->exec("UPDATE maintenance SET is_active=0");
        // 새로운 점검 기록 추가
        $stmt = $pdo->prepare("INSERT INTO maintenance (start_at, end_at, is_active, created_by) VALUES (?, ?, 1, ?)");
        $stmt->execute([$start, $end, $username]);
        writeLog($pdo, $username, '점검시작', '성공', $duration . '분');
        echo "<script>alert('점검이 시작되었습니다.');location.href='index.php';</script>";
        exit();
    }
    // 공지 등록 처리
    if (isset($_POST['add_notice'], $_POST['notice_title'], $_POST['notice_content'])) {
        $username = $_SESSION['admin'] ?? '';
        $title = trim($_POST['notice_title']);
        $content = trim($_POST['notice_content']);
        if ($title && $content) {
            $stmt = $pdo->prepare("INSERT INTO notices (title, content) VALUES (?, ?)");
            $stmt->execute([$title, $content]);
            writeLog($pdo, $username, '공지등록', '성공', $title);
            echo "<script>alert('공지사항이 등록되었습니다.');location.href='index.php';</script>";
            exit;
        }
    }
    // 공지 삭제 처리
    if (isset($_POST['delete_notice_id'])) {
        $id = (int)$_POST['delete_notice_id'];
        $pdo->prepare("DELETE FROM notices WHERE id = ?")->execute([$id]);
        $username = $_SESSION['admin'] ?? '';
        writeLog($pdo, $username, '공지삭제', '성공', $id);
        echo "<script>alert('공지사항이 삭제되었습니다.');location.href='index.php';</script>";
        exit;
    }
    // 공지 수정 처리
    if (isset($_POST['edit_notice_id'], $_POST['edit_notice_title'], $_POST['edit_notice_content'])) {
        $id = (int)$_POST['edit_notice_id'];
        $username = $_SESSION['admin'] ?? '';
        $title = trim($_POST['edit_notice_title']);
        $content = trim($_POST['edit_notice_content']);
        if ($title && $content) {
            $pdo->prepare("UPDATE notices SET title=?, content=? WHERE id=?")->execute([$title, $content, $id]);
            writeLog($pdo, $username, '공지수정', '성공', $id);
            echo "<script>alert('공지사항이 수정되었습니다.');location.href='index.php';</script>";
            exit;
        }
    }
    // 전체 공지사항 목록 조회 (관리자용)
    $all_notices = $pdo->query("SELECT * FROM notices ORDER BY created_at DESC")->fetchAll();
}

// 최근 2개 공지사항 조회(모든 사용자에게 노출)
$notices = $pdo->query("SELECT * FROM notices ORDER BY created_at DESC LIMIT 2")->fetchAll();

// [유지보수 모드 해제 처리] - 관리자만 가능. 점검 종료 시 사용
if (isset($_POST['unset_maintenance'])) {
    $username = $_SESSION['admin'] ?? '';
    $pdo->exec("UPDATE maintenance SET is_active=0");
    writeLog($pdo, $username, '점검종료', '성공', '');
    echo "<script>alert('점검이 종료되었습니다.');location.href='index.php';</script>"; 
    exit();
}

// 오늘 고장, 미처리 고장, 취약점 제보, 오늘 보안 이벤트 - 관리자/게스트 구분 없이 항상 쿼리
$today_faults = 0;
$pending_faults = 0;
$total_vul_reports = 0;
$today_security_events = 0;
$is_maintenance = false;
try {
  $today_faults_stmt = $pdo->prepare("SELECT COUNT(*) FROM faults WHERE DATE(created_at) = :today");
  $today_faults_stmt->execute(['today' => date('Y-m-d')]);
  $today_faults = $today_faults_stmt->fetchColumn();
  // 미처리 고장: status가 '진행', '미처리', 'pending', 'investigating' 등 모두 포함
  $pending_faults = $pdo->query("SELECT COUNT(*) FROM faults WHERE status IN ('진행', '미처리', 'pending', 'investigating')")->fetchColumn();
  // 취약점 제보 전체
  $total_vul_reports = $pdo->query("SELECT COUNT(*) FROM vulnerability_reports")->fetchColumn();
  // 오늘 보안 이벤트(공격감지, PHPIDS 등)
  $today_security_stmt = $pdo->prepare("SELECT COUNT(*) FROM logs WHERE (log_message LIKE '%공격감지%' OR log_message LIKE '%PHPIDS%') AND DATE(created_at) = :today");
  $today_security_stmt->execute(['today' => date('Y-m-d')]);
  $today_security_events = $today_security_stmt->fetchColumn();
  // 점검상태
  $row = $pdo->query("SELECT is_active FROM maintenance ORDER BY id DESC LIMIT 1")->fetch();
  $is_maintenance = $row && $row['is_active'] == 1;
} catch (Exception $e) {
  // 쿼리 실패 시 0 유지
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>PLC 관리자 대시보드</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="assets/css/main.css">
  <link href="https://fonts.googleapis.com/css?family=Noto+Sans+KR:400,700&display=swap" rel="stylesheet">
  <style>
    body {
      background: #f7f9fb;
      color: #222;
      font-family: 'Noto Sans KR', 'Nanum Gothic', sans-serif;
      margin: 0;
      padding: 0;
    }
    .main-content {
      max-width: 1200px;
      margin: 0 auto;
      padding: 32px 16px 0 16px;
    }
    .dashboard-cards {
      display: flex;
      gap: 24px;
      flex-wrap: wrap;
      margin-bottom: 32px;
    }
    .dashboard-card {
      flex: 1 1 180px;
      min-width: 180px;
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 1px 6px rgba(0,0,0,0.06);
      padding: 24px 20px 18px 20px;
      display: flex;
      flex-direction: column;
      align-items: flex-start;
      justify-content: center;
      transition: box-shadow 0.2s;
    }
    .dashboard-card:hover {
      box-shadow: 0 4px 16px rgba(0,91,172,0.10);
    }
    .card-title {
      font-size: 1.08rem;
      font-weight: 600;
      color: #005BAC;
      margin-bottom: 8px;
      letter-spacing: 0.5px;
    }
    .card-value {
      font-size: 2.1rem;
      font-weight: 700;
      color: #222;
      margin-bottom: 2px;
    }
    .card-badge {
      display: inline-block;
      font-size: 0.98rem;
      font-weight: 600;
      border-radius: 8px;
      padding: 3px 12px;
      margin-top: 6px;
      background: #f1f3f6;
      color: #005BAC;
    }
    .card-green { color: #1abc9c; }
    .card-red { color: #e74c3c; }
    .card-yellow { color: #f39c12; }
    .card-blue { color: #005BAC; }
    .card-gray { color: #888; }
    .dashboard-section {
      margin-bottom: 36px;
    }
    .section-title {
      font-size: 1.13rem;
      font-weight: 700;
      color: #005BAC;
      margin-bottom: 12px;
      letter-spacing: 0.5px;
    }
    .issue-list {
      background: #fff;
      border-radius: 10px;
      box-shadow: 0 1px 6px rgba(0,0,0,0.05);
      padding: 18px 18px 8px 18px;
      margin-bottom: 18px;
    }
    .issue-item {
      display: flex;
      align-items: center;
      gap: 18px;
      padding: 10px 0;
      border-bottom: 1px solid #f0f0f0;
      font-size: 1.01rem;
    }
    .issue-item:last-child { border-bottom: none; }
    .issue-badge {
      font-size: 0.93rem;
      font-weight: 600;
      border-radius: 7px;
      padding: 2px 10px;
      background: #f8f9fa;
      color: #e74c3c;
      margin-right: 8px;
    }
    .quick-menu {
      display: flex;
      gap: 24px;
      margin-bottom: 32px;
      flex-wrap: wrap;
      justify-content: flex-start;
    }
    .quick-card {
      flex: 1 1 180px;
      min-width: 180px;
      background: #f8f9fa;
      border-radius: 12px;
      box-shadow: 0 1px 6px rgba(0,0,0,0.04);
      padding: 18px 12px 14px 12px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: box-shadow 0.2s, background 0.2s;
      text-align: center;
    }
    .quick-card:hover {
      background: #e3f0ff;
      box-shadow: 0 4px 16px rgba(0,91,172,0.10);
    }
    .quick-icon {
      font-size: 2.1rem;
      margin-bottom: 8px;
      color: #005BAC;
    }
    @media (max-width: 900px) {
      .main-content { max-width: 100vw; padding: 12px 2vw; }
      .dashboard-cards, .quick-menu { flex-direction: column; gap: 14px; }
      .dashboard-card, .quick-card { min-width: 0; }
    }
  </style>
  <style>
    .notice-modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.18); justify-content: center; align-items: center; }
    .notice-modal.active { display: flex; }
    .notice-modal-content { background: #fff; border-radius: 14px; padding: 32px 24px; min-width: 280px; max-width: 420px; box-shadow: 0 4px 24px rgba(0,0,0,0.13); position: relative; }
    .notice-modal-close { position: absolute; top: 12px; right: 16px; background: none; border: none; font-size: 2rem; color: #888; cursor: pointer; }
  </style>
</head>
<body>
<header class="header" role="banner" style="box-shadow:0 2px 8px rgba(0,0,0,0.08);">
  <a class="logo" aria-label="홈으로" style="font-size:1.5rem;letter-spacing:2px;">PLC Rotator System</a>
  <nav class="main-nav" aria-label="주요 메뉴">
    <ul style="display:flex;align-items:center;gap:32px;justify-content:center;">
      <li><a href="index.php" aria-current="page">대시보드</a></li>
      <li><a href="control.php">제어</a></li>
      <li><a href="faults.php">고장</a></li>
      <?php if (isset($_SESSION['admin'])): ?>
      <li><a href="logs.php">로그</a></li>
      <?php endif; ?>
      <li>
        <?php if (isset($_SESSION['admin'])): ?>
          <a href="admin/vulnerability_management.php">취약점 제보</a>
        <?php else: ?>
          <a href="vulnerability_report.php">취약점 제보</a>
        <?php endif; ?>
      </li>
      <li><a href="logout.php">로그아웃</a></li>
    </ul>
  </nav>
</header>
<main class="main-content">
<?php if (isset($_SESSION['admin'])): ?>
  <!-- 시스템 모니터링 캐러셀 (네비게이션 점 제거, 자동 전환만) -->
  <section class="system-monitor-carousel" style="margin-bottom:32px;">
    <div id="sysMonCarousel" class="sysmon-carousel-cards"></div>
  </section>
  <script>
    const sysMonItems = [
      { key: 'cpu', title: 'CPU 부하', unit: '1min load', icon: '🖥️' },
      { key: 'mem', title: '메모리 사용', unit: 'GB 사용 / 전체', icon: '💾' },
      { key: 'disk', title: '디스크 사용', unit: 'GB 사용 / 전체', icon: '🗄️' },
      { key: 'uptime', title: '업타임', unit: '서버 가동 시간', icon: '⏱️' },
      { key: 'net', title: '네트워크', unit: 'MB 수신 / 송신', icon: '🌐' }
    ];
    let sysMonData = {}, sysMonIdx = 0, sysMonTimer = null, sysMonPaused = false;
    function fetchSysMon() {
      fetch('admin/system_status_api.php').then(r=>r.json()).then(d=>{
        sysMonData = d;
        renderSysMonCard();
      });
    }
    function renderSysMonCard() {
      const c = document.getElementById('sysMonCarousel');
      if (!c) return;
      let html = '';
      sysMonItems.forEach((item, i) => {
        let value = '';
        if(item.key==='cpu') value = sysMonData.cpu_load?.toFixed(2)||'-';
        if(item.key==='mem') value = `${(sysMonData.mem_used/1024/1024).toFixed(1)} / ${(sysMonData.mem_total/1024/1024).toFixed(1)}`;
        if(item.key==='disk') value = `${(sysMonData.disk_used/1024/1024/1024).toFixed(1)} / ${(sysMonData.disk_total/1024/1024/1024).toFixed(1)}`;
        if(item.key==='uptime') value = sysMonData.uptime||'-';
        if(item.key==='net') value = `${(sysMonData.net_rx/1024/1024).toFixed(1)} / ${(sysMonData.net_tx/1024/1024).toFixed(1)}`;
        html += `<div class="sysmon-card${i===sysMonIdx?' active':''}" style="display:${i===sysMonIdx?'block':'none'};">
          <div class="sysmon-icon">${item.icon}</div>
          <div class="sysmon-title">${item.title}</div>
          <div class="sysmon-value">${value}</div>
          <div class="sysmon-unit">${item.unit}</div>
        </div>`;
      });
      c.innerHTML = html;
    }
    function nextSysMon() { sysMonIdx = (sysMonIdx+1)%sysMonItems.length; renderSysMonCard(); }
    function resetSysMonTimer() {
      if(sysMonTimer) clearInterval(sysMonTimer);
      if(!sysMonPaused) sysMonTimer = setInterval(nextSysMon, 3500);
    }
    document.addEventListener('DOMContentLoaded',()=>{
      fetchSysMon();
      setInterval(fetchSysMon, 5000);
      resetSysMonTimer();
      const c = document.getElementById('sysMonCarousel');
      if(c) {
        c.onmouseenter = ()=>{ sysMonPaused=true; if(sysMonTimer) clearInterval(sysMonTimer); };
        c.onmouseleave = ()=>{ sysMonPaused=false; resetSysMonTimer(); };
      }
    });
  </script>
  <style>
    .system-monitor-carousel { width:100%; max-width:700px; margin:0 auto 32px auto; }
    .sysmon-carousel-cards { position:relative; min-height:140px; }
    .sysmon-card { background:#f8faff; border-radius:18px; box-shadow:0 2px 12px rgba(51,102,204,0.07); padding:32px 24px 24px 24px; text-align:center; min-width:220px; max-width:340px; margin:0 auto; transition:box-shadow 0.2s; }
    .sysmon-card .sysmon-icon { font-size:2.5rem; margin-bottom:8px; }
    .sysmon-card .sysmon-title { font-size:1.13rem; font-weight:700; color:#005BAC; margin-bottom:6px; }
    .sysmon-card .sysmon-value { font-size:2.2rem; font-weight:800; color:#222; margin-bottom:2px; }
    .sysmon-card .sysmon-unit { font-size:1.01rem; color:#888; }
  </style>
<?php endif; ?>
  <!-- 상단 요약 카드 -->
  <section class="dashboard-cards">
    <div class="dashboard-card">
      <div class="card-title">🛠️ 오늘 고장</div>
      <div class="card-value card-yellow"><?= $today_faults ?>건</div>
    </div>
    <div class="dashboard-card">
      <div class="card-title">⏳ 미처리 고장</div>
      <div class="card-value card-red"><?= $pending_faults ?>건</div>
    </div>
    <div class="dashboard-card" id="maintenanceCard" style="cursor:pointer;">
      <div class="card-title">🔧 점검상태</div>
      <div class="card-value card-green"><?= $is_maintenance ? '점검중' : '정상' ?></div>
      <div class="card-badge card-gray" style="margin-top:8px;">클릭하여 점검 제어</div>
    </div>
    <div class="dashboard-card">
      <div class="card-title">❗ 오늘 보안 이벤트</div>
      <div class="card-value card-blue"><?= $today_security_events ?>건</div>
    </div>
    <div class="dashboard-card">
      <div class="card-title">🔒 취약점 제보</div>
      <div class="card-value card-blue"><?= $total_vul_reports ?>건</div>
    </div>
  </section>
  <!-- 주요 기능 빠른 진입 메뉴 -->
  <section class="quick-menu">
    <div class="quick-card" onclick="location.href='admin/user_management.php'">
      <div class="quick-icon">👤</div>
      <div>계정 관리</div>
    </div>
    <div class="quick-card" onclick="location.href='admin/file_management.php'">
      <div class="quick-icon">📁</div>
      <div>파일 관리</div>
    </div>
    <div class="quick-card" onclick="location.href='admin/fault_maintenance_history.php'">
      <div class="quick-icon">📝</div>
      <div>고장/점검 이력</div>
    </div>
    <div class="quick-card" onclick="location.href='admin/security_center.php'">
      <div class="quick-icon">🛡️</div>
      <div>보안 통합</div>
    </div>
    <div class="quick-card" onclick="location.href='admin/vulnerability_management.php'">
      <div class="quick-icon">🔒</div>
      <div>취약점 관리</div>
    </div>
    <div class="quick-card" onclick="location.href='logs.php'">
      <div class="quick-icon">📊</div>
      <div>로그</div>
    </div>
  </section>
  <!-- 최근 이슈/알림/이벤트 강조 -->
  <section class="dashboard-section">
    <div class="section-title">🚨 최근 미처리 고장/보안 이벤트/취약점</div>
    <div class="issue-list redesign-issue-list">
      <?php
      // 최근 미처리 고장 3건
      $recent_faults = $pdo->query("SELECT * FROM faults WHERE status IN ('진행', '미처리', 'pending', 'investigating') ORDER BY created_at DESC LIMIT 3")->fetchAll();
      foreach ($recent_faults as $f): ?>
        <div class="issue-item">
          <span class="issue-badge" style="background:#ffe082;color:#b26a00;">고장</span>
          <span><b><?= htmlspecialchars($f['part']) ?></b> (<?= $f['status'] ?>) - <?= $f['created_at'] ?></span>
        </div>
      <?php endforeach; ?>
      <?php
      // 최근 보안 이벤트 3건
      $recent_sec = $pdo->query("SELECT * FROM logs WHERE (log_message LIKE '%공격감지%' OR log_message LIKE '%PHPIDS%') ORDER BY created_at DESC LIMIT 3")->fetchAll();
      foreach ($recent_sec as $s): ?>
        <div class="issue-item">
          <span class="issue-badge" style="background:#b3e5fc;color:#01579b;">보안</span>
          <span><?= htmlspecialchars($s['log_message']) ?> - <?= $s['created_at'] ?></span>
        </div>
      <?php endforeach; ?>
      <?php
      // 최근 취약점 제보 3건
      $recent_vul = $pdo->query("SELECT * FROM vulnerability_reports ORDER BY created_at DESC LIMIT 3")->fetchAll();
      foreach ($recent_vul as $v): ?>
        <div class="issue-item">
          <span class="issue-badge" style="background:#d1c4e9;color:#4527a0;">취약점</span>
          <span><b><?= htmlspecialchars($v['title']) ?></b> (<?= $v['status'] ?>) - <?= $v['created_at'] ?></span>
        </div>
      <?php endforeach; ?>
      <?php if (count($recent_faults) + count($recent_sec) + count($recent_vul) === 0): ?>
        <div style="color:#888;text-align:center;padding:18px 0;">최근 미처리 고장, 보안 이벤트, 취약점 제보가 없습니다.</div>
      <?php endif; ?>
    </div>
  </section>
  <!-- 공지사항 카드(심플/둥글게 리디자인) -->
<section style="margin:32px 0 24px 0;display:flex;gap:32px;align-items:flex-start;flex-wrap:wrap;">
  <div class="notice-card-main" style="flex:1;min-width:320px;background:#f8faff;border-radius:18px;box-shadow:0 2px 12px rgba(51,102,204,0.07);padding:28px 32px 18px 32px;">
    <h3 style="font-size:1.13rem;font-weight:700;color:#3366cc;margin-bottom:10px;display:flex;align-items:center;gap:8px;">
      <span style="font-size:1.3rem;">📢</span> 최근 공지사항
    </h3>
    <?php if (count($notices) > 0): ?>
      <?php foreach ($notices as $notice): ?>
        <div style="margin-bottom:14px;">
          <div style="font-size:1.08rem;font-weight:700;color:#3366cc;"> <?= htmlspecialchars($notice['title']) ?> </div>
          <div style="font-size:0.97rem;color:#444;line-height:1.5;margin-bottom:2px;"> <?= nl2br(htmlspecialchars($notice['content'])) ?> </div>
          <div style="font-size:0.92rem;color:#888;">등록일: <?= date('Y-m-d', strtotime($notice['created_at'])) ?></div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div style="color:#888;">등록된 공지사항이 없습니다.</div>
    <?php endif; ?>
  </div>
  <?php if (isset($_SESSION['admin'])): ?>
  <div style="flex:0 0 160px;display:flex;align-items:flex-start;justify-content:flex-end;">
    <button id="noticeManageBtn" class="btn btn-primary" style="padding:10px 24px;font-size:1.05rem;border-radius:10px;box-shadow:0 1px 6px rgba(0,91,172,0.08);">공지사항 관리</button>
  </div>
  <?php endif; ?>
</section>
  <!-- 차트/통계(슬라이드/탭 등으로 정돈, 필요시) -->
  <section style="margin:0 0 32px 0;">
    <div class="chart-carousel redesign">
      <div class="chart-slide active">
        <div class="chart-card"><canvas id="logChart" height="200"></canvas></div>
        <div id="logChartEmpty" style="display:none;text-align:center;color:#aaa;padding:24px 0;">일별 로그 데이터가 없습니다.</div>
      </div>
      <div class="chart-slide">
        <div class="chart-card"><canvas id="typeChart" height="200"></canvas></div>
        <div id="typeChartEmpty" style="display:none;text-align:center;color:#aaa;padding:24px 0;">유형별 로그 데이터가 없습니다.</div>
      </div>
      <div class="chart-slide">
        <div class="chart-card"><canvas id="userChart" height="200"></canvas></div>
        <div id="userChartEmpty" style="display:none;text-align:center;color:#aaa;padding:24px 0;">사용자별 로그 데이터가 없습니다.</div>
      </div>
    </div>
  </section>
  <style>
    .chart-carousel.redesign { display:flex; gap:24px; justify-content:center; align-items:stretch; flex-wrap:wrap; }
    .chart-card { background:#f8faff; border-radius:18px; box-shadow:0 2px 12px rgba(51,102,204,0.09); padding:24px 18px 12px 18px; min-width:320px; max-width:420px; margin:0 auto; display:flex; flex-direction:column; align-items:center; }
    .chart-card canvas { border-radius:14px; background:#fff; box-shadow:0 1px 6px rgba(0,91,172,0.06); }
    @media(max-width:900px){.chart-card{min-width:0;max-width:100vw;padding:8px 2vw;}}
  </style>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
  <script>
  // 차트 캐러셀 동작 (그래프 클릭 시 다음 슬라이드, 자연스러운 전환)
  const chartSlides = document.querySelectorAll('.chart-slide');
  let chartIdx = 0;
  function showChart(idx) {
    chartSlides.forEach((el, i) => el.classList.toggle('active', i === idx));
  }
  chartSlides.forEach(slide => {
    slide.onclick = () => {
      chartIdx = (chartIdx + 1) % chartSlides.length;
      showChart(chartIdx);
    };
  });
  showChart(chartIdx);
  // PHP 데이터를 JS로 전달
  const logLabels = <?= json_encode($dates ?? []) ?>;
  const logData = <?= json_encode($log_counts_by_date ?? []) ?>;
  const typeLabels = <?= json_encode($type_labels ?? []) ?>;
  const typeData = <?= json_encode($type_counts ?? []) ?>;
  const userLabels = <?= json_encode($user_labels ?? []) ?>;
  const userData = <?= json_encode($user_counts ?? []) ?>;
  // 차트 공통 옵션
  function getGradient(ctx, color) {
    const gradient = ctx.createLinearGradient(0, 0, 0, 180);
    gradient.addColorStop(0, color+"22");
    gradient.addColorStop(1, color+"00");
    return gradient;
  }
  // 일별 로그 차트
  if (document.getElementById('logChart')) {
    const ctx = document.getElementById('logChart').getContext('2d');
    new Chart(ctx, {
      type: 'line',
      data: {
        labels: logLabels,
        datasets: [{
          label: '일별 로그',
          data: logData,
          borderColor: '#005BAC',
          backgroundColor: getGradient(ctx,'#005BAC'),
          fill: true,
          tension: 0.5,
          borderWidth: 4,
          pointRadius: 7,
          pointBackgroundColor: '#fff',
          pointBorderColor: '#005BAC',
          pointBorderWidth: 3,
          shadowOffsetX:0,shadowOffsetY:2,shadowBlur:8,shadowColor:'rgba(0,91,172,0.18)'
        }]
      },
      options: {
        plugins: {
          legend: { display: true, labels: { color: '#005BAC', font: { weight: 'bold', size: 16 } } },
          tooltip: { backgroundColor: '#005BAC', titleColor: '#fff', bodyColor: '#fff', borderColor: '#fff', borderWidth: 2 },
          datalabels: { color: '#005BAC', font: { weight: 'bold' }, align: 'top', display: true, formatter: v => v.toFixed(2) }
        },
        animation: { duration: 1200, easing: 'easeOutBounce' },
        scales: { y: { beginAtZero: true, grid: { color: '#e3f0ff' }, ticks: { color: '#005BAC', font: { weight: 'bold' } } }, x: { grid: { color: '#e3f0ff' }, ticks: { color: '#005BAC' } } }
      },
      plugins: [ChartDataLabels]
    });
  }
  // 유형별 로그 차트
  if (document.getElementById('typeChart')) {
    const ctx = document.getElementById('typeChart').getContext('2d');
    new Chart(ctx, {
      type: 'bar',
      data: {
        labels: typeLabels,
        datasets: [{
          label: '유형별 로그',
          data: typeData,
          backgroundColor: '#43e97b',
          borderRadius: 12,
          borderSkipped: false
        }]
      },
      options: {
        plugins: {
          legend: { display: true, labels: { color: '#43e97b', font: { weight: 'bold', size: 16 } } },
          tooltip: { backgroundColor: '#43e97b', titleColor: '#fff', bodyColor: '#fff' },
          datalabels: { color: '#43e97b', font: { weight: 'bold' }, align: 'top', display: true, formatter: v => v.toFixed(1) }
        },
        animation: { duration: 1200, easing: 'easeOutBounce' },
        scales: { y: { beginAtZero: true, grid: { color: '#e3f0ff' }, ticks: { color: '#43e97b', font: { weight: 'bold' } } }, x: { grid: { color: '#e3f0ff' }, ticks: { color: '#43e97b' } } }
      },
      plugins: [ChartDataLabels]
    });
  }
  // 사용자별 로그 차트
  if (document.getElementById('userChart')) {
    const ctx = document.getElementById('userChart').getContext('2d');
    new Chart(ctx, {
      type: 'bar',
      data: {
        labels: userLabels,
        datasets: [{
          label: '사용자별 로그',
          data: userData,
          backgroundColor: '#3366cc',
          borderRadius: 12,
          borderSkipped: false
        }]
      },
      options: {
        plugins: {
          legend: { display: true, labels: { color: '#3366cc', font: { weight: 'bold', size: 16 } } },
          tooltip: { backgroundColor: '#3366cc', titleColor: '#fff', bodyColor: '#fff' },
          datalabels: { color: '#3366cc', font: { weight: 'bold' }, align: 'top', display: true, formatter: v => v.toFixed(1) }
        },
        animation: { duration: 1200, easing: 'easeOutBounce' },
        scales: { y: { beginAtZero: true, grid: { color: '#e3f0ff' }, ticks: { color: '#3366cc', font: { weight: 'bold' } } }, x: { grid: { color: '#e3f0ff' }, ticks: { color: '#3366cc' } } }
      },
      plugins: [ChartDataLabels]
    });
  }
  </script>

</main>
<!-- 공지사항 관리 모달 -->
<div id="noticeModal" class="notice-modal">
  <div class="notice-modal-content">
    <button class="notice-modal-close" onclick="closeNoticeModal()">&times;</button>
    <h3>공지사항 관리</h3>
    <form class="notice-form" method="post">
      <input type="text" name="notice_title" placeholder="공지 제목" required style="width:100%;padding:6px 8px;margin-bottom:6px;border-radius:6px;border:1.5px solid #b3c6e0;font-size:0.98rem;">
      <textarea name="notice_content" placeholder="공지 내용" required style="width:100%;padding:6px 8px;border-radius:6px;border:1.5px solid #b3c6e0;font-size:0.98rem;min-height:36px;margin-bottom:6px;"></textarea>
      <button type="submit" name="add_notice" class="notice-btn">공지 등록</button>
    </form>
    <div class="notice-list">
      <h4 style="margin:0 0 8px 0;font-size:1.01rem;color:#3366cc;">공지사항 목록</h4>
      <?php if (count($all_notices) > 0): ?>
        <ul style="padding-left:0;list-style:none;max-height:180px;overflow-y:auto;">
          <?php foreach ($all_notices as $notice): ?>
            <li style="margin-bottom:10px;padding-bottom:8px;border-bottom:1px solid #e0e0e0;">
              <div style="font-weight:700;font-size:1.01rem;color:#3366cc;"> <?= htmlspecialchars($notice['title']) ?> </div>
              <div style="font-size:0.97rem;color:#444;line-height:1.5;"> <?= nl2br(htmlspecialchars($notice['content'])) ?> </div>
              <div style="font-size:0.92rem;color:#888;margin-top:2px;">등록일: <?= date('Y-m-d', strtotime($notice['created_at'])) ?></div>
              <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:4px;">
                <button type="button" onclick="showEditForm(<?= $notice['id'] ?>)" class="notice-btn" style="background:#3366cc;">수정</button>
                <form method="post" style="display:inline;">
                  <input type="hidden" name="delete_notice_id" value="<?= $notice['id'] ?>">
                  <button type="submit" class="notice-btn" style="background:#E53935;">삭제</button>
                </form>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <div style="color:#888;text-align:center;">등록된 공지사항이 없습니다.</div>
      <?php endif; ?>
    </div>
  </div>
</div>
<!-- 점검상태 제어 모달 -->
<div id="maintenanceModal" class="notice-modal">
  <div class="notice-modal-content" style="max-width:340px;">
    <button class="notice-modal-close">&times;</button>
    <?php if ($is_maintenance): ?>
      <h3>점검 상태</h3>
      <div style="font-size:1.05rem;color:#888;margin-bottom:18px;">현재 시스템이 점검 중입니다.</div>
      <div style="font-size:0.98rem;color:#3366cc;">점검 해제를 원하시면 카드를 클릭하세요.</div>
    <?php else: ?>
      <h3>점검 시작</h3>
      <form method="post">
        <label for="duration" style="font-weight:600;">점검 시간(분):</label>
        <input type="number" name="duration" id="duration" min="1" max="1440" required style="width:100%;padding:8px 10px;margin:12px 0 18px 0;border-radius:6px;border:1.5px solid #b3c6e0;font-size:1.05rem;">
        <button type="submit" name="set_maintenance" class="btn btn-primary" style="width:100%;padding:10px 0;font-size:1.08rem;">점검 시작</button>
      </form>
    <?php endif; ?>
  </div>
</div>
<!-- 점검 해제 확인 모달 -->
<div id="maintenanceConfirmModal" class="notice-modal">
  <div class="notice-modal-content" style="max-width:340px;">
    <div style="font-size:1.13rem;font-weight:700;color:#3366cc;margin-bottom:18px;">점검을 종료하시겠습니까?</div>
    <form method="post" style="display:flex;gap:12px;justify-content:center;">
      <button type="submit" name="unset_maintenance" class="btn btn-danger" style="padding:10px 24px;font-size:1.05rem;border-radius:10px;">예</button>
      <button type="button" id="cancelMaintenanceBtn" class="btn btn-secondary" style="padding:10px 24px;font-size:1.05rem;border-radius:10px;background:#eee;color:#333;">아니오</button>
    </form>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  // 공지사항 관리 모달 열기/닫기
  const noticeBtn = document.getElementById('noticeManageBtn');
  const noticeModal = document.getElementById('noticeModal');
  function openNoticeModal() { noticeModal.classList.add('active'); }
  function closeNoticeModal() { noticeModal.classList.remove('active'); }
  if (noticeBtn) noticeBtn.onclick = openNoticeModal;
  if (noticeModal) {
    noticeModal.onclick = function(e) { if(e.target===noticeModal) closeNoticeModal(); };
    document.addEventListener('keydown', function(e){ if(e.key==='Escape' && noticeModal.classList.contains('active')) closeNoticeModal(); });
  }

  // 운영/시스템 카드 클릭 시 모달 열기
  const opsMenuCard = document.getElementById('opsMenuCard');
  const opsMenuModal = document.getElementById('opsMenuModal');
  function openOpsMenuModal() { opsMenuModal.classList.add('active'); }
  function closeOpsMenuModal() { opsMenuModal.classList.remove('active'); }
  if (opsMenuCard) opsMenuCard.onclick = openOpsMenuModal;
  if (opsMenuModal) {
    opsMenuModal.onclick = function(e) { if(e.target===opsMenuModal) closeOpsMenuModal(); };
    document.addEventListener('keydown', function(e){ if(e.key==='Escape' && opsMenuModal.classList.contains('active')) closeOpsMenuModal(); });
  }

  // 점검상태 카드 클릭 시 모달 열기
  const maintenanceCard = document.getElementById('maintenanceCard');
  const maintenanceModal = document.getElementById('maintenanceModal');
  const maintenanceConfirmModal = document.getElementById('maintenanceConfirmModal');
  const isMaintenance = <?php echo json_encode($is_maintenance); ?>;
  function openMaintenanceModal() { maintenanceModal.classList.add('active'); }
  function closeMaintenanceModal() { maintenanceModal.classList.remove('active'); }
  if (maintenanceCard) {
    if (isMaintenance) {
      maintenanceCard.onclick = function() {
        maintenanceConfirmModal.classList.add('active');
      };
    } else {
      maintenanceCard.onclick = openMaintenanceModal;
    }
  }
  if (maintenanceModal) {
    maintenanceModal.onclick = function(e) { if(e.target===maintenanceModal) closeMaintenanceModal(); };
    document.addEventListener('keydown', function(e){ if(e.key==='Escape' && maintenanceModal.classList.contains('active')) closeMaintenanceModal(); });
    const closeBtn = maintenanceModal.querySelector('.notice-modal-close');
    if (closeBtn) closeBtn.onclick = closeMaintenanceModal;
  }
  // 점검 해제 확인 모달 닫기
  if (maintenanceConfirmModal) {
    maintenanceConfirmModal.onclick = function(e) { if(e.target===maintenanceConfirmModal) maintenanceConfirmModal.classList.remove('active'); };
    document.addEventListener('keydown', function(e){ if(e.key==='Escape' && maintenanceConfirmModal.classList.contains('active')) maintenanceConfirmModal.classList.remove('active'); });
    const cancelBtn = document.getElementById('cancelMaintenanceBtn');
    if (cancelBtn) cancelBtn.onclick = function() { maintenanceConfirmModal.classList.remove('active'); };
  }
});
</script>
<script>
// 점검 상태 실시간 확인 및 UI 자동 갱신 (남은 시간 표시)
let remainTimer = null;
function updateMaintenanceStatusUI(isActive, endAt) {
  const card = document.getElementById('maintenanceCard');
  if (!card) return;
  const value = card.querySelector('.card-value');
  const badge = card.querySelector('.card-badge');
  if (remainTimer) { clearTimeout(remainTimer); remainTimer = null; }
  if (isActive && endAt) {
    value.textContent = '점검중';
    value.classList.add('card-green');
    // 남은 시간 계산
    function updateRemain() {
      const now = new Date();
      const end = new Date(endAt.replace(/-/g, '/'));
      let diff = Math.floor((end - now) / 1000);
      if (diff <= 0) {
        badge.textContent = '점검 종료';
        return;
      }
      const m = Math.floor(diff / 60);
      const s = diff % 60;
      badge.textContent = `남은 시간: ${m}분 ${s}초`;
      remainTimer = setTimeout(updateRemain, 1000);
    }
    updateRemain();
  } else {
    value.textContent = '정상';
    value.classList.add('card-green');
    badge.textContent = '클릭하여 점검 제어';
  }
}
function pollMaintenanceStatus() {
  fetch('check_maintenance_status.php')
    .then(r=>r.json())
    .then(d=>{
      updateMaintenanceStatusUI(d.is_active === 1, d.end_at);
      setTimeout(pollMaintenanceStatus, 5000);
    })
    .catch(()=>setTimeout(pollMaintenanceStatus, 7000));
}
document.addEventListener('DOMContentLoaded', function() {
  pollMaintenanceStatus();
});
</script>
</body>
</html>
