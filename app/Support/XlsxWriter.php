<?php

namespace App\Support;

/**
 * 엑셀 파일(.xlsx)을 짓는다.
 *
 * **왜 손으로 짓는가** — 이 저장소에는 엑셀을 쓰는 꾸러미가 없고(PhpSpreadsheet 가
 * `composer.json` 에 없다), 배포 흐름에 `composer install` 단계도 없다. 꾸러미를
 * 더해도 서버에 깔리지 않는다. `ZipArchive` 는 운영에 있다(2026-10-13 확인).
 *
 * 여태의 내보내기는 모두 CSV 였다. 그것으로 되는 일이면 CSV 가 낫다 — 이 자리는
 * **토스페이먼츠에 그대로 올려야 하는 파일**이라 .xlsx 여야 한다.
 *
 * 담는 것은 평평한 표뿐이다. 수식ㆍ그림은 담지 않는다. 장은 여럿 담을 수 있고,
 * 칸 너비와 고르는 목록(데이터 유효성)까지는 담는다 — 토스 양식이 그 둘을 쓴다.
 *
 * ## 긴 숫자는 **정수칸**으로 담는다 (2026-10-15)
 *
 * 엑셀의 기본 서식(General)은 **열한 자리까지만** 자릿수 그대로 보이고, 열두 자리부터
 * 지수 표기로 바꿔 버린다 — 열 너비를 아무리 넓혀도 그렇다. 운송장번호가 그 자리였다.
 *
 *     8952388931    열 자리  → 그대로 보인다
 *     600885123456  열두 자리 → 6.00885E+11 로 보인다
 *
 * 운영에서 에스크로 엑셀을 받은 담당자가 깨진 값을 보았다. 담긴 값은 멀쩡한데 **보이는
 * 것만** 틀린 것이라 더 나빴다 — 그대로 복사하면 깨진 글자가 옮겨 간다.
 *
 * `정수칸` 에 적은 칸은 서식 `0`(자릿수 그대로)으로 담는다. 값도 자료형도 그대로이므로
 * 받는 쪽이 읽는 것은 달라지지 않는다. 보이는 것만 고친다.
 */
class XlsxWriter
{
    /**
     * 표 여러 장을 임시 파일로 쓰고 그 경로를 돌려준다.
     *
     * 장 하나의 꼴 —
     *
     *   [
     *     '이름'   => '배송정보',
     *     '줄들'   => [[...], [...]],      각 줄은 칸 값의 배열
     *     '숫자칸' => [1, 3],               숫자로 담을 칸(0부터). 값이 숫자일 때만 먹는다
     *     '정수칸' => [3],                  **자릿수를 그대로 보일 칸** — 아래를 보라
     *     '굵은줄' => [1],                  굵게 적을 줄(1부터)
     *     '너비'   => [16, 52, 22, 18],     칸 너비
     *     '목록'   => [['범위' => 'C2:C100', '값' => "'택배사코드'!\$A\$1:\$A\$191"]],
     *     '거르개' => true,                 머리줄에 자동 필터
     *   ]
     *
     * @param  array<int, array<string, mixed>>  $장들
     * @return string  쓴 파일의 경로
     */
    public static function 여러장(array $장들): string
    {
        if (! $장들) {
            throw new \InvalidArgumentException('담을 장이 없습니다.');
        }

        $경로 = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip  = new \ZipArchive();

        /* 덮어쓴다 — tempnam 이 이미 빈 파일을 만들어 둔다 */
        if ($zip->open($경로, \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('엑셀 파일을 만들지 못했습니다.');
        }

        $종류 = ''; $장목록 = ''; $이음 = '';

        foreach (array_values($장들) as $자리 => $장) {
            $번호 = $자리 + 1;
            $zip->addFromString("xl/worksheets/sheet{$번호}.xml", self::장xml($장));

            $종류   .= '<Override PartName="/xl/worksheets/sheet' . $번호 . '.xml"'
                     . ' ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $장목록 .= '<sheet name="' . self::글(self::장이름거른것((string) ($장['이름'] ?? "Sheet{$번호}")))
                     . '" sheetId="' . $번호 . '" r:id="rId' . $번호 . '"/>';
            $이음   .= '<Relationship Id="rId' . $번호 . '"'
                     . ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"'
                     . ' Target="worksheets/sheet' . $번호 . '.xml"/>';
        }

        $꼴번호 = count($장들) + 1;

        $zip->addFromString('[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . $종류
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>');

        $zip->addFromString('_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');

        $zip->addFromString('xl/workbook.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>' . $장목록 . '</sheets>'
            . '</workbook>');

        $zip->addFromString('xl/_rels/workbook.xml.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . $이음
            . '<Relationship Id="rId' . $꼴번호 . '"'
            . ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>');

        /* 멋을 내지 않는다 — 굵게 하나와 줄바꿈 하나뿐이다.
           줄바꿈 꼴은 안내문이 여러 줄이라 필요하다. */
        $zip->addFromString('xl/styles.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="맑은 고딕"/></font>'
            . '<font><b/><sz val="11"/><name val="맑은 고딕"/></font></fonts>'
            . '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            . '<borders count="1"><border/></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="4">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1">'
            . '<alignment vertical="top" wrapText="1"/></xf>'
            /* 자릿수를 그대로 — 내장 서식 1 이 「0」이다. 열두 자리 운송장번호가
               General 에서 지수 표기로 깨지던 것을 막는다. */
            . '<xf numFmtId="1" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '</cellXfs>'
            . '</styleSheet>');

        $zip->close();

        return $경로;
    }

    /** 한 장만 담는 짧은 길 */
    public static function 한장(string $이름, array $머리글, iterable $줄들, array $숫자칸 = []): string
    {
        $모두 = [$머리글];

        foreach ($줄들 as $줄) {
            $모두[] = array_values((array) $줄);
        }

        return self::여러장([[
            '이름' => $이름, '줄들' => $모두, '숫자칸' => $숫자칸,
            '굵은줄' => [1], '거르개' => true,
        ]]);
    }

    /** 장 하나의 XML */
    private static function 장xml(array $장): string
    {
        $줄들   = array_values((array) ($장['줄들'] ?? []));
        $숫자칸 = array_fill_keys((array) ($장['숫자칸'] ?? []), true);
        $정수칸 = array_fill_keys((array) ($장['정수칸'] ?? []), true);
        $굵은줄 = array_fill_keys((array) ($장['굵은줄'] ?? []), true);
        $너비   = (array) ($장['너비'] ?? []);
        $목록   = (array) ($장['목록'] ?? []);

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';

        /* `cols` 는 `sheetData` **앞**에 와야 한다 — 차례가 틀리면 엑셀이 파일을 고치라고 되묻는다 */
        if ($너비) {
            $xml .= '<cols>';

            foreach ($너비 as $자리 => $w) {
                $xml .= '<col min="' . ($자리 + 1) . '" max="' . ($자리 + 1)
                      . '" width="' . (float) $w . '" customWidth="1"/>';
            }

            $xml .= '</cols>';
        }

        $xml .= '<sheetData>';
        $칸수 = 0;

        foreach ($줄들 as $자리 => $값들) {
            $번호 = $자리 + 1;
            $값들 = array_values((array) $값들);
            $칸수 = max($칸수, count($값들));
            $xml .= self::줄xml($번호, $값들, $숫자칸, isset($굵은줄[$번호]) ? 1 : 0, $정수칸);
        }

        $xml .= '</sheetData>';

        /* 차례가 정해져 있다 — sheetData → autoFilter → dataValidations */
        if (($장['거르개'] ?? false) && $칸수 && $줄들) {
            $xml .= '<autoFilter ref="A1:' . self::칸글자($칸수 - 1) . count($줄들) . '"/>';
        }

        if ($목록) {
            $xml .= '<dataValidations count="' . count($목록) . '">';

            foreach ($목록 as $것) {
                $xml .= '<dataValidation type="list" allowBlank="1" showInputMessage="1" showErrorMessage="1"'
                      . ' sqref="' . self::글((string) $것['범위']) . '">'
                      . '<formula1>' . self::글((string) $것['값']) . '</formula1>'
                      . '</dataValidation>';
            }

            $xml .= '</dataValidations>';
        }

        return $xml . '</worksheet>';
    }

    /** 한 줄 */
    private static function 줄xml(int $번호, array $값들, array $숫자칸, int $꼴, array $정수칸 = []): string
    {
        $xml = '<row r="' . $번호 . '">';

        foreach ($값들 as $자리 => $값) {
            if ($값 === null || $값 === '') {
                /* 빈 칸은 아예 담지 않는다 — 담으면 엑셀이 빈 글자를 값으로 읽는다 */
                continue;
            }

            $자리칸 = self::칸글자($자리) . $번호;

            if (isset($숫자칸[$자리]) && is_numeric($값)) {
                /* 굵은 줄이 먼저다 — 머리글뿐이라 정수칸과 겹칠 일이 없다 */
                $꼴글 = $꼴 ? ' s="' . $꼴 . '"' : (isset($정수칸[$자리]) ? ' s="3"' : '');
                $xml .= '<c r="' . $자리칸 . '"' . $꼴글 . '><v>' . (0 + $값) . '</v></c>';

                continue;
            }

            /* 여러 줄짜리 글은 줄바꿈이 보이는 꼴로 둔다 — 토스 양식의 안내문이 그렇다 */
            $여러줄 = str_contains((string) $값, "\n");
            $꼴글   = $꼴 ? ' s="' . $꼴 . '"' : ($여러줄 ? ' s="2"' : '');

            $xml .= '<c r="' . $자리칸 . '"' . $꼴글 . ' t="inlineStr"><is><t xml:space="preserve">'
                  . self::글((string) $값) . '</t></is></c>';
        }

        return $xml . '</row>';
    }

    /** 0 → A, 25 → Z, 26 → AA */
    private static function 칸글자(int $자리): string
    {
        $글 = '';

        for ($n = $자리; $n >= 0; $n = intdiv($n, 26) - 1) {
            $글 = chr(65 + $n % 26) . $글;
        }

        return $글;
    }

    /**
     * XML 에 담을 수 있게 거른다.
     *
     * 엑셀은 XML 1.0 이 금하는 제어문자가 하나라도 들어가면 **파일을 못 연다**고
     * 한다. 줄바꿈ㆍ탭은 남긴다 — 안내문이 여러 줄이다.
     */
    private static function 글(string $값): string
    {
        $값 = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $값) ?? $값;

        return htmlspecialchars($값, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /**
     * 장 이름 — 엑셀이 금하는 글자와 길이를 맞춘다.
     *
     * `: \ / ? * [ ]` 는 못 쓰고 31자를 넘을 수 없다. 넘기면 엑셀이 파일을 고치라고
     * 되묻는다.
     */
    private static function 장이름거른것(string $이름): string
    {
        $이름 = str_replace([':', '\\', '/', '?', '*', '[', ']'], ' ', $이름);
        $이름 = trim(preg_replace('/\s{2,}/u', ' ', $이름) ?? $이름);

        return mb_substr($이름 !== '' ? $이름 : 'Sheet1', 0, 31);
    }
}
