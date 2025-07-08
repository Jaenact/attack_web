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

    // 1. 기존 데이터베이스 삭제 후 재생성
    $pdo->exec("DROP DATABASE IF EXISTS rotator_system");
    $pdo->exec("CREATE DATABASE rotator_system CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    echo "✅ 데이터베이스 초기화 및 재생성 완료<br>";

    // 2. rotator_system 사용
    $pdo->exec("USE rotator_system");

    // 3. 통합 사용자 테이블 생성 (role 컬럼으로 관리자/게스트 구분)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin', 'guest') NOT NULL DEFAULT 'guest',
            name VARCHAR(100),
            phone VARCHAR(20),
            profile_img VARCHAR(255),
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    echo "✅ 통합 사용자 테이블 생성 완료<br>";

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

    // 5. 로그 테이블 생성
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

    // 8. 장비 상태 테이블 생성
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS machine_status (
            id INT PRIMARY KEY AUTO_INCREMENT,
            status ENUM('on','off') NOT NULL DEFAULT 'off',
            rpm INT NOT NULL DEFAULT 0,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    echo "✅ 장비 상태 테이블 생성 완료<br>";

    // 9. 알림(Notifications) 테이블 생성
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type VARCHAR(50) NOT NULL,
            message TEXT NOT NULL,
            url VARCHAR(255),
            is_read TINYINT(1) DEFAULT 0,
            target VARCHAR(50) DEFAULT 'admin',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "✅ 알림 테이블 생성 완료<br>";

    // 10. 기본 관리자 계정 생성
    $hashed_pw = password_hash("ateam12345!", PASSWORD_DEFAULT);
    $insert_stmt = $pdo->prepare("INSERT INTO users (username, password, role, name) VALUES ('admin', :pw, 'admin', '시스템 관리자')");
    $insert_stmt->execute(['pw' => $hashed_pw]);
    echo "✅ 기본 관리자 계정 생성 완료 (admin / ateam12345!)<br>";

    // 11. 기본 게스트 계정 생성
    $guest_pw = password_hash("guest1234!", PASSWORD_DEFAULT);
    $insert_stmt = $pdo->prepare("INSERT INTO users (username, password, role, name) VALUES ('guest', :pw, 'guest', '게스트 사용자')");
    $insert_stmt->execute(['pw' => $guest_pw]);
    echo "✅ 기본 게스트 계정 생성 완료 (guest / guest1234!)<br>";

    // 12. 장비 상태 초기 데이터 삽입
    $pdo->exec("INSERT INTO machine_status (id, status, rpm) VALUES (1, 'off', 0)");
    echo "✅ 장비 상태 초기 데이터 삽입 완료<br>";

    echo "<br>🎉 데이터베이스 초기화 및 재구성 완료!<br>";
    echo "새로운 통합 사용자 테이블 구조로 변경되었습니다.<br>";
    echo "- 관리자 계정: admin / ateam12345!<br>";
    echo "- 게스트 계정: guest / guest1234!<br>";

} catch (PDOException $e) {
    echo "❌ 오류: " . $e->getMessage();
    exit;
}

?> 