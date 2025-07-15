<?php
require_once __DIR__ . '/db.php';

function isMaintenanceActive() {
    global $pdo;
    $now = date('Y-m-d H:i:s');
    $sql = "SELECT * FROM maintenance WHERE is_active=1 AND start_at <= :now AND end_at >= :now ORDER BY id DESC LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['now' => $now]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: false;
}

function maintenanceRedirectIfNeeded($allow_admin_path = null) {
    // 관리자 세션이면 통과 (예외 경로가 지정된 경우, 해당 경로에서만 허용)
    if (isset($_SESSION['admin'])) {
        if ($allow_admin_path) {
            $current = $_SERVER['SCRIPT_NAME'] ?? '';
            if (strpos($current, $allow_admin_path) === false) {
                header('Location: /maintenance.php');
                exit();
            }
        }
        return;
    }
    $maintenance = isMaintenanceActive();
    if ($maintenance) {
        header('Location: /index.html'); // 점검 안내 페이지로 이동
        exit();
    }
} 