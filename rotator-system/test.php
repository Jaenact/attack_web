<?php
require_once 'includes/db.php'; // rotator_system DB 연결됨

try {
    // 테이블 삭제
    $pdo->exec("DROP TABLE IF EXISTS faults");
    echo "🗑️ 기존 faults 테이블 삭제 완료<br>";

    // 테이블 재생성
    $sql = "
        CREATE TABLE faults (
            id INT AUTO_INCREMENT PRIMARY KEY,
            part VARCHAR(255) NOT NULL,
            filename VARCHAR(255),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;
    ";
    $pdo->exec($sql);
    echo "✅ faults 테이블 재생성 완료";
} catch (PDOException $e) {
    echo "❌ 오류 발생: " . $e->getMessage();
}
?>
<?php
