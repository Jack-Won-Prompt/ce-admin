<?php
// app/Models/MedicalAidClaimSetting.php
// 요양비 지급청구서[별지 제12호] 설정 (단일 행). 위임장 설정과 같은 방식이다.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicalAidClaimSetting extends Model
{
    protected $fillable = ['field_positions', 'sig_x', 'sig_y', 'sig_w'];

    protected $casts = [
        'field_positions' => 'array',
        'sig_x'           => 'float',
        'sig_y'           => 'float',
        'sig_w'           => 'float',
    ];

    /** 이 서식은 **가로 A4** 다 — 화면의 한계값도 이것을 본다 */
    public const 가로 = 297;
    public const 세로 = 210;

    /** 설정 파일에 적힌 기본 좌표. 요청 한 번에 한 번만 읽는다. */
    private static ?array $fileFields = null;

    /**
     * 기본 좌표는 반드시 '파일' 에서 읽는다.
     *
     * applyToConfig() 가 config('medical_aid_claim.fields') 를 병합 결과로 덮어쓰므로,
     * 런타임 config 를 기준으로 삼으면 한 번 적용한 뒤에는 기본값을 잃는다 — 그러면
     * 담아 둔 좌표를 지워도 원래 자리로 돌아가지 못한다(위임장에서 겪은 그대로다).
     */
    public static function defaultFields(): array
    {
        if (static::$fileFields === null) {
            $cfg = require config_path('medical_aid_claim.php');
            static::$fileFields = (array) ($cfg['fields'] ?? []);
        }

        return static::$fileFields;
    }

    /**
     * 글자 칸의 좌표 — 파일 기본값 위에 DB 값을 덮는다.
     *
     * 칸이 코드에 늘어도 DB 를 손대지 않아도 되고, DB 에 옛 칸이 남아 있어도
     * config 에 없으면 지나간다. 라벨은 늘 config 것을 쓴다(화면 문구는 코드가 쥔다).
     */
    public function fields(): array
    {
        $base  = static::defaultFields();
        $saved = (array) ($this->field_positions ?? []);

        foreach ($base as $key => $def) {
            $s = $saved[$key] ?? null;
            if (! is_array($s)) {
                continue;
            }
            foreach (['x', 'y', 'size'] as $k) {
                if (isset($s[$k]) && is_numeric($s[$k])) {
                    $base[$key][$k] = (float) $s[$k];
                }
            }
        }

        return $base;
    }

    /** 단일 설정 줄 — 없으면 설정 파일 기본값으로 만든다 */
    public static function current(): self
    {
        return static::firstOr(function () {
            $sig = (array) config('medical_aid_claim.signature', []);

            return static::create([
                'field_positions' => null,
                'sig_x' => $sig['x'] ?? 168,
                'sig_y' => $sig['y'] ?? 164.5,
                'sig_w' => $sig['w'] ?? 20,
            ]);
        });
    }

    /**
     * 담아 둔 값으로 런타임 config('medical_aid_claim.*') 를 덮는다.
     *
     * 서식을 그리는 자리(MedicalAidClaimForm::render)가 config 를 읽으므로,
     * 그 앞에서 한 번 부르면 화면에서 고친 자리가 그대로 종이에 간다.
     */
    public static function applyToConfig(): self
    {
        $s = static::current();

        config([
            'medical_aid_claim.fields'    => $s->fields(),
            'medical_aid_claim.signature' => [
                'x' => $s->sig_x,
                'y' => $s->sig_y,
                'w' => $s->sig_w,
            ],
        ]);

        return $s;
    }
}
