<?php
session_start();
require_once __DIR__ . '/../../src/db/db.php';
require_once __DIR__ . '/../../src/log/log_function.php';
if (!isset($_SESSION['admin'])) {
    echo "<script>alert('관리자만 접근 가능합니다.');location.href='index.php';</script>";
    exit();
}
// 공지 관리
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (isset($_POST['add_notice'], $_POST['notice_title'], $_POST['notice_content'])) {
        $title = trim($_POST['notice_title']); $content = trim($_POST['notice_content']);
        if ($title && $content) {
            $stmt = $pdo->prepare("INSERT INTO notices (title, content) VALUES (?, ?)");
            $stmt->execute([$title, $content]);
            writeLog($pdo, $_SESSION['admin'], '공지등록', '성공', $title);
        }
    }
    if (isset($_POST['delete_notice_id'])) {
        $id = (int)$_POST['delete_notice_id'];
        $pdo->prepare("DELETE FROM notices WHERE id = ?")->execute([$id]);
        writeLog($pdo, $_SESSION['admin'], '공지삭제', '성공', $id);
    }
    if (isset($_POST['edit_notice_id'], $_POST['edit_notice_title'], $_POST['edit_notice_content'])) {
        $id = (int)$_POST['edit_notice_id'];
        $title = trim($_POST['edit_notice_title']); $content = trim($_POST['edit_notice_content']);
        if ($title && $content) {
            $pdo->prepare("UPDATE notices SET title=?, content=? WHERE id=?")->execute([$title, $content, $id]);
            writeLog($pdo, $_SESSION['admin'], '공지수정', '성공', $id);
        }
    }
    // 배너/팝업 관리(간단 구현: notices 테이블에 type 컬럼 추가 없이, 별도 관리)
    if (isset($_POST['add_banner'], $_POST['banner_content'], $_POST['banner_start'], $_POST['banner_end'])) {
        $stmt = $pdo->prepare("INSERT INTO banners (content, start_at, end_at) VALUES (?, ?, ?)");
        $stmt->execute([trim($_POST['banner_content']), $_POST['banner_start'], $_POST['banner_end']]);
        writeLog($pdo, $_SESSION['admin'], '배너등록', '성공', $_POST['banner_content']);
    }
    if (isset($_POST['delete_banner_id'])) {
        $id = (int)$_POST['delete_banner_id'];
        $pdo->prepare("DELETE FROM banners WHERE id = ?")->execute([$id]);
        writeLog($pdo, $_SESSION['admin'], '배너삭제', '성공', $id);
    }
    if (isset($_POST['add_popup'], $_POST['popup_content'], $_POST['popup_start'], $_POST['popup_end'])) {
        $stmt = $pdo->prepare("INSERT INTO popups (content, start_at, end_at) VALUES (?, ?, ?)");
        $stmt->execute([trim($_POST['popup_content']), $_POST['popup_start'], $_POST['popup_end']]);
        writeLog($pdo, $_SESSION['admin'], '팝업등록', '성공', $_POST['popup_content']);
    }
    if (isset($_POST['delete_popup_id'])) {
        $id = (int)$_POST['delete_popup_id'];
        $pdo->prepare("DELETE FROM popups WHERE id = ?")->execute([$id]);
        writeLog($pdo, $_SESSION['admin'], '팝업삭제', '성공', $id);
    }
    header('Location: notice_banner_popup.php'); exit();
}
// 데이터 조회
$notices = $pdo->query("SELECT * FROM notices ORDER BY created_at DESC")->fetchAll();
$banners = $pdo->query("SELECT * FROM banners ORDER BY start_at DESC")->fetchAll();
$popups = $pdo->query("SELECT * FROM popups ORDER BY start_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>공지/배너/팝업 관리</title>
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <link rel="stylesheet" href="style.css">
  <style>
    .mg-table{width:100%;border-collapse:collapse;margin-top:24px;}
    .mg-table th,.mg-table td{border:1px solid #e1e5e9;padding:8px 10px;text-align:center;}
    .mg-table th{background:#f5f7fa;}
    .mg-table td{font-size:0.98rem;}
    .mg-actions button{margin:0 2px;}
    @media(max-width:700px){.mg-table{font-size:0.95rem;}}
  </style>
</head>
<body style="background:linear-gradient(135deg,rgba(0,91,172,0.08) 0%,rgba(0,91,172,0.13) 100%);min-height:100vh;">
  <main style="max-width:900px;margin:40px auto 0 auto;background:#fff;border-radius:16px;box-shadow:0 4px 24px rgba(0,91,172,0.10);padding:40px 32px;">
    <h2 style="font-size:2rem;font-weight:700;color:#005BAC;margin-bottom:18px;">공지/배너/팝업 관리</h2>
    <section style="margin-bottom:32px;">
      <h3>공지사항</h3>
      <form method="post" style="margin-bottom:12px;display:flex;gap:8px;align-items:center;">
        <input type="text" name="notice_title" placeholder="제목" required style="padding:6px 10px;border:1px solid #ccc;border-radius:6px;">
        <input type="text" name="notice_content" placeholder="내용" required style="padding:6px 10px;border:1px solid #ccc;border-radius:6px;">
        <button type="submit" name="add_notice" style="padding:6px 16px;background:#005BAC;color:#fff;border:none;border-radius:6px;">등록</button>
      </form>
      <table class="mg-table">
        <thead><tr><th>제목</th><th>내용</th><th>등록일</th><th>관리</th></tr></thead>
        <tbody>
          <?php foreach($notices as $n): ?>
          <tr>
            <td><?=htmlspecialchars($n['title'])?></td>
            <td><?=htmlspecialchars($n['content'])?></td>
            <td><?=date('Y-m-d',strtotime($n['created_at']))?></td>
            <td class="mg-actions">
              <form method="post" style="display:inline-block;"><input type="hidden" name="delete_notice_id" value="<?=$n['id']?>"><button type="submit" style="color:#ff4757;background:none;border:none;">삭제</button></form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>
    <section style="margin-bottom:32px;">
      <h3>배너</h3>
      <form method="post" style="margin-bottom:12px;display:flex;gap:8px;align-items:center;">
        <input type="text" name="banner_content" placeholder="배너 내용" required style="padding:6px 10px;border:1px solid #ccc;border-radius:6px;">
        <input type="date" name="banner_start" required>
        <input type="date" name="banner_end" required>
        <button type="submit" name="add_banner" style="padding:6px 16px;background:#43e97b;color:#fff;border:none;border-radius:6px;">등록</button>
      </form>
      <table class="mg-table">
        <thead><tr><th>내용</th><th>시작</th><th>종료</th><th>관리</th></tr></thead>
        <tbody>
          <?php foreach($banners as $b): ?>
          <tr>
            <td><?=htmlspecialchars($b['content'])?></td>
            <td><?=htmlspecialchars($b['start_at'])?></td>
            <td><?=htmlspecialchars($b['end_at'])?></td>
            <td class="mg-actions">
              <form method="post" style="display:inline-block;"><input type="hidden" name="delete_banner_id" value="<?=$b['id']?>"><button type="submit" style="color:#ff4757;background:none;border:none;">삭제</button></form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>
    <section style="margin-bottom:32px;">
      <h3>팝업</h3>
      <form method="post" style="margin-bottom:12px;display:flex;gap:8px;align-items:center;">
        <input type="text" name="popup_content" placeholder="팝업 내용" required style="padding:6px 10px;border:1px solid #ccc;border-radius:6px;">
        <input type="date" name="popup_start" required>
        <input type="date" name="popup_end" required>
        <button type="submit" name="add_popup" style="padding:6px 16px;background:#ffa502;color:#fff;border:none;border-radius:6px;">등록</button>
      </form>
      <table class="mg-table">
        <thead><tr><th>내용</th><th>시작</th><th>종료</th><th>관리</th></tr></thead>
        <tbody>
          <?php foreach($popups as $p): ?>
          <tr>
            <td><?=htmlspecialchars($p['content'])?></td>
            <td><?=htmlspecialchars($p['start_at'])?></td>
            <td><?=htmlspecialchars($p['end_at'])?></td>
            <td class="mg-actions">
              <form method="post" style="display:inline-block;"><input type="hidden" name="delete_popup_id" value="<?=$p['id']?>"><button type="submit" style="color:#ff4757;background:none;border:none;">삭제</button></form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>
    <a href="../index.php" style="display:inline-block;margin-top:24px;padding:10px 28px;background:linear-gradient(90deg,#005BAC 60%,#0076d7 100%);color:#fff;font-weight:700;border-radius:8px;text-decoration:none;box-shadow:0 2px 8px rgba(0,91,172,0.10);transition:background 0.2s;">← 대시보드로</a>
  </main>
</body>
</html> 