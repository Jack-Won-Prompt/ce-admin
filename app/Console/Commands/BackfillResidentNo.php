<?php

namespace App\Console\Commands;

use App\Models\Patient;
use App\Models\Prescription;
use App\Support\ResidentNo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 평문 주민등록번호를 암호화 컬럼으로 이관한다 — P0-1 3단계.
 *
 *   php artisan rrn:backfill --dry-run    이관 대상 건수만 센다
 *   php artisan rrn:backfill              실제 이관
 *   php artisan rrn:backfill --verify     잔여 0 / 불일치 0 확인
 *
 * 평문 컬럼은 건드리지 않는다. 제거는 검증 통과 후 별도 마이그레이션이 한다.
 */
class BackfillResidentNo extends Command
{
    protected $signature = 'rrn:backfill
                            {--dry-run : 변경 없이 대상 건수만 센다}
                            {--verify  : 이관 결과를 왕복 복호화로 검증한다}
                            {--chunk=200 : 한 번에 처리할 행 수}';

    protected $description = '평문 주민등록번호를 암호화·해시·마스킹 컬럼으로 이관';

    public function handle(): int
    {
        if (!config('rrn.key') || !config('rrn.pepper')) {
            $this->error('RRN_ENCRYPTION_KEY / RRN_HASH_PEPPER 가 없습니다. .env 를 먼저 설정하세요.');

            return self::FAILURE;
        }

        $this->line(sprintf('DB=%s', DB::connection()->getDatabaseName()));

        return $this->option('verify') ? $this->verify() : $this->backfill();
    }

    /* ── 이관 ─────────────────────────────────────────────── */

    private function backfill(): int
    {
        $dry   = (bool) $this->option('dry-run');
        $chunk = max(1, (int) $this->option('chunk'));
        $this->line($dry ? '모드: 예행(dry-run)' : '모드: 실제 이관');
        $this->newLine();

        /* patients — 해시·마스킹·보유기한까지 채운다 */
        $pending = Patient::withTrashed()
            ->whereNotNull('resident_no')->where('resident_no', '<>', '')
            ->whereNull('resident_no_enc');
        $this->line(sprintf('patients.resident_no        이관 대상 %d건', (clone $pending)->count()));

        $done = 0;
        if (!$dry) {
            (clone $pending)->chunkById($chunk, function ($rows) use (&$done) {
                foreach ($rows as $patient) {
                    $plain = $patient->getRawOriginal('resident_no');
                    $basis = $this->retentionBasisFor($patient);

                    // setter 를 거치지 않고 직접 넣는다 — 평문 컬럼을 다시 쓰지 않기 위함
                    Patient::withTrashed()->where('id', $patient->id)->update([
                        'resident_no_enc'        => ResidentNo::encrypt($plain),
                        'resident_no_hash'       => ResidentNo::hash($plain),
                        'resident_no_masked'     => ResidentNo::mask($plain),
                        'rrn_purpose'            => 'nhis_claim_form',
                        'rrn_retention_basis_at' => $basis?->toDateString(),
                        'rrn_retention_until'    => ResidentNo::retentionUntil($basis)?->toDateString(),
                    ]);
                    $done++;
                }
            });
            $this->line(sprintf('                            %d건 이관', $done));
        }

        /* prescriptions — OCR 원문은 정규화 없이 그대로 암호화 */
        $pendingRx = Prescription::withTrashed()
            ->whereNotNull('resident_no_ocr')->where('resident_no_ocr', '<>', '')
            ->whereNull('resident_no_ocr_enc');
        $this->line(sprintf('prescriptions.resident_no_ocr 이관 대상 %d건', (clone $pendingRx)->count()));

        $doneRx = 0;
        if (!$dry) {
            (clone $pendingRx)->chunkById($chunk, function ($rows) use (&$doneRx) {
                foreach ($rows as $rx) {
                    $plain = $rx->getRawOriginal('resident_no_ocr');
                    Prescription::withTrashed()->where('id', $rx->id)->update([
                        'resident_no_ocr_enc'    => ResidentNo::encrypt($plain),
                        'resident_no_ocr_masked' => ResidentNo::mask($plain),
                    ]);
                    $doneRx++;
                }
            });
            $this->line(sprintf('                            %d건 이관', $doneRx));
        }

        $this->newLine();
        $this->info($dry ? '예행 종료 — 변경 없음.' : '이관 완료. rrn:backfill --verify 로 확인하세요.');

        return self::SUCCESS;
    }

    /* ── 검증 ─────────────────────────────────────────────── */

    private function verify(): int
    {
        $this->line('모드: 검증');
        $this->newLine();

        $fail = 0;

        foreach ([
            ['patients', Patient::class, 'resident_no', 'resident_no_enc', 'resident_no_masked', 'resident_no_hash'],
            ['prescriptions', Prescription::class, 'resident_no_ocr', 'resident_no_ocr_enc', 'resident_no_ocr_masked', null],
        ] as [$label, $model, $plainCol, $encCol, $maskCol, $hashCol]) {

            $residual = $model::withTrashed()
                ->whereNotNull($plainCol)->where($plainCol, '<>', '')
                ->whereNull($encCol)->count();

            $mismatch = 0; $checked = 0;
            $model::withTrashed()->whereNotNull($encCol)->chunkById(200,
                function ($rows) use (&$mismatch, &$checked, $plainCol, $encCol, $maskCol, $hashCol) {
                    foreach ($rows as $row) {
                        $checked++;
                        $plain = $row->getRawOriginal($plainCol);
                        if ($plain === null || $plain === '') {
                            continue;   // 평문이 이미 제거된 뒤라면 대조할 원본이 없다
                        }
                        $back = ResidentNo::decrypt($row->getRawOriginal($encCol), 'backfill_verify');
                        if ($back !== $plain) { $mismatch++; continue; }
                        if ($row->getRawOriginal($maskCol) !== ResidentNo::mask($plain)) { $mismatch++; continue; }
                        if ($hashCol && $row->getRawOriginal($hashCol) !== ResidentNo::hash($plain)) { $mismatch++; }
                    }
                });

            $this->line(sprintf('%-14s 잔여 %d건 · 대조 %d건 · 불일치 %d건', $label, $residual, $checked, $mismatch));
            $fail += $residual + $mismatch;
        }

        $this->newLine();
        if ($fail === 0) {
            $this->info('검증 통과 — 잔여 0 / 불일치 0. 평문 컬럼 제거 마이그레이션을 진행할 수 있습니다.');

            return self::SUCCESS;
        }

        $this->error(sprintf('검증 실패 — 문제 %d건. 평문 컬럼을 제거하지 마세요.', $fail));

        return self::FAILURE;
    }

    /**
     * 보유 기한 기산점 = 해당 환자의 최종 주문일. 없으면 환자 등록일.
     * (config('rrn.retention.basis') 가 last_transaction 일 때)
     */
    private function retentionBasisFor(Patient $patient): ?\Illuminate\Support\Carbon
    {
        if (config('rrn.retention.basis') !== 'last_transaction') {
            return $patient->created_at;
        }

        $last = DB::table('orders')
            ->join('prescriptions', 'orders.prescription_id', '=', 'prescriptions.id')
            ->where('prescriptions.patient_id', $patient->id)
            ->max('orders.created_at');

        return $last ? \Illuminate\Support\Carbon::parse($last) : $patient->created_at;
    }
}
