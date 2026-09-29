<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 운영 고객의 주소를 거래처 주소 이력으로 옮긴다 (2026-09-29 지시).
 *
 * 거래처를 옮길 때는 **대표 주소 한 벌**만 담았다(address_id 가 가리키는 줄).
 * 이사한 뒤에도 지난 주문이 어디로 갔는지 되짚어야 하고, 같은 사람이 집과 직장을
 * 번갈아 쓰기도 한다 — 그래서 있는 주소를 모두 옮긴다.
 *
 * 환자((E))에게 붙는 주소만 옮긴다. 기관 계정에는 주소가 수천 개씩 달려 있는데
 * 그것은 거래처 이력이 아니라 배송지 목록이다. 환자만 보면 한 사람에 평균 1.73개ㆍ
 * 가장 많은 사람이 17개다(2026-09-29 확인).
 *
 * **다시 돌려도 겹치지 않는다** — 원천 번호(ww_address_id)에 유일 색인이 있다.
 *
 * 차례는 원천의 번호 순이다. 이력 화면은 나중에 선 줄을 최신으로 읽으므로, 뒤죽박죽
 * 넣으면 「지금 주소」가 옛 주소로 뒤집힌다.
 */
class MigrateAddressesFromWithworksCommand extends Command
{
    protected $signature = 'addresses:migrate-from-ww
                            {--force : 실제로 옮긴다. 없으면 세어 보이기만 한다}
                            {--limit= : 몇 줄만 시험 삼아}';

    protected $description = '운영 고객의 주소를 거래처 주소 이력으로 옮긴다';

    public function handle(): int
    {
        $정말 = (bool) $this->option('force');

        if (! Schema::hasColumn('patient_addresses', 'ww_address_id')) {
            $this->error('patient_addresses.ww_address_id 가 없습니다 — 마이그레이션을 먼저 돌려 주십시오.');

            return self::FAILURE;
        }

        $this->line('');
        $this->info('══ 운영 고객 주소 → 거래처 주소 이력 '
            . ($정말 ? '(실제로 옮깁니다)' : '(세어 보이기만 합니다)') . ' ══');
        $this->line('  원천 : ww_customer_addresses (읽기만 합니다)');
        $this->line('');

        /* 운영 고객 번호 → 거래처 번호. 한 번에 읽어 둔다 — 줄마다 물으면 이만 번 오간다. */
        $거래처 = DB::table('patients')->whereNotNull('ww_account_id')
            ->pluck('id', 'ww_account_id')->all();

        $this->line('  이어진 거래처 ' . number_format(count($거래처)) . '명');

        /* 이미 옮겨 둔 원천 번호 — 다시 돌릴 때 건너뛴다 */
        $이미 = DB::table('patient_addresses')->whereNotNull('ww_address_id')
            ->pluck('ww_address_id')->flip();

        $질의 = DB::table('ww_customer_addresses as a')
            ->join('ww_customers as c', 'c.ww_id', '=', 'a.account_id')
            ->whereRaw("c.account_name LIKE '(E)%'")
            ->whereNull('c.deleted_at')
            ->whereNull('a.deleted_at')
            ->orderBy('a.ww_id')
            ->select([
                'a.ww_id', 'a.account_id', 'a.zipcode',
                'a.address_line_1', 'a.address_line_2', 'a.created_at', 'a.updated_at',
            ]);

        if ($한도 = $this->option('limit')) {
            $질의->limit((int) $한도);
        }

        $셈 = ['모두' => 0, '새로' => 0, '이미있음' => 0, '거래처없음' => 0, '주소빔' => 0];
        $보기 = [];
        $담을것 = [];

        $질의->chunk(1000, function ($줄들) use (&$셈, &$보기, &$담을것, $거래처, $이미, $정말) {
            foreach ($줄들 as $a) {
                $셈['모두']++;

                if (isset($이미[$a->ww_id])) {
                    $셈['이미있음']++;

                    continue;
                }

                $번호 = $거래처[$a->account_id] ?? null;

                if (! $번호) {
                    $셈['거래처없음']++;

                    continue;
                }

                $한줄 = trim((string) $a->address_line_1);

                if ($한줄 === '') {
                    $셈['주소빔']++;

                    continue;
                }

                $셈['새로']++;

                if (count($보기) < 5) {
                    $보기[] = [$a->ww_id, $번호, mb_substr($한줄, 0, 34),
                        mb_substr((string) $a->address_line_2, 0, 18)];
                }

                if (! $정말) {
                    continue;
                }

                $담을것[] = [
                    'patient_id'     => $번호,
                    'ww_address_id'  => $a->ww_id,
                    'postcode'       => preg_replace('/\D/', '', (string) $a->zipcode) ?: null,
                    'address'        => mb_substr($한줄, 0, 300),
                    'address_detail' => mb_substr((string) $a->address_line_2, 0, 200) ?: null,
                    'created_by'     => null,
                    /* 언제 적힌 주소인지는 원천이 안다 — 우리가 옮긴 때로 두면
                       이력의 차례가 모두 오늘로 뭉친다 */
                    'created_at'     => $a->created_at ?: now(),
                    'updated_at'     => $a->updated_at ?: ($a->created_at ?: now()),
                ];

                if (count($담을것) >= 500) {
                    DB::table('patient_addresses')->insert($담을것);
                    $담을것 = [];
                }
            }
        });

        if ($정말 && $담을것 !== []) {
            DB::table('patient_addresses')->insert($담을것);
        }

        $this->line('');
        $this->table(['원천 #', '거래처 #', '주소', '상세'], $보기);
        $this->line('');
        $this->table(['무엇', '몇'], collect($셈)->map(fn ($v, $k) => [$k, number_format($v)])->values()->all());
        $this->line('');

        if (! $정말) {
            $this->warn('  세어 보이기만 했습니다. 실제로 옮기려면 --force 를 적어 주십시오.');
        } else {
            $this->info('  옮겼습니다. 거래처 주소 이력이 지금 '
                . number_format(DB::table('patient_addresses')->count()) . '줄입니다.');
        }

        $this->line('  ww_customer_addresses · ww_customers 는 읽기만 했습니다.');
        $this->line('');

        return self::SUCCESS;
    }
}
