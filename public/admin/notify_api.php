<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

if (!isset($_SESSION['admin'])) {
    http_response_code(403);
    echo json_encode(['error' => '접근 권한이 없습니다.']);
    exit();
}
require_once __DIR__ . '/../../src/db/db.php';

$type_icons = [
    'fault' => ['🆘','고장'],
    'security' => ['🚨','보안'],
    'maintenance' => ['🛠️','점검'],
    'notice' => ['📢','공지'],
    'default' => ['🔔','알림']
];

if (isset($_GET['action']) && $_GET['action']==='read' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $pdo->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND target='admin'");
    $stmt->execute([$id]);
    echo json_encode(['success'=>true]);
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM notifications WHERE target='admin' ORDER BY created_at DESC LIMIT 20");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt2 = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE target='admin' AND is_read=0");
$stmt2->execute();
$unread = $stmt2->fetchColumn();

$notis = array_map(function($n) use ($type_icons) {
    $icon = $type_icons[$n['type']] ?? $type_icons['default'];
    return [
        'id' => $n['id'],
        'type' => $n['type'],
        'type_icon' => $icon[0],
        'type_label' => $icon[1],
        'message' => $n['message'],
        'url' => $n['url'],
        'is_read' => $n['is_read'],
        'time' => date('m/d H:i', strtotime($n['created_at']))
    ];
}, $rows);

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success'=>true,
    'notifications'=>$notis,
    'unread'=>(int)$unread
], JSON_UNESCAPED_UNICODE); 