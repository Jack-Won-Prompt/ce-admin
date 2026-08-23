<?php
// 개인정보 동의서를 환자와 잇는다.
//
// 이 표는 밖에서 들어오는 제출 폼이라 이름ㆍ전화만 들고 있었다. 그래서 「이 환자가
// 개인정보 동의를 했는가」를 물을 길이 없었다 — 거래처 관리에서 그것으로 거르려면
// 열쇠가 있어야 한다. patient_id 를 더하고, 이미 쌓인 것은 이름+전화로 한 번 맞춰 채운다.
//
// 이름+전화가 맞는 환자가 딱 하나일 때만 잇는다. 여럿이면 어느 쪽인지 가릴 수 없어
// 비워 둔다 — 잘못 이어 두면 동의하지 않은 사람이 동의한 것으로 보인다.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('privacy_consents')) {
            return;
        }

        if (!Schema::hasColumn('privacy_consents', 'patient_id')) {
            Schema::table('privacy_consents', function (Blueprint $table) {
                $table->unsignedBigInteger('patient_id')->nullable()->after('id');
                $table->index('patient_id');
            });
        }

        $this->backfill();
    }

    public function down(): void
    {
        if (!Schema::hasColumn('privacy_consents', 'patient_id')) {
            return;
        }

        Schema::table('privacy_consents', function (Blueprint $table) {
            $table->dropIndex(['patient_id']);
            $table->dropColumn('patient_id');
        });
    }

    /** 이미 쌓인 동의서를 이름+전화로 환자에 잇는다. */
    private function backfill(): void
    {
        $digits = fn (?string $v) => preg_replace('/\D/', '', (string) $v);

        // 환자를 (이름, 전화숫자)로 미리 묶어 둔다 — 동의서마다 조회하지 않는다
        $byKey = [];
        DB::table('patients')->select('id', 'name', 'mobile', 'phone')->orderBy('id')
            ->chunk(500, function ($rows) use (&$byKey, $digits) {
                foreach ($rows as $p) {
                    foreach ([$p->mobile, $p->phone] as $tel) {
                        $d = $digits($tel);
                        if ($d === '') {
                            continue;
                        }
                        $byKey[trim((string) $p->name) . '|' . $d][] = $p->id;
                    }
                }
            });

        DB::table('privacy_consents')->whereNull('patient_id')
            ->select('id', 'name', 'phone', 'phone2')->orderBy('id')
            ->chunk(500, function ($rows) use ($byKey, $digits) {
                foreach ($rows as $c) {
                    $hit = null;
                    foreach ([$c->phone, $c->phone2] as $tel) {
                        $d = $digits($tel);
                        if ($d === '') {
                            continue;
                        }
                        $ids = $byKey[trim((string) $c->name) . '|' . $d] ?? [];
                        // 딱 하나일 때만 잇는다
                        if (count(array_unique($ids)) === 1) {
                            $hit = $ids[0];
                            break;
                        }
                    }
                    if ($hit) {
                        DB::table('privacy_consents')->where('id', $c->id)->update(['patient_id' => $hit]);
                    }
                }
            });
    }
};
