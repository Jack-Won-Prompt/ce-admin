<?php

namespace App\Services;

use App\Models\MessageHistory;
use App\Models\MessageTemplate;
use App\Services\Popbill\MessageService as PopbillMessageService;
use Illuminate\Support\Facades\Log;

/**
 * 여러 사람에게 한 번에 보내는 자리.
 *
 * 문자는 팝빌, 알림톡은 알리고로 서로 다른 업체를 탄다. 부르는 쪽이 그 사정을 알 필요는
 * 없으므로 여기서 갈라 준다. 결과 모양은 NHIS 일괄 청구(bulkSendFax)와 같게 둔다 —
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
        private readonly KakaoService $kakao,
    ) {}

    /**
     * @param  array $receivers [['rcv'=>'01012345678','rcvnm'=>'홍길동','patient_id'=>1], ...]
     * @return array ['success'=>bool,'total','success_count','fail_count','failed'=>[],'history_id']
     */
    public function sendBulk(string $channel, array $receivers, string $content, ?string $templateCode = null,
                             array $meta = []): array
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
                    ? $this->sendAlimtalkChunk($chunk, $content, $templateCode, $label ?? '')
                    : $this->sendSmsChunk($chunk, $content);
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
            'message'       => "{$ok}건 발송" . ($failed ? ', ' . count($failed) . '건 실패' : ''),
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

    /** 팝빌은 수신자 배열을 그대로 받는다 — 한 번 호출로 묶음 전체가 나간다 */
    private function sendSmsChunk(array $chunk, string $content): string
    {
        return count($chunk) === 1
            // 단건은 기존 편의 메서드를 그대로 쓴다
            ? $this->sms->send($chunk[0]['rcv'], $content, $chunk[0]['rcvnm'] ?? null)
            : $this->sms->sendManyXms($chunk, $content);
    }

    /**
     * 알리고는 한 번에 한 사람씩만 실어 보내도록 지금 코드가 짜여 있다.
     * 묶음을 돌며 보내고, 하나라도 실패하면 그 묶음을 실패로 본다.
     */
    private function sendAlimtalkChunk(array $chunk, string $content, ?string $templateCode, string $label): string
    {
        $fails = [];
        foreach ($chunk as $r) {
            $res = $this->kakao->sendAlimtalk($r['rcv'], (string) $templateCode,
                ['#{고객명}' => $r['rcvnm'] ?? '', '#{내용}' => $content], $label);
            if (empty($res['success'])) $fails[] = $r['rcv'] . ': ' . ($res['message'] ?? '실패');
        }
        if ($fails) throw new \RuntimeException(implode(' / ', array_slice($fails, 0, 3)));
        return 'ALIGO-' . now()->format('YmdHis');
    }
}
