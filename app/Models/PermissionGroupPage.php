<?php
// app/Models/PermissionGroupPage.php
// 권한 그룹 × 페이지 한 줄. 액션 6종의 허용 여부를 담는다.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PermissionGroupPage extends Model
{
    protected $fillable = [
        'permission_group_id', 'page_key',
        'can_view', 'can_create', 'can_update', 'can_delete', 'can_send', 'can_approve',
        'can_inspect_approve', 'can_final_approve',
    ];

    protected $casts = [
        'can_view'   => 'boolean',
        'can_create' => 'boolean',
        'can_update' => 'boolean',
        'can_delete' => 'boolean',
        'can_send'   => 'boolean',
        'can_approve' => 'boolean',
        'can_inspect_approve' => 'boolean',
        'can_final_approve'   => 'boolean',
    ];

    /** 액션명 → 컬럼명 */
    public const ACTION_COLUMN = [
        'view'   => 'can_view',
        'create' => 'can_create',
        'update' => 'can_update',
        'delete' => 'can_delete',
        'send'   => 'can_send',
        // 검수 완료ㆍ전자 승인 — 적는 사람과 승인하는 사람을 가른다
        'approve' => 'can_approve',
        /* 두 걸음 결재 (2026-09-28 지시) — 책임자와 최종승인자를 가른다.
           액션을 더할 때는 **칸도 함께 더해야 한다.** config 에 이름만 더하면
           화면에서는 체크되지만 저장할 자리가 없어 그대로 사라진다. */
        'inspect_approve' => 'can_inspect_approve',
        'final_approve'   => 'can_final_approve',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(PermissionGroup::class, 'permission_group_id');
    }
}
