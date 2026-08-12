<?php

namespace App\Support;

use App\Models\UserActivityLog;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * 주민등록번호 처리 — P0-1
 *
 * 이 클래스 밖에서 주민번호를 암호화하거나 복호화하지 않는다.
 * 복호화는 사유 코드를 반드시 받으며 그 즉시 감사로그에 남는다.
 */
final class ResidentNo
{
    private static ?Encrypter $encrypter = null;

    /** 숫자 13자리만 남긴다. 13자리가 아니면 주민번호로 보지 않는다. */
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $digits = preg_replace('/\D/', '', $value);

        return strlen($digits) === 13 ? $digits : null;
    }

    /** 화면·목록·엑셀에 쓰는 표기. 앞 7자리까지만 드러낸다. */
    /**
     * 마스킹된 값만으로 생년월일을 뽑는다 — '531019-2******' → 1953-10-19.
     *
     * 복호화하지 않는다. 앞 7자리는 마스킹에도 남아 있고, 나이를 알려고 원문을 여는 것은
     * 과한 처리다(P0-1). 뒷자리 첫 숫자가 세기와 국적을 가른다.
     *   1·2·5·6 → 1900년대   3·4·7·8 → 2000년대   9·0 → 1800년대
     */
    public static function birthDateFromMasked(?string $masked): ?\Carbon\Carbon
    {
        if (!preg_match('/^(\d{2})(\d{2})(\d{2})\s*-?\s*(\d)/', (string) $masked, $m)) {
            return null;
        }
        [, $yy, $mm, $dd, $g] = $m;

        $century = match ((int) $g) {
            1, 2, 5, 6 => 1900,
            3, 4, 7, 8 => 2000,
            9, 0       => 1800,
            default    => null,
        };
        if ($century === null) return null;

        $y = $century + (int) $yy;
        if (!checkdate((int) $mm, (int) $dd, $y)) return null;

        return \Carbon\Carbon::createFromDate($y, (int) $mm, (int) $dd)->startOfDay();
    }

    /** 만 나이가 기준보다 어린가. 생년월일을 못 읽으면 null — '모른다'와 '아니다'는 다르다. */
    public static function isMinorByMasked(?string $masked, ?int $age = null): ?bool
    {
        $birth = self::birthDateFromMasked($masked);
        if (!$birth) return null;

        return $birth->age < ($age ?? (int) config('delegation.minor_age', 19));
    }

    public static function mask(?string $value): ?string
    {
        $d = self::normalize($value);
        if ($d !== null) {
            return substr($d, 0, 6) . '-' . substr($d, 6, 1) . str_repeat('*', 6);
        }

        // 13자리가 아닌 값(OCR 오인식 등). 숫자만 뽑아 이어붙이면 '9OO101-1234561' 이
        // '910112-3******' 처럼 실재하지 않는 그럴듯한 앞자리로 둔갑한다.
        // 원문 위치를 그대로 두고 8번째 글자부터만 가린다.
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        return mb_substr($raw, 0, 7) . str_repeat('*', max(6, mb_strlen($raw) - 7));
    }

    /**
     * 조회용 해시. pepper 를 섞은 HMAC-SHA256.
     * 주민번호는 경우의 수가 좁아 순수 SHA-256 은 전수 대입으로 역산된다.
     */
    public static function hash(?string $value): ?string
    {
        $d = self::normalize($value);
        if ($d === null) {
            return null;
        }
        $pepper = (string) config('rrn.pepper');
        if ($pepper === '') {
            throw new RuntimeException('RRN_HASH_PEPPER 가 설정되지 않았습니다.');
        }

        return hash_hmac('sha256', $d, $pepper);
    }

    /** 암호문. 저장 컬럼은 VARBINARY 다. */
    public static function encrypt(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::encrypter()->encryptString($value);
    }

    /**
     * 복호화 — 사유 코드가 반드시 필요하다.
     *
     * @param string $reason config('rrn.decrypt_reasons') 에 등록된 코드
     */
    public static function decrypt(?string $payload, string $reason, array $target = []): ?string
    {
        if ($payload === null || $payload === '') {
            return null;
        }

        $allowed = (array) config('rrn.decrypt_reasons', []);
        if (!array_key_exists($reason, $allowed)) {
            throw new RuntimeException("허용되지 않은 복호화 사유 코드입니다: {$reason}");
        }

        $plain = self::encrypter()->decryptString($payload);
        self::audit($reason, $allowed[$reason], $target);

        return $plain;
    }

    /**
     * 보유 기한 = 기산점 + RoPA 기재 연수.
     * 기산점은 신규 주문이 생길 때마다 갱신되어야 한다(문서 §보존기간).
     */
    public static function retentionUntil(?\DateTimeInterface $basis): ?\Illuminate\Support\Carbon
    {
        if ($basis === null) {
            return null;
        }

        return \Illuminate\Support\Carbon::instance(
            \DateTimeImmutable::createFromInterface($basis)
        )->addYears((int) config('rrn.retention.years', 5));
    }

    /** 주민등록번호 검증부호(체크디지트) 확인 — 가공값 판별에 쓴다. */
    public static function checksumValid(?string $value): bool
    {
        $d = self::normalize($value);
        if ($d === null) {
            return false;
        }
        $w = [2, 3, 4, 5, 6, 7, 8, 9, 2, 3, 4, 5];
        $s = 0;
        for ($i = 0; $i < 12; $i++) {
            $s += $w[$i] * (int) $d[$i];
        }

        return ((11 - ($s % 11)) % 10) === (int) $d[12];
    }

    /* ── 내부 ────────────────────────────────────────────── */

    private static function encrypter(): Encrypter
    {
        if (self::$encrypter !== null) {
            return self::$encrypter;
        }

        $key = (string) config('rrn.key');
        if ($key === '') {
            throw new RuntimeException(
                'RRN_ENCRYPTION_KEY 가 설정되지 않았습니다. 주민번호를 평문으로 다루지 않도록 처리를 중단합니다.'
            );
        }
        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7), true) ?: '';
        }

        return self::$encrypter = new Encrypter($key, (string) config('rrn.cipher', 'AES-256-CBC'));
    }

    /** 복호화 1회 = 감사로그 1행. 기록에 실패해도 복호화 자체를 막지는 않되 남긴다. */
    private static function audit(string $reason, string $reasonText, array $target): void
    {
        try {
            UserActivityLog::create([
                'user_id'         => Auth::id(),
                'type'            => 'unmask',
                'action'          => 'unmask',
                'menu_name'       => $target['menu'] ?? null,
                'route_name'      => request()?->route()?->getName(),
                'url'             => request()?->fullUrl(),
                'ip_address'      => request()?->ip(),
                'user_agent'      => substr((string) request()?->userAgent(), 0, 300) ?: null,
                'target_type'     => $target['type'] ?? null,
                'target_id'       => $target['id'] ?? null,
                'record_count'    => $target['count'] ?? 1,
                'reason_code'     => $reason,
                'reason_text'     => $reasonText,
                'retention_until' => now()->addYears(3)->toDateString(),   // 감사로그 3년 (PIPC 고시 2023-6)
            ]);
        } catch (\Throwable $e) {
            Log::error('주민번호 복호화 감사로그 기록 실패', [
                'reason' => $reason,
                'error'  => $e->getMessage(),
            ]);
        }
    }
}
