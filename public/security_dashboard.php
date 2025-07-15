<?php
session_start();
require_once '../src/db/db.php';
require_once '../src/log/log_function.php';

// 로그인 체크
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['admin'] ?? '';

// 보안 통계 조회
$stats = [];

// 오늘 보안 이벤트 수
$stmt = $pdo->prepare("SELECT COUNT(*) FROM logs WHERE (log_message LIKE '%공격감지%' OR log_message LIKE '%PHPIDS%') AND DATE(created_at) = :today");
$stmt->execute(['today' => date('Y-m-d')]);
$stats['today_events'] = $stmt->fetchColumn();

// 이번 주 보안 이벤트 수
$stmt = $pdo->prepare("SELECT COUNT(*) FROM logs WHERE (log_message LIKE '%공격감지%' OR log_message LIKE '%PHPIDS%') AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$stmt->execute();
$stats['week_events'] = $stmt->fetchColumn();

// 높은 임팩트 이벤트 수 (임팩트 20 이상)
$stmt = $pdo->query("SELECT COUNT(*) FROM logs WHERE (log_message LIKE '%공격감지%' OR log_message LIKE '%PHPIDS%') AND log_message LIKE '%임팩트: 2%'");
$stats['high_impact'] = $stmt->fetchColumn();

// 공격 유형별 통계 (개선된 분류)
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

// 최근 보안 이벤트
$stmt = $pdo->prepare("SELECT * FROM logs WHERE (log_message LIKE '%공격감지%' OR log_message LIKE '%PHPIDS%') ORDER BY created_at DESC LIMIT 10");
$stmt->execute();
$recent_events = $stmt->fetchAll();

// 시간대별 보안 이벤트 (최근 24시간)
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

// IP별 공격 시도
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
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>보안 대시보드</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .dashboard-container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 12px rgba(0,0,0,0.1); text-align: center; }
        .stat-number { font-size: 2.5rem; font-weight: 700; color: #005BAC; }
        .stat-label { color: #666; margin-top: 5px; font-size: 14px; }
        .chart-container { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 12px rgba(0,0,0,0.1); margin-bottom: 30px; }
        .chart-title { font-size: 1.2rem; font-weight: 600; color: #333; margin-bottom: 20px; }
        .events-list { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 12px rgba(0,0,0,0.1); }
        .event-item { border-bottom: 1px solid #eee; padding: 15px 0; cursor: pointer; transition: background-color 0.2s; }
        .event-item:hover { background-color: #f8f9fa; }
        .event-item:last-child { border-bottom: none; }
        .event-time { color: #666; font-size: 12px; }
        .event-type { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; margin-left: 10px; }
        .event-type-danger { background: #ff4444; color: #fff; }
        .event-type-warning { background: #ff8800; color: #fff; }
        .event-type-info { background: #0099ff; color: #fff; }
        .alert-banner { background: linear-gradient(135deg, #ff4444 0%, #cc0000 100%); color: #fff; padding: 20px; border-radius: 8px; margin-bottom: 30px; text-align: center; }
        .alert-banner h2 { margin: 0; font-size: 1.5rem; }
        .alert-banner p { margin: 10px 0 0 0; opacity: 0.9; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: #fff; margin: 5% auto; padding: 30px; border-radius: 8px; width: 80%; max-width: 800px; max-height: 80vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .modal-title { font-size: 1.5rem; font-weight: 700; color: #333; }
        .close { color: #aaa; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close:hover { color: #000; }
        .event-detail { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 15px; }
        .event-detail h4 { margin: 0 0 10px 0; color: #333; }
        .event-detail pre { background: #fff; padding: 15px; border-radius: 4px; overflow-x: auto; white-space: pre-wrap; font-size: 14px; line-height: 1.5; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <h1>🛡️ 보안 대시보드</h1>
        <p>실시간 보안 이벤트 모니터링 및 분석</p>
        
        <?php if ($stats['today_events'] > 10): ?>
        <div class="alert-banner">
            <h2>🚨 보안 경고</h2>
            <p>오늘 <?= $stats['today_events'] ?>건의 보안 이벤트가 발생했습니다. 즉시 조사가 필요합니다.</p>
        </div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $stats['today_events'] ?></div>
                <div class="stat-label">오늘 보안 이벤트</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['week_events'] ?></div>
                <div class="stat-label">이번 주 보안 이벤트</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['high_impact'] ?></div>
                <div class="stat-label">높은 위험도 이벤트</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= count($top_attackers) ?></div>
                <div class="stat-label">활성 공격자 IP</div>
            </div>
        </div>
        
        <!-- 차트 2개를 한 줄에 반씩 배치 -->
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
        
        <div class="chart-container">
            <h2>🎯 상위 공격자 IP</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <?php foreach ($top_attackers as $attacker): ?>
                <div style="background: #f8f9fa; padding: 15px; border-radius: 4px; text-align: center;">
                    <div style="font-weight: 600; color: #333;"><?= htmlspecialchars($attacker['ip_address']) ?></div>
                    <div style="color: #666; margin-top: 5px;"><?= $attacker['count'] ?>회 시도</div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <!-- 이벤트 상세 모달 -->
    <div id="eventModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">🔍 보안 이벤트 상세 정보</h3>
                <span class="close" onclick="closeEventModal()">&times;</span>
            </div>
            <div id="eventDetailContent">
                <!-- 상세 내용이 여기에 로드됩니다 -->
            </div>
        </div>
    </div>
    
    <script>
        // 이벤트 데이터를 JavaScript로 전달
        const events = <?= json_encode($recent_events) ?>;
        
        function showEventDetail(index) {
            const event = events[index];
            const modal = document.getElementById('eventModal');
            const content = document.getElementById('eventDetailContent');
            
            // 임팩트 추출
            let impact = 0;
            if (event.log_message.includes('임팩트:')) {
                const match = event.log_message.match(/임팩트:\s*(\d+)/);
                if (match) impact = parseInt(match[1]);
            }
            
            // 위험도 레벨 결정
            let impactLevel = '낮음';
            let impactColor = '#0099ff';
            if (impact >= 20) {
                impactLevel = '높음';
                impactColor = '#ff4444';
            } else if (impact >= 10) {
                impactLevel = '중간';
                impactColor = '#ff8800';
            }
            
            content.innerHTML = `
                <div class="event-detail">
                    <h4>📅 기본 정보</h4>
                    <p><strong>발생 시간:</strong> ${event.created_at}</p>
                    <p><strong>사용자:</strong> ${event.username}</p>
                    <p><strong>IP 주소:</strong> ${event.ip_address}</p>
                    <p><strong>위험도:</strong> <span style="color: ${impactColor}; font-weight: bold;">${impactLevel} (${impact}점)</span></p>
                </div>
                
                <div class="event-detail">
                    <h4>📝 로그 메시지</h4>
                    <pre>${event.log_message}</pre>
                </div>
                
                <div class="event-detail">
                    <h4>🔍 분석 정보</h4>
                    <p><strong>이벤트 유형:</strong> ${event.action}</p>
                    <p><strong>로그 ID:</strong> ${event.id}</p>
                    <p><strong>상세 분석:</strong></p>
                    <ul>
                        <li>이 이벤트는 PHPIDS에 의해 감지되었습니다.</li>
                        <li>위험도 점수는 ${impact}점으로 ${impactLevel} 수준입니다.</li>
                        <li>IP 주소 ${event.ip_address}에서 발생했습니다.</li>
                        <li>사용자 ${event.username}의 활동과 관련이 있습니다.</li>
                    </ul>
                </div>
            `;
            
            modal.style.display = 'block';
        }
        
        function closeEventModal() {
            document.getElementById('eventModal').style.display = 'none';
        }
        
        // 모달 외부 클릭 시 닫기
        window.onclick = function(event) {
            const modal = document.getElementById('eventModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
        
        // 공격 유형별 차트
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
        
        // 시간대별 차트
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