<?php
// DB 완전 초기화 및 테이블 재생성 스크립트
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$host = 'localhost';
$username = 'root';
$password = '';
$dbname = 'rotator_system';

try {
    // 0. DB 접속 (데이터베이스 없이)
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. 기존 DB 삭제(있으면)
    $pdo->exec("DROP DATABASE IF EXISTS $dbname");
    echo "✅ 기존 DB 삭제 완료<br>";

    // 2. 새 DB 생성
    $pdo->exec("CREATE DATABASE $dbname CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    echo "✅ 새 데이터베이스 생성 완료<br>";
    $pdo->exec("USE $dbname");

    // 3. 관리자 테이블 생성
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✅ 관리자 테이블 생성 완료<br>";

    // 4. 사용자 테이블 생성
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✅ 사용자 테이블 생성 완료<br>";

    // 5. 고장 정보 테이블 생성 (username 컬럼 포함)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS faults (
            id INT AUTO_INCREMENT PRIMARY KEY,
            part VARCHAR(255) NOT NULL,
            filename VARCHAR(255),
            username VARCHAR(50),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✅ 고장 정보 테이블 생성 완료<br>";

    // 6. 시스템 로그 테이블 생성
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            username VARCHAR(50),
            session_id VARCHAR(128),
            action VARCHAR(255),
            details TEXT
        )
    ");
    echo "✅ 로그 테이블 생성 완료<br>";

    // 7. 예시 관리자 계정 (비밀번호: 1234 해시값으로 저장)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE username = 'admin'");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $hashed_pw = password_hash("1234", PASSWORD_DEFAULT);
        $insert_stmt = $pdo->prepare("INSERT INTO admins (username, password) VALUES ('admin', :pw)");
        $insert_stmt->execute(['pw' => $hashed_pw]);
        echo "✅ 예시 관리자 계정 등록 완료<br>";
    } else {
        echo "ℹ️ 관리자 계정 이미 존재<br>";
    }

} catch (PDOException $e) {
    echo "❌ 오류: " . $e->getMessage();
    exit;
}
?>
