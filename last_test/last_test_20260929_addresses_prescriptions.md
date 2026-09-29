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
