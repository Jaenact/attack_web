<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

if (!isset($_SESSION['admin'])) {
    http_response_code(403);
    echo json_encode(['error' => '접근 권한이 없습니다.']);
    exit();
}

require_once __DIR__ . '/../../src/db/db.php';

try {
    // PHPIDS 로그 조회
    $sql = "SELECT * FROM logs WHERE (log_message LIKE '%공격감지%' OR log_message LIKE '%PHPIDS%') ORDER BY created_at DESC LIMIT 50";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 임팩트 레벨 계산 함수
    function getImpactLevel($message) {
        if (strpos($message, '임팩트:') !== false) {
            preg_match('/임팩트:\s*(\d+)/', $message, $matches);
            if (isset($matches[1])) {
                $impact = (int)$matches[1];
                if ($impact >= 20) return ['danger', '🚨', '높음'];
                if ($impact >= 10) return ['warning', '⚠️', '중간'];
                return ['info', 'ℹ️', '낮음'];
            }
        }
        return ['default', '📝', '알 수 없음'];
    }
    
    // IP 마스킹 함수
    function maskIP($ip) {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            return $parts[0] . '.' . $parts[1] . '.***.***';
        }
        return $ip;
    }
    
    // 로그 데이터 가공
    $processed_logs = [];
    foreach ($logs as $log) {
        list($impact_class, $impact_icon, $impact_level) = getImpactLevel($log['log_message']);
        
        $processed_logs[] = [
            'id' => $log['id'],
            'username' => $log['username'],
            'ip_address' => maskIP($log['ip_address']),
            'log_message' => $log['log_message'],
            'created_at' => $log['created_at'],
            'impact_class' => $impact_class,
            'impact_icon' => $impact_icon,
            'impact_level' => $impact_level,
            'formatted_time' => date('Y-m-d H:i:s', strtotime($log['created_at']))
        ];
    }
    
    // 통계 정보
    $total_security_events = $pdo->query("SELECT COUNT(*) FROM logs WHERE (log_message LIKE '%공격감지%' OR log_message LIKE '%PHPIDS%')")->fetchColumn();
    $today_security_events = $pdo->prepare("SELECT COUNT(*) FROM logs WHERE (log_message LIKE '%공격감지%' OR log_message LIKE '%PHPIDS%') AND DATE(created_at) = :today");
    $today_security_events->execute(['today' => date('Y-m-d')]);
    $today_security_events = $today_security_events->fetchColumn();
    
    $response = [
        'success' => true,
        'logs' => $processed_logs,
        'stats' => [
            'total' => $total_security_events,
            'today' => $today_security_events
        ]
    ];
    
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => '서버 오류가 발생했습니다: ' . $e->getMessage()]);
}
?> 