<?php
session_start();
require_once '../db/db.php';
require_once '../log/log_function.php';

// 관리자만 접근 가능
if (!isset($_SESSION['admin'])) {
    echo "<script>alert('관리자 계정이 아닙니다.'); history.back();</script>";
    exit();
}

// IP 주소 마스킹 함수
function maskIP($ip) {
    if (empty($ip) || $ip === 'Unknown') {
        return 'N/A';
    }
    
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $parts = explode('.', $ip);
        if (count($parts) === 4) {
            return $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.xxx';
        }
    }
    
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $parts = explode(':', $ip);
        if (count($parts) >= 2) {
            $lastPart = array_pop($parts);
            return implode(':', $parts) . ':xxx';
        }
    }
    
    return $ip;
}

// 계정 삭제 처리
if (isset($_GET['delete_user'])) {
    $user_id = $_GET['delete_user'];
    $user_type = $_GET['user_type'];
    
    if ($user_type === 'admin') {
        $stmt = $pdo->prepare("DELETE FROM admins WHERE id = :id");
    } else {
        $stmt = $pdo->prepare("DELETE FROM guests WHERE id = :id");
    }
    
    $stmt->execute(['id' => $user_id]);
    
    writeLog($pdo, $_SESSION['admin'], "계정 삭제 - 타입: $user_type, ID: $user_id");
    echo "<script>alert('계정이 삭제되었습니다.'); location.href='user_management.php';</script>";
    exit();
}

// 비밀번호 재설정 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $user_id = $_POST['user_id'];
    $user_type = $_POST['user_type'];
    $new_password = $_POST['new_password'];
    
    if ($user_type === 'admin') {
        $stmt = $pdo->prepare("UPDATE admins SET password = :password WHERE id = :id");
    } else {
        $stmt = $pdo->prepare("UPDATE guests SET password = :password WHERE id = :id");
    }
    
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt->execute(['password' => $hashed_password, 'id' => $user_id]);
    
    writeLog($pdo, $_SESSION['admin'], "비밀번호 재설정 - 타입: $user_type, ID: $user_id");
    echo "<script>alert('비밀번호가 재설정되었습니다.'); location.href='user_management.php';</script>";
    exit();
}

// 페이지네이션 설정
$users_per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $users_per_page;

// 전체 사용자 수 조회
$total_admins = $pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
$total_guests = $pdo->query("SELECT COUNT(*) FROM guests")->fetchColumn();
$total_users = $total_admins + $total_guests;
$total_pages = ceil($total_users / $users_per_page);

// 사용자 목록 조회 (관리자 + 게스트)
$sql = "
    (SELECT id, username, password, 'admin' as type, created_at, '관리자' as type_name FROM admins)
    UNION ALL
    (SELECT id, username, password, 'guest' as type, created_at, '게스트' as type_name FROM guests)
    ORDER BY created_at DESC
    LIMIT :limit OFFSET :offset
";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':limit', $users_per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll();

// 각 사용자의 최근 활동 조회
function getRecentActivity($pdo, $username, $limit = 5) {
    $stmt = $pdo->prepare("SELECT log_message, created_at FROM logs WHERE username = :username ORDER BY created_at DESC LIMIT :limit");
    $stmt->bindValue(':username', $username, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

// 각 사용자의 활동 통계 조회
function getUserStats($pdo, $username) {
    $stats = [];
    
    // 총 활동 수
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM logs WHERE username = :username");
    $stmt->execute(['username' => $username]);
    $stats['total_activities'] = $stmt->fetchColumn();
    
    // 마지막 활동 시간
    $stmt = $pdo->prepare("SELECT created_at FROM logs WHERE username = :username ORDER BY created_at DESC LIMIT 1");
    $stmt->execute(['username' => $username]);
    $stats['last_activity'] = $stmt->fetchColumn();
    
    // 활동 유형별 통계
    $stmt = $pdo->prepare("
        SELECT 
            CASE 
                WHEN log_message LIKE '%로그인 성공%' THEN '로그인'
                WHEN log_message LIKE '%로그아웃%' THEN '로그아웃'
                WHEN log_message LIKE '%고장 접수%' THEN '고장접수'
                WHEN log_message LIKE '%고장 수정%' THEN '고장수정'
                WHEN log_message LIKE '%고장 삭제%' THEN '고장삭제'
                WHEN log_message LIKE '%장비 제어%' THEN '장비제어'
                ELSE '기타'
            END as activity_type,
            COUNT(*) as count
        FROM logs 
        WHERE username = :username 
        GROUP BY activity_type
    ");
    $stmt->execute(['username' => $username]);
    $stats['activity_types'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $stats;
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>사용자 관리</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .main {
            margin-left: 200px;
            padding: 20px;
        }
        .user-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .user-stats {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .stat-card {
            background: white;
            padding: 15px;
            border-radius: 6px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
        }
        .user-table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.05);
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 20px;
        }
        .user-table th, .user-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .user-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        .user-table tr:hover {
            background-color: #f8f9fa;
        }
        .user-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        .user-admin { background-color: #dc3545; color: white; }
        .user-guest { background-color: #6c757d; color: white; }
        .activity-list {
            max-height: 100px;
            overflow-y: auto;
            font-size: 12px;
            color: #666;
        }
        .activity-item {
            padding: 2px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .btn {
            padding: 4px 8px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
            display: inline-block;
            margin: 2px;
        }
        .btn-danger { background-color: #dc3545; color: white; }
        .btn-warning { background-color: #ffc107; color: #212529; }
        .btn-info { background-color: #17a2b8; color: white; }
        .btn:hover { opacity: 0.8; }
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 20px;
            gap: 10px;
        }
        .pagination a, .pagination span {
            padding: 8px 12px;
            text-decoration: none;
            border: 1px solid #ddd;
            border-radius: 4px;
            color: #333;
        }
        .pagination a:hover { background-color: #f8f9fa; }
        .pagination .current {
            background-color: #007bff;
            color: white;
            border-color: #007bff;
        }
        .pagination .disabled {
            color: #999;
            cursor: not-allowed;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 20px;
            border-radius: 8px;
            width: 400px;
        }
        .modal input {
            width: 100%;
            padding: 8px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .modal-buttons {
            text-align: right;
            margin-top: 15px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        .stat-item {
            background: #e9ecef;
            padding: 5px 8px;
            border-radius: 4px;
            font-size: 11px;
            text-align: center;
        }
        .pw-hash { font-size: 11px; color: #888; word-break: break-all; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h3>PLC 제어</h3>
        <ul>
            <li><a href="index.php">🏠 대시보드</a></li>
            <li><a href="control.php">⚙ 회전기 제어</a></li>
            <li><a href="faults.php">🚨 고장 게시판</a></li>
            <li><a href="logs.php">📄 로그</a></li>
            <li><a href="user_management.php">👥 사용자 관리</a></li>
            <li><a href="logout.php">🔐 로그아웃</a></li>
        </ul>
    </div>

    <div class="main">
        <div class="user-header">
            <h2>👥 사용자 관리</h2>
        </div>
        
        <div class="user-stats">
            <div class="stat-card">
                <div class="stat-number"><?= $total_admins ?></div>
                <div>관리자 계정</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $total_guests ?></div>
                <div>게스트 계정</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $total_users ?></div>
                <div>전체 계정</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $total_pages ?></div>
                <div>총 페이지</div>
            </div>
        </div>
        
        <table class="user-table">
            <thead>
                <tr>
                    <th width="8%">계정 유형</th>
                    <th width="12%">사용자명</th>
                    <th width="12%">가입일</th>
                    <th width="18%">비밀번호(해시)</th>
                    <th width="15%">활동 통계</th>
                    <th width="20%">최근 활동</th>
                    <th width="10%">마지막 활동</th>
                    <th width="10%">프로필</th>
                    <th width="13%">관리</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($users) > 0): ?>
                    <?php foreach ($users as $user): ?>
                        <?php 
                        $userStats = getUserStats($pdo, $user['username']);
                        $recentActivities = getRecentActivity($pdo, $user['username']);
                        ?>
                        <tr>
                            <td>
                                <span class="user-badge user-<?= $user['type'] ?>"><?= $user['type_name'] ?></span>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($user['username']) ?></strong><br>
                                <small>ID: <?= $user['id'] ?></small>
                            </td>
                            <td>
                                <?= date('Y-m-d H:i', strtotime($user['created_at'])) ?>
                            </td>
                            <td class="pw-hash">
                                <?= htmlspecialchars($user['password']) ?>
                            </td>
                            <td>
                                <div class="stats-grid">
                                    <div class="stat-item">
                                        총 <?= $userStats['total_activities'] ?>회
                                    </div>
                                    <?php foreach ($userStats['activity_types'] as $activity): ?>
                                        <div class="stat-item">
                                            <?= $activity['activity_type'] ?>: <?= $activity['count'] ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                            <td>
                                <div class="activity-list">
                                    <?php if (count($recentActivities) > 0): ?>
                                        <?php foreach ($recentActivities as $activity): ?>
                                            <div class="activity-item">
                                                <?= htmlspecialchars(substr($activity['log_message'], 0, 50)) ?>...
                                                <br><small><?= date('m-d H:i', strtotime($activity['created_at'])) ?></small>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="activity-item">활동 기록 없음</div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($userStats['last_activity']): ?>
                                    <?= date('m-d H:i', strtotime($userStats['last_activity'])) ?>
                                <?php else: ?>
                                    <span style="color: #999;">활동 없음</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-info" onclick="showProfileModal('<?= $user['id'] ?>', '<?= $user['type'] ?>')">👤 프로필</button>
                            </td>
                            <td>
                                <button class="btn btn-warning" onclick="showPasswordModal('<?= $user['id'] ?>', '<?= $user['type'] ?>', '<?= htmlspecialchars($user['username']) ?>')">
                                    🔑 비밀번호
                                </button>
                                <?php if ($user['username'] !== $_SESSION['admin']): ?>
                                    <a href="?delete_user=<?= $user['id'] ?>&user_type=<?= $user['type'] ?>" 
                                       class="btn btn-danger" 
                                       onclick="return confirm('정말 이 계정을 삭제하시겠습니까?')">
                                        ❌ 삭제
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px; color: #666;">
                            📝 등록된 사용자가 없습니다.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($current_page > 1): ?>
                    <a href="?page=1">« 처음</a>
                    <a href="?page=<?= $current_page - 1 ?>">‹ 이전</a>
                <?php else: ?>
                    <span class="disabled">« 처음</span>
                    <span class="disabled">‹ 이전</span>
                <?php endif; ?>
                
                <?php
                $start_page = max(1, $current_page - 2);
                $end_page = min($total_pages, $current_page + 2);
                
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <?php if ($i == $current_page): ?>
                        <span class="current"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?page=<?= $i ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($current_page < $total_pages): ?>
                    <a href="?page=<?= $current_page + 1 ?>">다음 ›</a>
                    <a href="?page=<?= $total_pages ?>">마지막 »</a>
                <?php else: ?>
                    <span class="disabled">다음 ›</span>
                    <span class="disabled">마지막 »</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- 비밀번호 재설정 모달 -->
    <div id="passwordModal" class="modal">
        <div class="modal-content">
            <h3>🔑 비밀번호 재설정</h3>
            <form method="post" action="user_management.php">
                <input type="hidden" name="reset_password" value="1">
                <input type="hidden" name="user_id" id="modalUserId">
                <input type="hidden" name="user_type" id="modalUserType">
                
                <label>사용자: <span id="modalUsername"></span></label><br>
                <label>새 비밀번호:</label>
                <input type="password" name="new_password" required placeholder="새 비밀번호 입력">
                
                <div class="modal-buttons">
                    <button type="button" class="btn btn-info" onclick="closePasswordModal()">취소</button>
                    <button type="submit" class="btn btn-warning">재설정</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 프로필 모달 -->
    <div id="profileModal" class="modal" style="display:none;position:fixed;z-index:9999;left:0;top:0;width:100vw;height:100vh;background:rgba(0,0,0,0.25);justify-content:center;align-items:center;">
        <div class="modal-content" style="background:#fff;padding:32px 28px;border-radius:10px;min-width:320px;max-width:400px;box-shadow:0 2px 12px rgba(0,0,0,0.12);position:relative;">
            <button onclick="closeProfileModal()" style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:22px;cursor:pointer;">&times;</button>
            <h3 style="margin-bottom:18px;">👤 프로필 정보</h3>
            <form id="profileForm" method="post" action="update_profile.php" enctype="multipart/form-data">
                <input type="hidden" name="user_id" id="profileUserId">
                <input type="hidden" name="user_type" id="profileUserType">
                <label>이름</label>
                <input type="text" name="name" id="profileName" style="width:100%;margin-bottom:10px;">
                <label>연락처</label>
                <input type="text" name="phone" id="profilePhone" style="width:100%;margin-bottom:10px;">
                <label>프로필 사진</label>
                <input type="file" name="profile_img" accept="image/*" style="width:100%;margin-bottom:10px;">
                <div id="profileImgPreview" style="margin-bottom:10px;"></div>
                <button type="submit" class="btn btn-primary" style="width:100%;margin-top:10px;">저장</button>
            </form>
        </div>
    </div>

    <script>
        function showPasswordModal(userId, userType, username) {
            document.getElementById('modalUserId').value = userId;
            document.getElementById('modalUserType').value = userType;
            document.getElementById('modalUsername').textContent = username;
            document.getElementById('passwordModal').style.display = 'block';
        }
        
        function closePasswordModal() {
            document.getElementById('passwordModal').style.display = 'none';
        }
        
        // 모달 외부 클릭 시 닫기
        window.onclick = function(event) {
            var modal = document.getElementById('passwordModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }

        function showProfileModal(userId, userType) {
            document.getElementById('profileModal').style.display = 'flex';
            document.getElementById('profileUserId').value = userId;
            document.getElementById('profileUserType').value = userType;
            fetch('update_profile.php?get=1&user_id=' + userId + '&user_type=' + userType)
                .then(res => res.json())
                .then(data => {
                    document.getElementById('profileName').value = data.name || '';
                    document.getElementById('profilePhone').value = data.phone || '';
                    if (data.profile_img) {
                        document.getElementById('profileImgPreview').innerHTML = '<img src="uploads/profile/' + data.profile_img + '" alt="프로필" style="max-width:100px;max-height:100px;border-radius:50%;">';
                    } else {
                        document.getElementById('profileImgPreview').innerHTML = '';
                    }
                });
        }

        function closeProfileModal() {
            document.getElementById('profileModal').style.display = 'none';
        }
    </script>
</body>
</html> 