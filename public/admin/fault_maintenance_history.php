<?php
session_start();
require_once __DIR__ . '/../../src/db/db.php';
if (!isset($_SESSION['admin'])) {
    echo "<script>alert('관리자만 접근 가능합니다.');location.href='index.php';</script>";
    exit();
}
// 월별 고장/점검 통계
$month = $_GET['month'] ?? date('Y-m');
$start = $month.'-01';
$end = date('Y-m-t', strtotime($start));
$faults = $pdo->prepare("SELECT * FROM faults WHERE created_at BETWEEN ? AND ? ORDER BY created_at DESC");
$faults->execute([$start, $end]);
$fault_rows = $faults->fetchAll(PDO::FETCH_ASSOC);
$maint = $pdo->prepare("SELECT * FROM maintenance WHERE start_at BETWEEN ? AND ? ORDER BY start_at DESC");
$maint->execute([$start, $end]);
$maint_rows = $maint->fetchAll(PDO::FETCH_ASSOC);
// 통계
$fault_cnt = count($fault_rows);
$maint_cnt = count($maint_rows);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>고장/점검 이력 상세</title>
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <link rel="stylesheet" href="style.css">
  <style>
    .history-table{width:100%;border-collapse:collapse;margin-top:24px;}
    .history-table th,.history-table td{border:1px solid #e1e5e9;padding:8px 10px;text-align:center;}
    .history-table th{background:#f5f7fa;}
    .stat-card{display:inline-block;background:#f5f7fa;border-radius:12px;padding:18px 24px;margin:8px 10px;min-width:160px;box-shadow:0 2px 8px rgba(0,0,0,0.07);text-align:center;}
    .stat-title{font-size:1.05rem;font-weight:700;margin-bottom:6px;}
    .stat-value{font-size:1.5rem;font-weight:800;}
    @media(max-width:700px){.history-table{font-size:0.95rem;}.stat-card{padding:8px 2px;min-width:90px;}}
  </style>
</head>
<body style="background:linear-gradient(135deg,rgba(0,91,172,0.08) 0%,rgba(0,91,172,0.13) 100%);min-height:100vh;">
  <main style="max-width:900px;margin:40px auto 0 auto;background:#fff;border-radius:16px;box-shadow:0 4px 24px rgba(0,91,172,0.10);padding:40px 32px;">
    <h2 style="font-size:2rem;font-weight:700;color:#005BAC;margin-bottom:18px;">고장/점검 이력 상세</h2>
    <form method="get" style="margin-bottom:18px;display:flex;gap:8px;align-items:center;">
      <input type="month" name="month" value="<?=htmlspecialchars($month)?>" style="padding:6px 10px;border:1px solid #ccc;border-radius:6px;">
      <button type="submit" style="padding:6px 16px;background:#005BAC;color:#fff;border:none;border-radius:6px;">월별 조회</button>
    </form>
    <div style="margin-bottom:18px;">
      <div class="stat-card"><div class="stat-title">고장 이력</div><div class="stat-value"><?=$fault_cnt?></div></div>
      <div class="stat-card"><div class="stat-title">점검 이력</div><div class="stat-value"><?=$maint_cnt?></div></div>
    </div>
    <h3 style="margin-top:24px;">고장 이력</h3>
    <table class="history-table">
      <thead><tr><th>ID</th><th>부위/설명</th><th>첨부</th><th>상태</th><th>담당자</th><th>등록일</th></tr></thead>
      <tbody>
        <?php foreach($fault_rows as $f): ?>
        <tr>
          <td><?=$f['id']?></td>
          <td><?=htmlspecialchars($f['part'])?></td>
          <td><?=$f['filename']?'<a href="../uploads/'.htmlspecialchars($f['filename']).'" target="_blank">파일</a>':'-'?></td>
          <td><?=htmlspecialchars($f['status'])?></td>
          <td><?=htmlspecialchars($f['manager'])?></td>
          <td><?=date('Y-m-d',strtotime($f['created_at']))?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <h3 style="margin-top:32px;">점검 이력</h3>
    <table class="history-table">
      <thead><tr><th>ID</th><th>시작</th><th>종료</th><th>등록자</th><th>상태</th></tr></thead>
      <tbody>
        <?php foreach($maint_rows as $m): ?>
        <tr>
          <td><?=$m['id']?></td>
          <td><?=htmlspecialchars($m['start_at'])?></td>
          <td><?=htmlspecialchars($m['end_at'])?></td>
          <td><?=htmlspecialchars($m['created_by'])?></td>
          <td><?=$m['is_active']?'진행중':'종료'?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <a href="../index.php" style="display:inline-block;margin-top:24px;padding:10px 28px;background:linear-gradient(90deg,#005BAC 60%,#0076d7 100%);color:#fff;font-weight:700;border-radius:8px;text-decoration:none;box-shadow:0 2px 8px rgba(0,91,172,0.10);transition:background 0.2s;">← 대시보드로</a>
  </main>
</body>
</html> 