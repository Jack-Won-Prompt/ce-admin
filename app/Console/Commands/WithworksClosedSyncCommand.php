<?php

namespace App\Console\Commands;

use App\Models\Prescription;
use App\Support\WithworksSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 「위드웍스에서 이미 끝난 처방전」을 우리 표에 적는다 (2026-10-06 · SR #78).
 *
 * 저쪽은 처방전과 판매주문을 상담으로 잇는다.
 *
 *   account_add_informations.id  ──→  counsellings.add_id
 *   counsellings.so_id           ──→  sales_orders.id
 *   sales_orders.so_confirm_date = 구매확정일 · erp_cancel_flag = 'Y' 면 취소
 *
 * `sales_orders.add_info_id` 로도 이어질 것 같지만 그 칸은 0.2%만 채워져 있다
 * (299,668건 중 617건 · 2026-10-06 실측). 상담을 타야 한다 — add_id 96.2% ·
 * so_id 91.0% 로 채워져 있다.
 *
 * 한 번 채워 두면 목록은 우리 표만 보면 된다. 저쪽이 바뀌므로 가져오기 뒤에 다시 돈다.
 */
class WithworksClosedSyncCommand extends Command
{
    protected $signature = 'withworks:closed-sync
                            {--all : 이미 적힌 것도 다시 본다 (없으면 아직 안 적힌 것만)}';

    protected $description = '위드웍스에서 이미 구매확정ㆍ취소된 처방전을 표시합니다 (읽기만 가져옵니다)';

    public function handle(): int
    {
        $연결 = WithworksSource::연결(WithworksSource::창고);

        $this->info('── 저쪽에서 마감된 처방전을 모읍니다');

        /* 저쪽 안에서 묶는다 — 번호를 이쪽으로 날라 오면 질의가 터진다 */
        $마감 = $연결->table('counsellings as c')
            ->join('sales_orders as s', 's.id', '=', 'c.so_id')
            ->where(fn ($q) => $q->whereNotNull('s.so_confirm_date')->orWhere('s.erp_cancel_flag', 'Y'))
            ->whereNotNull('c.add_id')->where('c.add_id', '>', 0)
            ->groupBy('c.add_id')
            ->selectRaw('c.add_id, MAX(s.so_no) so_no, MAX(s.so_confirm_date) 확정일,'
                      . ' MAX(CASE WHEN s.erp_cancel_flag = ? THEN 1 ELSE 0 END) 취소', ['Y'])
            ->get()
            ->keyBy('add_id');

        $this->line('   마감된 처방전 ' . number_format($마감->count()) . '장');

        $질의 = Prescription::whereNotNull('ww_add_id');

        if (! $this->option('all')) {
            $질의->whereNull('ww_closed_at')->where('ww_cancelled', false);
        }

        $볼것 = $질의->count();
        $this->line('   우리 처방전 ' . number_format($볼것) . '장을 봅니다');

        $적음 = 0;

        $질의->select('id', 'ww_add_id')->chunkById(2000, function ($묶음) use ($마감, &$적음) {
            $고칠것 = [];

            foreach ($묶음 as $p) {
                $r = $마감->get((int) $p->ww_add_id);
                if (! $r) { continue; }

                $고칠것[] = [
                    'id'           => $p->id,
                    'ww_so_no'     => $r->so_no ?: null,
                    'ww_closed_at' => $r->확정일 ?: null,
                    'ww_cancelled' => (bool) $r->취소,
                ];
            }

            foreach (array_chunk($고칠것, 500) as $조각) {
                DB::table('prescriptions')->upsert(
                    $조각, ['id'], ['ww_so_no', 'ww_closed_at', 'ww_cancelled']
                );
                $적음 += count($조각);
            }

            $this->output->write("\r   적음 " . number_format($적음) . '장      ');
        });

        $this->newLine();

        $끝남 = Prescription::whereNotNull('ww_add_id')
            ->where(fn ($q) => $q->whereNotNull('ww_closed_at')->orWhere('ww_cancelled', true))->count();
        $남음 = Prescription::whereNotNull('ww_add_id')
            ->whereNull('ww_closed_at')->where('ww_cancelled', false)->count();

        $this->info(sprintf('── 끝: 이번에 %s장 적음 · 마감 %s장 · 아직 쓸 수 있는 것 %s장',
            number_format($적음), number_format($끝남), number_format($남음)));

        Log::info('[위드웍스 마감표시] 끝', ['적음' => $적음, '마감' => $끝남, '남음' => $남음]);

        return self::SUCCESS;
    }
}
