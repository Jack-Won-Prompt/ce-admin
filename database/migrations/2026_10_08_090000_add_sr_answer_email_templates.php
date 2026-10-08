<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SR 답변 메일 문구 (2026-10-08 지시).
 *
 * 답변이 등록되면 올린 담당자에게 메일로 알린다. 문구를 코드에 박지 않고 이 표에
 * 두는 까닭은, 보내는 글을 담당자가 화면에서 고칠 수 있어야 하기 때문이다.
 *
 * 본문이 둘인 까닭 — 담당자가 직접 쓴 답변은 전문을 싣고, 자동으로 등록된 답변은
 * 「등록되었습니다」만 알린다. 자동 답변은 담당자가 한 번 보고 손볼 여지를 두어야
 * 해서, 내용을 메일로 먼저 보내지 않는다.
 *
 * 처리 완료된 답변은 보내지 않는다 — 그 잣대는 코드가 가린다.
 */
return new class extends Migration
{
    private const 문구 = [
        [
            'code'  => 'sr_answer_email_subject',
            'label' => 'SR 답변 메일 — 제목',
            'step'  => 'SR 에 답변이 등록될 때',
            'vars'  => '#{제목}',
            'body'  => '[CE Admin] 요청하신 건에 답변이 등록되었습니다 — #{제목}',
            'sort'  => 10,
        ],
        [
            'code'  => 'sr_answer_email',
            'label' => 'SR 답변 메일 — 본문(담당자 답변)',
            'step'  => '담당자가 직접 답변을 등록할 때',
            'vars'  => '#{이름}, #{제목}, #{등록일}, #{답변}, #{주소}',
            'body'  => "안녕하십니까, #{이름} 님.\n\n"
                     . "#{등록일}에 등록해 주신 요청에 답변을 등록하였습니다.\n\n"
                     . "［#{제목}］\n\n"
                     . "#{답변}\n\n"
                     . "아래 화면에서도 확인하실 수 있습니다.\n#{주소}\n\n"
                     . "추가로 문의하실 사항이 있으면 해당 건으로 요청해 주십시오.\n\n"
                     . "CE Admin 운영팀",
            'sort'  => 11,
        ],
        [
            'code'  => 'sr_answer_notice_email',
            'label' => 'SR 답변 메일 — 본문(등록 안내)',
            'step'  => '답변이 등록되어 확인을 안내할 때',
            'vars'  => '#{이름}, #{제목}, #{등록일}, #{주소}',
            'body'  => "안녕하십니까, #{이름} 님.\n\n"
                     . "#{등록일}에 등록해 주신 요청에 답변이 등록되었습니다. 아래 화면에서 "
                     . "내용을 확인해 주십시오.\n\n"
                     . "［#{제목}］\n\n"
                     . "#{주소}\n\n"
                     . "확인 후 보완이 필요한 사항이 있으면 해당 건으로 다시 요청해 주십시오.\n\n"
                     . "CE Admin 운영팀",
            'sort'  => 12,
        ],
    ];

    public function up(): void
    {
        foreach (self::문구 as $하나) {
            if (DB::table('message_templates')
                    ->where('channel', 'email')->where('code', $하나['code'])->exists()) {
                continue;
            }

            DB::table('message_templates')->insert([
                'channel'    => 'email',
                'code'       => $하나['code'],
                'label'      => $하나['label'],
                'screen'     => 'SR 관리',
                'step'       => $하나['step'],
                'variables'  => $하나['vars'],
                'body'       => $하나['body'],
                'sort_order' => $하나['sort'],
                'is_active'  => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('message_templates')
            ->where('channel', 'email')
            ->whereIn('code', array_column(self::문구, 'code'))
            ->delete();
    }
};
