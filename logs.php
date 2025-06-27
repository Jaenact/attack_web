<?php
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['admin'])) {
    echo "<script>alert('관리자 계정이 아닙니다. 이전 페이지로 돌아갑니다.'); history.back();</script>";
    exit();
}

// IP 주소 마스킹 함수
function maskIP($ip) {
    if (empty($ip) || $ip === 'Unknown') {
        return 'N/A';
    }
    
    // IPv4 주소인 경우
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $parts = explode('.', $ip);
        if (count($parts) === 4) {
            return $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.xxx';
        }
    }
    
    // IPv6 주소인 경우
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $parts = explode(':', $ip);
        if (count($parts) >= 2) {
            $lastPart = array_pop($parts);
            return implode(':', $parts) . ':xxx';
        }
    }
    
    return $ip; // 마스킹할 수 없는 경우 원본 반환
}

// 페이지네이션 설정
$logs_per_page = 20; // 페이지당 로그 수
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $logs_per_page;

// 전체 로그 수 조회
$total_logs = $pdo->query("SELECT COUNT(*) FROM logs")->fetchColumn();
$total_pages = ceil($total_logs / $logs_per_page);

// 로그 불러오기 (페이지네이션 적용)
$sql = "SELECT * FROM logs ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':limit', $logs_per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();

// 로그 메시지 포맷팅 함수
function formatLogMessage($message) {
    // 주요 키워드별로 색상 구분
    $keywords = [
        '로그인 성공' => 'success',
        '로그인 실패' => 'error',
        '로그아웃' => 'info',
        '고장 접수' => 'warning',
        '고장 수정' => 'warning',
        '고장 삭제' => 'danger',
        '장비 제어' => 'primary'
    ];
    
    foreach ($keywords as $keyword => $type) {
        if (strpos($message, $keyword) !== false) {
            return "<span class='log-type log-$type'>$keyword</span>" . substr($message, strlen($keyword));
        }
    }
    
    return $message;
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>로그 확인</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .main {
            margin-left: 200px;
            padding: 20px;
        }
        .log-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .log-stats {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .log-table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.05);
            border-radius: 8px;
            overflow: hidden;
        }
        .log-table th, .log-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .log-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        .log-table tr:hover {
            background-color: #f8f9fa;
        }
        .log-message {
            max-width: 400px;
            word-wrap: break-word;
            line-height: 1.4;
        }
        .log-type {
            font-weight: bold;
            padding: 2px 6px;
            border-radius: 4px;
            margin-right: 5px;
        }
        .log-success { background-color: #d4edda; color: #155724; }
        .log-error { background-color: #f8d7da; color: #721c24; }
        .log-warning { background-color: #fff3cd; color: #856404; }
        .log-danger { background-color: #f8d7da; color: #721c24; }
        .log-info { background-color: #d1ecf1; color: #0c5460; }
        .log-primary { background-color: #cce5ff; color: #004085; }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 30px;
            gap: 10px;
        }
        .pagination a, .pagination span {
            padding: 8px 12px;
            text-decoration: none;
            border: 1px solid #ddd;
            border-radius: 4px;
            color: #333;
        }
        .pagination a:hover {
            background-color: #f8f9fa;
        }
        .pagination .current {
            background-color: #007bff;
            color: white;
            border-color: #007bff;
        }
        .pagination .disabled {
            color: #999;
            cursor: not-allowed;
        }
        
        .user-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        .user-admin { background-color: #dc3545; color: white; }
        .user-guest { background-color: #6c757d; color: white; }
        
        .time-format {
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <h3>PLC 제어</h3>
        <ul>
            <li><a href="index.php">🏠 대시보드</a></li>
            <li><a href="control.php">⚙ 회전기 제어</a></li>
            <li><a href="faults.php">🚨 고장 게시판</a></li>
            <li><a href="logs.php">📄 로그</a></li>
            <li><a href="logout.php">🔐 로그아웃</a></li>
        </ul>
    </div>

    <div class="main">
        <div class="log-header">
            <h2>📄 시스템 로그 기록</h2>
        </div>
        
        <div class="log-stats">
            <strong>📊 통계:</strong> 총 <?= number_format($total_logs) ?>개의 로그 기록 | 
            현재 <?= $current_page ?>페이지 / <?= $total_pages ?>페이지
        </div>
        
        <table class="log-table">
            <thead>
                <tr>
                    <th width="15%">시간</th>
                    <th width="12%">사용자</th>
                    <th width="58%">활동 내용</th>
                    <th width="15%">IP 주소</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($logs) > 0): ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td class="time-format">
                                <?= date('Y-m-d H:i:s', strtotime($log['created_at'])) ?>
                            </td>
                            <td>
                                <?php 
                                $userClass = (strpos($log['username'], 'admin') !== false) ? 'user-admin' : 'user-guest';
                                $userLabel = (strpos($log['username'], 'admin') !== false) ? '관리자' : '게스트';
                                ?>
                                <span class="user-badge <?= $userClass ?>"><?= $userLabel ?></span><br>
                                <small><?= htmlspecialchars($log['username']) ?></small>
                            </td>
                            <td class="log-message">
                                <?= htmlspecialchars(formatLogMessage($log['log_message'])) ?>
                            </td>
                            <td>
                                <code><?= htmlspecialchars(maskIP($log['ip_address'])) ?></code>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" style="text-align: center; padding: 40px; color: #666;">
                            📝 로그 기록이 없습니다.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
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
                        <span class="current"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?page=<?= $i ?>"><?= $i ?></a>
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
    </div>
</body>
</html>
