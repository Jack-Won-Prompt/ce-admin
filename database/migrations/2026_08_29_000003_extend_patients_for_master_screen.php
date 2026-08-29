<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 거래처관리를 환자 정보의 정본으로 만든다 (화면 확정요청 2026-08-27, 2~4쪽).
 *
 * 지금까지 환자 정보는 거래처관리와 주문등록 두 곳에서 각각 고쳐졌다. 어느 쪽이
 * 맞는 값인지 알 수 없었고, 연락이 닿는지·어디로 거는 것이 나은지·누가 돈을 보내는지는
 * 담을 칸조차 없어 메모에 적히거나 적히지 않았다.
 *
 * 주소는 한 벌만 두어 왔다. 이사를 하면 옛 주소가 지워져, 지난 주문이 어디로 갔는지
 * 되짚을 수 없었다. 바뀔 때마다 한 줄씩 쌓고 가장 최근 것을 환자 칸에 둔다 —
 * 그 칸을 읽는 화면들은 그대로 두면서 이력을 얻는다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            // 돈을 보내는 사람 — 환자와 다른 일이 잦다(보호자가 보낸다)
            $table->string('remitter_name', 50)->nullable()->after('guardian_phone');

            // 어디로 거는 것이 나은가 — 전화·해피톡·업무폰 문자/카톡
            $table->string('contact_channel', 20)->nullable()->after('remitter_name');

            /* 연락이 닿는가. 예전에는 Active/Inactive 둘이었는데, 왜 안 닿는지가
               다음에 할 일을 정한다 — 사망과 수신거부와 타사이동은 서로 다른 일이다. */
            $table->string('contact_status', 20)->nullable()->after('contact_channel');

            $table->string('fax', 30)->nullable()->after('email');

            // 누가 만들고 누가 마지막으로 고쳤는가 (수정일자는 updated_at 이 이미 안다)
            $table->foreignId('created_by')->nullable()->after('contact_status')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')
                ->constrained('users')->nullOnDelete();
        });

        /**
         * 주소 이력.
         *
         * 가장 최근 것이 patients 의 주소 칸과 같다 — 그 칸을 읽는 화면(주문 등록·팩스·
         * 서류)은 손대지 않는다. 여기 쌓이는 것은 「언제 어디였는지」다.
         */
        Schema::create('patient_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('postcode', 10)->nullable();
            $table->string('address', 300)->nullable();
            $table->string('address_detail', 200)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['patient_id', 'id']);
        });

        /* 지금 담겨 있는 주소를 첫 줄로 옮겨 담는다. 이력이 비어 있으면 화면이
           「주소 없음」으로 보이는데, 실제로는 있는 주소다. */
        $rows = DB::table('patients')
            ->whereNotNull('address')->where('address', '<>', '')
            ->get(['id', 'postcode', 'address', 'address_detail', 'updated_at', 'created_at']);

        foreach ($rows->chunk(200) as $chunk) {
            DB::table('patient_addresses')->insert($chunk->map(fn ($p) => [
                'patient_id'     => $p->id,
                'postcode'       => $p->postcode,
                'address'        => $p->address,
                'address_detail' => $p->address_detail,
                'created_by'     => null,
                // 언제 적힌 주소인지는 알 수 없다 — 그 환자를 마지막으로 고친 때로 둔다
                'created_at'     => $p->updated_at ?? $p->created_at,
                'updated_at'     => $p->updated_at ?? $p->created_at,
            ])->all());
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_addresses');

        Schema::table('patients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn(['remitter_name', 'contact_channel', 'contact_status', 'fax']);
        });
    }
};
