<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 되돌리는 사유와 그 사유가 정하는 것들 (요청서 6쪽, 2026-08-31).
 *
 * 사유가 정해지면 두 가지가 함께 정해진다 — 금액을 조정하는가, 발행 내역에 넣는가.
 * 담당자가 매번 판단하면 사람마다 달라지고, 고객에게 안내한 내용도 갈린다.
 *
 * 배송비 부담도 여기서 정했으나 걷었다. 배송비는 없다(2026-09-03 확정).
 *
 * 코드 안의 배열에서 표로 옮긴 까닭 — 요청서가 사유마다 규칙을 정해 달라 했고, 그것은
 * 앞으로도 바뀔 값이다. 바뀔 때마다 배포하게 두면 결국 아무도 안 고친다.
 */
class ReturnReason extends Model
{
    protected $fillable = [
        'code', 'label', 'adjusts_amount', 'includes_issue', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'adjusts_amount' => 'boolean',
        'includes_issue' => 'boolean',
        'is_active'      => 'boolean',
        'sort_order'     => 'integer',
    ];

    /**
     * 코드로 찾아 쓰는 표.
     *
     * 한 요청 안에서 여러 번 부른다(목록의 줄마다). 그때마다 묻지 않는다.
     *
     * @return \Illuminate\Support\Collection<string, self>
     */
    public static function table(): \Illuminate\Support\Collection
    {
        static $cache = null;

        return $cache ??= static::orderBy('sort_order')->get()->keyBy('code');
    }

    /** 그 사유가 금액을 조정하는가 — 모르는 코드는 조정하는 쪽으로 둔다 */
    public static function adjusts(?string $code): bool
    {
        return static::table()[$code]?->adjusts_amount ?? true;
    }

    /** 그 사유가 발행 내역에 드는가 — 모르는 코드는 드는 쪽으로 둔다 */
    public static function includes(?string $code): bool
    {
        return static::table()[$code]?->includes_issue ?? true;
    }
}
