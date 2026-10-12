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
 *
 * ── 출고 상태도 함께 가져온다 (2026-10-12 지시 「출고 상태 이관 범위이고 우리에게 적용」)
 *
 *   sales_orders.id ──→ schedule_by_ships.so_id   ship_no · ship_complete_date · status
 *                   ──→ trackings.so_id            tracking_no
 *
 * 상태 코드는 우리 웹훅이 쓰는 것과 같은 자리다 — 95 출고완료 · 02 신규 · 17 할당완료
 * (운영 주문 208건에서 확인). 모르는 코드는 꾸미지 않고 숫자로 적는다.
 *
 * ── **이관 기준일까지만 가져온다** (2026-10-12 지시 「2026-09-30 일자 이후는 가져오지 않음」)
 *
 * 2026-10-01 부터는 CEadmin 이 정본이다. 그 뒤에 저쪽에서 선 판매ㆍ출고를 끌어오면
 * 우리 주문과 두 벌이 되어, 어느 쪽이 사실인지 화면에서 가를 수 없다. 그래서 확정일ㆍ
 * 출고완료일이 기준일을 넘는 줄은 아예 모으지 않는다.
 */
class WithworksClosedSyncCommand extends Command
{
    /**
     * 이관 범위의 끝 — 이 날까지만 가져온다 (2026-10-12 지시).
     *
     * 2026-10-01 부터는 CEadmin 이 정본이라 저쪽 값을 끌어오지 않는다. 날을 바꿔
     * 돌려 볼 일이 있으면 `--until=` 로 준다.
     */
    public const 기준일 = '2026-09-30';

    protected $signature = 'withworks:closed-sync
                            {--all : 이미 적힌 것도 다시 본다 (없으면 아직 안 적힌 것만)}
                            {--until= : 이 날까지만 가져온다 (없으면 이관 기준일 2026-09-30)}';

    protected $description = '위드웍스에서 이미 구매확정ㆍ취소ㆍ출고된 처방전을 표시합니다 (읽기만 가져옵니다)';

    public function handle(): int
    {
        $연결 = WithworksSource::연결(WithworksSource::창고);

        $까지 = (string) ($this->option('until') ?: self::기준일);

        if (! preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $까지)) {
            $this->error('--until 은 YYYY-MM-DD 로 주십시오.');

            return self::FAILURE;
        }

        $this->info("── 저쪽에서 마감ㆍ출고된 처방전을 모읍니다 (이관 기준일 {$까지} 까지)");

        /* 저쪽 안에서 묶는다 — 번호를 이쪽으로 날라 오면 질의가 터진다.

           **기준일을 넘는 줄은 모으지 않는다.** 확정일이 비어 있고 취소만 선 줄은
           취소한 날이 저쪽에 따로 없어, 판매주문을 세운 날(so_date)로 가린다. */
        $마감 = $연결->table('counsellings as c')
            ->join('sales_orders as s', 's.id', '=', 'c.so_id')
            ->where(fn ($q) => $q
                ->where(fn ($w) => $w->whereNotNull('s.so_confirm_date')
                                     ->whereDate('s.so_confirm_date', '<=', $까지))
                ->orWhere(fn ($w) => $w->where('s.erp_cancel_flag', 'Y')
                                       ->whereDate('s.so_date', '<=', $까지)))
            ->whereNotNull('c.add_id')->where('c.add_id', '>', 0)
            ->groupBy('c.add_id')
            ->selectRaw('c.add_id, MAX(s.so_no) so_no, MAX(s.so_confirm_date) 확정일,'
                      . ' MAX(CASE WHEN s.erp_cancel_flag = ? THEN 1 ELSE 0 END) 취소,'
                      . ' MAX(s.id) so_id', ['Y'])
            ->get()
            ->keyBy('add_id');

        $this->line('   마감된 처방전 ' . number_format($마감->count()) . '장');

        /* ── 출고 — 판매주문 번호로 한 번에 모은다 (2026-10-12 지시).

           출고완료일도 기준일로 묶는다. 저쪽에서 10월에 나간 것을 우리 이관 자료에
           적으면, 10-01 부터 CEadmin 이 정본이라는 것과 어긋난다.

           한 판매주문에 출고 지시가 둘 이상인 건(부분 출고)이 있다 — 마지막 것을
           쓴다. 먼저 나간 것을 적으면 「아직 안 나갔다」로 읽힌다. */
        $soIds = $마감->pluck('so_id')->filter()->values()->all();
        $출고   = collect();

        if ($soIds) {
            foreach (array_chunk($soIds, 5000) as $조각) {
                $출고 = $출고->merge(
                    $연결->table('schedule_by_ships')
                        ->whereIn('so_id', $조각)
                        ->where(fn ($q) => $q->whereNull('ship_complete_date')
                                             ->orWhereDate('ship_complete_date', '<=', $까지))
                        ->groupBy('so_id')
                        ->selectRaw('so_id, MAX(ship_no) ship_no, MAX(ship_complete_date) 출고일,'
                                  . ' MAX(status) 상태')
                        ->get()
                );
            }
        }

        $출고 = $출고->keyBy('so_id');
        $this->line('   출고 지시가 붙은 판매주문 ' . number_format($출고->count()) . '건');

        $운송 = collect();

        if ($soIds) {
            foreach (array_chunk($soIds, 5000) as $조각) {
                $운송 = $운송->merge(
                    $연결->table('trackings')->whereIn('so_id', $조각)
                        ->groupBy('so_id')
                        ->selectRaw('so_id, MAX(tracking_no) tracking_no')
                        ->get()
                );
            }
        }

        $운송 = $운송->keyBy('so_id');
        $this->line('   운송장이 붙은 판매주문 ' . number_format($운송->count()) . '건');

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

                $sh = $출고->get((int) $r->so_id);
                $tr = $운송->get((int) $r->so_id);

                $고칠것[] = [
                    'id'             => $p->id,
                    'ww_so_no'       => $r->so_no ?: null,
                    'ww_closed_at'   => $r->확정일 ?: null,
                    'ww_cancelled'   => (bool) $r->취소,
                    'ww_ship_no'     => $sh?->ship_no ?: null,
                    'ww_ship_status' => ($sh && $sh->상태 !== null && $sh->상태 !== '') ? (string) $sh->상태 : null,
                    'ww_shipped_at'  => $sh?->출고일 ?: null,
                    'ww_tracking_no' => $tr?->tracking_no ?: null,
                ];
            }

            /* **upsert 를 쓰지 않는다** (2026-10-06).

               이미 있는 줄을 고치는 일인데 upsert 는 INSERT 를 먼저 세운다 —
               `rx_number` 처럼 기본값이 없는 칸에서 「doesn't have a default value」로
               막힌다. 한 줄씩 고치면 8만 번을 오가므로, 묶음마다 CASE 로 한 번에 고친다. */
            foreach (array_chunk($고칠것, 500) as $조각) {
                $ids = array_column($조각, 'id');
                $값  = [];
                $식  = [];

                foreach (['ww_so_no', 'ww_closed_at', 'ww_cancelled',
                          'ww_ship_no', 'ww_ship_status', 'ww_shipped_at', 'ww_tracking_no'] as $칸) {
                    $조각들 = [];
                    foreach ($조각 as $줄) {
                        $조각들[] = 'WHEN ? THEN ?';
                        $값[] = $줄['id'];
                        $값[] = $줄[$칸];
                    }
                    $식[] = $칸 . ' = CASE id ' . implode(' ', $조각들) . ' END';
                }

                DB::update(
                    'UPDATE prescriptions SET ' . implode(', ', $식)
                    . ' WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')',
                    array_merge($값, $ids)
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

        $출고적음 = Prescription::whereNotNull('ww_shipped_at')->count();

        $this->info(sprintf('── 끝: 이번에 %s장 적음 · 마감 %s장 · 출고완료 %s장 · 아직 쓸 수 있는 것 %s장',
            number_format($적음), number_format($끝남), number_format($출고적음), number_format($남음)));

        Log::info('[위드웍스 마감표시] 끝', [
            '기준일' => $까지, '적음' => $적음, '마감' => $끝남,
            '출고완료' => $출고적음, '남음' => $남음,
        ]);

        return self::SUCCESS;
    }
}
