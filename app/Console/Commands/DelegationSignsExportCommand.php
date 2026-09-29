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
 * **주민등록번호 암호문(resident_no)은 내보내지 않는다.**
 *
 * 그 칸은 이 표에서 **읽히지 않는다** — DelegationSign 은 화면에 적는 주민번호도
 * (주민번호()), 나이도, 성년 판정도 모두 **가린 값(resident_no_masked)** 과
 * birth_date 로 한다. 복호화하는 자리가 한 군데도 없다. 그러니 가린 값과 생년월일만
 * 옮기면 화면과 판정은 그대로 서고, 풀리지 않을 암호문을 운영에 쌓지 않는다.
 *
 * 평문 주민번호를 꺼내 옮기는 길도 있지만 그러지 않는다 — 옮기는 동안 그것이 어딘가에
 * 남고, 지금 쓰이지도 않는 값을 위해 질 위험이 아니다.
 *
 * 내보낸 것은 줄마다 한 JSON 이다(JSON Lines). 16MB 를 한 덩이로 싸면 받는 쪽이
 * 통째로 메모리에 들어야 한다 — 줄 단위로 흘리면 그럴 일이 없다.
 */
class DelegationSignsExportCommand extends Command
{
    protected $signature = 'delegation-signs:export
                            {--to= : 적을 파일. 비우면 화면으로 흘린다}';

    protected $description = '위임장 서명을 JSON Lines 로 내보낸다 (주민번호 암호문은 빼고)';

    /** 옮기지 않는 칸 — 저쪽 열쇠로는 풀리지 않는다 */
    private const 뺄칸 = ['resident_no'];

    public function handle(): int
    {
        $손 = $this->option('to') ? fopen($this->option('to'), 'w') : fopen('php://stdout', 'w');

        $센것 = 0;

        DB::table('delegation_signs')->orderBy('id')->chunk(200, function ($줄들) use ($손, &$센것) {
            foreach ($줄들 as $줄) {
                $것 = (array) $줄;

                foreach (self::뺄칸 as $칸) {
                    unset($것[$칸]);
                }

                fwrite($손, json_encode($것, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
                $센것++;
            }
        });

        fclose($손);

        if ($this->option('to')) {
            $this->info('내보냈습니다 — ' . number_format($센것) . '줄 · ' . $this->option('to'));
        }

        return self::SUCCESS;
    }
}
