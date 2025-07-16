# 변경 이력

## [1.1.5] - 2025-07-16

### 점검(유지보수) 기능 및 대시보드/관리자 로그인 개선
- [신설] 관리자 전용 로그인 페이지(admin_login.php) 추가: 점검(유지보수) 중에도 관리자가 별도 로그인/점검 해제 가능하도록 구현. 이 페이지는 점검 상태와 무관하게 항상 접근 가능하며, 로그인 성공 시 세션에 admin 권한 부여
- [개선] 점검(유지보수) 기능 자동화 및 실시간 반영 로직 구현 (만료 시 자동 정상화, 남은 시간 실시간 표시, 점검 종료 시 자동 전환)
- [UI/UX] 대시보드(index.php) 점검 상태 카드, 점검 해제 UX(확인 모달), 실시간 상태 갱신 등 실무형 UI/UX로 리디자인
- [정비] 관리자 인증 쿼리(role='admin')로 통일, DB 구조와 일치하도록 수정
- [정비] index.php 내 디버깅용 error_log 코드 일괄 제거
- [정비] 전체적으로 점검/안내/종료 페이지 및 대시보드 UI를 심플&모던 스타일로 통일
- [UI/UX] admin_login.php에 Pretendard 폰트, 카드형 디자인, 반응형 스타일 적용. 로그인 실패 시 에러 메시지 노출, 입력폼/버튼 등 실무형 UX 반영
- [보안/진단] 세션 체크 및 에러 진단 코드 개선, 관리자/게스트 구분 및 권한 처리 명확화

### 취약점 제보 기능 정식 구현현
- [개선] 네비게이션 바에서 필요한 권한에 따라 분리적 이동(public/vulnerability_report.php: 사용자 페이지, /public/admin/vulnerability_management.php: 관리자 페이지지)
- [수정] public/vulnerability_report.php에서 관리자 접근 부분, DB 생성 부분은 제거거
- [UI/UX 대폭 개선] 관리자 취약점 관리(vulnerability_management.php) 상세 패널:
  - 제목/심각도/상태/분류/제보자/등록일을 카드 스타일로 요약, 정보 가독성 강화
  - 설명/재현 단계/영향도/메모 등은 구분선과 여백으로 섹션 분리, 폰트/컬러/여백 개선
  - 상태 변경 버튼은 현재 상태에 따라 다음 단계만 노출(검토대기→조사중→수정완료→거부됨)
  - 심각도/메모/저장/삭제 등은 하단 별도 영역에 넉넉한 여백과 명확한 버튼으로 배치
  - 전체적으로 카드/섹션 스타일, 여백, 컬러, 폰트 등 UI/UX를 실무적으로 개선


## [1.1.4] - 2025-07-16

### 보안/취약점 및 기능 개선
- [보안] 취약점 관리(취약점 상태 변경/일괄처리) 등 주요 POST 요청에 CSRF 방어 필요성 점검 및 안내 (public/admin/vulnerability_management.php)
- [보안] 모든 출력에 htmlspecialchars 적용, XSS 방어 강화 (public/faults.php, public/admin/vulnerability_management.php 등)
- [보안] 세션/권한 체크 강화, 세션 하이재킹 방지 로직 점검 (public/index.php, public/control.php 등)
- [보안] 입력값 타입/길이/허용값 서버 검증 강화 (public/faults.php, public/admin/vulnerability_management.php 등)
- [보안] 예외 처리 및 에러 메시지 노출 최소화, 상세 에러는 로그로만 기록 (src/db/db.php 등)
- [보안] X-Frame-Options 등 보안 헤더 적용 권고

### UI/UX 및 레이아웃 개선
- [개선] 고장 게시판, 취약점 관리, 대시보드 등 주요 화면의 레이아웃/스타일/반응형 구조 개선 (public/faults.php, public/admin/vulnerability_management.php, public/index.php)
- [개선] 공통 레이아웃(templates/layout.php) 구조 통합 및 네비게이션/프로필/푸터 등 UI 일원화
- [개선] Chart.js 차트, 캐러셀, 통계 카드 등 대시보드 시각화/UX 강화 (public/index.php)
- [개선] 버튼, 입력폼, 테이블 등 주요 UI 요소 크기/정렬/명칭/컬러 개선

### DB/코드 구조 및 파일 정리
- [통합] DB 설정/함수 통합, 불필요/중복/테스트 파일 정리 및 src/db/maintenance_check.php, src/db/log_function.php 등 삭제
- [정비] 공통 함수/레이아웃/스타일 적용, 코드 일관성 강화

### 기타
- [정비] CHANGELOG.md 등 문서 최신화 및 실무 기준 반영
- [정리] create_new_log.php, test_password.php, test_phpids.php, test_security_log.php 등 테스트/임시 파일 삭제

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