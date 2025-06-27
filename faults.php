<?php
session_start(); 

require_once 'includes/db.php';          
require_once 'log_function.php';

// 로그인 체크
if (!isset($_SESSION['admin']) && !isset($_SESSION['guest'])) {
    header('Location: login.php');
    exit();
}

$upload_dir = 'uploads/';

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
    $filename = null;
    $original_filename = null;

    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $tmp_name = $_FILES['file']['tmp_name'];              
        $origin_name = basename($_FILES['file']['name']);     
        $ext = pathinfo($origin_name, PATHINFO_EXTENSION);    
        $new_name = uniqid() . "." . $ext;                    

        if (move_uploaded_file($tmp_name, $upload_dir . $new_name)) {
            $filename = $new_name;
            $original_filename = $origin_name; // 원본 파일명 저장
        }
    }

    if (!empty($part)) {                                      
        $stmt = $pdo->prepare("INSERT INTO faults (part, filename, original_filename) VALUES (:part, :filename, :original_filename)"); 
        $stmt->execute(['part' => $part, 'filename' => $filename, 'original_filename' => $original_filename]); 

        $currentUser = isset($_SESSION['admin']) ? $_SESSION['admin'] : $_SESSION['guest'];
        
        // 자세한 로그 메시지 생성
        $logMessage = "고장 접수 - 입력내용: '$part'";
        if ($filename) {
            $logMessage .= ", 첨부파일: '$original_filename' (저장명: $filename)";
        } else {
            $logMessage .= ", 첨부파일: 없음";
        }
        
        writeLog($pdo, $currentUser, $logMessage);

        echo "<script>alert('고장 내용을 등록했습니다.'); location.href='faults.php';</script>"; 
        exit();                                               
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'], $_POST['new_part'])) {
    $id = $_POST['edit_id'];                                  
    $new_part = trim($_POST['new_part']);                     
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
        $ext = pathinfo($origin_name, PATHINFO_EXTENSION);    
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
        
        $stmt = $pdo->prepare("UPDATE faults SET part = :part, filename = :filename, original_filename = :original_filename WHERE id = :id"); 
        $stmt->execute([
            'part' => $new_part, 
            'filename' => $update_filename, 
            'original_filename' => $update_original_filename,
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
        
        writeLog($pdo, $currentUser, $logMessage);
        
        echo "<script>alert('수정 완료!'); location.href='faults.php';</script>"; 
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
    
    writeLog($pdo, $currentUser, $logMessage);

    echo "<script>alert('삭제 완료!'); location.href='faults.php';</script>"; 
    exit();                                                   
}

$edit_fault = null;                                           
if (isset($_GET['edit'])) {                                   
    $id = $_GET['edit'];                                      
    $stmt = $pdo->prepare("SELECT * FROM faults WHERE id = :id"); 
    $stmt->execute(['id' => $id]);                            
    $edit_fault = $stmt->fetch(PDO::FETCH_ASSOC);            
}

$stmt = $pdo->query("SELECT * FROM faults ORDER BY created_at DESC"); 
$faults = $stmt->fetchAll(PDO::FETCH_ASSOC);                  
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>고장 게시판</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .fault-box {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            max-width: 600px;
        }
        .fault-box h2 {
            margin-bottom: 20px;
        }
        .fault-box form {
            margin-bottom: 30px;
        }
        .fault-list {
            margin-top: 20px;
        }
        .fault-item {
            background: #f1f2f6;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        .fault-time {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        .file-info {
            background: #e8f4fd;
            padding: 8px;
            border-radius: 3px;
            margin: 5px 0;
            font-size: 12px;
        }
        .file-link {
            color: #2196f3;
            text-decoration: none;
        }
        .file-link:hover {
            text-decoration: underline;
        }
        .file-missing {
            color: #f44336;
            font-style: italic;
        }
    </style>
</head>
<body>
<div class="sidebar">
    <h3>PLC 제어</h3>
    <ul>
        <li><a href="index.php">🏠 대시보드</a></li>
        <li><a href="control.php">⚙ 회전기 제어</a></li>
        <li><a href="faults.php">🚨 고장 게시판</a></li>
        <li><a href="logs.php">📋 로그</a></li>
        <li><a href="logout.php">🔓 로그아웃</a></li>
    </ul>
</div>

<div class="main">
    <div class="fault-box">
        <h2>🚨 고장 접수</h2>
        
        <form method="post" action="faults.php" enctype="multipart/form-data">
            <label for="part">고장난 부위 또는 내용:</label><br>
            <input type="text" name="part" id="part" placeholder="예: 좌측 모터에서 소음 발생" required style="width:100%; margin-top:10px;"><br><br>

            <label for="file">고장 이미지 또는 파일:</label><br>
            <input type="file" name="file" id="file" style="margin-top:10px;"><br><br>

            <button type="submit">접수하기</button>
        </form>

        <?php if ($edit_fault): ?>
            <hr>
            <h3>✏️ 고장 내용 수정</h3>
            <form method="post" action="faults.php" enctype="multipart/form-data">
                <input type="hidden" name="edit_id" value="<?= $edit_fault['id'] ?>">
                <label for="new_part">수정할 내용:</label><br>
                <input type="text" name="new_part" id="new_part" value="<?= htmlspecialchars($edit_fault['part']) ?>" required style="width:100%; margin-top:10px;"><br><br>
                
                <?php if ($edit_fault['filename'] && fileExists($edit_fault['filename'])): ?>
                    <div class="file-info">
                        📎 현재 첨부파일: 
                        <a href="uploads/<?= urlencode($edit_fault['filename']) ?>" target="_blank" class="file-link">
                            <?= htmlspecialchars($edit_fault['original_filename'] ?? $edit_fault['filename']) ?>
                        </a>
                    </div>
                <?php endif; ?>
                
                <label for="new_file">새 파일로 교체 (선택사항):</label><br>
                <input type="file" name="new_file" id="new_file" style="margin-top:10px;"><br><br>
                
                <button type="submit">수정 완료</button>
                <a href="faults.php" style="margin-left:10px;">취소</a>
            </form>
        <?php endif; ?>

        <hr>

        <div class="fault-list">
            <h3>📋 접수된 고장 목록</h3>
            <?php if (count($faults) > 0): ?>
                <?php foreach ($faults as $fault): ?>
                    <div class="fault-item">
                        <div><?= htmlspecialchars($fault['part']) ?></div>
                        <?php if (isset($fault['filename']) && $fault['filename']): ?>
                            <?php if (fileExists($fault['filename'])): ?>
                                <div class="file-info">
                                    📎 첨부파일: 
                                    <a href="uploads/<?= urlencode($fault['filename']) ?>" target="_blank" class="file-link">
                                        <?= htmlspecialchars($fault['original_filename'] ?? $fault['filename']) ?>
                                    </a>
                                    (<?= strtoupper(pathinfo($fault['filename'], PATHINFO_EXTENSION)) ?>)
                                </div>
                            <?php else: ?>
                                <div class="file-missing">⚠️ 첨부파일이 존재하지 않습니다: <?= htmlspecialchars($fault['original_filename'] ?? $fault['filename']) ?></div>
                            <?php endif; ?>
                        <?php endif; ?>
                        <div class="fault-time">등록일: <?= $fault['created_at'] ?></div>
                        <div style="margin-top: 10px;">
                            <a href="?edit=<?= $fault['id'] ?>" style="margin-right:10px; text-decoration:none; color:blue;">✏️ 수정</a>
                            <a href="?delete=<?= $fault['id'] ?>" onclick="return confirm('정말 삭제할까요?');" style="color:red; text-decoration:none;">❌ 삭제</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>등록된 고장이 없습니다.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
