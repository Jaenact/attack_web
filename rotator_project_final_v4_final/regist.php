
<?php
require_once 'includes/db.php'; // DB 연결 (PDO 객체 $pdo 제공)

$message = '';

// 회원가입 요청 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm  = $_POST['confirm'];

    if (empty($username) || empty($password)) {
        $message = '❌ 사용자명과 비밀번호를 모두 입력하세요.';
    } elseif ($password !== $confirm) {
        $message = '❌ 비밀번호가 일치하지 않습니다.';
    } else {
        // 사용자명 중복 확인
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE username = :username");
        $stmt->execute(['username' => $username]);

        if ($stmt->fetchColumn() > 0) {
            $message = '⚠️ 이미 존재하는 사용자명입니다.';
        } else {
            // 비밀번호 해시 후 저장
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO admins (username, password) VALUES (:username, :password)");
            $stmt->execute(['username' => $username, 'password' => $hashed]);
            $message = '✅ 회원가입이 완료되었습니다. <a href="login.php">[로그인하기]</a>';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>회원가입</title>
    <style>
        body { font-family: sans-serif; background: #f4f4f4; padding: 50px; }
        .box { background: white; padding: 30px; max-width: 400px; margin: auto; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h2 { margin-bottom: 20px; }
        input[type="text"], input[type="password"] {
            width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ccc; border-radius: 5px;
        }
        button { padding: 10px 20px; background: steelblue; color: white; border: none; border-radius: 5px; }
        .message { margin-top: 15px; color: red; }
    </style>
</head>
<body>
    <div class="box">
        <h2>회원가입</h2>
        <form method="post" action="register.php">
            <input type="text" name="username" placeholder="사용자명" required>
            <input type="password" name="password" placeholder="비밀번호" required>
            <input type="password" name="confirm" placeholder="비밀번호 확인" required>
            <button type="submit">가입하기</button>
        </form>
        <?php if (!empty($message)): ?>
            <div class="message"><?= $message ?></div>
        <?php endif; ?>
    </div>
</body>
</html>
