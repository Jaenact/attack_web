<?php
session_start(); // 세션 시작 (사용자 인증 확인용)

if (!isset($_SESSION['admin'])) {        // 세션에 'admin'이 없으면 (즉, 로그인하지 않은 경우)
    echo "<script>alert('접근할 수 업습니다. 강제 로그아웃 합니다.'); location.href='login.php';</script>";
           // 로그인 페이지로 리디렉션
    exit();                              // 이후 코드 실행 중단
}

require_once 'includes/db.php';          // DB 연결 설정 포함 (PDO 객체 $pdo 제공)

$upload_dir = 'uploads/';                // 업로드될 파일을 저장할 디렉토리 경로

// ▶ 고장 접수 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['part']) && !isset($_POST['edit_id'])) {
    $part = trim($_POST['part']);        // 고장 내용 텍스트를 받아서 양끝 공백 제거
    $filename = null;                    // 기본 파일명은 null로 초기화

    // ▶ 파일 업로드가 있는 경우 처리
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $tmp_name = $_FILES['file']['tmp_name'];              // 임시 저장된 파일 경로
        $origin_name = basename($_FILES['file']['name']);     // 클라이언트가 업로드한 원본 파일명
        $ext = pathinfo($origin_name, PATHINFO_EXTENSION);    // 확장자 추출
        $new_name = uniqid() . "." . $ext;                    // 고유한 파일명 생성 (충돌 방지용)

        move_uploaded_file($tmp_name, $upload_dir . $new_name); // 파일을 uploads/ 디렉토리로 이동
        $filename = $new_name;                                // DB에 저장할 파일명 설정
    }

    if (!empty($part)) {                                      // 고장 내용이 비어있지 않다면
        $stmt = $pdo->prepare("INSERT INTO faults (part, filename) VALUES (:part, :filename)"); // INSERT 쿼리 준비
        $stmt->execute(['part' => $part, 'filename' => $filename]); // 바인딩 파라미터로 실행
        echo "<script>alert('고장 내용을 등록했습니다.'); location.href='faults.php';</script>"; // 등록 완료 후 리디렉션
        exit();                                               // 이후 코드 실행 중지
    }
}

// ▶ 고장 내용 수정 처리 (파일은 수정하지 않음)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'], $_POST['new_part'])) {
    $id = $_POST['edit_id'];                                  // 수정 대상 고장 ID
    $new_part = trim($_POST['new_part']);                     // 새 고장 내용 (공백 제거)

    if (!empty($new_part)) {                                  // 수정 내용이 비어있지 않다면
        $stmt = $pdo->prepare("UPDATE faults SET part = :part WHERE id = :id"); // UPDATE 쿼리 준비
        $stmt->execute(['part' => $new_part, 'id' => $id]);   // 파라미터 바인딩 후 실행
        echo "<script>alert('수정 완료!'); location.href='faults.php';</script>"; // 성공 알림 후 이동
        exit();                                               // 코드 종료
    }
}

// ▶ 고장 삭제 처리
if (isset($_GET['delete'])) {                                 // GET 방식으로 delete 파라미터가 있다면
    $id = $_GET['delete'];                                    // 삭제할 ID
    $stmt = $pdo->prepare("DELETE FROM faults WHERE id = :id"); // DELETE 쿼리 준비
    $stmt->execute(['id' => $id]);                            // ID 바인딩 후 실행
    echo "<script>alert('삭제 완료!'); location.href='faults.php';</script>"; // 알림 후 리디렉션
    exit();                                                   // 코드 종료
}

// ▶ 수정 요청 시: 해당 항목 불러오기 (폼에 표시하기 위함)
$edit_fault = null;                                           // 기본값은 null
if (isset($_GET['edit'])) {                                   // GET 파라미터에 edit이 있다면
    $id = $_GET['edit'];                                      // 수정할 고장 ID
    $stmt = $pdo->prepare("SELECT * FROM faults WHERE id = :id"); // SELECT 쿼리 준비
    $stmt->execute(['id' => $id]);                            // ID 바인딩 후 실행
    $edit_fault = $stmt->fetch(PDO::FETCH_ASSOC);             // 결과를 연관 배열로 가져옴
}

// ▶ 전체 고장 목록 불러오기
$stmt = $pdo->query("SELECT * FROM faults ORDER BY created_at DESC"); // 최신순으로 모든 고장 목록 가져오기
$faults = $stmt->fetchAll(PDO::FETCH_ASSOC);                  // 전체 결과를 연관 배열로 변환
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

            <label for="file">고장 이미지 또는 파일 (.jpg, .png, .exe 등):</label><br>
            <input type="file" name="file" id="file" style="margin-top:10px;"><br><br>

            <button type="submit">접수하기</button>
        </form>

        <?php if ($edit_fault): ?>
            <hr>
            <h3>✏️ 고장 내용 수정</h3>
            <form method="post" action="faults.php">
                <input type="hidden" name="edit_id" value="<?= $edit_fault['id'] ?>">
                <label for="new_part">수정할 내용:</label><br>
                <input type="text" name="new_part" id="new_part" value="<?= htmlspecialchars($edit_fault['part']) ?>" required style="width:100%; margin-top:10px;"><br><br>
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
                            <div><a href="uploads/<?= urlencode($fault['filename']) ?>" target="_blank">📎 첨부파일 보기</a></div>
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
