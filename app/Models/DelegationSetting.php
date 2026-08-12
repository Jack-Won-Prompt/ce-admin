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
        'field_positions', 'gsig_x', 'gsig_y', 'gsig_w',
    ];

    protected $casts = [
        'period_years'    => 'integer',
        'sig_x'           => 'float',
        'sig_y'           => 'float',
        'sig_w'           => 'float',
        'field_positions' => 'array',
        'gsig_x'          => 'float',
        'gsig_y'          => 'float',
        'gsig_w'          => 'float',
    ];

    /** 설정 파일에 적힌 기본 좌표. 요청 한 번에 한 번만 읽는다. */
    private static ?array $fileFields = null;

    /**
     * 기본 좌표는 반드시 '파일' 에서 읽는다.
     *
     * applyToConfig() 가 config('delegation.fields') 를 병합 결과로 덮어쓰기 때문에,
     * 런타임 config 를 기준으로 삼으면 한 번 적용한 뒤에는 기본값을 잃는다.
     * 그러면 저장한 좌표를 지워도 원래 자리로 돌아가지 못한다.
     */
    public static function defaultFields(): array
    {
        if (static::$fileFields === null) {
            $cfg = require config_path('delegation.php');
            static::$fileFields = (array) ($cfg['fields'] ?? []);
        }
        return static::$fileFields;
    }

    /**
     * 글자 항목의 좌표 — 파일 기본값 위에 DB 값을 덮는다.
     *
     * 항목이 코드에 늘어나도 DB 를 손대지 않아도 되고, DB 에 옛 항목이 남아 있어도
     * config 에 없으면 무시된다. 라벨은 항상 config 것을 쓴다(화면 문구를 코드에서 관리).
     */
    public function fields(): array
    {
        $base  = static::defaultFields();
        $saved = (array) ($this->field_positions ?? []);

        foreach ($base as $key => $def) {
            $s = $saved[$key] ?? null;
            if (!is_array($s)) continue;
            foreach (['x', 'y', 'size'] as $k) {
                if (isset($s[$k]) && is_numeric($s[$k])) $base[$key][$k] = (float) $s[$k];
            }
        }
        return $base;
    }

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
            // 글자 항목 좌표 — 저장된 값이 config 기본값을 덮는다
            'delegation.fields' => $s->fields(),
            'delegation.guardian_signature' => [
                'x' => $s->gsig_x ?? config('delegation.guardian_signature.x', 164),
                'y' => $s->gsig_y ?? config('delegation.guardian_signature.y', 280),
                'w' => $s->gsig_w ?? config('delegation.guardian_signature.w', 28),
            ],
        ]);

        return $s;
    }
}
