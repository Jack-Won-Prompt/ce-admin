# UNICORN FRS v0.4 구현 확인 결과 — 2026-09-06

- 기준 문서: `Unicorn_Project_FRS_v0.4_KO_20260827.docx`
- 원본 체크리스트: `frs/UNICORN_FRS_v0.4_Implementation_Check.md`
- 확인: 2026-09-06 · **읽기 전용** (코드·DB·설정 변경 없음)

> **읽는 법** — 근거가 없으면 `⚠`(확인 불가)로 두었습니다. 있을 것 같다는 판단으로
> `✅`를 주지 않았습니다. AWS 콘솔에 손이 닿지 않아 인프라 항목에 `⚠`가 있습니다.

---

## 1. 환경 정보

| 항목 | 값 |
|---|---|
| 확인 일시 (KST) | 2026-09-06 18:10 |
| 서버 구분 | **prod** (`APP_ENV=production`) — 별도 dev/stg 없음 |
| Git 브랜치 / 커밋 / 커밋 일시 | `main` / `76b8ee6` / 2026-09-06 18:03:55 +0900 |
| PHP / Laravel | 서버 **8.2.23** / **Laravel 12.56.0** (개발기 PHP 8.5.3) |
| 주요 패키지 | `linkhub/popbill ^1.14` · `barryvdh/laravel-dompdf ^3.1` · `aws/aws-sdk-php ^3.389` |
| Livewire | **없음** |
| 라우트 수 | 377 |
| 마이그레이션 | ran 113 / pending 0 |
| 테이블 수 | 67 |
| 테스트 파일 수 | 15 (`tests/Feature`, `tests/Unit`) |
| DB 접속 | `DB_HOST=3.34.53.36` — **공인 IP 직결** (private endpoint 아님) |
| 파일 저장 | `FILESYSTEM_DISK=local` — **S3 미사용**, `AWS_BUCKET` 빈값 |
| 큐 | `QUEUE_CONNECTION=file` · `ShouldQueue` **0건** · `app/Jobs` **없음** |
| AWS 리전 | `AWS_DEFAULT_REGION=us-east-1` |
| 확인 제약 | AWS 콘솔·인프라(KMS·S3 정책·Aurora·CloudWatch) 접근 없음 |

> **FRS 전제와 다른 점 셋** — ① PHP 8.3/Laravel 11 이 아니라 **8.2/Laravel 12**
> ② 개발 서버가 아니라 **운영 서버 한 대**(dev/stg 분리 없음)
> ③ Livewire 를 쓰지 않는다 (Blade + 자체 그리드 `wwGrid`)

---

## 2. 전역 점검

| ID | 상태 | 근거 | 비고 |
|---|---|---|---|
| G-01 | ✅ | `grep -rniE "openai|gpt-4|gpt4|chatgpt" app config routes resources` → **0건**. composer 에 openai 계열 없음 | OCR 은 `app/Services/OcrService.php:25` 주석대로 Textract 단일 |
| G-02 | **⛔** | `app/Models/WithworksSetting.php:80` `env('TODOWORKS_API_TOKEN')` 폴백 · `resources/views/prescriptions/order.blade.php:25,11141` 화면 문구 「Todoworks」 | 명칭 잔존 **3곳** |
| G-03 | ✅ | `config/database.php` 커넥션 = sqlite/mysql/mariadb/pgsql/sqlsrv 뿐. 모델에 `protected $connection` **0건**, `DB::connection(` **0건** | WITHWORKS 는 REST 만 (`app/Services/Withworks*.php`) |
| G-04 | ✅ | 팩스 **수신** 경로 자체가 없다 — `routes/` 에 fax inbound/callback 라우트 0건 | 발신·조회만 (`Popbill\FaxController`) |
| G-05 | ✅ | `grep -rniE "edi" app` × `nhis|hira|건보|공단` → 일치한 것은 `editor`·`RedirectResponse`·`medicare.nhis.or.kr` 뿐 | 공단 **포털 링크**만 (`NhisAssistController:36`) |
| G-06 | ✅ | `patients.resident_no_enc` · `resident_no_hash` · `resident_no_masked` — **평문 컬럼 없음**. `app/Support/ResidentNo.php:173 checksumValid()` 존재 | 5장 SEC-05 참조 |
| G-07 | **❌** | 서버 `.env` `AWS_DEFAULT_REGION=us-east-1`, `AWS_BUCKET=`(빈값). `config/services.php:38` 기본값도 `us-east-1` | **ap-northeast-2 아님.** 다만 S3·Textract 를 실제로 쓰지 않아 리전이 무의미한 상태 |
| G-08 | 🟡 | `activity_log`(spatie) + `user_activity_logs` 존재 | **3년 보존 정책 없음** — `schedule:list` 에 prune/retention 없음 |
| G-09 | ✅ | `common_codes` (`group, kind, code, label, sort_order, is_active, is_system`) | `is_system` 으로 시스템 코드 보호 |
| G-10 | 🟡 | 토스 `TossWebhookController:28-35` 서명 검증 · 위드웍스 `WithworksWebhookController:77-86` `hash_equals` 공유비밀 · 멱등 `withworks_events.event_id` 유일 | **팝빌 웹훅 없음**(폴링 `fax:sync-pending`). 토스 검증은 `webhook_secret` 이 **설정된 때만** 동작 |

---

## 3. 모듈별

### 3.1 M01 — 접수·플랫폼 기반

| FS ID | 상태 | 근거 | 비고 |
|---|---|---|---|
| FS-M01-001 | 🟡 | `patients` CRUD (`PatientController`) · `privacy_consents`·`prescription_consents` · `activity_log` | ④ **중복 후보 표시 없음** — 이름 검색은 있으나 WITHWORKS 동기화 대비 중복 경고 근거 없음 |
| FS-M01-002 | **❌** | `saml|oidc|entra|azure|socialite|cognito` grep → config·composer **0건** | **SSO 없음.** 자체 로그인 + OTP(`login_otp_tokens`) |
| FS-M01-003 | 🟡 | `permission_groups` + `permission_group_pages`(`can_view/create/update/delete/send/approve`) · `app/Support/permissions.php:14 perm()` | ① **역할 4종 고정 정의 없음**(그룹 자유 생성) ④ 권한 매트릭스 내보내기 없음 |
| FS-M01-004 | ✅ | `patients`·`patient_addresses` CRUD · 마스킹 `resident_no_masked` + 해제 `ResidentNo::decrypt($payload,$reason,$target)` · `activity_log` | ④ 동기화 출처는 `withworks_*` 계열로 주문에만 |
| FS-M01-005 | 🟡 | `POST api/prescriptions/upload` → `Api\PrescriptionApiController@upload` · `:36 mimes:jpg,jpeg,png,pdf,heic` `:37 max:10240` | ② **10MB**(FRS 20MB) ③ **S3 아님**(로컬) ⑤ OCR **잡 없음**(동기) |
| FS-M01-006 | 🟡 | `GET prescriptions/upload` · `PrescriptionController:847 max:50240` | 웹은 **50MB** — API(10MB)와 **제한이 다르다** |
| FS-M01-007 | 🟡 | `prescription_documents`·`prescription_attachments` (FK: prescription) · `documents/{document}/download` | ③ **pre-signed URL 없음**(`temporaryUrl` 0건) ⑤ 악성파일 검사 없음 ⑥ S3 미사용 |
| FS-M01-008 | 🟡 | `common_codes` · 상태 전이는 코드에 (`OrderReturn::FLOWS`, `App\Support\BillingStrategy`) | ③ 사용 중 코드 삭제 방지 근거 없음 ④ 변경 이력 없음 |
| FS-M01-009 | 🟡 | `activity_log`(대상·행위자·속성) · `user_activity_logs` | ③ **로그 접근 자체 로깅 없음** ④ 무결성 통제 없음 ⑤ 3년 보존 없음 |

### 3.2 M02 — OCR·검증

| FS ID | 상태 | 근거 | 비고 |
|---|---|---|---|
| FS-M02-001 | 🟡 | `app/Services/OcrService.php` Textract 단일 · `prescriptions.ocr_raw_data`·`ocr_confidence` | ① **비동기 잡 아님**(동기) ③ **필드별 신뢰도 아님** — 문서 단위 한 값 ⑤ 요청 ID 저장 없음 ⑥ 상태 4단계 없음. 서버에 Textract 자격증명 **미설정** |
| FS-M02-002 | ✅ | 주문 등록 「상세 목록」이 이미지-필드 나란히 (`resources/views/prescriptions/order.blade.php`) · `prescription_memos` | ② 보정 전 원본은 `ocr_raw_data` 에 보존 |
| FS-M02-003 | 🟡 | `prescriptions.status` · 검수 요청/승인 (`requestReviewRx()`·`approveRx()` → `reviewed_by`·`reviewed_at`·`review_memo`) | ① **3분기 아님** — 승인/요청 2단계. 반려·보완 사유 필수 아님 |
| FS-M02-004 | ❌ | `retry_count`·`manual_entry` 컬럼 **없음** | 수기 입력은 가능하나 **플래그가 없어 구분 불가** |

### 3.3 M03 — 주문·결제 확인·WITHWORKS

| FS ID | 상태 | 근거 | 비고 |
|---|---|---|---|
| FS-M03-001 | ✅ | `orders.prescription_id` · `order_items` · `App\Support\OrderSync::ensure()` · 검수 승인 전 저장 차단(「검수 완료 후 구매 진행 및 저장 가능합니다」) · 동의 2종 미완이면 차단 | |
| FS-M03-002 | ✅ | `BillingStrategy::TYPE_NONRX = '20'` — 처방외는 자격을 보지 않는다 · `orders.so_type` | 목록 분리는 필터로 |
| FS-M03-003 | 🟡 | `orders` 목록 검색 다수 · `order_return_logs` | **주문 상태 이력 테이블 없음**(`order_status_histories` 부재) — 반품만 이력이 있다 |
| FS-M03-004 | ✅ | `POST toss/webhook` → `TossWebhookController@handle` · `:28` 서명 헤더 · `:33-35` 검증 · `toss_payments.order_id` 유일 · `bank_transactions`·`bank_transaction_splits` 수동 매칭 | ⑥ 확정 방식·행위자 `orders.settle_status_by` |
| FS-M03-005 | 🟡 | `app/Services/WithworksSync.php`·`WithworksConfirm.php` · `orders.withworks_so_no` | ③ **멱등키 없음** ④ 자동 재시도 없음(수동 「다시 보내기」만) |
| FS-M03-006 | 🟡 | 웹훅 `api/webhook/withworks` + `schedule: withworks:sync */10` · `orders.withworks_ship_status` | ① **재고 API·캐시 없음** ④ 불일치 예외 큐 없음 |
| FS-M03-007 | ✅ | `prescriptions.five_program`·`five_110days` 는 **입력·표시 칸일 뿐** — 자격 판정 서비스·산식 **없음** | 6장 기대 상태 충족 |

### 3.4 M04 — 알림·지원·운영

| FS ID | 상태 | 근거 | 비고 |
|---|---|---|---|
| FS-M04-001 | 🟡 | `app/Services/MessageSender.php`·`KakaoService.php`·`Popbill/` · `message_histories`(channel·template_code·success_count·fail_count·error) | ① 8종 전부 확인 못함 ③ **발송 시점 수신동의 검사 근거 없음** ⑤ 재시도 없음 |
| FS-M04-002 | 🟡 | `message_templates` · `template_code` | ① 채널×이벤트 on/off 없음 ④ 동의 변경 이력 없음 |
| FS-M04-003 | ✅ | `service_requests`·`inquiries`·`inquiry_messages` · 처방 화면 「상담하기」(`openCounselWindow()`) · `prescriptions.counsel_status` | ④ 고객향 1:1 UI 없음 — P1 정상 |
| FS-M04-004 | 🟡 | `GET dashboard` → `DashboardController@index` | 현황 7종·작업 큐 3종 대조 못함 — 화면 실물 확인 필요 |
| FS-M04-005 | 🟡 | 개별 PDF · `privacy-consents/export` · 화면 그리드 `downloadExcel()` | ② **비동기 대량 Excel 없음**(`app/Exports` 부재) ③ 다운로드 감사 로그 부분 |
| FS-M04-006 | ❌ | `integration_logs` 테이블 **없음** · 연계 모니터링 화면 근거 없음 | `withworks_events` 는 수신 기록일 뿐 |

### 3.5 M05 — 요양비·청구·전자팩스·정산

| FS ID | 상태 | 근거 | 비고 |
|---|---|---|---|
| FS-M05-001 | 🟡 | `patients.nhis_renew_due`·`nhis_renew_told_at` · `app/Services/NhisRenewNotice.php` · `schedule: nhis:renew-notice 0 9 * * *` | **대상자 목록 화면이 없다** — 알림만 있다 |
| FS-M05-002 | ✅ | `prescription_consents`(token·signature_data·nice_verified_at·status·expires_at) · `GET consent/{token}` · dompdf 위임장 · `delegation_settings` | ③ NICE 폴백 `nice_settings` + `NICE_SIMULATE` |
| FS-M05-003 | ✅ | `orders.nhis_claim_status` · `local_claim_dispatches` · `app/Services/ClaimReadiness.php` · `schedule: claim:refresh` | |
| FS-M05-004 | ✅ | `cashbill_records`·`popbill_taxinvoices` · `orders.cash_receipt_status`·`tax_invoice_status`·`*_type` · `Popbill/` | ⑤ `activity_log` |
| FS-M05-005 | ✅ | `POST api/popbill/fax/send` · `fax_histories.receipt_num` · 수신처 = `billing_offices` · 실전송 확인(접수번호 `026090616270300005`) | ③ 중복 발송 점검 근거 없음 |
| FS-M05-006 | 🟡 | **폴링** `schedule: fax:sync-pending */5` · `fax_histories.popbill_result` | ① 콜백 없음 ③ 재발송-원전송 연결(`parent_id`) 없음 |
| FS-M05-007 | ⏸ | 팩스 **수신 기능 자체가 없다** | G-04 와 같은 근거 — 접수 전환 없음 충족 |
| FS-M05-008 | 🟡 | `billing_offices`(`kind`=nhis/local, office_name·dept·tel·fax·address·is_active) + `billing_office_areas` | ② 팩스번호 형식 검증 근거 없음 ④ 변경 이력 없음 ⑤ 시더 없음 |
| FS-M05-009 | 🟡 | 위와 **같은 테이블**(`kind` 로 가름) | FRS 는 두 표를 상정 — 7.5 참조 |

### 3.6 M06 — 폐쇄몰 (P2)

| FS ID | 상태 | 근거 | 비고 |
|---|---|---|---|
| FS-M06-001~002 | ⏸ | Cognito·셀프가입 코드 없음. `nice_settings` 는 **위임동의 본인확인**용(P1) | |
| FS-M06-003~005 | 🟡 | `shop_orders`·`shop_product_logs`·`shop_user_sessions` **존재** | P1 테이블 **FK 의존 없음**(문자열 참조). 다만 `shop_product_logs`(shop_user_name·email·product_name)·`shop_user_sessions`(email·ip) 에 **PII 컬럼** — FRS 가 짚은 그 자리 |
| FS-M06-006~007 | **🟡 오염** | `app/Services/TossPayments/PaymentCancelService.php`·`PaymentLinkController` — **카드결제·취소가 P1 흐름에 붙어 있다** | 가상계좌와 **분리되어 있지 않다**(같은 `toss_payments` 표·같은 결제 링크) — 7.5 참조 |
| FS-M06-008 | ✅ | 고객향 배송 추적 라우트 없음 | |
| FS-M06-009~010 | ⏸ | 급여 안내 링크 테이블·화면 없음 | P1 미구현 정상 |
| FS-M06-011~012 | ✅ | 고객향 1:1 UI 없음. `inquiries` 는 운영자용 | |
| FS-M06-013~014 | 🟡 | `POST api/prescriptions/upload` 은 **SR App(운영자·상담원)** 용이며 인증 필수 | 고객 셀프 업로드 아님 — 비인증 노출 금지 충족 |

---

## 4. 인터페이스

| IF ID | 상태 | 근거 | 비고 |
|---|---|---|---|
| IF-WW-001 | 🟡 | `app/Services/WithworksSync.php` · `withworks_settings` · `orders.withworks_so_no` | **멱등키 없음**, 재시도 정책 없음 |
| IF-WW-002 | ❌ | 재고 조회 호출·캐시 근거 **0건** | |
| IF-WW-003 | ✅ | `POST api/webhook/withworks` + `schedule: withworks:sync */10` · `hash_equals` 검증 · `withworks_events` 멱등 | |
| IF-WW-004 | 🟡 | `WithworksSyncCommand` 존재 · **write-back 없음**(G-03) | 초기 시드·컷오버 델타·정합성 점검 근거 없음 |
| IF-FAX-001 | ✅ | `linkhub/popbill` · `Popbill\FaxController@send` · `fax_histories.receipt_num` | 자격증명 `.env` |
| IF-FAX-002 | 🟡 | **폴링만** (`fax:sync-pending */5`) | 콜백 라우트 없음 |
| IF-FAX-003 | ⏸ | 수신 기능 없음 | G-04 충족 |
| IF-PG-001 | ✅ | `app/Services/TossPayments/VirtualAccountService.php` · `POST toss/webhook` · 서명 검증 · `toss_payments.order_id` 유일 | |
| IF-PG-002~004 | **🟡 오염** | `PaymentCancelService`(취소·부분취소·조회) · 결제링크 카드결제 | P2 인데 **P1 에서 동작** — 7.5 |
| IF-OCR-001 | ❌ | `OcrService` 는 있으나 서버에 Textract 자격증명 **미설정**, 리전 `us-east-1`, 비동기 API 미사용 | |
| IF-NTF-001 | ✅ | `MessageSender`·`KakaoService`·`Popbill/` · `message_templates.template_code` · `message_histories` | 동의 검사는 FS-M04-001 참조 |
| IF-STD-001 | ❌ | `billing_offices` 시더·임포트 커맨드 없음 | 화면에서 손으로 등록 |
| IF-AUTH-001 | ⏸ | Cognito 없음 | P2 정상 |
| IF-AUTH-002 | **❌** | Entra/SAML/OIDC 없음 | FS-M01-002 와 같음 |
| IF-NICE-001 | 🟡 | `nice_settings` · `app/Services/Nice/` · `NICE_SIMULATE=true` | P2 가 아니라 **P1 위임동의 본인확인**으로 쓰고 있다 — 7.5 |
| IF-NTS-001 | ✅ | `Popbill/` Cashbill·Taxinvoice · `cashbill_records`·`popbill_taxinvoices` · `activity_log` | `POPBILL_ISSUE_SIMULATE=true`(현재 시늉) |

---

## 5. NFR

| ID | 상태 | 근거 | 비고 |
|---|---|---|---|
| NFR-PER-005 | 🟡 | API `max:10240`(10MB) · 웹 `max:50240`/`max:51200`(50MB) · `mimes:jpg,jpeg,png,pdf,heic` | **FRS 20MB 와 다르고 경로마다 다르다.** heic 추가 |
| NFR-PER-006 | ❌ | 잡 자체가 없어 타임아웃·상태 표출 없음 | |
| NFR-PER-007 | 🟡 | 그리드 페이징 있음 | Export 잡 없음 |
| NFR-PER-008 | ❌ | `tries`/`backoff` 쓸 잡이 없음 | 스케줄 커맨드는 실패 알림 없음 |
| NFR-AVL-003 | ⚠ | AWS 콘솔 접근 없음 | DB 가 공인 IP 직결인 점만 확인 |
| SEC-01 | **❌** | SSO 없음 → MFA 강제 없음 | 로그인 OTP(`login_otp_tokens`)는 있으나 Entra MFA 아님 |
| SEC-02 | ❌ | S3 미사용(`FILESYSTEM_DISK=local`) — SSE-KMS 해당 없음 | Aurora 암호화는 `⚠` |
| SEC-03 | ❌ | `temporaryUrl` 0건 · S3 미사용 | 파일은 라우트 + 권한으로 보호 |
| SEC-04 | 🟡 | G-08 과 같음 | 3년 보존 정책 없음 |
| **SEC-05** | ✅ | ① `ResidentNo::encrypt/decrypt`(전용 Encrypter, `:126`·`:140`) ② `hash_hmac('sha256',…,$pepper)` `:111,122` → `resident_no_hash` ③ `resident_no_masked` `:89` ④ `decrypt($payload,$reason,$target)` → `audit()` `:210` ⑤ `retentionUntil()` `:161` `addYears(config('rrn.retention.years',5))` ⑥ `checksumValid()` `:173` + `BackfillResidentNo` 커맨드 | **여섯 포인트 모두 근거 있음** |
| SEC-06 | ✅ | `resident_no_masked` 접근자 · `Patient.php:244 $hidden` · `perm()` 게이트 | |
| SEC-07 | ✅ | `toss_payments` 에 카드번호 저장 컬럼 없음 — 마스킹된 값이 `raw_response` JSON 에만 | 다만 카드결제 자체가 P1 에 있음(7.5) |
| SEC-08 | ⚠ | `DB_HOST=3.34.53.36` — **공인 IP**. VPC 분리 여부는 콘솔 없이 확인 불가 | 운영/개발 서버가 **한 대** |
| OPS-01 | ❌ | CloudWatch 로그 드라이버·알람 설정 근거 없음 | `config/logging.php` 기본 |
| OPS-02 | **❌** | dev/stg 서버 없음 · 개발기 `.env` 가 **운영 DB 직결** | 운영 자료로 시험하고 있다 |

---

## 6. 미결 항목 — 구현 보류 준수

| ID | 상태 | 근거 |
|---|---|---|
| OI-01 | ✅ | Five/Six 수량 산식 코드 없음 — `five_program`·`five_110days` 는 입력·표시 칸뿐 |
| OI-02 | ✅ | `resolveBaselineDate` 류 없음 · 110일 기산 로직 0건 |
| OI-03 | ✅ | 판매상태2 ↔ UNICORN 상태 매핑 없음 |
| OI-04 | ✅ | 대리점/의료기샵 채널 분기 없음 — `orders.so_type` 은 위드웍스 판매유형 |
| OI-05 | ✅ | 「Cheong Entrance」류 오역 없음 |
| OI-06 | — | 문서 사안 |

---

## 7. 결과 요약

### 7.1 집계

| 구분 | 총 | ✅ | 🟡 | ❌ | ⏸ | ⛔ | ⚠ |
|---|---|---|---|---|---|---|---|
| 2장 전역 (G) | 10 | 6 | 2 | 1 | 0 | 1 | 0 |
| 3.1 M01 | 9 | 1 | 7 | 1 | 0 | 0 | 0 |
| 3.2 M02 | 4 | 1 | 2 | 1 | 0 | 0 | 0 |
| 3.3 M03 | 7 | 3 | 4 | 0 | 0 | 0 | 0 |
| 3.4 M04 | 6 | 1 | 4 | 1 | 0 | 0 | 0 |
| 3.5 M05 | 9 | 4 | 4 | 0 | 1 | 0 | 0 |
| 3.6 M06 (P2) | 7 | 2 | 3 | 0 | 2 | 0 | 0 |
| 4장 IF | 16 | 6 | 5 | 4 | 3 | 0 | 0 |
| 5장 NFR | 16 | 3 | 3 | 8 | 0 | 0 | 2 |
| 6장 OI | 6 | 5 | 0 | 0 | 0 | 0 | 0 |

### 7.2 `⛔` 위반 및 `❌` 미구현

| ID | 한 줄 | 근거 |
|---|---|---|
| **G-02 ⛔** | `TODOWORKS` 명칭 잔존 3곳 | `WithworksSetting.php:80` · `order.blade.php:25,11141` |
| G-07 ❌ | AWS 리전이 `us-east-1` | 서버 `.env` |
| FS-M01-002 ❌ | 관리자 SSO(Entra) 없음 | config·composer 0건 |
| FS-M02-004 ❌ | OCR 재처리·수기 폴백 플래그 없음 | `retry_count`·`manual_entry` 부재 |
| FS-M04-006 ❌ | 연계 모니터링 없음 | `integration_logs` 부재 |
| IF-WW-002 ❌ | 재고 API·캐시 없음 | 호출 근거 0건 |
| IF-OCR-001 ❌ | Textract 미설정·동기·리전 불일치 | 서버 `.env` · `OcrService` |
| IF-STD-001 ❌ | 기준정보 시더·임포트 없음 | 커맨드 부재 |
| IF-AUTH-002 ❌ | Entra ID 연계 없음 | 위와 같음 |
| NFR-PER-006 / 008 ❌ | 잡·큐가 없어 타임아웃·재시도 없음 | `ShouldQueue` 0건 |
| SEC-01 / 02 / 03 ❌ | SSO·MFA / KMS / pre-signed 모두 없음 | S3 미사용 |
| OPS-01 / 02 ❌ | CloudWatch 없음 · 환경 분리 없음 | 개발기가 운영 DB 직결 |

### 7.3 `🟡` 부분 구현 — 누락 포인트

- **비동기 처리가 통째로 없다** — `app/Jobs` 없음, `ShouldQueue` 0건. OCR·대량 발송·Export 가 모두 동기. (FS-M01-005⑤ · FS-M02-001① · FS-M04-005② · NFR-PER-006/008)
- **감사 로그 보존·무결성** — 3년 보존, 로그 접근 로깅, append-only 통제 없음. (G-08 · FS-M01-009③④⑤ · SEC-04)
- **주문 상태 이력 테이블 없음** — 반품만 `order_return_logs` 가 있다. (FS-M03-003④)
- **위드웍스 멱등키·자동 재시도 없음** (FS-M03-005③④ · IF-WW-001)
- **팩스 결과는 폴링만** — 콜백 없음, 재발송-원전송 연결 없음. (FS-M05-006 · IF-FAX-002)
- **공단 재등록 대상자 목록 화면 없음** — 알림만 있다. (FS-M05-001)
- **수신동의 검사·채널별 on/off 없음** (FS-M04-001③ · FS-M04-002①)
- **업로드 제한이 경로마다 다르다** — API 10MB · 웹 50MB. (NFR-PER-005)

### 7.4 `⚠` 확인 불가

- NFR-AVL-003(Aurora Multi-AZ·PITR) · SEC-08(VPC 분리) — **AWS 콘솔 접근 없음**
- FS-M04-004 현황 7종·작업 큐 3종 — 코드만으로 항목 수를 셀 수 없어 화면 실물 대조 필요

### 7.5 FRS와 다르게 구현된 사항

| 관련 ID | FRS 사양 | 실제 구현 | 근거 |
|---|---|---|---|
| G-07 · SEC-02 · SEC-03 · FS-M01-007 | 파일은 **S3 + KMS**, pre-signed 다운로드 | **서버 로컬 디스크**. S3 버킷·리전 미설정 | `FILESYSTEM_DISK=local`, `AWS_BUCKET` 빈값 |
| IF-OCR-001 · FS-M02-001 | Textract **비동기** + 필드별 신뢰도 | 자격증명 미설정, 동기 호출, 문서 단위 신뢰도 1값 | `OcrService.php` · 서버 `.env` |
| FS-M01-002 · SEC-01 | 관리자 **Entra ID SSO + MFA** | 자체 로그인 + **OTP** | `login_otp_tokens` |
| **IF-PG-002~004** | 카드결제·취소·조회는 **P2** | **P1 에서 동작** — 결제 링크로 카드결제, `PaymentCancelService` 로 부분·전액 취소. 가상계좌와 **같은 표**(`toss_payments`) | 2026-09-06 실전송 확인(`tlink20260906152205ZUMI4`, 10,500+30,000 취소) |
| IF-NICE-001 | NICE 는 **P2** | **P1 위임동의 본인확인**에 쓰고 있다(현재 `NICE_SIMULATE=true`) | `app/Services/Nice/` · `consent/{token}` |
| FS-M05-008 · 009 | `nhis_branches` / `local_governments` **두 표** | `billing_offices` **한 표** + `kind`(nhis·local) | 기능은 동등 |
| NFR-PER-005 | 20MB 단일 | API 10MB · 웹 50MB (**경로마다 다름**), heic 허용 | `PrescriptionApiController:37` · `PrescriptionController:847` |
| 1장 전제 | PHP 8.3 / Laravel 11 / Livewire | **PHP 8.2 / Laravel 12 / Livewire 미사용** | `php -v` · `artisan --version` |
| OPS-02 | dev / prod 분리 | **운영 한 대**. 개발기 `.env` 가 운영 DB 직결 | `APP_ENV=production` |

### 7.6 기계 판독용 요약

```json
{
  "frs_version": "0.4",
  "checked_at": "2026-09-06T18:10:00+09:00",
  "env": {"server": "prod(단일)", "branch": "main", "commit": "76b8ee6",
          "php": "8.2.23", "laravel": "12.56.0", "routes": 377, "tables": 67,
          "migrations_ran": 113, "migrations_pending": 0, "tests": 15},
  "results": [
    {"id":"G-01","status":"✅","evidence":"grep openai/gpt 0건","note":"OCR Textract 단일"},
    {"id":"G-02","status":"⛔","evidence":"WithworksSetting.php:80; order.blade.php:25,11141","note":"TODOWORKS 명칭 잔존"},
    {"id":"G-03","status":"✅","evidence":"config/database.php 커넥션 5종; DB::connection( 0건","note":"REST만"},
    {"id":"G-04","status":"✅","evidence":"fax inbound 라우트 0건","note":""},
    {"id":"G-05","status":"✅","evidence":"EDI grep 오탐만","note":"포털 링크만"},
    {"id":"G-06","status":"✅","evidence":"patients.resident_no_enc/hash/masked","note":"평문 없음"},
    {"id":"G-07","status":"❌","evidence":".env AWS_DEFAULT_REGION=us-east-1","note":"S3 미사용"},
    {"id":"G-08","status":"🟡","evidence":"activity_log, user_activity_logs","note":"3년 보존 없음"},
    {"id":"G-09","status":"✅","evidence":"common_codes","note":""},
    {"id":"G-10","status":"🟡","evidence":"TossWebhookController:28-35; WithworksWebhookController:77-86; withworks_events.event_id","note":"팝빌 웹훅 없음"},

    {"id":"FS-M01-001","status":"🟡","evidence":"patients, privacy_consents","note":"중복 후보 표시 없음"},
    {"id":"FS-M01-002","status":"❌","evidence":"saml/oidc/entra grep 0건","note":"SSO 없음"},
    {"id":"FS-M01-003","status":"🟡","evidence":"permission_group_pages.can_*","note":"역할 4종 고정 없음"},
    {"id":"FS-M01-004","status":"✅","evidence":"patients, patient_addresses, ResidentNo::decrypt","note":""},
    {"id":"FS-M01-005","status":"🟡","evidence":"api/prescriptions/upload; max:10240","note":"10MB, 로컬, 동기"},
    {"id":"FS-M01-006","status":"🟡","evidence":"PrescriptionController:847 max:50240","note":"API와 제한 불일치"},
    {"id":"FS-M01-007","status":"🟡","evidence":"prescription_documents/attachments","note":"pre-signed 없음"},
    {"id":"FS-M01-008","status":"🟡","evidence":"common_codes; OrderReturn::FLOWS","note":"삭제 방지·이력 없음"},
    {"id":"FS-M01-009","status":"🟡","evidence":"activity_log","note":"접근 로깅·무결성·보존 없음"},

    {"id":"FS-M02-001","status":"🟡","evidence":"OcrService.php; prescriptions.ocr_confidence","note":"동기, 문서단위 신뢰도"},
    {"id":"FS-M02-002","status":"✅","evidence":"order.blade.php 상세 목록","note":""},
    {"id":"FS-M02-003","status":"🟡","evidence":"reviewed_by/at/review_memo","note":"3분기 아님"},
    {"id":"FS-M02-004","status":"❌","evidence":"retry_count/manual_entry 부재","note":""},

    {"id":"FS-M03-001","status":"✅","evidence":"orders.prescription_id; OrderSync::ensure","note":""},
    {"id":"FS-M03-002","status":"✅","evidence":"BillingStrategy::TYPE_NONRX","note":""},
    {"id":"FS-M03-003","status":"🟡","evidence":"orders 목록","note":"주문 상태 이력 없음"},
    {"id":"FS-M03-004","status":"✅","evidence":"toss/webhook; toss_payments.order_id unique; bank_transaction_splits","note":""},
    {"id":"FS-M03-005","status":"🟡","evidence":"WithworksSync.php","note":"멱등키·재시도 없음"},
    {"id":"FS-M03-006","status":"🟡","evidence":"withworks:sync */10","note":"재고 API 없음"},
    {"id":"FS-M03-007","status":"✅","evidence":"five_program 표시칸만","note":"판정 로직 없음 — 기대 충족"},

    {"id":"FS-M04-001","status":"🟡","evidence":"MessageSender; message_histories","note":"동의검사·재시도 없음"},
    {"id":"FS-M04-002","status":"🟡","evidence":"message_templates","note":"채널별 on/off 없음"},
    {"id":"FS-M04-003","status":"✅","evidence":"service_requests, inquiries, counsel_status","note":""},
    {"id":"FS-M04-004","status":"🟡","evidence":"DashboardController@index","note":"항목 수 대조 못함"},
    {"id":"FS-M04-005","status":"🟡","evidence":"privacy-consents/export; wwGrid downloadExcel","note":"비동기 Export 없음"},
    {"id":"FS-M04-006","status":"❌","evidence":"integration_logs 부재","note":""},

    {"id":"FS-M05-001","status":"🟡","evidence":"NhisRenewNotice; nhis:renew-notice","note":"대상자 목록 화면 없음"},
    {"id":"FS-M05-002","status":"✅","evidence":"prescription_consents; consent/{token}; dompdf","note":""},
    {"id":"FS-M05-003","status":"✅","evidence":"nhis_claim_status; local_claim_dispatches; ClaimReadiness","note":""},
    {"id":"FS-M05-004","status":"✅","evidence":"cashbill_records; popbill_taxinvoices","note":""},
    {"id":"FS-M05-005","status":"✅","evidence":"popbill/fax/send; fax_histories.receipt_num","note":"중복 점검 없음"},
    {"id":"FS-M05-006","status":"🟡","evidence":"fax:sync-pending */5","note":"콜백·parent_id 없음"},
    {"id":"FS-M05-007","status":"⏸","evidence":"수신 기능 없음","note":"G-04 충족"},
    {"id":"FS-M05-008","status":"🟡","evidence":"billing_offices; billing_office_areas","note":"형식검증·이력·시더 없음"},
    {"id":"FS-M05-009","status":"🟡","evidence":"billing_offices.kind=local","note":"단일 표로 통합"},

    {"id":"FS-M06-001~002","status":"⏸","evidence":"Cognito 없음","note":"nice_settings 는 P1용"},
    {"id":"FS-M06-003~005","status":"🟡","evidence":"shop_orders, shop_product_logs, shop_user_sessions","note":"FK 의존 없음·PII 컬럼 있음"},
    {"id":"FS-M06-006~007","status":"🟡","evidence":"PaymentCancelService; PaymentLinkController","note":"P1 오염 — 가상계좌와 미분리"},
    {"id":"FS-M06-008","status":"✅","evidence":"고객향 추적 라우트 없음","note":""},
    {"id":"FS-M06-009~010","status":"⏸","evidence":"없음","note":""},
    {"id":"FS-M06-011~012","status":"✅","evidence":"고객향 UI 없음","note":""},
    {"id":"FS-M06-013~014","status":"🟡","evidence":"api/prescriptions/upload 인증 필수","note":"SR App 운영자용"},

    {"id":"IF-WW-001","status":"🟡","evidence":"WithworksSync.php","note":"멱등키 없음"},
    {"id":"IF-WW-002","status":"❌","evidence":"재고 호출 0건","note":""},
    {"id":"IF-WW-003","status":"✅","evidence":"api/webhook/withworks; hash_equals; withworks_events","note":""},
    {"id":"IF-WW-004","status":"🟡","evidence":"WithworksSyncCommand","note":"write-back 없음·정합성 점검 없음"},
    {"id":"IF-FAX-001","status":"✅","evidence":"linkhub/popbill; fax_histories","note":""},
    {"id":"IF-FAX-002","status":"🟡","evidence":"fax:sync-pending","note":"폴링만"},
    {"id":"IF-FAX-003","status":"⏸","evidence":"수신 없음","note":""},
    {"id":"IF-PG-001","status":"✅","evidence":"VirtualAccountService; toss/webhook 서명검증","note":""},
    {"id":"IF-PG-002~004","status":"🟡","evidence":"PaymentCancelService","note":"P2인데 P1에서 동작"},
    {"id":"IF-OCR-001","status":"❌","evidence":"Textract 자격증명 미설정; us-east-1","note":""},
    {"id":"IF-NTF-001","status":"✅","evidence":"MessageSender; message_templates; message_histories","note":""},
    {"id":"IF-STD-001","status":"❌","evidence":"시더 없음","note":""},
    {"id":"IF-AUTH-001","status":"⏸","evidence":"Cognito 없음","note":""},
    {"id":"IF-AUTH-002","status":"❌","evidence":"Entra 없음","note":""},
    {"id":"IF-NICE-001","status":"🟡","evidence":"app/Services/Nice; NICE_SIMULATE=true","note":"P1에서 사용 중"},
    {"id":"IF-NTS-001","status":"✅","evidence":"Popbill Cashbill/Taxinvoice","note":"ISSUE_SIMULATE=true"},

    {"id":"NFR-PER-005","status":"🟡","evidence":"max:10240 / max:50240","note":"경로마다 다름"},
    {"id":"NFR-PER-006","status":"❌","evidence":"잡 없음","note":""},
    {"id":"NFR-PER-007","status":"🟡","evidence":"그리드 페이징","note":"Export 잡 없음"},
    {"id":"NFR-PER-008","status":"❌","evidence":"ShouldQueue 0건","note":""},
    {"id":"NFR-AVL-003","status":"⚠","evidence":"콘솔 접근 없음","note":""},
    {"id":"SEC-01","status":"❌","evidence":"SSO 없음","note":"OTP는 있음"},
    {"id":"SEC-02","status":"❌","evidence":"FILESYSTEM_DISK=local","note":""},
    {"id":"SEC-03","status":"❌","evidence":"temporaryUrl 0건","note":""},
    {"id":"SEC-04","status":"🟡","evidence":"G-08","note":""},
    {"id":"SEC-05","status":"✅","evidence":"ResidentNo.php:111,122 hash_hmac; :126 encrypt; :140 decrypt(reason,target); :161 retentionUntil; :173 checksumValid; :210 audit","note":"여섯 포인트 충족"},
    {"id":"SEC-06","status":"✅","evidence":"resident_no_masked; Patient.php:244 hidden; perm()","note":""},
    {"id":"SEC-07","status":"✅","evidence":"카드 저장 컬럼 없음","note":""},
    {"id":"SEC-08","status":"⚠","evidence":"DB_HOST=공인IP","note":"VPC 확인 불가"},
    {"id":"OPS-01","status":"❌","evidence":"CloudWatch 설정 없음","note":""},
    {"id":"OPS-02","status":"❌","evidence":"APP_ENV=production 단일","note":"개발기가 운영 DB 직결"},

    {"id":"OI-01","status":"✅","evidence":"산식 없음","note":""},
    {"id":"OI-02","status":"✅","evidence":"기산 로직 없음","note":""},
    {"id":"OI-03","status":"✅","evidence":"상태 매핑 없음","note":""},
    {"id":"OI-04","status":"✅","evidence":"채널 분기 없음","note":""},
    {"id":"OI-05","status":"✅","evidence":"오역 라벨 없음","note":""},
    {"id":"OI-06","status":"—","evidence":"문서 사안","note":""}
  ],
  "deviations": [
    {"id":"G-07/SEC-02/SEC-03/FS-M01-007","frs":"S3+KMS, pre-signed","actual":"서버 로컬 디스크","evidence":"FILESYSTEM_DISK=local, AWS_BUCKET 빈값"},
    {"id":"IF-OCR-001/FS-M02-001","frs":"Textract 비동기·필드별 신뢰도","actual":"미설정·동기·문서단위 1값","evidence":"OcrService.php, 서버 .env"},
    {"id":"FS-M01-002/SEC-01","frs":"Entra ID SSO + MFA","actual":"자체 로그인 + OTP","evidence":"login_otp_tokens"},
    {"id":"IF-PG-002~004","frs":"카드결제·취소는 P2","actual":"P1에서 동작, 가상계좌와 같은 표","evidence":"PaymentCancelService, toss_payments"},
    {"id":"IF-NICE-001","frs":"NICE는 P2","actual":"P1 위임동의 본인확인에 사용","evidence":"app/Services/Nice"},
    {"id":"FS-M05-008/009","frs":"두 표","actual":"billing_offices 한 표 + kind","evidence":"billing_offices"},
    {"id":"NFR-PER-005","frs":"20MB","actual":"API 10MB / 웹 50MB","evidence":"PrescriptionApiController:37, PrescriptionController:847"},
    {"id":"1장","frs":"PHP 8.3 / Laravel 11 / Livewire","actual":"PHP 8.2 / Laravel 12 / Livewire 미사용","evidence":"php -v, artisan --version"},
    {"id":"OPS-02","frs":"dev/prod 분리","actual":"운영 한 대, 개발기가 운영 DB 직결","evidence":"APP_ENV=production"}
  ]
}
```
