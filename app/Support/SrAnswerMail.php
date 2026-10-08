<?php

namespace App\Support;

use App\Models\MessageTemplate;
use App\Models\ServiceRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * SR 답변을 올린 담당자에게 메일로 알린다 (2026-10-08 지시).
 *
 * ## 보내는 잣대
 *
 *   · 담당자가 직접 쓴 답변 → **전문**을 싣는다
 *   · 자동으로 등록된 답변   → **등록 안내만** 보낸다 (내용은 화면에서 본다)
 *   · 처리 완료로 옮긴 답변 → **보내지 않는다**
 *   · 답변을 고친 것        → **보내지 않는다** (처음 등록될 때 한 번만)
 *   · 받는 주소가 없거나 사용하지 않는 계정 → 보내지 않는다
 *
 * 자동 답변에 전문을 싣지 않는 까닭은 되돌릴 수 없기 때문이다. 화면의 답변은 고칠
 * 수 있지만 보낸 메일은 고칠 수 없다 — 담당자가 한 번 보고 손볼 여지를 남긴다.
 *
 * ## 보내는 때
 *
 * 응답을 돌려준 **뒤에** 보낸다(`defer`). 답변을 적는 자리에서 메일을 기다리면
 * SMTP 가 느릴 때 그 요청이 끌리고, 답변은 적혔는데 「보내지 못했습니다」로 기록되는
 * 어긋남이 생긴다. 메일을 못 보내는 것이 답변을 적는 일을 깨뜨려서는 안 된다.
 *
 * 글은 메시지 관리(email 채널)에서 고친다 — 코드의 글월은 표가 비었을 때의 대비다.
 */
class SrAnswerMail
{
    /** 보내기 — 어디서 터져도 부른 쪽을 깨뜨리지 않는다 */
    public static function 보낸다(ServiceRequest $sr, bool $전문): void
    {
        try {
            if (! config('mail.sr_answer_mail', false)) {
                return;
            }

            if ((string) $sr->status === 'done') {
                return;   // 처리 완료 답변은 알리지 않는다
            }

            $받는이 = $sr->user;
            $주소   = trim((string) ($받는이->email ?? ''));

            if ($주소 === '' || ! ($받는이->is_active ?? false)) {
                Log::info('[SR 메일] 받는 주소가 없어 보내지 않았습니다', ['sr' => $sr->id]);

                return;
            }

            $값 = [
                '#{이름}'   => (string) ($받는이->name ?? ''),
                '#{제목}'   => (string) $sr->title,
                '#{등록일}' => $sr->created_at?->format('n월 j일') ?? '',
                '#{답변}'   => self::글로($sr->answer),
                '#{주소}'   => rtrim((string) config('app.url'), '/') . '/sr',
            ];

            $제목 = MessageTemplate::문구('sr_answer_email_subject', $값,
                '[CE Admin] 요청하신 건에 답변이 등록되었습니다 — ' . $sr->title, 'email');

            $본문 = $전문
                ? MessageTemplate::문구('sr_answer_email', $값, self::기본전문($값), 'email')
                : MessageTemplate::문구('sr_answer_notice_email', $값, self::기본안내($값), 'email');

            /* 응답을 돌려준 뒤에 보낸다 */
            defer(function () use ($sr, $주소, $제목, $본문) {
                try {
                    Mail::raw($본문, fn ($m) => $m->to($주소)->subject($제목));

                    activity()->performedOn($sr)->log("답변 메일을 발송했습니다 → {$주소}");
                } catch (\Throwable $e) {
                    Log::warning('[SR 메일] 보내지 못했습니다', [
                        'sr' => $sr->id, 'to' => $주소, 'error' => $e->getMessage(),
                    ]);

                    activity()->performedOn($sr)
                        ->log('답변 메일을 발송하지 못했습니다 — ' . mb_substr($e->getMessage(), 0, 150));
                }
            });
        } catch (\Throwable $e) {
            Log::warning('[SR 메일] 준비하지 못했습니다', ['sr' => $sr->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * 답변은 편집기(Quill)가 만든 꾸밈글이다 — 메일은 글자로 보내므로 줄바꿈을
     * 살리면서 이름표를 걷어낸다.
     */
    private static function 글로(?string $꾸밈글): string
    {
        $글 = (string) $꾸밈글;
        $글 = preg_replace('~<(br|/p|/div|/li)[^>]*>~i', "\n", $글) ?? $글;
        $글 = preg_replace('~<li[^>]*>~i', '· ', $글) ?? $글;
        $글 = strip_tags($글);
        $글 = html_entity_decode($글, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $글 = preg_replace("~\n{3,}~", "\n\n", $글) ?? $글;

        return trim($글);
    }

    private static function 기본전문(array $값): string
    {
        return "안녕하십니까, {$값['#{이름}']} 님.\n\n"
             . "{$값['#{등록일}']}에 등록해 주신 요청에 답변을 등록하였습니다.\n\n"
             . "［{$값['#{제목}']}］\n\n"
             . "{$값['#{답변}']}\n\n"
             . "아래 화면에서도 확인하실 수 있습니다.\n{$값['#{주소}']}\n\n"
             . "추가로 문의하실 사항이 있으면 해당 건으로 요청해 주십시오.\n\n"
             . 'CE Admin 운영팀';
    }

    private static function 기본안내(array $값): string
    {
        return "안녕하십니까, {$값['#{이름}']} 님.\n\n"
             . "{$값['#{등록일}']}에 등록해 주신 요청에 답변이 등록되었습니다. "
             . "아래 화면에서 내용을 확인해 주십시오.\n\n"
             . "［{$값['#{제목}']}］\n\n"
             . "{$값['#{주소}']}\n\n"
             . "확인 후 보완이 필요한 사항이 있으면 해당 건으로 다시 요청해 주십시오.\n\n"
             . 'CE Admin 운영팀';
    }
}
