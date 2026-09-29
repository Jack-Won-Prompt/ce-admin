# 운영 이관 — 주소 옮기기 · 처방전ㆍ첨부파일 원천 분석 (2026-09-29)

지시 : 「주소부터 진행 처방전은 udf 명이 우리 한글명 항목명과 유사함, 첨부파일은 원천 소스
`E:\xampp\htdocs\withworks` 분석해서 운영 위드웍스 확인」

대상 서버는 **운영**(`3.37.111.215` · `/opt/ce-admin` · `unicorn_mycolo`). 원천을 읽는 일만
시험서버(`3.34.53.36`)에서 했다 — 위드웍스 창고 DB 에 붙는 자리가 두 서버 모두에 있다.

---

## 1. 주소 이관 — 마쳤다

| 무엇 | 몇 |
|---|---|
| 원천 `ww_customer_addresses` 가운데 (E) 환자 것 | 21,185줄 |
| 옮긴 것 | **21,037줄** |
| 주소글이 비어 건너뜀 | 148줄 |
| 거래처를 못 찾음 | 0줄 |
| 우편번호를 비운 줄 | 1줄 |

`patient_addresses` 는 11,647 → **32,684줄**.

### 1-1. 오류 — 우편번호가 칸을 넘쳐 멈췄다

```
SQLSTATE[22001]: String data, right truncated: 1406
Data too long for column 'postcode' at row 104
```

`patient_addresses.postcode` 는 `varchar(10)` 인데, 원천의 zipcode 칸에서 숫자만 뽑아도
열 자를 넘는 줄이 있었다. 500줄씩 한 문장으로 넣으므로 한 줄이 걸리면 그 묶음 500줄이
모두 들어가지 않는다. **앞선 일곱 묶음 3,500줄은 이미 들어가 있었다** — 그래서 고친 뒤
다시 돌렸을 때 「이미있음 3,500」으로 세어졌다(원천 번호에 유일 색인이 있어 겹치지 않는다).

무엇이 들어 있었는지 먼저 읽었다. 숫자만 남긴 길이별로 —

| 길이 | 줄 수 |
|---|---|
| 0자 | 1 |
| 5자 | 14,465 |
| **11자** | **1** ← 칸을 넘침 |

넘치는 그 한 줄(원천 #97426)의 zipcode 칸에는 **주소가 적혀 있었다** —
「서울 노원구 월계로42길 97 꿈의 숲 SK VIEW 101동 1203호」. 숫자만 뽑으면
`42971011203`(11자)이고, 열 자로 자르면 `4297101120` 이라는 **없는 번호**가 남는다.

그래서 자르지 않고 **비운다**. 우편번호꼴(다섯 자ㆍ여섯 자)이 아니면 `null` 이다 —
없는 것이 틀린 것보다 낫다.

```php
$숫자만 = preg_replace('/\D/', '', (string) $a->zipcode);
$이우편번호 = in_array(strlen($숫자만), [5, 6], true) ? $숫자만 : null;
```

커밋 `75b2a1cf`(자르기) → `1c2b7a16`(비우기로 고침).

### 1-2. 오류 — 운영에서 `git pull` 이 안 됐다

```
fatal: detected dubious ownership in repository at '/opt/ce-admin'
```

`sudo -u www-data git pull` 로 부른 탓이다. `/opt/ce-admin` 과 `/opt/ce-admin/.git` 은
**`ubuntu:ubuntu`** 이므로 git 은 `ubuntu` 로 불러야 한다. `www-data` 에게는 남의 저장소로
보여 git 이 스스로 멈춘다.

→ 운영에서 소스를 당길 때는 `ubuntu`, artisan 을 돌릴 때는 `sudo -u www-data`.

### 1-3. 오류 — `www-data` 로 tinker 가 안 됐다

```
ErrorException  Writing to directory /var/www/.config/psysh is not allowed.
```

`www-data` 의 집(`/var/www`)에 쓸 수 없어서다. artisan 명령은 잘 돌지만 tinker 만 걸린다.

```bash
sudo install -d -o www-data -g www-data -m 700 /tmp/wwhome
sudo -u www-data env HOME=/tmp/wwhome XDG_CONFIG_HOME=/tmp/wwhome/.config php artisan tinker …
```

### 1-4. 오류 — information_schema 질의에서 칸 이름이 겹쳤다

```
SQLSTATE[23000]: 1052 Column 'TABLE_NAME' in field list is ambiguous
```

`KEY_COLUMN_USAGE` 와 `REFERENTIAL_CONSTRAINTS` 를 이을 때 두 표에 같은 이름의 칸이 있다.
`u.TABLE_NAME`, `r.DELETE_RULE` 처럼 어느 표인지 밝혀야 한다.

### 1-5. 아직 확인을 기다리는 것 — 같은 주소가 두 줄로 선다

거래처를 옮길 때 담은 **대표 주소 11,647줄**이 그대로 남아 있다. 엄격하게 대어 보니 —

| 무엇 | 몇 |
|---|---|
| 우편번호ㆍ주소ㆍ상세가 **모두** 같은 짝이 옮겨온 줄에 있다 | **11,647** (전부) |
| 주소는 같은데 우편번호ㆍ상세가 다르다 | 0 |
| 같은 주소가 아예 없다 | 0 |

지워도 잃는 것이 없다. 게다가 이 11,647줄은 만든 때가 **오늘**로 찍혀 있어 원천 날짜를
가진 줄과 이력 차례가 섞인다.

지워도 되는 근거 —
- `patient_addresses` 를 **외래키로 가리키는 표가 없다**. `patient_address_id` 같은 칸을
  가진 표도 없다(전 스키마 검색).
- 「지금 주소」는 `patients.postcode/address/address_detail` 칸이 들고 있다. 주문 등록ㆍ
  팩스ㆍ서류는 그 칸을 읽으므로 이력 줄을 지워도 바뀌지 않는다
  (`Patient::saved` 훅이 그 칸을 보고 이력을 쌓는다).

옮겨온 줄끼리도 341묶음 808줄이 겹치지만, 이것은 **원천이 같은 주소를 날짜만 달리해 여러
번 담아 둔 것**이라 이력 그대로 남겼다.

---

## 2. 처방전 — udf 짝 (추측 아님 · 근거 있는 것만)

원천 표는 **`warehouse.account_add_informations`** (우리 거울 표 `ww_prescription_infos`).
화면은 `main/medical/standard/account_add_info` 이고 담는 곳은
`AccountAddInfoController::store`.

### 2-1. 위드웍스가 붙인 한글 이름

네 곳에서 모았다 — `edit_form.blade.php` 의 항목 이름(`resources/lang/ko/medical/standard/txt.json`
으로 풀었다) · 같은 파일 hidden 줄의 한글 주석 · `AccountAddInfoController::accUpdate` 의
주석(거래처 → 처방전으로 베끼는 자리) · 목록 질의의 코드표 별칭(`CL_XXX.descr as udfNN`).

| udf | 위드웍스 이름 | 어디서 |
|---|---|---|
| udf1 | 주민등록번호 | accUpdate 주석 |
| udf2 | 진단확인일 | 화면 항목 |
| udf3 | 상병구분 (코드표 `CL_SICKNESSCODE`) | 화면 항목 + 코드표 |
| udf4 | 2년 후 공단 재등록 필요여부 | 화면 항목 |
| udf5 | 상병코드 | 화면 항목 |
| udf6 | 구분(SB/SCI) | accUpdate 주석 |
| udf7 | 요류 역학 검사일 | 화면 항목 |
| udf8 | 1일 처방 개수 | 화면 항목 |
| udf9 | 총 처방기간 | 화면 항목 |
| udf10 | 총계 | 화면 항목 |
| udf11 | 레벨 (코드표 `CL_LEVEL`) | 코드표 |
| udf12 | 처방전 발행일 | 화면 항목 |
| udf13 | 처방전 사용 기간 (교부일로부터) | 화면 항목 |
| udf14 | 처방전 종료일 | 화면 항목 |
| udf15 | 담당 의사명 | 화면 항목 |
| udf16 | 상담진행 | hidden 주석 |
| udf17 | 구매 구분 (코드표 `CL_BUYGBN`) | 코드표 |
| udf18 | 입원산재보훈출국 | hidden 주석 |
| udf19 | 공단 등록 상태 (코드표 `CL_SATINSTATUS`) | 코드표 |
| udf20 | 사유 (코드표 `CL_ADDINFOREASON`) | 코드표 |
| udf21 | Email | hidden 주석 |
| udf22 | 소득공제/지출증빙/자진발급 | accUpdate 주석 |
| udf23 | 현금영수증번호 | accUpdate 주석 |
| udf24 | 보호자명 | accUpdate 주석 |
| udf25 | 주문 담당자 | 화면 항목 |
| udf26 | E1 Number | hidden 주석 |
| udf27 | CRM Number | hidden 주석 |
| udf28 | 환급 해당 기관 | 화면 항목 |
| udf29 | 모든 서류발행일 | 화면 항목 |
| udf30 | 다음 재구매 가능일 | 화면 항목 |
| udf31 | 원본서류보관 | hidden 주석 |
| udf32 | 신환 master 등록일 | accUpdate 주석 |
| udf33 | 기존 이카운트 병원명 | accUpdate 주석 |
| udf34 | 기존 DtC team 병원명 | accUpdate 주석 |
| udf39 | 요양병원 코드 | accUpdate 주석 |
| udf42 | 건보 위임 동의 시작일 | accUpdate 주석 |
| udf43 | 건보 위임 동의 종료일 | accUpdate 주석 |
| udf50 | NPD vs. Veteran | accUpdate 주석 |
| udf35ㆍ36ㆍ37ㆍ38ㆍ40ㆍ41ㆍ44ㆍ45ㆍ46ㆍ47ㆍ48ㆍ49 | **위드웍스도 이름을 두지 않았다** | — |

### 2-2. 우리 칸과의 짝 — 이미 우리 소스에 적혀 있었다

`app/Http/Controllers/PrescriptionController.php::counselingColumns()` 와
`app/Http/Controllers/WithworksDataController.php` 의 udf 짝 표에 적혀 있다. 새로 정할 일이
아니다.

**처방전에 붙는 것** (우리 `prescriptions`)

| udf | 우리 칸 | 근거 |
|---|---|---|
| udf2 | `diagnosis_date` | counselingColumns |
| udf3 | `disease_class` | counselingColumns |
| udf7 | `uro_date` | counselingColumns |
| udf11 | `benefit_class` | counselingColumns |
| udf13 | `rx_use_period` | counselingColumns |
| udf14 | `rx_end_date` | counselingColumns |
| udf17 | `purchase_type` | counselingColumns |
| udf18 | `special_case` | counselingColumns |
| udf20 | `reason` | counselingColumns |
| udf24 | `caregiver_name` | counselingColumns |
| udf25 | `order_manager` | counselingColumns |
| udf30 | `next_repurchase` | counselingColumns |

**거래처에 붙는 것** (우리 `patients`)

| udf | 우리 칸 |
|---|---|
| udf4 | `nhis_renew` |
| udf6 | `sb_sci` |
| udf19 | `nhis_reg_status` |
| udf22 | `deduction` |
| udf23 | `cash_receipt_no` |
| udf32 | `new_patient_date` |
| udf42 | `nhis_agree_start` |
| udf43 | `nhis_agree_end` |

**이름으로는 뻔하지만 우리 소스에 짝이 적혀 있지 않은 것** — 확인이 필요하다

| udf | 위드웍스 이름 | 우리 칸으로 보이는 것 |
|---|---|---|
| udf5 | 상병코드 | `disease_code` |
| udf8 | 1일 처방 개수 | `daily_count` |
| udf9 | 총 처방기간 | `total_days` |
| udf10 | 총계 | `total_count` |
| udf12 | 처방전 발행일 | `issued_date` |
| udf15 | 담당 의사명 | `doctor_name` |
| udf16 | 상담진행 | `counsel_status` 인지 `dealer_type` 인지 갈린다 |
| udf28 | 환급 해당 기관 | `claim_agency` 인지 `billing_office_id` 인지 갈린다 |
| udf33ㆍudf34 | 병원명 | `hospital_name` |
| udf39 | 요양병원 코드 | `hospital_code` |
| udf29 | 모든 서류발행일 | 우리에게 맞는 칸이 없다 |
| udf31 | 원본서류보관 | 우리에게 맞는 칸이 없다 |
| udf50 | NPD vs. Veteran | 우리에게 맞는 칸이 없다 |

### 2-3. 몇 줄인가

| 무엇 | 몇 |
|---|---|
| 원천 처방전 전체 | 100,023줄 |
| 최근 3년(`reg_date >= 2023-09-29`) | 56,824줄 |
| 그 가운데 살아 있는 것 | 55,197줄 |
| (E) 환자에 붙는 것 | 56,777줄 |
| 운영의 거울 표 `ww_prescription_infos` | **0줄** — 먼저 `withworks:import prescription_infos` |
| 운영의 `prescriptions` | 1줄 (시험 자취 · 지울 것) |

### 2-4. 앞서 잘못 본 것 — 운영에 위드웍스 접속 정보가 없다고 했다

`WithworksSource::연결('prescription_infos')` 로 불러 「접속 정보가 없습니다」가 났다.
`연결()` 이 받는 것은 **`warehouse`ㆍ`admin`** 이고 `prescription_infos` 는
`WithworksImport::대상` 의 열쇠다. 운영에는 `withworks_source` 설정이 **12줄 있다** —
`warehouse`ㆍ`admin` 모두 `database-1.cluster-ro-…`(위드웍스 운영 읽기 전용 복제본)에
붙는다. 즉 운영에서 바로 가져올 수 있다.

---

## 3. 처방전 첨부파일 — 있다. 앞서 「없다」고 한 것은 잘못이었다

### 3-1. 앞서 잘못 본 것

`file_managers`(엑셀 내보내기 3,304 + `chatting` 사진 28)와 `sales_order_images`(0줄)만 보고
「처방전 첨부파일은 위드웍스에 없다」고 보고했다. **틀렸다.**

원천 소스를 읽으니 `AccountAddInfoController::store` 가 첨부를
**`AccountAddInformationDetail`** 로 담는다 —

```php
$directoryPath = 'colo/';
$storageFileName = ImageManager::update($directoryPath . '/', $filename, $format, $file);
$sendImageData['add_id']      = $addInfoId;       // → account_add_informations.id
$sendImageData['file_name']   = $filename;        // 올린 사람이 준 이름
$sendImageData['refile_name'] = $storageFileName; // 실제로 담긴 이름
AccountAddInformationDetail::create($sendImageData);
```

- 표 : **`warehouse.account_add_information_details`** (`add_id` → 처방전)
- 파일 : `Storage::disk('public')` 의 `colo/<refile_name>`
  = 위드웍스 웹서버 `storage/app/public/colo/…` · 바깥에서 `/storage/colo/…`
- `file_path` 칸은 **197,725줄 모두 비어 있다** — 자리는 `refile_name` 하나로 정해진다

### 3-2. 운영 위드웍스에서 확인한 것

| 무엇 | 몇 |
|---|---|
| `account_add_information_details` 전체 | 197,723줄 |
| 살아 있는 것 | 188,520줄 |
| 최근 3년(처방전 `reg_date >= 2023-09-29`) | 103,389줄 |
| 그 가운데 (E) 환자 것 | **103,349줄** (처방전 **37,273장**) |
| 저장 파일이름이 빈 줄 | 0 |

해마다 : 2026년 32,207 · 2025년 31,132 · 2024년 31,618 · 2023년 32,172 · 2022년 27,455 ·
2021년 23,427 · 2020년 7,944 · 2019년 2,063 · 2018년 502

확장자 : jpg 177,421 · (확장자 없음) 8,591 · png 1,887 · jpeg 620 · gif 1

### 3-3. 파일을 받을 수 있나 — 받을 수 있다

| 주소 | 답 |
|---|---|
| `https://www.withworks.co.kr/storage/colo/<refile_name>` | **200 · image/jpeg** |
| `https://www.demoworks.co.kr/storage/colo/<refile_name>` | 200 이지만 `text/html`(로그인 화면) |
| `https://www.withworks.co.kr/storage/app/public/colo/…` | 404 |

표본 40개를 골고루 뽑아 받아 보았다 — **40개 모두 받혔다**. 합 38,365,624바이트 ·
**평균 959KB**.

### 3-4. 막히는 곳 — 자리가 없다

103,349장 × 평균 959KB ≈ **92GB**.

```
Filesystem      Size  Used Avail Use% Mounted on
/dev/root        29G  5.5G   23G  20% /
```

운영 디스크는 **29GB 가운데 23GB 만 남아 있다**. 92GB 는 들어가지 않는다.

### 3-5. 함께 보아야 할 것 — 남의 처방전 사진이 열려 있다

`https://www.withworks.co.kr/storage/colo/<refile_name>` 은 **로그인 없이** 열린다. 파일
이름이 `2026-09-29-6abb4e3bd3c90.jpg` 꼴로 날짜 + 열세 자 열쇠라 마구 맞히기는 어렵지만,
이름을 아는 사람은 누구나 받을 수 있다. 우리가 받을 때 쓰는 길이면서, 위드웍스 쪽에
알려야 할 것이기도 하다.

---

## 4. 이번에 손댄 것

| 파일 | 무엇 |
|---|---|
| `app/Console/Commands/MigrateAddressesFromWithworksCommand.php` | 우편번호가 우편번호꼴이 아니면 비운다 · `우편번호버림` 을 센다 |

커밋 `75b2a1cf` · `1c2b7a16`.

## 5. 확인을 기다리는 것

1. 대표 주소 11,647줄을 지울까 (32,684 → 21,037줄)
2. 2-2 의 「짝이 적혀 있지 않은 udf」 열셋
3. 첨부파일 92GB — 운영 디스크에 23GB 만 남았다
4. 거울 표를 3년치만 담을지, 전체를 담고 옮길 때만 자를지

---

# 2부 — 지시 넷을 받아 끝까지 (2026-09-29 이어서)

지시 : 「1.지우기, 2.그대로(udf16 무시, udf28은 데이터 확인해서 판단),
3.S3로 보내기(3개월치만) → **S3는 나중에하고 운영 디스크에 저장**,
4.전체 100,023줄을 담고 옮길 때만 3년으로」
그리고 「처방전 첨부파일 받아와서 주문 등록의 첨부파일에 매칭이 되어야 함」

## 6. 대표 주소 겹친 줄 — 지웠다

우편번호ㆍ주소ㆍ상세가 모두 같은 짝이 있는 줄만 골라 지웠다. 통째로 지우지 않았다.

| 무엇 | 몇 |
|---|---|
| 지우기 전 | 32,684줄 (옮겨온 것 21,037 · 먼저 있던 것 11,647) |
| 짝이 확인되어 지운 줄 | **11,647** |
| 짝이 없어 남긴 줄 | **0** |
| 지운 뒤 | **21,037줄** |

표본 다섯 명의 `patients.address`(지금 주소)가 모두 이력에 그대로 있다. 이력 차례도
원천 날짜순으로 선다 — 오늘 날짜로 찍혀 맨 위에 섰던 줄이 사라졌다.

남은 겹침 341묶음 808줄은 **원천이 같은 주소를 날짜만 달리해 여러 번 담아 둔 것**이라
이력 그대로 남겼다.

## 7. udf28 — 데이터로 판단했다

원천 값을 최근 3년치로 모아 보니 「인천서부지사」ㆍ「여수시청」ㆍ「관악구청」 같은
**기관 이름**이다. 코드가 아니다. 따라서 `claim_agency`(우리 세 갈래)가 아니라
`billing_office_id` 가 짝이다 — `claim_agency` 는 udf11(급여구분)에서 뽑는다
(`ClaimAgency::fromBenefitClass`; 원천 값이 일반ㆍ기초ㆍ산재ㆍ차상위경감ㆍ자동차보험으로
그 함수가 받는 말과 똑같다).

저쪽은 같은 곳을 여러 꼴로 적어 두었다. 꼴을 펴서 찾는다 —

| 원천 꼴 | 펴면 |
|---|---|
| `청주동부지사(공단)` | `청주동부지사` |
| `공단(제주지사)` | `제주지사` |
| `전주북부` | `전주북부지사` |

| 무엇 | 몇 |
|---|---|
| 값이 있는 처방전 | 37,810장 |
| 이름이 맞은 것 | **28,188장 (74.5%)** |
| 못 맞은 것 | 9,622장 (값 575가지) |

못 맞은 것은 **우리 `billing_offices` 에 그 기관이 없다**는 뜻이다 — 「서구지사」ㆍ
「동구지사」ㆍ「연수출장소」ㆍ「관악구청」ㆍ「화성시청」 따위. 광역시마다 있는 이름
(서구ㆍ동구ㆍ북구ㆍ남구지사)은 어느 곳인지 알 수 없으므로 **짐작하지 않고 비웠다.**
원래 값은 거울 표 `ww_prescription_infos.udf28` 에 그대로 있다.

「산재환자」(184장)는 기관이 아니라 구분이다 — 비웠다.

### 7-1. 오류 — 운영에 청구처 목록이 아예 없었다

첫 세어 보기에서 `기관맞춤 0` 이었다. 까닭은 운영의 `billing_offices` 가 **0줄**이라서다.
시험서버의 171곳은 마스터 관리 화면에서 손으로 넣은 것이라 씨앗(seeder)이 없다.

시험서버에서 그대로 뽑아 운영에 담았다 — 청구처 171곳(공단 지사 167 · 지자체 4) ·
관할 지역 255줄. `id` 를 그대로 옮겼다(지역 표가 그 번호를 가리킨다).

옮기는 중에 PowerShell 이 한글을 망가뜨렸다(`$곳` → `$怨?`). 값을 PowerShell 변수로
받으면 코드페이지를 거친다 — `cmd /c "plink … > file"` 로 **날바이트로 내려받아야** 한다.
싸는 PHP 의 변수 이름도 ASCII 로 두었다.

## 8. 처방전 — 50,963장 옮겼다

| 무엇 | 몇 |
|---|---|
| 거울 표(전체 담음) | **100,088줄** · 26.5초 |
| 최근 3년 · (E) 환자 · 살아 있는 것 | 55,261장 |
| 갈래 10 원외 + 30 원내 | **50,963장 ← 옮겼다** |
| 갈래 20 처방외 | 4,298장 — 처방전이 아니라 뺐다 (`--처방외` 로 켤 수 있다) |
| 거래처 못 찾음 | 0 |
| 처방번호 겹침 | 0 |

원천 코드표(`admin.code_lists`, `account_id=148659`)로 뜻을 확인했다 —

| 코드표 | 값 |
|---|---|
| `ACCADDSTATUS` | 02 등록 · 95 확인 → 우리 `pending` · `approved` |
| `ACCADDTYPE` | 10 처방전-원외 · 20 처방외 · 30 처방전-원내 |
| `LEVEL`(udf11) | 기초ㆍ산재ㆍ일반ㆍ자동차보험ㆍ차상위경감 |
| `BUYGBN`(udf17) | 신구매ㆍ재구매 |
| `SATINSTATUS`(udf19) | 완료ㆍ진행중ㆍ필요없음 |
| `SICKNESSCODE`(udf3) | 1 · 2-1 · 2-2 · 3 |
| `DIVERTICULUMS` | 01 1회 미만 … 17 10회 이상 |
| `FIVEPROGRAM` | 00 N/A · 05 Five · 06 Six |

옮긴 뒤 상태 : `approved` 50,941 · `pending` 22.
청구처 : nhis 42,874 · local 6,431 · none 1,585 · 빈 것 73.

값이 채워진 칸(주요) : `disease_code` 50,941 · `total_days` 50,941 · `doctor_name` 50,935 ·
`reason` 50,941 · `purchase_type` 50,963 · `next_repurchase` 48,356 · `daily_count` 39,992 ·
`issued_date` 38,447 · `caregiver_name` 38,830 · `order_manager` 37,150 ·
`hospital_code` 41,382 · `hospital_name` 14,807 · `billing_office_id` 28,188.

본보기 셋을 원천과 한 칸씩 대어 모두 일치했다(발행일ㆍ상병코드ㆍ1일개수ㆍ처방기간ㆍ
총계ㆍ급여ㆍ의사ㆍ병원ㆍ요양기관번호ㆍ등록일).

`udf33` 은 「주소지-병원명」 꼴이라(「광주동구봉로 -전남대학교병원」) 줄표 뒤만 병원
이름으로 담았다. 줄표가 없는 것(「병원명확인중」)은 그대로 둔다.

### 8-1. 처방번호에 `WW-` 를 붙였다

원천 `add_no` 가 두 꼴로 섞여 있다 — 「ADD0072363」 12,218장 · 맨 숫자 「77628」 38,745장.
맨 숫자를 그대로 담으면 처방번호 칸에 「77628」이 서서 우리 번호(`RX-20260929-001`)와
구별되지 않는다. 원래 값은 거울 표의 `add_no` 에 그대로 있다.

### 8-2. **오류 — 평문 주민번호를 옮겼다 (제가 세운 규칙을 제가 어겼다)**

「udf1(주민등록번호)은 평문이라 옮기지 않는다」고 적어 두고, 원천 메모 `descr` 은 그대로
옮겼다. 그 메모에 **평문 주민번호가 적힌 줄이 13장** 있었다 —

```
CMS 주민번호 오기재하여 691108-… → 420516-… 로 수정
위임장 update요청시 … (E)김지현A 주민번호 951231-… (E)김지현 주민번호 961231-…
```

거래처는 암호화해 담아 두었는데 처방전 `admin_note` 로 샌 것이다.

고침 : 뒤 여섯 자리를 가린다(`ResidentNo::mask` 와 같은 꼴 `YYMMDD-S******`). 앞자리를
남기는 것은 메모의 뜻이 「번호를 이렇게 바꿨다」라서다. 전화번호(`010-1234567`)는
여섯 자리 앞머리가 아니라 건드리지 않는다.

이미 담긴 13장도 고쳤다. 처방전 표 전체(`admin_note`ㆍ`review_memo`ㆍ`reference_note`ㆍ
`counsel_contents`)에 주민번호꼴이 **0** 이다.

## 9. 첨부파일 — 운영 디스크에 담았다 (S3 는 나중으로)

S3 는 쓸 수 없었다 —

| 무엇 | 상태 |
|---|---|
| `.env` 의 `AWS_ACCESS_KEY_ID`ㆍ`AWS_SECRET_ACCESS_KEY`ㆍ`AWS_BUCKET` | 칸만 있고 **값이 없다** |
| `league/flysystem-aws-s3-v3` | **깔려 있지 않다** |
| EC2 의 IAM 역할 | 없다 |

지시대로 운영 디스크에 담았다. 담는 곳은 우리 처방전 사진이 쓰는 `public` 디스크
(`storage/app/public/prescriptions/ww/Y/m/`). 받기 전에 남은 자리를 재고 2GB 를 남긴다 —
디스크가 차면 웹서버가 함께 멈춘다.

| 무엇 | 몇 |
|---|---|
| 저쪽에 있는 3개월치 | 11,301장 |
| 받은 것 | **11,296장 · 8.8GB** |
| 못 받은 것 | **5장** — 저쪽이 404. 표에는 줄이 있는데 파일이 지워졌다 |
| 처방전 그림이 된 것 | 4,674장 |
| 첨부 줄 | 6,622개 (첨부가 붙은 처방전 1,253장) |
| 디스크 | 29GB 가운데 15GB 씀 · 14GB 남음 |

못 받은 5장 : 원천 216179 · 221543 · 223314 · 223315 · 225696.

### 9-1. 주문 등록의 첨부와 맞추기 — 첫 장은 처방전 그림이다

주문은 늘 처방전에서 만들어진다 — `OrderController::store` 는 `prescription_id` 가
**필수**다. 그래서 옮긴 처방전으로 주문을 등록하면 첨부가 그대로 붙는다.

그런데 주문 등록의 「첨부」 목록(`OrderController::faxDocs`)은 두 곳을 읽는다 —
`prescription_attachments` 는 모두 세우지만 **「처방전」 줄만은 `prescriptions.image_path`**
를 읽는다. 받은 것을 전부 첨부로만 담으면 사진이 있는데도 그 줄이
「처방전 이미지가 없습니다」로 선다.

우리 관례가 그렇다 — `PrescriptionController` 업로드 자리는 올린 처방전 그림을
`image_path` 에, 그 밖의 서류(신분증ㆍ결과지)를 `prescription_attachments` 에 담는다.

그래서 **한 처방전의 첫 장(원천 번호가 가장 작은 것)은 `image_path`, 둘째 장부터 첨부**로
바꿨다. 저쪽은 앞뒤ㆍ여러 쪽을 여러 장으로 올리므로 둘째 장부터가 실제로 있다
(한 처방전에 최대 28장).

본보기 처방전 #46361 —

```
① 처방전 줄 (image_path) : prescriptions/ww/2026/07/2026-07-01-6a44be7c386e6.jpg
    KakaoTalk_20260701_154001179_06.jpg · image/jpeg · 493,296바이트 있음
② 첨부 줄 25개 — 모두 [처방전] · image/jpeg · 팩스에 실림 · 파일 있음
③ 주문 목록의 「파일」 칸에 설 수 : 26 (그림 1 + 첨부 25)
```

### 9-2. 오류 — 이어받기가 같은 장을 두 번 세웠다

첫 판에서는 받은 것을 모두 첨부로 담았다(4,902장). 그것을 `--첫장올리기` 로 손질할 때
**첨부 줄을 지웠더니 원천 번호 표시가 사라졌다.** 이어받기는 `ww_detail_id` 로만 가리므로
이미 담은 그 파일을 다시 받아 첨부로 한 번 더 세웠다 — **2,042장**. 주문 등록의 첨부
목록에 같은 장이 두 줄로 섰다.

고침 : 이미 받은 것을 원천 번호와 **그림에 담긴 파일 이름** 둘로 가린다. 담긴 자리는
원천 이름으로 끝나므로 그것으로 알 수 있다.

```php
if (($그림이름[$처방전번호] ?? null) === $d->refile_name) { $셈['이미있음']++; continue; }
```

`--첫장올리기` 는 그림과 같은 파일을 가리키는 첨부 줄도 함께 치운다. 파일은 그림이
가리키고 있으니 그대로 둔다 — 다시 받으면 4GB 를 또 내려받아야 한다.

### 9-3. 온 판 확인 (마친 뒤)

| 무엇 | 몇 |
|---|---|
| 디스크 파일 | **11,296개 · 8.8GB** |
| 그림 + 첨부 줄 | **11,296개** (그림 4,674 + 첨부 6,622) |
| 그림과 같은 파일을 가리키는 첨부 줄 | **0** |
| 같은 처방전에 같은 파일이 두 줄인 묶음 | **0** |
| 줄은 있는데 파일이 없는 것 | **0** |
| 크기가 어긋난 것 | **0** |
| 주민번호꼴이 남은 줄 | **0** |

## 10. 이번에 손댄 것

| 파일 | 무엇 |
|---|---|
| `database/migrations/2026_09_29_050000_add_ww_add_id_to_prescriptions.php` | 처방전에 원천 번호 칸 · 유일 색인 |
| `database/migrations/2026_09_29_060000_add_ww_detail_id_to_prescription_attachments.php` | 첨부에 원천 번호 칸 · 유일 색인 |
| `app/Console/Commands/MigratePrescriptionsFromWithworksCommand.php` | `prescriptions:migrate-from-ww` |
| `app/Console/Commands/FetchWithworksAttachmentsCommand.php` | `prescriptions:fetch-ww-attachments` |

커밋 `4265342d` · `f72d93a1` · `84c5561e` · `85b7170e` · `2d366c4e` · `a5af30eb`.

## 11. 남은 것

1. **첨부파일 3개월치 너머** — 1년치는 37GB, 3년치는 94GB 다. 남은 자리는 14GB 뿐이라
   디스크를 늘리거나 S3 를 세워야 한다(값ㆍ버킷ㆍ어댑터 셋 모두 아직 없다).
2. **`billing_offices` 가 모자란다** — 공단 지사 167곳뿐이라 서구ㆍ동구ㆍ북구ㆍ남구지사ㆍ
   출장소가 없고, 지자체는 4곳뿐이라 구청ㆍ시청이 거의 없다. 9,622장의 청구 기관이
   그래서 비어 있다.
3. **남의 처방전 사진이 열려 있다** — `https://www.withworks.co.kr/storage/colo/<이름>` 이
   로그인 없이 열린다. 우리가 받는 길이면서 위드웍스 쪽에 알려야 할 것이다.
4. 운영의 시험 자취 `prescriptions` 1줄(`RX-20260929-001`)은 그대로 있다.
