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
     * 수신자가 여럿일 수 있다. 하나라도 보내는 중이면 보내는 중, 모두 성공이어야 성공,
     * 하나라도 실패면 실패로 본다 — 부분 실패를 성공으로 적으면 안 된다.
     */
    private function overallState(array $messages): int
    {
        $states = array_map(fn ($s) => (int) ($s->state ?? 0), $messages);
        $unique = array_unique($states);

        return match (true) {
            in_array(FaxHistory::STATE_SENDING, $states, true)                       => FaxHistory::STATE_SENDING,
            count($unique) === 1 && $states[0] === FaxHistory::STATE_OK              => FaxHistory::STATE_OK,
            in_array(FaxHistory::STATE_FAIL, $states, true)                          => FaxHistory::STATE_FAIL,
            count($unique) === 1 && $states[0] === FaxHistory::STATE_CANCEL           => FaxHistory::STATE_CANCEL,
            default                                                                  => FaxHistory::STATE_WAIT,
        };
    }

    /** 결과코드 — 실패한 수신자의 코드를 먼저 남긴다. 왜 실패했는지가 알고 싶은 값이다. */
    private function resultCode(array $messages): ?int
    {
        $result = null;

        foreach ($messages as $s) {
            if (isset($s->result) && $s->result !== null) {
                $result = (int) $s->result;
                if ((int) ($s->state ?? 0) === FaxHistory::STATE_FAIL) {
                    break;
                }
            }
        }

        return $result;
    }
}
