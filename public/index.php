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
    <section style="display:flex;gap:24px;flex-wrap:wrap;margin:36px 0 24px 0;justify-content:space-between;">
      <div onclick="location.href='admin/user_management.php'" style="flex:1 1 0;min-width:200px;cursor:pointer;background:linear-gradient(120deg,#e3f0ff 60%,#f8f9fa 100%);border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,0.07);padding:28px 18px;display:flex;flex-direction:column;align-items:center;transition:box-shadow 0.2s;" onmouseover="this.style.boxShadow='0 4px 20px rgba(0,91,172,0.15)'" onmouseout="this.style.boxShadow='0 2px 12px rgba(0,0,0,0.07)'">
        <span style="font-size:2.2rem;color:#005BAC;font-weight:700;display:flex;align-items:center;gap:8px;">👤 사용자</span>
        <span style="font-size:2.1rem;font-weight:800;margin-top:8px;letter-spacing:1px;">관리</span>
        <span style="font-size:0.9rem;font-weight:600;margin-top:4px;color:#005BAC;">계정/권한/비밀번호</span>
      </div>
      <div onclick="location.href='admin/file_management.php'" style="flex:1 1 0;min-width:200px;cursor:pointer;background:linear-gradient(120deg,#f8e3ff 60%,#f9f8fa 100%);border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,0.07);padding:28px 18px;display:flex;flex-direction:column;align-items:center;transition:box-shadow 0.2s;" onmouseover="this.style.boxShadow='0 4px 20px rgba(156,39,176,0.15)" onmouseout="this.style.boxShadow='0 2px 12px rgba(0,0,0,0.07)'">
        <span style="font-size:2.2rem;color:#9C27B0;font-weight:700;display:flex;align-items:center;gap:8px;">📁 파일</span>
        <span style="font-size:2.1rem;font-weight:800;margin-top:8px;letter-spacing:1px;">관리</span>
        <span style="font-size:0.9rem;font-weight:600;margin-top:4px;color:#9C27B0;">업로드/다운로드/위험</span>
      </div>
      <div onclick="location.href='admin/system_status.php'" style="flex:1 1 0;min-width:200px;cursor:pointer;background:linear-gradient(120deg,#e3ffe3 60%,#f8f9fa 100%);border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,0.07);padding:28px 18px;display:flex;flex-direction:column;align-items:center;transition:box-shadow 0.2s;" onmouseover="this.style.boxShadow='0 4px 20px rgba(67,233,123,0.15)'" onmouseout="this.style.boxShadow='0 2px 12px rgba(0,0,0,0.07)'">
        <span style="font-size:2.2rem;color:#43e97b;font-weight:700;display:flex;align-items:center;gap:8px;">🖥️ 시스템</span>
        <span style="font-size:2.1rem;font-weight:800;margin-top:8px;letter-spacing:1px;">모니터링</span>
        <span style="font-size:0.9rem;font-weight:600;margin-top:4px;color:#43e97b;">CPU/메모리/디스크</span>
      </div>
      <div onclick="location.href='admin/fault_maintenance_history.php'" style="flex:1 1 0;min-width:200px;cursor:pointer;background:linear-gradient(120deg,#fffbe3 60%,#f9f8fa 100%);border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,0.07);padding:28px 18px;display:flex;flex-direction:column;align-items:center;transition:box-shadow 0.2s;" onmouseover="this.style.boxShadow='0 4px 20px rgba(255,179,0,0.15)'" onmouseout="this.style.boxShadow='0 2px 12px rgba(0,0,0,0.07)'">
        <span style="font-size:2.2rem;color:#FFB300;font-weight:700;display:flex;align-items:center;gap:8px;">📝 고장/점검</span>
        <span style="font-size:2.1rem;font-weight:800;margin-top:8px;letter-spacing:1px;">이력</span>
        <span style="font-size:0.9rem;font-weight:600;margin-top:4px;color:#FFB300;">상세/통계/파일</span>
      </div>
      <div onclick="location.href='admin/notice_banner_popup.php'" style="flex:1 1 0;min-width:200px;cursor:pointer;background:linear-gradient(120deg,#e3f0ff 60%,#f8f9fa 100%);border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,0.07);padding:28px 18px;display:flex;flex-direction:column;align-items:center;transition:box-shadow 0.2s;" onmouseover="this.style.boxShadow='0 4px 20px rgba(0,91,172,0.15)'" onmouseout="this.style.boxShadow='0 2px 12px rgba(0,0,0,0.07)'">
        <span style="font-size:2.2rem;color:#005BAC;font-weight:700;display:flex;align-items:center;gap:8px;">📢 공지/배너/팝업</span>
        <span style="font-size:2.1rem;font-weight:800;margin-top:8px;letter-spacing:1px;">관리</span>
        <span style="font-size:0.9rem;font-weight:600;margin-top:4px;color:#005BAC;">공지/이벤트/노출</span>
      </div>
    </section>
    <!-- 현황 카드 4분할 (관리자만) -->
    <section style="display:flex;gap:28px;justify-content:space-between;flex-wrap:wrap;margin:36px 0 24px 0;">
      <div style="flex:1 1 0;min-width:180px;background:linear-gradient(120deg,#e3f0ff 60%,#f8f9fa 100%);border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,0.07);padding:28px 18px;display:flex;flex-direction:column;align-items:center;">
        <span style="font-size:2.2rem;color:#005BAC;font-weight:700;display:flex;align-items:center;gap:8px;"><svg width='28' height='28' viewBox='0 0 24 24' fill='none'><circle cx='12' cy='12' r='12' fill='#FFB300'/><path d='M12 7v5' stroke='#fff' stroke-width='2.2' stroke-linecap='round'/><circle cx='12' cy='16' r='1.3' fill='#fff'/></svg>고장</span>
        <span style="font-size:2.1rem;font-weight:800;margin-top:8px;letter-spacing:1px;"> <?= isset($total_faults) ? $total_faults : '?' ?> 건</span>
      </div>
      <div style="flex:1 1 0;min-width:180px;background:linear-gradient(120deg,#e3f0ff 60%,#f8f9fa 100%);border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,0.07);padding:28px 18px;display:flex;flex-direction:column;align-items:center;">
        <span style="font-size:2.2rem;color:#43e97b;font-weight:700;display:flex;align-items:center;gap:8px;"><svg width='28' height='28' viewBox='0 0 24 24' fill='none'><circle cx='12' cy='12' r='12' fill='#43e97b'/><path d='M12 7v5' stroke='#fff' stroke-width='2.2' stroke-linecap='round'/><circle cx='12' cy='16' r='1.3' fill='#fff'/></svg>점검상태</span>
        <span style="font-size:1.3rem;font-weight:700;margin-top:8px;letter-spacing:1px;"> <?= isset($is_maintenance) ? ($is_maintenance ? '점검중' : '정상') : '?' ?> </span>
      </div>
      <div style="flex:1 1 0;min-width:180px;background:linear-gradient(120deg,#e3f0ff 60%,#f8f9fa 100%);border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,0.07);padding:28px 18px;display:flex;flex-direction:column;align-items:center;">
        <span style="font-size:2.2rem;color:#E53935;font-weight:700;display:flex;align-items:center;gap:8px;"><svg width='28' height='28' viewBox='0 0 24 24' fill='none'><circle cx='12' cy='12' r='12' fill='#E53935'/><path d='M12 7v5' stroke='#fff' stroke-width='2.2' stroke-linecap='round'/><circle cx='12' cy='16' r='1.3' fill='#fff'/></svg>오늘 접수</span>
        <span style="font-size:2.1rem;font-weight:800;margin-top:8px;letter-spacing:1px;"> <?= isset($today_faults) ? $today_faults : '?' ?> 건</span>
      </div>
      <div onclick="showSecurityModal()" style="flex:1 1 0;min-width:180px;background:linear-gradient(120deg,#ff4757 60%,#ff3742 100%);border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,0.07);padding:28px 18px;display:flex;flex-direction:column;align-items:center;cursor:pointer;transition:transform 0.2s,box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 20px rgba(0,0,0,0.15)'" onmouseout="this.style.transform='translateY(0)';this.style.boxShadow='0 2px 12px rgba(0,0,0,0.07)'">
        <span style="font-size:2.2rem;color:#fff;font-weight:700;display:flex;align-items:center;gap:8px;"><svg width='28' height='28' viewBox='0 0 24 24' fill='none'><circle cx='12' cy='12' r='12' fill='#fff'/><path d='M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z' fill='#ff4757'/></svg>보안이벤트</span>
        <span style="font-size:2.1rem;font-weight:800;margin-top:8px;letter-spacing:1px;color:#fff;"> <?= isset($total_security_events) ? $total_security_events : '?' ?> 건</span>
        <span style="font-size:0.9rem;font-weight:600;margin-top:4px;color:rgba(255,255,255,0.8);">오늘: <?= isset($today_security_events) ? $today_security_events : '?' ?>건</span>
        <span style="font-size:0.8rem;font-weight:500;margin-top:8px;color:rgba(255,255,255,0.7);">클릭하여 상세보기</span>
      </div>
    </section>
    <?php endif; ?>
    <!-- 공지사항 카드 (모든 사용자) -->
    <?php if (count($notices) > 0): ?>
      <section class="notice-section" style="margin:0 0 32px 0;">
        <h3 style="color:#005BAC;font-size:1.25rem;margin-bottom:16px;display:flex;align-items:center;gap:8px;font-weight:700;">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm0-4h-2V7h2v8z" fill="#005BAC"/></svg>
          최근 공지사항
        </h3>
        <div style="display:flex;gap:24px;flex-wrap:wrap;">
          <?php foreach ($notices as $notice): ?>
            <div style="background:linear-gradient(120deg,#f8f9fa 60%,#e3f0ff 100%);border-radius:14px;box-shadow:0 2px 8px rgba(0,0,0,0.06);padding:22px 28px 18px 28px;min-width:220px;max-width:340px;flex:1 1 220px;display:flex;flex-direction:column;gap:8px;transition:box-shadow 0.2s;">
              <div style="font-weight:800;font-size:1.13rem;color:#003366;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;letter-spacing:0.5px;">
                <?= htmlspecialchars($notice['title']) ?>
              </div>
              <div style="font-size:1.01rem;color:#444;line-height:1.7;max-height:3.2em;overflow:hidden;text-overflow:ellipsis;">
                <?= nl2br(htmlspecialchars($notice['content'])) ?>
              </div>
              <div style="font-size:0.97rem;color:#888;margin-top:4px;align-self:flex-end;">
                <?= date('Y-m-d', strtotime($notice['created_at'])) ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>
    <?php if (isset($_SESSION['admin'])): ?>
    <!-- 관리자 전용 섹션 -->
    <section style="margin:0 0 32px 0;">
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:18px;">
        <span style="background:#005BAC;color:#fff;font-size:0.98rem;font-weight:700;padding:4px 14px;border-radius:8px;">관리자 전용</span>
        <span style="font-size:1.18rem;font-weight:700;color:#005BAC;">서버 점검 관리</span>
      </div>
      <div style="background:linear-gradient(120deg,#f8f9fa 60%,#e3f0ff 100%);border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,0.07);padding:28px 24px 18px 24px;display:flex;flex-wrap:wrap;align-items:center;gap:24px;min-height:80px;">
        <?php
        // 점검 상태 확인 (이미 위에서 조회했으므로 재사용)
        $end_at = $is_maintenance ? $pdo->query("SELECT end_at FROM maintenance WHERE is_active=1 ORDER BY id DESC LIMIT 1")->fetchColumn() : null;
        ?>
        <form method="post" style="margin:0;display:flex;gap:18px;flex-wrap:wrap;align-items:center;">
          <?php if (!$is_maintenance): ?>
            <input type="hidden" name="set_maintenance" value="1">
            <button type="submit" name="duration" value="30" style="background:#1976D2;color:#fff;padding:18px 0;border:none;border-radius:10px;font-weight:700;font-size:1.15rem;min-width:140px;letter-spacing:1px;">30분 점검</button>
            <button type="submit" name="duration" value="60" style="background:#1976D2;color:#fff;padding:18px 0;border:none;border-radius:10px;font-weight:700;font-size:1.15rem;min-width:140px;letter-spacing:1px;">1시간 점검</button>
            <button type="submit" name="duration" value="90" style="background:#1976D2;color:#fff;padding:18px 0;border:none;border-radius:10px;font-weight:700;font-size:1.15rem;min-width:140px;letter-spacing:1px;">1시간 30분 점검</button>
            <button type="submit" name="duration" value="120" style="background:#1976D2;color:#fff;padding:18px 0;border:none;border-radius:10px;font-weight:700;font-size:1.15rem;min-width:140px;letter-spacing:1px;">2시간 점검</button>
          <?php else: ?>
            <button type="submit" name="unset_maintenance" style="background:#005BAC;color:#fff;padding:18px 0;border:none;border-radius:10px;font-weight:700;font-size:1.15rem;min-width:140px;letter-spacing:1px;">점검 종료</button>
          <?php endif; ?>
        </form>
        <?php if ($is_maintenance && $end_at): ?>
        <div id="maintenance-timer" style="font-size:1.12rem;color:#B23C2A;font-weight:600;white-space:nowrap;min-width:180px;">
          남은 점검 시간: <span id="timer-remaining"></span>
        </div>
        <script>
        function updateTimer() {
          var endAt = new Date('<?= $end_at ?>'.replace(/-/g, '/'));
          var now = new Date();
          if (isNaN(endAt.getTime())) {
            document.getElementById('timer-remaining').textContent = '시간 정보 없음';
            return;
          }
          var diff = Math.floor((endAt - now) / 1000);
          if (diff <= 0) {
            document.getElementById('timer-remaining').textContent = '점검 종료';
            return;
          }
          var h = Math.floor(diff / 3600);
          var m = Math.floor((diff % 3600) / 60);
          var s = diff % 60;
          var str = (h > 0 ? h+'시간 ' : '') + (m > 0 ? m+'분 ' : '') + s+'초';
          document.getElementById('timer-remaining').textContent = str;
        }
        updateTimer();
        setInterval(updateTimer, 1000);
        </script>
        <?php endif; ?>
      </div>
    </section>
    <!-- 로그 통계 차트 카드 -->
    <section style="margin:0 0 32px 0;">
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:18px;">
        <span style="background:#005BAC;color:#fff;font-size:0.98rem;font-weight:700;padding:4px 14px;border-radius:8px;">관리자 전용</span>
        <span style="font-size:1.18rem;font-weight:700;color:#005BAC;">시스템 로그 통계</span>
      </div>
      <div style="display:flex;flex-wrap:wrap;gap:40px;justify-content:center;align-items:flex-start;">
        <div style="flex:1 1 340px;min-width:280px;max-width:480px;background:linear-gradient(120deg,#f8f9fa 60%,#e3f0ff 100%);padding:28px 18px 18px 18px;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,0.07);">
          <canvas id="logChart" height="110"></canvas>
        </div>
        <div style="flex:1 1 260px;min-width:200px;max-width:340px;background:linear-gradient(120deg,#f8f9fa 60%,#e3f0ff 100%);padding:28px 18px 18px 18px;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,0.07);">
          <canvas id="typeChart" height="110"></canvas>
        </div>
        <div style="flex:1 1 260px;min-width:200px;max-width:340px;background:linear-gradient(120deg,#f8f9fa 60%,#e3f0ff 100%);padding:28px 18px 18px 18px;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,0.07);">
          <canvas id="userChart" height="110"></canvas>
        </div>
      </div>
    </section>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
      // 1. 최근 7일간 일별 로그 수 (Line)
      const ctx = document.getElementById('logChart').getContext('2d');
      new Chart(ctx, {
        type: 'line',
        data: {
          labels: <?= json_encode($dates) ?>,
          datasets: [{
            label: '일별 로그 수',
            data: <?= json_encode($log_counts_by_date) ?>,
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
      // 2. 유형별 로그 수 (Bar)
      const typeChart = document.getElementById('typeChart').getContext('2d');
      new Chart(typeChart, {
        type: 'bar',
        data: {
          labels: <?= json_encode($type_labels) ?>,
          datasets: [{
            label: '유형별 로그 수',
            data: <?= json_encode($type_counts) ?>,
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
      // 3. 사용자별 로그 수 (Pie)
      const userChart = document.getElementById('userChart').getContext('2d');
      new Chart(userChart, {
        type: 'pie',
        data: {
          labels: <?= json_encode($user_labels) ?>,
          datasets: [{
            label: '사용자별 로그 수',
            data: <?= json_encode($user_counts) ?>,
            backgroundColor: ['#3C8DBC','#FFB300','#4CAF50','#E91E63','#9C27B0'],
          }]
        },
        options: {
          responsive: true,
          plugins: { legend: { display: true } }
        }
      });
    });
    </script>
    <!-- 공지사항 관리 카드(관리자만) -->
    <section style="margin:0 0 32px 0;">
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:18px;">
        <span style="background:#005BAC;color:#fff;font-size:0.98rem;font-weight:700;padding:4px 14px;border-radius:8px;">관리자 전용</span>
        <span style="font-size:1.18rem;font-weight:700;color:#005BAC;">공지사항 관리</span>
      </div>
      <?php if (isset($_SESSION['admin'])): ?>
        <button id="showNoticeListBtn" style="background:#005BAC;color:#fff;padding:8px 22px;border:none;border-radius:8px;font-weight:600;font-size:1.02rem;margin-bottom:18px;">공지사항 목록보기</button>
        <!-- 공지사항 목록 모달/영역 -->
        <div id="noticeListModal" style="display:none;position:fixed;z-index:9999;left:0;top:0;width:100vw;height:100vh;background:rgba(0,0,0,0.18);justify-content:center;align-items:center;">
          <div style="background:#fff;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,0.12);padding:32px 28px;min-width:320px;max-width:520px;position:relative;">
            <button onclick="document.getElementById('noticeListModal').style.display='none'" style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:22px;cursor:pointer;">&times;</button>
            <h3 style="color:#005BAC;font-size:1.15rem;margin-bottom:16px;display:flex;align-items:center;gap:8px;">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm0-4h-2V7h2v8z" fill="#005BAC"/></svg>
              공지사항 전체 목록
            </h3>
            <?php if (count($all_notices) > 0): ?>
              <ul style="padding-left:0;list-style:none;max-height:340px;overflow-y:auto;">
                <?php foreach ($all_notices as $notice): ?>
                  <li style="margin-bottom:18px;padding-bottom:12px;border-bottom:1px solid #e0e0e0;">
                    <div class="notice-view" id="notice-view-<?= $notice['id'] ?>">
                      <div style="font-weight:700;font-size:1.08rem;color:#003366;"> <?= htmlspecialchars($notice['title']) ?> </div>
                      <div style="font-size:0.98rem;color:#444;line-height:1.6;"> <?= nl2br(htmlspecialchars($notice['content'])) ?> </div>
                      <div style="font-size:0.92rem;color:#888;margin-top:2px;">등록일: <?= date('Y-m-d', strtotime($notice['created_at'])) ?></div>
                      <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:4px;">
                        <button type="button" onclick="showEditForm(<?= $notice['id'] ?>)" style="background:#005BAC;color:#fff;padding:6px 16px;border:none;border-radius:8px;font-weight:600;">수정</button>
                        <form method="post" style="display:inline;">
                          <input type="hidden" name="delete_notice_id" value="<?= $notice['id'] ?>">
                          <button type="submit" style="background:#E53935;color:#fff;padding:6px 16px;border:none;border-radius:8px;font-weight:600;">삭제</button>
                        </form>
                      </div>
                    </div>
                    <form method="post" id="notice-edit-form-<?= $notice['id'] ?>" style="display:none;margin:0;">
                      <input type="hidden" name="edit_notice_id" value="<?= $notice['id'] ?>">
                      <input type="text" name="edit_notice_title" value="<?= htmlspecialchars($notice['title']) ?>" required style="width:100%;padding:6px 8px;margin-bottom:4px;border-radius:6px;border:1.5px solid #b3c6e0;font-size:1rem;">
                      <textarea name="edit_notice_content" required style="width:100%;padding:6px 8px;border-radius:6px;border:1.5px solid #b3c6e0;font-size:1rem;min-height:36px;"><?= htmlspecialchars($notice['content']) ?></textarea>
                      <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:4px;">
                        <button type="submit" style="background:#005BAC;color:#fff;padding:6px 16px;border:none;border-radius:8px;font-weight:600;">저장</button>
                        <button type="button" onclick="hideEditForm(<?= $notice['id'] ?>)" style="background:#bbb;color:#fff;padding:6px 16px;border:none;border-radius:8px;font-weight:600;">취소</button>
                      </div>
                    </form>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?>
              <div style="color:#888;text-align:center;">등록된 공지사항이 없습니다.</div>
            <?php endif; ?>
          </div>
        </div>
        <script>
          document.getElementById('showNoticeListBtn').onclick = function() {
            document.getElementById('noticeListModal').style.display = 'flex';
          };
          function showEditForm(id) {
            document.getElementById('notice-view-' + id).style.display = 'none';
            document.getElementById('notice-edit-form-' + id).style.display = 'block';
          }
          function hideEditForm(id) {
            document.getElementById('notice-edit-form-' + id).style.display = 'none';
            document.getElementById('notice-view-' + id).style.display = 'block';
          }
        </script>
        <!-- 공지사항 등록 폼 -->
        <form method="post" style="background:#e3f2fd;border-radius:12px;padding:18px 22px 14px 22px;max-width:420px;margin-bottom:32px;box-shadow:0 2px 8px rgba(0,0,0,0.04);">
          <h4 style="margin:0 0 10px 0;font-size:1.08rem;color:#005BAC;display:flex;align-items:center;gap:6px;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm0-4h-2V7h2v8z" fill="#005BAC"/></svg>
            새 공지 등록
          </h4>
          <input type="text" name="notice_title" placeholder="공지 제목" required style="width:100%;padding:8px 10px;margin-bottom:8px;border-radius:6px;border:1.5px solid #b3c6e0;font-size:1rem;">
          <textarea name="notice_content" placeholder="공지 내용" required style="width:100%;padding:8px 10px;border-radius:6px;border:1.5px solid #b3c6e0;font-size:1rem;min-height:48px;margin-bottom:8px;"></textarea>
          <button type="submit" name="add_notice" style="background:#005BAC;color:#fff;padding:8px 22px;border:none;border-radius:8px;font-weight:600;font-size:1.02rem;">공지 등록</button>
        </form>
      <?php endif; ?>
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
      fetch('get_security_logs.php')
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
    </script>
  </main>
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
