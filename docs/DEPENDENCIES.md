# 의존성 및 환경 정보

본 문서는 PLC Rotator System의 설치/운영에 필요한 주요 의존성, 환경, 테이블, 연동 경로, 보안 정책을 실제 코드/운영 흐름과 일치하게 정리합니다.

## 1. 필수 소프트웨어/버전
- PHP 7.4 이상 (8.1+ 권장)
- MySQL 5.7 이상 (8.0+ 권장)
- Apache 2.4+ 또는 Nginx 1.18+
- Node.js 16.0+ (프론트엔드 빌드)

## 2. 주요 PHP 확장
- PDO, JSON, mbstring, fileinfo, session

## 3. 데이터베이스/테이블
- users: 계정/권한(관리자/게스트) 관리 (role 컬럼으로 구분)
- faults: 고장 이력/첨부파일
- logs: 이벤트/보안 로그
- maintenance: 유지보수(점검) 모드/스케줄
- notices: 공지사항
- notifications: 알림 메시지
- vulnerability_reports: 취약점 제보/관리
- popups, banners: 팝업/배너 메시지

## 4. 주요 디렉토리/연동 경로
- public/: 서비스 페이지(메인, 제어, 고장, 점검, 취약점, 로그, 관리자 로그인 등)
  - admin/: 관리자 전용 기능(취약점/계정/파일/시스템 관리 등)
  - assets/: CSS, JS, 이미지 등 정적 리소스
  - uploads/: 첨부파일/프로필 이미지 저장소
- src/: DB, 인증, 로그, 사용자 관리 등 핵심 로직
- sql/: DB 스키마/초기화/샘플 데이터
- PHPIDS/: 보안 라이브러리(공격 탐지)
- docs/: 운영/유지보수, 변경이력, 의존성, 버전, DB 스키마 등 문서

## 5. 프론트엔드/JS 라이브러리
- jQuery, Chart.js, Moment.js, SweetAlert2 등

## 6. 개발/운영 도구
- Composer(2.0+), npm(8.0+), Webpack, Sass, ESLint, Jest 등

## 7. 권한/보안 설정
- uploads/, /tmp 등 디렉토리 권한 755, 소유자 www-data
- DB/로그/파일 접근 권한 최소화
- prepared statement, 파일 업로드 제한, 세션/비밀번호 정책 적용
- 관리자/게스트 권한(role), 점검 중 관리자 전용 로그인(admin_login.php) 항상 허용
- PHPIDS 기반 공격 탐지, XSS 방어(htmlspecialchars), CSRF 방어, 예외/에러 최소화

## 8. 참고
- 상세 구조/흐름: PROJECT_DETAIL.md, README.md, DB_SCHEMA.md 참고
- 장애/보안/운영 문의: dev@rotator-system.com 