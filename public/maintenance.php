<?php
session_start();
date_default_timezone_set('Asia/Seoul');
require_once '../src/db/db.php';
$maintenance = $pdo->query("SELECT * FROM maintenance WHERE is_active=1 ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
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
  <meta name="viewport" content="width=1280">
  <link href="https://fonts.googleapis.com/css?family=Pretendard:400,600,700&display=swap" rel="stylesheet">
  <style>
    body { background: #f9f9f9; color: #212121; font-family: 'Pretendard', 'Noto Sans KR', Arial, sans-serif; margin: 0; padding: 0; min-height: 100vh; display: flex; flex-direction: column; }
    .container { max-width: 480px; margin: 0 auto; padding: 0 20px; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 100vh; }
    .maintenance-icon { width: 80px; height: 80px; margin-bottom: 32px; display: flex; align-items: center; justify-content: center; background: #003366; border-radius: 50%; }
    .maintenance-icon svg { width: 48px; height: 48px; color: #fff; }
    h1 { font-size: 2.2rem; font-weight: 700; color: #003366; margin-bottom: 18px; margin-top: 0; letter-spacing: -0.01em; }
    .desc { font-size: 1.1rem; color: #4a4a4a; margin-bottom: 32px; text-align: center; line-height: 1.6; }
    .info-box { background: #fffbe6; color: #f5a623; border-radius: 10px; padding: 16px 24px; font-size: 1rem; margin-bottom: 32px; border: 1.5px solid #ffe58f; text-align: center; }
    .contact { color: #006699; font-weight: 600; margin-top: 12px; font-size: 1rem; }
    @media (max-width: 600px) { .container { max-width: 98vw; padding: 0 4vw; } h1 { font-size: 1.4rem; } }
  </style>
</head>
<body>
<?php if (!isset($_SESSION['admin'])): ?>
<script>
function checkMaintenanceStatus() {
  fetch('check_maintenance_status.php')
    .then(response => response.json())
    .then(data => {
      if (data.is_active === 0) {
        window.location.href = 'maintenance_end.php';
      } else {
        // 점검이 계속 중이면 5초 후 새로고침
        setTimeout(function() { location.reload(); }, 5000);
      }
    })
    .catch(() => {
      // 에러 시에도 5초 후 재시도
      setTimeout(checkMaintenanceStatus, 5000);
    });
}
checkMaintenanceStatus();
</script>
<?php endif; ?>
  <div class="container">
    <div class="maintenance-icon">
      <svg fill="none" viewBox="0 0 48 48"><circle cx="24" cy="24" r="24" fill="#003366"/><path d="M24 14v10" stroke="#fff" stroke-width="3" stroke-linecap="round"/><circle cx="24" cy="32" r="2.5" fill="#f5a623"/></svg>
    </div>
    <h1 id="admin-login-trigger" style="cursor:pointer;">시스템 점검중입니다</h1>
    <div class="desc">
      현재 PLC Rotator System은<br>
      더 나은 서비스 제공을 위해<br>
      <b>시스템 점검 및 업그레이드</b>를 진행하고 있습니다.<br><br>
      점검 시간 동안 모든 서비스 이용이 일시 중단됩니다.<br>
      빠른 시간 내에 정상화될 수 있도록 최선을 다하겠습니다.
    </div>
    <div class="info-box">
      점검 기간: <b><?= htmlspecialchars($maintenance['start_at']) ?></b> ~ <b><?= htmlspecialchars($maintenance['end_at']) ?></b><br>
      등록자: <b><?= htmlspecialchars($display_name) ?></b><br>
      (예정 시간은 상황에 따라 변동될 수 있습니다)
    </div>
    <?php if ($end_at): ?>
    <div id="maintenance-timer" style="font-size:1.12rem;color:#E53935;font-weight:600;margin-bottom:18px;">
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
    <div class="contact">
      문의: 관리자에게 연락 바랍니다.
    </div>
    <!-- 관리자 로그인 모달 -->
    <div id="adminLoginModal" style="display:none;position:fixed;z-index:9999;left:0;top:0;width:100vw;height:100vh;background:rgba(0,0,0,0.25);align-items:center;justify-content:center;">
      <div style="background:#fff;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,0.18);padding:32px 28px 24px 28px;min-width:320px;max-width:98vw;position:relative;">
        <button id="adminLoginClose" style="position:absolute;right:14px;top:10px;font-size:1.5rem;background:none;border:none;color:#888;cursor:pointer;">×</button>
        <h3 style="margin-bottom:18px;color:#005BAC;font-size:1.15rem;">관리자 로그인</h3>
        <form method="post" action="login.php">
          <input type="hidden" name="admin_maintenance_login" value="1">
          <input type="text" name="username" placeholder="아이디" required style="width:100%;padding:10px;margin-bottom:10px;border-radius:6px;border:1.5px solid #cfd8dc;">
          <input type="password" name="password" placeholder="비밀번호" required style="width:100%;padding:10px;margin-bottom:10px;border-radius:6px;border:1.5px solid #cfd8dc;">
          <button type="submit" style="width:100%;padding:10px;background:#005BAC;color:#fff;border:none;border-radius:6px;font-weight:600;font-size:1.08rem;">로그인</button>
        </form>
      </div>
    </div>
    <script>
      document.getElementById('admin-login-trigger').onclick = function() {
        document.getElementById('adminLoginModal').style.display = 'flex';
      };
      document.getElementById('adminLoginClose').onclick = function() {
        document.getElementById('adminLoginModal').style.display = 'none';
      };
      document.getElementById('adminLoginModal').onclick = function(e) {
        if (e.target === this) this.style.display = 'none';
      };
    </script>
  </div>
</body>
</html>