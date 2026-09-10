<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 오간 웹훅 한 건 (2026-09-10 지시).
 *
 * 값을 그대로 담되 열쇠가 될 만한 이름은 가려서 담는다(config/webhooks.mask_keys) —
 * 로그는 담당자가 보는 자리고, 여기서 새면 서명 위조나 결제 조작으로 이어진다.
 */
class WebhookLog extends Model
{
    protected $fillable = [
        'webhook_id', 'provider', 'event_code', 'direction', 'url', 'http_method',
        'ok', 'http_status', 'signature_ok', 'headers', 'payload', 'response',
        'error', 'duration_ms', 'ip', 'ref', 'occurred_at',
    ];

    protected $casts = [
        'ok'           => 'boolean',
        'signature_ok' => 'boolean',
        'headers'      => 'array',
        'occurred_at'  => 'datetime',
    ];

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }

    public function getProviderLabelAttribute(): string
    {
        return config('webhooks.providers')[$this->provider] ?? $this->provider;
    }

    public function getDirectionLabelAttribute(): string
    {
        return config('webhooks.directions')[$this->direction] ?? $this->direction;
    }

    /** 성공ㆍ실패는 말로 적는다 — ○/× 는 인쇄와 엑셀에서 뜻을 잃는다 */
    public function getResultLabelAttribute(): string
    {
        return $this->ok ? '성공' : '실패';
    }

    /**
     * 가릴 것은 가리고 담는다.
     *
     * 이름이 열쇠처럼 보이면 값을 (가림)으로 바꾼다. 묶음 안쪽까지 훑는다 —
     * 토스는 secret 을 data 안에 넣어 보낸다.
     */
    public static function 가리기(mixed $값): mixed
    {
        if (! is_array($값)) {
            return $값;
        }

        $가릴것 = array_map('strtolower', (array) config('webhooks.mask_keys', []));
        $결과   = [];

        foreach ($값 as $k => $v) {
            $이름 = strtolower((string) $k);
            $숨김 = false;

            foreach ($가릴것 as $자물쇠) {
                if (str_contains($이름, $자물쇠)) {
                    $숨김 = true;
                    break;
                }
            }

            $결과[$k] = $숨김 ? '(가림)' : (is_array($v) ? self::가리기($v) : $v);
        }

        return $결과;
    }
}
