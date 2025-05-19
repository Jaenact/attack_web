<?php
/* ────────────────────────────────────
   1) 세션 초기화(로그인 정보 삭제)
   2) 완전히 파기 후
   3) login.php로 리디렉션
   ──────────────────────────────────── */
session_start();

/* 1) 모든 세션 변수 비우기 */
$_SESSION = [];
session_unset();

/* 2) 세션 쿠키까지 제거(옵션) */
if (ini_get("session.use_cookies")) {
  $params = session_get_cookie_params();
  setcookie(session_name(), '', time() - 42000,
    $params["path"], $params["domain"],
    $params["secure"], $params["httponly"]
  );
}

//취약점점

/* 3) 세션 파기 */
session_destroy();

/* 4) 로그인 페이지로 즉시 이동 */
header("Location: login.php");
exit;
?>
