# 운영 및 유지보수 가이드

본 문서는 PLC Rotator System의 실무 운영, 점검 자동화, 관리자 전용 로그인, 실시간 반영, 백업, 복구, 장애 대응, 성능 최적화, 보안 관리 등 현장 중심의 유지보수 절차를 실제 코드/운영 흐름과 일치하게 안내합니다.

## 1. 일상 점검 및 모니터링
- 웹서버, DB, 로그, 디스크, 네트워크 상태 주기적 확인
- 주요 서비스(제어, 고장, 로그인, 점검, 취약점 등) 정상 동작 체크
- 대시보드(index.php)에서 점검 상태/남은 시간/실시간 상태 갱신 확인
- 보안 로그 및 PHPIDS 경고 주기적 검토

## 2. 점검(유지보수) 모드 관리
- 유지보수(점검) 모드는 자동화되어 만료 시 자동 정상화, 남은 시간(분:초) 실시간 표시
- 점검 종료 시 모든 사용자에게 안내 페이지(maintenance_end.php)로 자동 이동
- 점검 중에도 관리자 전용 로그인(admin_login.php)으로 관리자 접근/점검 해제 가능
- 점검 상태/해제는 대시보드에서 카드 클릭→모달→확인(예/아니오) UX로 처리

## 3. 정기 백업 및 복구
- DB, 코드, 첨부파일(uploads/) 정기 백업 스크립트 운영
- 백업 파일 30일 이상 보관, 주기적 복원 테스트
- 복구 시 DB, 코드, 파일 권한까지 일괄 적용

## 4. 장애/문제 발생 시 대응
- 웹서버/DB 재시작, 로그 분석, 디스크/메모리 점유율 확인
- PHPIDS, 시스템 로그, 에러 로그 등 다각도 진단
- 관리자/게스트 권한, 세션/점검 상태, 실시간 반영 로직 등 점검
- 주요 명령어 및 체크리스트는 아래 참조

## 5. 성능 최적화
- DB 인덱스 추가, 로그/이력 테이블 정리
- PHP, 웹서버, DB 설정 최적화(메모리, 캐시 등)
- 불필요한 파일/데이터 주기적 정리

## 6. 보안 관리
- 비밀번호 정책 강화, 세션/권한 관리(관리자/게스트 role 구분)
- PHPIDS, prepared statement, 파일 업로드 제한 등 적용
- 관리자 전용 로그인(admin_login.php) 항상 허용, 점검 중에도 접근 가능
- 보안 패치 및 의존성 업데이트 주기적 수행

## 7. 주요 명령어/스크립트 예시

### 서비스 점검/재시작
```bash
sudo systemctl restart apache2
sudo systemctl status apache2
sudo systemctl restart mysql
```

### DB/코드/파일 백업
```bash
mysqldump -u username -p'password' rotator_system > /backup/db_backup.sql
tar -czf /backup/code_backup.tar.gz /var/www/html/rotator-system/
tar -czf /backup/uploads_backup.tar.gz /var/www/html/rotator-system/uploads/
```

### 복구
```bash
mysql -u username -p rotator_system < /backup/db_backup.sql
tar -xzf /backup/code_backup.tar.gz -C /
tar -xzf /backup/uploads_backup.tar.gz -C /
sudo chown -R www-data:www-data /var/www/html/rotator-system/
sudo chmod 755 /var/www/html/rotator-system/
```

### 모니터링/로그 확인
```bash
df -h
free -m
tail -f /var/log/apache2/error.log
tail -f /var/www/html/rotator-system/logs/security.log
```

## 8. 참고
- 상세 구조/흐름: PROJECT_DETAIL.md, README.md, DB_SCHEMA.md 참고
- 장애/보안/운영 문의: dev@rotator-system.com 