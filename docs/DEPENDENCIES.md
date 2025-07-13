# 의존성 정보

## PHP 의존성

### 필수 확장
| 확장 | 버전 | 용도 |
|------|------|------|
| PDO | 7.4+ | DB 연결 |
| JSON | 7.4+ | API 응답 |
| mbstring | 7.4+ | 문자열 처리 |
| fileinfo | 7.4+ | 파일 검증 |
| session | 7.4+ | 세션 관리 |

### PHP 버전
- **최소**: PHP 7.4
- **권장**: PHP 8.1+
- **테스트 완료**: 7.4, 8.0, 8.1, 8.2

---

## 데이터베이스

### MySQL/MariaDB
| 구성 요소 | 최소 버전 | 권장 버전 |
|-----------|-----------|-----------|
| MySQL | 5.7 | 8.0+ |
| MariaDB | 10.3 | 10.6+ |

### 주요 테이블
```sql
admins          # 관리자 계정
guests          # 게스트 계정  
faults          # 고장 관리
logs             # 활동 로그
maintenance      # 유지보수 모드
```

---

## 웹서버

### Apache
- **최소 버전**: 2.4
- **권장 버전**: 2.4+
- **설정 파일**: .htaccess

### Nginx
- **최소 버전**: 1.18
- **권장 버전**: 1.24+
- **설정 파일**: nginx.conf

---

## 보안 라이브러리

### PHPIDS
- **버전**: 내장 라이브러리
- **용도**: 공격 탐지 및 방지
- **위치**: `PHPIDS/lib/IDS/`
- **설정**: `PHPIDS/lib/IDS/Config/Config.ini.php`

#### 주요 기능
- SQL 인젝션 탐지
- XSS 공격 탐지
- 파일 업로드 공격 탐지

#### 설정 예시
```php
'General' => array(
    'filter_type' => 'xml',
    'base_path' => '/path/to/PHPIDS/lib/IDS/',
    'use_base_path' => true,
    'filter_path' => 'default_filter.xml',
    'tmp_path' => '/tmp',
    'scan_keys' => false,
    'exceptions' => array(
        'GET' => array('query'),
        'POST' => array('submit'),
        'COOKIE' => array('session')
    )
)
```

#### 연동된 페이지
- 로그인 페이지 (`public/login.php`)
- PLC 제어 페이지 (`public/control.php`)
- 고장 관리 페이지 (`public/faults.php`)
- 회원가입 페이지 (`public/make_account.php`)
- 로그 페이지 (`public/logs.php`)

---

## 프론트엔드

### CSS 프레임워크
| 라이브러리 | 버전 | 용도 |
|------------|------|------|
| Bootstrap | 5.3.0 | UI 프레임워크 |
| Custom CSS | 1.0.0 | 커스텀 스타일 |

### JavaScript 라이브러리
| 라이브러리 | 버전 | 용도 |
|------------|------|------|
| jQuery | 3.7.0 | DOM 조작 |
| Chart.js | 4.3.0 | 차트 생성 |
| Moment.js | 2.29.4 | 날짜/시간 처리 |
| SweetAlert2 | 11.7.0 | 알림 모달 |

### 개발 도구
| 도구 | 버전 | 용도 |
|------|------|------|
| Webpack | 5.88.0 | 번들링 |
| Sass | 1.62.0 | CSS 전처리기 |
| ESLint | 8.42.0 | 코드 검사 |
| Jest | 29.5.0 | 테스트 |

---

## 개발 환경

### Composer (PHP)
- **버전**: 2.0+
- **설정 파일**: `composer.json`
- **자동로드**: PSR-4 표준

#### 주요 스크립트
```bash
composer install          # 의존성 설치
composer update          # 의존성 업데이트
composer test            # 테스트 실행
composer security:check  # 보안 검사
```

### npm (Node.js)
- **버전**: 8.0+
- **설정 파일**: `package.json`
- **빌드 도구**: Webpack, Sass

#### 주요 스크립트
```bash
npm install              # 의존성 설치
npm run build            # 프로덕션 빌드
npm run dev              # 개발 모드
npm run test             # 테스트 실행
npm run lint             # 코드 검사
```

---

## 시스템 요구사항

### 하드웨어
| 구성 요소 | 최소 사양 | 권장 사양 |
|-----------|-----------|-----------|
| CPU | 1코어 | 2코어+ |
| RAM | 1GB | 4GB+ |
| 저장공간 | 10GB | 50GB+ |
| 네트워크 | 10Mbps | 100Mbps+ |

### 권한 설정
```bash
# 웹서버 권한
sudo chown -R www-data:www-data /var/www/html/rotator-system/
sudo chmod 755 /var/www/html/rotator-system/
sudo chmod 755 /var/www/html/rotator-system/uploads/

# 로그 파일 권한
sudo chmod 644 /var/log/apache2/error.log
sudo chmod 644 /var/log/nginx/error.log
```

---

## 의존성 업데이트

### 보안 업데이트
```bash
# 정기 점검
composer audit
npm audit

# 업데이트 실행
composer update
npm update

# 테스트 실행
composer test
npm run test
```

### 메이저 버전 업데이트
```bash
# 백업 생성
composer dump-autoload
npm run build

# 단계적 업데이트
composer update --with-dependencies
npm update --save

# 호환성 검사
php -l src/
npm run lint
```

---

## 문제 해결

### 일반적인 문제
| 문제 | 원인 | 해결 방법 |
|------|------|-----------|
| Composer 메모리 부족 | PHP 메모리 제한 | `php -d memory_limit=-1 composer install` |
| npm 권한 오류 | 권한 문제 | `sudo npm install` 또는 `npm install --unsafe-perm` |
| PHP 확장 누락 | 확장 미설치 | `sudo apt-get install php-mysql php-json` |

### 디버깅 명령어
```bash
# PHP 정보 확인
php -m
php -i | grep extension

# Composer 진단
composer diagnose
composer show

# npm 진단
npm doctor
npm list
```

---

## 라이센스 정보

| 라이브러리 | 라이센스 | 버전 |
|------------|----------|------|
| PHPIDS | LGPL | 내장 |
| Bootstrap | MIT | 5.3.0 |
| jQuery | MIT | 3.7.0 |
| Chart.js | MIT | 4.3.0 |
| Moment.js | MIT | 2.29.4 |
| SweetAlert2 | MIT | 11.7.0 |

모든 의존성은 오픈소스 라이센스를 따르며, 상업적 사용이 가능합니다. 