<?php
$db = new SQLite3(__DIR__ . '/ctf.db');

// --- 플래그 등록 처리 ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["flag_value"], $_POST["point"])) {
    $flag_value = trim($_POST["flag_value"]);
    $point = intval($_POST["point"]);

    if ($flag_value !== "") {
        $stmt = $db->prepare("INSERT OR IGNORE INTO flags (flag_value, point) VALUES (:flag, :point)");
        $stmt->bindValue(':flag', $flag_value, SQLITE3_TEXT);
        $stmt->bindValue(':point', $point, SQLITE3_INTEGER);
        $stmt->execute();
        $msg = "✅ 플래그 등록 완료!";
    }
}

// --- 사용자 삭제 처리 ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_user"])) {
    $del_name = trim($_POST["delete_user"]);
    if ($del_name !== "") {
        $stmt = $db->prepare("DELETE FROM users WHERE name = :name");
        $stmt->bindValue(":name", $del_name, SQLITE3_TEXT);
        $stmt->execute();
        $msg = "🗑️ 사용자 '{$del_name}' 삭제 완료!";
    }
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>관리자 패널 - CTF</title>
  <style>
    body {
      background-color: #0d0d0d;
      color: #eee;
      font-family: monospace;
      padding: 40px;
    }
    h1, h2 {
      color: #00ffc3;
    }
    input, button {
      padding: 8px;
      margin: 5px;
      background-color: #111;
      color: #00ffc3;
      border: 1px solid #00ffc355;
      border-radius: 4px;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 20px;
      background-color: #111;
    }
    th, td {
      border: 1px solid #333;
      padding: 10px;
    }
    th {
      color: #00ffc3;
    }
    .message {
      margin: 10px 0;
      color: #66ff99;
    }
  </style>
</head>
<body>

  <h1>🔐 CTF 관리자 패널</h1>

  <?php if (isset($msg)): ?>
    <div class="message"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <h2>➕ 플래그 등록</h2>
  <form method="POST">
    플래그 값: <input type="text" name="flag_value" required>
    점수: <input type="number" name="point" required>
    <button type="submit">등록</button>
  </form>

  <h2>📋 등록된 플래그 목록</h2>
  <table>
    <tr><th>flag_value</th><th>point</th></tr>
    <?php
    $flags = $db->query("SELECT flag_value, point FROM flags");
    while ($row = $flags->fetchArray(SQLITE3_ASSOC)) {
        echo "<tr><td>" . htmlspecialchars($row['flag_value']) . "</td><td>{$row['point']}</td></tr>";
    }
    ?>
  </table>

  <h2>🧹 사용자 점수 삭제</h2>
  <form method="POST">
    사용자 이름: <input type="text" name="delete_user" required>
    <button type="submit">삭제</button>
  </form>

</body>
</html>
