<?php
// 외부에서 세션 ID 고정 가능
if (isset($_GET["sid"])) {
    session_id($_GET["sid"]);
}
session_start();

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id = $_POST["id"] ?? "";
    $pw = $_POST["pw"] ?? "";

    if ($id === "admin" && $pw === "admin1234") {
        $_SESSION["auth"] = true;
        header("Location: flag.php?sid=" . session_id());
        exit;
    } else {
        $error = "❌ 로그인 실패!";
    }
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>연구실 컴퓨터 로그인</title>
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../style.css">
</head>
<body>
  <h2>🔐 연구실 컴퓨터 로그인</h2>
  <form method="post" action="?sid=<?= htmlspecialchars($_GET['sid'] ?? '') ?>">
    <label>ID:</label>
    <input type="text" name="id" placeholder="admin">
    <label>PW:</label>
    <input type="text" name="pw" placeholder="비밀번호">
    <button type="submit">로그인</button>
  </form>

  <?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <br>
  <button onclick="toggleCode()">📂 문제 코드 보기</button>
  <pre id="code" style="display:none; background:#111; color:#0f0; padding:10px; border-radius:8px;">
if (isset($_GET["sid"])) {
    session_id($_GET["sid"]);
}
session_start();

if (isset($_SESSION["auth"]) && $_SESSION["auth"] === true) {
    // 로그인 성공 시
    ...
}
  </pre>

  <script>
    function toggleCode() {
      const c = document.getElementById("code");
      c.style.display = c.style.display === "none" ? "block" : "none";
    }
  </script>
  <div style="margin-top: 40px; text-align: center;">
  <a href="http://210.102.178.92:9999/wargame/ctf.php" style="
    display: inline-block;
    padding: 12px 24px;
    background-color: #00ffc3;
    color: #000;
    font-weight: bold;
    text-decoration: none;
    border-radius: 8px;
    box-shadow: 0 0 15px #00ffc388;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
  " onmouseover="this.style.transform='scale(1.05)'; this.style.boxShadow='0 0 25px #00ffc3aa';"
     onmouseout="this.style.transform='scale(1)'; this.style.boxShadow='0 0 15px #00ffc388';">
    ← 문제 목록으로 돌아가기
  </a>
</div>
</body>
</html>
