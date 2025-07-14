# 운영 및 유지보수 가이드

본 문서는 PLC Rotator System의 실무 운영, 점검, 백업, 복구, 장애 대응, 성능 최적화, 보안 관리 등 현장 중심의 유지보수 절차를 안내합니다.

## 1. 일상 점검 및 모니터링
- 웹서버, DB, 로그, 디스크, 네트워크 상태 주기적 확인
- 주요 서비스(제어, 고장, 로그인 등) 정상 동작 체크
- 보안 로그 및 PHPIDS 경고 주기적 검토

## 2. 정기 백업 및 복구
- DB, 코드, 첨부파일(uploads/) 정기 백업 스크립트 운영
- 백업 파일 30일 이상 보관, 주기적 복원 테스트
- 복구 시 DB, 코드, 파일 권한까지 일괄 적용

## 3. 장애/문제 발생 시 대응
- 웹서버/DB 재시작, 로그 분석, 디스크/메모리 점유율 확인
- PHPIDS, 시스템 로그, 에러 로그 등 다각도 진단
- 주요 명령어 및 체크리스트는 아래 참조

## 4. 성능 최적화
- DB 인덱스 추가, 로그/이력 테이블 정리
- PHP, 웹서버, DB 설정 최적화(메모리, 캐시 등)
- 불필요한 파일/데이터 주기적 정리

## 5. 보안 관리
- 비밀번호 정책 강화, 세션/권한 관리
- PHPIDS, prepared statement, 파일 업로드 제한 등 적용
- 보안 패치 및 의존성 업데이트 주기적 수행

## 6. 주요 명령어/스크립트 예시

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

## 7. 참고
- 상세 구조/흐름: PROJECT_DETAIL.md, README.md 참고
- 장애/보안/운영 문의: dev@rotator-system.com 