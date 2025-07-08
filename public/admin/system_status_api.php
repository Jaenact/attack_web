<?php
session_start();
if (!isset($_SESSION['admin'])) { http_response_code(403); echo json_encode(['error'=>'권한없음']); exit(); }
header('Content-Type: application/json; charset=utf-8');
// CPU 사용률
$load = sys_getloadavg();
// 메모리
$meminfo = @file_get_contents('/proc/meminfo');
$mem_total = $mem_free = $mem_available = 0;
if ($meminfo) {
    preg_match('/MemTotal:\s+(\d+)/', $meminfo, $m1);
    preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $m2);
    $mem_total = isset($m1[1]) ? (int)$m1[1] : 0;
    $mem_available = isset($m2[1]) ? (int)$m2[1] : 0;
    $mem_used = $mem_total - $mem_available;
} else {
    $mem_total = $mem_available = $mem_used = 0;
}
// 디스크
$disk_total = disk_total_space('/');
$disk_free = disk_free_space('/');
$disk_used = $disk_total - $disk_free;
// 업타임
$uptime = @file_get_contents('/proc/uptime');
$uptime_sec = $uptime ? (int)floatval(explode(' ', $uptime)[0]) : 0;
$uptime_str = sprintf('%d일 %02d:%02d:%02d', $uptime_sec/86400, ($uptime_sec/3600)%24, ($uptime_sec/60)%60, $uptime_sec%60);
// 네트워크(eth0 기준)
$net = @file('/proc/net/dev');
$rx = $tx = 0;
if ($net) {
    foreach($net as $line) {
        if (strpos($line,'eth0:')!==false) {
            $parts = preg_split('/\s+/', trim($line));
            $rx = (int)$parts[1]; $tx = (int)$parts[9];
        }
    }
}
echo json_encode([
    'cpu_load' => $load[0],
    'mem_total' => $mem_total,
    'mem_used' => $mem_used,
    'mem_available' => $mem_available,
    'disk_total' => $disk_total,
    'disk_used' => $disk_used,
    'uptime' => $uptime_str,
    'net_rx' => $rx,
    'net_tx' => $tx
]); 