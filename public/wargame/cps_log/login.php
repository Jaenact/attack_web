<?php
session_start();
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id = $_POST["id"] ?? "";
    $pw = $_POST["pw"] ?? "";

    if ($id === "admin" && $pw === "finishcps") {
        $_SESSION["auth"] = true;
        header("Location: admin.php");
        exit;
    } else {
        $timestamp = date("d-M-Y H:i:s");
        $remote_ip = $_SERVER["REMOTE_ADDR"] ?? "UNKNOWN";
        $request_uri = $_SERVER["REQUEST_URI"] ?? "UNKNOWN";
        $user_agent = $_SERVER["HTTP_USER_AGENT"] ?? "UNKNOWN";

        $log = "[$timestamp] POST $request_uri from $remote_ip → FAIL (id=$id, pw=$pw) UA=\"$user_agent\"\n";
        error_log($log, 3, "/wargame/cps_log/error.log");

        $error = "❌ 로그인 실패";
    }
}

// 문제 코드 출력 처리
$raw_code = file_get_contents(__FILE__);
$sanitized_code = str_replace('pw === "finishcps"', 'pw === "fakepw"', $raw_code);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>연구실 로그인</title>
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../style.css">
  <style>
    body {
      font-family: 'Orbitron', sans-serif;
      padding: 20px;
    }
    form {
      margin-bottom: 20px;
    }
    .code-box {
      background-color: #1e1e1e;
      color: #d4d4d4;
      padding: 15px;
      border-radius: 10px;
      font-family: monospace;
      white-space: pre-wrap;
      overflow-x: auto;
      display: none; /* 초기에 숨김 */
    }
    .view-btn {
      margin-top: 15px;
      display: inline-block;
      padding: 8px 14px;
      background-color: #333;
      color: white;
      text-decoration: none;
      border-radius: 6px;
      cursor: pointer;
    }
    .view-btn:hover {
      background-color: #555;
    }
  </style>
  <script>
    function toggleCodeBox() {
      const codeBox = document.getElementById("codeBox");
      const btn = document.getElementById("toggleBtn");
      if (codeBox.style.display === "none") {
        codeBox.style.display = "block";
        btn.textContent = "📄 문제 파일 접기";
      } else {
        codeBox.style.display = "none";
        btn.textContent = "📄 문제 파일 보기";
      }
    }
  </script>
</head>
<body>
  <h2>🔐 연구실 컴퓨터 로그인</h2>
  <form method="post" action="">
    <label>ID:</label>
    <input type="text" name="id" required>
    <label>PW:</label>
    <input type="password" name="pw" required>
    <input type="submit" value="로그인">
    <?php if ($error): ?>
      <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
  </form>

  <button id="toggleBtn" class="view-btn" onclick="toggleCodeBox()">📄 문제 파일 보기</button>

  <div class="code-box" id="codeBox">
    <code><?= htmlspecialchars($sanitized_code) ?></code>
  </div>
</body>

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
</html>


