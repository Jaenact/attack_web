<?php
session_start();
require_once __DIR__ . '/../../src/db/db.php';
require_once __DIR__ . '/../../src/log/log_function.php';
if (!isset($_SESSION['admin'])) {
    echo "<script>alert('관리자만 접근 가능합니다.');location.href='index.php';</script>";
    exit();
}
// 고장 파일 + 프로필 이미지 등 통합 파일 목록 조회
$fault_files = $pdo->query("SELECT id, part AS info, filename, original_filename, created_at, 'fault' AS type, NULL AS username FROM faults WHERE filename IS NOT NULL AND filename != ''")->fetchAll(PDO::FETCH_ASSOC);
$profile_files = $pdo->query("SELECT id, name AS info, profile_img AS filename, profile_img AS original_filename, created_at, 'profile' AS type, username FROM users WHERE profile_img IS NOT NULL AND profile_img != ''")->fetchAll(PDO::FETCH_ASSOC);
$files = array_merge($fault_files, $profile_files);
// 파일 경로
$upload_dir = realpath(__DIR__ . '/../uploads') . '/';
$profile_dir = realpath(__DIR__ . '/../uploads/profile') . '/';
// 파일 삭제 처리
if (isset($_GET['delete'], $_GET['type'], $_GET['id'])) {
    $id = intval($_GET['id']); $type = $_GET['type'];
    if ($type==='fault') {
        $row = $pdo->prepare("SELECT filename FROM faults WHERE id=?"); $row->execute([$id]); $f = $row->fetchColumn();
        if ($f && file_exists($upload_dir.$f)) unlink($upload_dir.$f);
        $pdo->prepare("UPDATE faults SET filename=NULL, original_filename=NULL WHERE id=?")->execute([$id]);
        writeLog($pdo, $_SESSION['admin'], '파일삭제', '성공', "고장파일 ID:$id");
    } else if ($type==='profile') {
        $row = $pdo->prepare("SELECT profile_img FROM users WHERE id=?"); $row->execute([$id]); $f = $row->fetchColumn();
        if ($f && file_exists($profile_dir.$f)) unlink($profile_dir.$f);
        $pdo->prepare("UPDATE users SET profile_img=NULL WHERE id=?")->execute([$id]);
        writeLog($pdo, $_SESSION['admin'], '파일삭제', '성공', "프로필 ID:$id");
    }
    header('Location: file_management.php'); exit();
}
// 파일 다운로드 로그 기록
if (isset($_GET['download'], $_GET['type'], $_GET['id'])) {
    $id = intval($_GET['id']); $type = $_GET['type'];
    if ($type==='fault') {
        $row = $pdo->prepare("SELECT filename, original_filename FROM faults WHERE id=?"); $row->execute([$id]); $f = $row->fetch(PDO::FETCH_ASSOC);
        $path = $upload_dir.($f['filename']??''); $oname = $f['original_filename']??'';
    } else {
        $row = $pdo->prepare("SELECT profile_img, name FROM users WHERE id=?"); $row->execute([$id]); $f = $row->fetch(PDO::FETCH_ASSOC);
        $path = $profile_dir.($f['profile_img']??''); $oname = $f['profile_img']??'';
    }
    if ($path && file_exists($path)) {
        writeLog($pdo, $_SESSION['admin'], '파일다운로드', '성공', "$oname");
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.basename($oname).'"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($path));
        readfile($path); exit();
    }
    echo "<script>alert('파일이 존재하지 않습니다.');location.href='file_management.php';</script>"; exit();
}
function isDangerFile($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, ['php','exe','sh','bat','js']);
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>파일 업로드/다운로드 관리</title>
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <style>
    .file-table{width:100%;border-collapse:collapse;margin-top:24px;}
    .file-table th,.file-table td{border:1px solid #e1e5e9;padding:8px 10px;text-align:center;}
    .file-table th{background:#f5f7fa;}
    .danger{color:#fff;background:#ff4757;font-weight:700;}
    .file-actions a{margin:0 2px;}
    @media(max-width:700px){.file-table{font-size:0.95rem;}}
  </style>
</head>
<body style="background:linear-gradient(135deg,rgba(0,91,172,0.08) 0%,rgba(0,91,172,0.13) 100%);min-height:100vh;">
  <main style="max-width:900px;margin:40px auto 0 auto;background:#fff;border-radius:16px;box-shadow:0 4px 24px rgba(0,91,172,0.10);padding:40px 32px;">
    <h2 style="font-size:2rem;font-weight:700;color:#005BAC;margin-bottom:18px;">파일 업로드/다운로드 관리</h2>
    <table class="file-table">
      <thead>
        <tr><th>구분</th><th>파일명</th><th>원본명</th><th>설명/소유자</th><th>업로드일</th><th>위험</th><th>관리</th></tr>
      </thead>
      <tbody>
        <?php foreach($files as $f): ?>
        <tr<?=isDangerFile($f['filename'])?' class="danger"':''?>>
          <td><?=$f['type']==='fault'?'고장':'프로필'?></td>
          <td><?=htmlspecialchars($f['filename'])?></td>
          <td><?=htmlspecialchars($f['original_filename'])?></td>
          <td><?=htmlspecialchars($f['info'])?><?php if($f['type']==='profile') echo '('.htmlspecialchars($f['username']).')';?></td>
          <td><?=date('Y-m-d',strtotime($f['created_at']))?></td>
          <td><?=isDangerFile($f['filename'])?'<span>위험</span>':'-'?></td>
          <td class="file-actions">
            <a href="?download=1&type=<?=$f['type']?>&id=<?=$f['id']?>" style="color:#005BAC;text-decoration:underline;">다운로드</a>
            <a href="?delete=1&type=<?=$f['type']?>&id=<?=$f['id']?>" style="color:#ff4757;text-decoration:underline;" onclick="return confirm('정말 삭제하시겠습니까?');">삭제</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <a href="../index.php" style="display:inline-block;margin-top:24px;padding:10px 28px;background:linear-gradient(90deg,#005BAC 60%,#0076d7 100%);color:#fff;font-weight:700;border-radius:8px;text-decoration:none;box-shadow:0 2px 8px rgba(0,91,172,0.10);transition:background 0.2s;">← 대시보드로</a>
  </main>
</body>
</html> 