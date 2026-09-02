<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 첨부 서류에 「개인정보 동의서」를 더한다(요청서 2026-09-02).
 *
 * 전자서명이 안 되는 환자에게는 종이로 받는다. 그 종이를 올릴 자리가 없어 지금은
 * 「기타」로 올리고 있는데, 그러면 무엇을 받아 둔 것인지 목록에서 알 수 없다.
 *
 * 위임장과 같은 자리(etc)에 둔다 — 우리가 서식을 정해 받는 종이라 담당자가
 * 이름을 바꾸거나 지울 것이 아니다(is_system).
 *
 * 곁들여 요양비 지급청구서의 kind 가 비어 있던 것을 청구 자료로 채운다. 어제 넣을
 * 때 빠뜨렸다 — 갈래가 없으면 서류 고르는 자리에서 어느 묶음에도 서지 않는다.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('common_codes')->updateOrInsert(
            ['group' => 'doc_type', 'code' => 'privacy_consent'],
            [
                'kind'       => 'etc',
                'label'      => '개인정보 동의서',
                'sort_order' => 20,
                'is_active'  => true,
                'is_system'  => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        DB::table('common_codes')
            ->where('group', 'doc_type')
            ->where('code', 'medical_aid_claim')
            ->whereNull('kind')
            ->update(['kind' => 'claim', 'sort_order' => 70, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('common_codes')
            ->where('group', 'doc_type')
            ->where('code', 'privacy_consent')
            ->delete();
    }
};
