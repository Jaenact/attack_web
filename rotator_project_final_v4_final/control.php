<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/log.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

echo "<h2>PLC 상태 확인</h2>";
echo "<p>현재 RPM: " . htmlspecialchars(file_get_contents("rpm.txt")) . "</p>";
echo "<p>전원 상태: " . htmlspecialchars(file_get_contents("power.txt")) . "</p>";

if (isAdmin()) {
    echo '<form method="post">
        <input type="number" name="rpm" placeholder="회전수 (RPM)">
        <select name="action">
            <option value="on">ON</option>
            <option value="off">OFF</option>
        </select>
        <button type="submit">제어</button>
    </form>';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $rpm = $_POST['rpm'] ?? 0;

        file_put_contents("rpm.txt", $rpm);
        file_put_contents("power.txt", $action);

        write_log("장비 제어", "RPM: $rpm, 상태: $action");
        echo "<script>alert('장비 제어 완료'); location.href='control.php';</script>";
        exit();
    }
} else {
    echo "<p>※ 일반 사용자는 상태만 확인할 수 있습니다.</p>";
}
?>