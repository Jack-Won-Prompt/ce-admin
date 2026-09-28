<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 책임자 검수 승인 · 최종승인자 서명을 권한 그룹에 줄 수 있게 한다 (2026-09-28 지시).
 *
 * 권한 표는 액션 여섯(조회ㆍ등록ㆍ수정ㆍ삭제ㆍ발송ㆍ승인)을 **칸으로** 못박아 두었다.
 * config 에 액션 이름만 더해도 저장할 자리가 없어 체크가 사라진다 — 화면에서는
 * 체크했는데 저장하면 풀리는 꼴이 된다. 칸을 두 개 더한다.
 *
 * 이미 「승인」을 가진 그룹에는 두 권한을 함께 켜 둔다. 켜지 않으면 오늘 승인하던
 * 사람이 내일 못 누르게 된다 — 판정이 옛 approve 로 되돌아 막히지는 않지만(모델의
 * canApproveStep 이 둘을 함께 본다), 권한 화면에는 빈 칸으로 보여 헷갈린다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permission_group_pages', function (Blueprint $table) {
            $table->boolean('can_inspect_approve')->default(false)->after('can_approve');
            $table->boolean('can_final_approve')->default(false)->after('can_inspect_approve');
        });

        DB::table('permission_group_pages')
            ->where('page_key', 'order-returns')
            ->where('can_approve', true)
            ->update(['can_inspect_approve' => true, 'can_final_approve' => true]);
    }

    public function down(): void
    {
        Schema::table('permission_group_pages', function (Blueprint $table) {
            $table->dropColumn(['can_inspect_approve', 'can_final_approve']);
        });
    }
};
