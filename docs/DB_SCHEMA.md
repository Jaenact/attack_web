# 데이터베이스 스키마 안내

이 프로젝트에서 사용되는 모든 주요 데이터베이스 테이블 정보를 실제 코드/운영 흐름과 100% 일치하도록 정리합니다.

---

## 1. users
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
- 회원가입, 로그인, 권한(관리자/게스트) 관리, 사용자 정보 관리 등
- 관리자 전용 로그인(admin_login.php)에서 role='admin'으로 인증

**실제 사용 예시:**
- `public/login.php`, `public/admin_login.php`, `src/auth/auth.php`, `src/user/user_management.php` 등

---

## 2. maintenance
| 컬럼명      | 타입 및 제약조건                | 설명                |
|-------------|-------------------------------|---------------------|
| id          | INT AUTO_INCREMENT PRIMARY KEY | 고유 ID              |
| is_active   | TINYINT(1) DEFAULT 0          | 유지보수 활성화 여부 |
| start_at    | DATETIME                      | 유지보수 시작 시각   |
| end_at      | DATETIME                      | 유지보수 종료 시각   |
| created_by  | VARCHAR(100)                  | 점검 시작자(관리자)  |

**용도:**
- 시스템 유지보수(점검) 모드 관리, 점검 기간 안내, 자동화(만료 시 자동 정상화, 남은 시간 실시간 표시)
- 점검 종료 시 안내 페이지(maintenance_end.php)로 자동 이동

**실제 사용 예시:**
- `public/index.php`, `public/maintenance.php`, `public/check_maintenance_status.php`, `src/db/db.php` 등

---

## 3. faults
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
- `public/faults.php`, `public/admin/fault_maintenance_history.php` 등

---

## 4. logs
| 컬럼명        | 타입 및 제약조건                | 설명                |
|---------------|-------------------------------|---------------------|
| id            | INT AUTO_INCREMENT PRIMARY KEY | 고유 ID              |
| username      | VARCHAR(100)                  | 사용자명             |
| ip_address    | VARCHAR(45)                   | IP 주소              |
| log_message   | TEXT                          | 로그 메시지          |
| created_at    | DATETIME                      | 생성일               |

**용도:**
- 시스템 내 모든 주요 이벤트(로그인, 고장등록, 점검, 보안이벤트 등) 기록 및 감사 로그

**실제 사용 예시:**
- `src/log/log_function.php`의 `writeLog()` 함수, `public/logs.php` 등

---

## 5. notices
| 컬럼명      | 타입 및 제약조건                | 설명                |
|-------------|-------------------------------|---------------------|
| id          | INT AUTO_INCREMENT PRIMARY KEY | 고유 ID              |
| title       | VARCHAR(255)                  | 공지 제목            |
| content     | TEXT                          | 공지 내용            |
| created_at  | DATETIME                      | 등록일               |

**용도:**
- 공지사항 등록/수정/삭제/목록 관리, 대시보드/공지 카드 등

**실제 사용 예시:**
- `public/index.php`, `public/admin/notice_banner_popup.php` 등

---

## 6. notifications
| 컬럼명      | 타입 및 제약조건                | 설명                |
|-------------|-------------------------------|---------------------|
| id          | INT AUTO_INCREMENT PRIMARY KEY | 고유 ID              |
| type        | VARCHAR(50)                   | 알림 유형            |
| message     | TEXT                          | 알림 메시지          |
| url         | VARCHAR(255)                  | 관련 URL             |
| target      | VARCHAR(50)                   | 알림 대상(관리자 등) |
| created_at  | DATETIME                      | 생성일               |

**용도:**
- 주요 이벤트 발생 시 관리자 등에게 알림 메시지 발송/저장, 대시보드/알림 카드 등

**실제 사용 예시:**
- `src/log/log_function.php`, `public/index.php` 등

---

## 7. vulnerability_reports
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
- 취약점 제보, 관리, 상태 변경 등 보안 취약점 관리, 관리자/사용자 분리

**실제 사용 예시:**
- `public/vulnerability_report.php`, `public/admin/vulnerability_management.php` 등

---

## 8. popups, banners
| 컬럼명   | 타입           | 설명                |
|----------|----------------|---------------------|
| id       | INT, PK        | 고유 ID             |
| content  | TEXT           | 표시할 내용         |
| start_at | DATE           | 시작일              |
| end_at   | DATE           | 종료일              |

**용도:**
- 기간 한정 배너/팝업 메시지 관리, 대시보드/공지 등에서 활용

**실제 사용 예시:**
- `public/admin/notice_banner_popup.php` 등

---

> 각 테이블의 실제 사용 위치(파일명), 주요 쿼리, 용도까지 포함하여 문서화하였으니, 새로운 개발자도 쉽게 구조와 활용법을 파악할 수 있습니다. 