<?php

namespace App\Console\Commands;

use App\Services\TossPayments\TossClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * 밖으로 나간 자취를 치운다 — 토스 승인과 위드웍스 판매주문 (2026-09-29 지시).
 *
 * 시험 자료를 지우면 우리 표는 비지만 **저쪽에는 그대로 남는다.** 토스는 승인된
 * 결제를, 위드웍스는 판매주문을 들고 있다. 그것을 치우려면 결제열쇠와 판매주문번호가
 * 있어야 하는데, 그 값은 우리 표에만 있었으므로 지우는 순간 사라진다.
 *
 * 그래서 test-data:purge 가 지우기 **앞에** 열쇠를 파일로 적어 둔다
 * (storage/app/private/purge-traces/<때>.json). 이 명령은 그 파일을 읽는다.
 *
 * 팝빌은 여기서 다루지 않는다 — 그쪽 자취 표는 외래키가 SET NULL 이라 살아남아
 * 표에서 바로 읽을 수 있고, 이미 제 명령이 있다
 * (popbill:purge-orphan-taxinvoices · popbill:purge-orphan-cashbills).
 *
 * **시험 환경에서만 쓴다.** 운영 결제를 취소하면 실제로 돈이 되돌아간다. 그래서
 * 돌리기 전에 토스가 시험 열쇠를 쓰고 있는지, 위드웍스가 demoworks 를 보고 있는지
 * 스스로 견주고, 아니면 멈춘다 — --force 를 주어도 멈춘다.
 *
 * 되돌릴 수 없는 일이라 기본은 「보여 주기만」이다.
 */
class PurgeExternalTracesCommand extends Command
{
    protected $signature = 'test-data:purge-external
                            {--file= : 열쇠 파일. 비우면 purge-traces 의 가장 나중 것}
                            {--only= : toss 또는 withworks 만}
                            {--refund-account= : 가상계좌 무르기용 「은행코드:계좌번호:예금주」}
                            {--force : 정말 치운다}';

    protected $description = '시험 자료가 밖에 남긴 자취(토스 승인ㆍ위드웍스 판매주문)를 치운다';

    public function handle(TossClient $toss): int
    {
        $정말 = (bool) $this->option('force');
        $만   = $this->option('only');

        $길 = $this->열쇠파일();

        if ($길 === null) {
            return self::FAILURE;
        }

        $적힌것 = json_decode((string) Storage::disk('local')->get($길), true);

        if (! is_array($적힌것)) {
            $this->error('  열쇠 파일을 읽지 못했습니다 — ' . $길);

            return self::FAILURE;
        }

        $this->line('');
        $this->info('══ 밖으로 나간 자취 치우기 ' . ($정말 ? '(실제로 치웁니다)' : '(보여 주기만 합니다)') . ' ══');
        $this->line('  열쇠 파일 : ' . Storage::disk('local')->path($길));
        $this->line('  적은 때   : ' . ($적힌것['적은때'] ?? '?'));
        $this->line('');

        if (! $this->시험환경인가()) {
            return self::FAILURE;
        }

        if ($만 === null || $만 === 'toss') {
            $this->토스($toss, $적힌것['토스승인'] ?? [], $정말);
        }

        if ($만 === null || $만 === 'withworks') {
            $this->위드웍스($적힌것['위드웍스판매주문'] ?? [], $적힌것['위드웍스반품주문'] ?? [], $정말);
        }

        $this->line('');

        if (! $정말) {
            $this->warn('  보여 주기만 했습니다. 정말 치우려면 --force 를 주십시오.');
        }

        $this->line('');

        return self::SUCCESS;
    }

    /**
     * 시험 환경인가 — 아니면 여기서 멈춘다.
     *
     * 운영 결제를 취소하면 실제로 돈이 되돌아가고, 운영 창고의 판매주문을 지우면
     * 나갈 물건이 사라진다. 사람이 --force 를 잘못 친 한 번으로 그 일이 일어나서는
     * 안 된다. 열쇠와 주소를 **직접 보고** 가린다.
     */
    private function 시험환경인가(): bool
    {
        $토스열쇠 = (string) config('toss.secret_key');
        $창고주소 = (string) config('services.demoworks.api_url');

        $토스시험 = str_starts_with($토스열쇠, 'test_');
        $창고시험 = str_contains($창고주소, 'demoworks');

        $this->line('  토스   열쇠 ' . ($토스열쇠 ? mb_substr($토스열쇠, 0, 10) . '…' : '(빔)')
            . ' → ' . ($토스시험 ? '시험' : '운영'));
        $this->line('  위드웍스 주소 ' . ($창고주소 ?: '(빔)')
            . ' → ' . ($창고시험 ? '시험' : '운영'));
        $this->line('');

        if ($토스시험 && $창고시험) {
            return true;
        }

        $this->error('  시험 환경이 아닙니다 — 이 명령은 운영에 쓰지 않습니다.');
        $this->line('    운영 결제를 취소하면 실제로 돈이 되돌아가고,');
        $this->line('    운영 창고의 판매주문을 지우면 나갈 물건이 사라집니다.');

        return false;
    }

    /** 열쇠 파일 고르기 — 비우면 가장 나중 것 */
    private function 열쇠파일(): ?string
    {
        if ($이름 = $this->option('file')) {
            $길 = str_contains($이름, '/') ? $이름 : 'purge-traces/' . $이름;

            if (! Storage::disk('local')->exists($길)) {
                $this->error('  그런 열쇠 파일이 없습니다 — ' . $길);

                return null;
            }

            return $길;
        }

        $것들 = Storage::disk('local')->files('purge-traces');
        sort($것들);

        if ($것들 === []) {
            $this->error('  purge-traces 에 열쇠 파일이 없습니다.');
            $this->line('    test-data:purge 를 돌리면 지우기 앞에서 적어 둡니다.');

            return null;
        }

        return end($것들);
    }

    /**
     * 토스 승인 무르기.
     *
     * 이미 다 무른 것은 건너뛴다 — 다시 부르면 저쪽이 거절하고, 그 거절이 실패로
     * 읽혀 「치우지 못했다」로 남는다.
     *
     * **우리 표에 적힌 승인이 곧 토스가 쥔 돈은 아니다** (2026-09-29 확인).
     * 시험 승인(/pay/{token}/simulate)은 장부에만 적는 것이라 결제열쇠가
     * `TEST_…` 로 서고 토스에는 그런 결제가 없다 — 무르려 하면
     * `NOT_FOUND_PAYMENT` 가 돌아온다. 서른한 건 가운데 서른 건이 그러했다.
     * 그러니 그 답은 실패가 아니라 **치울 것이 없다**는 뜻으로 적는다.
     *
     * 가상계좌로 받은 돈은 돌려줄 계좌가 있어야 무를 수 있다
     * (`INVALID_REFUND_ACCOUNT_NUMBER`). 주문을 지운 뒤라 고객의 계좌를 알 수
     * 없으므로 짐작해 넣지 않는다 — 사람이 --refund-account 로 준다.
     */
    private function 토스(TossClient $toss, array $것들, bool $정말): void
    {
        $this->info('── 토스 승인 ──');

        $할것 = array_values(array_filter($것들, fn ($t) =>
            (int) ($t['amount'] ?? 0) > (int) ($t['cancel_amount'] ?? 0)));

        $남은돈 = array_sum(array_map(fn ($t) =>
            (int) $t['amount'] - (int) ($t['cancel_amount'] ?? 0), $할것));

        $this->line('  적힌 승인 ' . count($것들) . '건 · 우리 표 기준으로 남은 것 ' . count($할것)
            . '건 (' . number_format($남은돈) . '원)');

        if ($할것 === []) {
            return;
        }

        /* 보여 주기일 때는 **저쪽에 직접 물어본다** (2026-09-29).

           열쇠 파일은 지우던 그때를 찍어 둔 것이라, 치운 뒤에 다시 돌려도 같은
           숫자를 낸다 — 아무 일도 일어나지 않은 것처럼 읽힌다. 실제로 그렇게 보여
           한참을 헤맸다. 무엇이 남았는지는 토스가 아는 것이 맞다. */
        if (! $정말) {
            $this->line('  토스에 지금 상태를 물어봅니다 …');

            $셈 = ['없음' => 0, '살아 있음' => 0, '무름' => 0, '못 물어봄' => 0];
            $살아있는돈 = 0;

            foreach ($할것 as $t) {
                try {
                    $res   = $toss->get('/v1/payments/' . $t['payment_key']);
                    $상태 = (string) ($res['status'] ?? '?');
                    $남은 = (int) ($res['balanceAmount'] ?? 0);

                    if (in_array($상태, ['CANCELED', 'EXPIRED', 'ABORTED'], true) || $남은 === 0) {
                        $셈['무름']++;
                    } else {
                        $셈['살아 있음']++;
                        $살아있는돈 += $남은;
                        $this->line('    살아 있음 ' . $t['payment_key'] . ' ' . $상태
                            . ' ' . number_format($남은) . '원');
                    }
                } catch (\Throwable $e) {
                    if (str_contains($e->getMessage(), 'NOT_FOUND_PAYMENT')) {
                        $셈['없음']++;
                    } else {
                        $셈['못 물어봄']++;
                    }
                }
            }

            $this->line('    토스에 없음 ' . $셈['없음'] . '건 (시험 승인이라 장부에만 있었습니다)');
            $this->line('    이미 무른 것 ' . $셈['무름'] . '건');
            $this->line('    아직 살아 있는 것 ' . $셈['살아 있음'] . '건 ('
                . number_format($살아있는돈) . '원)');

            if ($셈['못 물어봄'] > 0) {
                $this->warn('    물어보지 못한 것 ' . $셈['못 물어봄'] . '건');
            }

            return;
        }

        $몸통 = ['cancelReason' => '시험 자료 정리 (주문 삭제됨)'];

        if ($계좌 = $this->환불계좌()) {
            $몸통['refundReceiveAccount'] = $계좌;
        }

        $무름   = 0;
        $없음   = 0;
        $계좌필요 = 0;
        $못함   = 0;

        foreach ($할것 as $t) {
            $열쇠 = (string) $t['payment_key'];
            $몫   = (int) $t['amount'] - (int) ($t['cancel_amount'] ?? 0);

            try {
                $res = $toss->post('/v1/payments/' . $열쇠 . '/cancel', $몸통);

                $this->line('  무름 ' . $열쇠 . ' ' . number_format($몫) . '원 → '
                    . ($res['status'] ?? '?'));
                $무름++;
            } catch (\Throwable $e) {
                $말 = $e->getMessage();

                if (str_contains($말, 'NOT_FOUND_PAYMENT')) {
                    $없음++;

                    continue;
                }

                if (str_contains($말, 'REFUND_ACCOUNT')) {
                    $this->warn('  돌려줄 계좌가 있어야 무릅니다 (가상계좌) ' . $열쇠
                        . ' ' . number_format($몫) . '원');
                    $계좌필요++;

                    continue;
                }

                $this->error('  무르지 못함 ' . $열쇠 . ' → ' . mb_substr($말, 0, 110));
                $못함++;
            }
        }

        if ($없음 > 0) {
            $this->line('  토스에 없는 승인 ' . $없음 . '건 — 시험 승인이라 장부에만 있었습니다.');
        }

        if ($계좌필요 > 0) {
            $this->line('  --refund-account=은행코드:계좌번호:예금주 를 주면 무를 수 있습니다.');
        }

        $this->info('  무름 ' . $무름 . '건 · 치울 것 없음 ' . $없음
            . '건 · 계좌 필요 ' . $계좌필요 . '건 · 못함 ' . $못함 . '건');
    }

    /** 「은행코드:계좌번호:예금주」 를 토스가 받는 꼴로 */
    private function 환불계좌(): ?array
    {
        $값 = (string) $this->option('refund-account');

        if ($값 === '') {
            return null;
        }

        $조각 = explode(':', $값);

        if (count($조각) !== 3) {
            $this->error('  --refund-account 는 「은행코드:계좌번호:예금주」 꼴이어야 합니다.');

            return null;
        }

        return ['bank' => $조각[0], 'accountNumber' => $조각[1], 'holderName' => $조각[2]];
    }

    /**
     * 위드웍스 판매주문 지우기.
     *
     * so_delete 는 주문번호로 지운다. 창고가 이미 손을 댄 건(할당ㆍ피킹)은 거절할
     * 수 있는데, 그 거절도 그대로 적는다 — 저쪽에 남았다는 뜻이라 사람이 알아야 한다.
     */
    private function 위드웍스(array $판매, array $반품, bool $정말): void
    {
        $this->line('');
        $this->info('── 위드웍스 판매주문 ──');
        $this->line('  적힌 것 — 판매주문 ' . count($판매) . '건 · 반품주문 ' . count($반품) . '건');

        $주소 = rtrim((string) config('services.demoworks.api_url'), '/');
        $끈   = (string) config('services.demoworks.token');

        if (! $주소 || ! $끈) {
            $this->error('  위드웍스 연동 설정이 없습니다.');

            return;
        }

        /* 여기도 보여 주기일 때는 창고에 직접 물어본다 — 열쇠 파일은 찍어 둔 그때다 */
        if (! $정말) {
            $this->line('  창고에 지금 상태를 물어봅니다 …');

            $남음 = 0;
            $없음 = 0;

            foreach (array_merge(array_keys($판매), array_keys($반품)) as $주문번호) {
                try {
                    $res = Http::withToken($끈)->timeout(10)
                        ->get($주소 . '/api/v1/ce-admin/so_show', ['ce_order_number' => (string) $주문번호]);

                    ($res->successful() && ($res->json('success') ?? false) && $res->json('result'))
                        ? $남음++ : $없음++;
                } catch (\Throwable) {
                    $없음++;
                }
            }

            $this->line('    아직 남은 것 ' . $남음 . '건 · 없는 것 ' . $없음 . '건');

            return;
        }

        /* 판매주문과 반품주문 모두 주문번호를 열쇠로 지운다. 적어 둔 것은
           [주문번호 => 판매주문번호] 꼴이라 열쇠 쪽을 쓴다. */
        $지움     = 0;
        $이미없음 = 0;
        $못함     = 0;

        foreach (['판매주문' => $판매, '반품주문' => $반품] as $무엇 => $것들) {
            foreach ($것들 as $주문번호 => $판매번호) {
                try {
                    $res = Http::withToken($끈)->timeout(20)->asForm()
                        ->delete($주소 . '/api/v1/ce-admin/so_delete', [
                            'ce_order_number' => (string) $주문번호,
                        ]);

                    $몸  = $res->json();
                    $말  = (string) ($몸['message'] ?? $res->status());
                    $됐나 = $res->successful() && ($몸['success'] ?? false);

                    /* 창고가 「찾을 수 없거나 이미 삭제되었다」고 답하면서도 success 를
                       준다. 그것을 「지움」으로 적으면 이번에 지운 것처럼 읽힌다 —
                       무엇이 실제로 사라졌는지 셈이 어긋난다. */
                    $이미 = $됐나 && str_contains($말, '찾을 수 없');

                    $this->line('  ' . ($이미 ? '이미 없음' : ($됐나 ? '지움    ' : '못 지움  '))
                        . ' ' . $무엇 . ' ' . $주문번호 . ' (SO ' . $판매번호 . ') → '
                        . mb_substr($말, 0, 80));

                    if ($이미) {
                        $이미없음++;
                    } elseif ($됐나) {
                        $지움++;
                    } else {
                        $못함++;
                    }
                } catch (\Throwable $e) {
                    $this->error('  못 지움 ' . $무엇 . ' ' . $주문번호 . ' → '
                        . mb_substr($e->getMessage(), 0, 100));
                    $못함++;
                }
            }
        }

        $this->info('  지움 ' . $지움 . '건 · 이미 없던 것 ' . $이미없음
            . '건 · 못함 ' . $못함 . '건');
    }
}
