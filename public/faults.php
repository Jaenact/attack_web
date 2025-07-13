<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
if (!isset($_SESSION['admin']) && !isset($_SESSION['guest'])) {
  header("Location: login.php");
  exit();
}
// require_once '../src/db/maintenance_check.php';
// maintenanceRedirectIfNeeded();
$title = '고장 관리';
$active = 'faults';

require_once '../src/db/db.php';          
require_once '../src/log/log_function.php';

// PHPIDS 라이브러리 로딩
require_once __DIR__ . '/../PHPIDS/lib/IDS/Init.php';
require_once __DIR__ . '/../PHPIDS/lib/IDS/Monitor.php';
require_once __DIR__ . '/../PHPIDS/lib/IDS/Report.php';
require_once __DIR__ . '/../PHPIDS/lib/IDS/Filter/Storage.php';
require_once __DIR__ . '/../PHPIDS/lib/IDS/Caching/CacheFactory.php';
require_once __DIR__ . '/../PHPIDS/lib/IDS/Caching/CacheInterface.php';
require_once __DIR__ . '/../PHPIDS/lib/IDS/Caching/FileCache.php';
require_once __DIR__ . '/../PHPIDS/lib/IDS/Filter.php';
require_once __DIR__ . '/../PHPIDS/lib/IDS/Event.php';
require_once __DIR__ . '/../PHPIDS/lib/IDS/Converter.php';

use IDS\Init;
use IDS\Monitor;

$upload_dir = realpath(__DIR__ . '/../uploads');
if ($upload_dir === false) {
    // uploads 폴더가 없으면 생성
    $upload_dir = __DIR__ . '/../uploads';
    mkdir($upload_dir, 0777, true);
    $upload_dir = realpath($upload_dir);
}
$upload_dir .= '/';
// echo '실제 업로드 경로: ' . $upload_dir . "<br>";

// 파일 존재 여부 확인 함수
function fileExists($filename) {
    global $upload_dir;
    return $filename && file_exists($upload_dir . $filename);
}

// 파일 삭제 함수
function deleteFile($filename) {
    global $upload_dir;
    if ($filename && file_exists($upload_dir . $filename)) {
        return unlink($upload_dir . $filename);
    }
    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['part']) && !isset($_POST['edit_id'])) {
    $part = trim($_POST['part']);        
    $status = $_POST['status'] ?? '접수';
    $manager = $_POST['manager'] ?? null;
    $filename = null;
    $original_filename = null;

    $username = $_SESSION['admin'] ?? $_SESSION['guest'] ?? '';
    writeLog($pdo, $username, '고장등록', '시도');

    // --- PHPIDS 공격 탐지: 입력값만 별도 검사 ---
    try {
        $request = [
            'POST' => [
                'part' => $part,
                'status' => $status,
                'manager' => $manager
            ]
        ];
        $init = Init::init(__DIR__ . '/../PHPIDS/lib/IDS/Config/Config.ini.php');
        $ids = new Monitor($init);
        $result = $ids->run($request);
        if (!$result->isEmpty()) {
            $impact = $result->getImpact();
            $logMessage = 'PHPIDS 고장접수 입력값 공격 감지! 임팩트: ' . $impact . ', 상세: ' . print_r($result, true);
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            $stmt = $pdo->prepare('INSERT INTO logs (username, action, log_message, ip_address) VALUES (?, ?, ?, ?)');
            $stmt->execute([$username, '공격감지', $logMessage, $ip]);
        }
    } catch (Exception $e) {
        // PHPIDS 오류 시 로그 기록
        $logMessage = 'PHPIDS 오류: ' . $e->getMessage();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $stmt = $pdo->prepare('INSERT INTO logs (username, action, log_message, ip_address) VALUES (?, ?, ?, ?)');
        $stmt->execute([$username, 'PHPIDS오류', $logMessage, $ip]);
    }
    // --- 기존 파일 업로드 및 DB 저장 로직 ---
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $tmp_name = $_FILES['file']['tmp_name'];              
        $origin_name = basename($_FILES['file']['name']);     
        $origin_name = iconv("UTF-8", "UTF-8//IGNORE", $origin_name); // 한글 파일명 깨짐 방지
        $ext = strtolower(pathinfo($origin_name, PATHINFO_EXTENSION));    
        if ($ext === 'php') {
            echo "<script>alert('PHP 파일은 업로드할 수 없습니다.'); history.back();</script>";
            exit();
        }
        $new_name = uniqid() . "." . $ext;                    

        $target_path = $upload_dir . $new_name;
        $move_result = move_uploaded_file($tmp_name, $target_path);
        if ($move_result) {
            $filename = $new_name;
            $original_filename = $origin_name; // 원본 파일명 저장
        } else {
            error_log("파일 업로드 실패: $origin_name");
            header("Location: faults.php?error=upload");
            exit();
        }
    }

    if (!empty($part)) {                                      
        // 현재 로그인한 사용자의 ID 가져오기
        $currentUser = isset($_SESSION['admin']) ? $_SESSION['admin'] : $_SESSION['guest'];
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username");
        $stmt->execute(['username' => $currentUser]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $user_id = $user ? $user['id'] : null;
        
        $stmt = $pdo->prepare("INSERT INTO faults (part, filename, original_filename, status, manager, user_id) VALUES (:part, :filename, :original_filename, :status, :manager, :user_id)"); 
        $stmt->execute(['part' => $part, 'filename' => $filename, 'original_filename' => $original_filename, 'status' => $status, 'manager' => $manager, 'user_id' => $user_id]); 

        $currentUser = isset($_SESSION['admin']) ? $_SESSION['admin'] : $_SESSION['guest'];
        
        // 자세한 로그 메시지 생성
        $logMessage = "고장 접수 - 입력내용: '$part'";
        if ($filename) {
            $logMessage .= ", 첨부파일: '$original_filename' (저장명: $filename)";
        } else {
            $logMessage .= ", 첨부파일: 없음";
        }
        
        writeLog($pdo, $currentUser, '고장접수', '성공', $logMessage);
        header("Location: faults.php");
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'], $_POST['new_part'])) {
    $id = $_POST['edit_id'];                                  
    $new_part = trim($_POST['new_part']);                     
    $new_status = $_POST['edit_status'] ?? '접수';
    $new_manager = $_POST['edit_manager'] ?? null;
    $new_filename = null;
    $new_original_filename = null;

    // 기존 파일 정보 가져오기
    $stmt = $pdo->prepare("SELECT filename, original_filename FROM faults WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $existing_fault = $stmt->fetch(PDO::FETCH_ASSOC);
    $old_filename = $existing_fault['filename'] ?? null;
    $old_original_filename = $existing_fault['original_filename'] ?? null;

    // 새 파일 업로드 처리
    if (isset($_FILES['new_file']) && $_FILES['new_file']['error'] === UPLOAD_ERR_OK) {
        $tmp_name = $_FILES['new_file']['tmp_name'];              
        $origin_name = basename($_FILES['new_file']['name']);     
        $origin_name = iconv("UTF-8", "UTF-8//IGNORE", $origin_name); // 한글 파일명 깨짐 방지
        $ext = strtolower(pathinfo($origin_name, PATHINFO_EXTENSION));    
        if ($ext === 'php') {
            echo "<script>alert('PHP 파일은 업로드할 수 없습니다.'); history.back();</script>";
            exit();
        }
        $new_name = uniqid() . "." . $ext;                    

        if (move_uploaded_file($tmp_name, $upload_dir . $new_name)) {
            $new_filename = $new_name;
            $new_original_filename = $origin_name;
            
            // 기존 파일 삭제
            if ($old_filename) {
                deleteFile($old_filename);
            }
        }
    }

    if (!empty($new_part)) {                                  
        $update_filename = $new_filename !== null ? $new_filename : $old_filename;
        $update_original_filename = $new_original_filename !== null ? $new_original_filename : $old_original_filename;
        
        $stmt = $pdo->prepare("UPDATE faults SET part = :part, filename = :filename, original_filename = :original_filename, status = :status, manager = :manager WHERE id = :id"); 
        $stmt->execute([
            'part' => $new_part, 
            'filename' => $update_filename, 
            'original_filename' => $update_original_filename,
            'status' => $new_status,
            'manager' => $new_manager,
            'id' => $id
        ]); 

        $currentUser = isset($_SESSION['admin']) ? $_SESSION['admin'] : $_SESSION['guest'];
        
        // 자세한 수정 로그 메시지 생성
        $logMessage = "고장 수정 - ID: $id";
        $logMessage .= ", 이전내용: '" . ($existing_fault['part'] ?? '없음') . "'";
        $logMessage .= ", 새내용: '$new_part'";
        
        if ($new_filename) {
            $logMessage .= ", 새첨부파일: '$new_original_filename' (저장명: $new_filename)";
            if ($old_filename) {
                $logMessage .= ", 기존파일삭제: '$old_filename'";
            }
        } elseif ($old_filename) {
            $logMessage .= ", 첨부파일유지: '$old_original_filename'";
        } else {
            $logMessage .= ", 첨부파일: 없음";
        }
        
        writeLog($pdo, $currentUser, '고장수정', '성공', $logMessage);
        header("Location: faults.php");
        exit();
    }
}

if (isset($_GET['delete'])) {                                 
    $id = $_GET['delete'];
    
    // 삭제할 파일 정보 가져오기
    $stmt = $pdo->prepare("SELECT filename, original_filename, part FROM faults WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $fault = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // DB에서 삭제
    $stmt = $pdo->prepare("DELETE FROM faults WHERE id = :id"); 
    $stmt->execute(['id' => $id]);
    
    // 실제 파일도 삭제
    if ($fault && $fault['filename']) {
        deleteFile($fault['filename']);
    }
    
    $currentUser = isset($_SESSION['admin']) ? $_SESSION['admin'] : $_SESSION['guest'];
    
    // 자세한 삭제 로그 메시지 생성
    $logMessage = "고장 삭제 - ID: $id";
    if ($fault) {
        $logMessage .= ", 삭제된내용: '" . $fault['part'] . "'";
        if ($fault['filename']) {
            $logMessage .= ", 첨부파일: '" . ($fault['original_filename'] ?? $fault['filename']) . "' (저장명: $fault[filename])";
        } else {
            $logMessage .= ", 첨부파일: 없음";
        }
    }
    
    writeLog($pdo, $currentUser, '고장삭제', '성공', $logMessage);
    header("Location: faults.php");
    exit();                                                   
}

$edit_fault = null;                                           
if (isset($_GET['edit'])) {                                   
    $id = $_GET['edit'];                                      
    $stmt = $pdo->prepare("SELECT * FROM faults WHERE id = :id"); 
    $stmt->execute(['id' => $id]);                            
    $edit_fault = $stmt->fetch(PDO::FETCH_ASSOC);            
}

// 페이지네이션 변수 추가
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$page_size = 10; // 한 페이지에 보여줄 고장 수

$total_faults = $pdo->query("SELECT COUNT(*) FROM faults")->fetchColumn();
$total_pages = ceil($total_faults / $page_size);

$offset = ($page - 1) * $page_size;
$limit = (int)$page_size;
$offset = (int)$offset;
$stmt = $pdo->query("SELECT f.*, u.username as created_by FROM faults f LEFT JOIN users u ON f.user_id = u.id ORDER BY f.created_at DESC LIMIT $limit OFFSET $offset");
$faults = $stmt->fetchAll(PDO::FETCH_ASSOC);


// [고장 게시판 상단에 검색/필터/정렬 폼 추가]
?>
<!-- 고장 게시판 메인 레이아웃 -->
<style>
  html, body { height: 100%; }
  body { background: #F5F7FA; color: #222; font-family: 'Noto Sans KR', 'Nanum Gothic', sans-serif; min-height: 100vh; display: flex; flex-direction: column; }
  .skip-link { position: absolute; left: -9999px; top: auto; width: 1px; height: 1px; overflow: hidden; z-index: 100; }
  .skip-link:focus { left: 16px; top: 16px; width: auto; height: auto; background: #005BAC; color: #fff; padding: 8px 16px; border-radius: 6px; }
  .header { display: flex; align-items: center; justify-content: space-between; background: #005BAC; color: #fff; padding: 0 32px; height: 64px; }
  .logo { display: flex; align-items: center; font-weight: bold; font-size: 1.3rem; letter-spacing: 1px; text-decoration: none; color: #fff; }
  .logo svg { margin-right: 8px; }
  .main-nav ul { display: flex; gap: 32px; list-style: none; }
  .main-nav a { color: #fff; text-decoration: none; font-weight: 500; padding: 8px 0; border-bottom: 2px solid transparent; transition: border 0.2s; display: flex; align-items: center; gap: 6px; }
  .main-nav a[aria-current="page"], .main-nav a:hover { border-bottom: 2px solid #FFB300; }
  .main-content {
    display: flex;
    gap: 32px;
    max-width: 1400px;
    width: 98vw;
    margin: 40px auto 0 auto;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    padding: 40px 32px;
    flex: 1 0 auto;
    min-height: 600px;
  }
  .main-content h2 { font-size: 1.7rem; font-weight: 700; color: #005BAC; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
  .fault-form-panel { flex: 0 0 400px; max-width: 420px; border-right: 1px solid #E0E6EF; padding-right: 32px; }
  .fault-list-panel { flex: 1 1 0; padding-left: 32px; }
  .fault-list-panel form[method="get"] {
    display: flex;
    gap: 14px;
    align-items: center;
    background: #f8f9fa;
    padding: 12px 16px;
    border-radius: 8px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.03);
    flex-wrap: nowrap;
    min-width: 0;
  }
  .fault-list-panel form[method="get"] > * {
    min-width: 140px;
    flex: 1 1 0;
    max-width: 260px;
  }
  .fault-list-panel form[method="get"] button {
    min-width: 90px;
    max-width: 120px;
    flex: 0 0 auto;
    background: #3C8DBC;
    color: #fff;
    border: none;
    border-radius: 6px;
    font-weight: 500;
    padding: 7px 18px;
    transition: background 0.2s;
    cursor: pointer;
  }
  .fault-list-panel form[method="get"] button:hover {
    background: #005BAC;
  }
  .fault-list { margin-top: 0; }
  .fault-item {
    background: #f5f7fa;
    border-radius: 14px;
    box-shadow: 0 2px 8px rgba(60,139,188,0.06);
    margin-bottom: 24px;
    overflow: hidden;
  }
  .fault-main {
    padding: 22px 28px 12px 28px;
    background: #fff;
  }
  .fault-part {
    font-size: 1.15rem;
    font-weight: 700;
    margin-bottom: 8px;
  }
  .fault-meta {
    font-size: 0.98rem;
    color: #666;
    display: flex;
    gap: 16px;
    align-items: center;
  }
  .status-badge {
    display: inline-block;
    padding: 4px 16px;
    border-radius: 16px;
    font-size: 0.98rem;
    font-weight: 600;
    color: #fff;
    margin-right: 0;
  }
  .status-badge.received { background: #3C8DBC; }
  .status-badge.processing { background: #FF9800; }
  .status-badge.completed { background: #4CAF50; }
  .fault-sub {
    background: #f8f9fa;
    padding: 14px 28px 18px 28px;
    border-top: 1px solid #e0e6ed;
  }
  .toggle-detail {
    background: none;
    border: none;
    color: #3C8DBC;
    font-weight: 600;
    cursor: pointer;
    margin-bottom: 8px;
  }
  .file-info { background: #e8f4fd; padding: 8px; border-radius: 3px; margin: 5px 0; font-size: 12px; }
  .file-link { color: #2196f3; text-decoration: none; }
  .file-link:hover { text-decoration: underline; }
  .file-missing { color: #f44336; font-style: italic; }
  .btn { display: inline-flex; align-items: center; gap: 4px; background: #005BAC; color: #fff; border: none; border-radius: 6px; padding: 8px 16px; font-size: 1rem; cursor: pointer; transition: background 0.2s; text-decoration: none; }
  .btn:hover { background: #003F7D; }
  .comment-list { margin-top: 10px; }
  .comment-item { background: #fff; padding: 7px 10px; margin: 4px 0; border-radius: 4px; }
  .footer { margin-top: 40px; padding: 24px 0 12px 0; background: none; color: #888; font-size: 15px; }
  @media (max-width: 1200px) {
    .main-content { max-width: 99vw; width: 99vw; padding: 16px 2vw; }
    .fault-form-panel, .fault-list-panel { max-width: none; padding: 0; border: none; }
    .fault-list-panel form[method="get"] { flex-wrap: wrap; }
    .fault-list-panel form[method="get"] > * { max-width: none; width: 100%; }
  }
  .fault-form-panel form {
    background: #f8f9fa;
    border-radius: 18px;
    box-shadow: 0 2px 12px rgba(60,139,188,0.08);
    padding: 32px 28px 24px 28px;
    max-width: 480px;
    margin: 0 auto 32px auto;
    display: flex;
    flex-direction: column;
    gap: 8px;
  }
  .fault-form-panel input[type="text"],
  .fault-form-panel select,
  .fault-form-panel input[type="file"] {
    border-radius: 12px;
    border: 1.5px solid #cfd8dc;
    padding: 14px 16px;
    font-size: 1rem;
    background: #fff;
    transition: border 0.2s;
    margin-top: 2px;
  }
  .fault-form-panel textarea {
    border-radius: 12px;
    border: 1.5px solid #cfd8dc;
    padding: 14px 16px;
    font-size: 1rem;
    background: #fff;
    transition: border 0.2s;
    margin-top: 2px;
    min-height: 48px;
    resize: vertical;
    overflow: hidden;
  }
  .fault-form-panel textarea:focus {
    border: 1.5px solid #3C8DBC;
    outline: none;
  }
  .fault-form-panel button[type="submit"] {
    border-radius: 18px;
    background: linear-gradient(90deg, #3C8DBC 60%, #005BAC 100%);
    color: #fff;
    font-weight: 600;
    font-size: 1.1rem;
    padding: 14px 0;
    border: none;
    margin-top: 8px;
    box-shadow: 0 2px 8px rgba(60,139,188,0.08);
    transition: background 0.2s;
  }
  .fault-form-panel button[type="submit"]:hover {
    background: linear-gradient(90deg, #005BAC 60%, #3C8DBC 100%);
  }
  .fault-form-panel label {
    font-weight: 600;
    color: #005BAC;
    margin-bottom: 2px;
    margin-top: 8px;
  }
  @media (max-width: 600px) {
    .fault-form-panel form {
      padding: 16px 6vw 16px 6vw;
      max-width: 99vw;
    }
  }
  .manager {
    font-size: 0.98rem;
    color: #666;
  }
  .created {
    font-size: 0.98rem;
    color: #666;
  }
  .author {
    font-size: 0.98rem;
    color: #666;
  }
  .comment-count {
    font-size: 0.98rem;
    color: #666;
  }
  .fault-action-btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    border: none;
    border-radius: 8px;
    padding: 7px 16px;
    font-size: 1rem;
    font-weight: 500;
    cursor: pointer;
    margin-right: 8px;
    background: #f0f4fa;
    color: #3C8DBC;
    transition: background 0.18s, color 0.18s, box-shadow 0.18s;
    box-shadow: 0 1px 4px rgba(60,139,188,0.06);
  }
  .fault-action-btn.edit { color: #1976D2; }
  .fault-action-btn.delete { color: #E53935; }
  .fault-action-btn.edit:hover { background: #e3f2fd; }
  .fault-action-btn.delete:hover { background: #ffebee; }

  .comment-form input[type="text"] {
    border-radius: 8px 0 0 8px;
    border: 1.5px solid #cfd8dc;
    padding: 10px 14px;
    font-size: 1rem;
    background: #fff;
    transition: border 0.2s;
    flex: 1;
  }
  .comment-form button {
    border-radius: 0 8px 8px 0;
    background: #3C8DBC;
    color: #fff;
    font-weight: 600;
    font-size: 1rem;
    padding: 10px 22px;
    border: none;
    margin-left: -1px;
    transition: background 0.2s;
  }
  .comment-form button:hover { background: #005BAC; }

  .fault-icons {
    display: flex;
    gap: 8px;
    align-items: center;
    margin-top: 6px;
  }
  .fault-icon-btn {
    background: none;
    border: none;
    font-size: 1.3rem;
    cursor: pointer;
    padding: 4px 8px;
    border-radius: 6px;
    transition: background 0.18s, color 0.18s;
  }
  .fault-icon-btn.bookmark { color: #FFB300; }
  .fault-icon-btn.bookmark:hover { background: #fff8e1; }
  .fault-icon-btn.report { color: #E53935; }
  .fault-icon-btn.report:hover { background: #ffebee; }

  .btn-cancel {
    display: inline-block;
    margin-top: 10px;
    color: #fff;
    background: #bbb;
    border-radius: 8px;
    padding: 8px 18px;
    font-weight: 500;
    text-decoration: none;
    transition: background 0.18s;
  }
  .btn-cancel:hover { background: #888; }

  /* 임시: 고장 목록 모두 보이게 강제 */
  .fault-list, .fault-item, .fault-list-panel {
    height: auto !important;
    overflow: visible !important;
    display: block !important;
    max-height: none !important;
  }
</style>
<body>
  <a href="#main-content" class="skip-link">본문 바로가기</a>
  <header class="header" role="banner">
    <a href="index.php" class="logo" aria-label="홈으로">
      <svg width="28" height="28" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><rect width="24" height="24" rx="6" fill="#fff" fill-opacity="0.18"/><path fill="#005BAC" d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8v-10h-8v10zm0-18v6h8V3h-8z"/></svg>
      PLC Rotator System
    </a>
    <nav class="main-nav" aria-label="주요 메뉴">
      <ul style="display:flex;align-items:center;gap:32px;">
        <li><a href="index.php"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8v-10h-8v10zm0-18v6h8V3h-8z" fill="#fff"/></svg>대시보드</a></li>
        <li><a href="control.php"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 2a10 10 0 100 20 10 10 0 000-20zm1 17.93V20h-2v-.07A8.001 8.001 0 014.07 13H4v-2h.07A8.001 8.001 0 0111 4.07V4h2v.07A8.001 8.001 0 0119.93 11H20v2h-.07A8.001 8.001 0 0113 19.93z" fill="#fff"/></svg>제어</a></li>
        <li><a href="faults.php" aria-current="page"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm0-4h-2V7h2v8z" fill="#fff"/></svg>고장</a></li>
        <?php if (isset($_SESSION['admin'])): ?>
        <li><a href="logs.php"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 6v18h18V6H3zm16 16H5V8h14v14zm-7-2h2v-2h-2v2zm0-4h2v-4h-2v4z" fill="#fff"/></svg>로그</a></li>
        <?php endif; ?>
        <li><a href="logout.php"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M16 13v-2H7V8l-5 4 5 4v-3h9zm3-10H5c-1.1 0-2 .9-2 2v6h2V5h14v14H5v-6H3v6c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z" fill="#fff"/></svg>로그아웃</a></li>
      </ul>
    </nav>
  </header>
  <main id="main-content" class="main-content" tabindex="-1">
    <div class="fault-form-panel">
      <?php if ($edit_fault): ?>
        <h2>✏️ 고장 내용 수정</h2>
        <form method="post" action="faults.php" enctype="multipart/form-data">
          <input type="hidden" name="edit_id" value="<?= $edit_fault['id'] ?>">
          <label for="new_part">수정할 내용:</label>
          <textarea name="new_part" id="new_part" required style="width:100%; min-height:48px; resize:vertical; overflow:hidden; margin-top:2px; border-radius:12px; border:1.5px solid #cfd8dc; padding:14px 16px; font-size:1rem; background:#fff; transition:border 0.2s;"><?= htmlspecialchars($edit_fault['part']) ?></textarea>
          <label for="edit_status">상태:</label>
          <select name="edit_status" id="edit_status" style="width:100%;margin-top:2px; border-radius:12px; border:1.5px solid #cfd8dc; padding:14px 16px; font-size:1rem; background:#fff;">
            <option value="접수" <?= $edit_fault['status']==='접수'?'selected':'' ?>>접수</option>
            <option value="처리중" <?= $edit_fault['status']==='처리중'?'selected':'' ?>>처리중</option>
            <option value="완료" <?= $edit_fault['status']==='완료'?'selected':'' ?>>완료</option>
          </select>
          <label for="edit_manager">담당자(선택):</label>
          <input type="text" name="edit_manager" id="edit_manager" value="<?= $edit_fault['manager']??'' ?>" style="width:100%;margin-top:2px; border-radius:12px; border:1.5px solid #cfd8dc; padding:14px 16px; font-size:1rem; background:#fff;">
          <?php if ($edit_fault['filename'] && fileExists($edit_fault['filename'])): ?>
            <div class="file-info">
              📎 현재 첨부파일: 
              <a href="uploads/<?= urlencode($edit_fault['filename']) ?>" target="_blank" class="file-link">
                <?= htmlspecialchars($edit_fault['original_filename'] ?? $edit_fault['filename']) ?>
              </a>
            </div>
          <?php endif; ?>
          <label for="new_file">새 파일로 교체 (선택사항):</label>
          <input type="file" name="new_file" id="new_file" style="margin-top:2px; border-radius:12px; border:1.5px solid #cfd8dc; padding:14px 16px; font-size:1rem; background:#fff;">
          <button type="submit">수정 완료</button>
        </form>
        <a href="faults.php" class="btn-cancel">수정 취소</a>
      <?php else: ?>
        <h2><svg width="28" height="28" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm0-4h-2V7h2v8z" fill="#005BAC"/></svg>고장 접수</h2>
        <form method="post" action="faults.php" enctype="multipart/form-data">
          <label for="part">고장난 부위 또는 내용:</label>
          <textarea name="part" id="part" placeholder="예: 좌측 모터에서 소음 발생" required style="width:100%; min-height:48px; resize:vertical; overflow:hidden;"></textarea>
          <label for="status">상태:</label>
          <select name="status" id="status" style="width:100%;margin-top:2px;">
            <option value="접수">접수</option>
            <option value="처리중">처리중</option>
            <option value="완료">완료</option>
          </select>
          <label for="manager">담당자(선택):</label>
          <input type="text" name="manager" id="manager" placeholder="담당자 이름 또는 아이디" style="width:100%;margin-top:2px;">
          <label for="file">고장 이미지 또는 파일:</label>
          <input type="file" name="file" id="file" style="margin-top:2px;">
          <button type="submit">접수하기</button>
        </form>
      <?php endif; ?>
    </div>
    <div class="fault-list-panel">
      <h2 style="font-size:1.2rem; color:#005BAC; margin-bottom:12px; display:flex;align-items:center;gap:8px;"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 6v18h18V6H3zm16 16H5V8h14v14zm-7-2h2v-2h-2v2zm0-4h2v-4h-2v4z" fill="#005BAC"/></svg>고장 목록</h2>
      <!-- 검색/필터/정렬 폼을 고장 목록 상단에 배치 -->
      <form method="get" action="faults.php" style="margin-bottom:18px;display:flex;gap:10px;align-items:center;background:#f8f9fa;padding:12px 16px;border-radius:8px;box-shadow:0 1px 4px rgba(0,0,0,0.03);">
        <select name="filter_status" style="padding:6px 10px;border-radius:6px;border:1px solid #ccc;">
          <option value="">상태 전체</option>
          <option value="접수">접수</option>
          <option value="처리중">처리중</option>
          <option value="완료">완료</option>
        </select>
        <input type="text" name="filter_manager" placeholder="담당자 검색" value="<?= $_GET['filter_manager']??'' ?>" style="padding:6px 10px;border-radius:6px;border:1px solid #ccc;">
        <input type="text" name="filter_keyword" placeholder="내용/부위 검색" value="<?= $_GET['filter_keyword']??'' ?>" style="padding:6px 10px;border-radius:6px;border:1px solid #ccc;">
        <select name="sort" style="padding:6px 10px;border-radius:6px;border:1px solid #ccc;">
          <option value="recent">최신순</option>
          <option value="old">오래된순</option>
          <option value="comment">댓글많은순</option>
        </select>
        <button type="submit" style="background:#3C8DBC;color:#fff;padding:7px 18px;border:none;border-radius:6px;font-weight:500;">검색/정렬</button>
      </form>
      <div class="fault-list">
        <?php if (count($faults) > 0): ?>
          <?php foreach ($faults as $fault): ?>
            <div class="fault-item">
              <div class="fault-main">
                <div class="fault-part"><?= htmlspecialchars($fault['part']) ?></div>
                <div class="fault-meta">
                  <span class="status-badge <?=
                    $fault['status']==='접수' ? 'received' :
                    ($fault['status']==='처리중' ? 'processing' : 'completed') ?>">
                    <?= htmlspecialchars($fault['status']) ?>
                  </span>
                  <span class="manager">담당자: <?= $fault['manager']??'-' ?></span>
                  <span class="created">등록일: <?= $fault['created_at'] ?></span>
                  <span class="author">작성자: <?= htmlspecialchars($fault['created_by'] ?? '알 수 없음') ?></span>
                </div>
                <?php if ($fault['filename'] && fileExists($fault['filename'])): ?>
                  <div class="file-info" style="margin-top:6px;">
                    📎 첨부파일: <a href="uploads/<?= urlencode($fault['filename']) ?>" target="_blank" class="file-link">
                      <?= htmlspecialchars($fault['original_filename'] ?? $fault['filename']) ?>
                    </a>
                  </div>
                <?php endif; ?>
              </div>
              <div class="fault-sub">
                <button class="toggle-detail">상세보기</button>
                <div class="fault-detail" style="display:none;">
                  <div style="margin-top:12px;padding:16px;background:#f8f9fa;border-radius:8px;">
                    <div style="margin-bottom:12px;font-size:0.95rem;color:#666;">
                      <strong>📋 상세 정보</strong><br>
                      작성자: <?= htmlspecialchars($fault['created_by'] ?? '알 수 없음') ?><br>
                      등록일: <?= $fault['created_at'] ?><br>
                      담당자: <?= $fault['manager']??'-' ?>
                    </div>
                    <div style="margin-bottom:12px;">
                      <button class="fault-action-btn edit" onclick="location.href='?edit=<?= $fault['id'] ?>'">
                        ✏️ 수정
                      </button>
                      <button class="fault-action-btn delete" onclick="if(confirm('정말 삭제할까요?')) location.href='?delete=<?= $fault['id'] ?>'">
                        ❌ 삭제
                      </button>
                    </div>
                    <?php if (isset($_SESSION['admin'])): ?>
                      <form method="post" action="faults.php" style="margin-bottom:8px;">
                        <input type="hidden" name="note_id" value="<?= $fault['id'] ?>">
                        <div style="background:#fff;border:1.5px solid #3C8DBC;border-radius:10px;padding:10px 14px 8px 14px;display:flex;align-items:flex-start;gap:10px;">
                          <span style="font-size:1.2rem;color:#3C8DBC;margin-top:2px;">📝</span>
                          <textarea name="admin_note" placeholder="관리자 메모" style="width:100%;min-height:36px;border:none;background:transparent;resize:vertical;outline:none;font-size:1rem;"><?= htmlspecialchars($fault['admin_note']??'') ?></textarea>
                          <button type="submit" style="background:#3C8DBC;color:#fff;border:none;border-radius:8px;padding:8px 16px;font-weight:600;">저장</button>
                        </div>
                      </form>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p>등록된 고장이 없습니다.</p>
        <?php endif; ?>
      </div>
      <!-- 페이지네이션 하단 표시 -->
      <div style="margin-top:18px;text-align:center;">
        <?php for($i=1;$i<=$total_pages;$i++): ?>
          <a href="?<?= http_build_query(array_merge($_GET, ['page'=>$i])) ?>" style="margin:0 4px;<?= $i==$page?'font-weight:bold;text-decoration:underline;':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
      </div>
    </div>
  </main>
  <footer class="footer" role="contentinfo">
    <div style="text-align:center;">기관명 | 주소 | <a href="#" style="color:#FFB300; text-decoration:underline;">이용약관</a> | <a href="#" style="color:#FFB300; text-decoration:underline;">개인정보처리방침</a> | 고객센터: 1234-5678</div>
    <div style="margin-top:8px; text-align:center;">© 2024 PLC Rotator System</div>
  </footer>
  <script>
  document.addEventListener('DOMContentLoaded', function() {
    var partTextarea = document.getElementById('part');
    if (partTextarea) {
      partTextarea.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
      });
    }
    
    // 상세보기 토글 기능 활성화
    document.querySelectorAll('.toggle-detail').forEach(btn => {
      btn.addEventListener('click', function() {
        const detail = this.nextElementSibling;
        if (detail.style.display === 'none' || !detail.style.display) {
          detail.style.display = 'block';
          this.textContent = '상세보기 닫기';
        } else {
          detail.style.display = 'none';
          this.textContent = '상세보기';
        }
      });
    });
  });
  </script>
</body>
</html>
