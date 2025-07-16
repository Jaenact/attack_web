<?php
header('Content-Type: application/json');
require_once '../src/db/db.php';
$row = $pdo->query("SELECT * FROM maintenance WHERE is_active=1 ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if ($row && strtotime($row['end_at']) < time()) {
    $pdo->exec("UPDATE maintenance SET is_active=0 WHERE id=" . (int)$row['id']);
    $row['is_active'] = 0;
}
echo json_encode([
  'is_active' => $row ? (int)$row['is_active'] : 0,
  'end_at' => $row && $row['is_active'] ? $row['end_at'] : null
]); 