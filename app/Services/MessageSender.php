<?php

namespace App\Services;

use App\Models\MessageHistory;
use App\Models\MessageTemplate;
use App\Services\Popbill\KakaoService as PopbillKakaoService;
use App\Services\Popbill\MessageService as PopbillMessageService;
use Illuminate\Support\Facades\Log;

/**
 * 여러 사람에게 한 번에 보내는 자리.
 *
 * 문자도 알림톡도 팝빌로 나간다. 부르는 쪽이 그 사정을 알 필요는 없으므로 여기서 갈라 준다.
 *
 * **알림톡은 2026-09-30 에 알리고에서 팝빌로 옮겼다.** 알리고 열쇠(KAKAO_API_KEY·
 * KAKAO_SENDER_KEY)는 한 번도 채워진 적이 없어 그 길은 늘 「키가 설정되지 않았습니다」로
 * 끝났다 — 알림톡 채널을 켜 두어도 한 통도 나가지 않았다는 뜻이다. 승인받은 템플릿이
 * 사는 곳도 팝빌이니 보내는 곳도 팝빌이어야 맞다. 결과 모양은 NHIS 일괄 청구(bulkSendFax)와 같게 둔다 —
 * 화면이 이미 그 모양을 읽을 줄 안다.
 *
 * 한 번에 보내는 수신자 수는 묶어서 나눈다. 업체 한 번 호출에 다 실으면 하나가 잘못됐을 때
 * 전부 되돌아온다.
 */
class MessageSender
{
    /** 업체 한 번 호출에 실을 수신자 수 */
    private const CHUNK = 100;

    public function __construct(
        private readonly PopbillMessageService $sms,
        private readonly PopbillKakaoService $kakao,
    ) {}

    /**
     * @param  array $receivers [['rcv'=>'01012345678','rcvnm'=>'홍길동','patient_id'=>1], ...]
     * @return array ['success'=>bool,'total','success_count','fail_count','failed'=>[],'history_id']
     */
    /**
     * @param array $값들 알림톡 본문의 변수를 채울 값 — ['#{이름}' => '홍길동', …].
     *                    알림톡은 **승인된 본문**으로 나가야 해서 $content 를 쓸 수 없다
     *                    (그 글은 문자용이다). 문자에는 쓰이지 않는다.
     */
    public function sendBulk(string $channel, array $receivers, string $content, ?string $templateCode = null,
                             array $meta = [], array $값들 = []): array
    {
        $receivers = $this->clean($receivers);
        if (!$receivers) {
            return ['success' => false, 'message' => '보낼 수 있는 번호가 없습니다.',
                    'total' => 0, 'success_count' => 0, 'fail_count' => 0, 'failed' => []];
        }

        $label = $templateCode
            ? (MessageTemplate::channel($channel)->where('code', $templateCode)->value('label') ?? $templateCode)
            : null;

        $ok = 0; $failed = []; $receipts = []; $err = null;

        foreach (array_chunk($receivers, self::CHUNK) as $chunk) {
            try {
                $receipts[] = $channel === 'alimtalk'
                    ? $this->sendAlimtalkChunk($chunk, $templateCode, $label ?? '', $값들)
                    : $this->sendSmsChunk($chunk, $content, (bool) ($meta['업무발송'] ?? false));
                $ok += count($chunk);
            } catch (\Throwable $e) {
                $err = $e->getMessage();
                Log::error('[메시지] 묶음 발송 실패', ['channel' => $channel, 'count' => count($chunk), 'error' => $err]);
                foreach ($chunk as $r) {
                    $failed[] = ['rcv' => $r['rcv'], 'rcvnm' => $r['rcvnm'] ?? '', 'error' => $err];
                }
            }
        }

        $history = MessageHistory::create([
            'channel'         => $channel,
            'template_code'   => $templateCode,
            'template_label'  => $label,
            'content'         => $content,
            'total'           => count($receivers),
            'success_count'   => $ok,
            'fail_count'      => count($failed),
            'receivers'       => $receivers,
            'receipt_nums'    => array_values(array_filter($receipts)),
            'error'           => $err,
            'source'          => $meta['source'] ?? 'messages',
            'prescription_id' => $meta['prescription_id'] ?? null,
            'sent_by'         => auth()->id(),
        ]);

        return [
            'success'       => $ok > 0,
            /* 못 간 까닭을 함께 적는다 (2026-09-07).
               「0건 발송, 1건 실패」만 돌려주던 것을 고친다 — 담당자는 그 말을 보고
               번호가 틀렸나 하고 다시 눌렀고, 정작 팝빌이 돌려준 말(「파트너 잔여포인트가
               부족합니다」)은 서버 로그에만 남아 있었다. 서버에 들어가야 읽는 말은
               그 자리에서는 없는 말이다. */
            'message'       => "{$ok}건 발송"
                               . ($failed ? ', ' . count($failed) . '건 실패' : '')
                               . ($err ? ' — ' . $err : ''),
            'total'         => count($receivers),
            'success_count' => $ok,
            'fail_count'    => count($failed),
            'failed'        => $failed,
            'history_id'    => $history->id,
        ];
    }

    /** 번호를 숫자만 남기고, 번호 없는 사람과 같은 번호를 걸러낸다 */
    private function clean(array $receivers): array
    {
        $seen = [];
        $out  = [];
        foreach ($receivers as $r) {
            $num = preg_replace('/\D/', '', (string) ($r['rcv'] ?? ''));
            if (strlen($num) < 9 || strlen($num) > 11) continue;
            if (isset($seen[$num])) continue;          // 같은 번호로 두 번 보내지 않는다
            $seen[$num] = true;
            $out[] = ['rcv' => $num, 'rcvnm' => $r['rcvnm'] ?? '', 'patient_id' => $r['patient_id'] ?? null];
        }
        return $out;
    }

    /**
     * 팝빌은 수신자 배열을 그대로 받는다 — 한 번 호출로 묶음 전체가 나간다
     *
     * $업무발송 은 「이것은 시험이 아니다」라는 뜻이다 (2026-09-16 지시).
     * 서면 「우리에게만」도 「시뮬레이션」도 따르지 않고 적힌 번호로 나간다.
     * 한 사람에게 보내는 자리에서만 선다 — 묶음 경로는 아직 이 깃발을 보지 않는다.
     */
    private function sendSmsChunk(array $chunk, string $content, bool $업무발송 = false): string
    {
        return count($chunk) === 1
            // 단건은 기존 편의 메서드를 그대로 쓴다
            ? $this->sms->send($chunk[0]['rcv'], $content, $chunk[0]['rcvnm'] ?? null, $업무발송)
            : $this->sms->sendManyXms($chunk, $content);
    }

    /**
     * 알림톡 한 묶음 — 팝빌 `SendATS`.
     *
     * ## 문자 글을 쓰지 않는다
     *
     * 알림톡은 **승인받은 본문 그대로**만 나간다. 부르는 쪽이 건네는 $content 는
     * 문자용으로 지은 글이라 승인 본문과 다르다 — 그대로 보내면 팝빌이
     * 「내용과 템플릿이 일치하지 않음」(630)으로 되돌린다. 그래서 여기서 알림톡
     * 유형의 본문을 읽어 변수만 채운다.
     *
     * ## 사람마다 따로 채운다
     *
     * `#{이름}` 은 받는 사람마다 다르다. 팝빌은 수신자마다 `msg` 를 따로 받으므로
     * 한 번 호출에 묶음 전체를 싣되 글은 사람마다 짓는다.
     *
     * ## 못 채운 변수가 있으면 보내지 않는다
     *
     * 팝빌이 거절할 뿐 아니라, 새어 나가면 환자가 「#{이름}님」을 읽는다.
     */
    private function sendAlimtalkChunk(array $chunk, ?string $templateCode, string $label, array $값들): string
    {
        $틀 = MessageTemplate::channel('alimtalk')->active()
            ->where('code', $templateCode)->first();

        if (! $틀 || trim((string) $틀->ats_template_code) === '') {
            throw new \RuntimeException("「{$label}」에 승인된 알림톡 템플릿 코드가 없습니다.");
        }

        if (trim((string) $틀->body) === '') {
            throw new \RuntimeException("「{$label}」의 알림톡 본문이 비어 있습니다.");
        }

        $받을이들 = [];
        $첫글     = '';

        foreach ($chunk as $r) {
            $이름 = trim((string) ($r['rcvnm'] ?? ''));

            /* 사람마다 다른 값이 먼저다 — 부르는 쪽이 준 것은 그대로 둔다 */
            $이사람 = $값들 + array_filter([
                '#{이름}'   => $이름,
                '#{고객명}' => $이름,
            ], fn ($v) => $v !== '');

            $글 = trim(strtr((string) $틀->body, $이사람));

            if (preg_match_all('/#\{[^}]*\}/u', $글, $남은것)) {
                throw new \RuntimeException(
                    "「{$label}」에 채우지 못한 값이 있습니다 — "
                    . implode(', ', array_unique($남은것[0])));
            }

            $받을이 = $this->kakao->newReceiver();
            $받을이->rcv   = $r['rcv'];
            $받을이->rcvnm = $이름;
            $받을이->msg   = $글;

            $받을이들[] = $받을이;
            $첫글 = $첫글 ?: $글;
        }

        return $this->kakao->sendAts(
            corpNum:      (string) config('popbill.test.corp_num'),
            templateCode: (string) $틀->ats_template_code,
            /* 발신번호는 대체문자용이다 — 팝빌에 승인된 번호여야 한다 */
            sender:       (string) (config('popbill.test.sms_sender') ?: config('popbill.test.sender_num')),
            content:      $첫글,
            messages:     $받을이들,
            userId:       (string) config('popbill.test.user_id'),
        );
    }
}
