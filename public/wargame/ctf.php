<?php
$db = new SQLite3(__DIR__ . '/ctf.db');
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $flag = trim($_POST['flag'] ?? '');

    // 유효한 플래그인지 확인
    $stmt = $db->prepare("SELECT point FROM flags WHERE flag_value = :flag");
    $stmt->bindValue(":flag", $flag);
    $flagResult = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

    if ($flagResult) {
        $point = (int)$flagResult['point'];

        // 이미 제출했는지 확인
        $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM submissions WHERE name = :name AND flag_value = :flag");
        $stmt->bindValue(":name", $name);
        $stmt->bindValue(":flag", $flag);
        $exists = $stmt->execute()->fetchArray(SQLITE3_ASSOC)['cnt'];

        if ($exists > 0) {
            $msg = "⚠️ 이미 제출한 플래그입니다.";
        } else {
            // submissions 테이블에 기록
            $stmt = $db->prepare("INSERT INTO submissions (name, flag_value, submitted_at) VALUES (:name, :flag, datetime('now'))");
            $stmt->bindValue(":name", $name);
            $stmt->bindValue(":flag", $flag);
            $stmt->execute();

            // users 테이블 점수 누적 (없으면 삽입, 있으면 갱신)
            $db->exec("CREATE TABLE IF NOT EXISTS users (name TEXT PRIMARY KEY, score INTEGER)");
            $stmt = $db->prepare("INSERT INTO users (name, score) VALUES (:name, :score)
                                  ON CONFLICT(name) DO UPDATE SET score = score + :score");
            $stmt->bindValue(":name", $name);
            $stmt->bindValue(":score", $point);
            $stmt->execute();

            $msg = "✅ 플래그 정답입니다! ({$point}점)";
        }
    } else {
        $msg = "❌ 플래그가 틀렸습니다.";
    }
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8" />
  <title>CTF 문제 목록</title>
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@600&display=swap" rel="stylesheet">
  <style>
    /* 생략 없이 위 스타일 그대로 유지 (기존 코드 복사됨) */
    * { box-sizing: border-box; }
    body {
      background-color: #0b0b0b;
      color: #e0e0e0;
      font-family: 'Orbitron', sans-serif;
      margin: 0;
      padding: 40px;
      position: relative;
      overflow-x: hidden;
    }
    body::before {
      content: "";
      position: fixed;
      top: 0; left: 0;
      width: 200%; height: 200%;
      background: repeating-linear-gradient(0deg, #0f0f0f 0px, #141414 2px);
      animation: scrollBG 60s linear infinite;
      z-index: -1; opacity: 0.3;
    }
    @keyframes scrollBG {
      from { transform: translateY(0); }
      to { transform: translateY(-100px); }
    }
    h1, h2 {
      color: #00ffc3;
      text-shadow: 0 0 10px #00ffc388, 0 0 20px #00ffc355, 0 0 30px #00ffc322;
    }
    table {
      width: 100%; border-collapse: collapse;
      margin-top: 20px; border: 1px solid #333;
      background-color: #111;
      border-radius: 10px;
      overflow: hidden;
    }
    th, td {
      padding: 14px 16px;
      text-align: left;
      border-bottom: 1px solid #222;
    }
    th {
      background: #0f0f0f;
      color: #00ffc3;
      text-shadow: 0 0 5px #00ffc366;
    }
    tr:hover {
      background-color: #11111199;
    }
    a {
      color: #66ccff;
      text-decoration: none;
      font-weight: bold;
      text-shadow: 0 0 5px #66ccff88;
    }
    a:hover {
      color: #33bbff;
      text-decoration: underline;
    }
    .form-section {
      margin-top: 50px;
      display: flex;
      align-items: center;
      gap: 16px;
    }
    .flag-form input[type=text] {
      padding: 10px;
      width: 220px;
      background: #111;
      border: 1px solid #00ffc355;
      border-radius: 5px;
      color: #00ffc3;
      text-shadow: 0 0 3px #00ffc377;
    }
    .flag-form input[type=submit] {
      padding: 10px 20px;
      background-color: #00ffc3;
      color: #0b0b0b;
      font-weight: bold;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      box-shadow: 0 0 10px #00ffc344;
    }
    .flag-form input[type=submit]:hover {
      background-color: #00e6b2;
    }
    .floating-msg {
      position: fixed;
      top: 40px;
      left: 50%;
      transform: translateX(-50%);
      padding: 14px 18px;
      background-color: #1e1e1e;
      color: #fff;
      border-radius: 8px;
      box-shadow: 0 0 10px #00ffc388;
      z-index: 999;
      opacity: 0;
      animation: fadeInOut 3s ease forwards;
      font-weight: bold;
    }

    .floating-msg.success { background-color: #003f3a; color: #00ffc3; }
    .floating-msg.error   { background-color: #3a0000; color: #ff6060; }
    .floating-msg.warn    { background-color: #333000; color: #ffff66; }
    @keyframes fadeInOut {
      0% { opacity: 0; transform: translateY(-10px); }
      10% { opacity: 1; transform: translateY(0); }
      90% { opacity: 1; }
      100% { opacity: 0; transform: translateY(-10px); }
    }
    #transitionCover {
      position: fixed;
      top: 0;
      left: 0;
      width: 100vw;
      height: 100vh;
      background-color: #0b0b0b;
      z-index: 9999;
      pointer-events: none;
      opacity: 0;
      transition: opacity 0.6s ease-in-out;
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: 'Orbitron', sans-serif;
      font-size: 22px;
      color: #00ffc3;
      text-shadow: 0 0 5px #00ffc388, 0 0 10px #00ffc355;
    }
    #transitionCover.loading::after {
      content: "🔄 문제 로딩 중...";
      animation: blink 1.5s infinite;
    }
    @keyframes blink {
      0% { opacity: 1; }
      50% { opacity: 0.4; }
      100% { opacity: 1; }
    }
  </style>
</head>
<body>
  <h1>📂 CTF 문제 목록</h1>
  <table>
    <thead>
      <tr><th>문제 이름</th><th>작성자</th><th>난이도</th><th>문제 URL</th></tr>
    </thead>
    <tbody>
      <tr>
        <td>Hex Only</td><td>jaehyun</td><td>중</td>
        <td><a href="#" onclick="smoothRedirect('hexonly/hexonly.php')">문제 보기</a></td>
      </tr>
      <tr>
        <td>CPS Log</td><td>jaehyun</td><td>하</td>
        <td><a href="#" onclick="smoothRedirect('cps_log/login.php')">문제 보기</a></td>
      </tr>
      <tr>
        <td>0e의 저주</td><td>jaehyun</td><td>중</td>
        <td><a href="#" onclick="smoothRedirect('0x/login.php')">문제 보기</a></td>
      </tr>
      <tr>
        <td>Shadow Session</td><td>jaehyun</td><td>중</td>
        <td><a href="#" onclick="smoothRedirect('shadow_session/login.php')">문제 보기</a></td>
      </tr>
    </tbody>
  </table>

  <h2>🏁 플래그 제출</h2>
  <form method="POST" action="ctf.php" class="form-section flag-form">
    <input type="text" name="name" placeholder="이름" required>
    <input type="text" name="flag" placeholder="플래그" required>
    <input type="submit" value="제출">
  </form>

  <h2 style="margin-top: 60px;">📊 사용자 순위</h2>
  <table>
    <thead><tr><th>🥇 순위</th><th>이름</th><th>점수</th></tr></thead>
    <tbody>
      <?php
      $rank = 1;
      $res = $db->query("SELECT name, SUM(score) as total FROM users GROUP BY name ORDER BY total DESC");
      while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $highlight = $rank <= 3 ? ' style="background-color:#003322; font-weight:bold;"' : '';
        echo "<tr$highlight><td>{$rank}</td><td>" . htmlspecialchars($row['name']) . "</td><td>{$row['total']}</td></tr>";
        $rank++;
      }
      ?>
    </tbody>
  </table>

  <div id="transitionCover"></div>
  <script>
    function smoothRedirect(url) {
      const cover = document.getElementById("transitionCover");
      cover.classList.add("loading");
      cover.style.opacity = "1";
      cover.style.pointerEvents = "auto";
      setTimeout(() => { window.location.href = url; }, 1300);
    }
  </script>

  <?php if (!empty($msg)): ?>
    <div class="floating-msg <?= strpos($msg,'✅')!==false ? 'success' : (strpos($msg,'⚠️')!==false ? 'warn' : 'error') ?>">
      <?= strip_tags($msg) ?>
    </div>
  <?php endif; ?>
</body>
</html>
