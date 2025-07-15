<?php
session_start();
require_once 'src/db/db.php';
require_once 'src/log/log_function.php';

// 테스트용 세션 설정
$_SESSION['admin'] = 'test_admin';

// PHPIDS 라이브러리 로딩
if (file_exists(__DIR__ . '/PHPIDS/lib/IDS/Init.php')) {
    require_once __DIR__ . '/PHPIDS/lib/IDS/Init.php';
    require_once __DIR__ . '/PHPIDS/lib/IDS/Monitor.php';
    require_once __DIR__ . '/PHPIDS/lib/IDS/Report.php';
    require_once __DIR__ . '/PHPIDS/lib/IDS/Filter/Storage.php';
    require_once __DIR__ . '/PHPIDS/lib/IDS/Caching/CacheFactory.php';
    require_once __DIR__ . '/PHPIDS/lib/IDS/Caching/CacheInterface.php';
    require_once __DIR__ . '/PHPIDS/lib/IDS/Caching/FileCache.php';
    require_once __DIR__ . '/PHPIDS/lib/IDS/Filter.php';
    require_once __DIR__ . '/PHPIDS/lib/IDS/Event.php';
    require_once __DIR__ . '/PHPIDS/lib/IDS/Converter.php';
}

echo "<h2>보안 로그 테스트</h2>";

// 테스트 1: XSS 공격 시뮬레이션
echo "<h3>테스트 1: XSS 공격 (고장접수)</h3>";
try {
    $request = [
        'POST' => [
            'part' => '<script>alert("XSS Attack");</script>',
            'status' => '접수',
            'manager' => 'test_manager'
        ]
    ];
    
    $init = \IDS\Init::init(__DIR__ . '/PHPIDS/lib/IDS/Config/Config.ini.php');
    $ids = new \IDS\Monitor($init);
    $result = $ids->run($request);
    
    if (!$result->isEmpty()) {
        $userInput = [
            'part' => '<script>alert("XSS Attack");</script>',
            'status' => '접수',
            'manager' => 'test_manager'
        ];
        $logMessage = format_phpids_event($result, '고장접수', $userInput);
        
        // 로그 저장
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $pdo->prepare('INSERT INTO logs (username, action, log_message, ip_address) VALUES (?, ?, ?, ?)');
        $stmt->execute(['test_admin', '공격감지', $logMessage, $ip]);
        
        echo "<div style='background: #ffe6e6; padding: 15px; border: 1px solid #ff9999; border-radius: 5px; margin: 10px 0;'>";
        echo "<strong>✅ XSS 공격 감지됨!</strong><br>";
        echo "<pre style='background: #f9f9f9; padding: 10px; border-radius: 3px; overflow-x: auto;'>";
        echo htmlspecialchars($logMessage);
        echo "</pre>";
        echo "</div>";
    } else {
        echo "<div style='background: #e6ffe6; padding: 15px; border: 1px solid #99ff99; border-radius: 5px; margin: 10px 0;'>";
        echo "❌ XSS 공격이 감지되지 않았습니다.";
        echo "</div>";
    }
} catch (Exception $e) {
    echo "<div style='background: #ffe6e6; padding: 15px; border: 1px solid #ff9999; border-radius: 5px; margin: 10px 0;'>";
    echo "❌ 오류: " . $e->getMessage();
    echo "</div>";
}

// 테스트 2: SQL Injection 공격 시뮬레이션
echo "<h3>테스트 2: SQL Injection 공격 (제어)</h3>";
try {
    $request = [
        'POST' => [
            'action' => "admin' OR 1=1 --",
            'rpm' => '1500'
        ]
    ];
    
    $init = \IDS\Init::init(__DIR__ . '/PHPIDS/lib/IDS/Config/Config.ini.php');
    $ids = new \IDS\Monitor($init);
    $result = $ids->run($request);
    
    if (!$result->isEmpty()) {
        $userInput = [
            'action' => "admin' OR 1=1 --",
            'rpm' => '1500'
        ];
        $logMessage = format_phpids_event($result, '제어', $userInput);
        
        // 로그 저장
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $pdo->prepare('INSERT INTO logs (username, action, log_message, ip_address) VALUES (?, ?, ?, ?)');
        $stmt->execute(['test_admin', '공격감지', $logMessage, $ip]);
        
        echo "<div style='background: #ffe6e6; padding: 15px; border: 1px solid #ff9999; border-radius: 5px; margin: 10px 0;'>";
        echo "<strong>✅ SQL Injection 공격 감지됨!</strong><br>";
        echo "<pre style='background: #f9f9f9; padding: 10px; border-radius: 3px; overflow-x: auto;'>";
        echo htmlspecialchars($logMessage);
        echo "</pre>";
        echo "</div>";
    } else {
        echo "<div style='background: #e6ffe6; padding: 15px; border: 1px solid #99ff99; border-radius: 5px; margin: 10px 0;'>";
        echo "❌ SQL Injection 공격이 감지되지 않았습니다.";
        echo "</div>";
    }
} catch (Exception $e) {
    echo "<div style='background: #ffe6e6; padding: 15px; border: 1px solid #ff9999; border-radius: 5px; margin: 10px 0;'>";
    echo "❌ 오류: " . $e->getMessage();
    echo "</div>";
}

// 테스트 3: 회원가입 공격 시뮬레이션
echo "<h3>테스트 3: 회원가입 공격</h3>";
try {
    $request = [
        'POST' => [
            'username' => "admin<script>alert('XSS')</script>",
            'name' => "Test User",
            'phone' => "010-1234-5678"
        ]
    ];
    
    $init = \IDS\Init::init(__DIR__ . '/PHPIDS/lib/IDS/Config/Config.ini.php');
    $ids = new \IDS\Monitor($init);
    $result = $ids->run($request);
    
    if (!$result->isEmpty()) {
        $userInput = [
            'username' => "admin<script>alert('XSS')</script>",
            'name' => "Test User",
            'phone' => "010-1234-5678"
        ];
        $logMessage = format_phpids_event($result, '회원가입', $userInput);
        
        // 로그 저장
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $pdo->prepare('INSERT INTO logs (username, action, log_message, ip_address) VALUES (?, ?, ?, ?)');
        $stmt->execute(['test_admin', '공격감지', $logMessage, $ip]);
        
        echo "<div style='background: #ffe6e6; padding: 15px; border: 1px solid #ff9999; border-radius: 5px; margin: 10px 0;'>";
        echo "<strong>✅ 회원가입 공격 감지됨!</strong><br>";
        echo "<pre style='background: #f9f9f9; padding: 10px; border-radius: 3px; overflow-x: auto;'>";
        echo htmlspecialchars($logMessage);
        echo "</pre>";
        echo "</div>";
    } else {
        echo "<div style='background: #e6ffe6; padding: 15px; border: 1px solid #99ff99; border-radius: 5px; margin: 10px 0;'>";
        echo "❌ 회원가입 공격이 감지되지 않았습니다.";
        echo "</div>";
    }
} catch (Exception $e) {
    echo "<div style='background: #ffe6e6; padding: 15px; border: 1px solid #ff9999; border-radius: 5px; margin: 10px 0;'>";
    echo "❌ 오류: " . $e->getMessage();
    echo "</div>";
}

echo "<h3>최근 보안 로그 확인</h3>";
$stmt = $pdo->prepare("SELECT * FROM logs WHERE action='공격감지' ORDER BY created_at DESC LIMIT 3");
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($logs)) {
    echo "<div style='background: #f0f8ff; padding: 15px; border: 1px solid #87ceeb; border-radius: 5px; margin: 10px 0;'>";
    echo "<strong>📋 최근 보안 로그:</strong><br><br>";
    foreach ($logs as $log) {
        echo "<div style='background: white; padding: 10px; margin: 5px 0; border-radius: 3px; border-left: 4px solid #ff6b6b;'>";
        echo "<strong>시각:</strong> " . $log['created_at'] . "<br>";
        echo "<strong>사용자:</strong> " . $log['username'] . "<br>";
        echo "<strong>IP:</strong> " . $log['ip_address'] . "<br>";
        echo "<strong>로그 내용:</strong><br>";
        echo "<pre style='background: #f9f9f9; padding: 8px; border-radius: 3px; font-size: 12px; overflow-x: auto;'>";
        echo htmlspecialchars($log['log_message']);
        echo "</pre>";
        echo "</div>";
    }
    echo "</div>";
} else {
    echo "<div style='background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; border-radius: 5px; margin: 10px 0;'>";
    echo "📝 아직 보안 로그가 없습니다.";
    echo "</div>";
}
?>

<style>
body { font-family: 'Noto Sans KR', sans-serif; margin: 20px; background: #f5f7fa; }
h2 { color: #005BAC; border-bottom: 2px solid #005BAC; padding-bottom: 10px; }
h3 { color: #333; margin-top: 30px; }
pre { white-space: pre-wrap; word-wrap: break-word; }
</style> 