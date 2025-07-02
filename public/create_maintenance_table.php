<?php
require_once __DIR__ . '/../src/db/db.php';

$sql = "
CREATE TABLE IF NOT EXISTS maintenance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    start_at DATETIME NOT NULL,
    end_at DATETIME NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_by VARCHAR(50),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

try {
    $pdo->exec($sql);
    echo "maintenance 테이블이 성공적으로 생성되었습니다!";
} catch (PDOException $e) {
    echo "에러: " . $e->getMessage();
} 