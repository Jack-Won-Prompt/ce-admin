<?php
// app/Models/RegistrationSetting.php
// 자가도뇨 소모성 재료 등록 신청서(별지 제4호서식) 설정 (단일 행).
// 값이 없으면 config/registration.php 기본값으로 시드한다 — 위임장 설정과 같은 얼개다.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegistrationSetting extends Model
{
    protected $fillable = [
        'sig_x', 'sig_y', 'sig_w', 'field_positions', 'check_positions',
    ];

    protected $casts = [
        'sig_x'           => 'float',
        'sig_y'           => 'float',
        'sig_w'           => 'float',
        'field_positions' => 'array',
        'check_positions' => 'array',
    ];

    /** 설정 파일에 적힌 기본 좌표. 요청 한 번에 한 번만 읽는다. */
    private static ?array $file = null;

    /**
     * 기본 좌표는 반드시 '파일' 에서 읽는다.
     *
     * applyToConfig() 가 config('registration.*') 를 병합 결과로 덮으므로, 런타임 config 를
     * 기준으로 삼으면 한 번 적용한 뒤에는 기본값을 잃는다 — 저장한 좌표를 지워도 원래
     * 자리로 돌아가지 못한다(위임장에서 겪은 자리다).
     */
    private static function fromFile(string $key): array
    {
        if (static::$file === null) {
            static::$file = (array) require config_path('registration.php');
        }
        return (array) (static::$file[$key] ?? []);
    }

    public static function defaultFields(): array
    {
        return static::fromFile('fields');
    }

    public static function defaultChecks(): array
    {
        return static::fromFile('checks');
    }

    /** 글자 항목의 좌표 — 파일 기본값 위에 DB 값을 덮는다. 라벨은 언제나 파일 것을 쓴다. */
    public function fields(): array
    {
        return static::merge(static::defaultFields(), (array) $this->field_positions, ['x', 'y', 'size']);
    }

    /** 체크 표시 자리 — 크기는 없다. 긋기 시작할 한 점뿐이다. */
    public function checks(): array
    {
        return static::merge(static::defaultChecks(), (array) $this->check_positions, ['x', 'y']);
    }

    /**
     * 항목이 코드에 늘어나도 DB 를 손대지 않아도 되고, DB 에 옛 항목이 남아 있어도
     * 파일에 없으면 무시된다.
     */
    private static function merge(array $base, array $saved, array $keys): array
    {
        foreach ($base as $key => $def) {
            $s = $saved[$key] ?? null;
            if (! is_array($s)) continue;
            foreach ($keys as $k) {
                if (isset($s[$k]) && is_numeric($s[$k])) $base[$key][$k] = (float) $s[$k];
            }
        }
        return $base;
    }

    /** 표가 아직 없는 서버인가 — 마이그레이션 전에도 서식은 나와야 한다. */
    public static function tableExists(): bool
    {
        static $has = null;
        if ($has === null) {
            try {
                $has = \Illuminate\Support\Facades\Schema::hasTable('registration_settings');
            } catch (\Throwable) {
                $has = false;
            }
        }
        return $has;
    }

    /** 단일 설정 행 (없으면 파일 기본값으로 만든다). */
    public static function current(): self
    {
        if (! static::tableExists()) {
            // 표가 없으면 담아 두지 않는다 — 파일 기본값만 쥔 채로 쓴다
            return new static([
                'sig_x' => (float) config('registration.signature.x', 151),
                'sig_y' => (float) config('registration.signature.y', 196),
                'sig_w' => (float) config('registration.signature.w', 28),
            ]);
        }

        return static::firstOr(fn () => static::create([
            'sig_x' => (float) config('registration.signature.x', 151),
            'sig_y' => (float) config('registration.signature.y', 196),
            'sig_w' => (float) config('registration.signature.w', 28),
        ]));
    }

    /**
     * DB 값으로 런타임 config('registration.*') 를 덮는다 — 서식을 그리는 쪽은 config 만 본다.
     *
     * 표가 아직 없는 서버에서는 파일 기본값 그대로다. 자리는 조금 어긋날 수 있어도
     * 서식은 나온다 — 마이그레이션을 기다리느라 팩스가 멈추지 않아야 한다.
     */
    public static function applyToConfig(): self
    {
        $s = static::current();

        config([
            'registration.signature' => ['x' => $s->sig_x, 'y' => $s->sig_y, 'w' => $s->sig_w],
            'registration.fields'    => $s->fields(),
            'registration.checks'    => $s->checks(),
        ]);

        return $s;
    }
}
