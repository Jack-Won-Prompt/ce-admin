<?php

namespace App\Services\TossPayments;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 토스페이먼츠 정산내역 (2026-09-11 확인요청 1ㆍ6ㆍ7쪽).
 *
 * 토스 화면의 네 갈래(요약ㆍ건별ㆍ일자별ㆍ결제수단별)를 우리 화면에서 그대로 본다.
 * 저쪽이 주는 것은 건별 한 벌뿐이라, 나머지 셋은 그것을 묶어 만든다.
 *
 * 결제 내역(TossPayment)과는 다른 것이다. 그쪽은 「받았는가」를, 이쪽은 「얼마가
 * 언제 우리 통장으로 들어오는가」를 말한다 — 수수료와 부가세가 빠진 뒤의 값이다.
 */
class SettlementService
{
    /** 한 번에 받아 오는 줄 수. 저쪽이 정한 윗값이 5000 이다. */
    private const 쪽크기 = 1000;

    /** 아무리 길어도 이만큼에서 멈춘다 — 기간을 잘못 넣어도 화면이 멎지 않게 */
    private const 최대쪽 = 20;

    /**
     * 기간의 정산 줄을 모두 받아 온다.
     *
     * @param  string  $기준  soldDate(매출일) | paidOutDate(정산액 입금일)
     * @return array<int, array<string, mixed>>
     */
    public function 가져오기(string $부터, string $까지, string $기준 = 'soldDate'): array
    {
        $키 = config('toss.test_mode') ? config('toss.test.secret_key') : config('toss.live.secret_key');

        if (! $키) {
            return [];
        }

        $기준 = in_array($기준, ['soldDate', 'paidOutDate'], true) ? $기준 : 'soldDate';

        /* 같은 물음이 탭을 옮길 때마다 되풀이된다 — 네 갈래가 한 벌을 나눠 쓴다.
           길게 담아 두지는 않는다. 저쪽 값이 하루 안에도 바뀐다. */
        return Cache::remember(
            "toss:settle:{$기준}:{$부터}:{$까지}",
            now()->addMinutes(5),
            fn () => $this->모아오기($키, $부터, $까지, $기준),
        );
    }

    private function 모아오기(string $키, string $부터, string $까지, string $기준): array
    {
        $모두 = [];

        for ($쪽 = 1; $쪽 <= self::최대쪽; $쪽++) {
            try {
                $res = Http::withBasicAuth($키, '')
                    ->acceptJson()
                    ->timeout(20)
                    ->get('https://api.tosspayments.com/v1/settlements', [
                        'startDate' => $부터,
                        'endDate'   => $까지,
                        'dateType'  => $기준,
                        'page'      => $쪽,
                        'size'      => self::쪽크기,
                    ]);
            } catch (\Throwable $e) {
                Log::warning('[토스 정산] 부르지 못했습니다', ['error' => $e->getMessage()]);
                break;
            }

            if ($res->failed()) {
                Log::warning('[토스 정산] 받지 못했습니다', [
                    'status' => $res->status(), 'body' => mb_substr($res->body(), 0, 300),
                ]);
                break;
            }

            $몸 = $res->json();
            /* 저쪽이 목록으로 줄 때도 있고 감싸서 줄 때도 있다 */
            $줄 = is_array($몸) ? ($몸['settlements'] ?? (array_is_list($몸) ? $몸 : [])) : [];

            if (! $줄) {
                break;
            }

            $모두 = array_merge($모두, $줄);

            if (count($줄) < self::쪽크기) {
                break;
            }
        }

        return $모두;
    }

    // ── 네 갈래 ───────────────────────────────────────────

    /** 요약 — 한 줄로 본다 */
    public function 요약(array $줄들): array
    {
        $합 = $this->더하기($줄들);

        return [[
            '건수'       => count($줄들),
            '매출액'     => $합['매출액'],
            'PG수수료'   => $합['수수료'],
            'PG부가세'   => $합['부가세'],
            '수수료합'   => $합['수수료합'],
            '입금정산액' => $합['정산액'],
        ]];
    }

    /** 건별 — 저쪽이 준 그대로 */
    public function 건별(array $줄들): array
    {
        return array_map(fn ($s) => [
            '매출일'     => (string) ($s['soldDate'] ?? ''),
            '입금일'     => (string) ($s['paidOutDate'] ?? ''),
            '승인일시'   => $this->때($s['approvedAt'] ?? null),
            '결제수단'   => (string) ($s['method'] ?? ''),
            '주문번호'   => (string) ($s['orderId'] ?? ''),
            '상점아이디' => (string) ($s['mId'] ?? ''),
            '매출액'     => (int) ($s['amount'] ?? 0),
            'PG수수료'   => (int) ($s['supplyAmount'] ?? 0),
            'PG부가세'   => (int) ($s['vat'] ?? 0),
            '수수료합'   => (int) ($s['fee'] ?? 0),
            '정산액'     => (int) ($s['payOutAmount'] ?? 0),
        ], $줄들);
    }

    /** 일자별 — 매출일로 묶는다 */
    public function 일자별(array $줄들): array
    {
        return $this->묶기($줄들, fn ($s) => (string) ($s['soldDate'] ?? ''), '매출일');
    }

    /** 결제수단별 */
    public function 결제수단별(array $줄들): array
    {
        return $this->묶기($줄들, fn ($s) => (string) ($s['method'] ?? ''), '결제수단');
    }

    // ──────────────────────────────────────────────────────

    private function 묶기(array $줄들, callable $열쇠, string $칸이름): array
    {
        $통 = [];
        foreach ($줄들 as $s) {
            $k = $열쇠($s) ?: '(없음)';
            $통[$k][] = $s;
        }
        ksort($통);

        $결과 = [];
        foreach ($통 as $k => $묶음) {
            $합 = $this->더하기($묶음);
            $결과[] = [
                $칸이름      => $k,
                '건수'       => count($묶음),
                '매출액'     => $합['매출액'],
                'PG수수료'   => $합['수수료'],
                'PG부가세'   => $합['부가세'],
                '수수료합'   => $합['수수료합'],
                '정산액'     => $합['정산액'],
            ];
        }

        return $결과;
    }

    private function 더하기(array $줄들): array
    {
        $합 = ['매출액' => 0, '수수료' => 0, '부가세' => 0, '수수료합' => 0, '정산액' => 0];

        foreach ($줄들 as $s) {
            $합['매출액']   += (int) ($s['amount'] ?? 0);
            $합['수수료']   += (int) ($s['supplyAmount'] ?? 0);
            $합['부가세']   += (int) ($s['vat'] ?? 0);
            $합['수수료합'] += (int) ($s['fee'] ?? 0);
            $합['정산액']   += (int) ($s['payOutAmount'] ?? 0);
        }

        return $합;
    }

    /** 2026-09-06T15:22:05+09:00 → 2026-09-06 15:22 */
    private function 때(?string $v): string
    {
        if (! $v) {
            return '';
        }

        try {
            return \Illuminate\Support\Carbon::parse($v)->format('Y-m-d H:i');
        } catch (\Throwable) {
            return (string) $v;
        }
    }
}
