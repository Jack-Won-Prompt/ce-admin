<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 내보낸 위임장 서명을 받아 담는다 (2026-09-29 지시).
 *
 * delegation-signs:export 가 낸 JSON Lines 를 읽는다. 짝은 **원본의 id** 다 —
 * 그 번호가 위임장 서명 화면의 줄 번호이고, 다시 받아도 두 줄로 서지 않게 한다.
 *
 * **주민등록번호 암호문은 오지 않는다.** 두 서버의 암호화 열쇠가 달라 풀리지 않을
 * 값이라 내보내는 쪽에서 뺐다. 가린 값과 생년월일은 그대로 오고, 화면과 나이ㆍ성년
 * 판정은 그것만 본다.
 *
 * 받는 표에 없는 칸은 버린다 — 두 서버의 마이그레이션이 한 걸음 어긋나 있어도
 * 받다가 멈추지 않게. 무엇을 버렸는지는 세어 보인다.
 */
class DelegationSignsImportCommand extends Command
{
    protected $signature = 'delegation-signs:import
                            {--from= : 읽을 파일. 비우면 표준입력}
                            {--force : 실제로 담는다. 없으면 세어 보이기만 한다}';

    protected $description = '내보낸 위임장 서명을 받아 담는다';

    public function handle(): int
    {
        $정말 = (bool) $this->option('force');
        $손   = $this->option('from') ? fopen($this->option('from'), 'r') : fopen('php://stdin', 'r');

        if (! $손) {
            $this->error('읽을 자리를 열지 못했습니다.');

            return self::FAILURE;
        }

        $있는칸 = Schema::getColumnListing('delegation_signs');
        $버린칸 = [];
        $셈     = ['읽음' => 0, '새로' => 0, '덧씀' => 0, '건너뜀' => 0];
        $담을것 = [];

        while (($줄 = fgets($손)) !== false) {
            $줄 = trim($줄);

            if ($줄 === '') {
                continue;
            }

            $것 = json_decode($줄, true);

            if (! is_array($것) || ! isset($것['id'])) {
                $셈['건너뜀']++;

                continue;
            }

            $셈['읽음']++;

            foreach (array_keys($것) as $칸) {
                if (! in_array($칸, $있는칸, true)) {
                    $버린칸[$칸] = true;
                    unset($것[$칸]);
                }
            }

            $담을것[] = $것;

            if (count($담을것) >= 100) {
                $this->쏟기($담을것, $정말, $셈);
            }
        }

        $this->쏟기($담을것, $정말, $셈);
        fclose($손);

        $this->line('');
        $this->table(['무엇', '몇'], collect($셈)->map(fn ($v, $k) => [$k, number_format($v)])->values()->all());

        if ($버린칸) {
            $this->warn('  받는 표에 없어 버린 칸 — ' . implode(', ', array_keys($버린칸)));
        }

        $this->line('');

        if (! $정말) {
            $this->warn('  세어 보이기만 했습니다. 실제로 담으려면 --force 를 적어 주십시오.');
        } else {
            $this->info('  담았습니다. 지금 ' . number_format(DB::table('delegation_signs')->count()) . '줄입니다.');
        }

        $this->line('');

        return self::SUCCESS;
    }

    /** 모아 둔 것을 한 번에 담는다 — 줄마다 오가면 3,659번 오간다 */
    private function 쏟기(array &$담을것, bool $정말, array &$셈): void
    {
        if ($담을것 === []) {
            return;
        }

        if ($정말) {
            $번호들 = array_column($담을것, 'id');
            $이미   = DB::table('delegation_signs')->whereIn('id', $번호들)->pluck('id')->flip();

            foreach ($담을것 as $것) {
                isset($이미[$것['id']]) ? $셈['덧씀']++ : $셈['새로']++;

                DB::table('delegation_signs')->updateOrInsert(['id' => $것['id']], $것);
            }
        } else {
            $번호들 = array_column($담을것, 'id');
            $이미   = DB::table('delegation_signs')->whereIn('id', $번호들)->count();
            $셈['덧씀'] += $이미;
            $셈['새로'] += count($담을것) - $이미;
        }

        $담을것 = [];
    }
}
