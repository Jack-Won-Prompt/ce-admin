<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 공통 코드 한 줄.
 *
 * 무슨 목록을 담는지는 config/common-codes.php 가 정한다 — 목록을 늘릴 때 이 파일을
 * 고칠 일이 없다.
 */
class CommonCode extends Model
{
    protected $fillable = [
        'group', 'kind', 'code', 'label', 'note', 'sort_order', 'is_active', 'is_system', 'created_by',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active'  => 'boolean',
        'is_system'  => 'boolean',
    ];

    /** 한 요청 안에서 같은 목록을 여러 번 묻는다 — 한 번만 읽는다 */
    private static array $memo = [];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeGroup($q, string $group)
    {
        return $q->where('group', $group);
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    /** 정의된 목록 전부 [key => ['label','hint','kinds']] */
    public static function groups(): array
    {
        return (array) config('common-codes.groups', []);
    }

    public static function groupOr404(string $key): array
    {
        $all = static::groups();
        abort_unless(isset($all[$key]), 404, '없는 코드 목록입니다.');

        return $all[$key];
    }

    /** 그 목록의 갈래 [key => label] */
    public static function kinds(string $group): array
    {
        return collect(static::groups()[$group]['kinds'] ?? [])
            ->map(fn ($k) => $k['label'] ?? '')->all();
    }

    /**
     * 쓸 수 있는 코드들. 갈래를 주면 그 갈래만.
     *
     * @return \Illuminate\Support\Collection<int, CommonCode>
     */
    public static function options(string $group, ?string $kind = null)
    {
        $all = static::$memo[$group] ??= static::group($group)->active()
            ->orderBy('kind')->orderBy('sort_order')->orderBy('id')->get();

        return $kind === null ? $all : $all->where('kind', $kind)->values();
    }

    /** 코드 → 이름 (저장된 값을 화면 글자로 옮길 때) */
    public static function labels(string $group): array
    {
        return static::options($group)->pluck('label', 'code')->all();
    }

    /** 지금 쓸 수 있는 코드 값들 — 저장 전에 이 안에 드는지 본다 */
    public static function codes(string $group, ?string $kind = null): array
    {
        return static::options($group, $kind)->pluck('code')->all();
    }

    /** 표를 고쳤으면 기억해 둔 것을 버린다 */
    public static function forget(?string $group = null): void
    {
        if ($group === null) {
            static::$memo = [];
        } else {
            unset(static::$memo[$group]);
        }
    }
}
