<?php
require_once __DIR__ . '/../src/db/db.php';

try {
    // faults 테이블에 필요한 컬럼들 추가
    $columns = [
        'user_id' => "ALTER TABLE faults ADD COLUMN user_id INT NULL",
        'status' => "ALTER TABLE faults ADD COLUMN status VARCHAR(20) DEFAULT '접수'",
        'manager' => "ALTER TABLE faults ADD COLUMN manager VARCHAR(100) NULL",
        'original_filename' => "ALTER TABLE faults ADD COLUMN original_filename VARCHAR(255) NULL",
        'admin_note' => "ALTER TABLE faults ADD COLUMN admin_note TEXT NULL"
    ];
    
    foreach ($columns as $column => $query) {
        try {
            // 컬럼이 존재하는지 확인
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'faults' AND COLUMN_NAME = ?");
            $stmt->execute([$column]);
            if ($stmt->fetchColumn() == 0) {
                $pdo->exec($query);
                echo "컬럼 '$column' 추가 완료<br>";
            } else {
                echo "컬럼 '$column' 이미 존재<br>";
            }
        } catch (Exception $e) {
            echo "컬럼 '$column' 추가 중 오류: " . $e->getMessage() . "<br>";
        }
    }
    
    // users 테이블이 없으면 생성
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        name VARCHAR(100) NULL,
        profile_img VARCHAR(255) NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // 기존 admin 계정을 users 테이블에 추가 (없는 경우)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = 'admin'");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $stmt = $pdo->prepare("INSERT INTO users (username, password, name) VALUES (?, ?, ?)");
        $stmt->execute(['admin', '$2y$10$j6UPBx3ib9BlCk4a0frLIeJoiCAuzCRvR4KcXfTi4K79Cn.yYhNwe', '관리자']);
    }
    
    echo "데이터베이스 업데이트가 완료되었습니다.<br>";
    echo "추가된 컬럼들:<br>";
    echo "- user_id: 작성자 ID<br>";
    echo "- status: 고장 상태<br>";
    echo "- manager: 담당자<br>";
    echo "- original_filename: 원본 파일명<br>";
    echo "- admin_note: 관리자 메모<br>";
    
} catch (Exception $e) {
    echo "오류 발생: " . $e->getMessage();
}
?> 