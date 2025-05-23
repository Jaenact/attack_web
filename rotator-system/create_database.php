<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$host = 'localhost';
$username = 'root';
$password = ''; // XAMPP 기본은 비밀번호 없음

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



    // 3.1. 게스트트 테이블 생성
    $pdo->exec("
            CREATE TABLE IF NOT EXISTS guests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✅ 게스트 테이블 생성 완료<br>";



    // 4. 고장 정보 테이블 생성 (파일 업로드용 컬럼 포함)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS faults (
            id INT AUTO_INCREMENT PRIMARY KEY,
            part VARCHAR(255) NOT NULL,
            filename VARCHAR(255),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✅ 고장 정보 테이블 생성 완료<br>";


    //5. 로그 기능을 위한 테이블 생성성
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL,
            action VARCHAR(255),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ALTER TABLE logs ADD COLUMN log_message TEXT NOT NULL;
        )
    ");
    echo "✅ 로그 테이블 생성 완료<br>";



    // 5. 예시 관리자 계정 (비밀번호: 1234 해시값으로 저장)
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
