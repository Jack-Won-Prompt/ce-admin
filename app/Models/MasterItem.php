<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 마스터 항목 (병원ㆍ대리점).
 *
 * 어떤 칸을 무슨 이름으로 쓸지는 config/masters.php 가 정한다. 카테고리를 늘릴 때
 * 이 파일을 고칠 일이 없다.
 */
class MasterItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category', 'code', 'name', 'biz_no', 'ceo', 'manager',
        'phone', 'fax', 'email', 'address', 'note', 'is_active', 'sort_order', 'created_by',
    ];

    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];

    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public function scopeCategory($q, string $category) { return $q->where('category', $category); }
    public function scopeActive($q)                     { return $q->where('is_active', true); }

    /** 정의된 카테고리 전부 [key => ['label','desc','fields']] */
    public static function categories(): array
    {
        return (array) config('masters.categories', []);
    }

    public static function categoryOr404(string $key): array
    {
        $all = static::categories();
        abort_unless(isset($all[$key]), 404, '없는 카테고리입니다.');
        return $all[$key];
    }

    /** 그 카테고리에서 실제로 쓰는 칸 이름들 */
    public static function fieldKeys(string $category): array
    {
        return array_keys((array) (static::categories()[$category]['fields'] ?? []));
    }
}
