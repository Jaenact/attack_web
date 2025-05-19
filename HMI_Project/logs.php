<?php
// 관리자만 접근 가능한 로그 페이지
session_start();
require_once 'includes/db.php';

// 관리자 체크: 아니면 대시보드로 이동
if (!isset($_SESSION['admin']) || !$_SESSION['admin']) {
    header('Location: index.php');
    exit();
}

// 검색 및 정렬 파라미터
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$order = (isset($_GET['order']) && $_GET['order'] === 'asc') ? 'ASC' : 'DESC';

// 검색 쿼리 준비
$where = '';
$params = [];
if ($search !== '') {
    $where = "WHERE username LIKE :search OR action LIKE :search OR details LIKE :search";
    $params['search'] = "%$search%";
}

// 로그 불러오기 (최대 100개)
$sql = "SELECT * FROM logs $where ORDER BY timestamp $order LIMIT 100";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>시스템 로그</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .log-box { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); max-width: 900px; }
        .log-box h2 { margin-bottom: 20px; }
        .log-search { margin-bottom: 20px; }
        .log-table { width: 100%; border-collapse: collapse; }
        .log-table th, .log-table td { border: 1px solid #ccc; padding: 8px; text-align: center; }
        .log-table th { background: #f1f2f6; }
        .log-table tr:nth-child(even) { background: #f9f9f9; }
        .log-table tr:hover { background: #eaf6ff; }
        .log-controls { margin-bottom: 10px; }
    </style>
</head>
<body>
<div class="sidebar">
    <h3>PLC 제어</h3>
    <ul>
        <li><a href="index.php">🏠 대시보드</a></li>
        <li><a href="control.php">⚙ 회전기 제어</a></li>
        <li><a href="faults.php">🚨 고장 게시판</a></li>
        <li><a href="logs.php">📝 시스템 로그</a></li>
        <li><a href="logout.php">🔓 로그아웃</a></li>
    </ul>
</div>
<div class="main">
    <div class="log-box">
        <h2>📝 시스템 로그</h2>
        <form class="log-search" method="get" action="logs.php">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="검색 (사용자명, 동작, 내용)">
            <button type="submit">검색</button>
            <a href="logs.php" style="margin-left:10px;">초기화</a>
        </form>
        <div class="log-controls">
            <a href="?order=desc<?= $search ? '&search=' . urlencode($search) : '' ?>" style="margin-right:10px;<?= $order==='DESC'?'font-weight:bold;':'' ?>">최신순</a>
            <a href="?order=asc<?= $search ? '&search=' . urlencode($search) : '' ?>" style="<?= $order==='ASC'?'font-weight:bold;':'' ?>">오래된순</a>
        </div>
        <table class="log-table">
            <thead>
            <tr>
                <th>시간</th>
                <th>사용자</th>
                <th>세션 ID</th>
                <th>동작</th>
                <th>내용</th>
            </tr>
            </thead>
            <tbody>
            <?php if (count($logs) === 0): ?>
                <tr><td colspan="5">로그가 없습니다.</td></tr>
            <?php else: ?>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= htmlspecialchars($log['timestamp']) ?></td>
                        <td><?= htmlspecialchars($log['username']) ?></td>
                        <td><?= htmlspecialchars($log['session_id']) ?></td>
                        <td><?= htmlspecialchars($log['action']) ?></td>
                        <td><?= nl2br(htmlspecialchars($log['details'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
        <p style="margin-top:10px; color:#888; font-size:13px;">최대 100개까지 표시됩니다.</p>
    </div>
</div>
</body>
</html> 