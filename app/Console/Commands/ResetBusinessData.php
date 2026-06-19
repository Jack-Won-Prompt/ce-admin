<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ResetBusinessData extends Command
{
    protected $signature   = 'data:reset {--force : 확인 없이 바로 실행}';
    protected $description = '업무 데이터 초기화 (처방전·주문·서류·환자)';

    // 삭제 순서: 자식 테이블 → 부모 테이블
    private array $tables = [
        'prescription_documents',
        'prescription_memos',
        'prescription_consents',
        'prescription_items',
        'fax_histories',
        'cashbill_records',
        'popbill_taxinvoices',
        'toss_payments',
        'orders',
        'prescriptions',
        'patients',
        'activity_log',
    ];

    // 삭제할 스토리지 디렉토리
    private array $storageDirs = [
        'consents',
        'fax_pdfs',
        'cash_receipts',
        'tax_invoices',
        'public/fax_pdfs',
        'public/cash_receipts',
    ];

    public function handle(): int
    {
        $this->warn('========================================');
        $this->warn('  업무 데이터 초기화');
        $this->warn('========================================');
        $this->line('대상 테이블:');
        foreach ($this->tables as $t) {
            $this->line("  · {$t}");
        }
        $this->line('대상 스토리지:');
        foreach ($this->storageDirs as $d) {
            $this->line("  · storage/app/{$d}");
        }
        $this->newLine();

        if (!$this->option('force')) {
            if (!$this->confirm('위 데이터를 모두 삭제합니다. 계속하시겠습니까?', false)) {
                $this->info('취소됐습니다.');
                return self::SUCCESS;
            }
        }

        // ── 1. DB TRUNCATE ─────────────────────────────────
        $this->info('DB 초기화 중...');
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ($this->tables as $table) {
            try {
                DB::table($table)->truncate();
                $this->line("  ✓ {$table}");
            } catch (\Throwable $e) {
                $this->warn("  - {$table} 건너뜀: " . $e->getMessage());
            }
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // ── 2. 스토리지 파일 삭제 ──────────────────────────
        $this->info('스토리지 파일 초기화 중...');
        foreach ($this->storageDirs as $dir) {
            try {
                $disk = str_starts_with($dir, 'public/') ? 'public' : 'local';
                $path = str_starts_with($dir, 'public/') ? substr($dir, 7) : $dir;

                $files = Storage::disk($disk)->allFiles($path);
                if ($files) {
                    Storage::disk($disk)->delete($files);
                }
                $this->line("  ✓ {$dir} (" . count($files) . "개 파일 삭제)");
            } catch (\Throwable $e) {
                $this->warn("  - {$dir} 건너뜀: " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info('초기화 완료.');
        return self::SUCCESS;
    }
}
