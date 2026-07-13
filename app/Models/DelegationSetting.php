<?php
// app/Models/DelegationSetting.php
// 요양비 위임장 설정 (단일 행). 값이 없으면 config/delegation.php(.env) 기본값으로 시드.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DelegationSetting extends Model
{
    protected $fillable = [
        'provider_name', 'provider_biz_no', 'provider_ceo', 'provider_phone',
        'account_receiver', 'account_bank', 'account_holder', 'account_number',
        'period_years', 'sig_x', 'sig_y', 'sig_w',
    ];

    protected $casts = [
        'period_years' => 'integer',
        'sig_x'        => 'float',
        'sig_y'        => 'float',
        'sig_w'        => 'float',
    ];

    /**
     * 단일 설정 행 반환 (없으면 config/delegation.php 기본값으로 생성).
     */
    public static function current(): self
    {
        return static::firstOr(function () {
            return static::create([
                'provider_name'    => config('delegation.provider.name', ''),
                'provider_biz_no'  => config('delegation.provider.biz_no', ''),
                'provider_ceo'     => config('delegation.provider.ceo', ''),
                'provider_phone'   => config('delegation.provider.phone', ''),
                'account_receiver' => config('delegation.account.receiver', ''),
                'account_bank'     => config('delegation.account.bank', ''),
                'account_holder'   => config('delegation.account.holder', ''),
                'account_number'   => config('delegation.account.number', ''),
                'period_years'     => (int) config('delegation.period_years', 5),
                'sig_x'            => (float) config('delegation.signature.x', 164),
                'sig_y'            => (float) config('delegation.signature.y', 266),
                'sig_w'            => (float) config('delegation.signature.w', 28),
            ]);
        });
    }

    /**
     * DB 설정값으로 런타임 config('delegation.*') 를 덮어써서
     * 기존 config() 를 읽는 컨트롤러·블레이드가 그대로 DB 값을 쓰게 한다.
     */
    public static function applyToConfig(): self
    {
        $s = static::current();

        config([
            'delegation.provider' => [
                'name'   => $s->provider_name,
                'biz_no' => $s->provider_biz_no,
                'ceo'    => $s->provider_ceo,
                'phone'  => $s->provider_phone,
            ],
            'delegation.account' => [
                'receiver' => $s->account_receiver,
                'bank'     => $s->account_bank,
                'holder'   => $s->account_holder,
                'number'   => $s->account_number,
            ],
            'delegation.period_years' => $s->period_years,
            'delegation.signature' => [
                'x' => $s->sig_x,
                'y' => $s->sig_y,
                'w' => $s->sig_w,
            ],
        ]);

        return $s;
    }
}
