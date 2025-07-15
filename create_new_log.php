<?php
session_start();
require_once 'src/db/db.php';
require_once 'src/log/log_function.php';

// 테스트용 세션 설정
$_SESSION['admin'] = 'test_admin';

echo "<h2>새로운 보안 로그 생성 테스트</h2>";

// 새로운 형식의 로그 메시지 생성 (실제 PHPIDS 결과를 시뮬레이션)
$sampleLogs = [
    [
        'context' => '고장접수',
        'userInput' => [
            'part' => '<script>alert("XSS Attack");</script>',
            'status' => '접수',
            'manager' => 'test_manager'
        ],
        'impact' => 26,
        'events' => [
            [
                'value' => '<script>alert("XSS Attack");</script>',
                'name' => 'XSS Attack',
                'filterId' => 1,
                'description' => 'Finds html breaking injections including whitespace attacks',
                'impact' => 4
            ],
            [
                'value' => '<script>alert("XSS Attack");</script>',
                'name' => 'Script Injection',
                'filterId' => 16,
                'description' => 'Detects possible includes and typical script methods',
                'impact' => 5
            ]
        ]
    ],
    [
        'context' => '제어',
        'userInput' => [
            'action' => "admin' OR 1=1 --",
            'rpm' => '1500'
        ],
        'impact' => 15,
        'events' => [
            [
                'value' => "admin' OR 1=1 --",
                'name' => 'SQL Injection',
                'filterId' => 33,
                'description' => 'Detects SQL meta-characters',
                'impact' => 15
            ]
        ]
    ],
    [
        'context' => '회원가입',
        'userInput' => [
            'username' => "admin<script>alert('XSS')</script>",
            'name' => "Test User",
            'phone' => "010-1234-5678"
        ],
        'impact' => 20,
        'events' => [
            [
                'value' => "admin<script>alert('XSS')</script>",
                'name' => 'XSS in Username',
                'filterId' => 38,
                'description' => 'Detects possibly malicious html elements',
                'impact' => 20
            ]
        ]
    ]
];

// 새로운 형식으로 로그 생성
foreach ($sampleLogs as $index => $logData) {
    // 새로운 형식의 로그 메시지 생성
    $lines = [];
    $lines[] = "[PHPIDS] 보안 이벤트 감지";
    $lines[] = "- 시각: " . date('Y-m-d H:i:s');
    $lines[] = "- IP: " . ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
    $lines[] = "- 사용자: test_admin (관리자)";
    $lines[] = "- 발생위치: " . $logData['context'];
    
    // 사용자 입력값 요약
    $inputSummary = [];
    foreach ($logData['userInput'] as $key => $value) {
        if (is_string($value) && !empty($value)) {
            $inputSummary[] = "$key=" . (strlen($value) > 50 ? substr($value, 0, 50) . "..." : $value);
        }
    }
    if (!empty($inputSummary)) {
        $lines[] = "- 입력값: " . implode(", ", $inputSummary);
    }
    
    $lines[] = "- 임팩트 점수: " . $logData['impact'];
    
    // 각 이벤트 상세 정보
    foreach ($logData['events'] as $event) {
        $lines[] = "- 감지 파라미터: " . $event['value'];
        $lines[] = "- 필터/룰: " . $event['name'] . " (ID: " . $event['filterId'] . ")";
        $lines[] = "- 탐지 기준: " . $event['description'];
        $lines[] = "- 위험도: " . $event['impact'];
    }
    
    $logMessage = implode("\n", $lines);
    
    // DB에 로그 저장
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $stmt = $pdo->prepare('INSERT INTO logs (username, action, log_message, ip_address) VALUES (?, ?, ?, ?)');
    $stmt->execute(['test_admin', '공격감지', $logMessage, $ip]);
    
    echo "<div style='background: #ffe6e6; padding: 15px; border: 1px solid #ff9999; border-radius: 5px; margin: 10px 0;'>";
    echo "<strong>✅ 새로운 형식 로그 생성됨! (테스트 " . ($index + 1) . ")</strong><br>";
    echo "<strong>컨텍스트:</strong> " . $logData['context'] . "<br>";
    echo "<strong>임팩트:</strong> " . $logData['impact'] . "<br>";
    echo "<pre style='background: #f9f9f9; padding: 10px; border-radius: 3px; overflow-x: auto; font-size: 12px;'>";
    echo htmlspecialchars($logMessage);
    echo "</pre>";
    echo "</div>";
}

echo "<h3>최근 생성된 새로운 형식 로그 확인</h3>";
$stmt = $pdo->prepare("SELECT * FROM logs WHERE action='공격감지' ORDER BY created_at DESC LIMIT 3");
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($logs)) {
    echo "<div style='background: #f0f8ff; padding: 15px; border: 1px solid #87ceeb; border-radius: 5px; margin: 10px 0;'>";
    echo "<strong>📋 최근 보안 로그 (새로운 형식):</strong><br><br>";
    foreach ($logs as $log) {
        echo "<div style='background: white; padding: 10px; margin: 5px 0; border-radius: 3px; border-left: 4px solid #ff6b6b;'>";
        echo "<strong>시각:</strong> " . $log['created_at'] . "<br>";
        echo "<strong>사용자:</strong> " . $log['username'] . "<br>";
        echo "<strong>IP:</strong> " . $log['ip_address'] . "<br>";
        echo "<strong>로그 내용:</strong><br>";
        echo "<pre style='background: #f9f9f9; padding: 8px; border-radius: 3px; font-size: 12px; overflow-x: auto; white-space: pre-wrap;'>";
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

echo "<h3>기존 vs 새로운 형식 비교</h3>";
echo "<div style='background: #f0f0f0; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<strong>기존 형식 (print_r):</strong><br>";
echo "<pre style='background: #fff; padding: 8px; border-radius: 3px; font-size: 11px;'>";
echo "PHPIDS 고장접수 입력값 공격 감지! 임팩트: 52, 상세: IDS\\Report Object\n";
echo "(\n";
echo "    [events:protected] => Array\n";
echo "        (\n";
echo "            [POST.part] => IDS\\Event Object\n";
echo "                (\n";
echo "                    [name:protected] => POST.part\n";
echo "                    [value:protected] => &lt;script&gt;alert('XSS');&lt;/script&gt;\n";
echo "                    [filters:protected] => Array\n";
echo "                        (\n";
echo "                            [0] => IDS\\Filter Object\n";
echo "                                (\n";
echo "                                    [id] => 1\n";
echo "                                    [rule:protected] => (?:\"[^\"]*[^-]?&gt;)|(?:[^\\w\\s]\\s*\\/&gt;)|(?:&gt;\")...\n";
echo "                                )\n";
echo "                        )\n";
echo "                )\n";
echo "        )\n";
echo "    [impact:protected] => 52\n";
echo ")";
echo "</pre><br>";

echo "<strong>새로운 형식 (요약):</strong><br>";
echo "<pre style='background: #fff; padding: 8px; border-radius: 3px; font-size: 11px;'>";
echo "[PHPIDS] 보안 이벤트 감지\n";
echo "- 시각: 2024-06-10 14:23:11\n";
echo "- IP: 192.168.0.10\n";
echo "- 사용자: admin (관리자)\n";
echo "- 발생위치: 고장접수\n";
echo "- 입력값: part=&lt;script&gt;alert(\"XSS Attack\");&lt;/script&gt;, status=접수, manager=test_manager\n";
echo "- 임팩트 점수: 26\n";
echo "- 감지 파라미터: &lt;script&gt;alert(\"XSS Attack\");&lt;/script&gt;\n";
echo "- 필터/룰: XSS Attack (ID: 1)\n";
echo "- 탐지 기준: Finds html breaking injections including whitespace attacks\n";
echo "- 위험도: 4";
echo "</pre>";
echo "</div>";
?>

<style>
body { font-family: 'Noto Sans KR', sans-serif; margin: 20px; background: #f5f7fa; }
h2 { color: #005BAC; border-bottom: 2px solid #005BAC; padding-bottom: 10px; }
h3 { color: #333; margin-top: 30px; }
pre { white-space: pre-wrap; word-wrap: break-word; }
</style> 