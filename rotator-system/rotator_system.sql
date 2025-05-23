-- rotator_system.sql

-- 데이터베이스 생성 (이미 생성된 경우 생략 가능)
CREATE DATABASE IF NOT EXISTS rotator_system CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE rotator_system;

-- 관리자 테이블 생성
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 고장 정보 테이블 생성
CREATE TABLE IF NOT EXISTS faults (
                                      id INT AUTO_INCREMENT PRIMARY KEY,
                                      part VARCHAR(255) NOT NULL,
    filename VARCHAR(255),  -- 파일 이름 저장용 컬럼
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

-- 예시 관리자 계정 (비밀번호: 1234 해시)
INSERT INTO admins (username, password)
VALUES ('admin', '$2y$10$j6UPBx3ib9BlCk4a0frLIeJoiCAuzCRvR4KcXfTi4K79Cn.yYhNwe');
