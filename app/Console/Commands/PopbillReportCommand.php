<?php

namespace App\Console\Commands;

use App\Services\Popbill\CashbillService;
use App\Services\Popbill\FaxService;
use App\Services\Popbill\KakaoService;
use App\Services\Popbill\MessageService;
use App\Services\Popbill\TaxinvoiceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PopbillReportCommand extends Command
{
    protected $signature = 'popbill:report
                            {--corp-num= : 사업자번호 (기본값: 테스트 사업자번호)}
                            {--date= : 조회 기준일 YYYYMM (기본값: 이번 달)}
                            {--output=table : 출력 형식 (table|json|csv)}';

    protected $description = '팝빌 서비스별 잔여포인트 및 당월 전송/발행 건수 조회';

    public function __construct(
        private readonly TaxinvoiceService $taxSvc,
        private readonly CashbillService   $cashSvc,
        private readonly KakaoService      $kakaoSvc,
        private readonly MessageService    $msgSvc,
        private readonly FaxService        $faxSvc,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $corpNum = $this->option('corp-num') ?? config('popbill.test.corp_num');
        $month   = $this->option('date') ?? now()->format('Ym');
        $start   = $month . '01';
        $end     = now()->parse($month . '01')->endOfMonth()->format('Ymd');

        $this->info("팝빌 리포트 — 사업자번호: {$corpNum} / 기간: {$start}~{$end}");
        $this->newLine();

        $rows = [];

        // 세금계산서
        try {
            $balance = $this->taxSvc->getBalance($corpNum);
            $result  = $this->taxSvc->search($corpNum, 'SELL', $start, $end, perPage: 1);
            $rows[]  = ['세금계산서', number_format($balance), $result->total ?? 0, '정상'];
        } catch (\Throwable $e) {
            $rows[] = ['세금계산서', '-', '-', $e->getMessage()];
        }

        // 현금영수증
        try {
            $balance = $this->cashSvc->getBalance($corpNum);
            $result  = $this->cashSvc->search($corpNum, $start, $end, perPage: 1);
            $rows[]  = ['현금영수증', number_format($balance), $result->total ?? 0, '정상'];
        } catch (\Throwable $e) {
            $rows[] = ['현금영수증', '-', '-', $e->getMessage()];
        }

        // 카카오 알림톡
        try {
            $balance = $this->kakaoSvc->getBalance($corpNum);
            $rows[]  = ['카카오 알림톡', number_format($balance), '-', '정상'];
        } catch (\Throwable $e) {
            $rows[] = ['카카오 알림톡', '-', '-', $e->getMessage()];
        }

        // SMS/LMS
        try {
            $balance = $this->msgSvc->getBalance($corpNum);
            $result  = $this->msgSvc->search($corpNum, 'SMS', $start, $end, perPage: 1);
            $rows[]  = ['문자(SMS/LMS)', number_format($balance), $result->total ?? 0, '정상'];
        } catch (\Throwable $e) {
            $rows[] = ['문자(SMS/LMS)', '-', '-', $e->getMessage()];
        }

        // 팩스
        try {
            $balance = $this->faxSvc->getBalance($corpNum);
            $result  = $this->faxSvc->search($corpNum, 'S', $start, $end, perPage: 1);
            $rows[]  = ['팩스', number_format($balance), $result->total ?? 0, '정상'];
        } catch (\Throwable $e) {
            $rows[] = ['팩스', '-', '-', $e->getMessage()];
        }

        $output = $this->option('output');

        if ($output === 'json') {
            $data = array_map(fn($r) => array_combine(['service','balance','count','status'], $r), $rows);
            $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            $this->line($json);
            $this->saveReport($corpNum, $month, $json, 'json');
        } elseif ($output === 'csv') {
            $csv = "서비스,잔여포인트,당월건수,상태\n";
            foreach ($rows as $r) $csv .= implode(',', $r) . "\n";
            $this->line($csv);
            $this->saveReport($corpNum, $month, $csv, 'csv');
        } else {
            $this->table(['서비스', '잔여포인트', '당월 건수', '상태'], $rows);
        }

        return self::SUCCESS;
    }

    private function saveReport(string $corpNum, string $month, string $content, string $ext): void
    {
        $path = "popbill-reports/{$corpNum}_{$month}.{$ext}";
        Storage::disk('local')->put($path, $content);
        $this->info("리포트 저장: storage/app/{$path}");
    }
}
