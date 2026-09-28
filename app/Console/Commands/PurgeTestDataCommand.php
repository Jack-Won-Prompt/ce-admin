<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
                            {--traces : 주인 없이 남는 이력까지 지운다. 기본은 남긴다}
                            {--orphan-files : 가리키는 줄이 없는 파일을 치운다}';

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

        if ($this->option('orphan-files')) {
            $this->주인없는파일($정말);

            return self::SUCCESS;
        }

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

        /* 딸린 파일의 길을 **지우기 앞에서** 모은다 (2026-09-29 확인).

           지운 뒤에 모으려 했더니 0개가 나왔다 — 첨부 줄이 이미 사라져 길을 읽을
           자리가 없었기 때문이다. 파일 527개(77MB)가 주인 없이 서버에 남았다.
           길은 표에만 적혀 있으므로 표를 지우는 순간 영영 알 수 없게 된다. */
        $파일길 = $this->파일길모으기($처방);

        /* 남는 이력도 **지우기 앞에서** 센다. 뒤에 세면 외래키가 번호를 비운(SET NULL)
           다음이라 한 줄도 잡히지 않는다 — 「남깁니다」라고 표를 보여 주면서 13줄만
           적어, 문자 619줄이 어떻게 되었는지 사람이 알 수 없었다. */
        $this->남는이력($처방, $주문);

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

        if ($this->option('files')) {
            $this->파일지우기($파일길, $정말);
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
     * 지운 뒤 이력은 어떻게 되는가 (2026-09-29 지시).
     *
     * 「테스트 이력 제거하지 않음」이 지시다. 실제로 어떻게 되는지는 표가 아니라
     * **외래키 규칙**이 정한다 —
     *
     *   SET NULL   줄은 남고 처방전ㆍ주문 번호만 빈다. 문자 619ㆍ위드웍스 487ㆍ
     *              팝빌 72+9ㆍ팩스 7 이 그렇다. 지시대로 남는 자리다.
     *   CASCADE    줄이 **딸려 지워진다.** 지역 청구 발송ㆍ건보 팩스 기록이 그렇다.
     *              막을 길이 없다 — 주문을 지우면 표가 함께 지운다.
     *
     * 둘을 갈라 적는다. 앞선 판은 CASCADE 인 것을 「남깁니다」에 적어 두어, 3줄이
     * 조용히 사라지는데도 남는다고 읽혔다.
     *
     * **세기만 한다.** 지우기 앞에서 불려야 하므로, 여기서 지우면 뿌리보다 먼저
     * 지우는 셈이 된다. --traces 는 지우는 자리에서 따로 다룬다.
     */
    private function 남는이력($처방, $주문): void
    {
        /* [표, 칸, 열쇠, 이름, 딸려지워지나] */
        $것들 = [
            ['message_histories',              'prescription_id', $처방, '문자ㆍ알림 발송 이력',     false],
            ['withworks_events',               'order_id',        $주문, '위드웍스 주고받은 것',     false],
            ['popbill_taxinvoices',            'order_id',        $주문, '팝빌 세금계산서 자취',     false],
            ['cashbill_records',               'order_id',        $주문, '팝빌 현금영수증 자취',     false],
            ['prescription_reupload_requests', 'prescription_id', $처방, '처방전 다시 올리기 요청',  false],
            ['fax_histories',                  'prescription_id', $처방, '팩스 보낸 자취',           false],
            ['local_claim_dispatches',         'order_id',        $주문, '지역 청구 발송',           true],
            ['nhis_fax_logs',                  'order_id',        $주문, '건보 팩스 기록',           true],
        ];

        $남음 = [];
        $딸림 = [];

        foreach ($것들 as [$표, $칸, $열쇠, $이름, $딸려지나]) {
            $this->확인($표);

            if (! Schema::hasTable($표)) {
                continue;
            }

            $n = DB::table($표)->whereIn($칸, $열쇠)->count();

            if ($n === 0) {
                continue;
            }

            if ($딸려지나) {
                $딸림[] = [$표, $이름, number_format($n)];
            } else {
                $남음[] = [$표, $이름, number_format($n)];
            }
        }

        if ($남음 !== []) {
            $this->line('');
            $this->info('  ── 이력은 남습니다 (번호만 빕니다) — 지시대로 ──');
            $this->table(['표', '무엇', '줄'], $남음);
        }

        if ($딸림 !== []) {
            $this->line('');
            $this->warn('  ── 이 표는 딸려 지워집니다 (표가 그렇게 맺어져 있습니다) ──');
            $this->table(['표', '무엇', '줄'], $딸림);
        }
    }

    /**
     * 가리키는 줄이 없는 파일을 치운다 (2026-09-29).
     *
     * 파일 길은 표에만 적혀 있어, 표를 먼저 지우면 길을 알 수 없게 된다. 실제로
     * 그렇게 되어 527개(77MB)가 서버에 남았다 — 「딸린 파일 0개」로 보고되었다.
     * 지우는 차례는 고쳤지만, 이미 남은 것을 치울 길이 따로 있어야 한다.
     *
     * **폴더를 통째로 지우지 않는다.** 파일 하나하나를 표와 견주어, 아무 줄도
     * 가리키지 않는 것만 지운다. 폴더째 지우면 아직 쓰이는 파일이 함께 사라진다 —
     * 팩스 자취(fax/)와 문의 첨부가 같은 자리 아래에 있다.
     */
    private function 주인없는파일(bool $정말): void
    {
        $this->line('');
        $this->info('══ 주인 없는 파일 치우기 ' . ($정말 ? '(실제로 지웁니다)' : '(세어 보이기만 합니다)') . ' ══');

        /* 아직 쓰이는 길 — 표에 적힌 것 모두. 여기 없는 파일이 주인 없는 파일이다. */
        $쓰는길 = collect();

        foreach ([['prescription_attachments', 'file_path'],
                  ['prescription_attachments', 'overlay_source_path'],
                  ['prescription_documents',   'file_path'],
                  ['chat_messages',            'attachment_path'],
                  ['fax_histories',            'pdf_path'],
                  ['inquiries',                'attachment_path']] as [$표, $칸]) {
            if (! Schema::hasTable($표) || ! Schema::hasColumn($표, $칸)) {
                continue;
            }

            $쓰는길 = $쓰는길->merge(
                DB::table($표)->whereNotNull($칸)->where($칸, '<>', '')->pluck($칸)
            );
        }

        $쓰는길 = $쓰는길->map(fn ($p) => ltrim((string) $p, '/'))->unique()->flip();

        $this->line('  표가 가리키는 파일 ' . number_format($쓰는길->count()) . '개');

        $disk  = Storage::disk('public');
        $주인없음 = [];
        $크기    = 0;

        foreach ($disk->allFiles() as $f) {
            if (isset($쓰는길[$f])) {
                continue;
            }

            /* 우리가 만든 것이 아닌 자리는 건드리지 않는다 — 폴더 이름으로 가린다.
               나중에 다른 기능이 같은 저장소를 쓰기 시작해도 그 파일은 남는다. */
            if (! preg_match('~^(attachments|prescriptions|inquiry-attachments)/~', $f)) {
                continue;
            }

            $주인없음[] = $f;
            $크기 += (int) $disk->size($f);
        }

        $this->line('  주인 없는 파일 ' . number_format(count($주인없음)) . '개 ('
            . number_format($크기 / 1048576, 1) . 'MB)');

        if ($주인없음 === []) {
            $this->line('');

            return;
        }

        $this->line('  보기 —');
        foreach (array_slice($주인없음, 0, 5) as $f) {
            $this->line('    ' . $f);
        }

        if (! $정말) {
            $this->line('');
            $this->warn('  세어 보이기만 했습니다. 실제로 지우려면 --force 를 적어 주십시오.');
            $this->line('');

            return;
        }

        $지움 = 0;

        foreach ($주인없음 as $f) {
            if ($disk->delete($f)) {
                $지움++;
            }
        }

        $this->line('');
        $this->info('  지운 파일 ' . number_format($지움) . '개');
        $this->line('');
    }

    /**
     * 딸린 파일의 길 — **지우기 앞에서** 모은다.
     *
     * 첨부(처방전 사진ㆍ덧그린 서류)와 서류함(만들어 낸 PDF) 두 자리에 있다.
     * 첨부는 덧그리기 원본(overlay_source_path)도 따로 들고 있어 그것까지 모은다 —
     * 빠뜨리면 원본만 남아 폴더가 줄지 않는다.
     */
    private function 파일길모으기($처방): \Illuminate\Support\Collection
    {
        $길 = collect();

        foreach ([['prescription_attachments', 'file_path'],
                  ['prescription_attachments', 'overlay_source_path'],
                  ['prescription_documents',   'file_path']] as [$표, $칸]) {
            if (! Schema::hasTable($표) || ! Schema::hasColumn($표, $칸)) {
                continue;
            }

            $길 = $길->merge(
                DB::table($표)->whereIn('prescription_id', $처방)
                    ->whereNotNull($칸)->where($칸, '<>', '')->pluck($칸)
            );
        }

        return $길->unique()->values();
    }

    /** 딸린 파일 — 지우면 되돌릴 수 없어 따로 물어본다(--files) */
    private function 파일지우기($길, bool $정말): void
    {
        $this->line('');
        $this->line('  딸린 파일 ' . number_format($길->count()) . '개' . ($정말 ? ' — 지웁니다' : ''));

        if (! $정말) {
            return;
        }

        $지움 = 0;
        $못함 = 0;

        foreach ($길 as $p) {
            try {
                Storage::disk('public')->delete($p) ? $지움++ : $못함++;
            } catch (\Throwable $e) {
                $못함++;
            }
        }

        $this->line('    지운 것 ' . number_format($지움)
            . ' · 찾지 못한 것 ' . number_format($못함));
    }
}
