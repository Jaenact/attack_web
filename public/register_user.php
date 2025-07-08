<?php
session_start();
require_once '../src/db/db.php';
require_once '../src/log/log_function.php';

// 관리자만 접근 가능
if (!isset($_SESSION['admin'])) {
    echo "<script>alert('관리자 계정이 아닙니다.'); history.back();</script>";
    exit();
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'guest';
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    // 입력 검증
    if (empty($username) || empty($password) || empty($confirm_password)) {
        $error = '모든 필수 항목을 입력해주세요.';
    } elseif ($password !== $confirm_password) {
        $error = '비밀번호가 일치하지 않습니다.';
    } elseif (strlen($password) < 6) {
        $error = '비밀번호는 최소 6자 이상이어야 합니다.';
    } elseif (!in_array($role, ['admin', 'guest'])) {
        $error = '유효하지 않은 역할입니다.';
    } else {
        // 사용자명 중복 확인
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
        $stmt->execute(['username' => $username]);
        if ($stmt->fetchColumn() > 0) {
            $error = '이미 존재하는 사용자명입니다.';
        } else {
            // 새 사용자 등록
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO users (username, password, role, name, phone) 
                VALUES (:username, :password, :role, :name, :phone)
            ");
            
            try {
                $stmt->execute([
                    'username' => $username,
                    'password' => $hashed_password,
                    'role' => $role,
                    'name' => $name,
                    'phone' => $phone
                ]);
                
                $message = "사용자가 성공적으로 등록되었습니다. (사용자명: $username)";
                writeLog($pdo, $_SESSION['admin'], "사용자 등록", "성공", "새 사용자: $username (역할: $role)");
                
                // 폼 초기화
                $_POST = [];
            } catch (PDOException $e) {
                $error = '사용자 등록 중 오류가 발생했습니다: ' . $e->getMessage();
                writeLog($pdo, $_SESSION['admin'], "사용자 등록", "실패", "오류: " . $e->getMessage());
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>사용자 등록</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 500px;
            margin: 50px auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #555;
        }
        input[type="text"], input[type="password"], select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 6px;
            font-size: 16px;
            transition: border-color 0.3s ease;
            box-sizing: border-box;
        }
        input[type="text"]:focus, input[type="password"]:focus, select:focus {
            outline: none;
            border-color: #007bff;
        }
        .btn {
            background-color: #007bff;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
            transition: background-color 0.3s ease;
        }
        .btn:hover {
            background-color: #0056b3;
        }
        .btn-secondary {
            background-color: #6c757d;
            margin-top: 10px;
        }
        .btn-secondary:hover {
            background-color: #545b62;
        }
        .message {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .required {
            color: #dc3545;
        }
        .form-row {
            display: flex;
            gap: 15px;
        }
        .form-row .form-group {
            flex: 1;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>👤 사용자 등록</h1>
        
        <?php if ($message): ?>
            <div class="message success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="username">사용자명 <span class="required">*</span></label>
                <input type="text" id="username" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="password">비밀번호 <span class="required">*</span></label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">비밀번호 확인 <span class="required">*</span></label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="role">역할 <span class="required">*</span></label>
                    <select id="role" name="role" required>
                        <option value="guest" <?= ($_POST['role'] ?? '') === 'guest' ? 'selected' : '' ?>>게스트</option>
                        <option value="admin" <?= ($_POST['role'] ?? '') === 'admin' ? 'selected' : '' ?>>관리자</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="name">이름</label>
                    <input type="text" id="name" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label for="phone">전화번호</label>
                <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" placeholder="010-1234-5678">
            </div>
            
            <button type="submit" class="btn">사용자 등록</button>
        </form>
        
        <a href="user_management.php" class="btn btn-secondary">사용자 관리로 돌아가기</a>
    </div>

    <script>
        // 비밀번호 확인 실시간 검증
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.style.borderColor = '#dc3545';
            } else {
                this.style.borderColor = '#28a745';
            }
        });
        
        // 비밀번호 입력 시 확인 필드 초기화
        document.getElementById('password').addEventListener('input', function() {
            document.getElementById('confirm_password').style.borderColor = '#e1e5e9';
        });
    </script>
</body>
</html> 