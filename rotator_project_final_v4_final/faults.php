<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/log.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$upload_dir = 'uploads/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// ▶ 고장 등록
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['part'])) {
    $part = $_POST['part'];
    $filename = null;
    $user_id = $_SESSION['user_id'];

    // ▶ 파일 업로드가 있는 경우 처리
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $tmp_name = $_FILES['file']['tmp_name'];
        $origin_name = basename($_FILES['file']['name']);
        $ext = pathinfo($origin_name, PATHINFO_EXTENSION);
        $new_name = uniqid() . "." . $ext;
        move_uploaded_file($tmp_name, $upload_dir . $new_name);
        $filename = $new_name;
    }

    $stmt = $pdo->prepare("INSERT INTO faults (part, filename, user_id) VALUES (?, ?, ?)");
    $stmt->execute([$part, $filename, $user_id]);

    write_log("고장 등록", "부품: $part, 파일: $filename");
    echo "<script>alert('고장이 등록되었습니다.'); location.href='faults.php';</script>";
    exit();
}

// ▶ 삭제 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delete_id = $_POST['delete_id'];

    $stmt = $pdo->prepare("SELECT * FROM faults WHERE id = ?");
    $stmt->execute([$delete_id]);
    $fault = $stmt->fetch();

    if ($fault && (isAdmin() || $fault['user_id'] == $_SESSION['user_id'])) {
        $stmt = $pdo->prepare("DELETE FROM faults WHERE id = ?");
        $stmt->execute([$delete_id]);

        write_log("고장 삭제", "ID: $delete_id");
        echo "<script>alert('삭제 완료'); location.href='faults.php';</script>";
        exit();
    } else {
        echo "<script>alert('삭제 권한이 없습니다.');</script>";
    }
}

// ▶ 게시글 출력
$stmt = $pdo->query("SELECT * FROM faults");
$faults = $stmt->fetchAll();
?>

<h2>고장 게시판</h2>
<form method="post" enctype="multipart/form-data">
    부품: <input type="text" name="part" required>
    파일: <input type="file" name="file">
    <button type="submit">등록</button>
</form>

<table border="1">
    <tr><th>ID</th><th>부품</th><th>파일</th><th>작성자</th><th>작업</th></tr>
<?php foreach ($faults as $f): ?>
<tr>
    <td><?= $f['id'] ?></td>
    <td><?= htmlspecialchars($f['part']) ?></td>
    <td>
        <?php if ($f['filename']): ?>
            <a href="uploads/<?= htmlspecialchars($f['filename']) ?>" target="_blank">파일 보기</a>
        <?php endif; ?>
    </td>
    <td><?= htmlspecialchars($f['user_id']) ?></td>
    <td>
        <?php if (isAdmin() || $f['user_id'] == $_SESSION['user_id']): ?>
        <form method="post" style="display:inline;">
            <input type="hidden" name="delete_id" value="<?= $f['id'] ?>">
            <button type="submit">삭제</button>
        </form>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
</table>
