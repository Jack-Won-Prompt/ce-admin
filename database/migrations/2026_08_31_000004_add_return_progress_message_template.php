<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 교환·반품·취소 진행 안내 문구 (요청서 4쪽, 2026-08-31).
 *
 * 코드에도 기본 문구를 두었지만, 그것은 유형이 없을 때의 대비다. 담당자가 화면에서
 * 고칠 수 있어야 손으로 보낼 때와 문구가 갈리지 않는다.
 *
 * 알림톡은 넣지 않는다 — 팝빌에 틀을 올리고 그 코드(ats_template_code)를 받아야 나가는데,
 * 우리가 미리 정할 수 없다. 담당자가 올린 뒤 메시지 유형 화면에서 같은 코드로 만들면
 * ReturnPatientNotice 가 알아서 알림톡으로 보낸다.
 */
return new class extends Migration
{
    private const CODE = 'return_progress';

    public function up(): void
    {
        if (DB::table('message_templates')->where('channel', 'sms')->where('code', self::CODE)->exists()) {
            return;
        }

        DB::table('message_templates')->insert([
            'channel'     => 'sms',
            'code'        => self::CODE,
            'label'       => '교환·반품 진행 안내',
            'description' => '접수한 교환·반품·취소가 어디까지 왔는지 환자에게 알립니다.',
            'body'        => "[콜로플라스트] #{고객명}님, 접수하신 #{유형} 건이 #{상태} 상태입니다.\n"
                           . "접수번호: #{접수번호}",
            'sort_order'  => 90,
            'is_active'   => 1,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('message_templates')->where('channel', 'sms')->where('code', self::CODE)->delete();
    }
};
