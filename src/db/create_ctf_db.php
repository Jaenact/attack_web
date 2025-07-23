<?php
$db = new SQLite3('ctf.db');

// users 테이블 생성
$db->exec("
  CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    score INTEGER DEFAULT 0
  );
");

// flags 테이블 생성
$db->exec("
  CREATE TABLE IF NOT EXISTS flags (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    flag_value TEXT NOT NULL UNIQUE,
    point INTEGER NOT NULL
  );
");

echo "✅ ctf.db 생성 완료 및 테이블 초기화됨.";
?>
