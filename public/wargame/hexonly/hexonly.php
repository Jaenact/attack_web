<?php
$result = "";

if (isset($_GET["img"])) {
    $code = $_GET["img"];

    // 필터링
    $banned_keywords = ["open", "flag", "print", "read", "import", "__", "system", "file", "cat"];
    foreach ($banned_keywords as $word) {
        if (stripos($code, $word) !== false) {
            $result = "🚫 Blocked by filter!";
            goto render;
        }
    }

    // 허용 형식: exec("\x..") only
    if (!preg_match('/^exec\((\"|\')\\\\x[0-9a-fA-F]{2}(\\\\x[0-9a-fA-F]{2})*(\"|\')\)$/', $code)) {
        $result = "🚫 Only hex-encoded exec() payloads are allowed.";
        goto render;
    }

    // 실행
    $escaped = escapeshellarg($code);
    $command = "python3 -c $escaped";
    $output = shell_exec($command);
    $result = $output ?: "(no output)";
}

render:
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Hex Only - CTF Challenge</title>
    <style>
        body {
            background-color: #111;
            color: #eee;
            font-family: monospace;
            padding: 30px;
        }
        .container {
            max-width: 800px;
            margin: auto;
        }
        textarea, input[type=submit] {
            width: 100%;
            font-family: monospace;
            font-size: 1em;
            margin-top: 10px;
        }
        textarea {
            background-color: #1e1e1e;
            color: #00ffcc;
            border: 1px solid #444;
            padding: 10px;
            height: 150px;
        }
        input[type=submit] {
            background-color: #222;
            color: #fff;
            border: 1px solid #555;
            padding: 10px;
            cursor: pointer;
        }
        .code-box {
            background-color: #1b1b1b;
            padding: 15px;
            margin-top: 20px;
            border: 1px solid #333;
        }
        .output {
            background-color: #1b1b1b;
            color: #00ff00;
            padding: 15px;
            margin-top: 20px;
            white-space: pre-wrap;
            border: 1px solid #333;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>🧩 Challenge: Hex Only</h2>
        <p><i>The file <code>flag.txt</code> is in the same directory as this script.</i></p>

        <form method="GET">
            <textarea name="img" placeholder="Enter your payload..."></textarea>
            <input type="submit" value="Submit">
        </form>

        <?php if ($result): ?>
            <div class="output"><?= htmlspecialchars($result) ?></div>
        <?php endif; ?>

        <div class="code-box">
<pre>
&lt;?php
$code = $_GET["img"];

$banned_keywords = ["open", "flag", "print", "read", "import", "__", "system", "file", "cat"];
foreach ($banned_keywords as $word) {
    if (stripos($code, $word) !== false) {
        die("🚫 Blocked by filter!");
    }
}

if (!preg_match('/^exec\((\"|\')\\\\x[0-9a-fA-F]{2}(\\\\x[0-9a-fA-F]{2})*(\"|\')\)$/', $code)) {
    die("🚫 Only hex-encoded exec() payloads are allowed.");
}

$escaped = escapeshellarg($code);
$output = shell_exec("python3 -c $escaped");
?&gt;
</pre>
        </div>
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