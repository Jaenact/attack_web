<?php
// [대시보드 메인] - 관리자/게스트 로그인 후 접근 가능. 시스템 주요 현황, 통계, 공지사항 관리 기능 제공
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
date_default_timezone_set('Asia/Seoul');
if (!isset($_SESSION['admin']) && !isset($_SESSION['guest'])) {
  // 로그인하지 않은 사용자는 접근 불가. 로그인 페이지로 강제 이동
  header("Location: login.php");
  exit();
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
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>PLC 대시보드</title>
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
    .main-content { max-width: 900px; margin: 40px auto 0 auto; background: #fff; border-radius: 8px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); padding: 40px 32px; flex: 1 0 auto; }
    .main-content h2 { font-size: 2rem; font-weight: 700; color: #005BAC; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
    .main-content p { font-size: 1.1rem; margin-bottom: 8px; }
    .footer { background: #222; color: #fff; text-align: center; padding: 24px 0 16px 0; font-size: 0.95rem; margin-top: 48px; flex-shrink: 0; }
    @media (max-width: 700px) {
      .header { flex-direction: column; height: auto; padding: 0 8px; }
      .main-nav ul { gap: 12px; }
      .main-content { padding: 24px 8px; }
    }
  </style>
</head>
<body>
<?php if (isset(
  $is_maintenance) && $is_maintenance && !isset($_SESSION['admin'])): ?>
  <script>
    setTimeout(function() {
      window.location.href = 'maintenance_end.php';
    }, 5000);
  </script>
<?php endif; ?>
  <header class="header" role="banner" style="box-shadow:0 2px 8px rgba(0,0,0,0.08);background:linear-gradient(90deg,#005BAC 60%,#0076d7 100%);">
    <a href="index.php" class="logo" aria-label="홈으로" style="font-size:1.5rem;letter-spacing:2px;">
      <svg width="28" height="28" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><rect width="24" height="24" rx="6" fill="#fff" fill-opacity="0.18"/><path fill="#005BAC" d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8v-10h-8v10zm0-18v6h8V3h-8z"/></svg>
      PLC Rotator System
    </a>
    <nav class="main-nav" aria-label="주요 메뉴">
      <ul style="display:flex;align-items:center;gap:32px;justify-content:center;">
        <li><a href="index.php" aria-current="page"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8v-10h-8v10zm0-18v6h8V3h-8z" fill="#fff"/></svg>대시보드</a></li>
        <li><a href="control.php"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 2a10 10 0 100 20 10 10 0 000-20zm1 17.93V20h-2v-.07A8.001 8.001 0 014.07 13H4v-2h.07A8.001 8.001 0 0111 4.07V4h2v.07A8.001 8.001 0 0119.93 11H20v2h-.07A8.001 8.001 0 0113 19.93z" fill="#fff"/></svg>제어</a></li>
        <li><a href="faults.php"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm0-4h-2V7h2v8z" fill="#fff"/></svg>고장</a></li>
        <?php if (isset($_SESSION['admin'])): ?>
        <li><a href="logs.php"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 6v18h18V6H3zm16 16H5V8h14v14zm-7-2h2v-2h-2v2zm0-4h2v-4h-2v4z" fill="#fff"/></svg>로그</a></li>

        <?php if (!isset($_SESSION['admin'])): ?>
        <li><a href="vulnerability_report.php"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z" fill="#fff"/></svg>취약점제보</a></li>
        <?php endif; ?>
        <li style="position:relative;">
          <button id="notifyBtn" style="background:none;border:none;cursor:pointer;position:relative;">
            <svg width="24" height="24" fill="none" viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9v5.28l-1.29 1.29A1 1 0 005 17h14a1 1 0 00.71-1.71L19 14.28V9c0-3.87-3.13-7-7-7zm0 18a2 2 0 002-2H10a2 2 0 002 2z" fill="#fff"/></svg>
            <span id="notifyBadge" style="position:absolute;top:-4px;right:-4px;background:#ff4757;color:#fff;font-size:0.8rem;padding:2px 6px;border-radius:12px;display:none;">0</span>
          </button>
          <div id="notifyDropdown" style="display:none;position:absolute;right:0;top:36px;min-width:320px;max-width:400px;z-index:1000;background:#fff;border-radius:10px;box-shadow:0 4px 24px rgba(0,0,0,0.18);overflow:hidden;">
            <div style="padding:12px 16px;font-weight:700;font-size:1.1rem;background:#005BAC;color:#fff;">실시간 알림</div>
            <div id="notifyList" style="max-height:340px;overflow-y:auto;"></div>
            <div style="padding:8px 0;text-align:center;background:#f5f7fa;font-size:0.95rem;">
              <a href="logs.php" style="color:#005BAC;text-decoration:underline;">전체 로그 보기</a>
            </div>
          </div>
        </li>
        <?php endif; ?>
        <li><a href="logout.php"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M16 13v-2H7V8l-5 4 5 4v-3h9zm3-10H5c-1.1 0-2 .9-2 2v6h2V5h14v14H5v-6H3v6c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z" fill="#fff"/></svg>로그아웃</a></li>
      </ul>
    </nav>
  </header>
  <?php if (isset($_SESSION['admin'])): ?>
  <main id="main-content" class="main-content" tabindex="-1" style="padding:0;background:transparent;box-shadow:none;max-width:1100px;">
    <h2 style="display:flex;align-items:center;gap:10px;">🚀 관리자 대시보드</h2>
    <!-- 상단 요약 카드 (한 줄, 5개) -->
    <section style="display:flex;gap:18px;flex-wrap:wrap;margin:32px 0 24px 0;justify-content:space-between;">
      <div style="flex:1 1 0;min-width:160px;background:linear-gradient(120deg,#fffbe3 60%,#f9f8fa 100%);border-radius:14px;box-shadow:0 2px 8px rgba(255,179,0,0.10);padding:18px 10px;display:flex;flex-direction:column;align-items:center;">
        <span style="font-size:1.3rem;color:#FFB300;font-weight:700;">🛠️ 오늘 고장</span>
        <span style="font-size:1.5rem;font-weight:800;margin-top:6px;letter-spacing:1px;"> <?= isset($today_faults) ? $today_faults : '0' ?> 건</span>
      </div>
      <div style="flex:1 1 0;min-width:160px;background:linear-gradient(120deg,#ffe3e3 60%,#f8f9fa 100%);border-radius:14px;box-shadow:0 2px 8px rgba(255,71,87,0.10);padding:18px 10px;display:flex;flex-direction:column;align-items:center;">
        <span style="font-size:1.3rem;color:#ff4757;font-weight:700;">⏳ 미처리 고장</span>
        <span style="font-size:1.5rem;font-weight:800;margin-top:6px;letter-spacing:1px;"> <?= isset($pending_faults) ? $pending_faults : '0' ?> 건</span>
      </div>
      <div id="maintenanceCard" onclick="toggleMaintenanceForm()" style="flex:1 1 0;min-width:160px;cursor:pointer;background:linear-gradient(120deg,#e3ffe3 60%,#f8f9fa 100%);border-radius:14px;box-shadow:0 2px 8px rgba(67,233,123,0.10);padding:18px 10px;display:flex;flex-direction:column;align-items:center;position:relative;transition:box-shadow 0.2s;">
        <span style="font-size:1.3rem;color:#43e97b;font-weight:700;">🔧 점검상태</span>
        <span style="font-size:1.2rem;font-weight:700;margin-top:6px;letter-spacing:1px;"> <?= isset($is_maintenance) ? ($is_maintenance ? '점검중' : '정상') : '?' ?> </span>
        <div id="maintenanceForm" style="display:none;position:absolute;top:100%;left:0;width:100%;background:#fff;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,0.12);padding:18px 12px;z-index:10;">
          <form method="post" style="margin:0;display:flex;flex-direction:column;gap:10px;align-items:center;">
            <?php if (!$is_maintenance): ?>
              <input type="hidden" name="set_maintenance" value="1">
              <button type="submit" name="duration" value="30" style="background:#1976D2;color:#fff;padding:10px 0;border:none;border-radius:8px;font-weight:700;font-size:1rem;min-width:120px;">30분 점검</button>
              <button type="submit" name="duration" value="60" style="background:#1976D2;color:#fff;padding:10px 0;border:none;border-radius:8px;font-weight:700;font-size:1rem;min-width:120px;">1시간 점검</button>
              <button type="submit" name="duration" value="90" style="background:#1976D2;color:#fff;padding:10px 0;border:none;border-radius:8px;font-weight:700;font-size:1rem;min-width:120px;">1시간 30분 점검</button>
              <button type="submit" name="duration" value="120" style="background:#1976D2;color:#fff;padding:10px 0;border:none;border-radius:8px;font-weight:700;font-size:1rem;min-width:120px;">2시간 점검</button>
            <?php else: ?>
              <button type="submit" name="unset_maintenance" style="background:#005BAC;color:#fff;padding:10px 0;border:none;border-radius:8px;font-weight:700;font-size:1rem;min-width:120px;">점검 종료</button>
            <?php endif; ?>
          </form>
        </div>
      </div>
      <div style="flex:1 1 0;min-width:160px;background:linear-gradient(120deg,#ff4757 60%,#ff3742 100%);border-radius:14px;box-shadow:0 2px 8px rgba(255,71,87,0.18);padding:18px 10px;display:flex;flex-direction:column;align-items:center;">
        <span style="font-size:1.3rem;color:#fff;font-weight:700;display:flex;align-items:center;gap:6px;"><svg width="22" height="22" viewBox="0 0 24 24" fill="#fff" style="margin-right:4px;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-1-13h2v6h-2zm0 8h2v2h-2z"/></svg>오늘 보안 이벤트</span>
        <span style="font-size:1.5rem;font-weight:800;margin-top:6px;letter-spacing:1px;color:#fff;"> <?= isset($today_security_events) ? $today_security_events : '0' ?> 건</span>
      </div>
      <div style="flex:1 1 0;min-width:160px;background:linear-gradient(120deg,#e3f0ff 60%,#f8f9fa 100%);border-radius:14px;box-shadow:0 2px 8px rgba(0,91,172,0.10);padding:18px 10px;display:flex;flex-direction:column;align-items:center;">
        <span style="font-size:1.3rem;color:#005BAC;font-weight:700;">🔒 취약점 제보</span>
        <span style="font-size:1.5rem;font-weight:800;margin-top:6px;letter-spacing:1px;"> <?= isset($total_vul_reports) && is_numeric($total_vul_reports) ? $total_vul_reports : '0' ?> 건</span>
      </div>
      <div style="flex:1 1 0;min-width:160px;background:linear-gradient(120deg,#e3f0ff 60%,#f8f9fa 100%);border-radius:14px;box-shadow:0 2px 8px rgba(0,91,172,0.10);padding:18px 10px;display:flex;flex-direction:column;align-items:center;">
        <button id="noticeToggleBtn" style="background:#005BAC;color:#fff;padding:10px 22px;border:none;border-radius:8px;font-weight:700;font-size:1.05rem;">공지사항 관리</button>
        <div id="noticeTogglePanel" style="display:none;margin-top:12px;text-align:left;">
          <div style="display:flex;gap:24px;align-items:flex-start;justify-content:center;width:100%;min-width:600px;flex-wrap:nowrap;">
            <form method="post" style="background:#e3f2fd;border-radius:12px;padding:18px 20px 14px 20px;flex:1 1 0;min-width:260px;max-width:480px;box-shadow:0 2px 8px rgba(0,0,0,0.04);">
              <h4 style="margin:0 0 8px 0;font-size:1.01rem;color:#005BAC;">새 공지 등록</h4>
              <input type="text" name="notice_title" placeholder="공지 제목" required style="width:100%;padding:6px 8px;margin-bottom:6px;border-radius:6px;border:1.5px solid #b3c6e0;font-size:0.98rem;">
              <textarea name="notice_content" placeholder="공지 내용" required style="width:100%;padding:6px 8px;border-radius:6px;border:1.5px solid #b3c6e0;font-size:0.98rem;min-height:36px;margin-bottom:6px;"></textarea>
              <button type="submit" name="add_notice" style="background:#005BAC;color:#fff;padding:6px 16px;border:none;border-radius:8px;font-weight:600;font-size:0.98rem;">공지 등록</button>
            </form>
            <div style="background:#f8f9fa;border-radius:10px;padding:14px 16px;flex:1 1 0;min-width:260px;max-width:480px;box-shadow:0 2px 8px rgba(0,0,0,0.03);">
              <h4 style="margin:0 0 8px 0;font-size:1.01rem;color:#005BAC;">공지사항 목록</h4>
              <?php if (count($all_notices) > 0): ?>
                <ul style="padding-left:0;list-style:none;max-height:180px;overflow-y:auto;">
                  <?php foreach ($all_notices as $notice): ?>
                    <li style="margin-bottom:10px;padding-bottom:8px;border-bottom:1px solid #e0e0e0;">
                      <div style="font-weight:700;font-size:1.01rem;color:#003366;"> <?= htmlspecialchars($notice['title']) ?> </div>
                      <div style="font-size:0.97rem;color:#444;line-height:1.5;"> <?= nl2br(htmlspecialchars($notice['content'])) ?> </div>
                      <div style="font-size:0.92rem;color:#888;margin-top:2px;">등록일: <?= date('Y-m-d', strtotime($notice['created_at'])) ?></div>
                      <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:4px;">
                        <button type="button" onclick="showEditForm(<?= $notice['id'] ?>)" style="background:#005BAC;color:#fff;padding:4px 12px;border:none;border-radius:8px;font-weight:600;font-size:0.95rem;">수정</button>
                        <form method="post" style="display:inline;">
                          <input type="hidden" name="delete_notice_id" value="<?= $notice['id'] ?>">
                          <button type="submit" style="background:#E53935;color:#fff;padding:4px 12px;border:none;border-radius:8px;font-weight:600;font-size:0.95rem;">삭제</button>
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
          <style>@media (max-width: 900px) { #noticeTogglePanel > div { flex-direction:column !important; gap:12px !important; min-width:0 !important; } }</style>
        </div>
      </div>
    </section>
    <!-- 중앙 주요 카드 3개 (운영/시스템, 고장/점검, 보안 통합) - 좌우 넓게 가로 스크롤/반응형 -->
    <section style="overflow-x:auto;white-space:nowrap;padding-bottom:8px;margin:36px 0 24px 0;">
      <div style="display:inline-flex;gap:32px;min-width:700px;">
        <div onclick="showOpsMenu(event)" style="min-width:260px;max-width:340px;cursor:pointer;background:linear-gradient(120deg,#e3f0ff 60%,#f8f9fa 100%);border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,0.07);padding:36px 18px;display:flex;flex-direction:column;align-items:center;transition:box-shadow 0.2s;position:relative;">
          <span style="font-size:2.2rem;color:#005BAC;font-weight:700;display:flex;align-items:center;gap:8px;">👤 운영/시스템</span>
          <span style="font-size:1.1rem;font-weight:600;margin-top:8px;color:#005BAC;">계정/파일/시스템 관리</span>
          <div class="ops-menu" style="display:none;position:absolute;top:80px;left:50%;transform:translateX(-50%);background:#fff;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,0.13);padding:18px 0;z-index:20;min-width:180px;">
            <button onclick="location.href='admin/user_management.php';event.stopPropagation();" style="display:block;width:100%;background:none;border:none;padding:12px 24px;font-size:1.08rem;color:#005BAC;font-weight:700;text-align:left;cursor:pointer;transition:background 0.15s;">계정 관리</button>
            <button onclick="location.href='admin/file_management.php';event.stopPropagation();" style="display:block;width:100%;background:none;border:none;padding:12px 24px;font-size:1.08rem;color:#005BAC;font-weight:700;text-align:left;cursor:pointer;transition:background 0.15s;">파일 관리</button>
            <button onclick="location.href='admin/system_status.php';event.stopPropagation();" style="display:block;width:100%;background:none;border:none;padding:12px 24px;font-size:1.08rem;color:#005BAC;font-weight:700;text-align:left;cursor:pointer;transition:background 0.15s;">시스템 모니터링</button>
          </div>
        </div>
        <div onclick="location.href='admin/fault_maintenance_history.php'" style="min-width:260px;max-width:340px;cursor:pointer;background:linear-gradient(120deg,#fffbe3 60%,#f9f8fa 100%);border-radius:16px;box-shadow:0 2px 12px rgba(255,179,0,0.10);padding:36px 18px;display:flex;flex-direction:column;align-items:center;transition:box-shadow 0.2s;">
          <span style="font-size:2.2rem;color:#FFB300;font-weight:700;display:flex;align-items:center;gap:8px;">📝 고장/점검</span>
          <span style="font-size:1.1rem;font-weight:600;margin-top:8px;color:#FFB300;">이력/통계/파일</span>
        </div>
        <div onclick="location.href='admin/security_center.php'" style="min-width:260px;max-width:340px;cursor:pointer;background:linear-gradient(120deg,#ffe3e3 60%,#f8f9fa 100%);border-radius:16px;box-shadow:0 2px 12px rgba(255,71,87,0.12);padding:36px 18px;display:flex;flex-direction:column;align-items:center;transition:box-shadow 0.2s;">
          <span style="font-size:2.2rem;color:#ff4757;font-weight:700;display:flex;align-items:center;gap:8px;">🛡️ 보안 통합</span>
          <span style="font-size:1.1rem;font-weight:600;margin-top:8px;color:#ff4757;">보안 이벤트/취약점/테스트</span>
        </div>
      </div>
    </section>
    <!-- 하단 차트/통계(기존 유지, 더 심플하게, 데이터 없을 때 안내) -->
    <section style="margin:0 0 32px 0;">
      <div style="display:flex;flex-wrap:wrap;gap:40px;justify-content:center;align-items:flex-start;">
        <div style="flex:1 1 340px;min-width:280px;max-width:480px;background:linear-gradient(120deg,#f8f9fa 60%,#e3f0ff 100%);padding:28px 18px 18px 18px;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,0.07);">
          <canvas id="logChart" height="110"></canvas>
          <div id="logChartEmpty" style="display:none;text-align:center;color:#aaa;padding:24px 0;">일별 로그 데이터가 없습니다.</div>
        </div>
        <div style="flex:1 1 260px;min-width:200px;max-width:340px;background:linear-gradient(120deg,#f8f9fa 60%,#e3f0ff 100%);padding:28px 18px 18px 18px;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,0.07);">
          <canvas id="typeChart" height="110"></canvas>
          <div id="typeChartEmpty" style="display:none;text-align:center;color:#aaa;padding:24px 0;">유형별 로그 데이터가 없습니다.</div>
        </div>
        <div style="flex:1 1 260px;min-width:200px;max-width:340px;background:linear-gradient(120deg,#f8f9fa 60%,#e3f0ff 100%);padding:28px 18px 18px 18px;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,0.07);">
          <canvas id="userChart" height="110"></canvas>
          <div id="userChartEmpty" style="display:none;text-align:center;color:#aaa;padding:24px 0;">사용자별 로그 데이터가 없습니다.</div>
        </div>
      </div>
    </section>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    // 공지사항 관리 토글
    document.getElementById('noticeToggleBtn').onclick = function() {
      var panel = document.getElementById('noticeTogglePanel');
      panel.style.display = panel.style.display === 'block' ? 'none' : 'block';
    };
    // 점검상태 카드 토글 + 애니메이션
    function toggleMaintenanceForm() {
      var form = document.getElementById('maintenanceForm');
      if (form.style.display === 'block') {
        form.style.opacity = 1;
        form.style.transition = 'opacity 0.3s';
        form.style.opacity = 0;
        setTimeout(function(){form.style.display = 'none';}, 300);
      } else {
        form.style.display = 'block';
        form.style.opacity = 0;
        setTimeout(function(){form.style.transition = 'opacity 0.3s';form.style.opacity = 1;}, 10);
      }
    }
    // 차트 데이터 예외처리 및 안내
    document.addEventListener('DOMContentLoaded', function() {
      // 1. 최근 7일간 일별 로그 수 (Line)
      const logData = <?= json_encode($log_counts_by_date ?? []) ?>;
      const logLabels = <?= json_encode($dates ?? []) ?>;
      if (!logData || logData.length === 0 || logData.every(v=>v===0)) {
        document.getElementById('logChart').style.display = 'none';
        document.getElementById('logChartEmpty').style.display = 'block';
      } else {
        const ctx = document.getElementById('logChart').getContext('2d');
        new Chart(ctx, {
          type: 'line',
          data: {
            labels: logLabels,
            datasets: [{
              label: '일별 로그 수',
              data: logData,
              borderColor: '#3C8DBC',
              backgroundColor: 'rgba(60,139,188,0.08)',
              fill: true,
              tension: 0.3
            }]
          },
          options: {
            responsive: true,
            plugins: { legend: { display: true } },
            scales: { y: { beginAtZero: true } }
          }
        });
      }
      // 2. 유형별 로그 수 (Bar)
      const typeData = <?= json_encode($type_counts ?? []) ?>;
      const typeLabels = <?= json_encode($type_labels ?? []) ?>;
      if (!typeData || typeData.length === 0 || typeData.every(v=>v===0)) {
        document.getElementById('typeChart').style.display = 'none';
        document.getElementById('typeChartEmpty').style.display = 'block';
      } else {
        const typeChart = document.getElementById('typeChart').getContext('2d');
        new Chart(typeChart, {
          type: 'bar',
          data: {
            labels: typeLabels,
            datasets: [{
              label: '유형별 로그 수',
              data: typeData,
              backgroundColor: '#FFB300',
              borderColor: '#FFB300',
              borderWidth: 1
            }]
          },
          options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
          }
        });
      }
      // 3. 사용자별 로그 수 (Pie)
      const userData = <?= json_encode($user_counts ?? []) ?>;
      const userLabels = <?= json_encode($user_labels ?? []) ?>;
      if (!userData || userData.length === 0 || userData.every(v=>v===0)) {
        document.getElementById('userChart').style.display = 'none';
        document.getElementById('userChartEmpty').style.display = 'block';
      } else {
        const userChart = document.getElementById('userChart').getContext('2d');
        new Chart(userChart, {
          type: 'pie',
          data: {
            labels: userLabels,
            datasets: [{
              label: '사용자별 로그 수',
              data: userData,
              backgroundColor: ['#3C8DBC','#FFB300','#4CAF50','#E91E63','#9C27B0'],
            }]
          },
          options: {
            responsive: true,
            plugins: { legend: { display: true } }
          }
        });
      }
    });
    // 공지사항 수정 폼 토글(기존 함수 재사용)
    function showEditForm(id) {
      var form = document.getElementById('notice-edit-form-' + id);
      var view = document.getElementById('notice-view-' + id);
      if (form && view) {
        form.style.display = 'block';
        view.style.display = 'none';
      }
    }
    // 운영/시스템 카드 메뉴 토글 함수
    function showOpsMenu(event) {
      // 이미 열려있는 메뉴 닫기
      document.querySelectorAll('.ops-menu').forEach(menu => menu.style.display = 'none');
      // 현재 클릭한 카드의 ops-menu만 열기
      const card = event.currentTarget;
      const menu = card.querySelector('.ops-menu');
      if (menu) {
        menu.style.display = 'block';
        // 외부 클릭 시 닫기
        document.addEventListener('click', function handler(e) {
          if (!card.contains(e.target)) {
            menu.style.display = 'none';
            document.removeEventListener('click', handler);
          }
        });
      }
      event.stopPropagation();
    }
    </script>
  </main>
<?php endif; ?>
    <!-- 공지사항 카드 (모든 사용자) -->
    <?php if (count($notices) > 0): ?>
      <section class="notice-section" style="text-align:center; margin-bottom:32px;">
          <h2 style="font-size:2rem; font-weight:800; margin-bottom:20px; letter-spacing:0.5px;">최근 공지사항</h2>
          <?php foreach ($notices as $notice): ?>
              <div class="notice-card" style="display:inline-block; vertical-align:top; margin:0 16px 16px 16px; padding:32px 36px 24px 36px; border-radius:18px; background:#f8f9fa; box-shadow:0 4px 24px #e3eaf5; min-width:320px; max-width:480px; text-align:left; position:relative;">
                  <div style="font-size:1.45rem; font-weight:900; color:#003366; margin-bottom:10px; letter-spacing:0.5px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                      <?=htmlspecialchars($notice['title'])?>
                  </div>
                  <div style="font-size:1.08rem; color:#333; line-height:1.7; max-height:3.4em; overflow:hidden; text-overflow:ellipsis; margin-bottom:18px;">
                      <?=nl2br(htmlspecialchars($notice['content']))?>
                  </div>
                  <div style="font-size:0.98rem; color:#888; position:absolute; bottom:16px; left:36px;">
                      작성자: <?=htmlspecialchars($notice['author'] ?? '관리자')?>
                  </div>
                  <div style="font-size:0.98rem; color:#aaa; position:absolute; bottom:16px; right:36px;">
                      <?=htmlspecialchars($notice['created_at'])?>
                  </div>
              </div>
          <?php endforeach; ?>
      </section>
    <?php endif; ?>

    <!-- 보안 로그 모달 -->
    <div id="securityModal" style="display:none;position:fixed;z-index:9999;left:0;top:0;width:100vw;height:100vh;background:rgba(0,0,0,0.5);justify-content:center;align-items:center;">
      <div style="background:#fff;border-radius:16px;box-shadow:0 8px 32px rgba(0,0,0,0.2);padding:0;min-width:320px;max-width:90vw;max-height:90vh;overflow:hidden;position:relative;">
        <!-- 모달 헤더 -->
        <div style="background:linear-gradient(135deg,#ff4757,#ff3742);color:#fff;padding:24px 28px;display:flex;justify-content:space-between;align-items:center;">
          <h3 style="margin:0;font-size:1.3rem;font-weight:700;display:flex;align-items:center;gap:8px;">
            🚨 PHPIDS 보안 로그
          </h3>
          <button onclick="closeSecurityModal()" style="background:none;border:none;color:#fff;font-size:24px;cursor:pointer;padding:0;width:32px;height:32px;display:flex;align-items:center;justify-content:center;">&times;</button>
        </div>
        
        <!-- 모달 내용 -->
        <div style="padding:24px 28px;max-height:60vh;overflow-y:auto;">
          <!-- 통계 정보 -->
          <div style="display:flex;gap:16px;margin-bottom:24px;flex-wrap:wrap;">
            <div style="background:linear-gradient(135deg,#ff4757,#ff3742);color:#fff;padding:16px;border-radius:12px;flex:1;min-width:120px;text-align:center;">
              <div style="font-size:1.8rem;font-weight:800;" id="totalSecurityEvents">-</div>
              <div style="font-size:0.9rem;opacity:0.9;">총 보안 이벤트</div>
            </div>
            <div style="background:linear-gradient(135deg,#ffa502,#ff9500);color:#fff;padding:16px;border-radius:12px;flex:1;min-width:120px;text-align:center;">
              <div style="font-size:1.8rem;font-weight:800;" id="todaySecurityEvents">-</div>
              <div style="font-size:0.9rem;opacity:0.9;">오늘 이벤트</div>
            </div>
          </div>
          
          <!-- 로그 목록 -->
          <div id="securityLogsList" style="space-y:16px;">
            <div style="text-align:center;padding:40px;color:#666;">
              <div style="font-size:1.2rem;margin-bottom:8px;">📊 로그를 불러오는 중...</div>
              <div style="font-size:0.9rem;">잠시만 기다려주세요</div>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- 운영/시스템 메뉴 모달 -->
    <div id="opsMenuModal" style="display:none;position:fixed;z-index:9999;left:0;top:0;width:100vw;height:100vh;background:rgba(0,0,0,0.18);justify-content:center;align-items:center;">
      <div style="background:#fff;border-radius:16px;box-shadow:0 8px 32px rgba(0,0,0,0.13);padding:0;min-width:260px;max-width:90vw;overflow:hidden;position:relative;">
        <div style="padding:32px 36px 24px 36px;display:flex;flex-direction:column;gap:24px;align-items:center;">
          <button onclick="location.href='admin/user_management.php';event.stopPropagation();" style="background:none;border:none;padding:16px 32px;font-size:1.25rem;color:#005BAC;font-weight:700;text-align:center;cursor:pointer;transition:background 0.15s;border-radius:10px;width:100%;">계정 관리</button>
          <button onclick="location.href='admin/file_management.php';event.stopPropagation();" style="background:none;border:none;padding:16px 32px;font-size:1.25rem;color:#005BAC;font-weight:700;text-align:center;cursor:pointer;transition:background 0.15s;border-radius:10px;width:100%;">파일 관리</button>
          <button onclick="location.href='admin/system_status.php';event.stopPropagation();" style="background:none;border:none;padding:16px 32px;font-size:1.25rem;color:#005BAC;font-weight:700;text-align:center;cursor:pointer;transition:background 0.15s;border-radius:10px;width:100%;">시스템 모니터링</button>
        </div>
        <button onclick="closeOpsMenuModal()" style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:2rem;color:#888;cursor:pointer;">&times;</button>
      </div>
    </div>

    <script>
    // 보안 로그 모달 관련 함수들
    function showSecurityModal() {
      document.getElementById('securityModal').style.display = 'flex';
      loadSecurityLogs();
    }
    
    function closeSecurityModal() {
      document.getElementById('securityModal').style.display = 'none';
    }
    
    function loadSecurityLogs() {
      const logsContainer = document.getElementById('securityLogsList');
      const totalEvents = document.getElementById('totalSecurityEvents');
      const todayEvents = document.getElementById('todaySecurityEvents');
      
      // 로딩 상태 표시
      logsContainer.innerHTML = `
        <div style="text-align:center;padding:40px;color:#666;">
          <div style="font-size:1.2rem;margin-bottom:8px;">📊 로그를 불러오는 중...</div>
          <div style="font-size:0.9rem;">잠시만 기다려주세요</div>
        </div>
      `;
      
      // AJAX로 로그 데이터 가져오기
      fetch('/admin/get_security_logs.php')
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            // 통계 업데이트
            totalEvents.textContent = data.stats.total;
            todayEvents.textContent = data.stats.today;
            
            // 로그 목록 렌더링
            if (data.logs.length > 0) {
              logsContainer.innerHTML = data.logs.map(log => `
                <div style="background:${getImpactColor(log.impact_class)};border-radius:12px;padding:20px;margin-bottom:16px;color:#fff;box-shadow:0 2px 8px rgba(0,0,0,0.1);">
                  <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px;">
                    <div style="display:flex;align-items:center;gap:8px;">
                      <span style="font-size:1.2rem;">${log.impact_icon}</span>
                      <span style="font-weight:700;font-size:1.1rem;">보안이벤트 (${log.impact_level})</span>
                    </div>
                    <span style="font-size:0.9rem;opacity:0.8;">${log.formatted_time}</span>
                  </div>
                  <div style="display:flex;gap:16px;margin-bottom:12px;flex-wrap:wrap;">
                    <span style="font-size:0.9rem;opacity:0.9;">사용자: ${log.username}</span>
                    <span style="font-size:0.9rem;opacity:0.9;">IP: ${log.ip_address}</span>
                  </div>
                  <div style="background:rgba(255,255,255,0.1);padding:12px;border-radius:8px;font-size:0.95rem;line-height:1.5;white-space:pre-wrap;">
                    ${log.log_message}
                  </div>
                </div>
              `).join('');
            } else {
              logsContainer.innerHTML = `
                <div style="text-align:center;padding:40px;color:#666;">
                  <div style="font-size:1.2rem;margin-bottom:8px;">🚀 보안 이벤트가 없습니다!</div>
                  <div style="font-size:0.9rem;">시스템이 안전합니다</div>
                </div>
              `;
            }
          } else {
            logsContainer.innerHTML = `
              <div style="text-align:center;padding:40px;color:#e74c3c;">
                <div style="font-size:1.2rem;margin-bottom:8px;">❌ 오류가 발생했습니다</div>
                <div style="font-size:0.9rem;">${data.error || '알 수 없는 오류'}</div>
              </div>
            `;
          }
        })
        .catch(error => {
          logsContainer.innerHTML = `
            <div style="text-align:center;padding:40px;color:#e74c3c;">
              <div style="font-size:1.2rem;margin-bottom:8px;">❌ 네트워크 오류</div>
              <div style="font-size:0.9rem;">로그를 불러올 수 없습니다</div>
            </div>
          `;
          console.error('Error:', error);
        });
    }
    
    function getImpactColor(impactClass) {
      switch(impactClass) {
        case 'danger': return 'linear-gradient(135deg,#ff4757,#ff3742)';
        case 'warning': return 'linear-gradient(135deg,#ffa502,#ff9500)';
        case 'info': return 'linear-gradient(135deg,#2ed573,#1e90ff)';
        default: return 'linear-gradient(135deg,#747d8c,#57606f)';
      }
    }
    
    // 모달 외부 클릭 시 닫기
    document.getElementById('securityModal').addEventListener('click', function(e) {
      if (e.target === this) {
        closeSecurityModal();
      }
    });
    
    // ESC 키로 모달 닫기
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape' && document.getElementById('securityModal').style.display === 'flex') {
        closeSecurityModal();
      }
    });

    // 운영/시스템 메뉴 모달 관련 함수들
    function showOpsMenu(event) {
      var modal = document.getElementById('opsMenuModal');
      modal.style.display = 'flex';
      // 외부 클릭 시 닫기
      modal.onclick = function(e) {
        if (e.target === modal) closeOpsMenuModal();
      };
      // ESC로 닫기
      document.addEventListener('keydown', escHandler);
      event.stopPropagation();
    }
    function closeOpsMenuModal() {
      var modal = document.getElementById('opsMenuModal');
      modal.style.display = 'none';
      document.removeEventListener('keydown', escHandler);
    }
    function escHandler(e) {
      if (e.key === 'Escape') closeOpsMenuModal();
    }
    </script>
  </main>
  <?php if (isset($_SESSION['guest'])): ?>
  <main id="main-content" class="main-content" tabindex="-1" style="padding:0;background:transparent;box-shadow:none;max-width:1100px;">
    <h3 style="display:flex;align-items:center;gap:10px;">내 최근 활동</h3>
    <section style="display:flex;gap:24px;flex-wrap:wrap;margin:36px 0 24px 0;justify-content:space-between;">
      <div style="flex:1 1 0;min-width:220px;background:linear-gradient(120deg,#f8f9fa 60%,#e3f0ff 100%);border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,0.07);padding:24px 16px;display:flex;flex-direction:column;gap:10px;">
        <div style="font-weight:700;font-size:1.1rem;color:#005BAC;">📝 내가 쓴 고장 제보</div>
        <?php if (!empty($my_faults)): ?>
          <?php foreach ($my_faults as $fault): ?>
            <div style="font-size:0.98rem;color:#333;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
              <?= htmlspecialchars($fault['part']) ?> <span style="color:#888;font-size:0.92rem;">(<?= $fault['created_at'] ?>)</span>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div style="color:#aaa;font-size:0.97rem;">작성한 고장 제보가 없습니다.</div>
        <?php endif; ?>
      </div>
      <div style="flex:1 1 0;min-width:220px;background:linear-gradient(120deg,#f8f9fa 60%,#e3f0ff 100%);border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,0.07);padding:24px 16px;display:flex;flex-direction:column;gap:10px;">
        <div style="font-weight:700;font-size:1.1rem;color:#005BAC;">🔒 내가 쓴 취약점 제보</div>
        <?php if (!empty($my_vul_reports)): ?>
          <?php foreach ($my_vul_reports as $vul): ?>
            <div style="font-size:0.98rem;color:#333;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
              <?= htmlspecialchars($vul['title']) ?> <span style="color:#888;font-size:0.92rem;">(<?= $vul['created_at'] ?>)</span>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div style="color:#aaa;font-size:0.97rem;">작성한 취약점 제보가 없습니다.</div>
        <?php endif; ?>
      </div>
      <div style="flex:1 1 0;min-width:220px;background:linear-gradient(120deg,#f8f9fa 60%,#e3f0ff 100%);border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,0.07);padding:24px 16px;display:flex;flex-direction:column;gap:10px;">
        <div style="font-weight:700;font-size:1.1rem;color:#005BAC;">🔔 내 최근 알림</div>
        <?php if (!empty($my_notifications)): ?>
          <?php foreach ($my_notifications as $noti): ?>
            <div style="font-size:0.98rem;color:#333;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
              <?= htmlspecialchars($noti['message']) ?> <span style="color:#888;font-size:0.92rem;">(<?= $noti['time'] ?? $noti['created_at'] ?>)</span>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div style="color:#aaa;font-size:0.97rem;">알림이 없습니다.</div>
        <?php endif; ?>
      </div>
    </section>
  </main>
<?php endif; ?>
  <footer class="footer" role="contentinfo">
    <div>가천대학교 CPS |  <a href="#" style="color:#FFB300; text-decoration:underline;">이용약관</a> | <a href="#" style="color:#FFB300; text-decoration:underline;">개인정보처리방침</a> | 고객센터: 1234-5678</div>
    <div style="margin-top:8px;">© 2025 PLC Control</div>
  </footer>
  <?php if (isset($_SESSION['admin'])): ?>
  <script>
  let notifyTimer = null;
  function fetchNotifications() {
    fetch('notify_api.php')
      .then(res => res.json())
      .then(data => {
        const badge = document.getElementById('notifyBadge');
        const list = document.getElementById('notifyList');
        if (data.unread > 0) {
          badge.textContent = data.unread;
          badge.style.display = 'inline-block';
        } else {
          badge.style.display = 'none';
        }
        if (data.notifications.length > 0) {
          list.innerHTML = data.notifications.map(n => `
            <div style="padding:12px 16px;border-bottom:1px solid #eee;cursor:pointer;${n.is_read?'opacity:0.6;':''}" onclick="readNotification(${n.id}, '${n.url}')">
              <span style="font-weight:600;">${n.type_icon} ${n.type_label}</span>
              <span style="display:block;font-size:0.97rem;margin-top:2px;">${n.message}</span>
              <span style="font-size:0.85rem;color:#888;float:right;">${n.time}</span>
            </div>
          `).join('');
        } else {
          list.innerHTML = '<div style="padding:24px;text-align:center;color:#888;">알림이 없습니다.</div>';
        }
      });
  }
  function readNotification(id, url) {
    fetch('notify_api.php?action=read&id='+id)
      .then(()=>{fetchNotifications(); if(url) location.href=url;});
  }
  document.getElementById('notifyBtn').onclick = function(e) {
    e.stopPropagation();
    const dropdown = document.getElementById('notifyDropdown');
    dropdown.style.display = dropdown.style.display==='block?'none':'block';
    if(dropdown.style.display==='block') fetchNotifications();
  };
  document.body.onclick = function() {
    document.getElementById('notifyDropdown').style.display = 'none';
  };
  notifyTimer = setInterval(fetchNotifications, 10000); // 10초마다 갱신
  </script>
  <?php endif; ?>
</body>
</html>
