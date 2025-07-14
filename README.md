# PLC Rotator Control System

## 프로젝트 개요
PLC 기반 설비의 통합 제어 및 관리 시스템입니다. 관리자/게스트 권한 분리, 실시간 제어, 고장/보안/운영 로그, 유지보수 모드, 보안(공격 탐지) 등 현장 실무에 필요한 기능을 제공합니다.

## 주요 기능
- 실시간 PLC 제어(ON/OFF, RPM 설정)
- 고장/취약점 제보 및 관리(파일 첨부 포함)
- 공지/알림/점검 등 대시보드 통합 관리
- 관리자/게스트 권한 분리 및 계정 관리
- 활동/보안 로그 기록 및 통계
- 유지보수(점검) 모드, 실시간 알림
- PHPIDS 기반 공격 탐지 및 보안 강화

## 설치 및 실행
1. 저장소 클론
   ```bash
   git clone https://github.com/rotator-system/plc-control.git
   cd rotator-system
   ```
2. 의존성 설치
   ```bash
   composer install
   npm install
   ```
3. DB 및 관리자 계정 생성
   ```bash
   composer run setup
   ```
4. 권한 설정
   ```bash
   sudo chown -R www-data:www-data uploads/
   sudo chmod 755 uploads/
   ```
5. 웹서버 접속
   - http://your-server/rotator-system/

## 폴더 구조
```
rotator-system/
├── public/        # 서비스 페이지(메인, 제어, 고장, 로그 등)
├── src/           # 핵심 로직(DB, 인증, 로그, 사용자 관리)
├── assets/        # 정적 리소스(CSS, JS, 이미지)
├── uploads/       # 첨부파일 저장소
├── sql/           # DB 스키마/초기화
├── docs/          # 상세 문서(운영, 변경이력 등)
├── PHPIDS/        # 보안 라이브러리
```

## 운영/유지보수
- 점검/백업/복구/모니터링 등 실무 운영 가이드는 `docs/MAINTENANCE.md` 참고
- 의존성/환경 정보는 `docs/DEPENDENCIES.md` 참고
- 변경이력 및 버전 정보는 `docs/CHANGELOG.md`, `docs/VERSION.md` 참고

## 문서/지원
- 상세 문서: `docs/` 폴더 및 각 md 파일 참고
- 문의: dev@rotator-system.com
- 이슈: GitHub Issues 활용

## 라이선스
MIT License (LICENSE 파일 참조) 