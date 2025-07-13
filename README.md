# PLC Rotator Control System

PLC 로터터 제어 및 통합 관리 시스템입니다.

## Quick Start

```bash
# 1. 저장소 클론
git clone https://github.com/rotator-system/plc-control.git
cd rotator-system

# 2. 의존성 설치
composer install
npm install

# 3. DB 설정 및 관리자 계정 생성
composer run setup

# 4. 권한 설정
sudo chown -R www-data:www-data uploads/
sudo chmod 755 uploads/

# 5. 접속
# 브라우저에서 http://your-server/rotator-system/ 접속
```

## Requirements

- **PHP**: 7.4+ (8.1+ 권장)
- **MySQL**: 5.7+ (8.0+ 권장)  
- **Web Server**: Apache 2.4+ 또는 Nginx 1.18+
- **Node.js**: 16.0+ (프론트엔드 빌드용)

## Default Admin Account

- **Username**: `admin`
- **Password**: `ateam4567!`

> **Important**: Change the default password after your first login.

## Project Structure

```
rotator-system/
├── public/          # 웹 페이지 (메인, 제어, 고장, 로그 등)
├── src/             # 핵심 로직 (DB, 인증, 로그, 사용자 관리)
├── assets/          # 정적 리소스 (CSS, JS, 이미지)
├── uploads/         # 업로드 파일 저장소
├── sql/             # DB 스키마 및 초기화 스크립트
├── docs/            # 문서 (변경이력, 의존성, 유지보수)
└── PHPIDS/          # 보안 라이브러리
```

## Features

- **PLC Control**: Real-time ON/OFF, RPM setting (admin only)
- **Fault Management**: Register/edit/delete faults with file attachments
- **Activity Log**: All major events logging with IP masking
- **User Management**: Admin/guest role separation
- **Maintenance Mode**: Real-time ON/OFF with admin exception access
- **Security**: PHPIDS attack detection, session authentication

## Development Commands

### PHP (Composer)
```bash
composer test              # 테스트 실행
composer security:check    # 보안 검사
composer setup             # DB 및 관리자 설정
composer backup            # DB 백업
```

### Frontend (npm)
```bash
npm run build             # 프로덕션 빌드
npm run dev               # 개발 모드
npm run test              # 테스트 실행
npm run lint              # 코드 검사
```

## Database Schema

| Table | Purpose | Key Features |
|-------|---------|--------------|
| `admins` | Admin accounts | Role-based access, password hashing |
| `guests` | Guest accounts | Limited permissions, activity tracking |
| `faults` | Fault management | File attachments, status tracking |
| `logs` | Activity logging | IP masking, detailed event tracking |
| `maintenance` | Maintenance mode | Real-time control, admin exceptions |

## Security Features

- **Password Hashing**: bcrypt encryption (no plain text storage)
- **Session Management**: Complete session destruction on logout
- **Attack Detection**: PHPIDS integrated threat detection
- **File Upload Security**: Type and size restrictions
- **Role-Based Access**: Strict admin/guest permissions
- **SQL Injection Prevention**: Prepared statements only
- **IP Privacy**: Masked IP addresses in logs

## Documentation

- **[docs/CHANGELOG.md](docs/CHANGELOG.md)** - 모든 변경 이력
- **[docs/VERSION.md](docs/VERSION.md)** - 현재 버전 정보
- **[docs/DEPENDENCIES.md](docs/DEPENDENCIES.md)** - 의존성 상세 정보
- **[docs/MAINTENANCE.md](docs/MAINTENANCE.md)** - 유지보수 가이드
- **[PROJECT_DETAIL.md](PROJECT_DETAIL.md)** - 프로젝트 상세 설명

## Deployment

### Pre-deployment Checklist
- [ ] All tests passing
- [ ] Security audit completed
- [ ] Database backup created
- [ ] Environment variables configured
- [ ] File permissions set correctly
- [ ] SSL certificate installed
- [ ] Monitoring configured

### Deployment Commands
```bash
# 프로덕션 빌드
npm run build
composer install --no-dev --optimize-autoloader

# 서비스 재시작
sudo systemctl restart apache2
sudo systemctl restart mysql

# 헬스 체크
curl -I http://your-server/rotator-system/
```

## Troubleshooting

### Common Issues

**Web Server Connection Error**
```bash
sudo systemctl restart apache2
sudo systemctl status apache2
```

**Database Connection Error**
```bash
sudo systemctl restart mysql
mysql -u username -p -h localhost
```

**File Upload Error**
```bash
sudo chown -R www-data:www-data uploads/
sudo chmod 755 uploads/
df -h  # 디스크 공간 확인
```

**Session Error**
```bash
sudo chown -R www-data:www-data /tmp/
sudo chmod 755 /tmp/
```

## Support

- **Email**: dev@rotator-system.com
- **Documentation**: See [docs/MAINTENANCE.md](docs/MAINTENANCE.md)
- **Issues**: Report bugs via GitHub Issues

## License

MIT License - see [LICENSE](LICENSE) file for details.

---

**Automation** | **Reliability** | **Security** | **Clean Code** 