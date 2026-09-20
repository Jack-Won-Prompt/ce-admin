<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 뼈대 네 표의 외래키를 건다 (2026-09-20 지시).
 *
 * 만드는 장(2026_04_01_000000_create_base_tables)에서는 걸 수 없었다.
 *
 *   · prescriptions 와 orders 가 서로를 가리킨다 — 둘 다 서기 전에는 걸리지 않는다
 *   · users 가 permission_groups 를 가리키는데, 그 표는 2026-07 장이 만든다
 *   · patients 를 가리키는 칸 가운데 일부는 뒤따르는 장이 더한다
 *
 * 그래서 모든 장이 지나간 **맨 끝**에서 한꺼번에 건다. 이미 걸려 있으면 건너뛴다 —
 * 운영 서버는 이 장을 돌 때 이미 다 걸려 있다.
 */
return new class extends Migration
{

    /**
     * 뼈대 네 표의 인덱스 — 표 · 이름 · 갈래 · 칸.
     *
     * 만드는 장에서는 걸지 않았다. 뒤따르는 장들도 같은 이름으로 인덱스를 만드는데,
     * 그 가운데 하나는 인덱스 목록을 배열 상수로 들고 돌아(promote_counseling_json_to_columns)
     * 미리 가려낼 수가 없었다. 먼저 걸어 두면 그 장이 「Duplicate key name」으로 깨진다.
     *
     * 그래서 맨 끝에서 **아직 없는 것만** 채운다.
     */
    private const 인덱스 = [
        ['users', 'users_email_unique', 'UNIQUE', '`email`'],
        ['patients', 'patients_resident_no_hash_index', 'INDEX', '`resident_no_hash`'],
        ['patients', 'patients_nhis_agree_end_idx', 'INDEX', '`nhis_agree_end`'],
        ['patients', 'patients_new_patient_date_idx', 'INDEX', '`new_patient_date`'],
        ['patients', 'patients_care_type_index', 'INDEX', '`care_type`'],
        ['prescriptions', 'prescriptions_rx_number_unique', 'UNIQUE', '`rx_number`'],
        ['prescriptions', 'prescriptions_status_created_at_index', 'INDEX', '`status`,`created_at`'],
        ['prescriptions', 'prescriptions_patient_id_index', 'INDEX', '`patient_id`'],
        ['prescriptions', 'prescriptions_rx_number_index', 'INDEX', '`rx_number`'],
        ['prescriptions', 'prescriptions_is_blank_draft_created_by_index', 'INDEX', '`is_blank_draft`,`created_by`'],
        ['prescriptions', 'rx_benefit_class_idx', 'INDEX', '`benefit_class`'],
        ['prescriptions', 'rx_purchase_type_idx', 'INDEX', '`purchase_type`'],
        ['prescriptions', 'rx_end_date_idx', 'INDEX', '`rx_end_date`'],
        ['prescriptions', 'rx_next_repurchase_idx', 'INDEX', '`next_repurchase`'],
        ['prescriptions', 'rx_counsel_no_idx', 'INDEX', '`counsel_no`'],
        ['prescriptions', 'rx_claim_agency_idx', 'INDEX', '`claim_agency`'],
        ['prescriptions', 'prescriptions_billing_strategy_index', 'INDEX', '`billing_strategy`'],
        ['prescriptions', 'prescriptions_billing_office_id_index', 'INDEX', '`billing_office_id`'],
        ['prescriptions', 'prescriptions_input_review_status_idx', 'INDEX', '`input_review_status`'],
        ['orders', 'orders_order_number_unique', 'UNIQUE', '`order_number`'],
        ['orders', 'orders_claim_ready_idx', 'INDEX', '`claim_ready`'],
        ['orders', 'orders_deposit_confirmed_at_index', 'INDEX', '`deposit_confirmed_at`'],
        ['orders', 'orders_parent_order_id_index', 'INDEX', '`parent_order_id`'],
        ['orders', 'orders_order_kind_index', 'INDEX', '`order_kind`'],
        ['orders', 'orders_cancel_state_index', 'INDEX', '`cancel_state`'],
        ['orders', 'orders_amend_state_index', 'INDEX', '`amend_state`'],
    ];

    /** 표 · 칸 · 가리키는 표 · 지울 때 어떻게 할 것인가 */
    private const 걸것 = [
        ['patients',      'created_by',         'users',             'set null'],
        ['patients',      'updated_by',         'users',             'set null'],
        ['prescriptions', 'patient_id',         'patients',          'set null'],
        ['prescriptions', 'assigned_user_id',   'users',             'set null'],
        ['prescriptions', 'created_by',         'users',             'set null'],
        ['prescriptions', 'updated_by',         'users',             'set null'],
        ['prescriptions', 'reviewed_by',        'users',             'set null'],
        ['prescriptions', 'counsel_order_id',   'orders',            'set null'],
        ['orders',        'prescription_id',    'prescriptions',     'cascade'],
        ['orders',        'patient_id',         'patients',          'set null'],
        ['orders',        'created_by',         'users',             'set null'],
        ['orders',        'operation_user_id',  'users',             'set null'],
        ['orders',        'settle_status_by',   'users',             'set null'],
        ['orders',        'closing_checked_by', 'users',             'set null'],
        ['users',         'permission_group_id', 'permission_groups', 'set null'],
    ];

    public function up(): void
    {
        $this->인덱스채우기();

        foreach (self::걸것 as [$표, $칸, $가리키는표, $지울때]) {
            if (! Schema::hasTable($표) || ! Schema::hasTable($가리키는표)) {
                continue;
            }
            if (! Schema::hasColumn($표, $칸) || $this->이미걸렸나($표, $칸)) {
                continue;
            }

            $이름 = "{$표}_{$칸}_foreign";
            $규칙 = $지울때 === 'cascade' ? 'CASCADE' : 'SET NULL';

            DB::statement(
                "ALTER TABLE `{$표}` ADD CONSTRAINT `{$이름}` "
                . "FOREIGN KEY (`{$칸}`) REFERENCES `{$가리키는표}` (`id`) ON DELETE {$규칙}"
            );
        }
    }

    public function down(): void
    {
        /* 되돌리지 않는다 — 외래키를 떼면 짝 잃은 줄이 생겨도 아무도 막지 못한다. */
    }


    /** 아직 없는 인덱스를 채운다 */
    private function 인덱스채우기(): void
    {
        foreach (self::인덱스 as [$표, $이름, $갈래, $칸]) {
            if (! Schema::hasTable($표) || $this->인덱스있나($표, $이름)) {
                continue;
            }

            /* 칸이 하나라도 없으면 건너뛴다 — 그 칸을 더하는 장이 제 인덱스도 함께 건다 */
            foreach (preg_split('/\s*,\s*/', trim($칸, '`')) as $한칸) {
                if (! Schema::hasColumn($표, trim($한칸, '`'))) {
                    continue 2;
                }
            }

            $말 = $갈래 === 'UNIQUE' ? 'UNIQUE INDEX' : ($갈래 === 'FULLTEXT' ? 'FULLTEXT INDEX' : 'INDEX');
            DB::statement("ALTER TABLE `{$표}` ADD {$말} `{$이름}` ({$칸})");
        }
    }

    /** 이 이름의 인덱스가 이미 있는가 */
    private function 인덱스있나(string $표, string $이름): bool
    {
        return DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $표)
            ->where('INDEX_NAME', $이름)
            ->exists();
    }

    /** 이 칸에 외래키가 이미 걸려 있는가 */
    private function 이미걸렸나(string $표, string $칸): bool
    {
        return DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $표)
            ->where('COLUMN_NAME', $칸)
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->exists();
    }
};
