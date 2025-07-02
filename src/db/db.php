<?php
$host = 'localhost';
$database   = 'rotator_system';
$username = 'root';         
$pass = '1234';             
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$database;charset=$charset";
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];

try {
    $pdo = new PDO($dsn, $username, $pass, $options);
} catch (PDOException $e) {
    echo "DB 연결 실패: " . $e->getMessage();
    exit;
}
?>

