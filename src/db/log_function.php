<?php
function writeLog($pdo, $username, $action, $result, $ip, $extra = null) {
    $msg = "[$action][$result]" . ($extra ? " $extra" : "");
    $stmt = $pdo->prepare("INSERT INTO logs (username, ip_address, log_message, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$username, $ip, $msg]);
} 