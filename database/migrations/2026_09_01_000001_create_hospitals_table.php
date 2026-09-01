<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 병원 표를 만든다.
 *
 * 지금까지 병원명ㆍ요양기관번호는 처방전마다 손으로 쳤다. 그래서 같은 병원이
 * 「순천향대학교 부속 부천병원」과 「순천향대학교 부천병원」으로 따로 서고, 번호는
 * 빈 채로 남은 건이 있다 — 청구할 때 그 번호가 없으면 공단이 받지 않는다.
 *
 * 한 번 적은 병원을 다시 고를 수 있게 표로 옮긴다. 이미 처방전에 적힌 것은
 * 그대로 씨앗으로 삼는다 — 요양기관번호가 있는 줄을 먼저 세우고, 같은 이름이
 * 여럿이면 번호가 있는 쪽을 남긴다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hospitals', function (Blueprint $t) {
            $t->id();
            $t->string('name', 120);
            // 요양기관번호 — 여덟 자리. 아직 모르는 병원이 있어 비워 둘 수 있다.
            $t->string('code', 20)->nullable();
            $t->string('tel', 30)->nullable();
            $t->string('fax', 30)->nullable();
            $t->string('address', 255)->nullable();
            $t->string('department', 60)->nullable();   // 진료과
            $t->string('memo', 255)->nullable();
            $t->boolean('is_active')->default(true);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->index('name');
            $t->index('code');
        });

        if (! Schema::hasTable('prescriptions')) {
            return;
        }

        /* 처방전에 적힌 것을 옮겨 심는다. 이름이 같으면 하나로 모으고, 요양기관번호는
           비어 있지 않은 것을 고른다 — 빈 번호로 덮으면 있던 것을 잃는다. */
        $rows = DB::table('prescriptions')
            ->selectRaw('TRIM(hospital_name) AS name, MAX(NULLIF(TRIM(hospital_code), "")) AS code')
            ->whereNotNull('hospital_name')
            ->whereRaw('TRIM(hospital_name) <> ""')
            ->groupBy(DB::raw('TRIM(hospital_name)'))
            ->get();

        $now = now();
        foreach ($rows as $r) {
            DB::table('hospitals')->insert([
                'name'       => $r->name,
                'code'       => $r->code,
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hospitals');
    }
};
