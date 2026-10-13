<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 「신규 위임동의」로 만들어진 처방전인지 표시한다 (2026-10-13 확인요청).
 *
 * 그 화면은 이름과 휴대폰만 받아 위임동의 알림톡을 보낸다. 동의 기록이 처방전에
 * 매달려야 해서(prescription_consents.prescription_id 가 필수) 처방전을 한 건 만드는데,
 * 이미지도 첨부도 품목도 없는 상태라 주문 등록 목록에 「주문 없음」 줄로 나왔다.
 * (E)이순옥 님 RX-20261013-020 건이 그랬다.
 *
 * upload_source 는 mobile·web 둘뿐이라 일반 웹 등록과 가려낼 수 없었다. 표시를 따로 둔다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->boolean('is_consent_only')->default(false)->after('is_blank_draft');
        });

        /* 지난 건에도 표시를 채운다.

           「신규 위임동의」는 만들 때마다 활동 이력에 「위임동의용 처방전 생성 → …」을
           남겨 왔다. 그 기록이 지금 남은 유일한 단서다 — upload_source 는 일반 웹 등록과
           똑같은 'web' 이라 가려낼 수 없다.

           활동 이력은 25MB 로 작아 앞글자 맞추기로 훑어도 부담이 없다. 채우는 것은
           처방전 쪽 한 칸뿐이고, 주문ㆍ서류ㆍ동의는 손대지 않는다. */
        $번호들 = DB::table('activity_log')
            ->where('subject_type', 'like', '%Prescription')
            ->where('description', 'like', '위임동의용 처방전 생성%')
            ->distinct()->pluck('subject_id');

        foreach ($번호들->chunk(500) as $묶음) {
            DB::table('prescriptions')->whereIn('id', $묶음->all())
                ->update(['is_consent_only' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropColumn('is_consent_only');
        });
    }
};
