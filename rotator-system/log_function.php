<?php


function writeLog($pdo, $username, $message) {
    $sql = "INSERT INTO logs (username, log_message, ip_address, created_at)
            VALUES (:username, :log_message, :ip_address, NOW())";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'username' => $username,
        'log_message' => $message,
        'ip_address' => $_SERVER['REMOTE_ADDR']  // 클라이언트 IP
    ]);
}


?>
