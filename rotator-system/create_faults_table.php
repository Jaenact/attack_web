<?php
require_once 'includes/db.php';   // rotator_system DB에 이미 연결 중

$sql = "
CREATE TABLE IF NOT EXISTS faults (
  id INT AUTO_INCREMENT PRIMARY KEY,
  part VARCHAR(255) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
";

$sql = "
CREATE TABLE IF NOT EXISTS faults (
  id INT AUTO_INCREMENT PRIMARY KEY,
  part VARCHAR(255) NOT NULL,
  filename VARCHAR(255),  -- 🔸 첨부파일 이름 저장
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
";

try {
  $pdo->exec($sql);
  echo "✅ faults 테이블 생성 완료!";
} catch (PDOException $e) {
  echo "❌ 오류: " . $e->getMessage();
}
?>
