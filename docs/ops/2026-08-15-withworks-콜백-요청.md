# Withworks → CEAdmin 물류 상태 콜백 요청

작성일 2026-08-15 · 받는 쪽(CEAdmin) 구현 완료, 보내는 쪽(Withworks) 구현 요청

CEAdmin 은 판매주문을 Withworks 에 넘긴 뒤의 진행을 알지 못한다. 지금은 10분마다 `so_show`
를 물어보는데, 물어보는 사이에 벌어진 일은 늦게 알고 호출도 낭비다. Withworks 가 바뀔 때마다
알려 주면 그때그때 맞출 수 있다.

아래 「Withworks 쪽 지시문」을 Withworks 프로젝트의 Claude 에게 그대로 넘기면 된다.

---

## 받는 쪽 규격 (CEAdmin — 구현 완료)

| 항목 | 값 |
|---|---|
| 주소 | `POST https://www.ceadmin.co.kr/api/webhook/withworks` |
| 인증 | 헤더 `X-Withworks-Secret: <공유 비밀>` |
| 형식 | `Content-Type: application/json` |
| 응답 | `200 {"success":true}` · `401` 비밀 불일치 · `422` 형식 오류 · `503` 우리 쪽 미설정 |

공유 비밀은 양쪽 `.env` 에 같은 값을 넣는다 (CEAdmin `WITHWORKS_WEBHOOK_SECRET`).
비밀이 설정돼 있지 않으면 CEAdmin 은 `503` 으로 거절한다 — 열어 두지 않는다.

### 보낼 것

```json
{
  "event_id": "wwevt_20260815_000123",
  "event": "so.shipped",
  "ce_order_number": "ORD-0009",
  "so_no": "S2608130005",
  "status": "shipped",
  "status_label": "출고완료",
  "occurred_at": "2026-08-15T18:00:00+09:00",
  "ship": {
    "ship_no": "SH-2026-0001",
    "ship_status": "shipped",
    "ship_status_label": "출고",
    "tracking_no": "1234567890123",
    "courier": "CJ대한통운",
    "delivered_at": null
  }
}
```

| 필드 | 필수 | 설명 |
|---|---|---|
| `event_id` | ✅ | 사건마다 고유. 재전송해도 **같은 값**이어야 중복 처리를 막는다 |
| `event` | ✅ | 아래 목록 중 하나 |
| `ce_order_number` | ✅ | CEAdmin 주문번호 (`so_store` 에 넘겼던 값) |
| `so_no` | | Withworks 판매주문번호 |
| `status` / `status_label` | | 그 시점의 판매주문 상태와 한글 표기. 길이 제한 없음 — 우리 요약 칸(50·100자)에 안 들어가면 잘라 담고 원본은 사건 표에 통째로 남긴다 |
| `occurred_at` | | 그쪽에서 일어난 시각 (ISO8601). 없으면 받은 시각을 쓴다 |
| `ship` | | 배송 정보가 **생겼거나 바뀐 사건에만** 넣는다 |

### 이벤트 목록

| `event` | 시점 | CEAdmin 주문 상태 |
|---|---|---|
| `so.created` | 판매주문 등록 | 안 바뀜 |
| `so.confirmed` | 판매주문 확정 | 안 바뀜 |
| `so.allocated` | 재고 할당 | 안 바뀜 |
| `so.picked` | 피킹 완료 | 안 바뀜 |
| `so.invoiced` | 송장 발행 | 안 바뀜 (송장번호는 저장) |
| `so.shipped` | 출고 완료 | `shipping` |
| `so.delivered` | 배송 완료 | `delivered` + `delivered_at` |
| `so.cancelled` | 취소 | `cancelled` |

CEAdmin 주문 상태는 네 가지뿐이라 할당·피킹은 상태를 바꾸지 않는다. 그래도 **보내야 한다** —
사건 기록으로 남아 진행 상황을 보여 준다.

`so.delivered` 는 특히 중요하다. CEAdmin 은 **배송이 끝나야 공단에 청구할 수 있다고 보고**,
이 사건을 받는 순간 청구 준비 여부를 다시 따진다.

### 지켜야 할 것

1. **재시도** — 응답이 `2xx` 가 아니거나 시간이 초과되면 다시 보낸다. 간격을 늘려 가며
   (예: 1분 → 5분 → 30분 → 2시간) 몇 차례 시도하고, 그래도 안 되면 로그에 남기고 포기한다.
2. **재전송 시 `event_id` 유지** — 새로 매기면 중복으로 쌓인다. CEAdmin 은 같은 `event_id`
   에 `200 {"message":"Already processed"}` 로 답한다.
3. **`4xx` 는 재시도하지 않는다** — 형식이 틀린 것이라 다시 보내도 같다. 로그에 남긴다.
4. **동기 호출 금지** — 출고 처리 트랜잭션 안에서 보내지 말 것. CEAdmin 이 느리면 Withworks
   업무가 멈춘다. 큐에 넣어 뒤에서 보낸다.
5. **순서는 보장하지 않아도 된다** — CEAdmin 은 온 것만 덮으므로 `so.picked` 가 `so.shipped`
   뒤에 도착해도 송장이 지워지지 않는다.

---

## Withworks 쪽 지시문 (그대로 복사해 넘길 것)

> CEAdmin 연동 작업입니다. 판매주문의 상태가 바뀔 때마다 CEAdmin 에 웹훅으로 알려 주세요.
>
> **보낼 곳**
> - `POST https://www.ceadmin.co.kr/api/webhook/withworks`
> - 헤더: `X-Withworks-Secret: <.env 의 CEADMIN_WEBHOOK_SECRET>`, `Content-Type: application/json`
> - 주소와 비밀은 `.env` 로 뺄 것 (`CEADMIN_WEBHOOK_URL`, `CEADMIN_WEBHOOK_SECRET`).
>   비밀이 없으면 아무것도 보내지 말고 로그만 남길 것.
>
> **보낼 내용** (JSON)
> ```json
> {
>   "event_id": "고유값 — 재전송 시에도 같은 값",
>   "event": "so.shipped",
>   "ce_order_number": "ORD-0009",
>   "so_no": "S2608130005",
>   "status": "shipped",
>   "status_label": "출고완료",
>   "occurred_at": "2026-08-15T18:00:00+09:00",
>   "ship": {
>     "ship_no": "SH-2026-0001",
>     "ship_status": "shipped",
>     "ship_status_label": "출고",
>     "tracking_no": "1234567890123",
>     "courier": "CJ대한통운",
>     "delivered_at": null
>   }
> }
> ```
> - `event_id`, `event`, `ce_order_number` 는 필수입니다.
> - `ship` 은 **배송 정보가 생겼거나 바뀐 사건에만** 넣으세요. 없는 사건에 빈 값으로 넣지
>   마세요 — 넣으면 CEAdmin 에 저장된 송장번호가 지워집니다.
> - `ce_order_number` 는 CEAdmin 이 `so_store` 로 넘겼던 그 값입니다. 판매주문에 저장돼
>   있을 테니 그대로 실어 주세요.
>
> **보낼 시점** — 아래 여덟 가지입니다. 상태를 바꾸는 코드 경로를 찾아 각각에 붙여 주세요.
>
> | event | 시점 |
> |---|---|
> | `so.created` | 판매주문 등록 |
> | `so.confirmed` | 판매주문 확정 |
> | `so.allocated` | 재고 할당 |
> | `so.picked` | 피킹 완료 (주문 피킹·토탈 피킹 모두) |
> | `so.invoiced` | 송장 발행 — `tracking_no` 를 꼭 실어 주세요 |
> | `so.shipped` | 출고 완료 |
> | `so.delivered` | 배송 완료 — `ship.delivered_at` 을 실어 주세요 |
> | `so.cancelled` | 취소 |
>
> CEAdmin 은 배송이 끝나야 공단에 요양비를 청구할 수 있습니다. `so.delivered` 가 안 오면
> 청구가 시작되지 않으니 이 사건은 특히 빠뜨리지 마세요.
>
> **구현 방식**
> - 상태 변경 트랜잭션 **안에서 동기로 호출하지 마세요.** CEAdmin 이 느리거나 죽으면
>   Withworks 업무가 멈춥니다. 큐(잡)에 넣어 뒤에서 보내세요.
> - 응답이 `2xx` 가 아니거나 시간이 초과되면 재시도하세요. 간격을 늘려 가며(1분 → 5분 →
>   30분 → 2시간) 몇 차례 시도하고, 그래도 안 되면 로그에 남기고 포기하세요.
> - **재시도할 때 `event_id` 를 새로 매기지 마세요.** 같은 값이어야 CEAdmin 이 중복을
>   걸러냅니다. 이미 처리된 건에는 `200 {"message":"Already processed"}` 가 옵니다.
> - `4xx` 응답은 재시도하지 마세요. 형식이 틀린 것이라 다시 보내도 같습니다.
> - 보낸 기록(이벤트, 주문번호, 응답 코드, 시도 횟수)을 남겨 주세요. 안 갔을 때 무엇이
>   빠졌는지 찾을 수 있어야 합니다.
> - 타임아웃은 10초면 충분합니다.
>
> **확인 방법**
> 테스트 주문 하나로 등록 → 확정 → 할당 → 피킹 → 송장 → 출고 → 배송완료를 차례로 진행하고,
> 매 단계에서 CEAdmin 이 `200` 을 돌려주는지 보세요. 같은 요청을 두 번 보내 두 번째에
> `Already processed` 가 오는지도 확인해 주세요.

---

## 붙인 뒤 CEAdmin 쪽에서 확인할 것

```bash
# 사건이 쌓이는지
php artisan tinker --execute="echo App\Models\WithworksEvent::count();"

# 어떤 사건이 왔는지
php artisan tinker --execute="App\Models\WithworksEvent::latest()->take(10)->get(['event','ce_order_number','status_label','occurred_at'])->each(fn(\$e)=>print(\$e->event.' '.\$e->ce_order_number.' '.\$e->status_label.PHP_EOL));"
```

콜백이 들어오기 시작해도 `withworks:sync` 폴링은 그대로 둔다. 웹훅이 몇 번 실패해도 결국
맞춰지는 그물이 있어야 하고, 10분에 한 번 훑는 비용은 싸다.
