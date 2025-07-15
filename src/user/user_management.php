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

// 계정 삭제 처리 (DB에서 완전 삭제)
if (isset($_GET['delete_user'])) {
    $user_id = $_GET['delete_user'];
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
    $stmt->execute(['id' => $user_id]);
    writeLog($pdo, $_SESSION['admin'], "계정 삭제 - ID: $user_id");
    echo "<script>alert('계정이 완전히 삭제되었습니다. 복구할 수 없습니다.'); location.href='user_management.php';</script>";
    exit();
}

// 계정 활성화 처리
if (isset($_GET['activate_user'])) {
    $user_id = $_GET['activate_user'];
    
    $stmt = $pdo->prepare("UPDATE users SET is_active = 1 WHERE id = :id");
    $stmt->execute(['id' => $user_id]);
    
    writeLog($pdo, $_SESSION['admin'], "계정 활성화 - ID: $user_id");
    echo "<script>alert('계정이 활성화되었습니다.'); location.href='user_management.php';</script>";
    exit();
}

// 비밀번호 재설정 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $user_id = $_POST['user_id'];
    $new_password = $_POST['new_password'];
    
    $stmt = $pdo->prepare("UPDATE users SET password = :password WHERE id = :id");
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt->execute(['password' => $hashed_password, 'id' => $user_id]);
    
    writeLog($pdo, $_SESSION['admin'], "비밀번호 재설정 - ID: $user_id");
    echo "<script>alert('비밀번호가 재설정되었습니다.'); location.href='user_management.php';</script>";
    exit();
}

// 페이지네이션 설정
$users_per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $users_per_page;

// 전체 사용자 수 조회
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_pages = ceil($total_users / $users_per_page);

// 사용자 목록 조회
$sql = "
    SELECT id, username, password, role, name, phone, profile_img, is_active, created_at, updated_at,
           CASE role WHEN 'admin' THEN '관리자' WHEN 'guest' THEN '게스트' END as role_name
    FROM users 
    ORDER BY created_at DESC
    LIMIT :limit OFFSET :offset
";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':limit', $users_per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll();

// 각 사용자의 최근 활동 조회
function getUserStats($pdo, $username) {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_logs,
            COUNT(CASE WHEN action = '로그인' THEN 1 END) as login_count,
            COUNT(CASE WHEN action = '로그아웃' THEN 1 END) as logout_count
        FROM logs 
        WHERE username = :username
    ");
    $stmt->execute(['username' => $username]);
    return $stmt->fetch();
}

function getRecentActivity($pdo, $username) {
    $stmt = $pdo->prepare("
        SELECT action, log_message, created_at 
        FROM logs 
        WHERE username = :username 
        ORDER BY created_at DESC 
        LIMIT 3
    ");
    $stmt->execute(['username' => $username]);
    return $stmt->fetchAll();
}

// 역할별 통계
$admin_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
$guest_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'guest'")->fetchColumn();
$active_count = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn();
$inactive_count = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 0")->fetchColumn();

?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>사용자 관리</title>
    <style>
        body {
            background: #181c24;
            color: #eaf6ff;
            font-family: 'Noto Sans KR', sans-serif;
        }
        .main {
            margin-left: 200px;
            padding: 32px 0 0 0;
            max-width: 900px;
            margin-right: auto;
            margin-left: auto;
        }
        .user-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        .user-header h2 {
            color: #7ecbff;
            font-size: 2rem;
            font-weight: 700;
        }
        .btn {
            padding: 6px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: background 0.2s;
        }
        .btn-add { background: #222c36; color: #7ecbff; border: 1px solid #7ecbff; }
        .btn-add:hover { background: #7ecbff; color: #181c24; }
        .btn-delete { background: #222c36; color: #ff5c5c; border: 1px solid #ff5c5c; }
        .btn-delete:hover { background: #ff5c5c; color: #fff; }
        .btn-reset { background: #222c36; color: #7ecbff; border: 1px solid #7ecbff; }
        .btn-reset:hover { background: #7ecbff; color: #181c24; }
        .btn-profile { background: #222c36; color: #eaf6ff; border: 1px solid #eaf6ff; }
        .btn-profile:hover { background: #eaf6ff; color: #181c24; }
        .user-table {
            width: 100%;
            border-collapse: collapse;
            background: #232a36;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 32px;
            box-shadow: 0 2px 16px rgba(0,0,0,0.12);
        }
        .user-table th, .user-table td {
            padding: 14px 10px;
            text-align: left;
        }
        .user-table th {
            background: #1a2028;
            color: #7ecbff;
            font-weight: 700;
            border-bottom: 2px solid #222c36;
        }
        .user-table tr {
            border-bottom: 1px solid #232a36;
        }
        .user-table tr:hover {
            background: #202736;
        }
        .user-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .user-admin { background: #7ecbff; color: #181c24; }
        .user-guest { background: #232a36; color: #7ecbff; border: 1px solid #7ecbff; }
        .user-active { background: #232a36; color: #7ecbff; border: 1px solid #7ecbff; }
        .user-inactive { background: #232a36; color: #ffb347; border: 1px solid #ffb347; }
        .action-buttons { display: flex; gap: 8px; }
        .password-hash { font-family: monospace; font-size: 11px; color: #7ecbff; max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .activity-info { font-size: 12px; color: #7ecbff; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(24,28,36,0.85); }
        .modal-content { background-color: #232a36; margin: 12% auto; padding: 28px; border-radius: 12px; width: 350px; color: #eaf6ff; }
        .modal input { width: 100%; padding: 10px; margin: 12px 0; border: 1px solid #7ecbff; border-radius: 6px; background: #181c24; color: #eaf6ff; }
        .modal label { color: #7ecbff; font-weight: 500; }
        .modal-buttons { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }
        .pagination { display: flex; justify-content: center; gap: 10px; margin-top: 20px; }
        .pagination a { padding: 8px 12px; border: 1px solid #7ecbff; text-decoration: none; color: #7ecbff; border-radius: 4px; }
        .pagination a:hover, .pagination .current { background-color: #7ecbff; color: #181c24; border-color: #7ecbff; }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main">
        <div class="user-header">
            <h2>👥 사용자 관리</h2>
            <a href="../../public/register_user.php" class="btn btn-add" style="text-decoration: none; padding: 8px 16px;">➕ 새 사용자 등록</a>
        </div>
        
        <div class="user-stats">
            <div class="stat-card">
                <div class="stat-number"><?= $admin_count ?></div>
                <div>관리자 계정</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $guest_count ?></div>
                <div>게스트 계정</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $active_count ?></div>
                <div>활성 계정</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $inactive_count ?></div>
                <div>비활성 계정</div>
            </div>
        </div>
        
        <table class="user-table">
            <thead>
                <tr>
                    <th width="8%">계정 유형</th>
                    <th width="12%">사용자명</th>
                    <th width="10%">이름</th>
                    <th width="12%">가입일</th>
                    <th width="18%">비밀번호(해시)</th>
                    <th width="15%">활동 통계</th>
                    <th width="20%">최근 활동</th>
                    <th width="10%">상태</th>
                    <th width="15%">관리</th>
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
                                <span class="user-badge user-<?= $user['role'] ?>"><?= $user['role_name'] ?></span>
                            </td>
                            <td><?= htmlspecialchars($user['username']) ?></td>
                            <td><?= htmlspecialchars($user['name'] ?? '미설정') ?></td>
                            <td><?= date('Y-m-d H:i', strtotime($user['created_at'])) ?></td>
                            <td>
                                <div class="password-hash" title="<?= htmlspecialchars($user['password']) ?>">
                                    <?= htmlspecialchars(substr($user['password'], 0, 20)) ?>...
                                </div>
                            </td>
                            <td>
                                <div class="activity-info">
                                    총 로그: <?= $userStats['total_logs'] ?><br>
                                    로그인: <?= $userStats['login_count'] ?><br>
                                    로그아웃: <?= $userStats['logout_count'] ?>
                                </div>
                            </td>
                            <td>
                                    <?php if (count($recentActivities) > 0): ?>
                                        <?php foreach ($recentActivities as $activity): ?>
                                        <div class="activity-info">
                                            <?= htmlspecialchars($activity['action']) ?> - 
                                            <?= date('m-d H:i', strtotime($activity['created_at'])) ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                    <div class="activity-info">활동 기록 없음</div>
                                    <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($user['is_active']): ?>
                                    <span class="user-badge user-active">활성</span>
                                <?php else: ?>
                                    <span class="user-badge user-inactive">비활성</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="?delete_user=<?= $user['id'] ?>" class="btn btn-delete" onclick="return confirm('정말로 이 계정을 완전히 삭제하시겠습니까? 삭제 후 복구할 수 없습니다.')">삭제</a>
                                    <button class="btn btn-reset" onclick="showPasswordModal(<?= $user['id'] ?>)">비밀번호 초기화</button>
                                    <button class="btn btn-profile" onclick="showProfileModal(<?= $user['id'] ?>)">프로필</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 20px;">등록된 사용자가 없습니다.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <!-- 페이지네이션 -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?= $i ?>" <?= $i == $current_page ? 'class="current"' : '' ?>><?= $i ?></a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- 비밀번호 초기화 모달 -->
    <div id="passwordModal" class="modal">
        <div class="modal-content">
            <h3>비밀번호 초기화</h3>
            <form method="POST">
                <input type="hidden" name="user_id" id="passwordUserId">
                <input type="hidden" name="reset_password" value="1">
                <label>새 비밀번호:</label>
                <input type="password" name="new_password" required>
                <label>비밀번호 확인:</label>
                <input type="password" name="confirm_password" required>
                <div class="modal-buttons">
                    <button type="button" onclick="closePasswordModal()">취소</button>
                    <button type="submit">초기화</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 프로필 수정 모달 -->
    <div id="profileModal" class="modal">
        <div class="modal-content">
            <h3>프로필 수정</h3>
            <form method="POST" action="update_profile.php">
                <input type="hidden" name="user_id" id="profileUserId">
                <label>이름:</label>
                <input type="text" name="name" id="profileName">
                <label>전화번호:</label>
                <input type="text" name="phone" id="profilePhone">
                <div class="modal-buttons">
                    <button type="button" onclick="closeProfileModal()">취소</button>
                    <button type="submit">수정</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showPasswordModal(userId) {
            document.getElementById('passwordUserId').value = userId;
            document.getElementById('passwordModal').style.display = 'block';
        }
        
        function closePasswordModal() {
            document.getElementById('passwordModal').style.display = 'none';
        }
        
        function showProfileModal(userId) {
            // AJAX로 사용자 정보 가져오기
            fetch(`update_profile.php?get=1&user_id=${userId}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('profileUserId').value = userId;
                    document.getElementById('profileName').value = data.name || '';
                    document.getElementById('profilePhone').value = data.phone || '';
                    document.getElementById('profileModal').style.display = 'block';
                });
        }

        function closeProfileModal() {
            document.getElementById('profileModal').style.display = 'none';
        }

        // 모달 외부 클릭 시 닫기
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html> 