<?php
// app/Models/PermissionGroupPage.php
// 권한 그룹 × 페이지 한 줄. 액션 5종의 허용 여부를 담는다.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PermissionGroupPage extends Model
{
    protected $fillable = [
        'permission_group_id', 'page_key',
        'can_view', 'can_create', 'can_update', 'can_delete', 'can_send',
    ];

    protected $casts = [
        'can_view'   => 'boolean',
        'can_create' => 'boolean',
        'can_update' => 'boolean',
        'can_delete' => 'boolean',
        'can_send'   => 'boolean',
    ];

    /** 액션명 → 컬럼명 */
    public const ACTION_COLUMN = [
        'view'   => 'can_view',
        'create' => 'can_create',
        'update' => 'can_update',
        'delete' => 'can_delete',
        'send'   => 'can_send',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(PermissionGroup::class, 'permission_group_id');
    }
}
