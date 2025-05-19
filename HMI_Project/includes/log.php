<?php
require_once 'db.php';  // DB 연결 필요

function write_log($action, $details = "") {
    global $pdo;  // DB 연결 객체

    $timestamp = date("Y-m-d H:i:s");
    $user = isset($_SESSION['username']) ? $_SESSION['username'] : 'Guest';
    $session_id = session_id();

    $stmt = $pdo->prepare("INSERT INTO logs (timestamp, username, session_id, action, details) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$timestamp, $user, $session_id, $action, $details]);
}
?>
