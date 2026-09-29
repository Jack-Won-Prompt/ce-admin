<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 위임장 서명을 다른 서버로 옮기려고 내보낸다 (2026-09-29 지시).
 *
 * 이 표는 운영 실자료인데 시험 서버에만 있었다. 운영 서버로 옮겨야 하는데, 두 서버의
 * **암호화 열쇠(APP_KEY)가 다르다** — 시험 8009337f… · 운영 04d83439…. 그래서 암호문을
 * 그대로 옮기면 운영에서 풀리지 않는다.
 *
 * **주민등록번호 암호문(resident_no)은 그대로 내보내지 않는다** — 저쪽 열쇠로는
 * 풀리지 않는 글자 뭉치일 뿐이다.
 *
 * `--with-rrn` 을 주면 **풀어서 평문으로** 실어 보낸다(2026-09-29 지시). 받는 쪽이
 * 제 열쇠로 다시 잠근다. 푸는 사유는 `backfill_verify` — 설정에 「이관 배치의 왕복
 * 검증」으로 이미 허용된 자리이고, 푼 사실은 줄마다 감사 자취에 남는다.
 *
 * **평문은 디스크에 닿지 않게 쓴다.** 파일로 적지 말고 곧바로 받는 쪽으로 흘려보낸다
 * (export | ssh … import). 중간 파일을 두면 지우는 것을 잊는 순간 그 파일이 남는다.
 *
 * 주지 않으면 가린 값과 생년월일만 간다. 그것만으로도 이 표의 화면과 나이ㆍ성년
 * 판정은 그대로 선다 — 복호화하는 자리가 한 군데도 없기 때문이다.
 *
 * 내보낸 것은 줄마다 한 JSON 이다(JSON Lines). 16MB 를 한 덩이로 싸면 받는 쪽이
 * 통째로 메모리에 들어야 한다 — 줄 단위로 흘리면 그럴 일이 없다.
 */
class DelegationSignsExportCommand extends Command
{
    protected $signature = 'delegation-signs:export
                            {--to= : 적을 파일. 비우면 화면으로 흘린다}
                            {--with-rrn : 주민등록번호를 풀어서 함께 보낸다}';

    protected $description = '위임장 서명을 JSON Lines 로 내보낸다 (주민번호 암호문은 빼고)';

    /** 옮기지 않는 칸 — 저쪽 열쇠로는 풀리지 않는다 */
    private const 뺄칸 = ['resident_no'];

    public function handle(): int
    {
        $주민도 = (bool) $this->option('with-rrn');

        if ($주민도 && $this->option('to')) {
            $this->error('주민등록번호를 파일로 적지 않습니다 — 받는 쪽으로 곧바로 흘려보내십시오.');

            return self::FAILURE;
        }

        $손 = $this->option('to') ? fopen($this->option('to'), 'w') : fopen('php://stdout', 'w');

        $센것 = 0;
        $푼것 = 0;

        DB::table('delegation_signs')->orderBy('id')
            ->chunk(200, function ($줄들) use ($손, &$센것, &$푼것, $주민도) {
                foreach ($줄들 as $줄) {
                    $것   = (array) $줄;
                    $암호문 = $것['resident_no'] ?? null;

                    foreach (self::뺄칸 as $칸) {
                        unset($것[$칸]);
                    }

                    if ($주민도 && $암호문) {
                        try {
                            $평문 = \App\Support\ResidentNo::decrypt($암호문, 'backfill_verify', [
                                'table' => 'delegation_signs', 'id' => $줄->id,
                            ]);

                            if ($평문) {
                                $것['resident_no_plain'] = $평문;
                                $푼것++;
                            }
                        } catch (\Throwable $e) {
                            /* 못 푸는 줄이 있어도 나머지는 옮긴다 — 그 줄만 가린 값으로 간다 */
                            \Illuminate\Support\Facades\Log::warning('[위임장 서명 내보내기] 주민번호를 풀지 못했다', [
                                'id' => $줄->id, 'error' => mb_substr($e->getMessage(), 0, 80),
                            ]);
                        }
                    }

                    fwrite($손, json_encode($것, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
                    $센것++;
                }
            });

        fclose($손);

        if ($this->option('to')) {
            $this->info('내보냈습니다 — ' . number_format($센것) . '줄 · ' . $this->option('to'));
        } elseif ($주민도) {
            fwrite(STDERR, '  주민등록번호를 푼 줄 ' . number_format($푼것) . "\n");
        }

        return self::SUCCESS;
    }
}
