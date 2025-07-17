<?php session_start(); ?>
<?php
require_once '../src/db/db.php';
require_once '../src/log/log_function.php';
$maintenance_active = false;
$stmt = $pdo->query("SELECT * FROM maintenance WHERE is_active=1 LIMIT 1");
if ($stmt->fetch()) {
    $maintenance_active = true;
}
if (!isset($_POST['admin_maintenance_login'])) {
    if ($maintenance_active) {
        header('Location: maintenance.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>PLC 로그인</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://fonts.googleapis.com/css?family=Noto+Sans+KR:400,700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/main.css">
</head>
<body>
  <header class="header" role="banner">
    <a class="logo" aria-label="홈으로">
      PLC Rotator System
    </a>
  </header>
  <main>
    <h2 class="text-center" style="padding-top: 50px;">로그인</h2>
    <form action="auth.php" method="post" class="card" style="max-width:400px; margin:0 auto; display:flex; flex-direction:column; gap:2px; padding:12px 48px 10px 48px; min-height:unset";>
      <div class="input-group" style="width:100%; margin-bottom:2px;">
        <label for="username" style="margin-bottom:2px; font-size:1.01rem;">아이디</label>
        <input type="text" id="username" name="username" class="input" placeholder="아이디" required autocomplete="username" style="width:100%; font-size:1.05rem; padding:6px 18px;">
      </div>
      <div class="input-group" style="width:100%; margin-bottom:2px;">
        <label for="password" style="margin-bottom:2px; font-size:1.01rem;">비밀번호</label>
        <input type="password" id="password" name="password" class="input" placeholder="비밀번호" required autocomplete="current-password" style="width:100%; font-size:1.05rem; padding:6px 18px;">
      </div>
      <button type="submit" class="btn btn-login" style="width:100%; font-size:1.05rem; padding:8px 0; margin-top:2px;">로그인</button>
    </form>
    <a href="make_account.php" class="text-center" style="display:block; margin-top:18px; color:#005BAC; text-decoration:underline;">회원가입</a>
  </main>
  <!-- 추가적인 반응형 스타일은 main.css에 위임 -->
</body>
</html>
