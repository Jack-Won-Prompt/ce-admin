<?php

namespace App\Support;

/**
 * 엑셀 파일(.xlsx) 한 장을 짓는다.
 *
 * **왜 손으로 짓는가** — 이 저장소에는 엑셀을 쓰는 꾸러미가 없고(PhpSpreadsheet 가
 * `composer.json` 에 없다), 배포 흐름에 `composer install` 단계도 없다. 꾸러미를
 * 더해도 서버에 깔리지 않는다. `ZipArchive` 는 운영에 있다(2026-10-13 확인).
 *
 * 여태의 내보내기는 모두 CSV 였다. 그것으로 되는 일이면 CSV 가 낫다 — 이 자리는
 * **토스페이먼츠에 그대로 올려야 하는 파일**이라 .xlsx 여야 한다(토스가 내려 주는
 * 「에스크로 결제내역」도 .xlsx 다).
 *
 * 담는 것은 한 장ㆍ평평한 표뿐이다. 수식ㆍ그림ㆍ여러 장은 담지 않는다 — 필요해지면
 * 그때 꾸러미를 들이는 편이 낫다.
 */
class XlsxWriter
{
    /**
     * 표 한 장을 임시 파일로 쓰고 그 경로를 돌려준다.
     *
     * 값은 글로 담는다(`inlineStr`). 숫자로 담고 싶은 칸은 `$숫자칸` 에 0부터 센
     * 자리를 적는다 — 주문번호ㆍ송장번호는 숫자로 담으면 엑셀이 지수꼴로 보이거나
     * 앞의 0 을 지워 버린다. 그래서 **숫자로 담는 것은 적어 준 칸만**이다.
     *
     * @param  string    $시트이름
     * @param  string[]  $머리글
     * @param  iterable  $줄들     각 줄은 $머리글 과 같은 길이의 배열
     * @param  int[]     $숫자칸   숫자로 담을 칸의 자리(0부터)
     * @return string    쓴 파일의 경로
     */
    public static function 한장(string $시트이름, array $머리글, iterable $줄들, array $숫자칸 = []): string
    {
        $숫자칸 = array_fill_keys($숫자칸, true);

        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
               . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
               . '<sheetData>';

        $sheet .= self::줄xml(1, $머리글, [], 1);

        $번호 = 1;

        foreach ($줄들 as $줄) {
            $sheet .= self::줄xml(++$번호, array_values((array) $줄), $숫자칸, 0);
        }

        $sheet .= '</sheetData>';

        /* 머리글 줄에 자동 필터를 걸어 둔다 — 담당자가 받아서 바로 거를 수 있게 */
        if ($머리글) {
            $sheet .= '<autoFilter ref="A1:' . self::칸글자(count($머리글) - 1) . $번호 . '"/>';
        }

        $sheet .= '</worksheet>';

        $경로 = tempnam(sys_get_temp_dir(), 'xlsx');

        $zip = new \ZipArchive();

        /* 덮어쓴다 — tempnam 이 이미 빈 파일을 만들어 둔다 */
        if ($zip->open($경로, \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('엑셀 파일을 만들지 못했습니다.');
        }

        $zip->addFromString('[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
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
            . '<sheets><sheet name="' . self::글(self::시트이름거른것($시트이름)) . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>');

        $zip->addFromString('xl/_rels/workbook.xml.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>');

        /* 멋을 내지 않는다 — 머리글을 굵게 하는 꼴 하나뿐이다.
           엑셀은 `cellXfs` 의 자리(s="1")로 꼴을 가리킨다. */
        $zip->addFromString('xl/styles.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="맑은 고딕"/></font>'
            . '<font><b/><sz val="11"/><name val="맑은 고딕"/></font></fonts>'
            . '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            . '<borders count="1"><border/></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>'
            . '</styleSheet>');

        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();

        return $경로;
    }

    /** 한 줄 */
    private static function 줄xml(int $번호, array $값들, array $숫자칸, int $꼴): string
    {
        $xml = '<row r="' . $번호 . '">';

        foreach ($값들 as $자리 => $값) {
            $자리칸 = self::칸글자($자리) . $번호;
            $꼴글   = $꼴 ? ' s="' . $꼴 . '"' : '';

            if ($값 === null || $값 === '') {
                /* 빈 칸은 아예 담지 않는다 — 담으면 엑셀이 빈 글자를 값으로 읽는다 */
                continue;
            }

            if (isset($숫자칸[$자리]) && is_numeric($값)) {
                $xml .= '<c r="' . $자리칸 . '"' . $꼴글 . '><v>' . (0 + $값) . '</v></c>';

                continue;
            }

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
     * 한다. 글 안에 그런 글자가 섞여 들어오는 일이 있어 미리 뗀다.
     */
    private static function 글(string $값): string
    {
        $값 = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $값) ?? $값;

        return htmlspecialchars($값, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /**
     * 시트 이름 — 엑셀이 금하는 글자와 길이를 맞춘다.
     *
     * `: \ / ? * [ ]` 는 못 쓰고 31자를 넘을 수 없다. 넘기면 엑셀이 파일을 고치라고
     * 되묻는다.
     */
    private static function 시트이름거른것(string $이름): string
    {
        $이름 = str_replace([':', '\\', '/', '?', '*', '[', ']'], ' ', $이름);
        $이름 = trim(preg_replace('/\s{2,}/u', ' ', $이름) ?? $이름);

        return mb_substr($이름 !== '' ? $이름 : 'Sheet1', 0, 31);
    }
}
