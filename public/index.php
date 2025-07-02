<?php session_start();
if (!isset($_SESSION['admin']) && !isset($_SESSION['guest'])) {
  header("Location: login.php");
  exit();
}
// echo "로그인 성공: ".($_SESSION['admin'] ?? $_SESSION['guest']);

// require_once '../src/db/maintenance_check.php';
// maintenanceRedirectIfNeeded('/public/index.php');

// 관리자만 로그 통계 집계
if (isset($_SESSION['admin'])) {
  // 1. 최근 7일간 일별 로그 수
  require_once '../src/db/db.php';
  $log_counts_by_date = [];
  $dates = [];
  for ($i = 6; $i >= 0; $i--) {
      $date = date('Y-m-d', strtotime("-$i days"));
      $dates[] = $date;
      $stmt = $pdo->prepare("SELECT COUNT(*) FROM logs WHERE DATE(created_at) = :date");
      $stmt->execute(['date' => $date]);
      $log_counts_by_date[] = (int)$stmt->fetchColumn();
  }
  // 2. 유형별 로그 수
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
  // 3. 사용자별 로그 수(상위 5명)
  $stmt = $pdo->query("SELECT username, COUNT(*) as cnt FROM logs GROUP BY username ORDER BY cnt DESC LIMIT 5");
  $user_rows = $stmt->fetchAll();
  $user_labels = array_column($user_rows, 'username');
  $user_counts = array_column($user_rows, 'cnt');
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
  <header class="header" role="banner">
    <a href="index.php" class="logo" aria-label="홈으로">
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
        <?php endif; ?>
        <li><a href="logout.php"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M16 13v-2H7V8l-5 4 5 4v-3h9zm3-10H5c-1.1 0-2 .9-2 2v6h2V5h14v14H5v-6H3v6c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z" fill="#fff"/></svg>로그아웃</a></li>
      </ul>
    </nav>
  </header>
  <main id="main-content" class="main-content" tabindex="-1">
    <h2><svg width="28" height="28" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8v-10h-8v10zm0-18v6h8V3h-8z" fill="#005BAC"/></svg>시스템 개요</h2>
    <p>환영합니다, <strong><?= htmlspecialchars($_SESSION['admin'] ?? $_SESSION['guest']) ?></strong>님!</p>
    <p>좌측 메뉴 또는 상단 메뉴를 통해 제어 기능을 이용하거나 시스템을 확인할 수 있습니다.</p>
    <ul style="margin-top:24px; margin-bottom:0; padding-left:20px; color:#555;">
      <li>회전기 상태 및 제어</li>
      <li>고장 접수 및 이력 확인</li>
      <li>시스템 로그 모니터링</li>
      <li>사용자 관리(관리자만)</li>
    </ul>
    <?php if (isset($_SESSION['admin'])): ?>
    <?php
    // 점검 상태 플래그만 사용
    $row = $pdo->query("SELECT is_active FROM maintenance ORDER BY id DESC LIMIT 1")->fetch();
    $is_maintenance = $row && $row['is_active'] == 1;
    if (isset($_POST['set_maintenance'])) {
        $pdo->exec("UPDATE maintenance SET is_active=1");
        echo "<script>alert('점검이 시작되었습니다.');location.href='index.php';</script>"; exit();
    }
    if (isset($_POST['unset_maintenance'])) {
        $pdo->exec("UPDATE maintenance SET is_active=0");
        echo "<script>alert('점검이 종료되었습니다.');location.href='index.php';</script>"; exit();
    }
    ?>
    <div style="margin:36px 0 0 0;max-width:900px;background:#f8f9fa;padding:28px 24px 18px 24px;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,0.06);display:flex;align-items:center;gap:40px;">
      <h3 style="font-size:1.15rem;color:#E53935;display:flex;align-items:center;gap:8px;margin:0;">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="12" fill="#E53935"/><path d="M12 7v5" stroke="#fff" stroke-width="2.2" stroke-linecap="round"/><circle cx="12" cy="16" r="1.3" fill="#fff"/></svg>
        서버 점검 관리</h3>
      <div style="font-weight:600;<?= $is_maintenance ? 'color:#c0392b;' : 'color:#43e97b;' ?>;margin:0 12px 0 0;">
        현재 상태: <?= $is_maintenance ? '점검중' : '정상' ?>
      </div>
      <form method="post" style="margin:0;display:flex;gap:12px;">
        <?php if (!$is_maintenance): ?>
          <button type="submit" name="set_maintenance" style="background:#E53935;color:#fff;padding:10px 24px;border:none;border-radius:8px;font-weight:700;font-size:1.08rem;">점검 시작</button>
        <?php else: ?>
          <button type="submit" name="unset_maintenance" style="background:#43e97b;color:#fff;padding:10px 24px;border:none;border-radius:8px;font-weight:700;font-size:1.08rem;">점검 종료</button>
        <?php endif; ?>
      </form>
    </div>
    <!-- 관리자용 로그 통계 차트 -->
    <div style="margin:40px 0 0 0;">
      <h3 style="font-size:1.3rem;color:#005BAC;margin-bottom:18px;display:flex;align-items:center;gap:8px;">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8v-10h-8v10zm0-18v6h8V3h-8z" fill="#005BAC"/></svg>
        시스템 로그 통계</h3>
      <div style="display:flex;flex-wrap:wrap;gap:40px;justify-content:center;align-items:flex-start;">
        <div style="flex:1 1 340px;min-width:280px;max-width:480px;background:#f8f9fa;padding:28px 18px 18px 18px;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,0.06);">
          <canvas id="logChart" height="110"></canvas>
        </div>
        <div style="flex:1 1 260px;min-width:200px;max-width:340px;background:#f8f9fa;padding:28px 18px 18px 18px;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,0.06);">
          <canvas id="typeChart" height="110"></canvas>
        </div>
        <div style="flex:1 1 260px;min-width:200px;max-width:340px;background:#f8f9fa;padding:28px 18px 18px 18px;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,0.06);">
          <canvas id="userChart" height="110"></canvas>
        </div>
      </div>
    </div>
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
    <?php endif; ?>
  </main>
  <footer class="footer" role="contentinfo">
    <div>가천대학교 CPS |  <a href="#" style="color:#FFB300; text-decoration:underline;">이용약관</a> | <a href="#" style="color:#FFB300; text-decoration:underline;">개인정보처리방침</a> | 고객센터: 1234-5678</div>
    <div style="margin-top:8px;">© 2025 PLC Control</div>
  </footer>
</body>
</html>
