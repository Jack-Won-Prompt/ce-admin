<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 개인정보 동의서에 서명을 담는다 (2026-10-02 지시).
 *
 * 「개인정보동의서에 서명내용 있으면 CE admin 서명확인완료(위임, 개인정보 모두)로
 *  보이게 / 처방 구매 안 하는 사람들은 저 링크에서만 서명동의 받아서 구매 진행」
 *
 * 공개 동의서(`/privacy`)는 여태 **동의 체크만** 받았다. 서명 칸이 아예 없었고
 * (2026-10-02 확인) 담긴 52건도 모두 모바일 동의다. 처방을 끼지 않고 사는 사람은
 * 그 링크 하나로 끝나야 하므로, 그 자리에서 서명까지 받아 담는다.
 *
 * ## 왜 서명을 따로 담는가
 *
 * 체크만 있는 동의와 서명이 있는 동의는 **증빙의 무게가 다르다.** 위임까지 인정하는
 * 것은 서명이 있을 때만이라, 둘을 칸으로 가려 두어야 한다.
 *
 * ## source_ref — 같은 것을 두 번 담지 않게
 *
 * 공개 동의서는 **다른 서버**(www.ceadmin.co.kr · 3.34.53.36)에 있고, 운영 서버로는
 * 웹훅으로 건너온다. 웹훅은 실패하면 다시 온다 — 그때 같은 동의가 두 줄로 쌓이면
 * 어느 것이 맞는지 알 수 없다. 보내는 쪽의 줄 번호를 적어 두고 그것으로 맞춘다.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('privacy_consents')) {
            return;
        }

        Schema::table('privacy_consents', function (Blueprint $table) {
            if (! Schema::hasColumn('privacy_consents', 'signature_data')) {
                /* 서명 그림 — data:image/png;base64,… 꼴. 처방전 동의(prescription_consents)
                   의 signature_data 와 같은 꼴로 담아, 위임장에 찍는 자리가 둘을 가리지
                   않아도 되게 한다. */
                $table->longText('signature_data')->nullable()->after('extra');
            }

            if (! Schema::hasColumn('privacy_consents', 'signed_at')) {
                $table->timestamp('signed_at')->nullable()->after('signature_data');
            }

            if (! Schema::hasColumn('privacy_consents', 'source_ref')) {
                /* 보내는 쪽의 줄 번호 — 웹훅이 다시 와도 한 줄로 맞춘다 */
                $table->string('source_ref', 60)->nullable()->after('signed_at');
            }

            if (! Schema::hasColumn('privacy_consents', 'source_host')) {
                /* 어느 자리에서 온 것인가 — 증빙을 찾을 때 담당자가 바로 알아야 한다 */
                $table->string('source_host', 120)->nullable()->after('source_ref');
            }
        });

        /* 들온 길이 겹치지 않게 — 같은 보낸이ㆍ같은 줄은 한 번만 담는다.
           비어 있는 줄(여태 52건)은 unique 에 걸리지 않는다(NULL 은 서로 다르다). */
        Schema::table('privacy_consents', function (Blueprint $table) {
            $이미 = collect(Schema::getIndexes('privacy_consents'))->pluck('name');

            if (! $이미->contains('privacy_consents_source_ref_host_unique')) {
                $table->unique(['source_ref', 'source_host'], 'privacy_consents_source_ref_host_unique');
            }

            if (! $이미->contains('privacy_consents_signed_at_index')) {
                $table->index('signed_at', 'privacy_consents_signed_at_index');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('privacy_consents')) {
            return;
        }

        Schema::table('privacy_consents', function (Blueprint $table) {
            $이미 = collect(Schema::getIndexes('privacy_consents'))->pluck('name');

            if ($이미->contains('privacy_consents_source_ref_host_unique')) {
                $table->dropUnique('privacy_consents_source_ref_host_unique');
            }
            if ($이미->contains('privacy_consents_signed_at_index')) {
                $table->dropIndex('privacy_consents_signed_at_index');
            }
        });

        Schema::table('privacy_consents', function (Blueprint $table) {
            foreach (['signature_data', 'signed_at', 'source_ref', 'source_host'] as $칸) {
                if (Schema::hasColumn('privacy_consents', $칸)) {
                    $table->dropColumn($칸);
                }
            }
        });
    }
};
