<?php

namespace App\Services;

use App\Models\InstitutionalNotice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InstitutionalNoticeCrawlerService
{
    private const FEE_KEYWORDS  = ['수가', '상대가치', '요양급여', '급여', '수가표'];
    private const MAX_PAGES     = 10;
    private const DELAY_MS      = 800;

    private Carbon $fromDate;

    public function __construct(?Carbon $fromDate = null)
    {
        // 기본: 전일 00:00 이후
        $this->fromDate = $fromDate ?? now()->subDay()->startOfDay();
    }

    public static function hasTodayData(): bool
    {
        try {
            return InstitutionalNotice::whereDate('crawled_at', now()->toDateString())->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    public function crawlAll(): array
    {
        $results = [
            'MOHW'      => 0,
            'HIRA'      => 0,
            'NHIS'      => 0,
            'errors'    => [],
            'from_date' => $this->fromDate->toDateString(),
        ];

        foreach (['MOHW' => 'crawlMohw', 'HIRA' => 'crawlHira', 'NHIS' => 'crawlNhis'] as $org => $method) {
            try {
                $results[$org] = $this->$method();
            } catch (\Throwable $e) {
                $results['errors'][] = "{$org}: " . $e->getMessage();
                Log::error("InstitutionalNoticeCrawler {$org}", ['error' => $e->getMessage()]);
            }
        }

        return $results;
    }

    // ─────────────────────────────────────────────────────────────────
    // MOHW (보건복지부)
    // 보도자료: board.es?mid=a10503010100&bid=0027&nPage={n}
    // 고시:     board.es?mid=a10409020000&bid=0026&nPage={n}
    // 구조: td.txt_left a.txt_title (link) + td[data-label="등록일"] (date)
    // preView tr: tr[id^="preView"] 안의 td → 본문 미리보기 텍스트 활용
    // ─────────────────────────────────────────────────────────────────
    private function crawlMohw(): int
    {
        $saved = 0;
        $boards = [
            '보도자료' => 'https://www.mohw.go.kr/board.es?mid=a10503010100&bid=0027&nPage={PAGE}',
            '고시'     => 'https://www.mohw.go.kr/board.es?mid=a10409020000&bid=0026&nPage={PAGE}',
        ];

        foreach ($boards as $type => $tpl) {
            for ($page = 1; $page <= self::MAX_PAGES; $page++) {
                $html = $this->fetchHtml(str_replace('{PAGE}', $page, $tpl));
                if (!$html) break;

                [$items, $done] = $this->parseMohw($html, $type);
                foreach ($items as $item) {
                    $saved += $this->saveNotice('MOHW', $item);
                }
                if ($done || empty($items)) break;
                if ($page < self::MAX_PAGES) usleep(self::DELAY_MS * 1000);
            }
        }
        return $saved;
    }

    private function parseMohw(string $html, string $type): array
    {
        $items    = [];
        $hasOlder = false;
        $dom      = $this->dom($html);
        $xpath    = new \DOMXPath($dom);

        // preView 텍스트 맵: preView{list_no} → 미리보기 텍스트
        $previewMap = [];
        $previewRows = $xpath->query('//tr[starts-with(@id,"preView")]');
        foreach ($previewRows as $pr) {
            $id   = ltrim($pr->getAttribute('id'), 'preView');
            $text = trim($pr->textContent);
            if ($id && $text) $previewMap[$id] = $text;
        }

        $rows = $xpath->query('//table[contains(@class,"board_list")]//tbody/tr[not(starts-with(@id,"preView"))]');
        if (!$rows || $rows->length === 0) return [[], false];

        foreach ($rows as $row) {
            // 제목·링크: td.txt_left a.txt_title 또는 첫 번째 a
            $aEl = $xpath->query('.//td[contains(@class,"txt_left")]//a[contains(@class,"txt_title")]', $row)->item(0)
                ?? $xpath->query('.//td[contains(@class,"txt_left")]//a', $row)->item(0);
            if (!$aEl) continue;

            $title = trim(preg_replace('/\s+/', ' ',
                str_replace(['새글', '새글\n'], '', $aEl->textContent)));
            if (strlen($title) < 3) continue;

            $href = $aEl->getAttribute('href');
            $url  = $this->abs('https://www.mohw.go.kr', $href);
            if (!$url) continue;

            // 날짜: td[data-label="등록일"]
            $dateEl = $xpath->query('.//td[@data-label="등록일"]', $row)->item(0);
            $dateStr = $dateEl ? $this->parseDate(trim($dateEl->textContent)) : null;

            if ($dateStr && Carbon::parse($dateStr)->lt($this->fromDate)) {
                $hasOlder = true;
                continue;
            }

            // preView 텍스트: URL에서 list_no 추출
            $listNo  = '';
            parse_str(parse_url($href, PHP_URL_QUERY), $qp);
            $listNo  = $qp['list_no'] ?? '';
            $preview = $previewMap[$listNo] ?? null;

            $items[] = [
                'notice_type' => $type,
                'title'       => $title,
                'notice_date' => $dateStr,
                'url'         => $url,
                'content'     => $preview,
                'attachments' => [],
            ];
        }
        return [$items, $hasOlder];
    }

    // ─────────────────────────────────────────────────────────────────
    // HIRA (건강보험심사평가원)
    // 공지사항:   bbsDummy.do?pgmid=HIRAA020002000100&pageIndex={n}
    // 약제급여평가: bbsDummy.do?pgmid=HIRAA030014040000&pageIndex={n}
    // 구조: td.col-tit a (link, relative) + td.col-date (date)
    // 보험인정기준은 onclick 기반 팝업 → 제목/날짜만 수집, URL은 목록 URL
    // ─────────────────────────────────────────────────────────────────
    private function crawlHira(): int
    {
        $saved = 0;
        $boards = [
            '공지사항'     => 'https://www.hira.or.kr/bbsDummy.do?pgmid=HIRAA020002000100&pageIndex={PAGE}',
            '약제급여평가' => 'https://www.hira.or.kr/bbsDummy.do?pgmid=HIRAA030014040000&pageIndex={PAGE}',
        ];

        foreach ($boards as $type => $tpl) {
            for ($page = 1; $page <= self::MAX_PAGES; $page++) {
                $html = $this->fetchHtml(str_replace('{PAGE}', $page, $tpl));
                if (!$html) break;

                [$items, $done] = $this->parseHira($html, $type,
                    str_replace('{PAGE}', $page, $tpl));
                foreach ($items as $item) {
                    $saved += $this->saveNotice('HIRA', $item);
                }
                if ($done || empty($items)) break;
                if ($page < self::MAX_PAGES) usleep(self::DELAY_MS * 1000);
            }
        }
        return $saved;
    }

    private function parseHira(string $html, string $type, string $pageUrl): array
    {
        $items    = [];
        $hasOlder = false;
        $dom      = $this->dom($html);
        $xpath    = new \DOMXPath($dom);

        $rows = $xpath->query('//table//tbody/tr');
        foreach ($rows as $row) {
            // 공지(notice) 행 포함
            // 제목: td.col-tit a
            $aEl = $xpath->query('.//td[contains(@class,"col-tit")]//a', $row)->item(0);
            if (!$aEl) continue;

            $title = trim(preg_replace('/\s+/', ' ', $aEl->textContent));
            if (strlen($title) < 3) continue;

            // 링크: onclick 기반인 경우 목록 URL 사용
            $href = $aEl->getAttribute('href');
            $url  = ($href && $href !== '#none' && !str_starts_with(trim($href), 'javascript'))
                ? $this->abs('https://www.hira.or.kr', $href)
                : $pageUrl;

            // 날짜: td.col-date
            $dateEl  = $xpath->query('.//td[contains(@class,"col-date")]', $row)->item(0);
            $dateStr = $dateEl ? $this->parseDate(trim($dateEl->textContent)) : null;

            if ($dateStr && Carbon::parse($dateStr)->lt($this->fromDate)) {
                $hasOlder = true;
                continue;
            }

            $items[] = [
                'notice_type' => $type,
                'title'       => $title,
                'notice_date' => $dateStr,
                'url'         => $url,
                'content'     => null,
                'attachments' => [],
            ];
        }
        return [$items, $hasOlder];
    }

    // ─────────────────────────────────────────────────────────────────
    // NHIS (국민건강보험공단)
    // 검진청구 공지: nhis.or.kr/nhis/minwon/wbhace10210m01.do
    //   (hers.nhis.or.kr는 암호화 AJAX 필요 → 접근 불가)
    // 구조: td.a-l a.a-link (href=?mode=view&articleNo=..., title=제목)
    //        마지막 <td>에 날짜 패턴 (YYYY.MM.DD)
    // 페이지네이션: ?mode=list&article.offset={(page-1)*10}&articleLimit=10
    // ─────────────────────────────────────────────────────────────────
    private function crawlNhis(): int
    {
        $saved   = 0;
        $base    = 'https://www.nhis.or.kr/nhis/minwon/wbhace10210m01.do';
        $perPage = 10;

        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            $offset = ($page - 1) * $perPage;
            $url    = "{$base}?mode=list&article.offset={$offset}&articleLimit={$perPage}";
            $html   = $this->fetchHtml($url);
            if (!$html) break;

            [$items, $done] = $this->parseNhis($html, $base);
            foreach ($items as $item) {
                $saved += $this->saveNotice('NHIS', $item);
            }
            if ($done || empty($items)) break;
            if ($page < self::MAX_PAGES) usleep(self::DELAY_MS * 1000);
        }
        return $saved;
    }

    private function parseNhis(string $html, string $base): array
    {
        $items    = [];
        $hasOlder = false;
        $dom      = $this->dom($html);
        $xpath    = new \DOMXPath($dom);

        $rows = $xpath->query('//tbody//tr');
        foreach ($rows as $row) {
            // 제목·링크
            $aEl = $xpath->query('.//td[contains(@class,"a-l")]//a[contains(@class,"a-link")]', $row)->item(0);
            if (!$aEl) continue;

            // title 속성에서 제목 가져오기 (더 깔끔함)
            $title = trim($aEl->getAttribute('title'));
            if (!$title) $title = trim(preg_replace('/\s+/', ' ', $aEl->textContent));
            // " 자세히 보기" 제거
            $title = preg_replace('/\s*자세히\s*보기\s*$/', '', $title);
            if (strlen($title) < 3) continue;

            $href = $aEl->getAttribute('href');
            $url  = $this->abs($base, $href);
            if (!$url) continue;

            // 날짜: tr 안에서 YYYY.MM.DD 패턴 찾기 (마지막 td에 위치)
            $tds     = $xpath->query('.//td', $row);
            $dateStr = null;
            for ($i = $tds->length - 1; $i >= 0; $i--) {
                $d = $this->parseDate(trim($tds->item($i)->textContent));
                if ($d) { $dateStr = $d; break; }
            }

            if ($dateStr && Carbon::parse($dateStr)->lt($this->fromDate)) {
                $hasOlder = true;
                continue;
            }

            $items[] = [
                'notice_type' => '검진청구 공지',
                'title'       => $title,
                'notice_date' => $dateStr,
                'url'         => $url,
                'content'     => null,
                'attachments' => [],
            ];
        }
        return [$items, $hasOlder];
    }

    // ─────────────────────────────────────────────────────────────────
    // 상세 내용 fetch (팝업용)
    // ─────────────────────────────────────────────────────────────────
    public function fetchDetail(InstitutionalNotice $notice): InstitutionalNotice
    {
        if ($notice->content) return $notice;

        $html = $this->fetchHtml($notice->url);
        if (!$html) return $notice;

        $dom   = $this->dom($html);
        $xpath = new \DOMXPath($dom);

        // 본문 선택자 — 기관별 패턴 포함
        $selectors = [
            '//*[contains(@class,"view_content")]',
            '//*[contains(@class,"board_view")]',
            '//*[contains(@class,"bbs_view")]',
            '//*[contains(@class,"cont_wrap")]',
            '//*[contains(@class,"view-cont")]',
            '//*[contains(@class,"a-view")]',
            '//*[@id="content"]',
        ];
        $content = '';
        foreach ($selectors as $sel) {
            $el = $xpath->query($sel)->item(0);
            if ($el) { $content = trim(preg_replace('/\s{3,}/', "\n\n", $el->textContent)); break; }
        }

        // 첨부파일
        $attachments = [];
        $exts = implode('|', ['hwp', 'pdf', 'xlsx', 'xls', 'zip', 'doc', 'docx']);
        $nodes = $xpath->query("//a[contains(@href,'.hwp') or contains(@href,'.pdf') or contains(@href,'.xlsx') or contains(@href,'.zip') or contains(@href,'download') or contains(@href,'fileDown') or contains(@href,'attach')]");
        foreach ($nodes as $node) {
            $href  = $node->getAttribute('href');
            $label = trim($node->textContent) ?: basename($href);
            if (!$href || str_starts_with($href, '#')) continue;
            $attachments[] = ['name' => $label, 'url' => $this->abs($notice->url, $href)];
        }

        $notice->update([
            'content'     => $content ?: null,
            'attachments' => $attachments ?: null,
        ]);
        return $notice;
    }

    // ─────────────────────────────────────────────────────────────────
    // 저장
    // ─────────────────────────────────────────────────────────────────
    private function saveNotice(string $org, array $item): int
    {
        try {
            if (InstitutionalNotice::where('url', $item['url'])->exists()) return 0;

            $text = $item['title'] . ' ' . ($item['content'] ?? '');
            InstitutionalNotice::create([
                'source_org'    => $org,
                'notice_type'   => $item['notice_type'] ?? null,
                'title'         => $item['title'],
                'notice_date'   => $item['notice_date'] ?? null,
                'content'       => $item['content'] ?? null,
                'url'           => $item['url'],
                'content_hash'  => $item['content'] ? hash('sha256', $item['content']) : null,
                'attachments'   => $item['attachments'] ?? null,
                'policy_impact' => $this->impact($text),
                'fee_impact'    => $this->feeImpact($text),
                'crawled_at'    => now(),
            ]);
            return 1;
        } catch (\Throwable $e) {
            Log::warning('InstitutionalNotice save', ['url' => $item['url'], 'error' => $e->getMessage()]);
            return 0;
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // 분류
    // ─────────────────────────────────────────────────────────────────
    private function impact(string $text): string
    {
        foreach (['고시', '일부개정', '상대가치', '수가', '요양급여'] as $kw) {
            if (str_contains($text, $kw)) return 'HIGH';
        }
        foreach (['급여기준', '인정기준', '심사', '청구'] as $kw) {
            if (str_contains($text, $kw)) return 'MEDIUM';
        }
        return 'LOW';
    }

    private function feeImpact(string $text): bool
    {
        foreach (self::FEE_KEYWORDS as $kw) {
            if (str_contains($text, $kw)) return true;
        }
        return false;
    }

    // ─────────────────────────────────────────────────────────────────
    // HTTP / DOM 헬퍼
    // ─────────────────────────────────────────────────────────────────
    private function fetchHtml(string $url, int $timeout = 20): ?string
    {
        try {
            $res = Http::timeout($timeout)
                ->withHeaders([
                    'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0 Safari/537.36',
                    'Accept-Language' => 'ko-KR,ko;q=0.9,en;q=0.5',
                    'Accept'          => 'text/html,application/xhtml+xml,*/*;q=0.8',
                    'Accept-Encoding' => 'gzip, deflate, br',
                ])
                ->get($url);
            return $res->successful() ? $res->body() : null;
        } catch (\Throwable $e) {
            Log::warning('InstitutionalNotice fetch', ['url' => $url, 'err' => $e->getMessage()]);
            return null;
        }
    }

    private function dom(string $html): \DOMDocument
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        return $dom;
    }

    private function abs(string $base, string $href): string
    {
        $href = trim($href);
        if (!$href || str_starts_with($href, 'javascript') || $href === '#') return '';
        if (str_starts_with($href, 'http')) return $href;
        if (str_starts_with($href, '//')) return 'https:' . $href;

        $p      = parse_url($base);
        $scheme = $p['scheme'] ?? 'https';
        $host   = $p['host'] ?? '';
        $port   = isset($p['port']) ? ':' . $p['port'] : '';

        if (str_starts_with($href, '/')) return "{$scheme}://{$host}{$port}{$href}";

        // ?query 형태 — 현재 페이지의 경로에 쿼리만 교체
        if (str_starts_with($href, '?')) {
            $path = $p['path'] ?? '/';
            return "{$scheme}://{$host}{$port}{$path}{$href}";
        }

        $dir = isset($p['path']) ? dirname($p['path']) : '';
        return "{$scheme}://{$host}{$port}{$dir}/{$href}";
    }

    private function parseDate(?string $s): ?string
    {
        if (!$s) return null;
        $s = trim(preg_replace('/\s+/', '', $s));
        if (preg_match('/(\d{4})[.\-\/](\d{1,2})[.\-\/](\d{1,2})/', $s, $m)) {
            return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
        }
        if (preg_match('/(\d{4})년(\d{1,2})월(\d{1,2})일/', $s, $m)) {
            return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
        }
        return null;
    }
}
