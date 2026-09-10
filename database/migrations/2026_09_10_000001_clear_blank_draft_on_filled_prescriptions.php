<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 딸린 것이 있는 처방전의 「빈 초안」 표시를 푼다 (2026-09-09 지시).
 *
 * 빈 초안 표시는 처방전 줄 자체가 저장될 때만 풀렸다. 그런데 앱으로 올린 건은 줄을
 * 만들어 두고 파일만 붙이므로 그 표시가 켜진 채로 남았고, 주문 등록 화면이 「주문
 * 목록」 탭으로 열려 웹으로 올린 건과 달리 상세 목록이 보이지 않았다.
 *
 * 앞으로는 서류가 붙는 그 자리에서 풀린다(PrescriptionAttachment::booted). 이미
 * 쌓인 것은 여기서 한 번 맞춘다 — 잣대는 blankDraft 스코프와 같다: 서류ㆍ동의ㆍ
 * 생성서류ㆍ주문 가운데 하나라도 딸려 있으면 더는 빈 초안이 아니다.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('prescriptions') || ! Schema::hasColumn('prescriptions', 'is_blank_draft')) {
            return;
        }

        /* 딸린 것을 or 로 잇되 「빈 초안인 것」과는 and 로 묶는다 —
           묶지 않으면 딸린 것이 있는 모든 처방전이 걸린다 */
        $대상 = DB::table('prescriptions')
            ->where('is_blank_draft', true)
            ->where(function ($w) {
                foreach ([
                    'prescription_attachments' => 'prescription_id',
                    'prescription_consents'    => 'prescription_id',
                    'prescription_documents'   => 'prescription_id',
                    'orders'                   => 'prescription_id',
                ] as $표 => $열쇠) {
                    if (Schema::hasTable($표)) {
                        $w->orWhereIn('id', DB::table($표)->whereNotNull($열쇠)->select($열쇠));
                    }
                }
            });

        /* 로그를 남기지 않는다. 하루치 로그 파일을 웹이 먼저 만들면 명령줄이 못 써서,
           자료는 이미 고쳐 놓고 로그 한 줄 때문에 마이그레이션이 죽었다(2026-09-10). */
        $대상->update(['is_blank_draft' => false]);
    }

    /**
     * 되돌리지 않는다.
     *
     * 어느 줄이 원래 켜져 있었는지 남겨 두지 않았고, 되살리면 그 건들이 다시 「주문
     * 목록」으로 열린다 — 고치려던 그 일이다.
     */
    public function down(): void
    {
    }
};
