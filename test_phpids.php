<?php
// PHPIDS 테스트 파일
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h2>PHPIDS 테스트</h2>";

// PHPIDS 라이브러리 로딩
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

use IDS\Init;
use IDS\Monitor;

try {
    echo "<p>✅ PHPIDS 라이브러리 로딩 성공</p>";
    
    // 테스트 데이터
    $request = [
        'GET' => [
            'test' => '<script>alert("XSS")</script>'
        ]
    ];
    
    echo "<p>테스트 데이터: " . htmlspecialchars($request['GET']['test']) . "</p>";
    
    // PHPIDS 초기화
    $init = Init::init(__DIR__ . '/PHPIDS/lib/IDS/Config/Config.ini.php');
    echo "<p>✅ PHPIDS 초기화 성공</p>";
    
    // 모니터 생성
    $ids = new Monitor($init);
    echo "<p>✅ PHPIDS 모니터 생성 성공</p>";
    
    // 공격 탐지 실행
    $result = $ids->run($request);
    echo "<p>✅ PHPIDS 실행 성공</p>";
    
    if (!$result->isEmpty()) {
        echo "<p style='color: red;'>🚨 공격 감지됨!</p>";
        echo "<p>임팩트: " . $result->getImpact() . "</p>";
        echo "<p>상세 정보:</p>";
        echo "<pre>" . print_r($result, true) . "</pre>";
    } else {
        echo "<p style='color: green;'>✅ 공격 감지되지 않음</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ 오류 발생: " . $e->getMessage() . "</p>";
    echo "<p>파일: " . $e->getFile() . "</p>";
    echo "<p>라인: " . $e->getLine() . "</p>";
}
?> 