<?php


function writeLog($pdo, $username, $action, $result, $extra = null) {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $msg = "[$action][$result]" . ($extra ? " $extra" : "");
        $stmt = $pdo->prepare("INSERT INTO logs (username, ip_address, log_message, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$username, $ip, $msg]);
    } catch (Exception $e) {
        // 로그 기록 실패 시 전체 동작에 영향 주지 않음
        // 필요시 error_log($e->getMessage()); 등으로 운영 로그에 남길 수 있음
    }
}





#     if (isset($_SESSION['admin'])) {
#       $username = $_SESSION['admin'];
#      writeLog($pdo, $username, "장비 상태 변경: $action, 회전수: $rpm"); }


?>
