<?php
session_start();
require_once '../src/db/db.php';
require_once '../src/log/log_function.php';

// 로그인 체크
if (!isset($_SESSION['admin']) && !isset($_SESSION['guest'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['admin'] ?? $_SESSION['guest'] ?? '';
$isAdmin = isset($_SESSION['admin']);

// 테스트 결과 저장
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_test'])) {
    $test_type = $_POST['test_type'] ?? '';
    $test_input = $_POST['test_input'] ?? '';
    $test_result = '';
    
    // PHPIDS 테스트
    try {
        require_once '../PHPIDS/lib/IDS/Init.php';
        require_once '../PHPIDS/lib/IDS/Monitor.php';
        
        $request = ['POST' => ['test_input' => $test_input]];
        $init = \IDS\Init::init(__DIR__ . '/../PHPIDS/lib/IDS/Config/Config.ini.php');
        $ids = new \IDS\Monitor($init);
        $result = $ids->run($request);
        
        if (!$result->isEmpty()) {
            $test_result = "🚨 공격 감지됨! 임팩트: " . $result->getImpact();
            writeLog($pdo, $username, '보안테스트', '공격감지', $test_type . ': ' . $test_input);
        } else {
            $test_result = "✅ 안전한 입력으로 판단됨";
            writeLog($pdo, $username, '보안테스트', '안전', $test_type . ': ' . $test_input);
        }
    } catch (Exception $e) {
        $test_result = "❌ 테스트 오류: " . $e->getMessage();
    }
}

// 테스트 히스토리 조회
$stmt = $pdo->prepare("SELECT * FROM logs WHERE action='보안테스트' ORDER BY created_at DESC LIMIT 10");
$stmt->execute();
$test_history = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>보안 테스트 도구</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .security-container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .test-section { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 12px rgba(0,0,0,0.1); margin-bottom: 30px; }
        .test-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .test-card { border: 1px solid #eee; border-radius: 8px; padding: 20px; background: #f8f9fa; }
        .test-card h3 { color: #005BAC; margin-bottom: 15px; }
        .test-input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 10px; }
        .btn-test { background: #005BAC; color: #fff; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; }
        .btn-test:hover { background: #004a8c; }
        .result-box { background: #f0f8ff; border: 1px solid #87ceeb; border-radius: 4px; padding: 15px; margin-top: 15px; }
        .result-danger { background: #ffe6e6; border-color: #ff9999; }
        .result-safe { background: #e6ffe6; border-color: #99ff99; }
        .history-list { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 12px rgba(0,0,0,0.1); }
        .history-item { border-bottom: 1px solid #eee; padding: 10px 0; }
        .history-item:last-child { border-bottom: none; }
        .ctf-challenge { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 30px; border-radius: 8px; margin-bottom: 30px; }
        .challenge-title { font-size: 1.5rem; font-weight: 700; margin-bottom: 15px; }
        .challenge-description { margin-bottom: 20px; }
        .challenge-hint { background: rgba(255,255,255,0.1); padding: 15px; border-radius: 4px; margin-top: 15px; }
    </style>
</head>
<body>
    <div class="security-container">
        <h1>🔧 보안 테스트 도구</h1>
        <p>다양한 보안 취약점을 테스트하고 탐지 시스템을 검증할 수 있습니다.</p>
        
        <div class="ctf-challenge">
            <div class="challenge-title">🎯 CTF Challenge</div>
            <div class="challenge-description">
                다음 공격 벡터들을 테스트해보세요. 각각 다른 보안 취약점을 시뮬레이션합니다.
            </div>
            <div class="challenge-hint">
                <strong>💡 힌트:</strong> XSS, SQL Injection, 파일 업로드, 인증 우회 등을 시도해보세요.
            </div>
        </div>
        
        <div class="test-grid">
            <div class="test-card">
                <h3>🔍 XSS 테스트</h3>
                <p>Cross-Site Scripting 공격을 시뮬레이션합니다.</p>
                <form method="POST">
                    <input type="hidden" name="test_type" value="XSS">
                    <input type="text" name="test_input" class="test-input" 
                           placeholder="<script>alert('XSS')</script>" 
                           value="<script>alert('XSS')</script>">
                    <button type="submit" name="run_test" class="btn-test">테스트 실행</button>
                </form>
            </div>
            
            <div class="test-card">
                <h3>💉 SQL Injection 테스트</h3>
                <p>SQL Injection 공격을 시뮬레이션합니다.</p>
                <form method="POST">
                    <input type="hidden" name="test_type" value="SQL Injection">
                    <input type="text" name="test_input" class="test-input" 
                           placeholder="' OR 1=1 --" 
                           value="' OR 1=1 --">
                    <button type="submit" name="run_test" class="btn-test">테스트 실행</button>
                </form>
            </div>
            
            <div class="test-card">
                <h3>📁 파일 업로드 테스트</h3>
                <p>악성 파일 업로드 공격을 시뮬레이션합니다.</p>
                <form method="POST">
                    <input type="hidden" name="test_type" value="File Upload">
                    <input type="text" name="test_input" class="test-input" 
                           placeholder="shell.php" 
                           value="shell.php">
                    <button type="submit" name="run_test" class="btn-test">테스트 실행</button>
                </form>
            </div>
            
            <div class="test-card">
                <h3>🔐 인증 우회 테스트</h3>
                <p>인증 시스템 우회 공격을 시뮬레이션합니다.</p>
                <form method="POST">
                    <input type="hidden" name="test_type" value="Auth Bypass">
                    <input type="text" name="test_input" class="test-input" 
                           placeholder="admin'--" 
                           value="admin'--">
                    <button type="submit" name="run_test" class="btn-test">테스트 실행</button>
                </form>
            </div>
            
            <div class="test-card">
                <h3>📊 정보 노출 테스트</h3>
                <p>정보 노출 취약점을 시뮬레이션합니다.</p>
                <form method="POST">
                    <input type="hidden" name="test_type" value="Info Disclosure">
                    <input type="text" name="test_input" class="test-input" 
                           placeholder="../../../etc/passwd" 
                           value="../../../etc/passwd">
                    <button type="submit" name="run_test" class="btn-test">테스트 실행</button>
                </form>
            </div>
            
            <div class="test-card">
                <h3>🎯 커스텀 테스트</h3>
                <p>직접 입력한 페이로드를 테스트합니다.</p>
                <form method="POST">
                    <input type="hidden" name="test_type" value="Custom">
                    <input type="text" name="test_input" class="test-input" 
                           placeholder="직접 입력하세요">
                    <button type="submit" name="run_test" class="btn-test">테스트 실행</button>
                </form>
            </div>
        </div>
        
        <?php if (isset($test_result)): ?>
        <div class="test-section">
            <h2>📋 테스트 결과</h2>
            <div class="result-box <?= strpos($test_result, '감지됨') !== false ? 'result-danger' : 'result-safe' ?>">
                <strong>테스트 유형:</strong> <?= htmlspecialchars($_POST['test_type'] ?? '') ?><br>
                <strong>입력값:</strong> <?= htmlspecialchars($_POST['test_input'] ?? '') ?><br>
                <strong>결과:</strong> <?= htmlspecialchars($test_result) ?>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="history-list">
            <h2>📜 테스트 히스토리</h2>
            <?php if (empty($test_history)): ?>
                <p>아직 테스트 기록이 없습니다.</p>
            <?php else: ?>
                <?php foreach ($test_history as $history): ?>
                <div class="history-item">
                    <strong>시각:</strong> <?= $history['created_at'] ?><br>
                    <strong>사용자:</strong> <?= htmlspecialchars($history['username']) ?><br>
                    <strong>결과:</strong> <?= htmlspecialchars($history['log_message']) ?><br>
                    <strong>IP:</strong> <?= htmlspecialchars($history['ip_address']) ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="test-section">
            <h2>🎓 학습 자료</h2>
            <div class="test-grid">
                <div class="test-card">
                    <h3>XSS (Cross-Site Scripting)</h3>
                    <p>웹사이트에 악성 스크립트를 삽입하는 공격</p>
                    <ul>
                        <li>&lt;script&gt;alert('XSS')&lt;/script&gt;</li>
                        <li>&lt;img src=x onerror=alert('XSS')&gt;</li>
                        <li>javascript:alert('XSS')</li>
                    </ul>
                </div>
                
                <div class="test-card">
                    <h3>SQL Injection</h3>
                    <p>데이터베이스 쿼리에 악성 코드를 삽입하는 공격</p>
                    <ul>
                        <li>' OR 1=1 --</li>
                        <li>'; DROP TABLE users; --</li>
                        <li>' UNION SELECT * FROM users --</li>
                    </ul>
                </div>
                
                <div class="test-card">
                    <h3>파일 업로드</h3>
                    <p>악성 파일을 업로드하여 서버를 공격</p>
                    <ul>
                        <li>shell.php</li>
                        <li>backdoor.jpg.php</li>
                        <li>webshell.asp</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 