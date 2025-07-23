<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8" />
  <title>CTF 문제 목록</title>
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@600&display=swap" rel="stylesheet">
  <style>
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
      top: 20px;
      right: 20px;
      padding: 14px 18px;
      background-color: #1e1e1e;
      color: #fff;
      border-radius: 8px;
      box-shadow: 0 0 10px #00ffc388;
      z-index: 999;
      opacity: 0;
      animation: fadeInOut 3s ease forwards;
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
    </tbody>
  </table>

  <h2>🏁 플래그 제출</h2>
  <form method="POST" class="form-section flag-form">
    <input type="text" name="name" placeholder="이름" required>
    <input type="text" name="flag" placeholder="플래그" required>
    <input type="submit" value="제출">
  </form>

  <h2 style="margin-top: 60px;">📊 사용자 순위</h2>
  <table>
    <thead><tr><th>🥇 순위</th><th>이름</th><th>점수</th></tr></thead>
    <tbody>
      <?php
      $db = new SQLite3(__DIR__ . '/ctf.db');
      $rank = 1;
      $res = $db->query("SELECT name, score FROM users ORDER BY score DESC");
      while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $highlight = $rank <= 3 ? ' style="background-color:#003322; font-weight:bold;"' : '';
        echo "<tr$highlight><td>{$rank}</td><td>" . htmlspecialchars($row['name']) . "</td><td>{$row['score']}</td></tr>";
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
