<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 청구처 — 공단 지사 또는 지자체 부서, 그리고 그 담당자.
 *
 * 한 줄이 「어디의 · 어느 부서의 · 누구에게 · 어떤 번호로」다. 같은 지사라도 담당업무가
 * 갈리면 번호가 다르므로(보험급여부만 스물두 줄) 담당자 단위로 쌓는다.
 *
 * 관할은 읍ㆍ면ㆍ동으로 적어 둔다(areas) — 환자 주소에서 그것만 뽑아 견주면 된다.
 */
class BillingOffice extends Model
{
    public const KIND_NHIS  = 'nhis';    // 건강보험공단 지사
    public const KIND_LOCAL = 'local';   // 지자체(시군구청)

    public const KINDS = [
        self::KIND_NHIS  => '건강보험공단',
        self::KIND_LOCAL => '지자체',
    ];

    protected $fillable = [
        'kind', 'region', 'office_name', 'dept', 'title', 'duty',
        'tel', 'fax', 'address', 'note', 'is_active', 'sort_order', 'created_by',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function areas(): HasMany
    {
        return $this->hasMany(BillingOfficeArea::class);
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeKind($q, ?string $kind)
    {
        return $kind ? $q->where('kind', $kind) : $q;
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? $this->kind;
    }

    /** 화면에 한 줄로 적을 이름 — 「마포지사 · 보험급여부」 */
    public function displayName(): string
    {
        return trim($this->office_name . ($this->dept ? ' · ' . $this->dept : ''));
    }

    /**
     * 주소에서 읍ㆍ면ㆍ동만 뽑는다.
     *
     * 「서울특별시 마포구 용강동 12-3」에서 「용강동」을 집는다. 도로명 주소에는
     * 읍면동이 없는 경우가 많아(「성암로 179」) 그때는 null 이다 — 못 뽑았으면
     * 못 뽑았다고 해야, 화면이 엉뚱한 관할을 들이밀지 않는다.
     */
    public static function emdFromAddress(?string $address): ?string
    {
        $addr = trim((string) $address);
        if ($addr === '') {
            return null;
        }

        // 동ㆍ읍ㆍ면으로 끝나는 낱말. 「중동」처럼 두 글자도 있어 최소 2자로 본다.
        if (preg_match_all('/([가-힣]{1,6}(?:[0-9]{0,2})?[동읍면])(?=\s|$)/u', $addr, $m)) {
            foreach ($m[1] as $cand) {
                // 「마포구 상암동」의 「…구」는 걸러진다. 「행정동」이 아닌 말도 함께 걸러 둔다.
                if (mb_strlen($cand) >= 2 && !preg_match('/(로동|가동)$/u', $cand)) {
                    return $cand;
                }
            }
        }

        return null;
    }

    /**
     * 주소에서 시군구를 뽑는다 — 읍면동이 겹칠 때 가려내는 데 쓴다.
     *
     * 「서울특별시 마포구 …」에서 앞에서부터 집으면 광역자치단체(서울특별시)가 잡힌다.
     * 우리가 원하는 것은 기초자치단체(마포구)라, 광역 이름은 걸러 내고 마지막 것을 쓴다
     * (「경기도 부천시 원미구」처럼 셋이 이어지면 구가 남는다).
     */
    public static function sigunguFromAddress(?string $address): ?string
    {
        $addr = trim((string) $address);
        if ($addr === '' || !preg_match_all('/([가-힣]{2,10}(?:시|군|구))(?=\s)/u', $addr, $m)) {
            return null;
        }

        $picked = null;
        foreach ($m[1] as $cand) {
            if (preg_match('/(특별시|광역시|특별자치시|특별자치도)$/u', $cand)) {
                continue;
            }
            $picked = $cand;
        }

        return $picked;
    }
}
