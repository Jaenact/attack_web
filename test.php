<?php
require_once 'includes/db.php'; // rotator_system DB 연결됨

try {
    // 테이블 삭제
    $pdo->exec("DROP TABLE IF EXISTS logs");
    echo "🗑️ 기존 faults 테이블 삭제 완료<br>";

} catch (PDOException $e) {
    echo "❌ 오류 발생: " . $e->getMessage();
}
?>
<?php



            

