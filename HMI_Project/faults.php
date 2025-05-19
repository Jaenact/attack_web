<?php
// 고장 게시판: 관리자만 수정/삭제, 모든 사용자는 작성 가능
session_start();
require_once 'includes/db.php';
require_once 'includes/log.php';

// 로그인 안 했으면 로그인 페이지로 이동
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$isAdmin = isset($_SESSION['admin']) && $_SESSION['admin'] === true;
$current_user = $_SESSION['username'];
$upload_dir = 'uploads/';

// 고장 접수(글 작성)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['part']) && !isset($_POST['edit_id'])) {
    $part = trim($_POST['part']);
    $filename = null;
    // 파일 업로드 처리
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $tmp_name = $_FILES['file']['tmp_name'];
        $origin_name = basename($_FILES['file']['name']);
        $ext = pathinfo($origin_name, PATHINFO_EXTENSION);
        $new_name = uniqid() . "." . $ext;
        move_uploaded_file($tmp_name, $upload_dir . $new_name);
        $filename = $new_name;
    }
    // username 필드에 항상 작성자 저장
    if (!empty($part)) {
        $stmt = $pdo->prepare("INSERT INTO faults (part, filename, username) VALUES (:part, :filename, :username)");
        $stmt->execute(['part' => $part, 'filename' => $filename, 'username' => $current_user]);
        write_log("고장 접수", "내용: $part");
        echo "<script>alert('고장 내용을 등록했습니다.'); location.href='faults.php';</script>";
        exit();
    }
}

// 관리자만 수정
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'], $_POST['new_part'])) {
    $id = $_POST['edit_id'];
    $new_part = trim($_POST['new_part']);
    if (!empty($new_part)) {
        $stmt = $pdo->prepare("UPDATE faults SET part = :part WHERE id = :id");
        $stmt->execute(['part' => $new_part, 'id' => $id]);
        write_log("고장 내용 수정", "ID: $id, 내용: $new_part");
        echo "<script>alert('수정 완료!'); location.href='faults.php';</script>";
        exit();
    }
}

// 관리자만 삭제
if ($isAdmin && isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM faults WHERE id = :id");
    $stmt->execute(['id' => $id]);
    write_log("고장 삭제", "ID: $id");
    echo "<script>alert('삭제 완료!'); location.href='faults.php';</script>";
    exit();
}

// 관리자만 수정 폼 노출
$edit_fault = null;
if ($isAdmin && isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM faults WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $edit_fault = $stmt->fetch(PDO::FETCH_ASSOC);
}

// 전체 고장 목록 불러오기
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
    </style>
</head>
<body>
    <div class="sidebar">
        <h3>PLC 제어</h3>
        <ul>
            <li><a href="index.php">🏠 대시보드</a></li>
            <li><a href="control.php">⚙ 회전기 제어</a></li>
            <li><a href="faults.php">🚨 고장 게시판</a></li>
            <li><a href="logs.php">📝 시스템 로그</a></li>
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

            <?php if ($isAdmin && $edit_fault): ?>
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
                            <div class="fault-time">
                                작성자: <?= isset($fault['username']) && $fault['username'] ? htmlspecialchars($fault['username']) : '알 수 없음' ?><br>
                                등록일: <?= $fault['created_at'] ?>
                            </div>
                            <?php if ($isAdmin): ?>
                            <div style="margin-top: 10px;">
                                <a href="?edit=<?= $fault['id'] ?>" style="margin-right:10px; text-decoration:none; color:blue;">✏️ 수정</a>
                                <a href="?delete=<?= $fault['id'] ?>" onclick="return confirm('정말 삭제할까요?');" style="color:red; text-decoration:none;">❌ 삭제</a>
                            </div>
                            <?php endif; ?>
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
