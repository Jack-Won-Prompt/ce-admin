<?php

namespace App\Services;

use App\Models\Setting;
use App\Support\WithworksSource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 위드웍스 운영 자료를 우리 표로 옮겨 담는다 (2026-09-18 지시).
 *
 * **마지막으로 담은 번호 뒤부터만 읽는다.** 열 만 줄을 날마다 다시 읽지 않으려는 것도
 * 있지만, 더 큰 까닭은 저쪽이 운영 중이라는 것이다 — 통째로 훑으면 그만큼 남의 운영
 * DB 에 짐을 지운다. 우리는 늘어난 것만 가져온다.
 *
 * 그 번호는 설정 화면에서 손으로 고칠 수 있다. 0 으로 되돌리면 처음부터 다시 읽는다 —
 * 칸을 새로 늘렸거나 저쪽에서 옛 줄을 고쳤을 때 쓴다.
 *
 * **고친 줄은 따라오지 않는다.** id 만 보고 자르므로, 이미 담은 줄이 저쪽에서 바뀌어도
 * 모른다. 그것까지 맞추려면 updated_at 을 봐야 하는데, 그러면 「어디까지」가 두 개가 되어
 * 되짚기가 어려워진다. 다시 담고 싶으면 번호를 0 으로 되돌린다.
 */
class WithworksImport
{
    /**
     * 무엇을 어디서 어디로.
     *
     * `한번에` 는 한 번에 읽어 오는 줄 수다. 만 줄씩 쥐면 메모리가 버겁고, 백 줄씩이면
     * 왕복이 잦다.
     */
    public const 대상 = [
        'prescription_infos' => [
            '이름'   => '처방전 정보',
            '갈래'   => WithworksSource::창고,
            '원천'   => 'account_add_informations',
            '우리'   => 'ww_prescription_infos',
            '거르개' => null,                       // 모두 가져온다 (2026-09-18 지시)
            '한번에' => 2000,
        ],
        'customers' => [
            '이름'   => '고객 정보',
            '갈래'   => WithworksSource::관리,
            '원천'   => 'accounts',
            '우리'   => 'ww_customers',
            '거르개' => ['top_account_id' => 148659, 'account_type' => '30'],
            '한번에' => 2000,
        ],
        'customer_addresses' => [
            '이름'   => '고객 주소',
            '갈래'   => WithworksSource::창고,
            '원천'   => 'account_addresses',
            '우리'   => 'ww_customer_addresses',
            '거르개' => null,                       // 아래에서 고객으로 좁힌다
            '한번에' => 2000,
        ],
    ];

    /** 마지막으로 담은 저쪽 번호가 담기는 자리 */
    public const 자리 = 'last_id';

    /**
     * 한 갈래를 가져온다.
     *
     * @return array{읽음:int, 담음:int, 마지막:int, 이름:string}
     */
    public function 가져오기(string $열쇠, ?callable $알림 = null): array
    {
        $d = self::대상[$열쇠] ?? throw new \InvalidArgumentException("모르는 갈래입니다 ({$열쇠}).");

        $마지막 = $this->마지막번호($열쇠);
        $칸들   = $this->우리칸($d['우리']);
        $읽음   = $담음 = 0;

        do {
            $줄들 = $this->한묶음($d, $마지막);

            if ($줄들->isEmpty()) {
                break;
            }

            $담을것 = $줄들->map(function ($r) use ($칸들) {
                $줄 = ['ww_id' => $r->id, 'imported_at' => now()];

                foreach ($칸들 as $칸) {
                    if ($칸 === 'ww_id' || $칸 === 'imported_at') { continue; }
                    /* 저쪽에 없는 칸은 건너뛴다 — 저쪽이 칸을 지워도 여기서 죽지 않는다 */
                    if (property_exists($r, $칸)) { $줄[$칸] = $r->{$칸}; }
                }

                return $줄;
            })->all();

            /* 같은 ww_id 가 다시 오면 덮어쓴다 — 번호를 0 으로 되돌려 다시 담을 때다 */
            DB::table($d['우리'])->upsert($담을것, ['ww_id'], array_keys(reset($담을것)));

            $읽음  += $줄들->count();
            $담음  += count($담을것);
            $마지막 = (int) $줄들->last()->id;

            $this->마지막번호저장($열쇠, $마지막);

            if ($알림) { $알림($읽음, $마지막); }
        } while ($줄들->count() >= $d['한번에']);

        Log::info('[위드웍스 가져오기] 끝', [
            '갈래' => $열쇠, '읽음' => $읽음, '마지막' => $마지막,
        ]);

        return ['읽음' => $읽음, '담음' => $담음, '마지막' => $마지막, '이름' => $d['이름']];
    }

    /** 저쪽에서 한 묶음 읽는다 — 번호 뒤부터, 번호 차례로 */
    private function 한묶음(array $d, int $뒤부터)
    {
        $q = WithworksSource::연결($d['갈래'])->table($d['원천'])
            ->where('id', '>', $뒤부터)
            ->orderBy('id')
            ->limit($d['한번에']);

        foreach ($d['거르개'] ?? [] as $칸 => $값) {
            $q->where($칸, $값);
        }

        /* 주소는 우리 고객 것만 — 저쪽 표에는 다른 대리점 것이 함께 있다 */
        if ($d['우리'] === 'ww_customer_addresses') {
            $q->whereIn('account_id', function ($s) {
                $s->from('accounts')->select('id')
                  ->where('top_account_id', 148659)->where('account_type', '30');
            });
        }

        return collect($q->get());
    }

    /**
     * 우리 표의 칸 이름.
     *
     * 표를 보고 정한다 — 목록을 코드에 또 적으면 마이그레이션과 어긋난다.
     */
    private function 우리칸(string $표): array
    {
        return \Illuminate\Support\Facades\Schema::getColumnListing($표);
    }

    public function 마지막번호(string $열쇠): int
    {
        return (int) (Setting::where('group', WithworksSource::묶음)
            ->where('key', "{$열쇠}_" . self::자리)->first()?->plainValue() ?? 0);
    }

    public function 마지막번호저장(string $열쇠, int $번호): void
    {
        $줄 = Setting::firstOrNew([
            'group' => WithworksSource::묶음,
            'key'   => "{$열쇠}_" . self::자리,
        ]);
        $줄->setPlainValue((string) $번호, secret: false);
        $줄->save();
    }

    /** 지금 어디까지 왔나 — 화면이 보여 준다 */
    public function 현황(): array
    {
        $out = [];

        foreach (self::대상 as $열쇠 => $d) {
            $담긴것 = \Illuminate\Support\Facades\Schema::hasTable($d['우리'])
                ? DB::table($d['우리'])->count() : 0;

            $out[$열쇠] = [
                '이름'     => $d['이름'],
                '원천'     => $d['갈래'] . '.' . $d['원천'],
                '우리표'   => $d['우리'],
                '담긴줄'   => $담긴것,
                '마지막'   => $this->마지막번호($열쇠),
                '마지막담은때' => \Illuminate\Support\Facades\Schema::hasTable($d['우리'])
                    ? DB::table($d['우리'])->max('imported_at') : null,
            ];
        }

        return $out;
    }
}
