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
  <title>PLC 관리자 대시보드</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="assets/css/main.css">
  <link href="https://fonts.googleapis.com/css?family=Noto+Sans+KR:400,700&display=swap" rel="stylesheet">
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
      <li><a href="logout.php">로그아웃</a></li>
    </ul>
  </nav>
</header>
<main class="main-content" style="max-width:1200px;margin:0 auto;">
  <!-- 상단 상태/알림/점검 배지 -->
  <?php if (isset(
    $is_maintenance) && $is_maintenance && !isset($_SESSION['admin'])): ?>
    <div class="badge badge-warning" style="margin-bottom:18px;">🔧 시스템 점검중</div>
  <?php endif; ?>
  <?php if (isset($_SESSION['admin'])): ?>
  <h2 class="card-title" style="margin-bottom:18px;">🚀 관리자 대시보드</h2>
  <!-- 상단 통계 카드 -->
  <section class="stats-carousel horizontal-scroll">
    <div class="stats-card stats-card-yellow">
      <div class="stats-title">🛠️ 오늘 고장</div>
      <div class="stats-value"><?= isset($today_faults) ? $today_faults : '0' ?>건</div>
    </div>
    <div class="stats-card stats-card-red">
      <div class="stats-title">⏳ 미처리 고장</div>
      <div class="stats-value"><?= isset($pending_faults) ? $pending_faults : '0' ?>건</div>
    </div>
    <div class="stats-card stats-card-green">
      <div class="stats-title">🔧 점검상태</div>
      <div class="stats-value"><?= isset($is_maintenance) ? ($is_maintenance ? '점검중' : '정상') : '?' ?></div>
    </div>
    <div class="stats-card stats-card-blue">
      <div class="stats-title">❗ 오늘 보안 이벤트</div>
      <div class="stats-value"><?= isset($today_security_events) ? $today_security_events : '0' ?>건</div>
    </div>
    <div class="stats-card stats-card-blue">
      <div class="stats-title">🔒 취약점 제보</div>
      <div class="stats-value"><?= isset($total_vul_reports) && is_numeric($total_vul_reports) ? $total_vul_reports : '0' ?>건</div>
    </div>
  </section>
  <?php endif; ?>
  <!-- 공지사항 카드(최근 1~2개만) + 관리 버튼 -->
  <section style="margin:32px 0 24px 0;display:flex;gap:32px;align-items:flex-start;flex-wrap:wrap;">
    <div style="flex:2;min-width:320px;">
      <h3 style="font-size:1.15rem;font-weight:700;color:#3366cc;margin-bottom:10px;">📢 최근 공지사항</h3>
      <?php if (count($notices) > 0): ?>
        <?php foreach ($notices as $notice): ?>
          <div class="notice-card" style="margin-bottom:14px;">
            <div style="font-size:1.08rem;font-weight:700;color:#3366cc;"> <?= htmlspecialchars($notice['title']) ?> </div>
            <div style="font-size:0.97rem;color:#444;line-height:1.5;"> <?= nl2br(htmlspecialchars($notice['content'])) ?> </div>
            <div style="font-size:0.92rem;color:#888;margin-top:2px;">등록일: <?= date('Y-m-d', strtotime($notice['created_at'])) ?></div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div style="color:#888;">등록된 공지사항이 없습니다.</div>
      <?php endif; ?>
    </div>
    <?php if (isset($_SESSION['admin'])): ?>
    <div style="flex:1;min-width:180px;text-align:right;">
      <button id="noticeManageBtn" class="btn btn-primary" style="padding:10px 24px;font-size:1.05rem;">공지사항 관리</button>
    </div>
    <?php endif; ?>
  </section>
  <?php if (isset($_SESSION['admin'])): ?>
  <!-- 운영/시스템/점검/보안 카드 메뉴 -->
  <section class="horizontal-scroll" style="gap:32px;justify-content:center;margin:36px 0 24px 0;">
    <div class="dashboard-feature-card" id="opsMenuCard">
      <div class="feature-title">👤 운영/시스템</div>
      <div class="feature-desc">계정/파일/시스템 관리</div>
    </div>
    <div class="dashboard-feature-card" onclick="location.href='admin/fault_maintenance_history.php'">
      <div class="feature-title">📝 고장/점검</div>
      <div class="feature-desc">이력/통계/파일</div>
    </div>
    <div class="dashboard-feature-card" onclick="location.href='admin/security_center.php'">
      <div class="feature-title">🛡️ 보안 통합</div>
      <div class="feature-desc">보안 이벤트/취약점/테스트</div>
    </div>
  </section>
  <!-- 운영/시스템 메뉴 모달 -->
  <div id="opsMenuModal" class="ops-menu-modal">
    <div class="ops-menu-content">
      <button class="ops-menu-close" onclick="closeOpsMenuModal()">&times;</button>
      <button onclick="location.href='admin/user_management.php'">계정 관리</button>
      <button onclick="location.href='admin/file_management.php'">파일 관리</button>
      <button onclick="location.href='admin/system_status.php'">시스템 모니터링</button>
    </div>
  </div>
  <!-- 차트/통계(슬라이드/탭 등으로 정돈, 필요시) -->
  <section style="margin:0 0 32px 0;">
    <div style="display:flex;flex-wrap:wrap;gap:40px;justify-content:center;align-items:flex-start;">
      <div style="flex:1 1 340px;min-width:280px;max-width:480px;background:#f8f9fa;padding:28px 18px 18px 18px;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,0.07);">
        <canvas id="logChart" height="110"></canvas>
        <div id="logChartEmpty" style="display:none;text-align:center;color:#aaa;padding:24px 0;">일별 로그 데이터가 없습니다.</div>
      </div>
      <div style="flex:1 1 260px;min-width:200px;max-width:340px;background:#f8f9fa;padding:28px 18px 18px 18px;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,0.07);">
        <canvas id="typeChart" height="110"></canvas>
        <div id="typeChartEmpty" style="display:none;text-align:center;color:#aaa;padding:24px 0;">유형별 로그 데이터가 없습니다.</div>
      </div>
      <div style="flex:1 1 260px;min-width:200px;max-width:340px;background:#f8f9fa;padding:28px 18px 18px 18px;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,0.07);">
        <canvas id="userChart" height="110"></canvas>
        <div id="userChartEmpty" style="display:none;text-align:center;color:#aaa;padding:24px 0;">사용자별 로그 데이터가 없습니다.</div>
      </div>
    </div>
  </section>
  <?php endif; ?>
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
<script>
// 공지사항 관리 모달 열기/닫기
const noticeBtn = document.getElementById('noticeManageBtn');
const noticeModal = document.getElementById('noticeModal');
function openNoticeModal() { noticeModal.classList.add('active'); }
function closeNoticeModal() { noticeModal.classList.remove('active'); }
noticeBtn.onclick = openNoticeModal;
noticeModal.onclick = function(e) { if(e.target===noticeModal) closeNoticeModal(); };
document.addEventListener('keydown', function(e){ if(e.key==='Escape' && noticeModal.classList.contains('active')) closeNoticeModal(); });

// 운영/시스템 카드 클릭 시 모달 열기
const opsMenuCard = document.getElementById('opsMenuCard');
const opsMenuModal = document.getElementById('opsMenuModal');
function openOpsMenuModal() { opsMenuModal.classList.add('active'); }
function closeOpsMenuModal() { opsMenuModal.classList.remove('active'); }
opsMenuCard.onclick = openOpsMenuModal;
opsMenuModal.onclick = function(e) { if(e.target===opsMenuModal) closeOpsMenuModal(); };
document.addEventListener('keydown', function(e){ if(e.key==='Escape' && opsMenuModal.classList.contains('active')) closeOpsMenuModal(); });
</script>
</body>
</html>
