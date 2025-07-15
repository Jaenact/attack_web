# 변경 이력

## [1.1.3] - 2025-07-15

### 보안/취약점 및 기능 수정 내역
- [취약점] 파일 업로드 기능에서 .php5, .phtml, .phar 등 우회 확장자 및 멀티 확장자(shell.php.jpg) 업로드 가능. 업로드 후 직접 접근 시 웹쉘 실행 위험. (public/faults.php)
- [취약점] 고장게시판 담당자(manager) 필드에 XSS(스크립트 삽입) 가능. 담당자 입력값에 <script>, <img onerror=...> 등 악성 코드 삽입 시, 고장 목록 조회 시 관리자/사용자 브라우저에서 스크립트 실행되어 세션 탈취, 권한 상승 등 2차 피해 발생 가능. (public/faults.php)
- [수정] 담당자(manager) 필드 출력 시 htmlspecialchars() 적용하여 XSS 방어. (public/faults.php)
- [수정] 파일 업로드 시 허용 확장자/실제 MIME 타입 검사 강화, 멀티 확장자/우회 방지 로직 추가. (public/faults.php)


## [1.1.2] - 2025-07-14

### 사용자 관리(계정/비밀번호/UX)
- [추가] 사용자 관리에서 계정 완전 삭제(DELETE) 기능 도입, 삭제 버튼 신설(관리자 계정은 삭제 불가)
- [변경] 비밀번호 초기화 시 관리자가 직접 새 비밀번호를 입력하여 즉시 변경 가능(랜덤 X, 인라인 입력폼)
- [개선] 전체 UI/UX를 화이트+파란(하늘) 계열로 심플하게 리디자인(테이블, 버튼, 입력폼, 배경 등)
- [개선] 관리 버튼(삭제/비번초기화) 컬러와 명칭 명확화, 불필요한 요소 최소화, 가독성 향상
- [수정] public/admin/user_management.php 내 모든 관련 로직 및 화면 구조 일원화

## [1.1.1] - 2025-07-13

### 대시보드/UX/화면
- [추가] 사용자 대시보드에 내 최근 활동(고장 제보, 취약점 제보, 알림) 섹션 신설 (`public/index.php`)
- [개선] 공지사항 카드 레이아웃 및 정보(제목, 내용, 작성자) 표시 방식 개선 (`public/index.php`)
- [수정] 운영/시스템 카드 클릭 시 드롭다운 → 모달 팝업 방식으로 변경, 외부 클릭/ESC로 닫힘 (`public/index.php`)
- [개선] 관리자 대시보드 카드/섹션 단순화, 주요 통계/상태 실시간 갱신, 불필요한 통계/카드 통합 (`public/index.php`)
- [추가] 실시간 알림(공격, 고장, 제어 등) 토스트/뱃지 표시, 알림 드롭다운 UI (`public/index.php`, `public/admin/notify_api.php`)
- [개선] 반응형 레이아웃, flex 구조, JS 이벤트 처리 등 반복 피드백 반영

### 기능/로직/백엔드
- [추가] 알림 테이블 및 테스트 데이터 생성, 알림 API 연동 (`src/log/log_function.php`, `public/admin/notify_api.php`)
- [개선] 공지/점검/운영/보안 관리 기능을 카드/토글/모달 내에서 직관적으로 통제 가능하도록 최적화 (`public/index.php`, `public/admin/system_status.php`)
- [수정] 컬럼/테이블 오류, DB 스키마/초기화/샘플 데이터 보강 (`sql/rotator_system.sql`)
- [추가] 보안 로그 API 및 대시보드 연동 (`public/admin/get_security_logs.php`, `public/security_dashboard.php`)

### 보안/운영
- [강화] PHPIDS 기반 공격 탐지 로직 개선, 보안 로그 별도 관리 (`src/log/log_function.php`, `public/control.php`, `public/faults.php`, `public/make_account.php`)
- [개선] DB/코드/파일/운영 로그 백업/복구/모니터링 가이드 보강 (`docs/MAINTENANCE.md`)
- [추가] 보안 이벤트 통계, 유형별/시간대별/상위IP 등 상세 대시보드 구현 (`public/security_dashboard.php`, `public/admin/security_center.php`)

### 문서/협업
- [리뉴얼] README.md, docs/README.md, MAINTENANCE.md 등 실무형 문서로 전면 개편
- [추가] PROJECT_DETAIL.md 기반 전체 구조/흐름/보안/운영 문서화
- [정비] CHANGELOG.md, DEPENDENCIES.md, VERSION.md 등 문서 최신화 및 실무 기준 반영

### 기타
- [정리] 불필요/중복/테스트 파일 정리 및 디렉토리 구조 통합
- [추가] 주요 관리자 기능(계정/파일/시스템/점검/공지 등) 별도 파일로 분리(`public/admin/` 하위)

## [1.1.0] - 2025-07-08
- 관리자 UI/UX 대폭 개선, DB 구조 통합, 보안/운영 기능 강화
- 실시간 알림/모니터링/파일/사용자/공지 관리 기능 추가
- PHPIDS 연동, 보안 로그 기록, 공지사항 UX 개선


## [1.0.x] 이하
- PLC 제어, 고장 관리, 활동/보안 로그, 유지보수 모드, 공지/알림 등 기본 기능 구현
- 보안(비밀번호 해시, 세션, prepared statement, 파일 업로드 제한 등) 적용 