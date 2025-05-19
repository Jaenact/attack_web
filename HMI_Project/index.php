<?php
// 대시보드: 최근 로그 5개만 표시 (관리자만)
session_start();
require_once 'includes/db.php';

// 로그인 체크
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
$isAdmin = isset($_SESSION['admin']) && $_SESSION['admin'] === true;

// 최근 로그 5개만 불러오기 (관리자만)
$logs = [];
if ($isAdmin) {
    $stmt = $pdo->query("SELECT * FROM logs ORDER BY timestamp DESC LIMIT 5");
    $logs = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>PLC 대시보드</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="sidebar">
    <h3>PLC 제어</h3>
    <ul>
        <li><a href="index.php">🏠 대시보드</a></li>
        <li><a href="control.php">⚙ 회전기 제어</a></li>
        <li><a href="faults.php">🚨 고장 게시판</a></li>
        <?php if ($isAdmin): ?>
        <li><a href="logs.php">📝 시스템 로그</a></li>
        <?php endif; ?>
        <li><a href="logout.php">🔓 로그아웃</a></li>
    </ul>
</div>

<div class="main">
    <h2>📊 시스템 개요</h2>
    <p>환영합니다, <strong><?= htmlspecialchars($username) ?></strong>님!</p>
    <p>좌측 메뉴를 통해 제어 기능을 이용하거나 시스템을 확인할 수 있습니다.</p>
    <?php if ($isAdmin): ?>
    <p><strong>관리자 권한</strong>으로 로그인하셨습니다.</p>
    <?php else: ?>
    <p><strong>일반 사용자</strong>로 로그인하셨습니다.</p>
    <?php endif; ?>
    <hr>
    <?php if ($isAdmin): ?>
    <!-- 최근 시스템 로그 5개만 표시 (관리자만) -->
    <h3>📝 최근 시스템 로그 (5개)</h3>
    <table border="1" cellpadding="5" cellspacing="0">
        <thead>
        <tr>
            <th>시간</th>
            <th>사용자</th>
            <th>동작</th>
            <th>내용</th>
        </tr>
        </thead>
        <tbody>
        <?php if (count($logs) === 0): ?>
            <tr><td colspan="4">로그가 없습니다.</td></tr>
        <?php else: ?>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?= htmlspecialchars($log['timestamp']) ?></td>
                    <td><?= htmlspecialchars($log['username']) ?></td>
                    <td><?= htmlspecialchars($log['action']) ?></td>
                    <td><?= nl2br(htmlspecialchars($log['details'])) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
    <div style="margin-top:10px;">
        <a href="logs.php">전체 로그 더보기</a>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
