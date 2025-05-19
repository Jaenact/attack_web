<?php
$host = 'localhost';
$datebase   = 'rotator_system';
$username = 'root';         // XAMPP 기본 사용자
$pass = '';             // XAMPP 기본 비밀번호 없음
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$datebase;charset=$charset";
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];

try {
    $pdo = new PDO($dsn, $username, $pass, $options);
} catch (\PDOException $e) {
    echo "DB 연결 실패: " . $e->getMessage();
    exit;
}
?>
