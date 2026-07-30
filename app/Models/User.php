<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'permission_group_id',
        'is_active',
        'toured_pages',
        'fcm_token',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
            'toured_pages'      => 'array',
        ];
    }

    /** 부여된 권한 그룹 (사용자당 1개, 미지정 가능) */
    public function permissionGroup(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PermissionGroup::class, 'permission_group_id');
    }

    /** 권한 판정 단축 — 내부적으로 PermissionService 를 쓴다 */
    public function canDo(string $page, string $action = 'view'): bool
    {
        return app(\App\Services\PermissionService::class)->allows($this, $page, $action);
    }

    public function markPageTouredIfNew(string $pageKey): void
    {
        $pages = $this->toured_pages ?? [];
        if (!in_array($pageKey, $pages, true)) {
            $pages[] = $pageKey;
            $this->update(['toured_pages' => $pages]);
        }
    }
}
