<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * 위드웍스로 보낸 것을 그대로 이메일로도 남긴다 (2026-09-07 지시).
 *
 * 연계가 잘 갔는지는 지금 저쪽 화면에 들어가 눈으로 찾아야 안다. 무엇을 어떤
 * 값으로 보냈는지는 로그에만 남고, 로그는 서버에 들어가야 읽는다. 그래서
 * 「보냈다는데 저쪽에 없다」가 생기면 무엇이 틀렸는지 짚는 데 한참 걸렸다 —
 * 실제로 반품 사유 칸(udf6)을 남의 자리에 얹어 두고 여러 날을 흘려보냈다.
 *
 * 보낸 것을 그대로 받아 두면 저쪽 화면과 나란히 놓고 견줄 수 있다.
 *
 * **저쪽이 이어서 할 일도 함께 적는다.** 값만 보내면 「그래서 무엇을 해야
 * 하나」를 사람이 따로 알고 있어야 하고, 그 앎이 머릿속에만 있으면 담당자가
 * 바뀔 때 끊긴다.
 *
 * **업무를 막지 않는다.** 메일이 안 나가도 주문은 이미 갔다. 여기서 던지면
 * 성공한 연계가 실패로 보이므로, 어떤 잘못이든 삼키고 로그에만 남긴다.
 */
class WithworksNotice
{
    /**
     * @param  string  $what     무엇을 보냈는가 (예: '주문 등록', '반품 등록')
     * @param  string  $endpoint 어느 자리로 (예: 'so_store')
     * @param  array   $payload  보낸 값 그대로
     * @param  mixed   $result   저쪽이 돌려준 것 (배열이면 그대로, 아니면 문자열로)
     */
    public static function sent(string $what, string $endpoint, array $payload, $result = null): void
    {
        $to = trim((string) config('web.withworks_email'));

        if ($to === '') {
            return;
        }

        try {
            $body = static::body($what, $endpoint, $payload, $result);

            /* 본문만 있는 편지다. 서식을 갖춘 화면을 만들 까닭이 없다 — 읽는 사람은
               「무엇을 어떤 값으로 보냈고 이제 무엇을 하나」만 보면 되고,
               그것은 글로 충분하다. */
            Mail::raw($body, function ($m) use ($to, $what, $payload) {
                $m->to($to)->subject(static::subject($what, $payload));
            });
        } catch (\Throwable $e) {
            Log::warning('[위드웍스 연계 알림] 메일 실패 (업무는 계속 진행)', [
                'to' => $to, 'what' => $what, 'error' => $e->getMessage(),
            ]);
        }
    }

    // ──────────────────────────────────────────────────────────

    /**
     * 제목에 우리 번호를 넣는다 — 받은 편지함에서 그 번호로 찾는다.
     *
     * 인코딩은 Symfony 에 맡긴다. 한때 우리가 먼저 base64 로 감싸 보았는데,
     * Symfony 가 그것을 다시 Q 인코딩으로 감싸 =?utf-8?Q?=3D=3FUTF-8=3FB… 처럼
     * 두 겹이 되었다 — 고치려던 깨짐을 도리어 만들었다. 손대지 않는 것이 맞다.
     *
     * 줄표(—)는 쓰지 않는다. 굳이 없어도 읽히고, 클라이언트마다 다루는 품이 다르다.
     */
    private static function subject(string $what, array $payload): string
    {
        $no = $payload['ce_order_number']
            ?? $payload['ce_return_number']
            ?? $payload['rx_number']
            ?? '';

        return '[CE Admin] 위드웍스 ' . $what . ($no !== '' ? ' ' . $no : '');
    }

    /**
     * 위드웍스에서 이어서 해야 할 일.
     *
     * 걸음은 3차 4회에서 실제로 밟아 본 그대로다 — 화면 이름과 단추 이름을
     * 적어, 저쪽 화면을 처음 여는 사람도 따라갈 수 있게 한다.
     *
     * @return array{0:string, 1:array<string>, 2:string}  [작업 유형, 걸음, 덧붙임]
     */
    private static function work(string $what): array
    {
        return match ($what) {
            '주문 등록', 'CE샵 주문 등록' => [
                '판매주문 출고',
                [
                    '주문 관리 › 판매 주문에서 이 건을 연다 (상태 「등록」)',
                    '통합 출고 관리 › 줄을 더블클릭 › 「미할당」 탭 › 줄 체크 › 「부분 할당」',
                    '작업 관리 › 출고번호로 검색 › 작업 수량을 먼저 넣고 그다음 작업 바코드 › 「피킹 확정」',
                    '통합 출고 관리 › 「확정」',
                ],
                '작업 수량을 비운 채 바코드부터 넣으면 목표수량이 1 이 되어 한 개씩만 피킹된다. '
                . '송장번호는 3PL 이 채워 보낸 것을 「운송장 엑셀업로드」로 올릴 때 들어온다.',
            ],

            '주문 수정' => [
                '판매주문 수정',
                ['주문 관리 › 판매 주문에서 바뀐 값을 확인한다'],
                '할당ㆍ피킹이 이미 걸린 건은 저쪽이 수정을 막는다 — 그때는 취소하고 다시 세워야 한다.',
            ],

            '반품 등록' => [
                '반품 입고',
                [
                    '주문 관리 › 판매 반품에서 이 건을 연다 (상태 「등록」)',
                    '반품 상태를 「확정」으로 바꾸고 저장한다 → 반품 입고가 선다',
                    '입고 확정 › 입고번호로 검색 › 줄을 더블클릭',
                    '상세 표의 「비고」에 검수 내용을 적는다 → 「확정」',
                ],
                '상세줄에 적은 비고는 CE Admin 의 「창고 검수 비고」로 그대로 온다 — '
                . '무엇을 보았는지가 CE 담당자가 검수를 판단하는 근거다.',
            ],

            '반품 상태 전달' => [
                '확인만',
                ['주문 관리 › 판매 반품에서 적요의 「[CE 상태]」 줄을 본다'],
                'CE Admin 이 어디까지 갔는지 알리는 것이라 저쪽에서 따로 할 일은 없다. '
                . '다만 CE 가 「취소」로 보내면 아직 손대지 않은 반품만 취소된다.',
            ],

            '금액조정 주문 등록' => [
                '금액조정 (출고 없음)',
                [
                    '주문 관리 › 판매 주문에서 이 건을 연다 (판매유형 「전산판매(금액조정)」)',
                    '출고하지 않는다 — 돈을 맞추는 주문이다',
                ],
                '부분 반품ㆍ자격 변경처럼 되돌린 뒤에도 돈이 남는 건에서 선다.',
            ],

            default => ['확인', ['주문 관리에서 이 건을 확인한다'], ''],
        };
    }

    private static function body(string $what, string $endpoint, array $payload, $result): string
    {
        $json = static function ($v): string {
            return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                ?: '(옮길 수 없는 값)';
        };

        [$type, $steps, $note] = static::work($what);

        $lines = [
            $what,
            '',
            '보낸 때   : ' . now()->format('Y-m-d H:i:s'),
            '보낸 곳   : ' . rtrim((string) config('services.demoworks.api_url'), '/')
                           . '/api/v1/ce-admin/' . $endpoint,
            '보낸 사람 : ' . (auth()->user()?->name ?? '(자동)'),
            '',
            '── 위드웍스에서 할 일 ─────────────────',
            '작업 유형 : ' . $type,
            '',
        ];

        foreach ($steps as $i => $step) {
            $lines[] = '  ' . ($i + 1) . '. ' . $step;
        }

        if ($note !== '') {
            $lines[] = '';
            $lines[] = '  ※ ' . $note;
        }

        $lines[] = '';
        $lines[] = '── 보낸 값 ────────────────────────────';
        $lines[] = $json($payload);
        $lines[] = '';
        $lines[] = '── 저쪽이 돌려준 것 ───────────────────';
        $lines[] = is_array($result) ? $json($result) : (string) ($result ?? '(없음)');

        return implode("\n", $lines);
    }
}
