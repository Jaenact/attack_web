<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$host = 'localhost';
$username = 'root';
$password = '1234';

try {
    // 0. DB 접속 (데이터베이스 없이)
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. 데이터베이스 생성
    $pdo->exec("CREATE DATABASE IF NOT EXISTS rotator_system CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    echo "✅ 데이터베이스 생성 완료<br>";

    // 2. rotator_system 사용
    $pdo->exec("USE rotator_system");

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

    // 3.1. 게스트 테이블 생성
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS guests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✅ 게스트 테이블 생성 완료<br>";

    // 4. 고장 정보 테이블 생성
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS faults (
            id INT AUTO_INCREMENT PRIMARY KEY,
            part VARCHAR(255) NOT NULL,
            filename VARCHAR(255),
            original_filename VARCHAR(255),
            status VARCHAR(50),
            manager VARCHAR(50),
            comment_count INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✅ 고장 정보 테이블 생성 완료<br>";

    // 5. 로그 테이블 생성 (기본 컬럼만 먼저 생성)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL,
            action VARCHAR(255),
            log_message TEXT NOT NULL,
            ip_address VARCHAR(255),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✅ 로그 테이블 생성 완료<br>";

    // 5-1. log_message 컬럼 추가 (이미 있으면 무시)
    try {
        $pdo->exec("ALTER TABLE logs ADD COLUMN log_message TEXT NOT NULL");
        echo "✅ log_message 컬럼 추가 완료<br>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "ℹ️ log_message 컬럼 이미 존재<br>";
        } else {
            throw $e;
        }
    }

    // 6. 점검 테이블 생성
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS maintenance (
            id INT AUTO_INCREMENT PRIMARY KEY,
            start_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            end_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            is_active TINYINT(1) DEFAULT 1,
            created_by VARCHAR(50),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✅ 점검 테이블 생성 완료<br>";

    // 7. 공지사항 테이블 생성
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            content TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "✅ 공지사항 테이블 생성 완료<br>";

    // 6. 예시 관리자 계정 등록
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE username = 'admin'");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $hashed_pw = password_hash("ateam4567!", PASSWORD_DEFAULT);
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
