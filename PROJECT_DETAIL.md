# PLC Rotator System 상세 설명서

---

## 1. 폴더/파일 구조 및 역할

```
/
├── public/           # 실제 서비스되는 웹페이지(메인, 제어, 고장, 로그, 로그인 등)
│   ├── index.php         # 대시보드(메인) - 시스템 현황, 통계, 공지, 관리자 UI
│   ├── control.php       # PLC 제어(ON/OFF, RPM 설정 등, 관리자만)
│   ├── faults.php        # 고장 관리(등록/수정/삭제/첨부파일)
│   ├── logs.php          # 활동/보안 로그(관리자만)
│   ├── login.php         # 로그인 페이지
│   ├── make_account.php  # 회원가입(관리자/게스트)
│   ├── maintenance.php   # 유지보수 모드 안내
│   ├── admin/            # 관리자 전용 기능(계정/파일/시스템 관리 등)
│   ├── templates/        # 공통 레이아웃(네비, 모달, 푸터 등)
│   └── ...               # 기타 페이지/업로드/리셋 등
├── src/              # 핵심 로직(모듈별 분리)
│   ├── db/               # DB 연결, 유지보수 체크, 로그 함수
│   ├── log/              # 로그 기록 함수(보안/이벤트/알림)
│   ├── user/             # 회원 관리(정보수정, 관리자 기능)
│   └── auth/             # 인증/권한 관련 함수
├── assets/           # 정적 리소스(CSS, 이미지 등)
│   └── css/
│       └── main.css      # 전체 UI/UX 스타일(정부/공공 스타일)
├── uploads/          # 첨부파일/프로필 이미지 저장소
│   └── profile/          # 프로필 이미지
├── PHPIDS/           # PHPIDS 보안 라이브러리(공격 탐지)
│   └── lib/IDS/          # IDS 핵심 클래스/설정/필터
│       └── Config/           # IDS 설정파일(Config.ini.php)
├── sql/              # DB 스키마/초기화/샘플 데이터
│   └── rotator_system.sql
├── README.md         # 간단 설명(설치/기능/보안 등)
└── test_phpids.php   # PHPIDS 동작 테스트용
```

---

## 2. 주요 파일/폴더 상세 설명 및 코드 예시

### 2-1. public/ (서비스 페이지)

#### index.php (대시보드 메인)
- **기능:** 로그인 후 접근, 시스템 통계/공지/관리자 UI 제공, 공통 레이아웃 사용
- **대표 코드:**
```php
// [대시보드 메인] - 관리자/게스트 로그인 후 접근 가능. 시스템 주요 현황, 통계, 공지사항 관리 기능 제공
session_start();
if (!isset($_SESSION['admin']) && !isset($_SESSION['guest'])) {
  header("Location: login.php");
  exit();
}
require_once '../src/db/db.php';
require_once '../src/log/log_function.php';
// ... 통계/공지/로그 집계 ...
```

#### control.php (PLC 제어)
- **기능:** 관리자만 제어 가능, PHPIDS로 입력값 공격 탐지, 로그 기록
- **대표 코드:**
```php
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    $rpm = $_POST['rpm'] ?? $currentRpm;
    // --- PHPIDS 공격 탐지: 입력값만 별도 검사 ---
    try {
        $request = [ 'POST' => [ 'action' => $action, 'rpm' => $rpm ] ];
        $init = Init::init(__DIR__ . '/../PHPIDS/lib/IDS/Config/Config.ini.php');
        $ids = new Monitor($init);
        $result = $ids->run($request);
        if (!$result->isEmpty()) {
            $impact = $result->getImpact();
            $logMessage = 'PHPIDS 제어 입력값 공격 감지! 임팩트: ' . $impact . ', 상세: ' . print_r($result, true);
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            $stmt = $pdo->prepare('INSERT INTO logs (username, action, log_message, ip_address) VALUES (?, ?, ?, ?)');
            $stmt->execute([$currentUser, '공격감지', $logMessage, $ip]);
        }
    } catch (Exception $e) {
        // PHPIDS 오류 시 로그 기록
        $logMessage = 'PHPIDS 오류: ' . $e->getMessage();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $stmt = $pdo->prepare('INSERT INTO logs (username, action, log_message, ip_address) VALUES (?, ?, ?, ?)');
        $stmt->execute([$currentUser, 'PHPIDS오류', $logMessage, $ip]);
    }
    // ... 제어 처리 ...
}
```

#### faults.php (고장 관리)
- **기능:** 고장 등록/수정/삭제/첨부파일, PHPIDS 공격 탐지, 로그 기록
- **대표 코드:**
```php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['part']) && !isset($_POST['edit_id'])) {
    $part = trim($_POST['part']);
    $status = $_POST['status'] ?? '접수';
    $manager = $_POST['manager'] ?? null;
    // ...
    // --- PHPIDS 공격 탐지: 입력값만 별도 검사 ---
    try {
        $request = [ 'POST' => [ 'part' => $part, 'status' => $status, 'manager' => $manager ] ];
        $init = Init::init(__DIR__ . '/../PHPIDS/lib/IDS/Config/Config.ini.php');
        $ids = new Monitor($init);
        $result = $ids->run($request);
        if (!$result->isEmpty()) {
            $impact = $result->getImpact();
            $logMessage = 'PHPIDS 고장접수 입력값 공격 감지! 임팩트: ' . $impact . ', 상세: ' . print_r($result, true);
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            $stmt = $pdo->prepare('INSERT INTO logs (username, action, log_message, ip_address) VALUES (?, ?, ?, ?)');
            $stmt->execute([$username, '공격감지', $logMessage, $ip]);
        }
    } catch (Exception $e) {
        // PHPIDS 오류 시 로그 기록
        $logMessage = 'PHPIDS 오류: ' . $e->getMessage();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $stmt = $pdo->prepare('INSERT INTO logs (username, action, log_message, ip_address) VALUES (?, ?, ?, ?)');
        $stmt->execute([$username, 'PHPIDS오류', $logMessage, $ip]);
    }
    // ... 파일 업로드 및 DB 저장 ...
}
```

#### logs.php (로그)
- **기능:** 모든 이벤트/보안 로그, 검색/필터, IP 마스킹, 상세 팝업
- **대표 코드:**
```php
$logs = $pdo->query("SELECT * FROM logs $where_sql ORDER BY created_at DESC LIMIT :limit OFFSET :offset")->fetchAll();
// ... 로그 목록 출력, logTypeInfo(), maskIP() 등 유틸 함수 ...
```

#### admin/
- **user_management.php:** 관리자 계정 관리(활성/비활성, 비번초기화 등)
- **file_management.php:** 첨부파일/프로필 이미지 관리
- **get_security_logs.php:** 보안 로그 API(JSON)
- **system_status.php:** 시스템 상태/통계

#### templates/layout.php (공통 레이아웃)
- **기능:** 네비, "내 정보" 모달, 푸터 등 모든 페이지에 일관된 UI 제공
- **대표 코드:**
```php
// ...
<body>
  <header class="header"> ... </header>
  <main class="main-content"> <?= $content ?> </main>
  <footer class="footer"> ... </footer>
  <!-- 내 정보/비밀번호 변경 모달 ... -->
</body>
// ...
```

---

### 2-2. src/ (핵심 로직)

#### db/db.php
- **기능:** PDO 기반 DB 연결
- **코드:**
```php
$pdo = new PDO('mysql:host=localhost;dbname=rotator_system;charset=utf8', 'user', 'password', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);
```

#### log/log_function.php
- **기능:** 로그 기록 및 알림 생성
- **코드:**
```php
function writeLog($pdo, $username, $action, $result, $extra = null) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $msg = "[$action][$result]" . ($extra ? " $extra" : "");
    $stmt = $pdo->prepare("INSERT INTO logs (username, ip_address, log_message, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$username, $ip, $msg]);
    // ... 알림 생성 ...
}
```

#### user/update_profile.php
- **기능:** 회원정보/비밀번호 변경 처리
- **코드:**
```php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_SESSION['admin'] ?? $_SESSION['guest'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    // ... 비밀번호 변경 로직 ...
}
```

#### auth/auth.php
- **기능:** 인증/권한 체크 함수
- **코드:**
```php
function isAdmin() {
    return isset($_SESSION['admin']);
}
function isGuest() {
    return isset($_SESSION['guest']);
}
```

---

### 2-3. assets/
- **css/main.css:** 전체 UI/UX 스타일(공공기관/정부 스타일, 반응형)

---

### 2-4. uploads/
- **첨부파일/프로필 이미지 저장소**
- **코드:**
```php
$upload_dir = realpath(__DIR__ . '/../uploads');
if ($upload_dir === false) {
    $upload_dir = __DIR__ . '/../uploads';
    mkdir($upload_dir, 0777, true);
    $upload_dir = realpath($upload_dir);
}
```

---

### 2-5. PHPIDS/
- **lib/IDS/**: PHPIDS 핵심 라이브러리(공격 탐지, 필터, 설정 등)
- **Config/Config.ini.php**: IDS 설정파일(필터, 캐시, 경로 등)
- **코드:**
```php
$init = IDS\Init::init(__DIR__ . '/../PHPIDS/lib/IDS/Config/Config.ini.php');
$ids = new IDS\Monitor($init);
$result = $ids->run($request);
```

---

### 2-6. sql/
- **rotator_system.sql:** DB 스키마, 테이블 구조, 샘플 데이터

---

## 3. 파일/폴더 간 유기적 관계

- public/의 각 페이지는 src/db/db.php로 DB 연결, src/log/log_function.php로 로그 기록, templates/layout.php로 공통 UI 적용, 필요시 PHPIDS로 공격 탐지(입력값 검사)
- 관리자 기능은 public/admin/ 하위에 집중
- 업로드 파일은 uploads/에 저장, 프로필 이미지는 uploads/profile/에 별도 관리
- DB 스키마는 sql/rotator_system.sql로 관리

---

## 4. 주요 흐름 예시

- 로그인 → 대시보드(index.php) → 제어(control.php) or 고장 관리(faults.php) → 로그(logs.php)
- 모든 주요 이벤트(제어, 고장, 로그인 등)는 logs 테이블에 기록
- 공격/이상 입력 감지 시 PHPIDS가 탐지, logs에 별도 기록 + 관리자 알림

---

## 5. 보안/유지보수/확장성

- 모든 DB 쿼리 Prepared Statement 사용
- 비밀번호 해시, 세션 인증, 파일 업로드 제한
- 공격 탐지(PHPIDS) 및 보안 로그 별도 관리
- 공통 레이아웃/스타일로 유지보수 용이
- 모듈별 분리(src/), 확장성/협업에 최적화

---

## 6. 팀원 협업/확장시 참고

- 새 페이지 추가 시 → public/에 파일 생성, src/에 필요한 로직 추가, templates/layout.php로 UI 통일
- DB 변경 시 → sql/rotator_system.sql도 함께 수정
- 보안/로그/알림 등은 src/log/log_function.php 참고

---

## 7. (부록) 주요 테이블 요약

- users/admins/guests: 사용자/관리자 계정
- faults: 고장 이력/첨부파일
- logs: 모든 이벤트/보안 로그
- maintenance: 유지보수 모드/스케줄
- notices: 공지사항

---

> 추가로 궁금한 파일/로직/관계가 있으면 언제든 요청해 주세요! 