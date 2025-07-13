<?php
// 안전한 DB 생성 스크립트 (데이터 삭제 없음)
// 운영 환경에서 실수로 실행해도 기존 데이터는 보존됩니다.

require_once '../src/db/db.php';

try {
    // 데이터베이스 생성 (이미 존재하면 건너뜀)
    $pdo->exec("CREATE DATABASE IF NOT EXISTS rotator_system CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;");
    $pdo->exec("USE rotator_system;");

    // admins 테이블
    $pdo->exec("CREATE TABLE IF NOT EXISTS admins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );");

    // faults 테이블
    $pdo->exec("CREATE TABLE IF NOT EXISTS faults (
            id INT AUTO_INCREMENT PRIMARY KEY,
            part VARCHAR(255) NOT NULL,
            filename VARCHAR(255),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );");

    // maintenance 테이블
    $pdo->exec("CREATE TABLE IF NOT EXISTS maintenance (
            id INT AUTO_INCREMENT PRIMARY KEY,
        start_at DATETIME NOT NULL,
        end_at DATETIME NOT NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_by VARCHAR(50),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );");

    // machine_status 테이블
    $pdo->exec("CREATE TABLE IF NOT EXISTS machine_status (
            id INT PRIMARY KEY AUTO_INCREMENT,
            status ENUM('on','off') NOT NULL DEFAULT 'off',
            rpm INT NOT NULL DEFAULT 0,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    );");

    // machine_status 최초 1행 삽입 (없을 경우)
    $pdo->exec("INSERT INTO machine_status (id, status, rpm) VALUES (1, 'off', 0)
        ON DUPLICATE KEY UPDATE id=id;");

    // 예시 관리자 계정 (비밀번호: 1234 해시)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE username = 'admin'");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO admins (username, password) VALUES ('admin', '$2y$10$j6UPBx3ib9BlCk4a0frLIeJoiCAuzCRvR4KcXfTi4K79Cn.yYhNwe');");
    }

    echo "✅ 데이터베이스 및 테이블 생성 완료 (기존 데이터는 보존됨)";
} catch (PDOException $e) {
    echo "❌ 오류: " . $e->getMessage();
}

?> 