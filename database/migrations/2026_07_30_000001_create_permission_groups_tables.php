<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 권한 그룹 체계.
 *  - permission_groups       : 그룹(역할) 정의
 *  - permission_group_pages  : 그룹 × 페이지 × 액션 5종 허용 여부
 *  - users.permission_group_id : 사용자당 그룹 1개
 *
 * 배포 무중단 조건: '전체 권한' 그룹을 만들어 기존 사용자 전원에게 부여한다.
 * 따라서 마이그레이션 직후 동작은 지금과 완전히 동일하고, 관리자가 그룹을 새로
 * 만들어 배정할 때부터 제한이 걸린다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permission_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->string('description', 200)->nullable();
            // 전권 그룹: 페이지 행과 무관하게 모든 권한 허용. 편집·삭제 금지.
            $table->boolean('is_full_access')->default(false);
            $table->timestamps();
        });

        Schema::create('permission_group_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permission_group_id')->constrained()->cascadeOnDelete();
            $table->string('page_key', 60);
            $table->boolean('can_view')->default(false);
            $table->boolean('can_create')->default(false);
            $table->boolean('can_update')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->boolean('can_send')->default(false);
            $table->timestamps();

            $table->unique(['permission_group_id', 'page_key']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('permission_group_id')->nullable()->after('role')
                  ->constrained()->nullOnDelete();
        });

        // 무중단: 전권 그룹 생성 후 기존 사용자 전원 배정
        $fullId = DB::table('permission_groups')->insertGetId([
            'name'           => '전체 권한',
            'description'    => '모든 페이지와 모든 동작을 사용할 수 있는 기본 그룹입니다.',
            'is_full_access' => true,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        DB::table('users')->update(['permission_group_id' => $fullId]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['permission_group_id']);
            $table->dropColumn('permission_group_id');
        });

        Schema::dropIfExists('permission_group_pages');
        Schema::dropIfExists('permission_groups');
    }
};
