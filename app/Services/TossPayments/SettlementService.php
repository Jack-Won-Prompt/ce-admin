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

    /**
     * 요약 — 정산액 입금일로 묶는다 (2026-09-14 확인요청 1쪽).
     *
     * 토스 화면의 요약 표가 그렇게 선다. 한 줄로 합쳐 놓으면 「언제 얼마가 들어오는가」가
     * 사라져 통장을 맞출 때 쓸 수 없다 — 그 표를 보는 까닭이 바로 그것이다.
     * 전체 합은 표 아래 합계줄이 세운다.
     */
    public function 요약(array $줄들): array
    {
        $통 = [];
        foreach ($줄들 as $s) {
            $통[(string) ($s['paidOutDate'] ?? '')][] = $s;
        }
        krsort($통);

        $결과 = [];
        foreach ($통 as $날 => $묶음) {
            $합    = $this->더하기($묶음);
            $매출일 = array_values(array_filter(array_map(fn ($x) => (string) ($x['soldDate'] ?? ''), $묶음)));
            sort($매출일);

            $결과[] = [
                '입금일'   => $날,
                '매출일'   => $매출일
                    ? (reset($매출일) === end($매출일) ? reset($매출일) : reset($매출일) . ' ~ ' . end($매출일))
                    : '',
                '건수'     => count($묶음),
                '매출액'   => $합['매출액'],
                'PG수수료' => $합['수수료'],
                'PG부가세' => $합['부가세'],
                '수수료합' => $합['수수료합'],
                '정산액'   => $합['정산액'],
            ];
        }

        return $결과;
    }

    /**
     * 건별 — 토스 화면의 칸을 그대로 세운다 (2026-09-14 확인요청 1쪽).
     *
     * 저쪽이 주지 않는 것이 있다 — 구매자명과 구매상품이 그렇다. 그 둘은 **우리가 안다**:
     * 정산 줄의 orderId 가 곧 우리가 토스에 보낸 주문 아이디라, 그것으로 우리 주문을
     * 되짚으면 누구의 무엇인지가 붙는다. 토스 화면을 볼 때 늘 따로 찾아보던 값이다.
     *
     * @param array<string, array{order_no:string, patient:string, product:string}> $우리것
     */
    public function 건별(array $줄들, array $우리것 = []): array
    {
        return array_map(function ($s) use ($우리것) {
            $카드 = is_array($s['card'] ?? null) ? $s['card'] : [];
            $취소 = is_array($s['cancel'] ?? null) ? $s['cancel'] : [];
            $간편 = is_array($s['easyPay'] ?? null) ? $s['easyPay'] : [];
            $계좌 = is_array($s['virtualAccount'] ?? null) ? $s['virtualAccount'] : [];
            $우리 = $우리것[(string) ($s['orderId'] ?? '')] ?? [];

            return [
                '상점아이디'   => (string) ($s['mId'] ?? ''),
                '입금일'       => (string) ($s['paidOutDate'] ?? ''),
                '매출일'       => (string) ($s['soldDate'] ?? ''),
                '승인일시'     => $this->때($s['approvedAt'] ?? null),
                '취소일시'     => $this->때($취소['canceledAt'] ?? null),
                '주문번호'     => (string) ($s['orderId'] ?? ''),
                /* 우리 쪽 값 — 토스 화면에는 없다. 담당자는 이 줄이 누구 것인지 알아야
                   통장과 맞출 수 있는데, 여태는 주문번호를 들고 다른 화면으로 갔다. */
                'CE주문번호'   => (string) ($우리['order_no'] ?? ''),
                '구매자명'     => (string) ($우리['patient']  ?? ''),
                '구매상품'     => (string) ($우리['product']  ?? ''),
                '결제수단'     => (string) ($s['method'] ?? ''),
                '결제상태'     => $취소 ? '취소' : '완료',
                '결제기관'     => (string) ($카드['company'] ?? ($간편['provider'] ?? ($계좌['bankCode'] ?? ''))),
                '카드종류'     => trim(((string) ($카드['cardType'] ?? '')) . ' ' . ((string) ($카드['ownerType'] ?? ''))),
                '할부'         => ((int) ($카드['installmentPlanMonths'] ?? 0)) > 0
                                    ? $카드['installmentPlanMonths'] . '개월' : '일시불',
                '카드승인번호' => (string) ($카드['approveNo'] ?? ''),
                '매입상태'     => (string) ($카드['acquireStatus'] ?? ''),
                'TID'          => (string) ($s['transactionKey'] ?? ''),
                '영수증'       => (string) ($카드['receiptUrl'] ?? ''),
                '결제취소액'   => (int) ($s['amount'] ?? 0),
                'PG수수료'     => (int) ($s['fee'] ?? 0),
                '공급가액'     => (int) ($s['supplyAmount'] ?? 0),
                '부가세'       => (int) ($s['vat'] ?? 0),
                '할부수수료'   => (int) ($s['interestFee'] ?? 0),
                '정산액'       => (int) ($s['payOutAmount'] ?? 0),
            ];
        }, $줄들);
    }

    /** 일자별 — 매출일로 묶는다 */
    public function 일자별(array $줄들): array
    {
        return $this->묶기($줄들, fn ($s) => (string) ($s['soldDate'] ?? ''), '매출일');
    }

    /**
     * 결제수단별 — 수수료를 갈래대로 가른다 (2026-09-14 확인요청 2쪽).
     *
     * 토스 화면이 PG수수료를 일반ㆍ할부ㆍ포인트ㆍ기타로 갈라 적는다. 그 갈래는 정산 줄의
     * fees 배열이 들고 있다(type: BASE 따위). 할부 수수료만은 따로 오는 칸이 있다.
     */
    public function 결제수단별(array $줄들): array
    {
        $통 = [];
        foreach ($줄들 as $s) {
            $통[(string) ($s['method'] ?? '') ?: '(없음)'][] = $s;
        }
        ksort($통);

        $결과 = [];
        foreach ($통 as $수단 => $묶음) {
            $합   = $this->더하기($묶음);
            $갈래 = ['일반' => 0, '할부' => 0, '포인트' => 0, '기타' => 0];

            foreach ($묶음 as $s) {
                $갈래['할부'] += (int) ($s['interestFee'] ?? 0);

                foreach ((array) ($s['fees'] ?? []) as $f) {
                    $이름 = match (strtoupper((string) ($f['type'] ?? ''))) {
                        'BASE'                    => '일반',
                        'INSTALLMENT', 'INTEREST' => '할부',
                        'POINT'                   => '포인트',
                        default                   => '기타',
                    };
                    $갈래[$이름] += (int) ($f['fee'] ?? 0);
                }
            }

            $결과[] = [
                '결제수단'     => $수단,
                '건수'         => count($묶음),
                '매출액'       => $합['매출액'],
                '수수료일반'   => $갈래['일반'],
                '수수료할부'   => $갈래['할부'],
                '수수료포인트' => $갈래['포인트'],
                '수수료기타'   => $갈래['기타'],
                'PG수수료'     => $합['수수료'],
                'PG부가세'     => $합['부가세'],
                '수수료합'     => $합['수수료합'],
                '정산액'       => $합['정산액'],
            ];
        }

        return $결과;
    }

    /**
     * 받아 온 줄을 걸러 낸다 (2026-09-14 확인요청 1쪽).
     *
     * 토스 화면이 묻는 것 가운데 정산 응답으로 가릴 수 있는 것만 건다 — 결제수단ㆍ
     * 결제상태ㆍ상점아이디다. 없는 값으로 칸을 만들어 두면 담당자가 넣어 보고
     * 아무것도 안 걸러지는 것을 겪는다.
     */
    public function 거르기(array $줄들, array $조건): array
    {
        $수단 = trim((string) ($조건['method'] ?? ''));
        $상태 = trim((string) ($조건['pay_status'] ?? ''));
        $상점 = trim((string) ($조건['mid'] ?? ''));

        if ($수단 === '' && $상태 === '' && $상점 === '') {
            return $줄들;
        }

        return array_values(array_filter($줄들, function ($s) use ($수단, $상태, $상점) {
            if ($수단 !== '' && (string) ($s['method'] ?? '') !== $수단) {
                return false;
            }
            if ($상점 !== '' && ! str_contains((string) ($s['mId'] ?? ''), $상점)) {
                return false;
            }
            if ($상태 !== '' && (($s['cancel'] ?? null) ? '취소' : '완료') !== $상태) {
                return false;
            }

            return true;
        }));
    }

    /** 고르개에 세울 결제수단 — 받아 온 줄에 실제로 있는 것만 */
    public function 수단들(array $줄들): array
    {
        $것 = array_values(array_unique(array_filter(
            array_map(fn ($s) => (string) ($s['method'] ?? ''), $줄들))));
        sort($것);

        return $것;
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
