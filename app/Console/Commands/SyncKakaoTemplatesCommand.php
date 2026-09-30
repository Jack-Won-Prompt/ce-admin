<?php

namespace App\Console\Commands;

use App\Models\MessageTemplate;
use App\Services\Popbill\KakaoService;
use Illuminate\Console\Command;

/**
 * 팝빌에서 승인된 알림톡 템플릿을 우리 메시지 유형으로 옮겨 담는다 (2026-09-30 지시).
 *
 * ## 왜
 *
 * 알림톡은 **승인받은 글자 그대로**만 나간다. 한 자라도 다르면 팝빌이 거절한다.
 * 그런데 담당자가 메시지 관리 화면에서 문구를 손으로 옮겨 적으면 반드시 어긋난다 —
 * 줄바꿈 하나, 마침표 하나가 다르다. 그래서 팝빌이 가진 본문을 그대로 읽어 담는다.
 *
 * ## 본문은 늘 덮는다
 *
 * 다른 유형은 담당자가 고친 문구를 되돌리지 않는데, 알림톡만은 다르다. 고친 문구는
 * 어차피 나가지 못하므로, 고쳐 둔 것이 있어도 승인된 글로 되돌린다. 승인 본문이
 * 바뀌면(재심사) 이 명령을 다시 돌려 맞춘다.
 *
 * ## 코드는 팝빌 코드에서 짓는다
 *
 * `kakao_<팝빌코드>` 로 짓는다. 문자 쪽 코드와 겹치지 않아야 한다 —
 * `MessageTemplate::보낼채널들` 은 **같은 코드**를 가진 문자ㆍ알림톡을 한 쌍으로 보고
 * 둘 다 보낸다. 겹치게 지으면 여태 문자만 나가던 자리에서 알림톡이 저절로 같이
 * 나가기 시작한다 — 그것은 사람이 정할 일이지 이 명령이 정할 일이 아니다.
 *
 * ## 승인된 것만 담는다
 *
 * 상태 3 만 담는다 (0 임시저장ㆍ2 심사중ㆍ3 승인ㆍ4 반려ㆍ5 정지ㆍ7 휴면ㆍ8 삭제).
 *
 * 팝빌은 읽기만 한다.
 */
class SyncKakaoTemplatesCommand extends Command
{
    /** 팝빌 템플릿 상태 — 승인 */
    private const 승인 = 3;

    protected $signature = 'kakao:sync-templates
                            {--force : 실제로 담는다. 없으면 보이기만 한다}
                            {--빈줄정리 : 코드도 본문도 없어 보낼 수 없는 옛 알림톡 줄을 끈다}';

    protected $description = '팝빌에서 승인된 알림톡 템플릿을 메시지 유형으로 옮겨 담습니다 (팝빌은 읽기만 합니다)';

    public function handle(KakaoService $kakao): int
    {
        $정말 = (bool) $this->option('force');
        $corp = (string) config('popbill.test.corp_num');

        $this->line('');
        $this->info('══ 팝빌 승인 알림톡 → 메시지 유형 '
            . ($정말 ? '(실제로 담습니다)' : '(보이기만 합니다)') . ' ══');
        $this->line('  팝빌 갈래 ' . config('popbill.env')
            . ' · 사업자 ' . ($corp ?: '(없음)'));

        if ($corp === '') {
            $this->error('팝빌 사업자번호가 설정되지 않았습니다.');

            return self::FAILURE;
        }

        if (! MessageTemplate::hasAtsColumn()) {
            $this->error('message_templates.ats_template_code 가 없습니다 — 마이그레이션을 먼저 돌려 주십시오.');

            return self::FAILURE;
        }

        try {
            $템플릿들 = $kakao->listTemplates($corp);
        } catch (\Throwable $e) {
            $this->error('팝빌 템플릿 목록을 읽지 못했습니다 — ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->line('  팝빌에 ' . count($템플릿들) . '개');
        $this->line('');

        $셈 = ['승인' => 0, '새로' => 0, '고침' => 0, '그대로' => 0, '건너뜀' => 0];
        $보기 = [];
        $끝 = (int) MessageTemplate::max('sort_order');

        foreach ($템플릿들 as $t) {
            $t = (array) $t;
            $팝빌코드 = trim((string) ($t['templateCode'] ?? ''));
            $이름     = trim((string) ($t['templateName'] ?? ''));
            $본문     = (string) ($t['template'] ?? '');

            if ($팝빌코드 === '' || (int) ($t['state'] ?? -1) !== self::승인) {
                $셈['건너뜀']++;

                continue;
            }

            $셈['승인']++;

            $코드 = 'kakao_' . $팝빌코드;
            $줄   = MessageTemplate::where('channel', 'alimtalk')
                ->where(fn ($q) => $q->where('code', $코드)
                                     ->orWhere('ats_template_code', $팝빌코드))
                ->first();

            $무엇 = $줄 === null ? '새로'
                : (($줄->body ?? '') !== $본문 || $줄->label !== $이름 ? '고침' : '그대로');

            $코드 = $줄?->code ?: $코드;
            $셈[$무엇]++;

            $보기[] = [$팝빌코드, mb_substr($이름, 0, 24), $코드,
                mb_strlen($본문) . '자', self::변수들($본문) ?: '-', $무엇];

            if (! $정말 || $무엇 === '그대로') {
                continue;
            }

            MessageTemplate::updateOrCreate(
                ['id' => $줄?->id],
                [
                    'channel'           => 'alimtalk',
                    /* **이미 있는 줄의 코드는 건드리지 않는다.** 사람이 문자 쪽 코드에
                       맞춰 고쳐 둔 것이 있다(신분증ㆍ위임장 서명처럼 문자와 알림톡을
                       함께 보내려면 코드가 같아야 한다). 여기서 되돌리면 그 자리가
                       조용히 문자만 나가는 상태로 돌아간다. */
                    'code'              => $줄?->code ?: $코드,
                    'ats_template_code' => $팝빌코드,
                    'label'             => $이름,
                    'description'       => '팝빌 승인 알림톡 · 채널 ' . ($t['plusFriendID'] ?? '-'),
                    /* 승인된 글 그대로 — 한 자라도 다르면 팝빌이 거절한다 */
                    'body'              => $본문,
                    'variables'         => self::변수들($본문) ?: null,
                    'sort_order'        => $줄?->sort_order ?? ++$끝,
                    'is_active'         => true,
                ]
            );
        }

        $this->table(['팝빌 코드', '이름', '우리 코드', '본문', '변수', '무엇'], $보기);
        $this->line('');
        $this->table(['무엇', '몇'],
            collect($셈)->map(fn ($v, $k) => [$k, number_format($v)])->values()->all());

        if ($this->option('빈줄정리')) {
            $끌것 = MessageTemplate::where('channel', 'alimtalk')->where('is_active', true)
                ->where(fn ($q) => $q->whereNull('ats_template_code')->orWhere('ats_template_code', ''))
                ->get();

            $this->line('');
            $this->line('  보낼 수 없는 알림톡 줄 ' . $끌것->count() . '개'
                . ($정말 ? ' — 끕니다' : ' (보이기만 합니다)'));

            foreach ($끌것 as $줄) {
                $this->line('    #' . $줄->id . ' ' . $줄->code . ' · ' . $줄->label);

                if ($정말) {
                    $줄->update(['is_active' => false]);
                }
            }
        }

        $this->line('');

        if (! $정말) {
            $this->warn('  보이기만 했습니다. 실제로 담으려면 --force 를 적어 주십시오.');
        } else {
            $this->info('  담았습니다. 쓸 수 있는 알림톡 유형이 지금 '
                . MessageTemplate::where('channel', 'alimtalk')->where('is_active', true)
                    ->whereNotNull('ats_template_code')->count() . '개입니다.');
        }

        $this->line('');

        return self::SUCCESS;
    }

    /** 본문이 쓰는 변수를 모아 준다 — 메시지 관리 화면이 그대로 띄운다 */
    private static function 변수들(string $본문): string
    {
        preg_match_all('/#\{[^}]*\}/u', $본문, $m);

        return implode(', ', array_values(array_unique($m[0] ?? [])));
    }
}
