<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 공통 코드에서 빠진 것을 채운다 (2026-09-30 실전 시험에서 드러남).
 *
 * ## 왜
 *
 * 운영의 `common_codes` 가 **통째로 비어 있었다**(0줄). 그래서 —
 *
 *   · 앱ㆍ웹의 처방자료 업로드가 **한 장도 올라가지 않았다**
 *     (`UploadDocTypes::codes()` 가 빈 배열이라 어떤 유형을 보내도 422 「올바른
 *     서류 유형이 아닙니다」)
 *   · 서류 유형이 이름을 잃어 목록ㆍ첨부 화면이 코드만 보인다
 *
 * 이 코드는 `2026_08_19_000003_create_common_codes_table` 이 심는 것이다. 표는
 * 서 있는데 줄만 없으니 그 마이그레이션은 이미 돌아간 것으로 적혀 있고, 다시
 * 돌릴 수 없다(`migrate` 는 지나간 것을 되돌아보지 않는다).
 *
 * ## 어떻게
 *
 * **없는 것만 넣는다.** 이미 있는 줄은 이름도 차례도 건드리지 않는다 — 담당자가
 * 환경 설정에서 고쳐 둔 것을 되돌리면 안 된다.
 *
 * 다시 돌려도 탈이 없다.
 */
class EnsureCommonCodesCommand extends Command
{
    protected $signature = 'common-codes:ensure {--force : 실제로 채운다. 없으면 보이기만 한다}';

    protected $description = '공통 코드에서 빠진 것을 채웁니다 (이미 있는 것은 건드리지 않습니다)';

    /** 마이그레이션이 심는 것과 **같은 값**이다 — 두 벌이 되지 않게 그대로 옮겨 적는다 */
    private const 있어야할것 = [
        // 처방 서류 — 병원에서 받아 오는 종이다
        ['rx',    'registration_form', '등록신청서',                    10, false],
        ['rx',    'prescription',      '처방전',                        20, false],
        ['rx',    'test_result',       '결과지',                        30, false],
        ['rx',    'id_card',           '신분증',                        40, false],

        // 청구 자료 — 공단ㆍ지자체에 내는 증빙이다
        ['claim', 'trade_statement',   '거래명세서',                    10, false],
        ['claim', 'cash_receipt',      '현금영수증',                    20, false],
        ['claim', 'card_sales',        '카드매출',                      30, false],
        ['claim', 'tax_invoice',       '세금계산서(주민등록번호)',      40, false],
        ['claim', 'purchase_confirm',  '의료용품구입확인서(지자체용)',  50, false],
        ['claim', 'registered_post',   '등기처리 영수증',               60, false],

        // 시스템이 만들거나 이름 붙일 수 없는 것들
        ['etc',   'delegation',        '위임장',                        10, true],
        ['etc',   'privacy_consent',   '개인정보 동의서',               20, true],
        ['etc',   'other',             '기타',                          90, true],
    ];

    public function handle(): int
    {
        if (! Schema::hasTable('common_codes')) {
            $this->error('common_codes 표가 없습니다 — 마이그레이션을 먼저 돌려 주십시오.');

            return self::FAILURE;
        }

        $정말 = (bool) $this->option('force');

        $있는것 = DB::table('common_codes')->where('group', 'doc_type')
            ->pluck('code')->flip()->all();

        $this->line('');
        $this->info('══ 공통 코드 doc_type '
            . ($정말 ? '(실제로 채웁니다)' : '(보이기만 합니다)') . ' ══');
        $this->line('  지금 담긴 것 ' . count($있는것) . '개 · 있어야 할 것 '
            . count(self::있어야할것) . '개');
        $this->line('');

        $넣을것 = [];
        $보기   = [];

        foreach (self::있어야할것 as [$kind, $code, $label, $sort, $system]) {
            $무엇 = isset($있는것[$code]) ? '그대로' : '넣는다';
            $보기[] = [$kind, $code, $label, $sort, $system ? 'O' : '', $무엇];

            if ($무엇 === '그대로') {
                continue;
            }

            $넣을것[] = [
                'group'      => 'doc_type',
                'kind'       => $kind,
                'code'       => $code,
                'label'      => $label,
                'sort_order' => $sort,
                'is_active'  => true,
                'is_system'  => $system,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $this->table(['갈래', '코드', '이름', '차례', '시스템', '무엇'], $보기);

        if ($정말 && $넣을것 !== []) {
            DB::table('common_codes')->insert($넣을것);
        }

        $this->line('');

        if (! $정말) {
            $this->warn('  보이기만 했습니다. 실제로 채우려면 --force 를 적어 주십시오.');
        } else {
            $this->info('  ' . count($넣을것) . '개를 넣었습니다. doc_type 이 지금 '
                . DB::table('common_codes')->where('group', 'doc_type')->count() . '개입니다.');
            $this->line('  업로드가 받는 유형 : '
                . implode(', ', \App\Support\UploadDocTypes::codes()));
        }

        $this->line('');

        return self::SUCCESS;
    }
}
