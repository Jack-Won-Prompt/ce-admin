# 팝빌(Popbill) API 연동 가이드

## 개요

팝빌 SDK(linkhub/popbill)를 활용하여 다음 5가지 서비스를 Laravel API로 노출합니다.

| 서비스 | 엔드포인트 접두사 |
|---|---|
| 세금계산서 | `GET/POST /api/popbill/taxinvoice/*` |
| 현금영수증 | `GET/POST /api/popbill/cashbill/*` |
| 카카오 알림톡 | `GET/POST /api/popbill/kakao/*` |
| 문자(SMS/LMS/XMS) | `GET/POST /api/popbill/message/*` |
| 팩스 | `GET/POST /api/popbill/fax/*` |

---

## 환경 설정

`.env` 에 아래 변수를 설정합니다.

```dotenv
POPBILL_ID=LINKTHELAB
POPBILL_SECRET_KEY=<팝빌 시크릿키>
POPBILL_IS_TEST=true                   # 운영 시 false
POPBILL_IP_RESTRICT_ON_OFF=false
POPBILL_USE_STATIC_IP=false
POPBILL_USE_LOCAL_TIME_YN=true
POPBILL_LINKHUB_COMM_MODE=CURL         # Windows XAMPP

# 테스트용 (운영에서는 실제 값으로 교체)
POPBILL_TEST_CORP_NUM=1234567890
POPBILL_TEST_USER_ID=testkorea
POPBILL_TEST_RECEIVER_HP=01011112222
POPBILL_TEST_SENDER_NUM=07043042991
POPBILL_TEST_RECEIVER_FAX=07043042991
```

> **주의**: `POPBILL_IS_TEST=false`로 변경하면 실제 요금이 부과됩니다.

---

## 아키텍처

```
app/
├── Providers/
│   └── PopbillServiceProvider.php   ← LINKHUB_COMM_MODE 정의 + 싱글톤 바인딩
├── Services/Popbill/
│   ├── PopbillBaseService.php       ← 공통 설정 추상 클래스
│   ├── TaxinvoiceService.php
│   ├── CashbillService.php
│   ├── KakaoService.php
│   ├── MessageService.php
│   └── FaxService.php
├── Http/Controllers/Popbill/
│   ├── TaxinvoiceController.php
│   ├── CashbillController.php
│   ├── KakaoController.php
│   ├── MessageController.php
│   └── FaxController.php
└── Console/Commands/
    └── PopbillReportCommand.php
config/
└── popbill.php
tests/Feature/Popbill/
├── TaxinvoiceTest.php
├── CashbillTest.php
├── KakaoTest.php
├── MessageTest.php
└── FaxTest.php
storage/app/
├── popbill-reports/     ← php artisan popbill:report 출력
└── popbill-assets/      ← 팩스 업로드 파일 등
```

---

## API 엔드포인트

모든 엔드포인트는 `auth:sanctum` 미들웨어로 보호됩니다 (Bearer 토큰 필요).

### 세금계산서

| Method | URL | 설명 |
|--------|-----|------|
| GET | `/api/popbill/taxinvoice/balance` | 잔여포인트 조회 |
| GET | `/api/popbill/taxinvoice/url` | 팝빌 관리 URL (togo: WRITE/BOX 등) |
| GET | `/api/popbill/taxinvoice/search` | 목록 조회 (start_date, end_date 필수) |
| GET | `/api/popbill/taxinvoice/info` | 상태 확인 (mgt_key_type, mgt_key 필수) |
| GET | `/api/popbill/taxinvoice/popup-url` | 팝업 URL |
| POST | `/api/popbill/taxinvoice/regist-issue` | 즉시발행 |

### 현금영수증

| Method | URL | 설명 |
|--------|-----|------|
| GET | `/api/popbill/cashbill/balance` | 잔여포인트 조회 |
| GET | `/api/popbill/cashbill/url` | 팝빌 관리 URL |
| GET | `/api/popbill/cashbill/search` | 목록 조회 |
| GET | `/api/popbill/cashbill/info` | 상태 확인 (mgt_key 필수) |
| POST | `/api/popbill/cashbill/regist-issue` | 즉시발행 |

### 카카오 알림톡

| Method | URL | 설명 |
|--------|-----|------|
| GET | `/api/popbill/kakao/balance` | 잔여포인트 조회 |
| GET | `/api/popbill/kakao/templates` | 알림톡 템플릿 목록 |
| GET | `/api/popbill/kakao/plus-friends` | 카카오 채널 목록 |
| GET | `/api/popbill/kakao/search` | 전송내역 조회 (state[] 필수) |
| GET | `/api/popbill/kakao/messages` | 전송내역 확인 (receipt_num 필수) |
| GET | `/api/popbill/kakao/sent-list-url` | 전송내역 팝업 URL |
| GET | `/api/popbill/kakao/template-url` | 템플릿관리 팝업 URL |
| POST | `/api/popbill/kakao/send-ats` | 알림톡 전송 |

**알림톡 전송 예시:**
```json
{
  "template_code": "템플릿코드",
  "sender": "07043042991",
  "content": "안녕하세요 #{이름}님",
  "messages": [
    { "rcv": "01012345678", "rcvnm": "홍길동", "msg": "안녕하세요 홍길동님" }
  ]
}
```

### 문자(SMS/LMS/XMS)

| Method | URL | 설명 |
|--------|-----|------|
| GET | `/api/popbill/message/balance` | 잔여포인트 조회 |
| GET | `/api/popbill/message/sender-numbers` | 발신번호 목록 |
| GET | `/api/popbill/message/search` | 전송내역 조회 (message_type 필수) |
| GET | `/api/popbill/message/messages` | 전송내역 확인 |
| GET | `/api/popbill/message/sent-list-url` | 전송내역 팝업 URL |
| POST | `/api/popbill/message/send-sms` | 단문 전송 (90자 이하) |
| POST | `/api/popbill/message/send-lms` | 장문 전송 (2000자 이하) |
| POST | `/api/popbill/message/send-xms` | 자동선택 전송 |
| POST | `/api/popbill/message/cancel-reserve` | 예약전송 취소 |

**SMS 전송 예시:**
```json
{
  "sender": "07043042991",
  "content": "[CE] 처방전 확인 요청드립니다.",
  "messages": [
    { "rcv": "01012345678", "rcvnm": "홍길동" }
  ]
}
```

### 팩스

| Method | URL | 설명 |
|--------|-----|------|
| GET | `/api/popbill/fax/balance` | 잔여포인트 조회 |
| GET | `/api/popbill/fax/sender-numbers` | 발신번호 목록 |
| GET | `/api/popbill/fax/search` | 전송내역 조회 (send_type: S/R 필수) |
| GET | `/api/popbill/fax/messages` | 전송내역 확인 |
| GET | `/api/popbill/fax/sent-list-url` | 전송내역 팝업 URL |
| POST | `/api/popbill/fax/send` | 팩스 전송 (multipart/form-data) |
| POST | `/api/popbill/fax/cancel-reserve` | 예약전송 취소 |

**팩스 전송 예시 (curl):**
```bash
curl -X POST /api/popbill/fax/send \
  -H "Authorization: Bearer <token>" \
  -F "sender=07043042991" \
  -F "receivers[0][rcv]=0212345678" \
  -F "receivers[0][rcvnm]=홍길동" \
  -F "files[]=@/path/to/document.pdf"
```

---

## Artisan 커맨드

```bash
# 당월 리포트 (테이블 형식)
php artisan popbill:report

# 특정 사업자번호, 특정 월, JSON 출력
php artisan popbill:report --corp-num=1234567890 --date=202504 --output=json

# CSV 출력 및 저장
php artisan popbill:report --output=csv
```

결과 파일은 `storage/app/popbill-reports/{corp_num}_{yyyymm}.{ext}` 에 저장됩니다.

---

## 테스트 실행

```bash
# 전체 팝빌 테스트 (실제 팝빌 테스트 서버 호출)
php artisan test --filter=Popbill

# 개별 테스트
php artisan test tests/Feature/Popbill/TaxinvoiceTest.php
```

> 테스트는 팝빌 테스트 서버에 실제 API 호출을 합니다. `.env`의 `POPBILL_IS_TEST=true` 확인 필수.

---

## 운영 전환 체크리스트

- [ ] `.env`: `POPBILL_IS_TEST=false`
- [ ] `.env`: `POPBILL_TEST_CORP_NUM` → 실제 사업자번호
- [ ] `.env`: `POPBILL_TEST_USER_ID` → 실제 팝빌 회원 아이디
- [ ] `.env`: `POPBILL_TEST_SENDER_NUM` → 사전 등록된 발신번호
- [ ] `.env`: `POPBILL_IP_RESTRICT_ON_OFF=true` (운영 서버 IP 등록 후)
- [ ] 팝빌 콘솔에서 연동회원 등록 및 포인트 충전
- [ ] 카카오 알림톡 채널/템플릿 사전 승인
