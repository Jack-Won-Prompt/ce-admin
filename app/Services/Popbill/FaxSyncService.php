<?php

namespace App\Services\Popbill;

use App\Models\FaxHistory;
use Illuminate\Support\Facades\Log;

/**
 * 팩스 전송 결과를 팝빌에서 받아 우리 이력에 적는다.
 *
 * 팩스는 접수와 전송이 갈린다. 접수는 바로 되지만 변환·발신은 몇 분 걸리고, 실패도 그때
 * 드러난다. 우리 표에는 접수 시점 상태만 적혀 있어서, 화면에서 '대기건 동기화'를 누르기
 * 전까지는 실패한 건도 접수 상태로 보였다. 공단 제출 서류라 실패를 늦게 아는 것은 위험하다.
 *
 * 화면 버튼과 스케줄러가 같은 코드를 쓰도록 여기에 모은다.
 */
class FaxSyncService
{
    public function __construct(private readonly FaxService $svc)
    {
    }

    /**
     * 아직 끝나지 않은 건을 팝빌에 물어 상태를 맞춘다.
     *
     * @return array{synced:int, errors:int, checked:int}
     */
    public function syncPending(?string $corpNum = null): array
    {
        $corpNum ??= config('popbill.test.corp_num');

        $pending = FaxHistory::where('corp_num', $corpNum)->pending()->get();
        $synced  = 0;
        $errors  = 0;

        foreach ($pending as $history) {
            try {
                $arr = $this->svc->getMessages($history->corp_num, $history->receipt_num, null);
                if (empty($arr)) {
                    continue;
                }

                $history->update([
                    'popbill_state'  => $this->overallState($arr),
                    'popbill_result' => $this->resultCode($arr),
                    'synced_at'      => now(),
                ]);
                $synced++;
            } catch (\Throwable $e) {
                Log::error('[Fax] 결과 동기화 실패', [
                    'receipt_num' => $history->receipt_num,
                    'error'       => $e->getMessage(),
                ]);
                $errors++;
            }
        }

        return ['synced' => $synced, 'errors' => $errors, 'checked' => $pending->count()];
    }

    /**
     * 한 건만 다시 묻는다 — 팝빌이 알려 왔을 때 그 건만 맞춘다 (2026-09-10 지시).
     *
     * 알려 온 본문의 상태를 그대로 믿지 않는다. 서명 없는 알림이라 위조할 수 있고,
     * 이름도 서비스마다 다르다 — 접수번호만 받아 여기서 다시 묻는다.
     */
    public function syncOne(FaxHistory $history): bool
    {
        try {
            $arr = $this->svc->getMessages($history->corp_num, $history->receipt_num, null);

            if (empty($arr)) {
                return false;
            }

            $history->update([
                'popbill_state'  => $this->overallState($arr),
                'popbill_result' => $this->resultCode($arr),
                'synced_at'      => now(),
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('[Fax] 한 건 결과 동기화 실패', [
                'receipt_num' => $history->receipt_num,
                'error'       => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * 수신자가 여럿일 수 있다 — 한 줄로 줄여 적는다.
     *
     * 팝빌 상태는 진행 순서대로 커진다(0 접수 → 1 변환중 → 2 전송중 → 3 완료).
     * 그래서 **아직 끝나지 않은 줄이 있으면 가장 뒤처진 상태**를 적는다 — 한 곳이라도
     * 진행 중이면 이 건은 진행 중이다. 모두 끝났으면 완료가 하나라도 있으면 완료,
     * 전부 취소면 취소다.
     *
     * **성공ㆍ실패를 여기서 가리지 않는다.** 그것은 결과코드(result)가 정한다 —
     * 완료(3)라도 결과코드가 100 이 아니면 닿지 않았다. 여태 이 자리에서 팝빌 상태를
     * 우리 상수(2 성공 · 3 실패)와 견주었는데 뜻이 서로 달라, 정상 전송된 팩스가
     * 실패로 적혔다 (2026-09-30 실전 시험에서 바로잡음).
     */
    private function overallState(array $messages): int
    {
        $states = array_map(fn ($s) => (int) ($s->state ?? FaxHistory::STATE_RECEIVED), $messages);

        if ($states === []) {
            return FaxHistory::STATE_RECEIVED;
        }

        $진행중 = array_filter($states, fn ($st) => $st < FaxHistory::STATE_DONE);

        if ($진행중 !== []) {
            return min($진행중);
        }

        return in_array(FaxHistory::STATE_DONE, $states, true)
            ? FaxHistory::STATE_DONE
            : FaxHistory::STATE_CANCEL;
    }

    /**
     * 결과코드 — **닿지 못한 수신자의 코드를 먼저** 남긴다.
     *
     * 왜 닿지 않았는지가 알고 싶은 값이다(504 잘못된 수신번호 · 505 응답 없음 …).
     * 여러 곳에 보냈는데 한 곳만 실패했다면 그 코드가 남아야 한다 — 성공 코드(100)를
     * 남기면 부분 실패가 성공으로 읽힌다.
     */
    private function resultCode(array $messages): ?int
    {
        $result = null;

        foreach ($messages as $s) {
            if (! isset($s->result) || $s->result === null) {
                continue;
            }

            $코드 = (int) $s->result;

            if ($코드 !== FaxHistory::RESULT_OK) {
                return $코드;                       // 닿지 않은 것이 있으면 그것으로 적는다
            }

            $result = $코드;
        }

        return $result;
    }
}
