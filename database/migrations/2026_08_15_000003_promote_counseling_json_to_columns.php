<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * counseling_data JSON 에 뭉쳐 있던 항목에 각자 컬럼을 준다.
 *
 * 왜 하는가 — 콜로 1차·2차 요청의 검색조건 상당수가 이 JSON 안에 갇혀 있다. JSON 은 인덱스를
 * 걸 수 없어 건수가 늘면 검색이 급격히 느려지고, 정렬도 되지 않는다.
 *
 * 어디로 나누는가 — 값의 임자를 따진다.
 *   환자 속성(건보 등록·위임동의 기간·기초 재평가·보호자 등) → patients
 *   처방전 속성(자격·상병구분·처방기간·Five/Six 등)          → prescriptions
 *
 * **기존 값은 옮기지 않는다.** 컬럼만 만든다. 앞으로 저장되는 값이 컬럼으로 가고, 이미 쌓인
 * 건은 Prescription::counselingMerged() 가 JSON 에서 그대로 읽어 화면에 낸다. 그래서 이
 * 마이그레이션은 데이터를 건드리지 않는다 — 되돌리면 컬럼만 사라진다.
 *
 * 상담 항목(counselling_no·counsel_date·type·status·contents 등)은 계속 JSON 에 남는다 —
 * 검색 대상이 아니고, 채번이 JSON_EXTRACT 에 걸려 있다(Prescription::generateCounselNo).
 *
 * 중복이던 값은 정본을 정해 두었다(코드에서 그쪽만 쓴다).
 *   erp_cd9    → prescriptions.hospital_code
 *   mobile2    → patients.phone
 *   department → prescriptions.specialty
 *
 * 다만 udf30(다음 재구매 가능일)과 repurchase_date(재구매 예정일)는 중복이 아니었다 —
 * 운영 11건이 전부 하루씩 다르다. 각자 컬럼을 둔다.
 */
return new class extends Migration
{
    /** 환자 속성 — 컬럼 => 형 */
    private const PATIENT_COLS = [
        'email'               => 'string:190',
        'sb_sci'              => 'string:50',
        'nhis_reg_status'     => 'string:20',
        'nhis_reg_date'       => 'date',
        'nhis_renew'          => 'string:100',
        'nhis_renew_due'      => 'date',
        'nhis_agree_start'    => 'date',
        'nhis_agree_end'      => 'date',
        'basic_reeval'        => 'string:100',
        'basic_reeval_due'    => 'date',
        'cash_receipt_no'     => 'string:50',
        'deduction'           => 'string:20',
        'new_patient_date'    => 'date',
        'guardian_name'       => 'string:50',
        'guardian_relation'   => 'string:50',
        'guardian_birth_date' => 'date',
        'guardian_phone'      => 'string:40',
    ];

    /** 처방전 속성 — 컬럼 => 형 */
    private const PRESCRIPTION_COLS = [
        'benefit_class'      => 'string:20',
        'disease_class'      => 'string:10',
        'uro_date'           => 'date',
        'diagnosis_date'     => 'date',
        'rx_use_period'      => 'int',
        'rx_end_date'        => 'date',
        'purchase_type'      => 'string:20',
        'next_repurchase'    => 'date',
        'five_program'       => 'string:10',
        'five_110days'       => 'string:50',
        'daily_use_qty'      => 'int',
        'order_manager'      => 'string:50',
        'special_case'       => 'string:50',
        'reason'             => 'string:200',
        'pay_date'           => 'date',
        'buy_date'           => 'date',
        'inmarket_due'       => 'date',
        'last_confirmed_qty' => 'int',
        'diverticulums'      => 'string:10',
    ];

    /** 검색·정렬에 실제로 쓰일 칸에만 인덱스를 건다 */
    private const INDEXES = [
        'patients' => [
            'nhis_agree_end'   => 'patients_nhis_agree_end_idx',
            'new_patient_date' => 'patients_new_patient_date_idx',
        ],
        'prescriptions' => [
            'benefit_class'   => 'rx_benefit_class_idx',
            'purchase_type'   => 'rx_purchase_type_idx',
            'rx_end_date'     => 'rx_end_date_idx',
            'next_repurchase' => 'rx_next_repurchase_idx',
        ],
    ];

    public function up(): void
    {
        foreach (['patients' => self::PATIENT_COLS, 'prescriptions' => self::PRESCRIPTION_COLS] as $name => $cols) {
            Schema::table($name, function (Blueprint $table) use ($name, $cols) {
                foreach ($cols as $col => $type) {
                    if (Schema::hasColumn($name, $col)) { continue; }
                    [$kind, $len] = array_pad(explode(':', $type), 2, null);
                    match ($kind) {
                        'string' => $table->string($col, (int) $len)->nullable(),
                        'date'   => $table->date($col)->nullable(),
                        'int'    => $table->integer($col)->nullable(),
                    };
                }
            });
        }

        foreach (self::INDEXES as $name => $indexes) {
            Schema::table($name, function (Blueprint $table) use ($indexes) {
                foreach ($indexes as $col => $idx) { $table->index($col, $idx); }
            });
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $name => $indexes) {
            Schema::table($name, function (Blueprint $table) use ($indexes) {
                foreach ($indexes as $idx) { $table->dropIndex($idx); }
            });
        }

        Schema::table('patients', fn (Blueprint $t) => $t->dropColumn(array_keys(self::PATIENT_COLS)));
        Schema::table('prescriptions', fn (Blueprint $t) => $t->dropColumn(array_keys(self::PRESCRIPTION_COLS)));
    }
};
