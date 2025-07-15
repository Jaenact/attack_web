<?php
$host = 'localhost';
$database   = 'rotator_system';
$username = 'root';         
$pass = '1234';             
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$database;charset=$charset";
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];

try {
    $pdo = new PDO($dsn, $username, $pass, $options);
} catch (PDOException $e) {
    echo "DB 연결 실패: " . $e->getMessage();
    exit;
}

// 유지보수 모드 체크 함수 (기존 maintenance_check.php)
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
        header('Location: /index.html');
        exit();
    }
}

// 로그 함수는 src/log/log_function.php에서 관리 (필요시 require_once로 연결)
// require_once __DIR__ . '/../log/log_function.php';
?>

