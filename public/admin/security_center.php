<?php
session_start();
require_once '../../src/db/db.php';
require_once '../../src/log/log_function.php';

if (!isset($_SESSION['admin'])) {
    header('Location: ../login.php');
    exit();
}

// --- 보안 이벤트(대시보드) 데이터 ---
// 오늘/주간/임팩트/공격유형/시간대별/상위IP 등 기존 security_dashboard.php 코드 이식
$stats = [];
$stmt = $pdo->prepare("SELECT COUNT(*) FROM logs WHERE (log_message LIKE '%공격감지%' OR log_message LIKE '%PHPIDS%') AND DATE(created_at) = :today");
$stmt->execute(['today' => date('Y-m-d')]);
$stats['today_events'] = $stmt->fetchColumn();
$stmt = $pdo->prepare("SELECT COUNT(*) FROM logs WHERE (log_message LIKE '%공격감지%' OR log_message LIKE '%PHPIDS%') AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$stmt->execute();
$stats['week_events'] = $stmt->fetchColumn();
$stmt = $pdo->query("SELECT COUNT(*) FROM logs WHERE (log_message LIKE '%공격감지%' OR log_message LIKE '%PHPIDS%') AND log_message LIKE '%임팩트: 2%'");
$stats['high_impact'] = $stmt->fetchColumn();
$stmt = $pdo->query("SELECT 
    CASE 
        WHEN log_message LIKE '%xss%' OR log_message LIKE '%script%' OR log_message LIKE '%alert%' THEN 'XSS'
        WHEN log_message LIKE '%sql%' OR log_message LIKE '%or 1=1%' OR log_message LIKE '%union%' THEN 'SQL Injection'
        WHEN log_message LIKE '%파일%' OR log_message LIKE '%upload%' THEN 'File Upload'
        WHEN log_message LIKE '%인증%' OR log_message LIKE '%auth%' THEN 'Auth Bypass'
        WHEN log_message LIKE '%eval%' OR log_message LIKE '%exec%' THEN 'Code Injection'
        WHEN log_message LIKE '%csrf%' THEN 'CSRF'
        WHEN log_message LIKE '%lfi%' OR log_message LIKE '%include%' THEN 'LFI/RFI'
        ELSE '기타'
    END as attack_type,
    COUNT(*) as count
    FROM logs 
    WHERE log_message LIKE '%공격감지%' OR log_message LIKE '%PHPIDS%'
    GROUP BY attack_type 
    ORDER BY count DESC");
$attack_types = $stmt->fetchAll();
$stmt = $pdo->prepare("SELECT * FROM logs WHERE (log_message LIKE '%공격감지%' OR log_message LIKE '%PHPIDS%') ORDER BY created_at DESC LIMIT 10");
$stmt->execute();
$recent_events = $stmt->fetchAll();
$stmt = $pdo->prepare("SELECT 
    HOUR(created_at) as hour,
    COUNT(*) as count
    FROM logs 
    WHERE (log_message LIKE '%공격감지%' OR log_message LIKE '%PHPIDS%') 
    AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    GROUP BY HOUR(created_at)
    ORDER BY hour");
$stmt->execute();
$hourly_events = $stmt->fetchAll();
$stmt = $pdo->prepare("SELECT 
    ip_address,
    COUNT(*) as count
    FROM logs 
    WHERE (log_message LIKE '%공격감지%' OR log_message LIKE '%PHPIDS%')
    GROUP BY ip_address
    ORDER BY count DESC
    LIMIT 10");
$stmt->execute();
$top_attackers = $stmt->fetchAll();

// --- 보안테스트 데이터 ---
$test_result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_test'])) {
    $test_type = $_POST['test_type'] ?? '';
    $test_input = $_POST['test_input'] ?? '';
    try {
        require_once '../../PHPIDS/lib/IDS/Init.php';
        require_once '../../PHPIDS/lib/IDS/Monitor.php';
        $request = ['POST' => ['test_input' => $test_input]];
        $init = \IDS\Init::init(__DIR__ . '/../../PHPIDS/lib/IDS/Config/Config.ini.php');
        $ids = new \IDS\Monitor($init);
        $result = $ids->run($request);
        if (!$result->isEmpty()) {
            $test_result = "🚨 공격 감지됨! 임팩트: " . $result->getImpact();
            writeLog($pdo, $_SESSION['admin'], '보안테스트', '공격감지', $test_type . ': ' . $test_input);
        } else {
            $test_result = "✅ 안전한 입력으로 판단됨";
            writeLog($pdo, $_SESSION['admin'], '보안테스트', '안전', $test_type . ': ' . $test_input);
        }
    } catch (Exception $e) {
        $test_result = "❌ 테스트 오류: " . $e->getMessage();
    }
}
$stmt = $pdo->prepare("SELECT * FROM logs WHERE action='보안테스트' ORDER BY created_at DESC LIMIT 10");
$stmt->execute();
$test_history = $stmt->fetchAll();

// --- 취약점 제보 데이터 ---
// 테이블 생성 보장
$check_table = $pdo->query("SHOW TABLES LIKE 'vulnerability_reports'");
$table_exists = $check_table->rowCount() > 0;
if (!$table_exists) {
    $create_table_sql = "
    CREATE TABLE IF NOT EXISTS vulnerability_reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        severity ENUM('critical', 'high', 'medium', 'low') DEFAULT 'medium',
        category ENUM('sql_injection', 'xss', 'csrf', 'file_upload', 'authentication', 'information_disclosure', 'other') DEFAULT 'other',
        reproduction_steps TEXT,
        impact TEXT,
        reported_by VARCHAR(100) NOT NULL,
        status ENUM('pending', 'investigating', 'fixed', 'rejected') DEFAULT 'pending',
        admin_notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($create_table_sql);
}
// 제보 등록 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_report'])) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $severity = $_POST['severity'] ?? 'medium';
    $category = $_POST['category'] ?? 'other';
    $reproduction_steps = trim($_POST['reproduction_steps'] ?? '');
    $impact = trim($_POST['impact'] ?? '');
    if ($title && $description) {
        $stmt = $pdo->prepare("INSERT INTO vulnerability_reports (title, description, severity, category, reproduction_steps, impact, reported_by, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
        $result = $stmt->execute([$title, $description, $severity, $category, $reproduction_steps, $impact, $_SESSION['admin']]);
        if ($result) {
            writeLog($pdo, $_SESSION['admin'], '취약점제보', '성공', $title);
            echo "<script>alert('취약점이 성공적으로 제보되었습니다.'); location.href='security_center.php#vul';</script>";
            exit();
        }
    }
}
$stmt = $pdo->prepare("SELECT * FROM vulnerability_reports ORDER BY created_at DESC LIMIT 10");
$stmt->execute();
$recent_reports = $stmt->fetchAll();
$stmt = $pdo->query("SELECT status, COUNT(*) as count FROM vulnerability_reports GROUP BY status");
$status_counts = $stmt->fetchAll();
$stats_vul = [];
foreach ($status_counts as $row) {
    $stats_vul[$row['status']] = $row['count'];
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>통합 보안 관리</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background: #f7f9fb; }
        .security-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 18px;
            margin: 32px 0 24px 0;
            justify-content: center;
        }
        .security-summary-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            padding: 22px 18px;
            min-width: 120px;
            text-align: center;
            transition: box-shadow 0.2s, transform 0.2s;
        }
        .security-summary-card:hover {
            box-shadow: 0 4px 24px rgba(0,91,172,0.13);
            transform: translateY(-2px) scale(1.03);
        }
        .security-summary-card .num {
            font-size: 2.1rem;
            font-weight: 800;
            color: #ff4757;
        }
        .security-summary-card .label {
            font-size: 1.05rem;
            color: #333;
            margin-top: 8px;
            font-weight: 600;
        }
        .security-tabs { display: flex; gap: 0; border-bottom: 2px solid #e0e0e0; margin-bottom: 0; }
        .security-tab { flex: 1; text-align: center; padding: 18px 0; font-size: 1.15rem; font-weight: 700; color: #888; background: #f7f9fb; border: none; outline: none; cursor: pointer; transition: background 0.2s, color 0.2s; }
        .security-tab.active { color: #ff4757; background: #fff; border-bottom: 2px solid #ff4757; }
        .security-tab-content { background: #fff; border-radius: 0 0 12px 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); padding: 32px 28px; min-height: 320px; }
        .event-item { border-bottom: 1px solid #eee; padding: 15px 0; cursor:pointer; transition: background 0.2s; }
        .event-item:hover { background: #f8f9fa; }
        .event-item:last-child { border-bottom: none; }
        .event-type { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; margin-left: 10px; }
        .event-type-danger { background: #ff4444; color: #fff; }
        .event-type-warning { background: #ff8800; color: #fff; }
        .event-type-info { background: #0099ff; color: #fff; }
        .chart-container { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 12px rgba(0,0,0,0.1); margin-bottom: 30px; }
        .chart-title { font-size: 1.2rem; font-weight: 600; color: #333; margin-bottom: 20px; }
        .reports-list { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 12px rgba(0,0,0,0.1); }
        .report-table { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        .report-table th, .report-table td { padding: 10px 8px; border-bottom: 1px solid #eee; text-align: left; font-size: 1rem; }
        .report-table th { background: #f8f9fa; color: #005BAC; font-weight: 700; }
        .report-table tr:hover { background: #f3f6fa; cursor:pointer; }
        .severity-badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
        .severity-critical { background: #ff4444; color: #fff; }
        .severity-high { background: #ff8800; color: #fff; }
        .severity-medium { background: #ffcc00; color: #333; }
        .severity-low { background: #00cc00; color: #fff; }
        .status-badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
        .status-pending { background: #ffcc00; color: #333; }
        .status-investigating { background: #0099ff; color: #fff; }
        .status-fixed { background: #00cc00; color: #fff; }
        .status-rejected { background: #ff4444; color: #fff; }
        .search-bar { margin-bottom: 18px; display: flex; gap: 10px; flex-wrap: wrap; }
        .search-bar input, .search-bar select { padding: 8px 12px; border-radius: 6px; border: 1.5px solid #b3c6e0; font-size: 1rem; }
        .search-bar button { background: #005BAC; color: #fff; border: none; border-radius: 6px; padding: 8px 18px; font-weight: 600; cursor: pointer; }
        .search-bar button:hover { background: #003366; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; }
        .modal-content { background: #fff; border-radius: 16px; box-shadow: 0 8px 32px rgba(0,0,0,0.2); padding: 32px 28px; min-width:320px; max-width:90vw; max-height:90vh; overflow-y:auto; position:relative; filter: drop-shadow(0 0 8px #ff4757aa); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .modal-title { font-size: 1.5rem; font-weight: 700; color: #333; }
        .close { color: #aaa; font-size: 32px; font-weight: bold; cursor: pointer; border-radius:50%; background:#f8f9fa; width:40px; height:40px; display:flex; align-items:center; justify-content:center; transition:background 0.2s; }
        .close:hover { color: #fff; background: #ff4757; }
        .modal-detail { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 15px; }
        .modal-detail h4 { margin: 0 0 10px 0; color: #333; }
        .modal-detail pre { background: #fff; padding: 15px; border-radius: 4px; overflow-x: auto; white-space: pre-wrap; font-size: 14px; line-height: 1.5; }
        .btn-action { background: #ff4757; color: #fff; border: none; border-radius: 8px; padding: 8px 22px; font-size: 1.02rem; font-weight: 700; cursor: pointer; margin-right: 8px; }
        .btn-action:hover { background: #E53935; }
        .btn-csv { background: #43e97b; color: #fff; border: none; border-radius: 8px; padding: 8px 22px; font-size: 1.02rem; font-weight: 700; cursor: pointer; }
        .btn-csv:hover { background: #2e7d32; }
        @media (max-width: 900px) {
            .security-summary { grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; }
            .security-summary-card { min-width: 90px; padding: 12px 6px; }
            .security-tab-content { padding: 12px 2px; }
            .modal-content { padding: 16px 6px; }
        }
        .test-card { background: #fff; border-radius: 14px; box-shadow: 0 2px 12px rgba(0,91,172,0.10); padding: 24px 18px; margin: 10px; flex: 1 1 260px; min-width: 220px; max-width: 340px; transition: box-shadow 0.2s, transform 0.2s; border-top: 6px solid #ff4757; position:relative; }
        .test-card:hover { box-shadow: 0 6px 24px rgba(0,91,172,0.18); transform: translateY(-2px) scale(1.03); border-top: 6px solid #005BAC; }
        .test-card h3 { margin-top:0; font-size:1.2rem; color:#ff4757; }
        .test-card p { color:#333; font-size:1rem; }
        .test-input { width: 100%; padding: 10px 12px; border-radius: 8px; border: 1.5px solid #b3c6e0; font-size: 1rem; margin-bottom: 10px; }
        .btn-test { background: #005BAC; color: #fff; border: none; border-radius: 8px; padding: 8px 22px; font-size: 1.02rem; font-weight: 700; cursor: pointer; transition: background 0.2s; }
        .btn-test:hover { background: #ff4757; }
        .test-section { margin-top: 24px; }
        .result-box { border-radius: 10px; padding: 18px 16px; font-size: 1.1rem; font-weight: 700; margin-bottom: 18px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); }
        .result-danger { background: #fff0f0; color: #ff4757; border-left: 6px solid #ff4757; }
        .result-safe { background: #f0fff0; color: #00cc00; border-left: 6px solid #00cc00; }
    </style>
</head>
<body>
    <h1 style="text-align:center;margin-top:32px;font-size:2.1rem;font-weight:800;color:#ff4757;">🛡️ 통합 보안 관리</h1>
    <div class="security-summary">
        <div class="security-summary-card">
            <div class="num"><?= $stats['today_events'] ?></div>
            <div class="label">오늘 보안 이벤트</div>
        </div>
        <div class="security-summary-card">
            <div class="num"><?= $stats['week_events'] ?></div>
            <div class="label">이번 주 보안 이벤트</div>
        </div>
        <div class="security-summary-card">
            <div class="num"><?= $stats['high_impact'] ?></div>
            <div class="label">높은 위험도 이벤트</div>
        </div>
        <div class="security-summary-card">
            <div class="num"><?= count($top_attackers) ?></div>
            <div class="label">활성 공격자 IP</div>
        </div>
        <div class="security-summary-card">
            <div class="num"><?= array_sum($stats_vul) ?></div>
            <div class="label">취약점 제보</div>
        </div>
    </div>
    <div class="security-tabs">
        <button class="security-tab" id="event-tab" type="button" onclick="showTab('event')">보안 이벤트</button>
        <button class="security-tab" id="test-tab" type="button" onclick="showTab('test')">보안테스트</button>
        <button class="security-tab" id="vul-tab" type="button" onclick="showTab('vul')">취약점 관리</button>
    </div>
    <!-- 보안 이벤트 탭 -->
    <div class="security-tab-content" id="event-content" style="display:none;">
        <div style="display: flex; gap: 24px; flex-wrap: wrap; margin-bottom: 30px;">
            <div class="chart-container" style="flex:1 1 320px; min-width:260px; max-width:600px;">
                <div class="chart-title">📊 공격 유형별 분포</div>
                <canvas id="attackTypeChart" width="400" height="200"></canvas>
            </div>
            <div class="chart-container" style="flex:1 1 320px; min-width:260px; max-width:600px;">
                <div class="chart-title">⏰ 시간대별 보안 이벤트 (최근 24시간)</div>
                <canvas id="hourlyChart" width="400" height="200"></canvas>
            </div>
        </div>
        <div class="events-list">
            <h2>📋 최근 보안 이벤트 (클릭하여 상세보기)</h2>
            <?php if (empty($recent_events)): ?>
                <p>최근 보안 이벤트가 없습니다.</p>
            <?php else: ?>
                <?php foreach ($recent_events as $index => $event): ?>
                <div class="event-item" onclick="showEventDetail(<?= $index ?>)">
                    <div class="event-time"><?= $event['created_at'] ?></div>
                    <div>
                        <strong><?= htmlspecialchars($event['username']) ?></strong>
                        <?php
                        $impact = 0;
                        if (preg_match('/임팩트:\s*(\d+)/', $event['log_message'], $matches)) {
                            $impact = (int)$matches[1];
                        }
                        $event_class = $impact >= 20 ? 'event-type-danger' : ($impact >= 10 ? 'event-type-warning' : 'event-type-info');
                        ?>
                        <span class="event-type <?= $event_class ?>">
                            임팩트: <?= $impact ?>
                        </span>
                    </div>
                    <div style="margin-top: 5px; font-size: 14px; color: #666;">
                        <?= htmlspecialchars(substr($event['log_message'], 0, 100)) ?>...
                    </div>
                    <div style="margin-top: 5px; font-size: 12px; color: #999;">
                        IP: <?= htmlspecialchars($event['ip_address']) ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <!-- 이벤트 상세 모달 -->
        <div id="eventModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title">🔍 보안 이벤트 상세 정보</h3>
                    <span class="close" onclick="closeEventModal()">&times;</span>
                </div>
                <div id="eventDetailContent"></div>
            </div>
        </div>
        <div class="chart-container">
            <h2>🎯 상위 공격자 IP</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <?php foreach ($top_attackers as $attacker): ?>
                <div style="background: #f8f9fa; padding: 15px; border-radius: 4px; text-align: center;">
                    <div style="font-weight: 600; color: #333;"> <?= htmlspecialchars($attacker['ip_address']) ?> </div>
                    <div style="color: #666; margin-top: 5px;"> <?= $attacker['count'] ?>회 시도</div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <!-- 보안테스트 탭 (CTF 스타일) -->
    <div class="security-tab-content" id="test-content" style="display:none;">
        <div class="ctf-challenge">
            <div class="challenge-title">🎯 CTF Challenge</div>
            <div class="challenge-description">
                다양한 공격 벡터를 테스트해보세요. 각각 다른 보안 취약점을 시뮬레이션합니다.
            </div>
            <div class="challenge-hint">
                <strong>💡 힌트:</strong> XSS, SQL Injection, 파일 업로드, 인증 우회, 정보 노출 등 다양한 페이로드를 시도해보세요.
            </div>
        </div>
        <div class="test-grid">
            <div class="test-card">
                <h3>🔍 XSS 테스트</h3>
                <p>Cross-Site Scripting 공격을 시뮬레이션합니다.</p>
                <form method="POST">
                    <input type="hidden" name="test_type" value="XSS">
                    <input type="text" name="test_input" class="test-input" placeholder="<script>alert('XSS')</script>" value="<script>alert('XSS')</script>">
                    <button type="submit" name="run_test" class="btn-test">테스트 실행</button>
                </form>
            </div>
            <div class="test-card">
                <h3>💉 SQL Injection 테스트</h3>
                <p>SQL Injection 공격을 시뮬레이션합니다.</p>
                <form method="POST">
                    <input type="hidden" name="test_type" value="SQL Injection">
                    <input type="text" name="test_input" class="test-input" placeholder="' OR 1=1 --" value="' OR 1=1 --">
                    <button type="submit" name="run_test" class="btn-test">테스트 실행</button>
                </form>
            </div>
            <div class="test-card">
                <h3>📁 파일 업로드 테스트</h3>
                <p>악성 파일 업로드 공격을 시뮬레이션합니다.</p>
                <form method="POST">
                    <input type="hidden" name="test_type" value="File Upload">
                    <input type="text" name="test_input" class="test-input" placeholder="shell.php" value="shell.php">
                    <button type="submit" name="run_test" class="btn-test">테스트 실행</button>
                </form>
            </div>
            <div class="test-card">
                <h3>🔐 인증 우회 테스트</h3>
                <p>인증 시스템 우회 공격을 시뮬레이션합니다.</p>
                <form method="POST">
                    <input type="hidden" name="test_type" value="Auth Bypass">
                    <input type="text" name="test_input" class="test-input" placeholder="admin'--" value="admin'--">
                    <button type="submit" name="run_test" class="btn-test">테스트 실행</button>
                </form>
            </div>
            <div class="test-card">
                <h3>📊 정보 노출 테스트</h3>
                <p>정보 노출 취약점을 시뮬레이션합니다.</p>
                <form method="POST">
                    <input type="hidden" name="test_type" value="Info Disclosure">
                    <input type="text" name="test_input" class="test-input" placeholder="../../../etc/passwd" value="../../../etc/passwd">
                    <button type="submit" name="run_test" class="btn-test">테스트 실행</button>
                </form>
            </div>
            <div class="test-card">
                <h3>🎯 커스텀 테스트</h3>
                <p>직접 입력한 페이로드를 테스트합니다.</p>
                <form method="POST">
                    <input type="hidden" name="test_type" value="Custom">
                    <input type="text" name="test_input" class="test-input" placeholder="직접 입력하세요">
                    <button type="submit" name="run_test" class="btn-test">테스트 실행</button>
                </form>
            </div>
        </div>
        <?php if ($test_result !== null): ?>
        <div class="test-section">
            <h2>📋 테스트 결과</h2>
            <div class="result-box <?= strpos($test_result, '감지됨') !== false ? 'result-danger' : 'result-safe' ?>">
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
    </div>
    <!-- 취약점 관리 탭 (관리자용 관리 중심) -->
    <div class="security-tab-content" id="vul-content" style="display:none;">
        <div class="search-bar">
            <input type="text" id="vulSearch" placeholder="제목/제보자/설명 검색...">
            <select id="vulStatusFilter">
                <option value="">상태 전체</option>
                <option value="pending">검토 대기</option>
                <option value="investigating">조사 중</option>
                <option value="fixed">수정 완료</option>
                <option value="rejected">거절</option>
            </select>
            <button type="button" onclick="exportVulCSV()" class="btn-csv">CSV 내보내기</button>
        </div>
        <div class="reports-list">
            <table class="report-table" id="vulTable">
                <thead>
                    <tr>
                        <th>제목</th>
                        <th>상태</th>
                        <th>심각도</th>
                        <th>제보자</th>
                        <th>제보일</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recent_reports as $i => $report): ?>
                    <tr onclick="showVulDetail(<?= $i ?>)">
                        <td><?= htmlspecialchars($report['title']) ?></td>
                        <td><span class="status-badge status-<?= $report['status'] ?>"><?= ucfirst($report['status']) ?></span></td>
                        <td><span class="severity-badge severity-<?= $report['severity'] ?>"><?= ucfirst($report['severity']) ?></span></td>
                        <td><?= htmlspecialchars($report['reported_by']) ?></td>
                        <td><?= $report['created_at'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <!-- 상세 모달 -->
        <div id="vulModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title">🔍 취약점 상세 정보</h3>
                    <span class="close" onclick="closeVulModal()">&times;</span>
                </div>
                <div id="vulDetailContent"></div>
            </div>
        </div>
    </div>
    <script>
        function showTab(tab) {
            ['event','test','vul'].forEach(function(t) {
                document.getElementById(t+'-tab').classList.remove('active');
                document.getElementById(t+'-content').style.display = 'none';
            });
            document.getElementById(tab+'-tab').classList.add('active');
            document.getElementById(tab+'-content').style.display = 'block';
        }
        window.onload = function() { showTab('event'); };
        // 보안 이벤트 상세 모달
        const events = <?= json_encode($recent_events) ?>;
        function showEventDetail(index) {
            const event = events[index];
            const modal = document.getElementById('eventModal');
            const content = document.getElementById('eventDetailContent');
            let impact = 0;
            if (event.log_message.includes('임팩트:')) {
                const match = event.log_message.match(/임팩트:\s*(\d+)/);
                if (match) impact = parseInt(match[1]);
            }
            let impactLevel = '낮음';
            let impactColor = '#0099ff';
            let impactIcon = '🟦';
            if (impact >= 20) {
                impactLevel = '높음';
                impactColor = '#ff4444';
                impactIcon = '🟥';
            } else if (impact >= 10) {
                impactLevel = '중간';
                impactColor = '#ff8800';
                impactIcon = '🟧';
            }
            // 유형 추출
            let type = '기타';
            if (event.log_message.match(/xss|script|alert/i)) type = 'XSS';
            else if (event.log_message.match(/sql|or 1=1|union/i)) type = 'SQL Injection';
            else if (event.log_message.match(/파일|upload/i)) type = 'File Upload';
            else if (event.log_message.match(/인증|auth/i)) type = 'Auth Bypass';
            else if (event.log_message.match(/eval|exec/i)) type = 'Code Injection';
            else if (event.log_message.match(/csrf/i)) type = 'CSRF';
            else if (event.log_message.match(/lfi|include/i)) type = 'LFI/RFI';
            // 카드형 요약 + 구분선 + 정보
            content.innerHTML = `
                <div style="display:flex;gap:18px;flex-wrap:wrap;justify-content:center;margin-bottom:18px;">
                    <div style="background:${impactColor};color:#fff;border-radius:10px;padding:18px 22px;min-width:120px;text-align:center;box-shadow:0 2px 12px rgba(0,0,0,0.08);font-size:1.2rem;">
                        <div style='font-size:2.2rem;'>${impactIcon}</div>
                        <div style='font-weight:700;'>임팩트</div>
                        <div style='font-size:1.5rem;font-weight:900;'>${impact}</div>
                        <div style='font-size:1rem;'>${impactLevel}</div>
                    </div>
                    <div style="background:#f8f9fa;border-radius:10px;padding:18px 22px;min-width:120px;text-align:center;box-shadow:0 2px 12px rgba(0,0,0,0.04);">
                        <div style='font-size:1.7rem;'>🔖</div>
                        <div style='font-weight:700;'>유형</div>
                        <div style='font-size:1.1rem;font-weight:900;color:#005BAC;'>${type}</div>
                    </div>
                    <div style="background:#f8f9fa;border-radius:10px;padding:18px 22px;min-width:120px;text-align:center;box-shadow:0 2px 12px rgba(0,0,0,0.04);">
                        <div style='font-size:1.7rem;'>⏰</div>
                        <div style='font-weight:700;'>시간</div>
                        <div style='font-size:1.1rem;font-weight:900;'>${event.created_at}</div>
                    </div>
                    <div style="background:#f8f9fa;border-radius:10px;padding:18px 22px;min-width:120px;text-align:center;box-shadow:0 2px 12px rgba(0,0,0,0.04);">
                        <div style='font-size:1.7rem;'>👤</div>
                        <div style='font-weight:700;'>사용자</div>
                        <div style='font-size:1.1rem;font-weight:900;'>${event.username}</div>
                    </div>
                    <div style="background:#f8f9fa;border-radius:10px;padding:18px 22px;min-width:120px;text-align:center;box-shadow:0 2px 12px rgba(0,0,0,0.04);">
                        <div style='font-size:1.7rem;'>🌐</div>
                        <div style='font-weight:700;'>IP</div>
                        <div style='font-size:1.1rem;font-weight:900;'>${event.ip_address}</div>
                    </div>
                </div>
                <hr style='margin:18px 0 18px 0;border:0;border-top:2px solid #eee;'>
                <div class="modal-detail">
                    <h4>📝 로그 메시지</h4>
                    <pre style='background:#fffbe6;border-left:4px solid ${impactColor};'>${event.log_message}</pre>
                </div>
                <div class="modal-detail">
                    <h4>🔍 분석 정보</h4>
                    <ul style='margin:0 0 0 18px;'>
                        <li>이벤트 유형: <b>${event.action}</b></li>
                        <li>로그 ID: <b>${event.id}</b></li>
                        <li>위험도 점수는 <b style='color:${impactColor};'>${impact}점 (${impactLevel})</b></li>
                        <li>IP 주소 <b>${event.ip_address}</b>에서 발생</li>
                        <li>사용자 <b>${event.username}</b>의 활동</li>
                    </ul>
                </div>
            `;
            modal.style.display = 'flex';
        }
        function closeEventModal() {
            document.getElementById('eventModal').style.display = 'none';
        }
        // 취약점 상세 모달
        const vulReports = <?= json_encode($recent_reports) ?>;
        function showVulDetail(index) {
            const report = vulReports[index];
            const modal = document.getElementById('vulModal');
            const content = document.getElementById('vulDetailContent');
            content.innerHTML = `
                <div class="modal-detail">
                    <h4>제목</h4>
                    <p>${report.title}</p>
                    <h4>상태/심각도</h4>
                    <p><span class="status-badge status-${report.status}">${report.status}</span> <span class="severity-badge severity-${report.severity}">${report.severity}</span></p>
                    <h4>제보자/제보일</h4>
                    <p>${report.reported_by} / ${report.created_at}</p>
                    <h4>분류</h4>
                    <p>${report.category}</p>
                    <h4>설명</h4>
                    <pre>${report.description}</pre>
                    ${report.reproduction_steps ? `<h4>재현 단계</h4><pre>${report.reproduction_steps}</pre>` : ''}
                    ${report.impact ? `<h4>영향도</h4><pre>${report.impact}</pre>` : ''}
                    ${report.admin_notes ? `<h4>관리자 메모</h4><pre>${report.admin_notes}</pre>` : ''}
                </div>
                <form method='POST' style='margin-top:10px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;'>
                    <input type='hidden' name='report_id' value='${report.id}'>
                    <select name='status' style='padding:6px 10px;border-radius:6px;'>
                        <option value='pending' ${report.status==='pending'?'selected':''}>검토 대기</option>
                        <option value='investigating' ${report.status==='investigating'?'selected':''}>조사 중</option>
                        <option value='fixed' ${report.status==='fixed'?'selected':''}>수정 완료</option>
                        <option value='rejected' ${report.status==='rejected'?'selected':''}>거절</option>
                    </select>
                    <input type='text' name='admin_notes' value='${report.admin_notes??''}' placeholder='관리자 메모' style='padding:6px 10px;border-radius:6px;min-width:120px;'>
                    <button type='submit' name='update_report' class='btn-action'>상태/메모 저장</button>
                    <button type='button' class='btn-csv' onclick='copyVulDetail(${index})'>상세 복사</button>
                </form>
            `;
            modal.style.display = 'flex';
        }
        function closeVulModal() {
            document.getElementById('vulModal').style.display = 'none';
        }
        // 외부 클릭/ESC로 모달 닫기
        window.onclick = function(event) {
            const eventModal = document.getElementById('eventModal');
            const vulModal = document.getElementById('vulModal');
            if (event.target == eventModal) eventModal.style.display = 'none';
            if (event.target == vulModal) vulModal.style.display = 'none';
        }
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeEventModal();
                closeVulModal();
            }
        });
        // 취약점 검색/필터/정렬/CSV
        document.getElementById('vulSearch').oninput = filterVulTable;
        document.getElementById('vulStatusFilter').onchange = filterVulTable;
        function filterVulTable() {
            const search = document.getElementById('vulSearch').value.toLowerCase();
            const status = document.getElementById('vulStatusFilter').value;
            const rows = document.querySelectorAll('#vulTable tbody tr');
            rows.forEach(row => {
                const tds = row.querySelectorAll('td');
                const text = Array.from(tds).map(td=>td.textContent.toLowerCase()).join(' ');
                const statusText = tds[1].textContent.toLowerCase();
                row.style.display = (text.includes(search) && (!status || statusText.includes(status))) ? '' : 'none';
            });
        }
        function exportVulCSV() {
            let csv = '제목,상태,심각도,제보자,제보일\n';
            document.querySelectorAll('#vulTable tbody tr').forEach(row => {
                if(row.style.display==='none') return;
                const tds = row.querySelectorAll('td');
                csv += Array.from(tds).map(td=>`"${td.textContent.replace(/"/g,'""')}"`).join(',')+'\n';
            });
            const blob = new Blob([csv], {type:'text/csv'});
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url; a.download = 'vulnerability_reports.csv';
            document.body.appendChild(a); a.click(); document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }
        function copyVulDetail(index) {
            const report = vulReports[index];
            let detail = `제목: ${report.title}\n상태: ${report.status}\n심각도: ${report.severity}\n제보자: ${report.reported_by}\n제보일: ${report.created_at}\n분류: ${report.category}\n설명: ${report.description}`;
            if(report.reproduction_steps) detail += `\n재현 단계: ${report.reproduction_steps}`;
            if(report.impact) detail += `\n영향도: ${report.impact}`;
            if(report.admin_notes) detail += `\n관리자 메모: ${report.admin_notes}`;
            navigator.clipboard.writeText(detail);
            alert('상세 정보가 복사되었습니다!');
        }
        // 차트
        const attackTypeCtx = document.getElementById('attackTypeChart').getContext('2d');
        new Chart(attackTypeCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_column($attack_types, 'attack_type')) ?>,
                datasets: [{
                    data: <?= json_encode(array_column($attack_types, 'count')) ?>,
                    backgroundColor: [
                        '#ff4444',
                        '#ff8800',
                        '#ffcc00',
                        '#00cc00',
                        '#0099ff'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
        const hourlyData = <?= json_encode($hourly_events) ?>;
        const hours = Array.from({length: 24}, (_, i) => i);
        const counts = hours.map(hour => {
            const found = hourlyData.find(item => item.hour == hour);
            return found ? found.count : 0;
        });
        new Chart(hourlyCtx, {
            type: 'line',
            data: {
                labels: hours.map(h => h + '시'),
                datasets: [{
                    label: '보안 이벤트',
                    data: counts,
                    borderColor: '#005BAC',
                    backgroundColor: 'rgba(0, 91, 172, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html> 