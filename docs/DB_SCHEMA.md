# 데이터베이스 스키마 안내

이 프로젝트에서 사용되는 모든 주요 데이터베이스 테이블 정보를 정리합니다.

---

## 1. banners

| 컬럼명   | 타입           | 설명                |
|----------|----------------|---------------------|
| id       | INT, PK        | 고유 ID             |
| content  | TEXT           | 배너에 표시할 내용  |
| start_at | DATE           | 배너 시작일         |
| end_at   | DATE           | 배너 종료일         |

**용도:**
- 사이트 상단/하단, 메인화면 등에서 기간 한정으로 노출되는 배너 메시지 관리
- 배너 내용(content), 노출 시작일(start_at), 종료일(end_at) 관리

**실제 사용 예시:**
- `public/admin/notice_banner_popup.php`에서 배너 등록/수정/삭제/목록 조회
- 예시 코드:
  ```php
  // 배너 등록
  $stmt = $pdo->prepare("INSERT INTO banners (content, start_at, end_at) VALUES (?, ?, ?)");
  $stmt->execute([$content, $start_at, $end_at]);
  // 배너 목록 조회
  $banners = $pdo->query("SELECT * FROM banners ORDER BY start_at DESC")->fetchAll();
  ```

---

## 2. faults

| 컬럼명              | 타입 및 제약조건                | 설명                |
|---------------------|-------------------------------|---------------------|
| id                  | INT AUTO_INCREMENT PRIMARY KEY | 고유 ID              |
| part                | VARCHAR(255)                  | 고장 부위/내용       |
| filename            | VARCHAR(255)                  | 첨부파일(서버저장명) |
| original_filename   | VARCHAR(255)                  | 첨부파일(원본명)     |
| status              | VARCHAR(50)                   | 상태(접수/처리 등)   |
| manager             | VARCHAR(100)                  | 담당자               |
| user_id             | INT                           | 등록자(사용자ID)     |
| created_at          | DATETIME                      | 등록일               |

**용도:**
- 고장 게시판(등록/수정/삭제/조회), 첨부파일 관리 등

**실제 사용 예시:**
- `public/faults.php`에서 고장 등록, 수정, 삭제, 파일 업로드/다운로드, 상태 변경 등
- 예시 코드:
  ```php
  // 고장 등록
  $stmt = $pdo->prepare("INSERT INTO faults (part, filename, original_filename, status, manager, user_id) VALUES (:part, :filename, :original_filename, :status, :manager, :user_id)");
  $stmt->execute([...]);
  // 고장 목록 조회
  $stmt = $pdo->query("SELECT * FROM faults ORDER BY created_at DESC");
  ```

---

## 3. logs

| 컬럼명        | 타입 및 제약조건                | 설명                |
|---------------|-------------------------------|---------------------|
| id            | INT AUTO_INCREMENT PRIMARY KEY | 고유 ID              |
| username      | VARCHAR(100)                  | 사용자명             |
| ip_address    | VARCHAR(45)                   | IP 주소              |
| log_message   | TEXT                          | 로그 메시지          |
| created_at    | DATETIME                      | 생성일               |

**용도:**
- 시스템 내 모든 주요 이벤트(로그인, 고장등록, 보안이벤트 등) 기록 및 감사 로그

**실제 사용 예시:**
- `src/log/log_function.php`의 `writeLog()` 함수에서 다양한 이벤트 발생 시 자동 기록
- 예시 코드:
  ```php
  writeLog($pdo, $username, '고장접수', '성공', $extra);
  // 내부적으로 logs 테이블에 INSERT
  ```

---

## 4. machine_status

| 컬럼명        | 타입 및 제약조건                | 설명                |
|---------------|-------------------------------|---------------------|
| id            | INT AUTO_INCREMENT PRIMARY KEY | 고유 ID              |
| status        | VARCHAR(50)                   | 기계 상태            |
| updated_at    | DATETIME                      | 상태 변경일          |

**용도:**
- PLC/설비 등 기계의 현재 상태(정상/점검/고장 등) 관리

**실제 사용 예시:**
- (예시: `public/admin/machine_status.php` 등에서 상태 변경/조회)
- 예시 코드:
  ```php
  $stmt = $pdo->prepare("UPDATE machine_status SET status=? WHERE id=?");
  $stmt->execute([$status, $id]);
  ```

---

## 5. maintenance

| 컬럼명      | 타입 및 제약조건                | 설명                |
|-------------|-------------------------------|---------------------|
| id          | INT AUTO_INCREMENT PRIMARY KEY | 고유 ID              |
| is_active   | TINYINT(1) DEFAULT 0          | 유지보수 활성화 여부 |
| start_at    | DATETIME                      | 유지보수 시작 시각   |
| end_at      | DATETIME                      | 유지보수 종료 시각   |

**용도:**
- 시스템 유지보수(점검) 모드 관리, 점검 기간 안내 등

**실제 사용 예시:**
- `src/db/db.php`의 `isMaintenanceActive()` 함수에서 유지보수 여부 체크
- 예시 코드:
  ```php
  $stmt = $pdo->query("SELECT * FROM maintenance WHERE is_active=1 AND start_at <= NOW() AND end_at >= NOW()");
  ```

---

## 6. notices

| 컬럼명      | 타입 및 제약조건                | 설명                |
|-------------|-------------------------------|---------------------|
| id          | INT AUTO_INCREMENT PRIMARY KEY | 고유 ID              |
| title       | VARCHAR(255)                  | 공지 제목            |
| content     | TEXT                          | 공지 내용            |
| created_at  | DATETIME                      | 등록일               |

**용도:**
- 공지사항 등록/수정/삭제/목록 관리

**실제 사용 예시:**
- `public/admin/notice_banner_popup.php`에서 공지 등록/수정/삭제/목록 조회
- 예시 코드:
  ```php
  $stmt = $pdo->prepare("INSERT INTO notices (title, content) VALUES (?, ?)");
  $stmt->execute([$title, $content]);
  $notices = $pdo->query("SELECT * FROM notices ORDER BY created_at DESC")->fetchAll();
  ```

---

## 7. notifications

| 컬럼명      | 타입 및 제약조건                | 설명                |
|-------------|-------------------------------|---------------------|
| id          | INT AUTO_INCREMENT PRIMARY KEY | 고유 ID              |
| type        | VARCHAR(50)                   | 알림 유형            |
| message     | TEXT                          | 알림 메시지          |
| url         | VARCHAR(255)                  | 관련 URL             |
| target      | VARCHAR(50)                   | 알림 대상(관리자 등) |
| created_at  | DATETIME                      | 생성일               |

**용도:**
- 주요 이벤트 발생 시 관리자 등에게 알림 메시지 발송/저장

**실제 사용 예시:**
- `src/log/log_function.php`의 `writeLog()` 함수에서 특정 이벤트 발생 시 자동 알림 생성
- 예시 코드:
  ```php
  $stmt2 = $pdo->prepare("INSERT INTO notifications (type, message, url, target) VALUES (?, ?, ?, 'admin')");
  $stmt2->execute([$type, $notify_msg, $url]);
  ```

---

## 8. popups

| 컬럼명   | 타입           | 설명                |
|----------|----------------|---------------------|
| id       | INT, PK        | 고유 ID             |
| content  | TEXT           | 팝업에 표시할 내용  |
| start_at | DATE           | 팝업 시작일         |
| end_at   | DATE           | 팝업 종료일         |

**용도:**
- 특정 기간 동안 노출되는 팝업 메시지 관리

**실제 사용 예시:**
- `public/admin/notice_banner_popup.php`에서 팝업 등록/삭제/목록 조회
- 예시 코드:
  ```php
  $stmt = $pdo->prepare("INSERT INTO popups (content, start_at, end_at) VALUES (?, ?, ?)");
  $stmt->execute([$content, $start_at, $end_at]);
  $popups = $pdo->query("SELECT * FROM popups ORDER BY start_at DESC")->fetchAll();
  ```

---

## 9. users

| 컬럼명        | 타입 및 제약조건                | 설명                |
|---------------|-------------------------------|---------------------|
| id            | INT AUTO_INCREMENT PRIMARY KEY | 고유 ID              |
| username      | VARCHAR(100) UNIQUE           | 사용자명(로그인ID)   |
| password      | VARCHAR(255)                  | 비밀번호(해시)       |
| role          | ENUM('admin','guest')         | 권한(관리자/게스트)  |
| name          | VARCHAR(100)                  | 이름                 |
| phone         | VARCHAR(50)                   | 전화번호             |
| profile_img   | VARCHAR(255)                  | 프로필 이미지        |
| is_active     | TINYINT(1) DEFAULT 1          | 활성화 여부          |
| created_at    | DATETIME                      | 가입일               |
| updated_at    | DATETIME                      | 수정일               |

**용도:**
- 회원가입, 로그인, 권한 관리, 사용자 정보 관리 등

**실제 사용 예시:**
- `public/login.php`, `src/auth/auth.php`, `src/user/user_management.php`, `src/user/update_profile.php` 등에서 사용
- 예시 코드:
  ```php
  // 로그인
  $sql = "SELECT * FROM users WHERE username = ? AND is_active = 1";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([$username]);
  $user = $stmt->fetch();
  // 회원정보 수정
  $stmt = $pdo->prepare("UPDATE users SET name = :name, phone = :phone WHERE username = :username");
  $stmt->execute([...]);
  ```

---

## 10. vulnerability_reports

| 컬럼명              | 타입 및 제약조건                                                                 | 설명                |
|---------------------|-------------------------------------------------------------------------------|---------------------|
| id                  | INT AUTO_INCREMENT PRIMARY KEY                                                  | 고유 ID              |
| title               | VARCHAR(255) NOT NULL                                                          | 취약점 제목          |
| description         | TEXT NOT NULL                                                                  | 상세 설명            |
| severity            | ENUM('critical','high','medium','low') DEFAULT 'medium'                        | 심각도              |
| category            | ENUM('sql_injection','xss','csrf','file_upload','authentication','information_disclosure','other') DEFAULT 'other' | 취약점 분류         |
| reproduction_steps  | TEXT                                                                           | 재현 단계            |
| impact              | TEXT                                                                           | 영향도              |
| reported_by         | VARCHAR(100) NOT NULL                                                          | 제보자(아이디/이름)  |
| status              | ENUM('pending','investigating','fixed','rejected') DEFAULT 'pending'           | 처리 상태           |
| admin_notes         | TEXT                                                                           | 관리자 메모           |
| created_at          | TIMESTAMP DEFAULT CURRENT_TIMESTAMP                                            | 등록일               |
| updated_at          | TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP                | 수정일               |

**용도:**
- 취약점 제보, 관리, 상태 변경 등 보안 취약점 관리

**실제 사용 예시:**
- `public/vulnerability_report.php`(사용자 제보), `public/admin/vulnerability_management.php`(관리자 관리) 등에서 사용
- 예시 코드:
  ```php
  // 취약점 제보 등록
  $stmt = $pdo->prepare("INSERT INTO vulnerability_reports (title, description, severity, category, reproduction_steps, impact, reported_by, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
  $stmt->execute([...]);
  // 취약점 목록 조회
  $stmt = $pdo->prepare("SELECT * FROM vulnerability_reports WHERE reported_by = ? ORDER BY created_at DESC");
  $stmt->execute([$username]);
  ```

---

> 각 테이블의 실제 사용 위치(파일명), 주요 쿼리, 용도까지 포함하여 문서화하였으니, 새로운 개발자도 쉽게 구조와 활용법을 파악할 수 있습니다. 