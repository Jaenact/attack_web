<?php
session_start();
require_once '../db/db.php';
require_once '../log/log_function.php';

if (!isset($_SESSION['admin']) && !isset($_SESSION['guest'])) {
    http_response_code(403);
    echo '로그인이 필요합니다.';
    exit();
}

$userType = isset($_SESSION['admin']) ? 'admin' : 'guest';
$username = $_SESSION['admin'] ?? $_SESSION['guest'];

// 기존 정보 조회
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username");
$stmt->execute(['username' => $username]);
$user = $stmt->fetch();

$name = $_POST['name'] ?? '';
$phone = $_POST['phone'] ?? '';
$profile_img = $user['profile_img'] ?? null;

// 프로필 이미지 업로드 처리
if (isset($_FILES['profile_img']) && $_FILES['profile_img']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = 'uploads/profile/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
    $origin_name = basename($_FILES['profile_img']['name']);
    $ext = pathinfo($origin_name, PATHINFO_EXTENSION);
    $new_name = uniqid('profile_') . '.' . $ext;
    $target = $upload_dir . $new_name;
    if (move_uploaded_file($_FILES['profile_img']['tmp_name'], $target)) {
        // 기존 이미지 삭제
        if ($profile_img && file_exists($upload_dir . $profile_img)) {
            unlink($upload_dir . $profile_img);
        }
        $profile_img = $new_name;
    }
}

// DB 업데이트
$stmt = $pdo->prepare("UPDATE users SET name = :name, phone = :phone, profile_img = :profile_img WHERE username = :username");
$stmt->execute([
    'name' => $name,
    'phone' => $phone,
    'profile_img' => $profile_img,
    'username' => $username
]);

// user_id 파라미터가 있을 때 해당 사용자 정보 조회/수정 (관리자만)
if (isset($_GET['get']) && isset($_GET['user_id']) && isset($_SESSION['admin'])) {
    $user_id = $_GET['user_id'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute(['id' => $user_id]);
    $user = $stmt->fetch();
    header('Content-Type: application/json');
    echo json_encode([
        'name' => $user['name'] ?? '',
        'phone' => $user['phone'] ?? '',
        'profile_img' => $user['profile_img'] ?? ''
    ]);
    exit();
}

// POST로 user_id가 있으면 해당 사용자 정보 수정 (관리자만)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id']) && isset($_SESSION['admin'])) {
    $user_id = $_POST['user_id'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute(['id' => $user_id]);
    $user = $stmt->fetch();
    $name = $_POST['name'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $profile_img = $user['profile_img'] ?? null;
    if (isset($_FILES['profile_img']) && $_FILES['profile_img']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/profile/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $origin_name = basename($_FILES['profile_img']['name']);
        $ext = pathinfo($origin_name, PATHINFO_EXTENSION);
        $new_name = uniqid('profile_') . '.' . $ext;
        $target = $upload_dir . $new_name;
        if (move_uploaded_file($_FILES['profile_img']['tmp_name'], $target)) {
            if ($profile_img && file_exists($upload_dir . $profile_img)) {
                unlink($upload_dir . $profile_img);
            }
            $profile_img = $new_name;
        }
    }
    $stmt = $pdo->prepare("UPDATE users SET name = :name, phone = :phone, profile_img = :profile_img WHERE id = :id");
    $stmt->execute([
        'name' => $name,
        'phone' => $phone,
        'profile_img' => $profile_img,
        'id' => $user_id
    ]);
    header('Location: user_management.php');
    exit();
}

if (isset($_POST['change_pw'])) {
    $current_pw = $_POST['current_pw'] ?? '';
    $new_pw = $_POST['new_pw'] ?? '';
    // 1. 현재 비밀번호 확인
    $stmt = $pdo->prepare("SELECT password FROM users WHERE username = :username");
    $stmt->execute(['username' => $username]);
    $row = $stmt->fetch();
    if (!$row || !password_verify($current_pw, $row['password'])) {
        echo json_encode(['success'=>false, 'msg'=>'현재 비밀번호가 일치하지 않습니다.']);
        exit();
    }
    // 2. 새 비밀번호 해시 저장
    $hashed_pw = password_hash($new_pw, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password = :pw WHERE username = :username");
    $stmt->execute(['pw'=>$hashed_pw, 'username'=>$username]);
    echo json_encode(['success'=>true, 'msg'=>'비밀번호가 성공적으로 변경되었습니다.']);
    exit();
}

// 프로필/비밀번호 변경 시도 시
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_SESSION['admin'] ?? $_SESSION['guest'] ?? '';
    writeLog($pdo, $username, '프로필수정', '시도');
    // ... 기존 프로필/비밀번호 변경 처리 ...
    if ($수정_성공) {
        writeLog($pdo, $username, '프로필수정', '성공');
    } else {
        writeLog($pdo, $username, '프로필수정', '실패', $errorMsg);
    }
}

header('Location: index.php');
exit(); 