<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 병원(요양기관).
 *
 * 처방전마다 손으로 치던 병원명ㆍ요양기관번호를 한자리에 모은다. 주문 등록에서
 * 조회해 고르고, 없으면 그 자리에서 만들어 고른다 — 거래처를 다루는 법과 같다.
 */
class Hospital extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'code', 'tel', 'fax', 'address', 'department', 'memo', 'is_active', 'created_by',
    ];

    protected $casts = ['is_active' => 'boolean'];

    /** 이름ㆍ요양기관번호로 찾는다 — 둘 중 무엇으로 기억하고 있든 걸리게 한다 */
    public function scopeSearch($q, ?string $keyword)
    {
        $keyword = trim((string) $keyword);
        if ($keyword === '') {
            return $q;
        }

        return $q->where(function ($w) use ($keyword) {
            $w->where('name', 'like', "%{$keyword}%")
              ->orWhere('code', 'like', "%{$keyword}%");
        });
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
