<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * 시험 자료를 지운다 (2026-09-29 지시).
 *
 * 운영 자료를 거래처 관리로 옮기기에 앞서 시험으로 쌓인 것을 치운다. 그리고 그 뒤에도
 * 시험을 다시 돌릴 수 있어야 하므로, **묶음(batch) 단위로 몇 번이고 지울 수 있게** 둔다.
 *
 * 지키는 것 셋 —
 *
 *   하나  **딱지가 test 인 줄만 지운다.** live 도, 딱지가 없는 줄도 건드리지 않는다.
 *         모르는 것은 남기는 편이 지우는 편보다 언제나 낫다.
 *
 *   둘    **운영 데이터 메뉴의 표는 절대 건드리지 않는다** (지시).
 *         ww_customers · ww_customer_addresses · ww_prescription_infos ·
 *         delegation_signs 넷이다. 아래 «막은표» 가 그것을 못박고, 지우는 자리마다
 *         다시 확인한다 — 나중에 표를 더하는 사람이 실수로 끼워 넣지 못하게.
 *
 *   셋    **먼저 세어 보이고, 그 다음에 지운다.** --dry 가 기본이다.
 *         실제로 지우려면 --force 를 적어야 한다.
 *
 * 밖으로 나간 자취(팝빌 발행ㆍ토스 승인ㆍ위드웍스 판매주문ㆍ문자)는 우리 표를 지워도
 * 저쪽에 남는다. 지우기 전에 그 목록을 내놓아 사람이 따로 치울 수 있게 한다.
 */
class PurgeTestDataCommand extends Command
{
    protected $signature = 'test-data:purge
                            {--batch= : 지울 묶음(data_batch). 비우면 test 딱지 전부}
                            {--force : 실제로 지운다. 없으면 세어 보이기만 한다}
                            {--files : 딸린 파일도 지운다}
                            {--traces : 주인 없이 남는 이력까지 지운다. 기본은 남긴다}';

    protected $description = '시험 딱지가 붙은 자료를 지운다 (운영 데이터 표는 건드리지 않는다)';

    /**
     * **절대 건드리지 않는 표** (2026-09-29 지시).
     *
     * 운영 데이터 메뉴가 보는 자리다. 여기 적힌 표는 이 명령이 어떤 길로도 지우거나
     * 고치지 않는다 — 아래 «확인» 이 매번 견준다.
     */
    private const 막은표 = [
        'ww_customers',
        'ww_customer_addresses',
        'ww_prescription_infos',
        'delegation_signs',
    ];

    public function handle(): int
    {
        $묶음 = $this->option('batch');
        $정말 = (bool) $this->option('force');

        $this->line('');
        $this->info('══ 시험 자료 지우기 ' . ($정말 ? '(실제로 지웁니다)' : '(세어 보이기만 합니다)') . ' ══');
        $this->line('  묶음 : ' . ($묶음 ?: '(test 딱지 전부)'));
        $this->line('');

        /* 지울 것을 고른다 — 나머지는 이것을 따라 내려간다.

           **모델이 아니라 표를 바로 읽는다.** 처방전ㆍ주문은 지운 표시(deleted_at)를
           쓰는 모델이라 Prescription::where(...) 로 고르면 지운 표시가 된 줄이
           빠진다 — 처방전 36줄ㆍ주문 14줄이 그러했다(2026-09-29 확인). 그 줄만
           남으면 딸린 품목ㆍ결제ㆍ서류가 주인 없이 남고, 다음 시험에서 셈이 어긋난다.
           지우려고 고르는 자리에서 「이미 지운 것처럼 보이는 줄」을 빼서는 안 된다. */
        $고르기 = fn (string $표) => DB::table($표)->where('data_origin', 'test')
            ->when($묶음, fn ($q) => $q->where('data_batch', $묶음))
            ->pluck('id');

        $거래처 = $고르기('patients');
        $처방   = $고르기('prescriptions');
        $주문   = $고르기('orders');
        $반품   = $고르기('order_returns');

        if ($거래처->isEmpty() && $처방->isEmpty() && $주문->isEmpty()) {
            $this->warn('  지울 것이 없습니다 — 시험 딱지가 붙은 줄이 없습니다.');

            return self::SUCCESS;
        }

        $this->바깥자취($주문, $반품);

        /* 지울 차례 — 매달린 것부터, 뿌리는 마지막에 */
        $셈 = [];

        foreach ($this->지울것($거래처, $처방, $주문, $반품) as [$표, $칸, $열쇠, $이름]) {
            $this->확인($표);

            $n = DB::table($표)->whereIn($칸, $열쇠)->count();

            if ($n === 0) {
                continue;
            }

            $셈[] = [$표, $이름, number_format($n)];

            if ($정말) {
                DB::table($표)->whereIn($칸, $열쇠)->delete();
            }
        }

        $this->line('');
        $this->table(['표', '무엇', '줄'], $셈);

        $this->남는이력($거래처, $처방, $주문, $정말);

        if ($this->option('files')) {
            $this->파일지우기($처방, $정말);
        }

        $this->line('');

        if (! $정말) {
            $this->warn('  세어 보이기만 했습니다. 실제로 지우려면 --force 를 적어 주십시오.');
        } else {
            $this->info('  지웠습니다.');
        }

        $this->line('  운영 데이터 표(' . implode(' · ', self::막은표) . ')는 건드리지 않았습니다.');
        $this->line('');

        return self::SUCCESS;
    }

    /**
     * 지울 차례 — [표, 칸, 열쇠, 사람이 읽을 이름].
     *
     * 매달린 것을 먼저 지우고 뿌리를 나중에 지운다. 외래키가 SET NULL 이라 뿌리부터
     * 지워도 표는 서지만, 그러면 주인 없는 줄이 남아 다음 시험에서 셈이 어긋난다.
     */
    private function 지울것($거래처, $처방, $주문, $반품): array
    {
        return [
            // ── 반품에 매달린 것
            ['order_return_items',       'order_return_id', $반품,   '반품 품목'],
            ['order_return_logs',        'order_return_id', $반품,   '반품 이력'],
            ['order_returns',            'id',              $반품,   '교환ㆍ반품ㆍ취소'],

            // ── 주문에 매달린 것
            ['order_items',              'order_id',        $주문,   '주문 품목'],
            ['order_amendments',         'order_id',        $주문,   '주문 정정'],
            ['toss_payments',            'order_id',        $주문,   '토스 결제'],
            ['payment_links',            'order_id',        $주문,   '결제 링크'],
            ['orders',                   'id',              $주문,   '주문'],

            // ── 처방전에 매달린 것
            ['prescription_items',       'prescription_id', $처방,   '처방 품목'],
            ['prescription_attachments', 'prescription_id', $처방,   '처방 첨부'],
            ['prescription_documents',   'prescription_id', $처방,   '서류함'],
            ['prescription_consents',    'prescription_id', $처방,   '처방 동의'],
            ['prescriptions',            'id',              $처방,   '처방전'],

            // ── 거래처에 매달린 것
            ['patient_addresses',        'patient_id',      $거래처, '거래처 주소'],
            ['privacy_consents',         'patient_id',      $거래처, '개인정보 동의'],
            /* 옮겨 담은 위임장 서명 — **사본이라 지운다.** 원본(delegation_signs)은
               «막은표» 에 있어 닿지 않는다. 사본을 남겨 두면 다음에 옮길 때 주인 없는
               서명이 거래처 화면에 서고, 유일 색인 때문에 다시 옮기지도 못한다. */
            ['patient_delegation_signs', 'patient_id',      $거래처, '위임장 서명(옮겨 담은 것)'],
            ['patients',                 'id',              $거래처, '거래처'],
        ];
    }

    /**
     * 건드려서는 안 되는 표인가 — 매번 견준다.
     *
     * 지울 표 목록은 사람이 손으로 적는 자리다. 나중에 한 줄 더하다 운영 데이터 표를
     * 끼워 넣으면 17,875줄이 소리 없이 사라진다. 그 일이 일어나지 않게 여기서 멈춘다.
     */
    private function 확인(string $표): void
    {
        if (in_array($표, self::막은표, true)) {
            throw new \RuntimeException(
                "운영 데이터 표({$표})는 지울 수 없습니다 — 이 명령이 닿아서는 안 되는 자리입니다.");
        }
    }

    /**
     * 밖으로 나간 자취 — 우리 표를 지워도 저쪽에 남는다.
     *
     * 지우기 전에 보여 주어야 사람이 팝빌ㆍ토스ㆍ위드웍스에서 따로 치울 수 있다.
     * 지운 뒤에는 무엇이 있었는지 알 길이 없다.
     */
    private function 바깥자취($주문, $반품): void
    {
        /* 여기도 표를 바로 읽는다 — 지운 표시가 된 주문에도 발행해 둔 증빙이 있다.
           모델로 세면 그 자취가 목록에서 빠져, 팝빌에 남은 것을 사람이 모르고 지나친다. */
        $세금 = DB::table('orders')->whereIn('id', $주문)->where('tax_invoice_status', 'issued')
            ->pluck('tax_invoice_no', 'order_number');
        $현금 = DB::table('orders')->whereIn('id', $주문)->where('cash_receipt_status', 'issued')
            ->pluck('cash_receipt_no', 'order_number');
        $토스 = DB::table('toss_payments')->whereIn('order_id', $주문)
            ->whereIn('status', ['DONE', 'PARTIAL_CANCELED'])->get(['payment_key', 'amount', 'cancel_amount']);
        $창고 = DB::table('orders')->whereIn('id', $주문)->whereNotNull('withworks_so_no')
            ->pluck('withworks_so_no', 'order_number');
        $반품창고 = DB::table('order_returns')->whereIn('id', $반품)->whereNotNull('withworks_so_no')
            ->pluck('withworks_so_no', 'receipt_no');

        $this->warn('  ── 밖으로 나간 자취 — 우리 표를 지워도 저쪽에 남습니다 ──');
        $this->line('     팝빌 세금계산서 ' . $세금->count() . '건 · 현금영수증 ' . $현금->count() . '건');
        $this->line('     토스 승인 ' . $토스->count() . '건 (아직 쥐고 있는 돈 '
            . number_format($토스->sum(fn ($t) => max(0, (int) $t->amount - (int) ($t->cancel_amount ?? 0)))) . '원)');
        $this->line('     위드웍스 판매주문 ' . $창고->count() . '건 · 반품주문 ' . $반품창고->count() . '건');
        $this->line('     ※ 지금 환경은 토스 test · 팝빌 IsTest · 위드웍스 demoworks 입니다.');
        $this->line('        운영이라면 이 명령을 쓰지 마십시오.');

        /* 열쇠를 적어 둔다 — **지우기 전에.**

           우리 줄을 지우면 팝빌 문서번호와 토스 결제열쇠가 함께 사라진다. 그것이
           없으면 저쪽에 남은 것을 취소할 길이 없다 — 토스가 아직 쥐고 있는 돈이
           2,948,100원인데 열쇠를 잃으면 화면으로도 지울 수 없다.
           세어 보이기만 할 때도 적는다. 지우기 전에 사람이 파일을 열어 볼 수 있어야 한다. */
        $적을것 = [
            '적은때'    => now()->toDateTimeString(),
            '정말지웠나' => false,
            '세금계산서' => $세금,
            '현금영수증' => $현금,
            '토스승인'   => $토스,
            '위드웍스판매주문' => $창고,
            '위드웍스반품주문' => $반품창고,
            '팝빌세금계산서자취' => DB::table('popbill_taxinvoices')->whereIn('order_id', $주문)
                ->get(['order_id', 'mgt_key', 'mgt_key_type', 'nts_confirm_num', 'state_code']),
            '팝빌현금영수증자취' => DB::table('cashbill_records')->whereIn('order_id', $주문)
                ->get(['order_id', 'mgt_key', 'confirm_num', 'state_code']),
        ];

        $이름 = 'purge-traces/' . now()->format('Ymd-His') . '.json';

        Storage::disk('local')->put($이름, json_encode($적을것, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $this->line('');
        $this->info('     밖으로 나간 자취의 열쇠를 적어 두었습니다 —');
        $this->line('       ' . Storage::disk('local')->path($이름));
        $this->line('       팝빌ㆍ토스에서 치울 때 이 파일의 문서번호ㆍ결제열쇠를 씁니다.');
        $this->line('');
    }

    /**
     * 지운 뒤 주인 없이 남는 이력 (2026-09-29 지시).
     *
     * 「테스트 이력 제거하지 않음」이 지시라 **기본은 남긴다.** 무엇을 언제 보냈고
     * 무엇을 발행했는지는 지운 자료와 별개로 남아야 하는 자취다.
     *
     * 다만 남는다는 것을 **보여 주어야** 한다. 거래처와 주문이 사라진 뒤 이 줄들은
     * 가리킬 곳이 없는 번호를 들고 있게 되고, 그것을 모르면 다음 시험에서 「문자가
     * 619건이나 나갔다」로 읽힌다. 세어 보이고, 정말 치우려면 --traces 를 적게 한다.
     */
    private function 남는이력($거래처, $처방, $주문, bool $정말): void
    {
        $것들 = [
            ['message_histories',              'prescription_id', $처방,   '문자ㆍ알림 발송 이력'],
            ['withworks_events',               'order_id',        $주문,   '위드웍스 주고받은 것'],
            ['popbill_taxinvoices',            'order_id',        $주문,   '팝빌 세금계산서 자취'],
            ['cashbill_records',               'order_id',        $주문,   '팝빌 현금영수증 자취'],
            ['prescription_reupload_requests', 'prescription_id', $처방,   '처방전 다시 올리기 요청'],
            ['fax_histories',                  'prescription_id', $처방,   '팩스 보낸 자취'],
            ['local_claim_dispatches',         'order_id',        $주문,   '지역 청구 발송'],
        ];

        $지울까 = (bool) $this->option('traces');
        $셈 = [];

        foreach ($것들 as [$표, $칸, $열쇠, $이름]) {
            $this->확인($표);

            if (! \Illuminate\Support\Facades\Schema::hasTable($표)) {
                continue;
            }

            $n = DB::table($표)->whereIn($칸, $열쇠)->count();

            if ($n === 0) {
                continue;
            }

            $셈[] = [$표, $이름, number_format($n)];

            if ($지울까 && $정말) {
                DB::table($표)->whereIn($칸, $열쇠)->delete();
            }
        }

        if ($셈 === []) {
            return;
        }

        $this->line('');
        $this->warn($지울까
            ? '  ── 이력도 함께 지웁니다 (--traces) ──'
            : '  ── 주인 없이 남는 이력 — 지시대로 남깁니다 ──');
        $this->table(['표', '무엇', '줄'], $셈);

        if (! $지울까) {
            $this->line('  이 줄들은 사라진 거래처ㆍ주문의 번호를 들고 남습니다.');
            $this->line('  정말 치우려면 --traces 를 함께 적어 주십시오.');
        }
    }

    /** 딸린 파일 — 지우면 되돌릴 수 없어 따로 물어본다(--files) */
    private function 파일지우기($처방, bool $정말): void
    {
        $길 = DB::table('prescription_attachments')->whereIn('prescription_id', $처방)
            ->whereNotNull('file_path')->pluck('file_path');

        $this->line('  딸린 파일 ' . $길->count() . '개' . ($정말 ? ' — 지웁니다' : ''));

        if (! $정말) {
            return;
        }

        foreach ($길 as $p) {
            try {
                Storage::disk('public')->delete($p);
            } catch (\Throwable $e) {
                $this->warn('    지우지 못함: ' . $p);
            }
        }
    }
}
