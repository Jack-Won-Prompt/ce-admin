<?php

namespace App\Console\Commands;

use App\Support\WithworksSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 위드웍스 판매주문의 품목을 우리 처방 품목으로 옮긴다 (2026-09-29 지시).
 *
 * ## 왜
 *
 * 처방전만 옮기고 품목을 옮기지 않아, 주문 등록 화면의 「주문 제품」 탭이 비어 있었다.
 * 그 탭은 `prescription_items` 를 읽고, 없으면 그 처방전의 주문 품목을 읽는데 — 옮긴
 * 건은 둘 다 없다(주문 줄은 담당자가 유형을 고를 때 선다).
 *
 * ## 어떻게 이어지나
 *
 * 저쪽 목록 화면(`AccountAddInfoController`)이 쓰는 그 길 그대로다 —
 *
 * ```
 * account_add_informations  →  counsellings.add_id
 *                           →  counsellings.so_id  →  sales_order_details.so_id
 *                                                  →  items.id (품번ㆍ품명)
 * ```
 *
 * **상담을 먼저 묶는다.** 한 처방전에 상담이 여러 줄인 건이 있다(최대 153줄). 모두 같은
 * 판매주문을 가리키므로, 그냥 이으면 품목이 부풀어 한 처방전에 3,952줄이 붙었다.
 * `DISTINCT add_id, so_id` 로 묶으면 64,506 → 60,580줄이 된다. 한 처방전에 붙는 판매주문은
 * **하나뿐**임을 확인했다(2026-09-29).
 *
 * ## 수량
 *
 * 확정(상태 95)은 `confirm_qty`, 등록만 된 것(02)은 그 칸이 비어 `request_qty` 를 쓴다
 * — 최근 3년 6만 줄 가운데 9,867줄이 그렇다.
 *
 * ## 담지 않는 것 — 돈
 *
 * `product_price`(단가)만 담는다. 우리 `insurance_price`(보험가)ㆍ`nhis_amount`(공단 부담)ㆍ
 * `patient_copay`(본인 부담)에 해당하는 칸이 저쪽에 무엇인지 확정할 근거가 없다
 * (`ckl_supply_price`ㆍ`ckl_consumer_price`ㆍ`a1` 이 있으나 뜻이 우리 것과 같다는 근거가
 * 없다). **짐작해 채우지 않는다** — 그 셋은 담당자가 청구전략을 고르면 우리 로직이 셈한다.
 *
 * ## 조심할 것
 *
 * 여기 담기는 품목은 **이미 판 것**이다. 옮긴 처방전을 열면 그것이 주문 제품 탭의 기본값이
 * 되므로, 그대로 「주문 생성 및 연계」를 누르면 같은 물건이 다시 나간다. 이른 재구매는
 * `RepurchaseWindow` 가 막지만 그것만 믿을 일은 아니다.
 *
 * **다시 돌려도 겹치지 않는다** — `ww_sod_id` 에 유일 색인이 있다.
 */
class MigratePrescriptionItemsFromWithworksCommand extends Command
{
    protected $signature = 'prescription-items:migrate-from-ww
                            {--force : 실제로 옮긴다. 없으면 세어 보이기만 한다}
                            {--limit= : 몇 줄만 시험 삼아}';

    protected $description = '위드웍스 판매주문 품목을 우리 처방 품목으로 옮깁니다 (읽기만 합니다)';

    public function handle(): int
    {
        $정말 = (bool) $this->option('force');

        if (! Schema::hasColumn('prescription_items', 'ww_sod_id')) {
            $this->error('prescription_items.ww_sod_id 가 없습니다 — 마이그레이션을 먼저 돌려 주십시오.');

            return self::FAILURE;
        }

        $this->line('');
        $this->info('══ 위드웍스 판매주문 품목 → 우리 처방 품목 '
            . ($정말 ? '(실제로 옮깁니다)' : '(세어 보이기만 합니다)') . ' ══');
        $this->line('  원천 : warehouse.sales_order_details (읽기만 합니다)');
        $this->line('');

        /* 옮겨 둔 처방전 — 원천 번호로 찾는다 */
        $우리처방전 = DB::table('prescriptions')->whereNotNull('ww_add_id')
            ->pluck('id', 'ww_add_id');

        if ($우리처방전->isEmpty()) {
            $this->error('옮겨 둔 처방전이 없습니다 — prescriptions:migrate-from-ww 를 먼저 돌려 주십시오.');

            return self::FAILURE;
        }

        $this->line('  옮겨 둔 처방전 ' . number_format($우리처방전->count()) . '장');

        $이미 = DB::table('prescription_items')->whereNotNull('ww_sod_id')
            ->pluck('ww_sod_id')->flip();
        $this->line('  이미 옮긴 품목 ' . number_format($이미->count()) . '줄');

        $창고 = WithworksSource::연결(WithworksSource::창고);

        $셈 = ['모두' => 0, '새로' => 0, '이미있음' => 0, '처방전없음' => 0,
               '수량없음' => 0, '품목없음' => 0];
        $보기 = [];
        $담을것 = [];
        $줄번호 = [];      // 처방전마다 몇째 줄인가

        /* 저쪽 표가 크다 — 우리가 가진 처방전 번호를 나눠 물어본다 */
        foreach (array_chunk($우리처방전->keys()->all(), 1000) as $묶음) {
            $줄들 = $창고->table('account_add_informations as p')
                /* 상담을 먼저 묶는다 — 한 처방전에 상담이 여러 줄이면 품목이 부푼다 */
                ->join(DB::raw('(SELECT DISTINCT add_id, so_id FROM counsellings
                                 WHERE deleted_at IS NULL AND so_id IS NOT NULL) cs'),
                       'cs.add_id', '=', 'p.id')
                ->join('sales_order_details as sod', function ($j) {
                    $j->on('sod.so_id', '=', 'cs.so_id')->whereNull('sod.deleted_at');
                })
                ->leftJoin('items as it', 'it.id', '=', 'sod.item_id')
                ->whereNull('p.deleted_at')
                ->whereIn('p.id', $묶음)
                ->orderBy('p.id')->orderBy('sod.id')
                ->get([
                    'p.id as add_id', 'sod.id as sod_id',
                    'sod.confirm_qty', 'sod.request_qty', 'sod.unit_price',
                    'it.item_code', 'it.item_name',
                ]);

            foreach ($줄들 as $r) {
                $셈['모두']++;

                if (isset($이미[$r->sod_id])) {
                    $셈['이미있음']++;

                    continue;
                }

                $처방전 = $우리처방전[$r->add_id] ?? null;

                if (! $처방전) {
                    $셈['처방전없음']++;

                    continue;
                }

                /* 확정 수량이 먼저다. 등록만 된 건은 그 칸이 비어 요청 수량을 쓴다. */
                $수량 = (int) ($r->confirm_qty ?: $r->request_qty);

                if ($수량 <= 0) {
                    $셈['수량없음']++;

                    continue;
                }

                $이름 = trim((string) $r->item_name);

                if ($이름 === '') {
                    $셈['품목없음']++;

                    continue;
                }

                $셈['새로']++;
                $줄번호[$처방전] = ($줄번호[$처방전] ?? -1) + 1;

                if (count($보기) < 5) {
                    $보기[] = [$r->add_id, $처방전, mb_substr($이름, 0, 30),
                        $r->item_code, number_format($수량),
                        number_format((float) $r->unit_price)];
                }

                if (! $정말) {
                    continue;
                }

                $담을것[] = [
                    'ww_sod_id'       => $r->sod_id,
                    'prescription_id' => $처방전,
                    'product_name'    => mb_substr($이름, 0, 255),
                    'product_code'    => mb_substr(trim((string) $r->item_code), 0, 255) ?: null,
                    'quantity'        => $수량,
                    'product_price'   => round((float) $r->unit_price, 2),
                    /* 보험가ㆍ공단 부담ㆍ본인 부담은 담지 않는다 — 위 설명 참고 */
                    'insurance_price' => null,
                    'nhis_amount'     => null,
                    'patient_copay'   => null,
                    'sort_order'      => $줄번호[$처방전],
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ];

                if (count($담을것) >= 500) {
                    DB::table('prescription_items')->insert($담을것);
                    $담을것 = [];
                }

                if ($한도 = $this->option('limit')) {
                    if ($셈['새로'] >= (int) $한도) {
                        break 2;
                    }
                }
            }
        }

        if ($정말 && $담을것 !== []) {
            DB::table('prescription_items')->insert($담을것);
        }

        $this->line('');
        $this->table(['원천 #', '처방전 #', '제품', '품번', '수량', '단가'], $보기);
        $this->line('');
        $this->table(['무엇', '몇'], collect($셈)->map(fn ($v, $k) => [$k, number_format($v)])->values()->all());
        $this->line('');

        if (! $정말) {
            $this->warn('  세어 보이기만 했습니다. 실제로 옮기려면 --force 를 적어 주십시오.');
        } else {
            $this->info('  옮겼습니다. 처방 품목이 지금 '
                . number_format(DB::table('prescription_items')->count()) . '줄입니다.');
        }

        $this->line('  보험가ㆍ공단 부담ㆍ본인 부담은 담지 않았습니다 — 청구전략을 고르면 셈합니다.');
        $this->line('  counsellings · sales_order_details · items 는 읽기만 했습니다.');
        $this->line('');

        return self::SUCCESS;
    }
}
