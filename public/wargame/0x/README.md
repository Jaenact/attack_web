# 0e의 저주

## 문제 설명

연구실 컴퓨터에 접근하려면 관리자 로그인이 필요하다.  
소스 코드를 보면 비밀번호는 md5 해시로 비교되며, 비교 연산자는 `==`로 느슨하게 처리된다.  
이는 PHP에서 타입 조작(Type Juggling)이 발생할 수 있는 조건이다.

특히 md5 해시값이 `0e...` 형식으로 시작할 경우,  
PHP는 이를 **과학적 표기법의 숫자 0**으로 인식하여 두 값이 달라도 같다고 판단할 수 있다.

이 문제는 **해시와 느슨한 비교를 악용한 인증 우회**를 다룬다.

## 목표

`admin.php`에 접근하여 플래그를 획득하라.

## 구성 파일

- `login.php`: 로그인 처리, md5 해시 기반 비교
- `admin.php`: 세션이 인증되었을 때만 플래그 제공
- `hint.txt`: 힌트 문서 (문제 코드 내부에 포함 가능)

## 취약점 요약

```php
$db = [
    "admin" => "0e830400451993494058024219903391"
];

if (isset($db[$id]) && md5($pw) == $db[$id]) {
    ...
}
```

PHP는 `md5("QNKCDZO") == "0e830400..."` 처럼 문자열끼리 비교할 때,  
양쪽 모두 `0e`로 시작하면 숫자 0처럼 인식하여 같다고 판단하게 된다.  
이로 인해 인증 우회가 가능하다.

## 풀이 절차

1. 로그인 페이지에서 ID는 `admin`, PW는 `QNKCDZO` 입력
2. 내부적으로 `md5("QNKCDZO") == "0e8304..."` → `0 == 0` 으로 판단되어 로그인 성공
3. `admin.php` 접속 시 플래그 출력

## 예시 페이로드

```
ID: admin
PW: QNKCDZO
```

## 예시 플래그

```
CPS{php_type_juggling_success}
```

## 대응 방안 (보안 지식 확장용)

- 해시 비교 시 `==`가 아닌 `===` 또는 `hash_equals()` 함수 사용
- `0e` 형태의 해시 충돌이 존재한다는 점을 인지하고 비교 방식 설계 필요
- 느슨한 비교는 인증 로직에 절대 사용하지 않도록 한다
