<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
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
    /**
     * 어디로 들어올 수 있는가.
     *
     * 현장에서 서류만 올리는 사람에게 관리자 화면까지 열어 둘 까닭이 없고,
     * 사무실에서만 일하는 사람이 앱에 들어갈 까닭도 없다.
     */
    public const ACCESS_SCOPES = [
        'both' => '웹 · 모바일',
        'web'  => '웹만',
        'app'  => '모바일만',
    ];

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'access_scope',
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

    /**
     * 이 창구로 들어올 수 있는가. 'web' 또는 'app'.
     *
     * 칸이 아직 없는 서버(마이그레이션 전)에서는 모두 들어올 수 있다고 본다 —
     * 배포 순서 때문에 아무도 못 들어오는 일이 없어야 한다.
     */
    public function canEnter(string $platform): bool
    {
        $scope = $this->access_scope ?: 'both';

        return $scope === 'both' || $scope === $platform;
    }

    /** 이 서버에 access_scope 칸이 있는가(마이그레이션 전이면 없다). */
    public static function hasAccessScopeColumn(): bool
    {
        static $exists = null;
        return $exists ??= Schema::hasColumn('users', 'access_scope');
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
