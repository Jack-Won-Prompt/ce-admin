<?php

namespace App\Support;

use App\Models\Prescription;
use App\Models\PrescriptionAttachment;
use App\Models\PrescriptionConsent;
use Illuminate\Support\Facades\Storage;

/**
 * 올려 둔 등록신청서 그림에 ③ 신청인란을 얹는다 (2026-09-17 지시).
 *
 * 등록신청서(자가도뇨 소모성 재료 급여대상자 등록 신청서 · 별지 제4호서식)는 병원이
 * ② 요양기관 확인란을 적고 확인해 내주는 종이다. 그 종이를 찍어 올리면 아래쪽
 * ③ 신청인란 — 신청인ㆍ수진자와의 관계ㆍ전화번호ㆍ서명 — 은 비어 있다.
 *
 * 위임장이 하는 일과 같다. 받아 둔 전자서명(위임 동의)과 우리가 아는 값을 그 자리에
 * 얹어, 공단으로 그대로 나갈 수 있는 한 장을 만든다.
 *
 * **원본은 지우지 않는다.** 얹은 그림을 첨부 자리에 놓고(그래야 팩스ㆍ내려받기가
 * 그대로 그것을 집는다) 원본은 overlay_source_path 에 남겨, 자리를 다시 잡거나
 * 되돌릴 수 있게 한다.
 *
 * 이 서버에는 Imagick 도 Ghostscript 도 없다 — GD 로 그린다. 그래서 PDF 로 올라온
 * 등록신청서에는 얹지 않는다(화면이 먼저 가린다).
 */
final class RegistrationOverlay
{
    /** 그림에 쓰는 글꼴 — 위임장ㆍ등록신청서 PDF 와 같은 것이다 */
    private const FONT = 'fonts/NanumGothic.ttf';

    /**
     * 얹을 값 — 없는 것은 비워 둔다(지어내지 않는다).
     *
     * **신청인은 늘 수진자 본인이다** (2026-09-17 지시). 그래서 미성년 건이라도
     * 법정대리인으로 갈라 적지 않는다 — 신청인은 환자 이름, 관계는 「본인」,
     * 전화번호는 환자 번호다.
     *
     * (처음에는 위임장 서식을 따라 미성년이면 법정대리인ㆍ그 관계로 적었는데,
     *  이 서류의 신청인란은 그렇게 쓰지 않는다고 바로잡았다.)
     *
     * @return array<string, string>
     */
    public static function values(Prescription $rx): array
    {
        $rx->loadMissing('patient');
        $pt      = $rx->patient;
        $consent = self::consent($rx);

        /* 신청한 날 — 서명을 받은 날이 있으면 그날이다. 없으면 오늘.
           종이에 찍히는 날과 서명한 날이 다르면 어느 것이 맞는지 대조할 길이 없다. */
        $날 = $consent?->responded_at ?: now();

        return [
            'apply_y'   => $날->format('Y'),
            'apply_m'   => $날->format('n'),
            'apply_d'   => $날->format('j'),
            'applicant' => trim((string) ($pt?->bare_name ?: $consent?->patient_name)),
            'relation'  => '본인',
            'tel'       => PhoneNo::format($pt?->mobile ?: $consent?->patient_mobile) ?: '',
        ];
    }

    /** 글자로 얹는 칸들 — 서명만 그림이다 */
    public const 글자칸 = ['apply_y', 'apply_m', 'apply_d', 'applicant', 'relation', 'tel'];

    /** 받아 둔 서명 — 미성년이면 법정대리인의 것이다. 없으면 null */
    public static function signature(Prescription $rx): ?string
    {
        $consent = self::consent($rx);

        if (! $consent) {
            return null;
        }

        /* 신청인이 늘 본인이므로 본인 서명을 먼저 본다 (2026-09-17 지시).
           미성년 건에서 본인 서명이 없을 때만 법정대리인 서명을 얹는다 — 받아 둔
           것이 그것뿐인데 빈 칸으로 내보내면 공단이 되돌려 보낸다. */
        $sig = $consent->signature_data ?: $consent->guardian_signature_data;

        if (! $sig) {
            return null;
        }

        $raw   = preg_match('#^data:image/\w+;base64,(.+)$#s', (string) $sig, $m) ? $m[1] : $sig;
        $bytes = base64_decode($raw, true);

        return ($bytes === false || $bytes === '') ? null : $bytes;
    }

    /** 처음 세워 주는 자리 — 서식을 똑바로 스캔했을 때의 자리다 */
    public static function defaults(): array
    {
        return config('registration_form.overlay.fields');
    }

    /**
     * 얹어서 그림 바이트를 돌려준다.
     *
     * @param  array $fields  화면에서 잡은 자리 — 그림 크기에 대한 몫(0~1)
     * @param  int   $rot     돌린 각도(0ㆍ90ㆍ180ㆍ270 · 시계 방향). 화면이 보여 준
     *                        그대로 굳힌다 — 자리의 몫도 돌린 뒤 그림을 기준으로 온다.
     * @return array{bytes:string, mime:string, ext:string}
     */
    public static function compose(PrescriptionAttachment $att, array $fields, int $rot = 0): array
    {
        $경로 = $att->바탕그림경로();

        if (! $경로 || ! Storage::disk('public')->exists($경로)) {
            throw new \RuntimeException('바탕이 될 그림을 찾지 못했습니다.');
        }

        $바탕 = self::열기(Storage::disk('public')->path($경로));
        $바탕 = self::돌리기($바탕, $rot);
        $너비 = imagesx($바탕);
        $높이 = imagesy($바탕);

        /* 사진에서 잘라 낸 그림은 알파가 살아 있을 수 있다 — 그대로 지켜야
           서명의 배경이 검게 앉지 않는다 */
        imagealphablending($바탕, true);
        imagesavealpha($바탕, true);

        $검정 = imagecolorallocate($바탕, 17, 17, 17);
        $글꼴 = storage_path(self::FONT);
        $기본 = (float) config('registration_form.overlay.size', 0.0107);

        $rx = $att->prescription;
        $값 = $rx ? self::values($rx) : [];

        /* **$fields 에 있는 칸만 얹는다.** 화면에서 지운 칸은 여기 오지 않는다 —
           서식에 이미 적혀 있는 값(병원이 손으로 써 준 것)과 겹치지 않게 하는 길이다. */
        foreach (self::글자칸 as $key) {
            $자리 = $fields[$key] ?? null;
            $글   = trim((string) ($값[$key] ?? ''));

            if (! $자리 || $글 === '') {
                continue;
            }

            /* 글자 크기는 칸마다 달리 잡을 수 있다(전화번호는 좁은 칸에 든다) */
            $pt = max(6, ($자리['size'] ?? $기본) * $높이 * 0.75);   // px → pt
            $x  = (int) round($자리['x'] * $너비);
            $y  = (int) round($자리['y'] * $높이 + $pt * 1.0);       // 기준선은 글 아래다

            if (is_file($글꼴)) {
                imagettftext($바탕, $pt, 0, $x, $y, $검정, $글꼴, $글);
            } else {
                /* 글꼴이 없으면 한글이 깨지므로 그리지 않는다 — 깨진 글자를
                   공단에 보내느니 빈 칸이 낫다 */
                \Illuminate\Support\Facades\Log::warning(
                    '[등록신청서 얹기] 한글 글꼴이 없어 글자를 얹지 못했습니다',
                    ['font' => $글꼴]
                );
            }
        }

        if ($rx && ($자리 = $fields['signature'] ?? null)) {
            self::서명얹기($바탕, $rx, $자리, $너비, $높이);
        }

        return self::내보내기($바탕, $att);
    }

    // ──────────────────────────────────────────────────────────

    /** 이 건에서 서명을 받아 둔 동의 — 없으면 null */
    private static function consent(Prescription $rx): ?PrescriptionConsent
    {
        return PrescriptionConsent::where('prescription_id', $rx->id)
            ->where('status', 'agreed')
            ->whereNotNull('signature_data')
            ->latest('id')
            ->first();
    }

    private static function 서명얹기($바탕, Prescription $rx, array $자리, int $너비, int $높이): void
    {
        $png = self::signature($rx);

        if (! $png) {
            return;
        }

        $서명 = @imagecreatefromstring($png);

        if ($서명 === false) {
            return;
        }

        imagealphablending($서명, true);
        imagesavealpha($서명, true);

        $w = max(1, (int) round(($자리['w'] ?? 0.086) * $너비));
        $h = max(1, (int) round($w * imagesy($서명) / imagesx($서명)));
        $x = (int) round($자리['x'] * $너비);
        $y = (int) round($자리['y'] * $높이);

        imagecopyresampled($바탕, $서명, $x, $y, 0, 0, $w, $h, imagesx($서명), imagesy($서명));
    }

    /**
     * 시계 방향으로 돌린다 (2026-09-17 지시).
     *
     * 세워서 찍거나 옆으로 스캔한 서류가 들어온다. 화면에서 바로 세우고, 저장할 때
     * 그 각도 그대로 굳힌다 — 화면과 나가는 종이가 달라서는 안 된다.
     *
     * GD 의 imagerotate 는 **반시계** 방향이라 각도를 뒤집어 넘긴다.
     */
    private static function 돌리기($img, int $rot)
    {
        $rot = ((int) $rot % 360 + 360) % 360;

        if (! in_array($rot, [90, 180, 270], true)) {
            return $img;                      // 0 이거나 알 수 없는 각도면 그대로
        }

        $돌린것 = imagerotate($img, -$rot, imagecolorallocatealpha($img, 0, 0, 0, 127));

        if ($돌린것 === false) {
            return $img;
        }

        imagealphablending($돌린것, true);
        imagesavealpha($돌린것, true);

        return $돌린것;
    }

    /** 무엇으로 왔든 GD 그림으로 연다 */
    private static function 열기(string $path)
    {
        $img = @imagecreatefromstring((string) file_get_contents($path));

        if ($img === false) {
            throw new \RuntimeException('그림을 열지 못했습니다 — 사진이나 스캔 파일인지 확인해 주십시오.');
        }

        return $img;
    }

    /**
     * 올라온 것과 같은 갈래로 내보낸다.
     *
     * JPEG 로 온 것을 PNG 로 바꾸면 파일이 몇 배로 커진다 — 팩스로 나갈 때
     * 쪽마다 굽는 시간이 그만큼 길어진다.
     */
    private static function 내보내기($img, PrescriptionAttachment $att): array
    {
        $png = str_contains((string) $att->file_mime_type, 'png');

        ob_start();
        if ($png) {
            imagesavealpha($img, true);
            imagepng($img, null, 6);
        } else {
            imagejpeg($img, null, 92);
        }
        $bytes = (string) ob_get_clean();

        return [
            'bytes' => $bytes,
            'mime'  => $png ? 'image/png' : 'image/jpeg',
            'ext'   => $png ? 'png' : 'jpg',
        ];
    }
}
