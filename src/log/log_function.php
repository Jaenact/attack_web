<?php


function writeLog($pdo, $username, $action, $result, $extra = null) {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $msg = "[$action][$result]" . ($extra ? " $extra" : "");
        $stmt = $pdo->prepare("INSERT INTO logs (username, ip_address, log_message, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$username, $ip, $msg]);

        // --- 알림 생성 로직 추가 ---
        $notify_types = [
            '고장접수' => ['fault', '새 고장 접수', 'faults.php'],
            '고장수정' => ['fault', '고장 내용 수정', 'faults.php'],
            '고장삭제' => ['fault', '고장 내용 삭제', 'faults.php'],
            '공격감지' => ['security', '보안 이벤트 감지', 'logs.php'],
            'PHPIDS' => ['security', '보안 이벤트 감지', 'logs.php'],
            '점검시작' => ['maintenance', '시스템 점검 시작', 'index.php'],
            '점검종료' => ['maintenance', '시스템 점검 종료', 'index.php'],
            '공지등록' => ['notice', '새 공지 등록', 'index.php'],
            '공지수정' => ['notice', '공지 수정', 'index.php'],
            '공지삭제' => ['notice', '공지 삭제', 'index.php'],
        ];
        if (isset($notify_types[$action])) {
            list($type, $title, $url) = $notify_types[$action];
            $notify_msg = $title . ' - ' . ($extra ? $extra : $msg);
            $stmt2 = $pdo->prepare("INSERT INTO notifications (type, message, url, target) VALUES (?, ?, ?, 'admin')");
            $stmt2->execute([$type, $notify_msg, $url]);
        }
    } catch (Exception $e) {
        // 로그 기록 실패 시 전체 동작에 영향 주지 않음
        // 필요시 error_log($e->getMessage()); 등으로 운영 로그에 남길 수 있음
    }
}





#     if (isset($_SESSION['admin'])) {
#       $username = $_SESSION['admin'];
#      writeLog($pdo, $username, "장비 상태 변경: $action, 회전수: $rpm"); }


?>
