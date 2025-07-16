<?php
session_start();
date_default_timezone_set('Asia/Seoul');
require_once '../src/db/db.php';
// 점검 상태 조회 시 자동 종료 처리
$maintenance = $pdo->query("SELECT * FROM maintenance WHERE is_active=1 ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if ($maintenance && strtotime($maintenance['end_at']) < time()) {
    $pdo->exec("UPDATE maintenance SET is_active=0 WHERE id=" . (int)$maintenance['id']);
    $maintenance['is_active'] = 0;
}
$end_at = $maintenance ? $maintenance['end_at'] : null;

// 등록자 이름 가져오기
$display_name = '';
if ($maintenance && !empty($maintenance['created_by'])) {
    $created_by = $maintenance['created_by'];
    // users 테이블에서 이름 조회
    $stmt = $pdo->prepare("SELECT name FROM users WHERE username = ?");
    $stmt->execute([$created_by]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $display_name = $row && !empty($row['name']) ? $row['name'] : $created_by;
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>점검중 | PLC Rotator System</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://fonts.googleapis.com/css?family=Pretendard:400,600,700&display=swap" rel="stylesheet">
  <style>
    body {
      background: #f7fafd;
      color: #222;
      font-family: 'Pretendard', 'Noto Sans KR', Arial, sans-serif;
      margin: 0; padding: 0;
      min-height: 100vh;
      display: flex; flex-direction: column;
      justify-content: center;
      align-items: center;
    }
    .brand {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-top: 48px;
      margin-bottom: 32px;
      user-select: none;
    }
    .brand-logo {
      width: 44px; height: 44px;
      background: #003366;
      border-radius: 12px;
      display: flex; align-items: center; justify-content: center;
    }
    .brand-logo svg {
      width: 28px; height: 28px; color: #fff;
    }
    .brand-name {
      font-size: 1.45rem;
      font-weight: 700;
      color: #003366;
      letter-spacing: -0.01em;
    }
    .main-card {
      background: #fff;
      border-radius: 18px;
      box-shadow: 0 2px 12px 0 rgba(0,0,0,0.04);
      padding: 38px 32px 32px 32px;
      max-width: 540px;
      width: 100%;
      display: flex;
      flex-direction: column;
      align-items: center;
      margin-bottom: 24px;
    }
    .maintenance-icon {
      width: 70px; height: 70px;
      background: #e6f0fa;
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      margin-bottom: 18px;
    }
    .maintenance-icon svg {
      width: 38px; height: 38px; color: #003366;
    }
    .main-title {
      font-size: 1.5rem;
      font-weight: 700;
      color: #003366;
      margin-bottom: 12px;
      margin-top: 0;
      text-align: center;
    }
    .main-desc {
      font-size: 1.08rem;
      color: #444;
      margin-bottom: 18px;
      text-align: center;
      line-height: 1.7;
    }
    .info-cards {
      display: flex;
      flex-direction: column;
      gap: 12px;
      width: 100%;
      margin-bottom: 10px;
    }
    .info-card {
      background: #f5f8ff;
      border-radius: 12px;
      padding: 16px 20px;
      display: flex;
      align-items: center;
      gap: 12px;
      font-size: 1rem;
      color: #003366;
      border: 1.2px solid #e3eaf5;
    }
    .info-card svg {
      width: 22px; height: 22px; color: #2d7be5;
      flex-shrink: 0;
    }
    .notice {
      font-size: 0.98rem;
      color: #888;
      text-align: center;
      margin-top: 18px;
      margin-bottom: 0;
    }
    .timer {
      font-size: 1.12rem;
      color: #E53935;
      font-weight: 600;
      margin-bottom: 18px;
      text-align: center;
    }
    @media (max-width: 600px) {
      .main-card { max-width: 98vw; padding: 28px 4vw 24px 4vw; }
      .brand { margin-top: 24px; margin-bottom: 18px; }
      .brand-logo { width: 36px; height: 36px; }
      .brand-name { font-size: 1.1rem; }
      .main-title { font-size: 1.1rem; }
      .main-desc { font-size: 0.98rem; }
      .info-card { font-size: 0.95rem; padding: 12px 10px; }
    }
    @media (max-width: 700px) {
      .main-card { max-width: 98vw; }
    }
  </style>
</head>
<body>
  <div class="brand">
    <span class="brand-name">PLC Rotator System</span>
  </div>
  <div class="main-card">
    <div class="maintenance-icon">
      <svg fill="none" viewBox="0 0 38 38"><circle cx="19" cy="19" r="19" fill="#003366"/><path d="M19 11v9" stroke="#fff" stroke-width="2.5" stroke-linecap="round"/><circle cx="19" cy="26" r="2" fill="#f5a623"/></svg>
    </div>
    <div class="main-title admin-login-trigger" style="cursor:pointer;">시스템 점검 안내</div>
    <div class="main-desc">
      더 나은 서비스 제공을 위해<br>
      <b>시스템 점검 및 업그레이드</b>를 진행하고 있습니다.<br>
      점검 시간 동안 모든 서비스 이용이 일시 중단됩니다.<br>
      빠른 시간 내에 정상화될 수 있도록 최선을 다하겠습니다.
    </div>
    <div class="info-cards">
      <div class="info-card">
        <svg fill="none" viewBox="0 0 24 24"><path d="M12 8v4l2.5 2.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/></svg>
        <span><b>점검 기간</b>: <?= htmlspecialchars($maintenance['start_at']) ?> ~ <?= htmlspecialchars($maintenance['end_at']) ?></span>
      </div>
      <div class="info-card">
        <svg fill="none" viewBox="0 0 24 24"><path d="M21 10.5V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h7.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><circle cx="18" cy="18" r="3" stroke="currentColor" stroke-width="2"/><path d="M18 16.5v1.5l1 1" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        <span><b>등록자</b>: <?= htmlspecialchars($display_name) ?></span>
      </div>
    </div>
    <?php if ($end_at): ?>
    <div class="timer">
      남은 점검 시간: <span id="timer-remaining"></span>
    </div>
    <?php endif; ?>
    <div class="notice">
      (예정 시간은 상황에 따라 변동될 수 있습니다)<br>
      문의: 관리자에게 연락 바랍니다.
    </div>
  </div>
<script>
function checkMaintenanceStatus() {
  fetch('check_maintenance_status.php')
    .then(response => response.json())
    .then(data => {
      if (data.is_active === 0) {
        window.location.href = 'maintenance_end.php';
      } else {
        setTimeout(function() { location.reload(); }, 5000);
      }
    })
    .catch(() => {
      setTimeout(checkMaintenanceStatus, 5000);
    });
}
checkMaintenanceStatus();
</script>
<?php if ($end_at): ?>
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
<script>
document.addEventListener('DOMContentLoaded', function() {
  const adminLoginTrigger = document.querySelector('.admin-login-trigger');
  if (adminLoginTrigger) {
    adminLoginTrigger.onclick = function() {
      window.location.href = 'login.php';
    };
    adminLoginTrigger.title = '관리자 로그인(점검 종료)';
  }
});
</script>
</body>
</html>