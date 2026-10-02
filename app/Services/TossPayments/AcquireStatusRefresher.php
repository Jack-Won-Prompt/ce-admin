<?php

namespace App\Services\TossPayments;

use App\Models\TossPayment;
use Illuminate\Support\Facades\Log;

/**
 * 카드 매입 상태를 토스에 다시 물어 적는다 (2026-10-02 지시).
 *
 * 「토스페이먼츠 결제하면, 매입전인지 매입후인지도 화면에서 보이게」
 *
 * ## 왜 다시 물어야 하는가
 *
 * 승인할 때 받아 둔 `raw_response` 는 **그 순간의 사본**이다. 카드사 매입은 보통 다음
 * 영업일에 끝나는데 우리 사본은 그때 바뀌지 않는다 — 운영의 카드 결제 23건이 모두
 * `READY` 였다(2026-10-02 확인). 그대로 적으면 한 달 전 결제도 「매입 전」이라 보인다.
 *
 * ## 화면을 세워 두지 않는다
 *
 * 목록을 열 때마다 모든 건을 물으면 토스를 수십 번 부르고 그만큼 화면이 늦는다.
 * 세 가지로 묶는다.
 *
 *   ① 끝난 것은 묻지 않는다 — 매입 완료ㆍ매입 취소는 더 바뀌지 않는다
 *   ② 방금 물어본 것은 다시 묻지 않는다 (기본 30분)
 *   ③ 한 번에 묻는 수를 막는다 (기본 15건)
 *
 * 그래서 목록을 여러 번 열면 남은 건이 조금씩 채워진다. 한 건을 꼭 지금 알아야 하면
 * `한건()` 으로 그것만 묻는다.
 *
 * ## 못 물어도 화면은 선다
 *
 * 토스가 늦거나 막히면 그 줄만 옛 값으로 남는다. 화면은 **언제 물어본 값인지**를
 * 함께 보여 주므로, 사람이 오래된 값을 「지금 그렇다」고 읽지 않는다.
 */
class AcquireStatusRefresher
{
    /** 이 사이에 물어본 것은 다시 묻지 않는다 (분) */
    private const 다시묻는틈 = 30;

    /** 한 번에 물을 수 있는 건수 */
    private const 한번에 = 15;

    public function __construct(private TossClient $toss)
    {
    }

    /**
     * 여러 건을 묶어 새로 고친다 — 목록 화면이 부른다.
     *
     * @param  \Illuminate\Support\Collection<int, TossPayment>|array  $줄들
     * @return int  실제로 고쳐 적은 건수
     */
    public function 여럿(iterable $줄들, int $최대 = self::한번에): int
    {
        if (! $this->toss->isConfigured()) {
            return 0;
        }

        $고침 = 0;

        foreach ($줄들 as $줄) {
            if ($고침 >= $최대) {
                break;
            }

            if (! $this->물어볼까($줄)) {
                continue;
            }

            if ($this->한건($줄)) {
                $고침++;
            }
        }

        return $고침;
    }

    /**
     * 이 줄을 지금 물어야 하나.
     *
     * 결제키가 없으면 물을 수 없다 — 가상계좌가 아직 입금 전인 줄이 그렇다.
     */
    public function 물어볼까(TossPayment $줄): bool
    {
        if (blank($줄->payment_key) || ! $줄->매입더볼까()) {
            return false;
        }

        return $줄->acquire_checked_at === null
            || $줄->acquire_checked_at->lt(now()->subMinutes(self::다시묻는틈));
    }

    /**
     * 한 건을 묻고 적는다. 적었으면 true.
     *
     * 터지지 않는다 — 목록 화면이 이것 때문에 멈추면 안 된다.
     */
    public function 한건(TossPayment $줄): bool
    {
        if (blank($줄->payment_key)) {
            return false;
        }

        try {
            $답 = $this->toss->get('/v1/payments/' . rawurlencode((string) $줄->payment_key));
        } catch (\Throwable $e) {
            Log::warning('[토스 매입상태] 묻지 못했다', [
                'payment_key' => $줄->payment_key,
                'error'       => mb_substr($e->getMessage(), 0, 200),
            ]);

            return false;
        }

        $값 = $답['card']['acquireStatus'] ?? null;

        /* 카드가 아닌 건(가상계좌ㆍ간편결제)은 `card` 가 없다. 물어본 사실만 적어
           두어 다음 열 때 또 묻지 않게 한다 — 값은 비운다. */
        $적을것 = [
            'acquire_checked_at' => now(),
        ];

        if (is_string($값) && $값 !== '') {
            $적을것['acquire_status'] = $값;
        }

        /* 상태도 함께 따라온다 — 매입을 물으러 간 길에 취소된 것을 알게 되는 일이 있다.
           다만 **우리가 적어 둔 취소 자취는 덮지 않는다**(toss_payments 는 한 주문 한
           줄이라, 덮으면 받은 돈이 0으로 읽히는 일이 있었다). 상태 글자만 맞춘다. */
        if (! empty($답['status']) && is_string($답['status'])) {
            $적을것['status'] = $답['status'];
        }

        $줄->forceFill($적을것)->saveQuietly();

        return true;
    }
}
