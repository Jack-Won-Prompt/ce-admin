<?php

namespace App\Support;

use App\Models\CommonCode;

/**
 * 처방자료 업로드에서 고를 수 있는 서류 유형.
 *
 * 웹 업로드 화면과 모바일 앱이 **같은 목록**을 쓴다. 예전에는 앱이 목록을 코드에
 * 박아 두어, 환경 설정(공통 코드)에서 유형을 늘리거나 이름을 바꿔도 앱만 옛 목록을
 * 보였고, 서버는 앱이 보낸 유형을 거절했다.
 *
 * 처방 서류(rx)와 기타(etc) 갈래만 쓴다 — 청구 서류(claim)는 이 화면이 받는 것이 아니다.
 *
 * 위임장(delegation)은 빼 둔다. 주문 등록에서 환자가 서명하면 그때 저절로 만들어져
 * 서류 관리에 들어간다. 여기서 또 올리게 두면 같은 위임장이 두 장이 되고, 어느 것이
 * 서명본인지 알 수 없다.
 */
final class UploadDocTypes
{
    private const KINDS    = ['rx', 'etc'];
    private const EXCLUDED = ['delegation'];

    /** 갈래별로 묶은 목록 — 웹 업로드 화면이 이 꼴로 쓴다. ['rx' => [[code,label]...], 'etc' => [...]] */
    public static function grouped(): array
    {
        $out = [];
        foreach (self::KINDS as $kind) {
            $out[$kind] = CommonCode::options('doc_type', $kind)
                ->reject(fn ($c) => in_array($c->code, self::EXCLUDED, true))
                ->map(fn ($c) => ['code' => $c->code, 'label' => $c->label])
                ->values()
                ->all();
        }

        return $out;
    }

    /** 한 줄로 늘어놓은 목록 — 앱이 이 꼴로 받는다. */
    public static function list(): array
    {
        $out = [];
        foreach (self::grouped() as $kind => $items) {
            foreach ($items as $item) {
                $out[] = $item + ['kind' => $kind];
            }
        }

        return $out;
    }

    /** 받아도 되는 코드 — 업로드 검증에 쓴다. */
    public static function codes(): array
    {
        return array_column(self::list(), 'code');
    }
}
