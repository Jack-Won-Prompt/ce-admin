<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 청구처가 맡는 읍ㆍ면ㆍ동 하나.
 *
 * 한 지사가 여러 동을 맡는다. 미리 다 채우지 않고, 건을 처리하며 확인한 것만 쌓는다.
 */
class BillingOfficeArea extends Model
{
    protected $fillable = ['billing_office_id', 'sido', 'sigungu', 'emd'];

    public function office(): BelongsTo
    {
        return $this->belongsTo(BillingOffice::class, 'billing_office_id');
    }
}
