<?php
session_start();
require_once __DIR__ . '/../../src/db/db.php';
require_once __DIR__ . '/../../src/log/log_function.php';
if (!isset($_SESSION['admin'])) {
    echo "<script>alert('관리자만 접근 가능합니다.');location.href='index.php';</script>";
    exit();
}
// 검색/필터
$search = trim($_GET['search'] ?? '');
$role = $_GET['role'] ?? '';
$where = [];
$params = [];
if ($search) {
    $where[] = "(username LIKE :search OR name LIKE :search)";
    $params['search'] = "%$search%";
}
if ($role && in_array($role, ['admin','guest'])) {
    $where[] = "role = :role";
    $params['role'] = $role;
}
$where_sql = $where ? 'WHERE '.implode(' AND ', $where) : '';
$stmt = $pdo->prepare("SELECT * FROM users $where_sql ORDER BY created_at DESC");
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
// 역할 변경, 잠금/해제, 비번 초기화 처리
// 계정 삭제 처리
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_user'], $_POST['username'])) {
    $u = $_POST['username'];
    if ($u !== 'admin') {
        $pdo->prepare("DELETE FROM users WHERE username=?")->execute([$u]);
        writeLog($pdo, $_SESSION['admin'], '계정삭제', '성공', $u);
        echo "<script>alert('계정이 완전히 삭제되었습니다. 복구할 수 없습니다.');location.href='user_management.php';</script>";
        exit();
    } else {
        echo "<script>alert('관리자 계정은 삭제할 수 없습니다.');location.href='user_management.php';</script>";
        exit();
    }
}
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (isset($_POST['change_role'], $_POST['username'], $_POST['role'])) {
        $u = $_POST['username']; $r = $_POST['role'];
        if (in_array($r,['admin','guest'])) {
            $pdo->prepare("UPDATE users SET role=? WHERE username=?")->execute([$r,$u]);
            writeLog($pdo, $_SESSION['admin'], '권한변경', '성공', "$u → $r");
        }
    }
    if (isset($_POST['toggle_active'], $_POST['username'])) {
        $u = $_POST['username'];
        $row = $pdo->prepare("SELECT is_active FROM users WHERE username=?");
        $row->execute([$u]);
        $cur = $row->fetchColumn();
        $new = $cur?0:1;
        $pdo->prepare("UPDATE users SET is_active=? WHERE username=?")->execute([$new,$u]);
        writeLog($pdo, $_SESSION['admin'], '계정상태변경', '성공', "$u → ".($new?'활성':'비활성'));
    }
    if (isset($_POST['reset_pw'], $_POST['username'])) {
        $u = $_POST['username'];
        $newpw = bin2hex(random_bytes(4));
        $pdo->prepare("UPDATE users SET password=? WHERE username=?")->execute([password_hash($newpw,PASSWORD_DEFAULT),$u]);
        writeLog($pdo, $_SESSION['admin'], '비밀번호초기화', '성공', "$u");
        echo "<script>alert('새 비밀번호: $newpw');location.href='user_management.php';</script>";
        exit();
    }
    if (isset($_POST['set_pw'], $_POST['username'], $_POST['newpw'])) {
        $u = $_POST['username'];
        $newpw = $_POST['newpw'];
        $pdo->prepare("UPDATE users SET password=? WHERE username=?")->execute([password_hash($newpw,PASSWORD_DEFAULT),$u]);
        writeLog($pdo, $_SESSION['admin'], '비밀번호초기화', '성공', "$u");
        echo "<script>alert('비밀번호가 변경되었습니다.');location.href='user_management.php';</script>";
        exit();
    }
    header('Location: user_management.php'); exit();
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>사용자 관리</title>
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <style>
    body { background: #f5f7fa; color: #222; font-family: 'Noto Sans KR', sans-serif; }
    main { max-width:900px; margin:40px auto 0 auto; background:#fff; border-radius:16px; box-shadow:0 4px 24px rgba(0,91,172,0.10); padding:40px 32px; }
    h2 { font-size:2rem; font-weight:700; color:#005BAC; margin-bottom:18px; }
    .user-table{width:100%;border-collapse:collapse;margin-top:24px; background:#fff; border-radius:10px; overflow:hidden; box-shadow:0 2px 16px rgba(0,0,0,0.06);}
    .user-table th,.user-table td{border:1px solid #e1e5e9;padding:12px 10px;text-align:center;}
    .user-table th{background:#e3f0ff;color:#005BAC;font-weight:700;}
    .user-table tr{border-bottom:1px solid #e1e5e9;}
    .user-table tr:hover{background:#f0f8ff;}
    .user-actions button, .user-actions form { display:inline-block; margin:0 2px; }
    .btn-setpw { background:#e3f0ff; color:#005BAC; border:1px solid #7ecbff; border-radius:6px; padding:4px 12px; font-weight:500; cursor:pointer; }
    .btn-setpw:hover { background:#005BAC; color:#fff; }
    .btn-delete { background:#fff; color:#ff5c5c; border:1px solid #ff5c5c; border-radius:6px; padding:4px 12px; font-weight:500; cursor:pointer; }
    .btn-delete:hover { background:#ff5c5c; color:#fff; }
    .role-select { padding: 6px 16px; border: 1.5px solid #005BAC; border-radius: 8px; background: #e3f0ff; color: #005BAC; font-weight: 600; font-size: 1rem; transition: border 0.2s, box-shadow 0.2s; box-shadow: 0 1px 4px rgba(0,91,172,0.07); outline: none; margin: 0 2px; }
    .role-select:focus, .role-select:hover { border: 2px solid #0076d7; background: #e3f0ff; }
    input[type='text'], input[type='password'] { background:#fff; color:#005BAC; border:1px solid #7ecbff; border-radius:6px; padding:6px 10px; }
    .pw-form { display:inline-block; }
  </style>
</head>
<body style="background:linear-gradient(135deg,rgba(0,91,172,0.08) 0%,rgba(0,91,172,0.13) 100%);min-height:100vh;">
  <main style="max-width:900px;margin:40px auto 0 auto;background:#fff;border-radius:16px;box-shadow:0 4px 24px rgba(0,91,172,0.10);padding:40px 32px;">
    <h2 style="font-size:2rem;font-weight:700;color:#005BAC;margin-bottom:18px;">사용자 관리</h2>
    <form method="get" style="margin-bottom:18px;display:flex;gap:8px;align-items:center;">
      <input type="text" name="search" value="<?=htmlspecialchars($search)?>" placeholder="이름/아이디 검색" style="padding:6px 10px;border:1px solid #ccc;border-radius:6px;">
      <select name="role" style="padding:6px 10px;border:1px solid #ccc;border-radius:6px;">
        <option value="">전체</option>
        <option value="admin" <?=$role==='admin'?'selected':''?>>관리자</option>
        <option value="guest" <?=$role==='guest'?'selected':''?>>게스트</option>
      </select>
      <button type="submit" style="padding:6px 16px;background:#005BAC;color:#fff;border:none;border-radius:6px;">검색</button>
      <a href="register_user.php" style="margin-left:auto;padding:6px 16px;background:#43e97b;color:#fff;border:none;border-radius:6px;text-decoration:none;">+ 사용자 등록</a>
    </form>
    <table class="user-table">
      <thead>
        <tr><th>아이디</th><th>이름</th><th>역할</th><th>상태</th><th>연락처</th><th>가입일</th><th>관리</th></tr>
      </thead>
      <tbody>
        <?php foreach($users as $u): ?>
        <tr<?=!$u['is_active']?' style="opacity:0.5;"':''?>>
          <td><?=htmlspecialchars($u['username'])?></td>
          <td><?=htmlspecialchars($u['name'])?></td>
          <td>
            <form method="post" style="display:inline-block;">
              <input type="hidden" name="username" value="<?=htmlspecialchars($u['username'])?>">
              <select name="role" onchange="this.form.submit()" <?=($u['username']==='admin')?'disabled':'';?> class="role-select">
                <option value="admin" <?=$u['role']==='admin'?'selected':''?>>관리자</option>
                <option value="guest" <?=$u['role']==='guest'?'selected':''?>>게스트</option>
              </select>
              <input type="hidden" name="change_role" value="1">
            </form>
          </td>
          <td>
            <form method="post" style="display:inline-block;">
              <input type="hidden" name="username" value="<?=htmlspecialchars($u['username'])?>">
              <button type="submit" name="toggle_active" style="padding:2px 10px;background:<?=$u['is_active']?'#43e97b':'#ff4757'?>;color:#fff;border:none;border-radius:6px;cursor:pointer;" <?=($u['username']==='admin')?'disabled':'';?>><?=$u['is_active']?'활성':'비활성'?></button>
            </form>
          </td>
          <td><?=htmlspecialchars($u['phone'])?></td>
          <td><?=date('Y-m-d',strtotime($u['created_at']))?></td>
          <td class="user-actions">
            <form method="post" class="pw-form" style="margin-bottom:4px;">
              <input type="hidden" name="username" value="<?=htmlspecialchars($u['username'])?>">
              <input type="password" name="newpw" placeholder="새 비번" required style="width:90px;">
              <button type="submit" name="set_pw" class="btn-setpw">비번초기화</button>
            </form>
            <?php if($u['username']!=='admin'): ?>
            <form method="post" style="display:inline-block;">
              <input type="hidden" name="username" value="<?=htmlspecialchars($u['username'])?>">
              <button type="submit" name="delete_user" class="btn-delete" onclick="return confirm('정말로 이 계정을 완전히 삭제하시겠습니까? 삭제 후 복구할 수 없습니다.')">삭제</button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <a href="../index.php" style="display:inline-block;margin-top:24px;padding:10px 28px;background:linear-gradient(90deg,#005BAC 60%,#0076d7 100%);color:#fff;font-weight:700;border-radius:8px;text-decoration:none;box-shadow:0 2px 8px rgba(0,91,172,0.10);transition:background 0.2s;">← 대시보드로</a>
  </main>
  <style>
    .role-select {
      padding: 6px 16px;
      border: 1.5px solid #005BAC;
      border-radius: 8px;
      background: linear-gradient(90deg,#e3f0ff 60%,#f8f9fa 100%);
      color: #005BAC;
      font-weight: 600;
      font-size: 1rem;
      transition: border 0.2s, box-shadow 0.2s;
      box-shadow: 0 1px 4px rgba(0,91,172,0.07);
      outline: none;
      margin: 0 2px;
    }
    .role-select:focus {
      border: 2px solid #0076d7;
      box-shadow: 0 2px 8px rgba(0,91,172,0.13);
      background: #e3f0ff;
    }
    .role-select:hover {
      border: 2px solid #0076d7;
      background: #e3f0ff;
    }
  </style>
</body>
</html> 