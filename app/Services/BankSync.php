<?php

namespace App\Services;

use App\Models\BankTransaction;
use App\Services\Popbill\EasyFinBankService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * 통장 거래내역을 팝빌에서 긁어 온다 (요청서 5쪽, 2026-08-31).
 *
 * 팝빌은 두 걸음으로 준다 — 「모아 달라」 하고(RequestJob) 다 모이면 읽는다(Search).
 * 은행에서 긁어 오는 데 시간이 걸려 한 번에 돌려주지 않는다. 그래서 작업번호를 받아 두고
 * 상태를 물어보며 기다린다.
 *
 * 두 가지로 쓴다.
 *   - 화면에서 「지금 가져오기」 — 눌러서 기다렸다 받는다(pull). 오늘 것을 곧바로 본다.
 *   - 서른 분마다 도는 훑기(bank:sync) — 눌러 주는 사람이 없어도 맞춰지는 그물.
 *
 * 다시 긁어도 같은 줄이 쌓이지 않는다. 팝빌이 주는 거래번호(tid)로 겹침을 막고,
 * 담당자가 맞춰 둔 것(order_id·kind·메모)은 덮지 않는다 — 훑기 한 번에 그 일이
 * 지워지면 매번 다시 해야 한다.
 */
class BankSync
{
    /** 다 모일 때까지 기다리는 최대 시간(초) — 화면에서 누른 사람이 있을 때만 기다린다 */
    private const WAIT_SECONDS = 25;

    public function __construct(private readonly EasyFinBankService $api) {}

    public function configured(): bool
    {
        return (bool) (config('popbill.LinkID')
            && config('bank.account.number')
            && config('bank.account.bank_code'));
    }

    /**
     * 그 기간을 긁어 표에 담는다.
     *
     * @param bool $wait 다 모일 때까지 기다릴지. 화면에서 누른 것이면 기다리고,
     *                   스케줄이면 기다리지 않는다 — 다음 차례에 읽으면 된다.
     *
     * @return array{ok: bool, saved: int, message: string}
     */
    public function pull(?Carbon $from = null, ?Carbon $to = null, bool $wait = true): array
    {
        if (!$this->configured()) {
            return ['ok' => false, 'saved' => 0, 'message' => '계좌조회 설정이 없습니다.'];
        }

        $from ??= today()->subDays(7);
        $to   ??= today();

        $corpNum = (string) config('popbill.test.corp_num');
        $bank    = (string) config('bank.account.bank_code');
        $acct    = (string) config('bank.account.number');

        try {
            $jobId = $this->api->requestJob(
                $corpNum, $bank, $acct, $from->format('Ymd'), $to->format('Ymd')
            );
        } catch (\Throwable $e) {
            Log::warning('[계좌조회] 수집 요청 실패', ['error' => $e->getMessage()]);

            return ['ok' => false, 'saved' => 0, 'message' => '수집을 요청하지 못했습니다 — ' . $e->getMessage()];
        }

        if (!$this->ready($corpNum, $jobId, $wait)) {
            /* 아직 모으는 중이다. 실패가 아니라 「이번엔 못 읽었다」이므로 다음 차례가
               같은 기간을 다시 걸어 읽는다 — 작업번호를 들고 있을 곳이 없다. */
            return ['ok' => true, 'saved' => 0, 'message' => '아직 모으는 중입니다 — 잠시 뒤 다시 봅니다.'];
        }

        return $this->readAll($corpNum, $jobId);
    }

    /** 다 모였는가 — 기다리라면 잠깐씩 다시 물어본다 */
    private function ready(string $corpNum, string $jobId, bool $wait): bool
    {
        $until = time() + ($wait ? self::WAIT_SECONDS : 0);

        do {
            try {
                $state = $this->api->jobState($corpNum, $jobId);
            } catch (\Throwable $e) {
                Log::warning('[계좌조회] 상태 조회 실패', ['job' => $jobId, 'error' => $e->getMessage()]);

                return false;
            }

            if ((int) ($state->jobState ?? 0) === EasyFinBankService::JOB_DONE) {
                // 다 모였는데 오류가 있으면 읽을 것이 없다
                if (!empty($state->errorCode)) {
                    Log::warning('[계좌조회] 수집 오류', [
                        'job' => $jobId, 'code' => $state->errorCode, 'reason' => $state->errorReason,
                    ]);

                    return false;
                }

                return true;
            }

            if (time() >= $until) {
                return false;
            }

            sleep(2);
        } while (true);
    }

    /** 페이지를 끝까지 읽어 담는다 */
    private function readAll(string $corpNum, string $jobId): array
    {
        $saved = 0;
        $page  = 1;

        do {
            try {
                $res = $this->api->search($corpNum, $jobId, [], null, $page, 500);
            } catch (\Throwable $e) {
                Log::warning('[계좌조회] 내역 조회 실패', ['job' => $jobId, 'error' => $e->getMessage()]);

                return ['ok' => false, 'saved' => $saved, 'message' => '내역을 읽지 못했습니다 — ' . $e->getMessage()];
            }

            foreach ($res->list ?? [] as $row) {
                $saved += $this->store($row) ? 1 : 0;
            }

            $pages = (int) ($res->pageCount ?? 1);
        } while (++$page <= $pages);

        return ['ok' => true, 'saved' => $saved, 'message' => "거래 {$saved}건을 받았습니다."];
    }

    /**
     * 한 줄을 담는다 — 은행이 준 칸만 덮는다.
     *
     * 담당자가 맞춰 둔 것(order_id·kind·메모)은 건드리지 않는다. 30분마다 도는 훑기에
     * 그 일이 지워지면 매번 다시 해야 한다.
     */
    private function store(object $row): bool
    {
        $tid = (string) ($row->tid ?? '');

        if ($tid === '') {
            return false;
        }

        $money = fn ($v) => (int) preg_replace('/[^\d-]/', '', (string) ($v ?? '0'));

        BankTransaction::updateOrCreate(['tid' => $tid], [
            'bank_code'      => config('bank.account.bank_code'),
            'account_number' => config('bank.account.number'),
            'trade_date'     => $this->date($row->trdate ?? null),
            'traded_at'      => $this->dateTime($row->trdt ?? null),
            'trade_serial'   => $row->trserial ?? null,
            'amount_in'      => $money($row->accIn ?? 0),
            'amount_out'     => $money($row->accOut ?? 0),
            'balance'        => $money($row->balance ?? 0),
            'remark1'        => $this->fit($row->remark1 ?? null),
            'remark2'        => $this->fit($row->remark2 ?? null),
            'remark3'        => $this->fit($row->remark3 ?? null),
            'remark4'        => $this->fit($row->remark4 ?? null),
            'bank_memo'      => $this->fit($row->memo ?? null, 500),
        ]);

        return true;
    }

    /** yyyyMMdd */
    private function date(?string $v): ?string
    {
        return $v && strlen($v) >= 8
            ? substr($v, 0, 4) . '-' . substr($v, 4, 2) . '-' . substr($v, 6, 2)
            : null;
    }

    /** yyyyMMddHHmmss */
    private function dateTime(?string $v): ?string
    {
        if (!$v || strlen($v) < 8) {
            return null;
        }

        $d = $this->date($v);
        $t = strlen($v) >= 14
            ? substr($v, 8, 2) . ':' . substr($v, 10, 2) . ':' . substr($v, 12, 2)
            : '00:00:00';

        return $d . ' ' . $t;
    }

    /** 칸에 들어갈 만큼만 — 남이 보내는 값이라 무엇이 올지 우리가 정하지 않는다 */
    private function fit(?string $v, int $max = 200): ?string
    {
        return ($v === null || $v === '') ? null : mb_substr($v, 0, $max);
    }
}
