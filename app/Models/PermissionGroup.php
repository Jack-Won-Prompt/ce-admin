<?php
// app/Models/PermissionGroup.php
// 권한 그룹(역할). 사용자당 1개를 부여하고, 그룹 × 페이지 × 액션으로 권한이 결정된다.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PermissionGroup extends Model
{
    protected $fillable = ['name', 'description', 'is_full_access'];

    protected $casts = ['is_full_access' => 'boolean'];

    public function pages(): HasMany
    {
        return $this->hasMany(PermissionGroupPage::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** 전권 그룹은 편집·삭제할 수 없다(잠김 사고 방지). */
    public function isLocked(): bool
    {
        return $this->is_full_access;
    }

    /**
     * 페이지별 허용 액션 맵. ['prescriptions' => ['view' => true, 'create' => false, ...], ...]
     */
    public function permissionMatrix(): array
    {
        /* 액션표를 돌린다 — 손으로 적어 두면 액션을 더할 때마다 여기가 빠진다
           (2026-09-28 에 실제로 그랬다) */
        $matrix = [];
        foreach ($this->pages as $p) {
            $한줄 = [];
            foreach (PermissionGroupPage::ACTION_COLUMN as $액션 => $칸) {
                $한줄[$액션] = (bool) $p->{$칸};
            }
            $matrix[$p->page_key] = $한줄;
        }
        return $matrix;
    }
}
