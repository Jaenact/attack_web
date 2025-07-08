<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
if (!isset($_SESSION['admin'])) {
  header("Location: login.php");
  exit();
}
require_once '../src/db/db.php';
// require_once '../src/db/maintenance_check.php';
// maintenanceRedirectIfNeeded();
// 검색/필터 파라미터
$search_ip = $_GET['ip'] ?? '';
$search_user = $_GET['user'] ?? '';
$search_keyword = $_GET['keyword'] ?? '';
$search_type = $_GET['type'] ?? '';
$search_start = $_GET['start'] ?? '';
$search_end = $_GET['end'] ?? '';
$where = [];
$params = [];
if ($search_ip) {
    $where[] = 'ip_address = :ip';
    $params['ip'] = $search_ip;
}
if ($search_user) {
    $where[] = 'username = :user';
    $params['user'] = $search_user;
}
if ($search_keyword) {
    $where[] = 'log_message LIKE :keyword';
    $params['keyword'] = "%$search_keyword%";
}
if ($search_start) {
    $where[] = 'created_at >= :start';
    $params['start'] = $search_start . ' 00:00:00';
}
if ($search_end) {
    $where[] = 'created_at <= :end';
    $params['end'] = $search_end . ' 23:59:59';
}
$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
$group_by_ip = isset($_GET['group_ip']) && $_GET['group_ip'] === '1';
$logs_per_page = 30;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $logs_per_page;
$total_logs = $pdo->query("SELECT COUNT(*) FROM logs")->fetchColumn();
$total_pages = ceil($total_logs / $logs_per_page);
try {
    if ($group_by_ip) {
        $sql = "SELECT ip_address, COUNT(*) as cnt, MIN(created_at) as first_time, MAX(created_at) as last_time FROM logs $where_sql GROUP BY ip_address ORDER BY last_time DESC LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue(":$k", $v);
        $stmt->bindValue(':limit', $logs_per_page, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $ip_groups = $stmt->fetchAll();
    } else {
        $sql = "SELECT * FROM logs $where_sql ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue(":$k", $v);
        $stmt->bindValue(':limit', $logs_per_page, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $logs = $stmt->fetchAll();
    }
} catch (Exception $e) {
    echo '<div style="color:red;font-weight:bold;">[쿼리 오류] ' . htmlspecialchars($e->getMessage()) . '</div>';
    echo '<pre>SQL: ' . htmlspecialchars($sql) . "\nPARAMS: " . print_r($params, true) . '</pre>';
}
if (!isset($logs)) {
    echo '<div style="color:red;font-weight:bold;">[오류] $logs 변수가 정의되지 않았습니다.</div>';
    $logs = [];
}
// 로그 목록을 불러온 후, 사용자 이름 매핑
$name_map = [];
if (isset($logs) && is_array($logs) && count($logs) > 0) {
    $usernames = array_unique(array_column($logs, 'username'));
    $usernames = array_filter($usernames, function($v) { return $v !== null && $v !== ''; }); // 빈값 제거
    if (count($usernames) > 0) {
        $in = implode(',', array_fill(0, count($usernames), '?'));
        $sql = "SELECT username, name FROM users WHERE username IN ($in)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_values($usernames));
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $name_map[$row['username']] = $row['name'];
        }
    }
}
function logTypeInfo($message) {
    $types = [
        '로그인 성공' => ['success', '✅'],
        '로그인 실패' => ['error', '❌'],
        '로그아웃' => ['info', '🔓'],
        '고장 접수' => ['warning', '🆘'],
        '고장 수정' => ['warning', '✏️'],
        '고장 삭제' => ['danger', '❌'],
        '장비 제어' => ['primary', '⚙️'],
        '공격감지' => ['danger', '🚨'],
        'PHPIDS' => ['danger', '🚨'],
    ];
    foreach ($types as $k => $v) {
        if (strpos($message, $k) !== false) return $v;
    }
    return ['default', '📝'];
}
function maskIP($ip) {
    if (empty($ip) || $ip === 'Unknown') return 'N/A';
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $parts = explode('.', $ip);
        if (count($parts) === 4) return $parts[0] . '.' . $parts[1] . '.xxx.xxx';
    }
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $parts = explode(':', $ip);
        if (count($parts) >= 2) return $parts[0] . ':' . $parts[1] . ':xxx:xxx';
    }
    return $ip;
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>시스템 로그</title>
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
    .main-content { max-width: 1100px; margin: 40px auto 0 auto; background: #fff; border-radius: 8px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); padding: 40px 32px; flex: 1 0 auto; }
    .main-content h2 { font-size: 1.7rem; font-weight: 700; color: #005BAC; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
    .footer { background: #222; color: #fff; text-align: center; padding: 24px 0 16px 0; font-size: 0.95rem; margin-top: 48px; flex-shrink: 0; }
    /* 로그 카드 스타일 */
    .log-list { margin: 0; padding: 0; }
    .log-card {
      background: #f8f9fa;
      border-radius: 14px;
      box-shadow: 0 2px 8px rgba(60,139,188,0.06);
      margin-bottom: 18px;
      overflow: hidden;
      display: flex;
      align-items: center;
      padding: 18px 28px;
      gap: 24px;
      transition: box-shadow 0.18s;
      cursor: pointer;
      border: 2px solid transparent;
    }
    .log-card:hover {
      box-shadow: 0 4px 16px rgba(60,139,188,0.13);
      border: 2px solid #3C8DBC22;
      background: #eaf3fb;
    }
    .log-badge {
      display: inline-block;
      min-width: 80px;
      padding: 6px 18px;
      border-radius: 16px;
      font-size: 1.08rem;
      font-weight: 700;
      color: #fff;
      text-align: center;
      margin-right: 0;
    }
    .log-badge.success { background: #43e97b; }
    .log-badge.error { background: #e74c3c; }
    .log-badge.info { background: #3C8DBC; }
    .log-badge.warning { background: #FF9800; }
    .log-badge.danger { background: #c0392b; }
    .log-badge.primary { background: #005BAC; }
    .log-badge.default { background: #888; }
    .log-meta { flex: 1 1 0; display: flex; flex-direction: column; gap: 4px; }
    .log-meta .log-user { font-weight: 600; color: #005BAC; font-size: 1.08rem; }
    .log-meta .log-time { color: #888; font-size: 0.98rem; }
    .log-meta .log-ip { color: #1976D2; font-size: 1.08rem; font-family: 'Consolas', 'monospace'; font-weight: 600; }
    .log-meta .log-simple { font-size: 1.13rem; font-weight: 700; color: #333; }
    @media (max-width: 700px) {
      .main-content { padding: 18px 2vw; }
      .log-card { flex-direction: column; gap: 8px; padding: 12px 8px; }
      .log-badge { min-width: 60px; font-size: 0.98rem; padding: 5px 10px; }
    }
    /* 모달 */
    .modal-bg {
      display: none;
      position: fixed;
      z-index: 9999;
      left: 0; top: 0; width: 100vw; height: 100vh;
      background: rgba(0,0,0,0.25);
      align-items: center;
      justify-content: center;
    }
    .modal-bg.active { display: flex; }
    .modal-box {
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 8px 32px rgba(60,139,188,0.18);
      padding: 36px 32px 28px 32px;
      min-width: 320px;
      max-width: 98vw;
      min-height: 180px;
      max-height: 90vh;
      overflow-y: auto;
      position: relative;
      animation: modalShow 0.18s;
    }
    @keyframes modalShow {
      from { opacity: 0; transform: translateY(40px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .modal-close {
      position: absolute; right: 18px; top: 18px; font-size: 1.7rem; color: #888; background: none; border: none; cursor: pointer; }
    .modal-title { font-size: 1.25rem; font-weight: 700; color: #005BAC; margin-bottom: 18px; }
    .modal-content-row { margin-bottom: 12px; font-size: 1.08rem; }
    .modal-content-row strong { display: inline-block; min-width: 80px; color: #1976D2; }
    .filter-form {
      display: flex;
      gap: 14px;
      align-items: center;
      background: #f8f9fa;
      padding: 12px 16px;
      border-radius: 8px;
      box-shadow: 0 1px 4px rgba(0,0,0,0.03);
      flex-wrap: wrap;
      min-width: 0;
      margin-bottom: 18px;
    }
    .filter-form input[type="text"],
    .filter-form input[type="date"] {
      min-width: 120px;
      max-width: 200px;
      border-radius: 6px;
      border: 1.5px solid #cfd8dc;
      padding: 8px 12px;
      font-size: 1rem;
      background: #fff;
      transition: border 0.2s;
    }
    .filter-form input[type="text"]:focus,
    .filter-form input[type="date"]:focus {
      border: 1.5px solid #3C8DBC;
      outline: none;
    }
    .filter-form label {
      font-weight: 500;
      color: #005BAC;
      margin-right: 4px;
    }
    .filter-form button {
      min-width: 90px;
      max-width: 120px;
      flex: 0 0 auto;
      background: #3C8DBC;
      color: #fff;
      border: none;
      border-radius: 6px;
      font-weight: 500;
      padding: 7px 18px;
      transition: background 0.2s;
      cursor: pointer;
    }
    .filter-form button:hover {
      background: #005BAC;
    }
    .filter-form a {
      color: #888;
      text-decoration: underline;
      margin-left: 10px;
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
        <li><a href="control.php"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 2a10 10 0 100 20 10 10 0 000-20zm1 17.93V20h-2v-.07A8.001 8.001 0 014.07 13H4v-2h.07A8.001 8.001 0 0111 4.07V4h2v.07A8.001 8.001 0 0119.93 11H20v2h-.07A8.001 8.001 0 0113 19.93z" fill="#fff"/></svg>제어</a></li>
        <li><a href="faults.php"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm0-4h-2V7h2v8z" fill="#fff"/></svg>고장</a></li>
        <li><a href="logs.php" aria-current="page"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 6v18h18V6H3zm16 16H5V8h14v14zm-7-2h2v-2h-2v2zm0-4h2v-4h-2v4z" fill="#fff"/></svg>로그</a></li>
        <li><a href="logout.php"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M16 13v-2H7V8l-5 4 5 4v-3h9zm3-10H5c-1.1 0-2 .9-2 2v6h2V5h14v14H5v-6H3v6c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z" fill="#fff"/></svg>로그아웃</a></li>
      </ul>
    </nav>
  </header>
  <main id="main-content" class="main-content" tabindex="-1">
    <h2><svg width="28" height="28" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 6v18h18V6H3zm16 16H5V8h14v14zm-7-2h2v-2h-2v2zm0-4h2v-4h-2v4z" fill="#005BAC"/></svg>시스템 로그 기록</h2>
    <form class="filter-form" method="get" action="logs.php" style="margin-bottom:18px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
      <input type="text" name="ip" placeholder="IP 검색" value="<?= htmlspecialchars($search_ip) ?>">
      <input type="text" name="user" placeholder="사용자 검색" value="<?= htmlspecialchars($search_user) ?>">
      <input type="text" name="keyword" placeholder="키워드 검색" value="<?= htmlspecialchars($search_keyword) ?>">
      <input type="date" name="start" value="<?= htmlspecialchars($search_start) ?>">
      <input type="date" name="end" value="<?= htmlspecialchars($search_end) ?>">
      <label><input type="checkbox" name="group_ip" value="1" <?= $group_by_ip ? 'checked' : '' ?>> IP별 그룹</label>
      <button type="submit">검색/필터</button>
      <a href="logs.php" style="margin-left:10px; color:#888; text-decoration:underline;">초기화</a>
    </form>
    <div class="log-stats" style="background:#f8f9fa;padding:15px;border-radius:8px;margin-bottom:20px;">
      <strong>📊 통계:</strong> 총 <?= number_format($total_logs) ?>개의 로그 기록 | 현재 <?= $current_page ?>페이지 / <?= $total_pages ?>페이지
    </div>
    <div class="log-list">
      <?php if (count($logs) > 0): ?>
        <?php foreach ($logs as $log): list($type, $icon) = logTypeInfo($log['log_message']); ?>
          <div class="log-card" tabindex="0" data-log='<?= htmlspecialchars(json_encode(["id"=>$log["id"]], JSON_UNESCAPED_UNICODE)) ?>' data-message="<?= htmlspecialchars($log['log_message']) ?>" data-type="<?= $type ?>" data-icon="<?= $icon ?>" data-user="<?= htmlspecialchars($log['username']) ?>" data-time="<?= date('s', strtotime($log['created_at'])) ?>초" data-ip="<?= htmlspecialchars($log['ip_address']) ?>">
            <span class="log-badge <?= $type ?>"><?= $icon ?> <?php
              if (strpos($log['log_message'], '장비 제어')!==false)      echo '장비제어';
              else if (strpos($log['log_message'], '고장 삭제')!==false) echo '고장삭제';
              else if (strpos($log['log_message'], '고장 접수')!==false) echo '고장접수';
              else if (strpos($log['log_message'], '고장 수정')!==false) echo '고장수정';
              else if (strpos($log['log_message'], '로그인 성공')!==false) echo '로그인';
              else if (strpos($log['log_message'], '로그인 실패')!==false) echo '로그인실패';
              else if (strpos($log['log_message'], '로그아웃')!==false) echo '로그아웃';
              else if (strpos($log['log_message'], '공격감지')!==false) echo '공격감지';
              else if (strpos($log['log_message'], 'PHPIDS')!==false) echo '공격감지';
              else echo '기타';
            ?></span>
            <div class="log-meta">
              <span class="log-user">사용자: <?= htmlspecialchars(isset($name_map[$log['username']]) && $name_map[$log['username']] ? $name_map[$log['username']] : $log['username']) ?></span>
              <span class="log-time">시간: <?= date('s', strtotime($log['created_at'])) ?>초</span>
              <span class="log-ip">IP: <?= htmlspecialchars(maskIP($log['ip_address'])) ?></span>
              <span class="log-simple">활동: <?php
                if (strpos($log['log_message'], '장비 제어')!==false)      echo '장비제어';
                else if (strpos($log['log_message'], '고장 삭제')!==false) echo '고장삭제';
                else if (strpos($log['log_message'], '고장 접수')!==false) echo '고장접수';
                else if (strpos($log['log_message'], '고장 수정')!==false) echo '고장수정';
                else if (strpos($log['log_message'], '로그인 성공')!==false) echo '로그인';
                else if (strpos($log['log_message'], '로그인 실패')!==false) echo '로그인실패';
                else if (strpos($log['log_message'], '로그아웃')!==false) echo '로그아웃';
                else if (strpos($log['log_message'], '공격감지')!==false) echo '공격감지';
                else if (strpos($log['log_message'], 'PHPIDS')!==false) echo '공격감지';
                else echo '기타';
              ?></span>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div style="text-align:center;padding:40px;color:#666;">📝 로그 기록이 없습니다.</div>
      <?php endif; ?>
    </div>
    <!-- 로그 상세 모달 -->
    <div class="modal-bg" id="logModalBg">
      <div class="modal-box">
        <button class="modal-close" id="logModalClose">×</button>
        <div class="modal-title">로그 상세 정보</div>
        <div class="modal-content-row"><strong>유형</strong> <span id="modalType"></span></div>
        <div class="modal-content-row"><strong>아이콘</strong> <span id="modalIcon"></span></div>
        <div class="modal-content-row"><strong>사용자</strong> <span id="modalUser"></span></div>
        <div class="modal-content-row"><strong>시간</strong> <span id="modalTime"></span></div>
        <div class="modal-content-row"><strong>IP</strong> <span id="modalIp"></span></div>
        <div class="modal-content-row"><strong>전체 메시지</strong> <span id="modalMsg"></span></div>
      </div>
    </div>
    <script>
      document.querySelectorAll('.log-card').forEach(card => {
        card.addEventListener('click', function() {
          document.getElementById('modalType').textContent = this.getAttribute('data-type');
          document.getElementById('modalIcon').textContent = this.getAttribute('data-icon');
          document.getElementById('modalUser').textContent = this.getAttribute('data-user');
          document.getElementById('modalTime').textContent = this.getAttribute('data-time');
          document.getElementById('modalIp').textContent = this.getAttribute('data-ip');
          document.getElementById('modalMsg').textContent = this.getAttribute('data-message');
          document.getElementById('logModalBg').classList.add('active');
        });
      });
      document.getElementById('logModalClose').onclick = function() {
        document.getElementById('logModalBg').classList.remove('active');
      };
      document.getElementById('logModalBg').onclick = function(e) {
        if (e.target === this) this.classList.remove('active');
      };
    </script>
    <?php if ($total_pages > 1): ?>
      <div class="pagination" style="display:flex;justify-content:center;align-items:center;margin-top:30px;gap:10px;">
        <?php if ($current_page > 1): ?>
          <a href="?page=1">« 처음</a>
          <a href="?page=<?= $current_page - 1 ?>">‹ 이전</a>
        <?php else: ?>
          <span class="disabled">« 처음</span>
          <span class="disabled">‹ 이전</span>
        <?php endif; ?>
        <?php
        $start_page = max(1, $current_page - 2);
        $end_page = min($total_pages, $current_page + 2);
        for ($i = $start_page; $i <= $end_page; $i++):
        ?>
          <?php if ($i == $current_page): ?>
            <span class="current" style="background:#007bff;color:white;border-radius:4px;padding:0 8px;"> <?= $i ?> </span>
          <?php else: ?>
            <a href="?page=<?= $i ?>"> <?= $i ?> </a>
          <?php endif; ?>
        <?php endfor; ?>
        <?php if ($current_page < $total_pages): ?>
          <a href="?page=<?= $current_page + 1 ?>">다음 ›</a>
          <a href="?page=<?= $total_pages ?>">마지막 »</a>
        <?php else: ?>
          <span class="disabled">다음 ›</span>
          <span class="disabled">마지막 »</span>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </main>
  <footer class="footer" role="contentinfo">
    <div>가천대학교 CPS |  <a href="#" style="color:#FFB300; text-decoration:underline;">이용약관</a> | <a href="#" style="color:#FFB300; text-decoration:underline;">개인정보처리방침</a> | 고객센터: 1234-5678</div>
    <div style="margin-top:8px;">© 2025 PLC Control</div>
  </footer>
</body>
</html>
