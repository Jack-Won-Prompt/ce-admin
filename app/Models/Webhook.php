<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 웹훅 정의 — 무엇을 어디로 주고받는가 (2026-09-10 지시).
 *
 * 여태 이 얼개가 코드 안에만 있었다. 무엇을 어디로 받는지 알려면 파일을 뒤져야 했고,
 * 실제로 오는지는 서버 로그를 열어야 알 수 있었다.
 */
class Webhook extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'provider', 'name', 'event_code', 'direction', 'url', 'http_method',
        'is_active', 'secret_env', 'description', 'note', 'sort',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort'      => 'integer',
    ];

    public function params(): HasMany
    {
        return $this->hasMany(WebhookParam::class)->orderBy('sort')->orderBy('id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(WebhookLog::class);
    }

    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function updater(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }

    public function getProviderLabelAttribute(): string
    {
        return config('webhooks.providers')[$this->provider] ?? $this->provider;
    }

    public function getDirectionLabelAttribute(): string
    {
        return config('webhooks.directions')[$this->direction] ?? $this->direction;
    }

    /** 받는 자리는 우리 주소다 — 상대에게 알려 줄 때는 도메인을 붙여 준다 */
    public function getFullUrlAttribute(): string
    {
        $url = (string) $this->url;

        if ($url === '' || str_starts_with($url, 'http')) {
            return $url;
        }

        return rtrim((string) config('app.url'), '/') . '/' . ltrim($url, '/');
    }

    /**
     * 이 알림을 받을 정의를 찾는다 — 로그를 그 줄에 붙이려고 본다.
     *
     * 이벤트 이름이 맞는 것이 먼저고, 없으면 그 구분의 받는 자리 가운데 하나다.
     * 못 찾아도 로그는 남긴다 — 모르는 이벤트가 온 것이야말로 봐야 할 일이다.
     */
    public static function 찾기(string $provider, ?string $eventCode, string $direction = 'inbound'): ?self
    {
        $q = static::where('provider', $provider)->where('direction', $direction);

        if ($eventCode) {
            $맞는것 = (clone $q)->where('event_code', $eventCode)->first();
            if ($맞는것) {
                return $맞는것;
            }
        }

        return (clone $q)->whereNull('event_code')->orderBy('sort')->first();
    }
}
