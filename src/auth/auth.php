<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/../log/log_function.php';

$username = $_POST['username'];
$password = $_POST['password'];

// 워게임용: 기본적인 필터링 (우회 가능하도록 설계)
function basicFilter($input) {
    // 공백 제거
    $input = str_replace(' ', '', $input);
    // 대소문자 변환
    $input = strtolower($input);
    // 기본적인 SQL 키워드 차단 (하지만 우회 가능)
    $blocked = ['union', 'select', 'from', 'where', 'or', 'and', 'drop', 'delete', 'insert', 'update'];
    foreach ($blocked as $word) {
        if (strpos($input, $word) !== false) {
            return false;
        }
    }
    return $input;
}

// 사용자명 필터링
$filtered_username = basicFilter($username);
if ($filtered_username === false) {
    echo "<script>alert('사용자명에 허용되지 않는 문자가 포함되어 있습니다.'); history.back();</script>";
    exit();
}

// 비밀번호 필터링 (더 엄격하게)
$filtered_password = basicFilter($password);
if ($filtered_password === false) {
    echo "<script>alert('비밀번호에 허용되지 않는 문자가 포함되어 있습니다.'); history.back();</script>";
    exit();
}

// 워게임용: SQL 인젝션 취약점이 있는 쿼리 (필터링 우회 필요)
// 하지만 로그인이 되도록 사용자명으로만 먼저 조회
$sql = "SELECT * FROM users WHERE username = '$filtered_username' AND is_active = 1";
$result = $pdo->query($sql);
$user = $result->fetch();

if ($user && password_verify($password, $user['password'])) {
    // 역할에 따라 세션 설정
    if ($user['role'] === 'admin') {
        $_SESSION['admin'] = $user['username'];
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = 'admin';
        writeLog($pdo, $username, "로그인", "성공", "관리자 로그인 성공 - 계정: $username");
    } else {
        $_SESSION['guest'] = $user['username'];
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = 'guest';
    writeLog($pdo, $username, "로그인", "성공", "게스트 로그인 성공 - 계정: $username");
    }
    
    header("Location: index.php");
    exit();
}

// 로그인 실패
writeLog($pdo, $username, "로그인", "실패", "시도계정: $username, 원인: 잘못된 비밀번호 또는 존재하지 않는 계정");
echo "<script>alert('로그인 실패: 아이디 또는 비밀번호가 잘못되었습니다.'); history.back();</script>";
exit();
?>
