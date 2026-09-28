<?php

namespace App\Support;

/**
 * 엑셀(.xlsx) 한 장을 표로 읽는다 — 라이브러리 없이 (2026-09-27 확인요청 8쪽).
 *
 * 마스터 업로드를 만들려는데 이 서버에는 엑셀을 읽는 라이브러리가 없다.
 * phpspreadsheet 를 들이면 vendor 가 수십 MB 늘고 배포마다 따라다닌다 — 우리가
 * 하려는 일은 「머리글 한 줄과 그 아래 줄들을 읽는다」 하나뿐이다.
 *
 * xlsx 는 XML 을 zip 으로 묶은 것이다. 서버에 ZipArchive 와 SimpleXML 이 이미 있어
 * 그 둘로 읽는다. 담는 것은 **글자뿐**이다 — 날짜 꼴ㆍ수식ㆍ서식은 가리지 않는다.
 * 우리가 받는 표(공단 지사 목록 따위)에는 그것이 없다.
 *
 * CSV 도 함께 받는다. 엑셀을 열 수 없는 자리에서 「다른 이름으로 저장」 한 번이면
 * 되는 길을 막아 둘 까닭이 없다.
 */
final class SimpleXlsx
{
    /**
     * 파일을 줄 배열로 읽는다. 첫 줄이 머리글이다.
     *
     * @return array{0: array<int,string>, 1: array<int, array<int,string>>} [머리글, 줄들]
     */
    public static function 읽기(string $path): array
    {
        $꼴 = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $줄들 = $꼴 === 'csv' ? self::csv읽기($path) : self::xlsx읽기($path);

        /* 위아래로 빈 줄이 붙어 오는 일이 잦다 — 엑셀에서 표 위에 제목을 얹거나
           아래에 합계를 비워 두기 때문이다. 내용이 하나도 없는 줄은 버린다. */
        $줄들 = array_values(array_filter(
            $줄들,
            fn (array $r) => implode('', array_map('trim', $r)) !== ''
        ));

        if (! $줄들) {
            return [[], []];
        }

        $머리 = array_map(fn ($v) => trim((string) $v), array_shift($줄들));

        return [$머리, $줄들];
    }

    /**
     * 머리글이 첫 줄이 아닌 표를 읽는다 (2026-09-27 확인요청 8쪽).
     *
     * 받아 본 공단 지사 목록이 그랬다 — 첫 줄에 「일반, 차상위질환자」라는 제목이
     * 있고 둘째 줄에 안내가 있고, 진짜 머리글(No·공단·주소…)은 셋째 줄에 있었다.
     * 사람이 만든 표는 대개 그렇다.
     *
     * 그래서 **찾을 이름이 가장 많이 걸리는 줄**을 머리글로 본다. 몇 번째 줄인지
     * 물어보게 하면 그 값을 잘못 적는 일이 또 생긴다.
     *
     * @param array<int, array<string>> $찾을이름들 칸마다 「이 중 하나면 그 칸」
     * @return array{0: array<int,string>, 1: array<int, array<int,string>>}
     */
    public static function 머리글찾아읽기(string $path, array $찾을이름들): array
    {
        [$첫머리, $나머지] = self::읽기($path);

        if (! $첫머리) {
            return [[], []];
        }

        $셈 = static function (array $줄) use ($찾을이름들): int {
            $n = 0;
            foreach ($찾을이름들 as $이름들) {
                if (self::칸찾기($줄, $이름들) !== null) {
                    $n++;
                }
            }

            return $n;
        };

        $best   = $셈($첫머리);
        $머리   = $첫머리;
        $아래   = $나머지;

        /* 앞쪽 몇 줄만 본다 — 표 위에 붙는 제목은 길어야 서너 줄이다 */
        foreach (array_slice($나머지, 0, 10) as $i => $줄) {
            $점 = $셈(array_map(fn ($v) => trim((string) $v), $줄));

            if ($점 > $best) {
                $best = $점;
                $머리 = array_map(fn ($v) => trim((string) $v), $줄);
                $아래 = array_slice($나머지, $i + 1);
            }
        }

        return [$머리, $아래];
    }

    /**
     * 같은 이름의 칸을 **모두** 찾는다 (2026-09-27 확인요청 8쪽).
     *
     * 사람이 쓰는 표에는 같은 머리글이 두 번 나오는 일이 잦다. 받아 본 공단 지사
     * 목록이 그랬다 — 왼쪽에 예전 주소ㆍ부서ㆍTELㆍFAX 가 있고, 그 오른쪽에
     * 「확인 담당자」와 함께 09월에 다시 확인한 같은 이름의 칸들이 붙어 있었다.
     *
     * 이럴 때 왼쪽을 집으면 **「다른 지역으로 발령」이라 적힌 옛 자료**가 들어온다.
     * 사람은 고친 것을 오른쪽에 덧붙인다 — 그러니 **오른쪽에 값이 있으면 그것**이다.
     *
     * @return array<int,int> 왼쪽부터의 칸 번호들
     */
    public static function 칸들찾기(array $머리, array $이름들): array
    {
        $납작 = static fn (string $s): string
            => preg_replace('/[\s()\[\]·ㆍ\/]+/u', '', trim($s)) ?? '';

        $찾을것 = array_map($납작, $이름들);
        $나온것 = [];

        foreach ($머리 as $i => $h) {
            if (in_array($납작((string) $h), $찾을것, true)) {
                $나온것[] = $i;
            }
        }

        return $나온것;
    }

    /**
     * 한 줄에서 그 칸의 값을 집는다 — 같은 이름의 칸이 여럿이면 **오른쪽 것이 먼저**.
     *
     * 오른쪽이 비어 있으면 왼쪽으로 물러난다. 고쳐 적지 않은 줄은 예전 값이라도
     * 있는 것이 없는 것보다 낫다.
     */
    public static function 값집기(array $줄, array $칸들): string
    {
        foreach (array_reverse($칸들) as $i) {
            $v = isset($줄[$i]) ? trim(preg_replace('/\s+/u', ' ', (string) $줄[$i]) ?? '') : '';

            if ($v !== '') {
                return $v;
            }
        }

        return '';
    }

    /** 머리글 이름으로 칸 번호를 찾는다 — 띄어쓰기와 괄호는 무시한다 */
    public static function 칸찾기(array $머리, array $이름들): ?int
    {
        $납작 = static fn (string $s): string
            => preg_replace('/[\s()\[\]·ㆍ\/]+/u', '', trim($s)) ?? '';

        foreach ($이름들 as $이름) {
            $찾을것 = $납작($이름);
            foreach ($머리 as $i => $h) {
                if ($납작((string) $h) === $찾을것) {
                    return $i;
                }
            }
        }

        return null;
    }

    // ──────────────────────────────────────────────────────

    private static function csv읽기(string $path): array
    {
        $줄들 = [];
        $fh = fopen($path, 'r');

        if (! $fh) {
            return [];
        }

        /* 엑셀이 저장한 CSV 는 BOM 으로 시작한다 — 떼지 않으면 첫 머리글이 어긋난다 */
        $첫 = fgets($fh);
        if ($첫 !== false) {
            rewind($fh);
            if (str_starts_with($첫, "\xEF\xBB\xBF")) {
                fseek($fh, 3);
            }
        }

        while (($r = fgetcsv($fh)) !== false) {
            $줄들[] = array_map(fn ($v) => (string) $v, $r);
        }

        fclose($fh);

        return $줄들;
    }

    private static function xlsx읽기(string $path): array
    {
        $zip = new \ZipArchive();

        if ($zip->open($path) !== true) {
            throw new \RuntimeException('엑셀 파일을 열지 못했습니다.');
        }

        /* ── 글자 광 ── 셀이 t="s" 이면 값이 아니라 이 광의 번호다 */
        $광 = [];
        if (($x = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $s = @simplexml_load_string($x);
            foreach ($s->si ?? [] as $si) {
                /* 한 칸 안에서 글꼴이 바뀌면 조각(r)이 여럿으로 갈린다 — 이어 붙인다 */
                $글 = '';
                if (isset($si->t)) {
                    $글 = (string) $si->t;
                } else {
                    foreach ($si->r ?? [] as $r) {
                        $글 .= (string) $r->t;
                    }
                }
                $광[] = $글;
            }
        }

        /* ── 첫 장 ── 이름이 무엇이든 첫 장을 읽는다 */
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheet === false) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $이름 = $zip->getNameIndex($i);
                if (str_starts_with($이름, 'xl/worksheets/') && str_ends_with($이름, '.xml')) {
                    $sheet = $zip->getFromName($이름);
                    break;
                }
            }
        }

        $zip->close();

        if ($sheet === false) {
            throw new \RuntimeException('엑셀 안에서 표를 찾지 못했습니다.');
        }

        $xml = @simplexml_load_string($sheet);
        if (! $xml) {
            throw new \RuntimeException('엑셀을 읽지 못했습니다 — 파일이 상했을 수 있습니다.');
        }

        $줄들 = [];

        foreach ($xml->sheetData->row ?? [] as $row) {
            $줄 = [];
            $끝 = -1;

            foreach ($row->c ?? [] as $c) {
                $자리 = self::칸번호((string) $c['r']);
                $값   = self::셀값($c, $광);

                /* 빈 칸은 xlsx 에 아예 없다 — 자리를 보고 채워야 칸이 밀리지 않는다 */
                for ($i = $끝 + 1; $i < $자리; $i++) {
                    $줄[$i] = '';
                }

                $줄[$자리] = $값;
                $끝 = max($끝, $자리);
            }

            ksort($줄);
            $줄들[] = array_values($줄);
        }

        return $줄들;
    }

    /** 셀 하나의 글자 — 글자 광을 가리키면 거기서 꺼낸다 */
    private static function 셀값(\SimpleXMLElement $c, array $광): string
    {
        $갈래 = (string) ($c['t'] ?? '');

        if ($갈래 === 's') {
            $번 = (int) $c->v;

            return $광[$번] ?? '';
        }

        if ($갈래 === 'inlineStr') {
            return trim((string) ($c->is->t ?? ''));
        }

        return trim((string) ($c->v ?? ''));
    }

    /** 「BC12」에서 칸 번호(0부터)를 뽑는다 */
    private static function 칸번호(string $ref): int
    {
        if (! preg_match('/^([A-Z]+)/', $ref, $m)) {
            return 0;
        }

        $n = 0;
        foreach (str_split($m[1]) as $글) {
            $n = $n * 26 + (ord($글) - 64);
        }

        return $n - 1;
    }
}
