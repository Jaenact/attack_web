# 유지보수 가이드

## 일일 점검

### 시스템 상태
- [ ] 웹서버 상태 확인
- [ ] DB 연결 확인
- [ ] 로그 파일 크기 확인
- [ ] 디스크 공간 확인

### 보안 점검
- [ ] 로그인 시도 로그 확인
- [ ] PHPIDS 경고 로그 확인
- [ ] 파일 업로드 로그 확인

### 기능 테스트
- [ ] 관리자 로그인 테스트
- [ ] PLC 제어 기능 테스트
- [ ] 고장 등록 기능 테스트

---

## 주간 점검

### 성능 최적화
- [ ] DB 쿼리 성능 분석
- [ ] 로그 테이블 정리 (30일 이상)
- [ ] 임시 파일 정리

### 백업 확인
- [ ] DB 백업 실행
- [ ] 코드 백업 확인
- [ ] 업로드 파일 백업 확인

### 보안 강화
- [ ] 비밀번호 정책 확인
- [ ] 접근 권한 재검토
- [ ] 보안 패치 적용 확인

---

## 문제 해결

### 웹서버 연결 오류
```bash
sudo systemctl restart apache2
sudo systemctl status apache2
```

### DB 연결 오류
```bash
sudo systemctl restart mysql
mysql -u username -p -h localhost
```

### 파일 업로드 오류
```bash
sudo chown -R www-data:www-data uploads/
sudo chmod 755 uploads/
df -h  # 디스크 공간 확인
```

### 세션 오류
```bash
sudo chown -R www-data:www-data /tmp/
sudo chmod 755 /tmp/
```

### PHPIDS 오류
```bash
# PHPIDS 라이브러리 확인
ls -la PHPIDS/lib/IDS/

# 보안 로그 확인
tail -f /var/www/html/rotator-system/logs/security.log
```

---

## 백업 및 복구

### 자동 백업 스크립트
```bash
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backup/rotator-system"

mkdir -p $BACKUP_DIR

# DB 백업
mysqldump -u username -p'password' rotator_system > $BACKUP_DIR/db_backup_$DATE.sql

# 코드 백업
tar -czf $BACKUP_DIR/code_backup_$DATE.tar.gz /var/www/html/rotator-system/

# 업로드 파일 백업
tar -czf $BACKUP_DIR/uploads_backup_$DATE.tar.gz /var/www/html/rotator-system/uploads/

# 30일 이상 된 백업 삭제
find $BACKUP_DIR -name "*.sql" -mtime +30 -delete
find $BACKUP_DIR -name "*.tar.gz" -mtime +30 -delete
```

### 복구 절차
```bash
# DB 복구
mysql -u username -p rotator_system < /backup/rotator-system/db_backup_20240101_120000.sql

# 코드 복구
tar -xzf /backup/rotator-system/code_backup_20240101_120000.tar.gz -C /

# 권한 복구
sudo chown -R www-data:www-data /var/www/html/rotator-system/
sudo chmod 755 /var/www/html/rotator-system/
```

---

## 성능 최적화

### DB 최적화
```sql
-- 인덱스 추가
CREATE INDEX idx_logs_created_at ON logs(created_at);
CREATE INDEX idx_logs_username ON logs(username);
CREATE INDEX idx_faults_created_at ON faults(created_at);
```

### 웹서버 최적화
```apache
# Apache 설정
<IfModule mpm_prefork_module>
    StartServers          5
    MinSpareServers       5
    MaxSpareServers      10
    MaxRequestWorkers    150
</IfModule>
```

### PHP 최적화
```ini
; php.ini
memory_limit = 256M
max_execution_time = 300
upload_max_filesize = 10M
opcache.enable = 1
opcache.memory_consumption = 128
```

---

## 모니터링

### 시스템 모니터링
```bash
#!/bin/bash
# 디스크 사용량 확인
DISK_USAGE=$(df / | awk 'NR==2 {print $5}' | sed 's/%//')
if [ $DISK_USAGE -gt 80 ]; then
    echo "경고: 디스크 사용량 80% 초과 ($DISK_USAGE%)"
fi

# 메모리 사용량 확인
MEMORY_USAGE=$(free | awk 'NR==2{printf "%.2f", $3*100/$2}')
if (( $(echo "$MEMORY_USAGE > 80" | bc -l) )); then
    echo "경고: 메모리 사용량 80% 초과 ($MEMORY_USAGE%)"
fi

# 웹서버 상태 확인
if ! systemctl is-active --quiet apache2; then
    echo "오류: Apache 서비스 중단"
fi
```

### 로그 모니터링
```bash
# 에러 로그 확인
tail -f /var/log/apache2/error.log

# 보안 로그 확인
tail -f /var/www/html/rotator-system/logs/security.log

# 특정 IP 접근 확인
grep "192.168.1.100" /var/log/apache2/access.log

# PHPIDS 경고 확인
grep "공격감지" /var/www/html/rotator-system/logs/security.log
```

---

## 프로젝트 특화 점검

### PLC 제어 시스템
- [ ] PLC 연결 상태 확인
- [ ] 제어 명령 전송 테스트
- [ ] RPM 설정 기능 확인
- [ ] 실시간 상태 모니터링

### 고장 관리 시스템
- [ ] 고장 등록 기능 테스트
- [ ] 파일 첨부 기능 확인
- [ ] 상태 변경 기능 확인
- [ ] 이메일 알림 기능 확인

### 사용자 관리
- [ ] 관리자/게스트 권한 분리 확인
- [ ] 비밀번호 변경 기능 확인
- [ ] 계정 삭제 기능 확인
- [ ] 활동 로그 기록 확인

---

## 연락처

- **이메일**: dev@rotator-system.com
- **전화**: 02-1234-5678
- **긴급연락**: 010-1234-5678

### 지원 시간
- **평일**: 09:00 ~ 18:00
- **주말**: 10:00 ~ 16:00
- **긴급**: 24시간 