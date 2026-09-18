<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 시험을 처음부터 다시 하려고 업무 자료를 지운다 (2026-09-18 지시).
 *
 * 남기는 것은 둘이다 — **설정 묶음**(관리자ㆍ권한ㆍ마스터ㆍ환경ㆍ연동 설정)과
 * **운영 데이터 묶음**(위임장 서명). 나머지 업무 자료는 모두 지운다.
 *
 * 왜 `data:reset` 을 두고 새로 만드는가 — 그 명령은 표 열두 개만 다룬다. 지금 표가
 * 일흔넷이라 그대로 돌리면 **반쯤 지워진 상태**가 된다. 자식은 남고 부모만 사라져,
 * 화면마다 빈 이름과 0원이 서는 자료가 남는다.
 *
 * 세 가지를 지킨다.
 *
 * **① 표를 빠짐없이 가른다.** 아래 세 목록에 들지 않은 표가 있으면 멈춘다 — 새 표가
 *    생겼는데 목록에 안 넣으면 조용히 남는다. 「빠뜨렸다」를 사람이 알아채기 전에
 *    이 명령이 먼저 말한다.
 *
 * **② 자식부터 지운다.** 외래키를 끄지 않는다. 끄고 지우면 CASCADE 가 돌지 않아
 *    고아가 남고, 무엇이 잘못됐는지도 드러나지 않는다. 차례가 틀리면 그 자리에서
 *    막히는 편이 낫다.
 *
 * **③ 번호를 되감지 않는다.** TRUNCATE 가 아니라 DELETE 다. 세금계산서 문서번호는
 *    「TI + 날짜 + 주문 id」라, id 를 1 부터 다시 시작하면 **같은 날 초기화한 경우
 *    옛 번호와 겹쳐** 팝빌이 거절한다. 반품 접수번호도 마찬가지다.
 *    되감아야 할 까닭이 분명하면 --reset-ids 로 따로 고른다.
 */
class ResetForRetest extends Command
{
    protected $signature = 'data:retest-reset
                            {--dry-run : 지울 것만 보여 주고 손대지 않는다}
                            {--reset-ids : 번호를 1부터 다시 시작한다 (겹침 위험)}
                            {--keep-files : 보관함 파일은 두고 표만 지운다}
                            {--force : 묻지 않고 바로 지운다}';

    protected $description = '시험을 다시 하려고 업무 자료를 지운다 (설정ㆍ운영 데이터는 남김)';

    /**
     * 지운다 — 자식이 앞, 부모가 뒤.
     *
     * CASCADE 가 걸린 것도 적어 둔다. 적어 두면 건수가 눈에 보이고, 언젠가 그 외래키가
     * 빠져도 이 목록이 계속 맞다.
     */
    private const 지울표 = [
        /* 기록 — 웹훅ㆍ오류 자취도 함께 지운다 (2026-09-18 지시).

           설정 묶음에 딸린 화면이라 처음에는 남겼는데, 담긴 것은 설정이 아니라
           **지난 시험의 자취**다. 남겨 두면 새로 시작한 시험의 자취와 섞여, 어느 것이
           이번 것인지 가리기 어렵다. 웹훅 자체(webhooks)와 그 항목(webhook_params)은
           설정이므로 그대로 남는다. */
        'user_activity_logs', 'activity_log', 'webhook_logs', 'error_logs',
        // 채팅ㆍ알림
        'chat_messages', 'chat_room_users', 'chat_rooms', 'fcm_notifications',
        // 지원
        'inquiry_messages', 'inquiries', 'notice_reads', 'notices', 'service_requests',
        // 돈
        'bank_transaction_splits', 'bank_transactions',
        'payment_links', 'toss_payments',
        // 증빙
        'cashbill_records', 'popbill_taxinvoices',
        // 발송
        'nhis_fax_logs', 'fax_histories', 'message_histories', 'local_claim_dispatches',
        // 반품
        'order_return_logs', 'order_return_items', 'order_returns',
        // 주문
        'order_item_lots', 'order_items', 'withworks_events', 'orders',
        // 시제품ㆍ쇼핑몰
        'sample_order_items', 'sample_orders',
        'shop_product_logs', 'shop_orders', 'shop_user_sessions',
        // 처방
        'prescription_memos', 'prescription_items', 'prescription_documents',
        'prescription_consents', 'prescription_attachments',
        'prescription_reupload_requests', 'prescriptions',
        // 환자
        'privacy_consents', 'patient_addresses', 'patients',
        /* 마스터 — 병원을 지운다 (2026-09-18 지시). 시험하며 쌓은 자료다. */
        'hospitals',
    ];

    /**
     * 표째로 비우지 않고 **고른 줄만** 지우는 자리 (2026-09-18 지시).
     *
     * 청구처가 그렇다. 공단 지사는 시험하며 쌓은 것이라 지우고, **지자체(시군구청)는
     * 남긴다** — 234곳은 요양비를 등기로 받는 곳이라 우리가 시험하며 만든 것이 아니라
     * 실제 행정 자료에 가깝다.
     *
     * 관할(billing_office_areas)은 통째로 남긴다(지시). 그래서 지워진 공단 지사의
     * 관할 줄은 홀로 남는다 — 외래키가 걸려 있지 않아 막히지도, 따라 지워지지도
     * 않는다. 목록 조회는 청구처 쪽에서 물으므로 그 줄은 보이지 않고, 공단 지사를
     * 다시 쌓을 때 옛 id 를 그대로 받지 않으면 이어지지 않는다.
     *
     * @var array<string, array<string, mixed>> 표 => [칸 => 값]
     */
    private const 골라지울표 = [
        'billing_offices' => ['kind' => 'nhis'],
    ];

    /** 남긴다 — 설정 묶음과 운영 데이터 묶음 */
    private const 남길표 = [
        // 설정 › 관리자ㆍ권한
        'users', 'admin_invitations', 'permission_groups', 'permission_group_pages',
        'personal_access_tokens', 'login_otp_tokens',
        /* 설정 › 마스터 관리 — 기관과 관할만 남는다.
           병원ㆍ청구처는 지울표로 옮겼다 (2026-09-18 지시). */
        'master_items', 'billing_office_areas',
        // 설정 › 환경ㆍ연동
        'common_codes', 'settings', 'message_templates', 'return_reasons',
        'delegation_settings', 'nice_settings', 'ocr_settings', 'withworks_settings',
        // 설정 › 웹훅 (자취인 webhook_logs ㆍ error_logs 는 지울표에 있다)
        'webhooks', 'webhook_params',
        // 운영 데이터 › 위임장 서명
        'delegation_signs',
    ];

    /** 프레임워크가 쓰는 자리 — 업무 자료가 아니다 */
    private const 건드리지않을표 = [
        'migrations', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs',
        'sessions', 'password_reset_tokens',
    ];

    /**
     * 지울 보관함 자리.
     *
     * **delegation-signs 는 없다.** 위임장 서명은 남기는 자료이고, 그 서명 그림이
     * 여기 있다(34MB).
     */
    private const 지울자리 = [
        'private' => [
            'tax_invoices', 'cash_receipts', 'consents', 'delegations',
            'registrations', 'fax', 'local_claims',
        ],
        'public' => [
            'prescriptions', 'fax', 'attachments', 'chat_attachments', 'inquiry-attachments',
        ],
    ];

    public function handle(): int
    {
        if (! $this->표를빠짐없이갈랐나()) {
            return self::FAILURE;
        }

        $건수 = $this->건수();
        $이건 = $this->option('dry-run');

        $this->그려준다($건수, $이건);

        if ($이건) {
            $this->newLine();
            $this->info('보여 주기만 했습니다 — 아무것도 지우지 않았습니다.');

            return self::SUCCESS;
        }

        if (! $this->option('force')
            && ! $this->confirm('위 자료를 지웁니다. 되돌릴 수 없습니다. 계속할까요?', false)) {
            $this->info('그만두었습니다.');

            return self::SUCCESS;
        }

        $this->표지우기();

        if (! $this->option('keep-files')) {
            $this->파일지우기();
        }

        $this->newLine();
        $this->info('끝났습니다. 남은 줄을 다시 세어 보십시오 — data:retest-reset --dry-run');

        return self::SUCCESS;
    }

    /**
     * 목록에 들지 않은 표가 있으면 멈춘다.
     *
     * 표가 새로 생겼는데 어느 목록에도 넣지 않으면, 지울 것이 조용히 남거나 남길 것이
     * 조용히 사라진다. 둘 다 나중에야 드러난다.
     */
    private function 표를빠짐없이갈랐나(): bool
    {
        $있는것 = collect(DB::select(
            'SELECT TABLE_NAME t FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?',
            [DB::getDatabaseName()]
        ))->pluck('t')->all();

        $가른것 = array_merge(self::지울표, array_keys(self::골라지울표),
                              self::남길표, self::건드리지않을표);
        $모르는것 = array_diff($있는것, $가른것);
        $없는것   = array_diff($가른것, $있는것);

        if ($없는것) {
            $this->warn('목록에 있으나 DB 에 없는 표 (지나갑니다): ' . implode(', ', $없는것));
        }

        if ($모르는것) {
            $this->error('어느 목록에도 없는 표가 있습니다 — 지울지 남길지 정해 주십시오:');
            foreach ($모르는것 as $t) {
                $this->line('  · ' . $t . ' (' . number_format($this->센다($t)) . '줄)');
            }
            $this->line('app/Console/Commands/ResetForRetest.php 의 세 목록 가운데 하나에 넣으십시오.');

            return false;
        }

        return true;
    }

    /** @return array{지움: array<string,int>, 남김: array<string,int>} */
    private function 건수(): array
    {
        $지움 = $남김 = [];

        foreach (self::지울표 as $t) {
            if (Schema::hasTable($t)) { $지움[$t] = $this->센다($t); }
        }
        foreach (self::골라지울표 as $t => $조건) {
            if (! Schema::hasTable($t)) { continue; }

            $이름 = $t . ' (' . $this->조건말($조건) . ')';
            $지움[$이름] = $this->센다($t, $조건);
            /* 남는 쪽도 함께 보여 준다 — 「고른 줄만」은 무엇이 남는지가 더 궁금하다 */
            $남김[$t . ' (나머지)'] = $this->센다($t) - $지움[$이름];
        }

        foreach (self::남길표 as $t) {
            if (Schema::hasTable($t)) { $남김[$t] = $this->센다($t); }
        }

        return ['지움' => $지움, '남김' => $남김];
    }

    private function 센다(string $표, array $조건 = []): int
    {
        try { return (int) DB::table($표)->where($조건)->count(); }
        catch (\Throwable $e) { return -1; }
    }

    /** 조건을 사람이 읽을 말로 — 「kind=nhis」 */
    private function 조건말(array $조건): string
    {
        return implode(' · ', array_map(
            fn ($k, $v) => $k . '=' . $v,
            array_keys($조건), array_values($조건)
        ));
    }

    private function 그려준다(array $건수, bool $이건): void
    {
        $this->newLine();
        $this->warn($이건 ? '── 지울 것 (보여 주기만 함) ──' : '── 지웁니다 ──');

        foreach ($건수['지움'] as $t => $n) {
            if ($n > 0) { $this->line(sprintf('  %-34s %8s', $t, number_format($n))); }
        }
        $this->line(sprintf('  %-34s %8s', '합계', number_format(array_sum($건수['지움']))));

        $this->newLine();
        $this->info('── 남길 것 ──');
        foreach ($건수['남김'] as $t => $n) {
            if ($n > 0) { $this->line(sprintf('  %-34s %8s', $t, number_format($n))); }
        }
        $this->line(sprintf('  %-34s %8s', '합계', number_format(array_sum($건수['남김']))));

        $this->newLine();
        $this->info('── 번호 ──');
        $this->line($this->option('reset-ids')
            ? '  1부터 다시 시작합니다 — 같은 날 다시 시험하면 팝빌 문서번호가 겹칠 수 있습니다.'
            : '  이어서 씁니다 (겹침 없음). 되감으려면 --reset-ids');

        if (! $this->option('keep-files')) {
            $this->newLine();
            $this->info('── 지울 보관함 ──');
            foreach (self::지울자리 as $disk => $dirs) {
                foreach ($dirs as $d) {
                    $this->line('  · storage/app/' . ($disk === 'private' ? 'private/' : 'public/') . $d);
                }
            }
            $this->line('  (delegation-signs 는 남깁니다 — 위임장 서명의 그림입니다)');
        }
    }

    private function 표지우기(): void
    {
        $this->newLine();
        $this->info('표를 지웁니다 (자식부터)…');

        foreach (self::지울표 as $표) {
            if (! Schema::hasTable($표)) { continue; }

            try {
                $전 = $this->센다($표);
                DB::table($표)->delete();
                $this->line(sprintf('  v %-34s %8s 줄', $표, number_format($전)));

                if ($this->option('reset-ids')) {
                    DB::statement("ALTER TABLE `{$표}` AUTO_INCREMENT = 1");
                }
            } catch (\Throwable $e) {
                /* 막히면 그 자리에서 말한다 — 차례가 틀렸거나 새 외래키가 생긴 것이다.
                   외래키를 꺼서 뚫지 않는다. 뚫으면 고아가 남고 아무도 모른다. */
                $this->error(sprintf('  X %-34s %s', $표, $e->getMessage()));
            }
        }

        foreach (self::골라지울표 as $표 => $조건) {
            if (! Schema::hasTable($표)) { continue; }

            try {
                $전 = $this->센다($표, $조건);
                DB::table($표)->where($조건)->delete();
                $this->line(sprintf('  v %-34s %8s 줄 (%s · 나머지 %s 줄 남김)',
                    $표, number_format($전), $this->조건말($조건),
                    number_format($this->센다($표))));
            } catch (\Throwable $e) {
                $this->error(sprintf('  X %-34s %s', $표, $e->getMessage()));
            }
        }
    }

    private function 파일지우기(): void
    {
        $this->newLine();
        $this->info('보관함을 비웁니다…');

        $뿌리 = storage_path('app');

        foreach (self::지울자리 as $disk => $dirs) {
            foreach ($dirs as $d) {
                $자리 = $뿌리 . '/' . ($disk === 'private' ? 'private/' : 'public/') . $d;

                if (! is_dir($자리)) {
                    $this->line('  - ' . $d . ' (없음)');
                    continue;
                }

                /* 자리 자체는 남기고 안만 비운다 — 자리를 지우면 권한ㆍ소유자가 함께
                   사라져, 다음에 웹이 만들 때 다른 꼴로 선다. */
                $지움 = $this->안을비운다($자리);
                $this->line(sprintf('  v %-28s %s개', $d, number_format($지움)));
            }
        }
    }

    private function 안을비운다(string $자리): int
    {
        $센것 = 0;

        foreach (scandir($자리) ?: [] as $이름) {
            if ($이름 === '.' || $이름 === '..' || $이름 === '.gitignore') { continue; }

            $것 = $자리 . '/' . $이름;

            try {
                if (is_dir($것)) {
                    $센것 += $this->안을비운다($것);
                    @rmdir($것);
                } else {
                    @unlink($것);
                    $센것++;
                }
            } catch (\Throwable $e) {
                $this->warn('    못 지움: ' . $것 . ' — ' . $e->getMessage());
            }
        }

        return $센것;
    }
}
