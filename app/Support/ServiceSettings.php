<?php
// app/Support/ServiceSettings.php
//
// config/settings-schema.php 의 선언을 읽어
//   · 화면에 뿌릴 값을 모으고
//   · 관리자가 저장한 값을 DB 에 담고
//   · 부팅할 때 런타임 config() 를 덮어쓴다.
//
// 값이 DB 에 없으면 .env/config 기본값을 그대로 쓴다. 그래서 이 기능을 켜도
// 지금 돌고 있는 설정이 바뀌지 않는다 — 관리자가 저장한 항목만 바뀐다.

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ServiceSettings
{
    /** 그룹 선언 전체. */
    public static function schema(): array
    {
        return (array) config('settings-schema', []);
    }

    public static function group(string $key): ?array
    {
        return static::schema()[$key] ?? null;
    }

    /** 이 그룹이 전용 모델(예: NiceSetting)에 저장되는가. */
    public static function modelOf(string $group): ?string
    {
        $m = static::group($group)['model'] ?? null;
        return is_string($m) && class_exists($m) ? $m : null;
    }

    public static function isSecret(array $field): bool
    {
        return ($field['type'] ?? 'text') === 'password';
    }

    /**
     * 화면에 보여줄 현재 값.
     * 비밀값은 원문을 내려보내지 않고 '채워져 있는지'만 알린다.
     *
     * @return array<string, array{value: mixed, filled: bool}>
     */
    public static function valuesFor(string $group): array
    {
        $def = static::group($group);
        if (! $def) return [];

        $out = [];

        if ($model = static::modelOf($group)) {
            $row = $model::current();
            foreach ($def['fields'] as $key => $f) {
                $raw = $row->{$f['column'] ?? $key};
                $out[$key] = static::isSecret($f)
                    ? ['value' => null, 'filled' => trim((string) $raw) !== '']
                    : ['value' => $raw,  'filled' => true];
            }
            return $out;
        }

        $stored = static::storedRows($group);
        foreach ($def['fields'] as $key => $f) {
            $row   = $stored[$key] ?? null;
            $plain = $row?->plainValue();
            // DB 에 없으면 지금 돌고 있는 설정값(=.env 기본값)을 보여준다.
            $eff   = $plain !== null && $plain !== '' ? $plain : config($f['config']);

            $out[$key] = static::isSecret($f)
                ? ['value' => null, 'filled' => trim((string) $eff) !== '']
                : ['value' => static::castOut($eff, $f), 'filled' => true];
        }
        return $out;
    }

    /**
     * 관리자가 보낸 입력을 저장한다.
     * 비밀값은 빈칸이면 건드리지 않는다 — 화면이 원문을 모르기 때문에,
     * 빈칸을 '지우기'로 받으면 열어볼 때마다 키가 날아간다.
     *
     * @return int 바뀐 항목 수
     */
    public static function save(string $group, array $input): int
    {
        $def = static::group($group);
        if (! $def) return 0;

        if ($model = static::modelOf($group)) {
            return static::saveToModel($model, $def, $input);
        }

        $stored  = static::storedRows($group);
        $changed = 0;

        foreach ($def['fields'] as $key => $f) {
            $secret = static::isSecret($f);
            $type   = $f['type'] ?? 'text';

            if ($type === 'bool') {
                $new = array_key_exists($key, $input) ? '1' : '0';
            } else {
                if (! array_key_exists($key, $input)) continue;
                $new = trim((string) $input[$key]);
                if ($secret && $new === '') continue;   // 빈칸 = 그대로 두기
            }

            $row = $stored[$key] ?? new Setting(['group' => $group, 'key' => $key]);
            if ($row->exists && $row->plainValue() === $new && $row->is_secret === $secret) {
                continue;
            }

            $row->group = $group;
            $row->key   = $key;
            $row->setPlainValue($new, $secret);
            $row->save();
            $changed++;
        }

        return $changed;
    }

    /** 전용 모델에 저장하는 그룹(NICE). */
    private static function saveToModel(string $model, array $def, array $input): int
    {
        $row     = $model::current();
        $changed = 0;

        foreach ($def['fields'] as $key => $f) {
            $col  = $f['column'] ?? $key;
            $type = $f['type'] ?? 'text';

            if ($type === 'bool') {
                $new = array_key_exists($key, $input);
            } else {
                if (! array_key_exists($key, $input)) continue;
                $new = trim((string) $input[$key]);
                if (static::isSecret($f) && $new === '') continue;
            }

            if ((string) $row->{$col} === (string) $new) continue;
            $row->{$col} = $new;
            $changed++;
        }

        if ($changed) {
            $row->save();
            // 자격증명이 바뀌면 발급받아 둔 토큰은 더 이상 쓸 수 없다.
            if (method_exists($row, 'forgetAccessToken')) {
                $row->forgetAccessToken();
            }
        }
        return $changed;
    }

    /**
     * DB 에 저장된 값으로 런타임 config() 를 덮어쓴다.
     * 부팅 때 한 번 부른다. DB 를 아직 못 쓰는 상황(마이그레이션 전 등)이면
     * 조용히 넘어가고 .env 설정을 그대로 쓴다.
     */
    public static function applyToConfig(): void
    {
        try {
            $rows = Setting::all()->groupBy('group');
        } catch (\Throwable $e) {
            // 테이블이 아직 없거나(배포 직후 마이그레이션 전) DB 를 못 쓰는 상황.
            // 매 요청 hasTable 로 확인하면 쿼리가 하나씩 늘어나므로 예외로 받는다.
            return;
        }

        foreach (static::schema() as $group => $def) {
            if (static::modelOf($group)) continue;   // 전용 모델이 자기 시점에 적용한다

            foreach ($rows[$group] ?? [] as $row) {
                $f = $def['fields'][$row->key] ?? null;
                if (! $f || empty($f['config'])) continue;

                $plain = $row->plainValue();
                if ($plain === null) continue;       // 복호화 실패 → 기본값 유지

                config([$f['config'] => static::castOut($plain, $f)]);
            }
        }
    }

    /** 저장은 문자열로 하니, 쓰는 쪽 타입에 맞춰 되돌린다. */
    private static function castOut(mixed $v, array $f): mixed
    {
        return match ($f['type'] ?? 'text') {
            'bool' => filter_var($v, FILTER_VALIDATE_BOOLEAN),
            'int'  => $v === '' || $v === null ? null : (int) $v,
            default => $v,
        };
    }

    /** @return array<string, Setting> */
    private static function storedRows(string $group): array
    {
        return Setting::where('group', $group)->get()->keyBy('key')->all();
    }
}
