# UNICORN FRS v0.4 구현 확인 체크리스트 (서버 실행용)

- 기준 문서: `Unicorn_Project_FRS_v0.4_KO_20260827.docx` / `..._EN_20260827.docx`
- 대상 서버: UNICORN CE Admin / API 개발 서버 (PHP 8.3 / Laravel 11, AWS ap-northeast-2)
- 작성일: 2026-08-31 · 작성: Jack Won (LTL) · 용도: 내부 검증 (클라이언트 배포 문서 아님)
- 결과 파일명: `UNICORN_FRS_v0.4_Implementation_Check_RESULT_YYYYMMDD.md` (본 파일을 복사하여 상태·근거·비고 열만 채운다)

---

## 0. 작업 지시 (서버 측 작업자 / 에이전트)

### 0.1 원칙
1. **읽기 전용.** 코드·DB·설정을 변경하지 않는다. `git status`가 작업 전후 동일해야 한다.
2. **추정 금지.** 파일 경로·라우트·테이블·컬럼·설정 키 등 **실제 확인한 근거**가 없으면 상태를 `⚠`(확인 불가)로 두고 비고에 이유를 적는다. 있을 것 같다는 판단으로 `✅`를 주지 않는다.
3. **근거는 재현 가능하게.** `경로:라인`, 라우트 이름, 테이블명.컬럼명, 설정 키, 실행한 명령과 출력 요약 형태로 기록한다.
4. **탐색 힌트는 힌트일 뿐이다.** 본 문서의 `탐색 힌트` 열은 Laravel 관례 기반 추정이다. 실제 프로젝트 구조가 다르면 실제 위치를 근거 열에 기록한다.
5. **P2(M06) 항목은 미구현이 정상이다.** 구현 여부가 아니라 "선행 구현 흔적 여부"와 "P1 데이터 모델에 P2 FK/의존이 섞여 있는지"만 기록한다.
6. **Five/Six(FS-M03-007)는 자격 판정 로직이 구현되어 있으면 안 된다** (Q1/Q3/Q4 미확정). 6장 참조.

### 0.2 상태 코드
| 코드 | 의미 | 판정 기준 |
|---|---|---|
| `✅` | 구현 | 확인 포인트 전부 근거 확인 |
| `🟡` | 부분 | 일부 확인 포인트만 근거 확인 — 비고에 누락 포인트 명시 |
| `❌` | 미구현 | 근거 없음 (관련 라우트/모델/마이그레이션/잡/설정 부재) |
| `⏸` | P2 대상 외 | M06 및 P2 전용 항목 — 미구현 정상 |
| `⛔` | 위반 | "존재하면 안 되는 것"이 발견됨 (2장·6장) — 반드시 근거 첨부 |
| `⚠` | 확인 불가 | 권한/환경 제약으로 확인 못함 — 비고에 이유 |

### 0.3 증거 수집 명령 (실행 후 출력 요약을 1장에 기록)
```bash
# 저장소·버전
git rev-parse --abbrev-ref HEAD; git rev-parse --short HEAD; git log -1 --date=iso --format='%ad'
php -v | head -1; php artisan --version
composer show 2>/dev/null | grep -Ei 'laravel/framework|livewire|linkhub/popbill|barryvdh/laravel-dompdf|aws/aws-sdk-php|league/flysystem-aws-s3'

# 라우트·마이그레이션·스케줄·큐
php artisan route:list --json > /tmp/routes.json; php artisan route:list | wc -l
php artisan migrate:status
php artisan schedule:list
grep -rn "ShouldQueue" app/Jobs app/Listeners 2>/dev/null | wc -l

# 스키마 (테이블 목록 + 주요 테이블 컬럼)  ※ 값(데이터)은 조회하지 않는다
php artisan db:show 2>/dev/null || php artisan tinker --execute='print_r(collect(DB::select("SHOW TABLES"))->map(fn($r)=>array_values((array)$r)[0])->all());'
php artisan db:table <테이블명>   # 필요 테이블마다

# 설정 키 (값은 마스킹하여 기록)
grep -rhoE '^[A-Z0-9_]+=' .env.example 2>/dev/null | sort -u
grep -rn "'textract'\|'toss'\|'popbill'\|'withworks'\|'cognito'\|'entra'\|'saml'\|'nice'" config/ | head -50

# 전역 금지어 (2장)
grep -rniE "openai|gpt-4|gpt4|chatgpt" app config routes resources --include=*.php | grep -v vendor
grep -rni "todoworks" app config routes resources database --include=*.php
grep -rniE "edi" app --include=*.php | grep -iE "nhis|hira|건보|공단" 

# 테스트 존재
find tests -name "*.php" | wc -l; ls tests/Feature tests/Unit 2>/dev/null
```

---

## 1. 환경 정보 (작업자 작성)

| 항목 | 값 |
|---|---|
| 확인 일시 (KST) | |
| 서버 구분 (dev / stg / prod) | |
| Git 브랜치 / 커밋 / 커밋 일시 | |
| PHP / Laravel / Livewire 버전 | |
| 주요 패키지 (popbill, dompdf, aws-sdk) 버전 | |
| 라우트 수 / 마이그레이션 수(ran / pending) | |
| 테이블 수 | |
| 테스트 파일 수 (Feature / Unit) | |
| DB 접속 방식 (private endpoint 여부) | |
| 확인 제약 사항 (접근 불가 항목 등) | |

---

## 2. 전역 점검 — 존재해야 하는 것 / 존재하면 안 되는 것

| ID | 구분 | 점검 내용 | 확인 방법 | 상태 | 근거 | 비고 |
|---|---|---|---|---|---|---|
| G-01 | 금지 | 외부 LLM/OpenAI/GPT 호출 코드·설정 없음 (OCR은 Textract 단일) | 2장 grep; `config/services.php`; composer에 openai 계열 패키지 없음 | | | |
| G-02 | 금지 | `TODOWORKS` 명칭 잔존 없음 (WITHWORKS만 사용) | 코드·마이그레이션·설정·시드 grep | | | |
| G-03 | 금지 | WITHWORKS DB로의 쓰기(write-back) 경로 없음 | WITHWORKS 클라이언트 클래스의 메서드가 조회/출고요청/상태수신만인지; DB connection에 WITHWORKS DB 직결 없음 (`config/database.php`) | | | |
| G-04 | 금지 | e-Fax 수신을 처방전 접수로 자동 전환하는 경로 없음 | 팩스 수신 핸들러가 `prescriptions` 생성/OCR 잡 디스패치를 하지 않음 | | | |
| G-05 | 금지 | NHIS 직접 EDI 연계 코드 없음 | grep 결과 | | | |
| G-06 | 금지 | 주민등록번호 **평문 컬럼** 없음; 유효 체크섬 실데이터가 dev DB에 없음 | 스키마에서 주민번호/생년+뒷자리 계열 컬럼 식별 → 암호화 캐스트/HMAC 컬럼 여부 (5장 SEC-05 참조). 데이터 값 자체는 조회하지 않고 **체크섬 검증 스크립트 존재 여부**만 확인 | | | |
| G-07 | 필수 | 리전 고정: Textract/S3/KMS 모두 `ap-northeast-2` | `config/services.php`, `config/filesystems.php`, `.env.example` | | | |
| G-08 | 필수 | 감사 로그 테이블 존재 + 3년 보존 정책(코드/스케줄/문서 중 하나) | `audit_logs` 계열 테이블; prune/retention 스케줄 | | | |
| G-09 | 필수 | 공통 코드 테이블 존재 (주문/처방/청구/팩스 상태 코드) | 5-M01-008 참조 | | | |
| G-10 | 필수 | 웹훅 엔드포인트(토스/팝빌/WITHWORKS) 서명 검증 + 멱등 처리 | 4장 IF 항목 참조 (요약 판정) | | | |

---

## 3. 모듈별 기능 사양 확인

> 열 설명 — **확인 포인트**: FRS 사양에서 도출한 구현 검증 항목. **탐색 힌트**: Laravel 관례 기반 위치 추정(실제와 다를 수 있음). **상태/근거/비고**: 작업자 작성.

### 3.1 M01 — 접수·플랫폼 기반

| FS ID | 기능 | 확인 포인트 | 탐색 힌트 | 상태 | 근거 | 비고 |
|---|---|---|---|---|---|---|
| FS-M01-001 | 운영자 대행 회원 등록 | ① 회원(환자/보호자) 생성 화면·라우트 ② 동의 항목(약관/개인정보) 및 **동의 채널·증빙 파일** 저장 컬럼 ③ 소비자단 노출 없음(P1) ④ WITHWORKS 동기화 데이터 대비 중복 후보 표시 ⑤ 감사 로그 기록 | `app/Livewire/**/Member*`, `members`/`patients`/`consents` 테이블 | | | |
| FS-M01-002 | 관리자 SSO (Entra ID) | ① SAML 2.0/OIDC 인증 라우트·콜백 ② 로컬 비밀번호 로그인 비활성(또는 SSO 강제) ③ 역할 매핑 ④ WITHWORKS로 SSO 확장 없음 ⑤ 로그인 이벤트 로깅 | `config/saml2*` 또는 socialite/azure 설정, `routes/web.php` auth | | | |
| FS-M01-003 | RBAC | ① 역할 4종(시스템관리자/Consumer/Sales/환자·보호자) 정의 ② 기능별 조회·등록·수정·승인·발송·다운로드 권한 분리(Gate/Policy/Permission) ③ 권한 변경 감사 로그 ④ 권한 매트릭스 내보내기 | `app/Policies`, `roles`/`permissions` 테이블(spatie 등), `AuthServiceProvider` | | | |
| FS-M01-004 | 환자/보호자 프로필 | ① 고객·환자·보호자·배송지·연락처·보험 기본정보 CRUD ② 민감 필드 마스킹 + 해제 권한 ③ 필드별 변경 이력 ④ WITHWORKS 원천 표시(동기화 출처 컬럼) | `patients`, `guardians`, `addresses`, `*_histories` | | | |
| FS-M01-005 | SR App 처방전 업로드 | ① 모바일 업로드 API(인증 필수) ② 파일 제약 JPG/PNG/PDF ≤ 20MB 검증 ③ S3 암호화 저장 ④ 메타(업로더·기기·시각) ⑤ 접수 큐 등록 + OCR 잡 디스패치 ⑥ 채널 코드 = SR | `routes/api.php`, `PrescriptionUpload*`, `prescriptions.channel` | | | |
| FS-M01-006 | CE Admin 웹 업로드 | ① 웹 업로드 화면 ② FS-M01-005와 동일 검증·저장·OCR ③ 채널 코드 = CE ④ 팩스→접수 자동 전환 없음(G-04) | Livewire 업로드 컴포넌트 | | | |
| FS-M01-007 | 문서 파일 관리 | ① 파일 메타 테이블(업무 객체 FK: 환자/주문/청구/팩스) ② 원본/썸네일/OCR 결과 분리 ③ pre-signed URL 만료형 다운로드 ④ 다운로드 감사 로그 ⑤ 악성 파일 검사 훅 ⑥ S3 Public Access Block(인프라 — 확인 가능 시) | `documents`/`files` 테이블, `Storage::temporaryUrl` | | | |
| FS-M01-008 | 공통 코드 | ① 코드 테이블(주문/처방/청구/팩스 상태, 제품 분류, 기관 유형) ② 상태 전이 규칙 정의 위치(코드/DB) ③ 사용 중 코드 삭제 방지 ④ 변경 이력 | `codes`/`code_groups`, `*StateMachine*`/enum | | | |
| FS-M01-009 | 감사 로그 | ① 조회·수정·다운로드·전송 이벤트 기록(사용자/일시/IP/기능/대상/결과) ② 로그 조회 권한 제한 ③ 로그 접근 자체 로깅 ④ 무결성 통제(append-only/해시 등) ⑤ 3년 보존 | `audit_logs`, 미들웨어/옵저버 | | | |

### 3.2 M02 — OCR·검증 (Amazon Textract)

| FS ID | 기능 | 확인 포인트 | 탐색 힌트 | 상태 | 근거 | 비고 |
|---|---|---|---|---|---|---|
| FS-M02-001 | Textract 추출 | ① 비동기 잡(큐) ② AWS SDK Textract 클라이언트, 리전 ap-northeast-2 ③ 추출 필드: 환자명·상병코드·처방일·의료기관·의사명·제품/수량 ④ 필드별 신뢰도 저장 ⑤ 요청 ID 저장 ⑥ 상태 대기/처리중/완료/실패 | `app/Jobs/*Ocr*`/`*Textract*`, `ocr_results` 테이블 | | | |
| FS-M02-002 | 검수·보정 | ① 이미지-필드 나란히 검수 화면 ② 보정값 버전 관리(원본 OCR 값 보존) ③ 고객/주문/급여기준 대비 일치·불일치 표시 ④ 불일치 건수 큐/알림 표출 | `PrescriptionReview*` 컴포넌트, `ocr_result_revisions` | | | |
| FS-M02-003 | 승인/반려/보완 | ① 3분기 상태 전이 ② 반려·보완 사유 필수 ③ 고객 알림 트리거(FS-M04-001) ④ 승인 처방전만 주문에 선택 가능 ⑤ 유효기간 참조 컬럼 | `prescriptions.status`, `approval_reason` | | | |
| FS-M02-004 | OCR 상태·재처리 | ① 상태 표출 ② 실패/타임아웃 재처리 액션 ③ 수기 입력 폴백 + 플래그 ④ 재처리 시도 기록 | `retry_count`, `manual_entry` 플래그 | | | |

### 3.3 M03 — 주문·결제 확인·WITHWORKS 물류

| FS ID | 기능 | 확인 포인트 | 탐색 힌트 | 상태 | 근거 | 비고 |
|---|---|---|---|---|---|---|
| FS-M03-001 | 처방 기반 주문(운영자) | ① 승인 처방전 선택/첨부 ② 제품·수량·급여가/본인부담·배송지 ③ 처방 필수 제품은 승인 처방전 없이 생성 차단 ④ 주문-처방전 연결 컬럼 ⑤ 결제 확인 흐름 진입 | `orders`, `order_items`, `orders.prescription_id` | | | |
| FS-M03-002 | 비처방 주문 | ① 주문 유형 플래그 ② 처방 검증·청구 판정에서 제외 ③ 목록/대시보드 분리 | `orders.type` | | | |
| FS-M03-003 | 주문 조회·상태 관리 | ① 검색 조건 8종(주문번호·고객명·연락처·주문일·결제·처방·출고·배송 상태) ② 페이징 20–100 ③ 상태 전이 규칙 준수 ④ 상태 이력 | `order_status_histories` | | | |
| FS-M03-004 | 결제 확인 후 처리 | ① 토스 입금통보 웹훅 엔드포인트 ② **서명 검증** ③ **멱등 처리**(중복 통보) ④ IBK 가상계좌-주문 매칭 ⑤ 미매칭 수동 매칭 큐 ⑥ 확정 방식(자동/수동, 행위자) 기록 ⑦ 확정 시 FS-M03-005 트리거 | `routes/api.php` webhook, `payments`, `virtual_accounts`, `unmatched_deposits` | | | |
| FS-M03-005 | WITHWORKS 출고 요청 | ① REST 클라이언트 ② 출고 요청 ID·접수 상태 저장 ③ **멱등키** ④ 자동 재시도 + 수동 재처리 ⑤ 실패 운영자 알림 ⑥ 페이로드 개인정보 최소화 | `WithworksClient`, `shipment_requests` | | | |
| FS-M03-006 | 재고·배송 상태 | ① 재고 API + 캐시 폴백(최신성 표시) ② 상태 수신(웹훅 또는 ≥1시간 주기 스케줄) ③ 운송장 번호 접근 통제 ④ 불일치 예외 큐 | `schedule:list`, `inventory_cache`, `shipments` | | | |
| FS-M03-007 | Five/Six 프로그램 | **6장 참조.** ① 프로그램 주문 플래그/구분 ② 처방전+상담 원천 참조(도뇨횟수 양 경로) ③ **자격 판정 서비스가 구현되어 있지 않거나 config gate로 비활성(예외 throw)** ④ 샘플주문 자동 등록·발송 감사 로그 설계 여부 | `FiveSix*`, `config/fivesix.php` | | | |

### 3.4 M04 — 알림·지원·운영 (팝빌)

| FS ID | 기능 | 확인 포인트 | 탐색 힌트 | 상태 | 근거 | 비고 |
|---|---|---|---|---|---|---|
| FS-M04-001 | 이벤트 알림 | ① 이벤트 8종(회원승인·보완요청·주문접수·결제완료·출고·배송·팩스결과·문의답변) 트리거 ② 팝빌 SMS/카카오/이메일 채널 ③ 채널별 수신동의 검사(발송 시점) ④ 발송 이력·결과 저장 ⑤ 실패 재시도 | `app/Notifications`, `notification_logs`, `linkhub/popbill` | | | |
| FS-M04-002 | 템플릿·수신동의 | ① 채널×이벤트 개별 on/off ② 사전 승인 템플릿만 사용(자유 생성 없음) ③ 알림톡 템플릿 코드 관리 ④ 동의 채널별 변경 이력 ⑤ **동의 수집이 NICE 본인인증 회원 기준**인지(P2 전제 — P1은 설계 확인) | `notification_templates`, `consents` | | | |
| FS-M04-003 | 문의 응대(유선+상담 이력) | ① **거래처(고객) 관리 화면 내 상담 기능** ② 상담 레코드: 담당자·상태·이력 ③ 상담 상태 라이프사이클 ④ 고객향 1:1 문의 UI 없음(P2) | `consultations` 테이블, Customer 상세 컴포넌트 | | | |
| FS-M04-004 | 대시보드 | ① 현황 7종(주문·결제·처방승인·요양비 등록/청구·팩스·배송·문의) ② 작업 큐 3종(검수 필요/주문 연계 대기/청구 송신 대기) ③ 드릴다운 ④ 타일에 민감정보 없음 | `Dashboard*` | | | |
| FS-M04-005 | 데이터 다운로드 | ① 대상 6종(주문·고객·청구·영수증·팩스·기관) ② 비동기 대량 Excel ③ 다운로드 감사 로그(행위자·조건·건수) ④ 권한 게이트 ⑤ 민감 필드 마스킹 | `app/Exports`, `export_jobs` | | | |
| FS-M04-006 | 연계 모니터링 | ① 팝빌/토스/Textract/WITHWORKS 성공·실패율·적체·최근 오류 ② 실패 건 재처리 진입점 ③ 운영자 알림 ④ 오류 레코드 PII 마스킹 | `integration_logs` | | | |

### 3.5 M05 — 요양비·청구·전자팩스·정산

| FS ID | 기능 | 확인 포인트 | 탐색 힌트 | 상태 | 근거 | 비고 |
|---|---|---|---|---|---|---|
| FS-M05-001 | 등록 대상자 조회 | ① 신규/재등록 대상 목록 ② 검색 6종(환자명·생년월일·등록상태·만료예정일·지사/지자체·담당자) ③ 조치 필요일 임박 순 정렬 ④ 마스킹 | `ReimbursementTarget*` | | | |
| FS-M05-002 | 등록/재등록 처리 | ① 서류 체크(신청서·위임장·처방·고객정보) ② 템플릿 병합 → PDF(dompdf) → S3 ③ 전자서명 SMS 링크 흐름 + NICE 대체 인증 폴백 설계 ④ 상태 라이프사이클 6단계 ⑤ 필수 서류 누락 시 발송 비활성 | `registrations`, `document_templates`, `esign_requests` | | | |
| FS-M05-003 | 청구 관리 | ① 청구 상태·금액·일자·접수기관·결과 ② 팩스 전송 라이프사이클 연동 ③ 수신 결과 통지 연결 ④ 청구-주문/영수증 매핑 검증 | `claims` | | | |
| FS-M05-004 | 영수증 정보 입력 | ① 항목 8종(주문번호·고객명·결제금액·본인부담·청구금액·발행일·증빙·상태) ② 팝빌 현금영수증(지출증빙/소득공제)·세금계산서 발행 호출 ③ 발행 결과(문서번호·상태) 저장 ④ 금액 합계 검증 ⑤ 감사 로그 | `receipts`, `tax_documents`, Popbill Cashbill/Taxinvoice | | | |
| FS-M05-005 | 전자팩스 발송 | ① 팝빌 e-Fax API ② 수신처 = 지사/지자체 기준정보 + 추천 수신처 ③ 중복 발송 점검 ④ 대량 발송 건별 비동기 ⑤ 접수번호·요청ID·상태 저장 ⑥ 우편 전용 표시 ⑦ 감사 로그 | `fax_transmissions` | | | |
| FS-M05-006 | 팩스 결과 관리 | ① 콜백 또는 폴링 ② 서명 검증·멱등 ③ 실패 사유 저장 + 재발송(원 전송 연결) ④ 성공 건 재발송 경고+사유 | 웹훅 라우트, `fax_transmissions.parent_id` | | | |
| FS-M05-007 | 수신 문서 처리 | ① 수신 파일 S3 암호화 저장 + 메타 ② 분류·케이스 연결 ③ 미분류 큐 ④ 담당자 알림 ⑤ **처방전 접수 전환 없음**(G-04) | `fax_inbound` | | | |
| FS-M05-008 | 공단 지사 기준정보 | ① CRUD(지사명·관할·팩스·전화·주소·사용여부) ② 팩스번호 형식 검증 ③ 비활성 지사 선택 불가 ④ 변경 이력 ⑤ 초기 적재 시더/커맨드 | `nhis_branches` | | | |
| FS-M05-009 | 지자체 기준정보 | ① CRUD(기관명·지역·담당부서·연락처·팩스·주소·사용여부) ② 우편 전용 표시 ③ FS-M05-008 동일 통제 | `local_governments` | | | |

### 3.6 M06 — 폐쇄몰 (P2) — 선행 구현 흔적 및 P1 오염 점검만

| FS ID | 기능 | 확인 포인트 (P2 관점) | 상태 | 근거 | 비고 |
|---|---|---|---|---|---|
| FS-M06-001~002 | 셀프 가입·Cognito 로그인 | Cognito/NICE 설정·코드가 있다면 비활성 상태인지; P1 인증 흐름과 충돌 없는지 | | | |
| FS-M06-003~005 | 카탈로그·장바구니·재구매 | CE shop 계열 테이블이 존재하면 **P1 테이블에 대한 FK 의존**·PII 컬럼(제품 조회 로그 등) 여부 | | | |
| FS-M06-006~007 | 온라인 결제·환불 | 토스 카드결제 코드가 있다면 P1 가상계좌 흐름과 분리되어 있는지 | | | |
| FS-M06-008 | 배송 추적 | 고객향 노출 없음 확인 | | | |
| FS-M06-009~010 | 급여 안내(공단 링크) | 링크 목록 관리 테이블/화면 유무 (P1 미구현 정상) | | | |
| FS-M06-011~012 | 1:1 문의·채팅 | 고객향 UI 없음; 상담 기능(FS-M04-003)과 데이터 모델 충돌 없음 | | | |
| FS-M06-013~014 | 환자 업로드·주문 | 고객향 업로드/주문 라우트 없음(비인증 노출 금지) | | | |

---

## 4. 인터페이스 확인 (IF-*)

| IF ID | 대상 | 확인 포인트 | 상태 | 근거 | 비고 |
|---|---|---|---|---|---|
| IF-WW-001 | WITHWORKS 출고 요청 | 클라이언트 클래스·엔드포인트 설정, TLS, 멱등키, 재시도 정책, 응답(요청 ID) 저장 | | | |
| IF-WW-002 | WITHWORKS 재고 | 읽기 전용 호출, 캐시 TTL, 폴백·최신성 표시 | | | |
| IF-WW-003 | WITHWORKS/택배 상태 | 웹훅 수신 라우트 **또는** ≥1시간 스케줄, 검증, 상태 매핑 | | | |
| IF-WW-004 | WITHWORKS 환자 동기화(Flow A) | 초기 시드 커맨드, 지속 동기화 잡, 컷오버 델타 로직, **write-back 없음**, 정합성 점검(건수·필수값·중복·참조키), 적재 이력 | | | |
| IF-FAX-001 | 팝빌 팩스 발신 | `linkhub/popbill` FAX 서비스 사용, 자격증명 암호화(.env/Secrets), 접수번호 저장 | | | |
| IF-FAX-002 | 팝빌 팩스 결과 | 콜백 라우트 또는 폴링 스케줄, 멱등 | | | |
| IF-FAX-003 | 팝빌 팩스 수신 | 수신 처리 핸들러, S3 저장, 접수 전환 없음 | | | |
| IF-PG-001 | 토스 가상계좌·입금통보 | 가상계좌 발급 API 호출, 웹훅 라우트, **서명 검증**, 멱등, 금액 일치 검증 | | | |
| IF-PG-002~004 | 토스 카드결제·취소·조회 (P2) | 존재 시 비활성/분리 여부만 | | | |
| IF-OCR-001 | Amazon Textract | SDK 클라이언트 리전, 비동기(StartDocumentAnalysis 등) 사용, 결과 저장 | | | |
| IF-NTF-001 | 팝빌 SMS/카카오, 이메일 | 메시지 서비스 클래스, 템플릿 코드, 동의 검사, 발송 로그 | | | |
| IF-STD-001 | 지사/지자체 기준정보 | 시더/임포트 커맨드, 검증 | | | |
| IF-AUTH-001 | Cognito (P2) | 설정 존재 시 비활성 여부 | | | |
| IF-AUTH-002 | Entra ID / IAM Identity Center | SAML/OIDC 설정, 역할 클레임 매핑, 로그 | | | |
| IF-NICE-001 | NICE (P2) | 설정 존재 시 비활성 여부 | | | |
| IF-NTS-001 | 팝빌 세금계산서/현금영수증 | Cashbill/Taxinvoice 서비스 호출 코드, 발행 결과 저장 컬럼, 감사 로그 | | | |

---

## 5. NFR 확인

| ID | 항목 | 확인 포인트 | 상태 | 근거 | 비고 |
|---|---|---|---|---|---|
| NFR-PER-005 | 파일 제약 | 업로드 검증 규칙 `mimes:jpg,jpeg,png,pdf`, `max:20480`(KB) 상당 | | | |
| NFR-PER-006 | OCR 30초 | 잡 타임아웃/상태 표출 존재 (성능값 자체는 측정 대상 아님) | | | |
| NFR-PER-007 | 페이징·비동기 다운로드 | 페이지 크기 20–100 옵션, 조건 없는 대량 조회 제한, Export 잡 | | | |
| NFR-PER-008 | 배치 재시도 | 잡 `tries`/`backoff`, 실패 알림, 실행 이력 | | | |
| NFR-AVL-003 | 백업·복구 (인프라) | Aurora Multi-AZ·PITR 활성 — 콘솔/CLI 접근 가능 시만; 불가 시 `⚠` | | | |
| SEC-01 | Entra ID SSO / MFA | IF-AUTH-002 + 관리자 MFA 강제 설정 | | | |
| SEC-02 | KMS 암호화 | S3 버킷 SSE-KMS 설정(`filesystems.php` 또는 인프라), Aurora 암호화 | | | |
| SEC-03 | S3 Public Access Block / pre-signed | 인프라 확인 가능 시; 코드상 `temporaryUrl` 사용 | | | |
| SEC-04 | 감사 로그 3년 | G-08 | | | |
| **SEC-05** | **주민등록번호 보호(B안)** | ① 컬럼 암호화(Eloquent `encrypted` 캐스트 또는 KMS 필드 암호화) ② **HMAC 검색 해시 컬럼** ③ 화면 마스킹 ④ **복호화 시 감사 로그** ⑤ 5년 보존 정책 ⑥ dev 데이터 무효 체크섬 치환 스크립트 존재 | | | |
| SEC-06 | 마스킹 | 민감 필드 마스킹 헬퍼/접근자, 해제 권한 | | | |
| SEC-07 | PG 보안 | 카드정보 저장 컬럼 없음(P1은 가상계좌만) | | | |
| SEC-08 | 네트워크 분리 (인프라) | Aurora publicly_accessible=false, 운영/개발 VPC 분리 — 확인 가능 시 | | | |
| OPS-01 | 모니터링 | CloudWatch 로그 드라이버/알람 설정 흔적 | | | |
| OPS-02 | 환경 분리 | `.env` 환경별 분리, prod 실데이터 dev 반입 금지 규칙 | | | |

---

## 6. 미결 항목 — 구현 보류 준수 확인

| ID | 항목 | 기대 상태 | 확인 포인트 | 상태 | 근거 | 비고 |
|---|---|---|---|---|---|---|
| OI-01 | Five/Six 수량 산식 (Q1) | **미구현 또는 config 미설정 시 예외** | 산식이 코드에 하드코딩되어 있으면 `⛔` | | | |
| OI-02 | 110일 기산점 (Q3) | 동일 | `resolveBaselineDate` 류가 특정 기준일을 확정 구현하고 있으면 `⛔` | | | |
| OI-03 | 발송 트리거 상태 매핑 (Q4) | 동일 | 판매상태2 ↔ UNICORN 상태 매핑이 확정 구현되어 있으면 `⛔` | | | |
| OI-04 | Q9 채널 존치 | 확정 전 | 대리점/의료기샵 채널 분기 로직이 구현되어 있으면 비고에 기록 | | | |
| OI-05 | FR-022 영문명 | 문서 사안 | 코드 영향 없음 — 화면 라벨이 "Cheong Entrance"류 오역을 사용하지 않는지만 확인 | | | |
| OI-06 | IF-NTS URS 명시 요건 | 문서 사안 | 코드 영향 없음 | | | |

---

## 7. 결과 요약 (작업자 작성)

### 7.1 집계
| 구분 | 총 항목 | ✅ | 🟡 | ❌ | ⏸ | ⛔ | ⚠ |
|---|---|---|---|---|---|---|---|
| 2장 전역 (G) | 10 | | | | | | |
| 3.1 M01 | 9 | | | | | | |
| 3.2 M02 | 4 | | | | | | |
| 3.3 M03 | 7 | | | | | | |
| 3.4 M04 | 6 | | | | | | |
| 3.5 M05 | 9 | | | | | | |
| 3.6 M06 (P2) | 7 | | | | | | |
| 4장 IF | 16 | | | | | | |
| 5장 NFR | 16 | | | | | | |
| 6장 OI | 6 | | | | | | |

### 7.2 `⛔` 위반 및 `❌` 미구현 목록 (ID · 한 줄 요약 · 근거)
-

### 7.3 `🟡` 부분 구현 — 누락 포인트 목록
-

### 7.4 `⚠` 확인 불가 — 사유
-

### 7.5 FRS와 다르게 구현된 사항 (사양 이탈 — FRS 개정 또는 코드 수정 판단 필요)
| 관련 ID | FRS 사양 | 실제 구현 | 근거 |
|---|---|---|---|
| | | | |

### 7.6 기계 판독용 요약 (반드시 작성 — 리뷰 시 자동 대조에 사용)
```json
{
  "frs_version": "0.4",
  "checked_at": "YYYY-MM-DDTHH:MM:SS+09:00",
  "env": {"server": "", "branch": "", "commit": ""},
  "results": [
    {"id": "G-01", "status": "✅|🟡|❌|⏸|⛔|⚠", "evidence": "", "note": ""},
    {"id": "FS-M01-001", "status": "", "evidence": "", "note": ""}
  ],
  "deviations": [
    {"id": "", "frs": "", "actual": "", "evidence": ""}
  ]
}
```
> `results`에는 2·3·4·5·6장의 **모든 ID**를 포함한다 (G-01~10, FS-M01-001~FS-M06-014 그룹 7행, IF-* 16행, NFR 16행, OI-01~06).
