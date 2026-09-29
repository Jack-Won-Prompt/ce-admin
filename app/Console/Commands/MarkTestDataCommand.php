<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 딱지 없는 줄에 시험 딱지를 붙인다 (2026-09-29).
 *
 * 처음에는 칸을 만드는 마이그레이션이 이 일까지 했다. 시험 서버에서는 맞는
 * 말이었지만, 같은 마이그레이션이 **운영에서 돌면 실제 환자ㆍ주문ㆍ처방전에
 * 시험 딱지가 붙는다** — 그러면 test-data:purge 의 지울 대상이 된다.
 * 마이그레이션은 어디서 돌아도 안전해야 하므로 여기로 옮겼다.
 *
 * **일부러 부르는 명령이다.** 어느 서버에서 무엇에 붙이는지 사람이 보고 누른다.
 * 붙이기 전에 어느 DB 인지 적어 보이고, --force 가 없으면 아무것도 쓰지 않는다.
 */
class MarkTestDataCommand extends Command
{
    protected $signature = 'test-data:mark
                            {--batch= : 묶음 이름. 비우면 오늘 날짜로 짓는다}
                            {--force : 실제로 붙인다. 없으면 세어 보이기만 한다}';

    protected $description = '딱지 없는 줄에 시험 딱지(data_origin=test)를 붙인다';

    /** 딱지를 붙일 표 — 칸을 만든 마이그레이션과 같은 목록이다 */
    private const 표들 = ['patients', 'prescriptions', 'orders', 'order_returns', 'sample_orders'];

    public function handle(): int
    {
        $정말 = (bool) $this->option('force');
        $묶음 = $this->option('batch') ?: ('test-' . now()->format('Y-m-d'));

        $this->line('');
        $this->info('══ 시험 딱지 붙이기 ' . ($정말 ? '(실제로 붙입니다)' : '(세어 보이기만 합니다)') . ' ══');

        /* 어느 DB 인지 먼저 적는다 — 운영에 붙이면 실제 자료가 지울 대상이 된다.
           명령 하나로 그 일이 일어나지 않도록 사람이 보고 누르게 한다. */
        $this->line('  DB   : ' . config('database.connections.' . config('database.default') . '.database')
            . ' @ ' . config('database.connections.' . config('database.default') . '.host'));
        $this->line('  묶음 : ' . $묶음);
        $this->line('');

        $셈 = [];

        foreach (self::표들 as $표) {
            if (! Schema::hasTable($표) || ! Schema::hasColumn($표, 'data_origin')) {
                continue;
            }

            $빈것 = DB::table($표)->whereNull('data_origin')->count();
            $전체 = DB::table($표)->count();

            $셈[] = [$표, number_format($전체), number_format($빈것)];

            if ($정말 && $빈것 > 0) {
                DB::table($표)->whereNull('data_origin')
                    ->update(['data_origin' => 'test', 'data_batch' => $묶음]);
            }
        }

        $this->table(['표', '전체', '딱지 없는 줄'], $셈);
        $this->line('');

        if (! $정말) {
            $this->warn('  세어 보이기만 했습니다. 실제로 붙이려면 --force 를 적어 주십시오.');
            $this->line('  **운영 DB 에는 붙이지 마십시오** — 붙는 순간 실제 자료가 지울 대상이 됩니다.');
        } else {
            $this->info('  붙였습니다. 지우려면 test-data:purge --batch=' . $묶음 . ' 를 쓰십시오.');
        }

        $this->line('');

        return self::SUCCESS;
    }
}
