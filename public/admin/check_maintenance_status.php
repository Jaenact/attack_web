<?php
header('Content-Type: application/json');
require_once '../src/db/db.php';
$row = $pdo->query("SELECT is_active FROM maintenance ORDER BY id DESC LIMIT 1")->fetch();
echo json_encode(['is_active' => $row ? (int)$row['is_active'] : 0]); 