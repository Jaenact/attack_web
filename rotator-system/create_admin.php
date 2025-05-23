<?php
require_once 'includes/db.php';

$username = 'admin';
$password = '1234';
$hash = password_hash($password, PASSWORD_DEFAULT);

$pdo->exec("DELETE FROM admins WHERE username='admin'");
$stmt = $pdo->prepare("INSERT INTO admins (username, password) VALUES (?, ?)");
$stmt->execute([$username, $hash]);

echo "✅ 관리자 계정 재등록 완료";
