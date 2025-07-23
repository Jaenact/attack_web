<?php
session_start();
if (!isset($_SESSION["auth"]) || $_SESSION["auth"] !== true) {
    die("🚫 접근 거부됨");
}
echo "<h1>🎉 플래그: CPS{php_type_juggling_success}</h1>";
?>
