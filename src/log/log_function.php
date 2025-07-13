<?php


function writeLog($pdo, $username, $action, $result, $extra = null) {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $msg = "[$action][$result]" . ($extra ? " $extra" : "");
        $stmt = $pdo->prepare("INSERT INTO logs (username, ip_address, log_message, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$username, $ip, $msg]);

        // --- 알림 생성 로직 추가 ---
        $notify_types = [
            '고장접수' => ['fault', '새 고장 접수', 'faults.php'],
            '고장수정' => ['fault', '고장 내용 수정', 'faults.php'],
            '고장삭제' => ['fault', '고장 내용 삭제', 'faults.php'],
            '공격감지' => ['security', '보안 이벤트 감지', 'logs.php'],
            'PHPIDS' => ['security', '보안 이벤트 감지', 'logs.php'],
            '점검시작' => ['maintenance', '시스템 점검 시작', 'index.php'],
            '점검종료' => ['maintenance', '시스템 점검 종료', 'index.php'],
            '공지등록' => ['notice', '새 공지 등록', 'index.php'],
            '공지수정' => ['notice', '공지 수정', 'index.php'],
            '공지삭제' => ['notice', '공지 삭제', 'index.php'],
        ];
        if (isset($notify_types[$action])) {
            list($type, $title, $url) = $notify_types[$action];
            $notify_msg = $title . ' - ' . ($extra ? $extra : $msg);
            $stmt2 = $pdo->prepare("INSERT INTO notifications (type, message, url, target) VALUES (?, ?, ?, 'admin')");
            $stmt2->execute([$type, $notify_msg, $url]);
        }
    } catch (Exception $e) {
        // 로그 기록 실패 시 전체 동작에 영향 주지 않음
        // 필요시 error_log($e->getMessage()); 등으로 운영 로그에 남길 수 있음
    }
}

/**
 * PHPIDS 보안 이벤트 결과를 요약 메시지로 변환
 * @param object $result PHPIDS 결과 객체
 * @param string $context 이벤트 발생 컨텍스트 (예: '제어', '고장접수', '회원가입')
 * @param array $userInput 사용자가 입력한 원본 데이터
 * @return string 요약된 보안 이벤트 메시지
 */
function format_phpids_event($result, $context = '', $userInput = []) {
    $lines = [];
    $lines[] = "[PHPIDS] 보안 이벤트 감지";
    $lines[] = "- 시각: " . date('Y-m-d H:i:s');
    $lines[] = "- IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    
    // 사용자 정보 추가
    if (isset($_SESSION['admin'])) {
        $lines[] = "- 사용자: " . $_SESSION['admin'] . " (관리자)";
    } elseif (isset($_SESSION['guest'])) {
        $lines[] = "- 사용자: " . $_SESSION['guest'] . " (일반사용자)";
    } else {
        $lines[] = "- 사용자: 미로그인";
    }
    
    // 컨텍스트 정보 추가
    if ($context) {
        $lines[] = "- 발생위치: " . $context;
    }
    
    // 사용자 입력값 요약 추가
    if (!empty($userInput)) {
        $inputSummary = [];
        foreach ($userInput as $key => $value) {
            if (is_string($value) && !empty($value)) {
                $inputSummary[] = "$key=" . (strlen($value) > 50 ? substr($value, 0, 50) . "..." : $value);
            }
        }
        if (!empty($inputSummary)) {
            $lines[] = "- 입력값: " . implode(", ", $inputSummary);
        }
    }
    
    $lines[] = "- 임팩트 점수: " . $result->getImpact();
    
    // 각 이벤트 상세 정보
    $events = $result->getEvents();
    foreach ($events as $event) {
        $lines[] = "- 감지 파라미터: " . $event->getValue();
        $lines[] = "- 필터/룰: " . $event->getName() . " (ID: " . $event->getFilterId() . ")";
        $lines[] = "- 탐지 기준: " . $event->getDescription();
        $lines[] = "- 위험도: " . $event->getImpact();
    }
    
    return implode("\n", $lines);
}


?>
