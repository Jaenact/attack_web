<?php
function write_log($action, $details = "") {
    $log_path = __DIR__ . '/../logs/activity.log';
    $timestamp = date("Y-m-d H:i:s");
    $user = isset($_SESSION['username']) ? $_SESSION['username'] : 'Guest';
    $session_id = session_id();
    $log_entry = "[$timestamp] 사용자: $user | 세션: $session_id | 동작: $action | 내용: $details\n";
    file_put_contents($log_path, $log_entry, FILE_APPEND);
}
?>
