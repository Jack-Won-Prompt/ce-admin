<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 어디서 온 줄인가 — 시험 자료를 가려낼 딱지 (2026-09-29 지시).
 *
 * 운영 자료를 거래처 관리로 옮기기에 앞서, **지금 있는 것이 시험 자료**임을 표에 적어
 * 둔다. 적어 두지 않으면 옮긴 뒤에 무엇이 시험이고 무엇이 옮겨 온 것인지 가릴 수 없다 —
 * 지울 때도 무엇을 지워야 하는지 알 수 없다.
 *
 *   test       시험으로 만든 줄. 지울 수 있다.
 *   migration  운영 자료에서 옮겨 온 줄. ww_account_id 로 저쪽과 이어진다.
 *   live       사람이 업무로 만든 줄. **절대 지우지 않는다.**
 *   (빈칸)     알 수 없는 줄. 역시 **지우지 않는다** — 모르는 것은 남긴다.
 *
 * data_batch 는 한 번의 옮기기ㆍ한 판의 시험을 통째로 되돌리는 손잡이다. 날짜만으로는
 * 같은 날 두 번 돌린 것을 가릴 수 없다.
 *
 * **운영 데이터 메뉴의 표(ww_customers · ww_customer_addresses · ww_prescription_infos ·
 * delegation_signs)에는 칸 하나도 더하지 않는다.** 그 표는 읽기만 하는 자리다 —
 * 지우는 것도 고치는 것도 닿아서는 안 된다(2026-09-29 지시). 위임장 서명을 거래처와
 * 잇는 일은 그 표에 patient_id 를 적어 넣는 것이 아니라, 서명을 거래처 쪽으로
 * **옮겨 담아** 한다(patient_delegation_signs — 바로 다음 마이그레이션).
 */
return new class extends Migration
{
    /** 딱지를 붙일 표 — 거래처와 그 아래로 매달리는 줄기 */
    private const 표들 = [
        'patients', 'prescriptions', 'orders',
        'order_returns', 'sample_orders',
    ];

    public function up(): void
    {
        foreach (self::표들 as $t) {
            if (! Schema::hasTable($t)) {
                continue;
            }

            Schema::table($t, function (Blueprint $table) use ($t) {
                if (! Schema::hasColumn($t, 'data_origin')) {
                    $table->string('data_origin', 12)->nullable()->index();
                }
                if (! Schema::hasColumn($t, 'data_batch')) {
                    $table->string('data_batch', 40)->nullable()->index();
                }
            });
        }

        /* 거래처만 운영 고객과 이어 둔다 — 다시 옮겨도 덧쓰기가 되게.
           유일 색인을 걸어 같은 사람이 두 줄로 서는 것을 표가 막는다. */
        if (Schema::hasTable('patients') && ! Schema::hasColumn('patients', 'ww_account_id')) {
            Schema::table('patients', function (Blueprint $table) {
                $table->unsignedBigInteger('ww_account_id')->nullable()->unique();
                $table->string('ww_account_code', 50)->nullable()->index();
            });
        }

        /* 지금 있는 줄은 **모두 시험이다** (2026-09-29 지시).

           딱지가 없는 줄은 지우지 않는 것이 규칙이므로, 여기서 붙여 두지 않으면
           지금 자료는 영영 지울 수 없다. 이 자리가 곧 「되돌릴 지점」이다. */
        $묶음 = 'test-2026-09-29';

        foreach (self::표들 as $t) {
            if (! Schema::hasTable($t)) {
                continue;
            }

            DB::table($t)->whereNull('data_origin')
                ->update(['data_origin' => 'test', 'data_batch' => $묶음]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('patients')) {
            Schema::table('patients', function (Blueprint $table) {
                $table->dropUnique(['ww_account_id']);
                $table->dropColumn(['ww_account_id', 'ww_account_code']);
            });
        }

        foreach (self::표들 as $t) {
            if (! Schema::hasTable($t)) {
                continue;
            }

            Schema::table($t, function (Blueprint $table) {
                $table->dropColumn(['data_origin', 'data_batch']);
            });
        }
    }
};
