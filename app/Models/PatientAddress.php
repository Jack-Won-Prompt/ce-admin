<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 환자 주소 한 줄 — 언제 어디였는지.
 *
 * 주소를 한 벌만 두면 이사한 뒤에는 지난 주문이 어디로 갔는지 되짚을 수 없다.
 * 바뀔 때마다 한 줄씩 쌓고, 가장 최근 것이 환자 칸의 주소와 같다.
 */
class PatientAddress extends Model
{
    protected $fillable = ['patient_id', 'postcode', 'address', 'address_detail', 'created_by'];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** 한 줄로 적는 주소 — (우편번호) 도로명 상세 */
    public function getFullAttribute(): string
    {
        return trim(implode(' ', array_filter([
            $this->postcode ? '(' . $this->postcode . ')' : '',
            $this->address,
            $this->address_detail,
        ]))) ?: '';
    }

    /** 같은 주소인가 — 같은 것을 두 줄로 쌓지 않으려고 견준다 */
    public function sameAs(?string $postcode, ?string $address, ?string $detail): bool
    {
        return (string) $this->postcode       === (string) $postcode
            && (string) $this->address        === (string) $address
            && (string) $this->address_detail === (string) $detail;
    }
}
