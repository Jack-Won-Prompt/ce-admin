<?php

namespace App\Support;

/**
 * 제품관리번호(장비코드) — 공단에 청구할 때 제품을 가리키는 번호.
 *
 * 공단 요양기관정보마당은 우리 품번(28410)이 아니라 등록 장비코드(NBC0121000005)로
 * 조회한다. 담당자가 매번 별도 표를 열어 찾고 있어, 제품이 서는 자리마다 함께 세운다.
 *
 * 정본은 위드웍스다 — 그쪽 품목에 값을 넣으면 items API 가 함께 준다. 아직 그 칸이
 * 없어, 올 때까지는 config/device_codes.php 의 표로 채운다. API 가 주기 시작하면
 * 그 값이 이기므로 여기를 고칠 일은 없다.
 */
final class DeviceCode
{
    /**
     * 위드웍스 품목 응답에서 장비코드를 읽는다.
     *
     * 저쪽이 어떤 이름으로 줄지 아직 정해지지 않아 그럴듯한 이름을 함께 본다.
     * 어느 것도 없으면 우리 표에서 찾는다.
     */
    public static function fromItem(array $item): ?string
    {
        foreach (['device_code', 'equipment_code', 'nhis_device_code', 'item_mgmt_no', 'mgmt_no'] as $k) {
            $v = trim((string) ($item[$k] ?? ''));
            if ($v !== '') {
                return $v;
            }
        }

        return self::for($item['item_code'] ?? null);
    }

    /** 품번으로 찾는다 — 없으면 null. 지어내지 않는다. */
    public static function for(?string $productCode): ?string
    {
        $code = trim((string) $productCode);
        if ($code === '') {
            return null;
        }

        /* 품번이 「28410」처럼 숫자만이 아니라 「28410-A」로 적힌 건이 있다.
           앞의 숫자만 떼어 한 번 더 찾는다 — 장비코드는 품목마다 붙는 값이다. */
        return config("device_codes.{$code}")
            ?? (preg_match('/^\d+/', $code, $m) ? config("device_codes.{$m[0]}") : null);
    }
}
