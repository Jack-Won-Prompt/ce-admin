<?php

use App\Support\SsoSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SSO 설정을 시험ㆍ운영으로 갈라 담는다 (2026-09-29 지시).
 *
 * 여태 한 벌뿐이었다(sso_web.tenant_id …). 운영으로 넘기려면 그 자리에 운영 값을
 * 덮어써야 하고, 되돌릴 일이 생기면 시험 값을 다시 받아 넣어야 한다 — 그 사이에는
 * 아무도 들어오지 못한다. 팝빌ㆍ토스ㆍ위드웍스가 그러하듯 두 벌을 함께 둔다.
 *
 * **지금 담긴 것은 시험 값으로 옮긴다.** 이 서버(ceadmin.co.kr)가 보는 것은
 * Dev/UAT 로 켜 둔 자격이다(2026-09-21). 운영 값은 아직 담기지 않았으므로 고른
 * 환경도 test 로 둔다 — 모르는 새 운영 자격으로 넘어가면 안 된다.
 *
 * 값은 옮기기만 한다. 다시 암호화하지 않는다 — Setting 이 담을 때 이미 했고,
 * 여기서 평문을 꺼내면 그 자취가 마이그레이션 기록에 남는다.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $무리 = SsoSettings::GROUP;

        foreach (array_keys(SsoSettings::FIELDS) as $칸) {
            $옛 = DB::table('settings')->where('group', $무리)->where('key', $칸)->first();

            if (! $옛) {
                continue;
            }

            $새이름 = 'test_' . $칸;

            /* 이미 시험 쪽에 값이 있으면 옛 줄만 치운다 — 덮어쓰면 나중에 넣은
               것이 먼저 넣은 것에 지워진다. */
            $이미 = DB::table('settings')->where('group', $무리)->where('key', $새이름)->exists();

            if ($이미) {
                DB::table('settings')->where('id', $옛->id)->delete();

                continue;
            }

            DB::table('settings')->where('id', $옛->id)->update([
                'key'        => $새이름,
                'updated_at' => now(),
            ]);
        }

        /* 고른 환경 — 없으면 시험으로 세운다 */
        if (! DB::table('settings')->where('group', $무리)->where('key', 'env')->exists()) {
            DB::table('settings')->insert([
                'group'      => $무리,
                'key'        => 'env',
                'value'      => 'test',
                'is_secret'  => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        SsoSettings::forget();
    }

    public function down(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $무리 = SsoSettings::GROUP;

        /* 되돌릴 때는 **고른 환경의 값**을 옛 이름으로 돌려 놓는다 — 운영으로 넘긴
           뒤에 되돌리면서 시험 값을 세우면 그 자리에서 로그인이 끊긴다. */
        $고른것 = DB::table('settings')->where('group', $무리)->where('key', 'env')->value('value') ?: 'test';

        foreach (array_keys(SsoSettings::FIELDS) as $칸) {
            DB::table('settings')->where('group', $무리)->where('key', $칸)->delete();

            DB::table('settings')->where('group', $무리)->where('key', $고른것 . '_' . $칸)
                ->update(['key' => $칸, 'updated_at' => now()]);

            foreach (array_keys(SsoSettings::ENVS) as $e) {
                DB::table('settings')->where('group', $무리)->where('key', $e . '_' . $칸)->delete();
            }
        }

        DB::table('settings')->where('group', $무리)->where('key', 'env')->delete();

        SsoSettings::forget();
    }
};
