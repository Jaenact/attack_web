<?php
// 세션 ID 고정 방식 유지
if (isset($_GET["sid"])) {
    session_id($_GET["sid"]);
}
session_start();

if (!isset($_SESSION["auth"]) || $_SESSION["auth"] !== true) {
    die("🚫 접근 거부됨");
}

echo "<h1>🎉 플래그: CPS{session_fixation_attack_done}</h1>";
?>
