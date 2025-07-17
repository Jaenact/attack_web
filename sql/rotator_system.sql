-- rotator_system.sql (DB 초기화용)

-- 데이터베이스 생성 (이미 존재하면 생략)
CREATE DATABASE IF NOT EXISTS rotator_system CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE rotator_system;

-- 1. users 테이블
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE,
    password VARCHAR(255),
    role ENUM('admin','guest'),
    name VARCHAR(100),
    phone VARCHAR(50),
    profile_img VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME,
    updated_at DATETIME
);

-- 2. maintenance 테이블
CREATE TABLE IF NOT EXISTS maintenance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    is_active TINYINT(1) DEFAULT 0,
    start_at DATETIME,
    end_at DATETIME,
    created_by VARCHAR(100)
);

-- 3. faults 테이블
CREATE TABLE IF NOT EXISTS faults (
    id INT AUTO_INCREMENT PRIMARY KEY,
    part VARCHAR(255),
    filename VARCHAR(255),
    original_filename VARCHAR(255),
    status VARCHAR(50),
    manager VARCHAR(100),
    user_id INT,
    created_at DATETIME
);

-- 4. logs 테이블
CREATE TABLE IF NOT EXISTS logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100),
    ip_address VARCHAR(45),
    log_message TEXT,
    created_at DATETIME
);

-- 5. notices 테이블
CREATE TABLE IF NOT EXISTS notices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255),
    content TEXT,
    created_at DATETIME
);

-- 6. notifications 테이블
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(50),
    message TEXT,
    url VARCHAR(255),
    target VARCHAR(50),
    created_at DATETIME
);

-- 7. vulnerability_reports 테이블
CREATE TABLE IF NOT EXISTS vulnerability_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    severity ENUM('critical','high','medium','low') DEFAULT 'medium',
    category ENUM('sql_injection','xss','csrf','file_upload','authentication','information_disclosure','other') DEFAULT 'other',
    reproduction_steps TEXT,
    impact TEXT,
    reported_by VARCHAR(100) NOT NULL,
    status ENUM('pending','investigating','fixed','rejected') DEFAULT 'pending',
    admin_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 8. popups 테이블
CREATE TABLE IF NOT EXISTS popups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content TEXT,
    start_at DATE,
    end_at DATE
);

-- 9. banners 테이블
CREATE TABLE IF NOT EXISTS banners (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content TEXT,
    start_at DATE,
    end_at DATE
);

-- 샘플 관리자 계정 (비밀번호: 1234 해시)
INSERT INTO users (username, password, role, name, is_active, created_at)
VALUES ('admin', '$2y$10$j6UPBx3ib9BlCk4a0frLIeJoiCAuzCRvR4KcXfTi4K79Cn.yYhNwe', 'admin', '관리자', 1, NOW())
ON DUPLICATE KEY UPDATE id=id;
