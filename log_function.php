<?php


function writeLog($pdo, $username, $message) {
    $sql = "INSERT INTO logs (username, log_message, ip_address, created_at)
            VALUES (:username, :log_message, :ip_address, NOW())";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'username' => $username,
        'log_message' => $message,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
    ]);
}





#     if (isset($_SESSION['admin'])) {
#       $username = $_SESSION['admin'];
#      writeLog($pdo, $username, "장비 상태 변경: $action, 회전수: $rpm"); }


?>
