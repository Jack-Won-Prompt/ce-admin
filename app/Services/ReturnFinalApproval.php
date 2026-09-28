<?php

namespace App\Services;

use App\Models\OrderReturn;
use App\Models\OrderReturnLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * 최종승인자 서명과 그 실행 (2026-09-28 지시).
 *
 * **서명이 곧 실행이다.** 서명을 받은 자리에서 곧바로 돈이 움직인다 —
 *
 *   전액 환불   토스에 받은 돈 전부를 무른다
 *   부분 환불   받은 돈에서 차감액을 뺀 만큼 무른다
 *   차액 청구   교환이다. 돈을 무르지 않고 **고객에게 더 청구**한다.
 *               다만 링크는 여기서 보내지 않는다 — 담당자가 전화로 알린 뒤 누른다.
 *
 * 서명을 받는 길이 둘이다(화면에서 바로 · SMS 링크). 실행을 두 곳에 적으면 한쪽만
 * 고치는 날이 오므로 여기 한 곳에 둔다. 공개 서명 화면도 이 자리를 부른다.
 *
 * 실행이 막혀도 **서명은 지우지 않는다.** 서명을 무르면 최종승인자에게 다시 받아야
 * 하는데, 막힌 까닭은 대개 우리 쪽이 아니다(토스가 기간을 넘겼다는 따위).
 */
class ReturnFinalApproval
{
    /** 서명 링크 문자의 틀 코드 — message_templates 의 정본 (2026-09-28 지시) */
    public const 문구코드 = 'return_final_sign';

    /**
     * 서명 요청 문자의 기본 문구.
     *
     * 담당자가 화면에서 고칠 수 있게 message_templates 에 담기지만, 표가 비었거나
     * 꺼져 있을 때 쓸 정본이 하나 있어야 한다. 두 벌로 적으면 한쪽만 고쳐진다.
     */
    public static function 기본문구(): string
    {
        return '[콜로플라스트] 교환ㆍ반품 최종 승인 요청' . chr(10)
             . '접수 #{접수번호} · #{구분}' . chr(10)
             . '#{결재경로} #{금액}원' . chr(10)
             . '아래에서 내용을 확인하고 서명해 주십시오. (24시간)' . chr(10)
             . '#{서명링크}';
    }

    public function __construct(
        private readonly WithworksReturns $withworks,
    ) {}

    /**
     * 서명을 받아 적고 곧바로 실행한다.
     *
     * 서명 저장과 단계 옮기기는 한 거래로 묶는다 — 서명만 남고 단계가 안 옮겨지면
     * 「서명은 받았는데 아무 일도 안 일어난」 건이 된다. 실행(토스)은 그 밖이다.
     * 밖으로 나가는 일을 거래 안에 두면, 토스가 늦을 때 표가 잠긴다.
     *
     * @return string 앞에 「!」가 붙으면 못 한 것이다
     */
    public function 서명하고실행(
        OrderReturn $return,
        ?User $서명자,
        string $서명그림,
        ?string $ip = null,
        ?string $agent = null,
    ): string {
        if (! $return->inspect_confirmed_at) {
            return '! 책임자 검수 승인 뒤에 서명할 수 있습니다.';
        }

        if ($return->final_signed_at) {
            return '! 이미 서명을 받은 건입니다.';
        }

        if (! $return->needsFinalSign()) {
            return '! 금액 변동이 없는 건이라 서명을 받지 않습니다.';
        }

        $경로 = $this->서명그림저장($return, $서명그림);

        DB::transaction(function () use ($return, $서명자, $서명그림, $경로, $ip, $agent) {
            $return->forceFill([
                'final_signed_by'       => $서명자?->id,
                'final_signed_at'       => now(),
                'final_sign_path'       => $경로,
                'final_sign_base64'     => $서명그림,
                'final_sign_ip'         => $ip,
                'final_sign_user_agent' => mb_substr((string) $agent, 0, 255),
                /* 서명이 곧 전자 승인이다 — 흐름의 approved 가 그 자리다 */
                'approved_by'           => $서명자?->id,
                'approved_at'           => now(),
                'status'                => 'approved',
                'refund_stage'          => 'signed',
                /* 열쇠(token)는 지우지 않는다. 지우면 그 주소가 404 가 되어, 서명을
                   마친 사람이 링크를 다시 열었을 때 「없는 쪽」으로 떨어진다 — 서명이
                   들어갔는지조차 알 수 없다. 두 번 서명하는 것은 final_signed_at 이
                   막는다(서명링크살았나). */
            ])->save();

            OrderReturnLog::create([
                'order_return_id' => $return->id,
                'from_status'     => 'inspected',
                'to_status'       => 'approved',
                'reason'          => '최종승인자 서명 — ' . ($서명자?->name ?? '')
                                     . ' · ' . $return->refundRouteLabel()
                                     . ' ' . number_format($return->움직일금액()) . '원',
                'created_by'      => $서명자?->id,
            ]);
        });

        $this->withworks->pushStatus($return->fresh());

        return $this->실행($return->fresh(['order.patient']));
    }

    /**
     * 서명 뒤의 실행 — 환불이거나 차액 청구다.
     *
     * 재시도도 이 자리를 부른다. 이미 끝난 건은 다시 하지 않는다.
     */
    public function 실행(OrderReturn $return): string
    {
        if ($return->refund_stage === 'refunded') {
            return '이미 환불이 끝난 건입니다.';
        }

        $길 = $return->refundRoute();

        /* 교환의 차액은 여기서 보내지 않는다 — 담당자가 전화로 알린 뒤 누른다.
           환불은 고객이 받는 것이라 즉시가 맞지만, 추가 결제는 고객이 내는 것이다.
           설명 없이 링크만 가면 「왜 또 돈을 내라는가」가 된다. */
        if ($길 === OrderReturn::ROUTE_TOPUP) {
            $return->forceFill(['refund_stage' => 'topup_wait'])->save();

            return sprintf(
                '서명을 받았습니다 — 고객에게 %s원을 더 받아야 합니다. 전화로 알린 뒤 ［차액 결제 링크 보내기］를 눌러 주십시오.',
                number_format($return->움직일금액()));
        }

        $몫 = $return->움직일금액();

        if ($몫 <= 0) {
            $return->forceFill(['refund_stage' => 'signed'])->save();

            return '! 돌려줄 금액이 0원입니다 — 받은 돈과 차감 금액을 확인해 주십시오.';
        }

        $결과 = app(\App\Services\TossPayments\PaymentCancelService::class)
            ->cancel($return->order, $return->receipt_no . ' ' . $return->typeLabel(), $몫, $this->돌려줄계좌($return));

        if (! ($결과['ok'] ?? false)) {
            $return->forceFill([
                'refund_stage'      => 'refund_failed',
                'refund_attempts'   => (int) $return->refund_attempts + 1,
                'refund_last_error' => mb_substr((string) ($결과['message'] ?? ''), 0, 500),
            ])->save();

            Log::warning('[교환반품] 서명 뒤 환불 실패', [
                'receipt' => $return->receipt_no, 'amount' => $몫, 'error' => $결과['message'] ?? null,
            ]);

            return '! 서명은 받았으나 환불하지 못했습니다 — ' . ($결과['message'] ?? '')
                 . ' 사유를 살펴본 뒤 ［환불 다시 시도］를 눌러 주십시오.';
        }

        DB::transaction(function () use ($return, $몫) {
            $return->forceFill([
                'refund_stage'      => 'refunded',
                'refund_attempts'   => (int) $return->refund_attempts + 1,
                'refund_last_error' => null,
                'refunded_at'       => $return->refunded_at ?? now(),
                /* 실제로 무른 돈을 적는다 (2026-09-28 시험에서 드러남).

                   접수할 때 담당자가 적어 둔 값(받은 돈 그대로)이 이미 들어 있어
                   ?: 로는 덮이지 않았다 — 19,500원을 물렀는데 표에는 22,500원이
                   남아, 목록의 「환불금액」과 실제 오간 돈이 달랐다. */
                'refund_amount'     => $몫,
                $return->refund_method === 'va' ? 'bank_cancelled_at' : 'card_cancelled_at' => now(),
                'status'            => 'refunded',
            ])->save();

            OrderReturnLog::create([
                'order_return_id' => $return->id,
                'from_status'     => 'approved',
                'to_status'       => 'refunded',
                'reason'          => '서명 즉시 환불 — ' . number_format($몫) . '원',
            ]);
        });

        activity()->performedOn($return->order)
            ->log("{$return->receipt_no} 최종 서명 후 환불 — " . number_format($몫) . '원');

        return sprintf('서명을 받고 %s원을 환불했습니다.', number_format($몫));
    }

    /**
     * 교환의 차액을 고객에게 청구한다 — 결제 링크를 보낸다.
     *
     * 주문 금액을 올려 두어야 링크가 열린다. 「다 받았는가」로 가리므로(2026-09-15),
     * 금액이 그대로면 「이미 결제가 끝난 주문」이라며 거절한다 — 그것이 옳다.
     */
    public function 차액청구(OrderReturn $return, ?string $번호 = null): string
    {
        if (! $return->final_signed_at) {
            return '! 최종승인자 서명 뒤에 청구할 수 있습니다.';
        }

        if ($return->refundRoute() !== OrderReturn::ROUTE_TOPUP) {
            return '! 차액을 청구할 건이 아닙니다.';
        }

        $몫 = $return->움직일금액();

        if ($몫 <= 0) {
            return '! 더 받을 금액이 없습니다.';
        }

        $order = $return->order;

        if (! $order) {
            return '! 주문을 찾을 수 없습니다.';
        }

        /* 주문 금액을 차액만큼 올린다 — 링크는 「아직 안 받은 몫」으로 열린다 */
        $order->forceFill(['total_amount' => (int) $order->total_amount + $몫])->save();

        $링크 = app(\App\Services\PaymentLinkService::class)
            ->issue($order->fresh(), \App\Models\PaymentLink::METHOD_CARD,
                    $번호 ?: ($order->patient?->mobile));

        if (! ($링크['sent'] ?? false)) {
            /* 못 보냈으면 올린 금액을 되돌린다 — 링크도 없는데 금액만 올라 있으면
               다음에 또 올려 두 배가 된다 */
            $order->forceFill(['total_amount' => (int) $order->total_amount - $몫])->save();

            return '! 결제 링크를 보내지 못했습니다 — ' . ($링크['message'] ?? '');
        }

        $return->forceFill([
            'refund_stage'          => 'topup_sent',
            'topup_payment_link_id' => $링크['link']?->id,
            'topup_sent_at'         => now(),
        ])->save();

        return sprintf('차액 %s원 결제 링크를 보냈습니다 — 고객이 결제하면 「차액 입금」으로 바뀝니다.', number_format($몫));
    }

    /** 최종승인자 반려 — 창고로 되돌린다 */
    public function 반려(OrderReturn $return, ?User $사람, string $사유): void
    {
        DB::transaction(function () use ($return, $사람, $사유) {
            $return->forceFill([
                'final_rejected_by'     => $사람?->id,
                'final_rejected_at'     => now(),
                'final_reject_reason'   => $사유,
                /* 열쇠는 그대로 두고 **검수 승인을 거둔다** — 서명링크살았나() 가
                   inspect_confirmed_at 을 함께 보므로 옛 링크는 그 자리에서 닫힌다.
                   다시 승인해 새로 보내면 열쇠가 새로 나 옛 주소는 영원히 닫힌다. */
                'inspect_result'        => null,
                'inspect_deduct_amount' => null,
                'inspect_confirmed_by'  => null,
                'inspect_confirmed_at'  => null,
                'refund_route'          => null,
                'refund_stage'          => null,
                'status'                => 'inspecting',
            ])->save();

            OrderReturnLog::create([
                'order_return_id' => $return->id,
                'from_status'     => 'inspected',
                'to_status'       => 'inspecting',
                'reason'          => '최종승인자 반려 — ' . $사유,
                'created_by'      => $사람?->id,
            ]);
        });

        $this->withworks->pushStatus($return->fresh());

        app(\App\Services\ReturnNotice::class)
            ->tellTaker($return->fresh(), '최종승인자가 반려했습니다 — ' . $사유, 'warning');
    }

    // ──────────────────────────────────────────────────────

    /** 가상계좌로 받은 건은 돌려줄 계좌를 함께 보내야 한다 */
    private function 돌려줄계좌(OrderReturn $return): ?array
    {
        if ($return->refund_method !== 'va') {
            return null;
        }

        if (! $return->refund_bank || ! $return->refund_account) {
            return null;
        }

        return [
            'bank'          => $return->refund_bank,
            'accountNumber' => $return->refund_account,
            'holderName'    => $return->refund_holder ?: ($return->order?->patient?->name ?? ''),
        ];
    }

    /**
     * 서명 그림을 파일로 남긴다.
     *
     * base64 도 표에 담지만 파일을 따로 두는 까닭은 하나다 — 결재 자취를 종이로
     * 뽑아야 할 때 표에서 꺼내 쓰는 것보다 파일이 다루기 쉽다.
     */
    private function 서명그림저장(OrderReturn $return, string $base64): ?string
    {
        if (! preg_match('#^data:image/(png|jpeg);base64,(.+)$#s', $base64, $m)) {
            return null;
        }

        $바이트 = base64_decode($m[2], true);

        if ($바이트 === false || strlen($바이트) < 100) {
            return null;
        }

        $길 = 'return-signs/' . $return->id . '/' . uniqid('sig_') . '.' . ($m[1] === 'jpeg' ? 'jpg' : 'png');

        /* put 은 못 써도 던지지 않고 false 를 돌려준다(throw=false). 그것을 보지
           않으면 없는 파일을 가리키는 길이 표에 남는다 — 종이로 뽑으려 할 때야
           비어 있는 것을 안다. 파일을 못 써도 서명은 잃지 않는다(표의 base64). */
        try {
            if (! Storage::disk('local')->put($길, $바이트)) {
                throw new \RuntimeException('put 이 false 를 돌려주었습니다');
            }
        } catch (\Throwable $e) {
            Log::warning('[교환반품] 최종 서명 그림을 쓰지 못했습니다', [
                'receipt' => $return->receipt_no, 'error' => $e->getMessage(),
            ]);

            return null;
        }

        return $길;
    }
}
