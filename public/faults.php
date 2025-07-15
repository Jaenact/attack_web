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

if (isset($_GET['download'])) {
    $file = $_GET['download'];
    if (file_exists($file)) {
        error_log("CWD: " . getcwd());
        error_log("Requested: " . $file);
        error_log("Resolved: " . realpath($file));
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        readfile($file);
        exit;
    } else {
        http_response_code(404);
        echo "File not found: " . htmlspecialchars($file);
        exit;
    }
}

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
            $userInput = [
                'part' => $part,
                'status' => $status,
                'manager' => $manager
            ];
            $logMessage = format_phpids_event($result, '고장접수', $userInput);
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
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>고장 관리</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="assets/css/main.css">
  <link href="https://fonts.googleapis.com/css?family=Noto+Sans+KR:400,700&display=swap" rel="stylesheet">
  <style>
    /* faults.php 전용: 좌우 분할 레이아웃 */
    .faults-flex-wrap {
      display: flex;
      gap: 40px;
      align-items: flex-start;
      margin-top: 0;
    }
    .fault-form {
      flex: 1 1 350px;
      min-width: 320px;
      max-width: 400px;
      background: #fff;
      border-radius: 10px;
      padding: 32px 20px 28px 20px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    }
    .fault-list {
      flex: 2 1 600px;
      min-width: 480px;
      background: #f8fafd;
      border-radius: 10px;
      padding: 32px 20px 28px 20px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    }
    @media (max-width: 900px) {
      .faults-flex-wrap {
        flex-direction: column;
        gap: 18px;
      }
      .fault-form, .fault-list {
        min-width: 0;
        max-width: 100%;
        padding: 18px 8px;
      }
    }
    /* 기존 .main-content의 flex 스타일 제거 (카드형 유지) */
    .main-content {
      display: block;
      max-width: 1200px;
      margin: 40px auto 0 auto;
      background: #fff;
      border-radius: 8px;
      box-shadow: 0 2px 12px rgba(0,0,0,0.06);
      padding: 40px 32px;
      flex: 1 0 auto;
      min-height: 600px;
    }
    /* 고장 게시글 카드 스타일 */
    .fault-item {
      border: 1.5px solid #e0e6ef;
      background: #fff;
      border-radius: 12px;
      padding: 18px 20px 12px 20px;
      margin-bottom: 18px;
      box-shadow: 0 2px 8px rgba(0,91,172,0.03);
      transition: border 0.18s, box-shadow 0.18s;
    }
    .fault-item:hover {
      border: 1.5px solid #b3c6e6;
      box-shadow: 0 4px 16px rgba(0,91,172,0.07);
    }
    /* 상태 뱃지 강조 */
    .status-badge {
      display: inline-block;
      border-radius: 8px;
      padding: 3px 12px;
      font-size: 0.98rem;
      font-weight: 700;
      margin-right: 8px;
    }
    .status-badge.received { background: #e3f0ff; color: #005BAC; }
    .status-badge.processing { background: #fffbe3; color: #f5a623; }
    .status-badge.completed { background: #e3ffe3; color: #43e97b; }
    /* 상세보기 버튼 작게 */
    .fault-item .toggle-detail {
      padding: 6px 18px;
      font-size: 1rem;
      min-width: 0;
      border-radius: 7px;
      margin-top: 8px;
      margin-bottom: 0;
    }
    /* 수정/삭제 버튼 작게 */
    .fault-action-btn {
      padding: 5px 14px;
      font-size: 0.98rem;
      min-width: 0;
      border-radius: 7px;
      margin-right: 6px;
      margin-bottom: 2px;
    }
    /* 검색/정렬 버튼 글씨 깨짐 방지 및 크기 축소 */
    .fault-list form button {
      padding: 5px 10px;
      font-size: 0.98rem;
      border-radius: 6px;
      min-width: 0;
      height: 34px;
      white-space: nowrap;
      line-height: 1.1;
      width: 54px;
      text-align: center;
    }
    /* 검색/정렬 폼 가로 정렬 및 버튼 input 높이 맞춤 */
    .fault-list form {
      display: flex;
      gap: 8px;
      align-items: center;
      justify-content: flex-start;
      flex-wrap: wrap;
      margin-bottom: 18px;
      background: #f8f9fa;
      padding: 12px 16px;
      border-radius: 8px;
      box-shadow: 0 1px 4px rgba(0,0,0,0.03);
    }
    .fault-list form button {
      height: 38px;
      padding: 0 16px;
      font-size: 1rem;
      border-radius: 6px;
      min-width: 0;
      white-space: nowrap;
      margin-left: 0;
      background: #3C8DBC;
      color: #fff;
      border: none;
      font-weight: 500;
      transition: background 0.18s;
    }
    .fault-list form button:hover {
      background: #2554a3;
    }
    @media (max-width: 700px) {
      .fault-list form {
        flex-direction: column;
        align-items: stretch;
        gap: 6px;
      }
      .fault-list form button {
        width: 100%;
        margin-left: 0;
      }
    }
  </style>
</head>
<body>
  <header class="header" role="banner" style="box-shadow:0 2px 8px rgba(0,0,0,0.08);">
    <a class="logo" aria-label="홈으로" style="font-size:1.5rem;letter-spacing:2px;">PLC Rotator System</a>
    <nav class="main-nav" aria-label="주요 메뉴">
      <ul style="display:flex;align-items:center;gap:32px;justify-content:center;">
        <li><a href="index.php">대시보드</a></li>
        <li><a href="control.php">제어</a></li>
        <li><a href="faults.php" aria-current="page">고장</a></li>
        <?php if (isset($_SESSION['admin'])): ?>
        <li><a href="logs.php">로그</a></li>
        <?php endif; ?>
        <li><a href="logout.php">로그아웃</a></li>
      </ul>
    </nav>
  </header>
  <main class="main-content" id="main-content" tabindex="-1">
    <div class="faults-flex-wrap">
      <div class="fault-form">
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
              <a href="faults.php?download=<?= urlencode('../uploads/' . $fault['filename']) ?>" target="_blank" class="file-link">
                <?= htmlspecialchars($fault['original_filename'] ?? $fault['filename']) ?>
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
    <div class="fault-list">
      <h2 style="font-size:1.2rem; color:#005BAC; margin-bottom:12px; display:flex;align-items:center;gap:8px;"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 6v18h18V6H3zm16 16H5V8h14v14zm-7-2h2v-2h-2v2zm0-4h2v-4h-2v4z" fill="#005BAC"/></svg>고장 목록</h2>
      <!-- 검색/필터/정렬 폼을 고장 목록 상단에 배치 -->
      <form method="get" action="faults.php">
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
        <button type="submit">검색</button>
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
    <div>가천대학교 CPS |  <a href="#" style="color:#FFB300; text-decoration:underline;">이용약관</a> | <a href="#" style="color:#FFB300; text-decoration:underline;">개인정보처리방침</a> | 고객센터: 1234-5678</div>
    <div style="margin-top:8px;">© 2025 PLC Control</div>
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
