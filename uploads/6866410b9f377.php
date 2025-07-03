<?php
if (!isset($_GET['file'])) {
    echo "No file specified.";
    exit;
}

$filepath = $_GET['file'];

// 상대경로도 허용되도록 현재 디렉토리 기준으로 정규화
$realpath = realpath($filepath);

// 파일이 실제 존재하는지 확인
if ($realpath === false || !file_exists($realpath)) {
    echo "File not found.";
    exit;
}

// 다운로드용 헤더 설정
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($realpath) . '"');
header('Content-Length: ' . filesize($realpath));

// 파일 전송
readfile($realpath);
exit;
?>
