<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 메시지 유형.
 *
 * 예전에는 PrescriptionController::smsTemplates() 와 KakaoService::templates() 안에
 * 배열로 박혀 있었다. 그 값들이 이 표의 처음 내용이 된다(마이그레이션이 아니라
 * seedDefaults() 가 채운다 — 표가 비어 있을 때만 넣는다).
 */
class MessageTemplate extends Model
{
    public const CHANNELS = ['sms' => '문자(SMS)', 'alimtalk' => '카카오 알림톡'];

    protected $fillable = ['channel', 'code', 'ats_template_code', 'label', 'description',
                           'body', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];

    public function scopeChannel($q, string $channel) { return $q->where('channel', $channel); }
    public function scopeActive($q)                   { return $q->where('is_active', true); }

    /**
     * 화면이 쓰던 [코드 => ['label','desc','text']] 모양을 준다.
     *
     * 표가 아직 없으면(마이그레이션 전 배포) 예전 값을 그대로 돌려준다 —
     * 검수 화면이 500 으로 죽지 않아야 한다. 표가 있고 비어 있으면 한 번 채운다.
     */
    public static function resolve(string $channel): array
    {
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('message_templates')) {
                return static::hardcoded($channel);
            }
            static::seedDefaults();
            $map = static::asLegacyMap($channel);
            return $map ?: static::hardcoded($channel);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[메시지 유형] 표를 읽지 못해 기본값을 쓴다', ['error' => $e->getMessage()]);
            return static::hardcoded($channel);
        }
    }

    private static function hardcoded(string $channel): array
    {
        $rows = $channel === 'sms' ? static::defaultSms() : static::defaultAlimtalk();
        return array_map(fn ($t) => $t + ['text' => ''], $rows);
    }

    /** 화면이 쓰던 [코드 => ['label','desc','text']] 모양 그대로 준다 */
    public static function asLegacyMap(string $channel): array
    {
        return static::channel($channel)->active()
            ->orderBy('sort_order')->orderBy('id')
            ->get()
            ->mapWithKeys(fn (self $t) => [$t->code => [
                'label' => $t->label,
                'desc'  => $t->description ?? '',
                'text'  => $t->body ?? '',
                /* 알림톡은 이 코드로만 나간다. 칸이 아직 없는 서버에서도 화면이 서야
                   하므로 없으면 빈 값이다. */
                'ats'   => static::hasAtsColumn() ? ($t->ats_template_code ?? '') : '',
            ]])
            ->all();
    }

    /** 팝빌 템플릿 코드 칸이 있는가 — 마이그레이션 전 서버에서도 화면이 서야 한다 */
    public static function hasAtsColumn(): bool
    {
        static $has = null;

        if ($has === null) {
            try {
                $has = \Illuminate\Support\Facades\Schema::hasColumn('message_templates', 'ats_template_code');
            } catch (\Throwable) {
                $has = false;
            }
        }

        return $has;
    }

    /**
     * 표가 비어 있으면 예전에 코드에 박혀 있던 값으로 채운다.
     * 화면을 열 때 부르므로, 배포 직후 관리자가 아무것도 하지 않아도 예전과 같이 동작한다.
     */
    public static function seedDefaults(): void
    {
        /* 표가 이미 차 있어도 **새로 생긴 유형**은 넣는다 (2026-09-18 지시).

           예전에는 비어 있을 때만 채워, 뒤에 추가한 유형은 이미 쓰고 있는 서버에
           영영 서지 않았다. 담당자는 화면에서 문구를 고칠 자리조차 없었다.
           이미 있는 코드는 건드리지 않는다 — 고쳐 둔 문구를 되돌리면 안 된다. */
        if (static::exists()) {
            /* 한 번 훑으면 그만이다 — resolve() 가 화면마다 부르므로 요청마다 한 번으로 둔다 */
            static $훑었나 = false;

            if (! $훑었나) {
                $훑었나 = true;
                static::없는것채우기();
            }

            return;
        }

        $rows = [];
        $i = 0;
        foreach (static::defaultSms() as $code => $t) {
            $rows[] = ['channel' => 'sms', 'code' => $code, 'label' => $t['label'],
                       'description' => $t['desc'], 'body' => $t['text'],
                       'sort_order' => $i++, 'is_active' => true,
                       'created_at' => now(), 'updated_at' => now()];
        }
        $i = 0;
        foreach (static::defaultAlimtalk() as $code => $t) {
            $rows[] = ['channel' => 'alimtalk', 'code' => $code, 'label' => $t['label'],
                       'description' => $t['desc'], 'body' => $t['text'] ?? null,
                       'sort_order' => $i++, 'is_active' => true,
                       'created_at' => now(), 'updated_at' => now()];
        }
        static::insert($rows);
    }

    /** 코드마다 견주어 없는 것만 넣는다 */
    private static function 없는것채우기(): void
    {
        $있는것 = static::pluck('code', 'code')->all();
        $rows   = [];
        $끝     = (int) static::max('sort_order');

        foreach ([['sms', static::defaultSms()], ['alimtalk', static::defaultAlimtalk()]] as [$channel, $set]) {
            foreach ($set as $code => $t) {
                if (isset($있는것[$code])) {
                    continue;
                }

                $rows[] = [
                    'channel' => $channel, 'code' => $code, 'label' => $t['label'],
                    'description' => $t['desc'], 'body' => $t['text'] ?? null,
                    'sort_order' => ++$끝, 'is_active' => true,
                    'created_at' => now(), 'updated_at' => now(),
                ];
            }
        }

        if ($rows) {
            static::insert($rows);
        }
    }

    public static function defaultSms(): array
    {
        return [
            'rx_received' => [
                'label' => '처방전 접수 완료',
                'desc'  => '처방전이 접수되었음을 안내',
                'text'  => "[콜로플라스트] #{고객명}님, 처방전이 접수되었습니다.\n처방번호: #{처방번호}\n확인 후 연락드리겠습니다.",
            ],
            'order_confirmed' => [
                'label' => '주문 접수',
                /* 결제 안내와 갈라 두 통으로 보낸다(2026-09-03). 받을 돈이 없는
                   건(차상위경감ㆍ기초)은 결제 안내가 나가지 않아, 합쳐 두면 그
                   사람들은 주문이 섰다는 것조차 듣지 못했다.
                   본인부담이 0 이면 그 줄은 빠진다. */
                'desc'  => '주문이 접수되었음을 안내 — 결제 안내는 별도 발송',
                'text'  => "[콜로플라스트] #{고객명}님, 주문이 접수되었습니다.\n주문번호: #{주문번호}\n본인 부담금: #{본인부담금}원",
            ],
            /* 「가상계좌 발급 안내」는 두지 않는다 — 발급 단추를 화면에서 걷은 뒤로
               담당자가 해 줄 수 없는 것을 환자에게 약속하는 글이 되었다. */
            'shipping_started' => [
                'label' => '배송 시작',
                'desc'  => '택배 발송 및 운송장 안내',
                'text'  => "[콜로플라스트] #{고객명}님, 제품이 발송되었습니다.\n주문번호: #{주문번호}\n운송장: #{운송장번호}",
            ],
            /* 결제가 끝나면 알린다 (2026-09-18 운영 시험에서 드러남) */
            \App\Services\PaymentDoneNotice::TEMPLATE => [
                'label' => '결제 완료',
                'desc'  => '결제가 끝났음을 안내 — 결제 직후 자동 발송',
                'text'  => \App\Services\PaymentDoneNotice::기본문구(),
            ],
            /* 교환ㆍ반품ㆍ취소가 저절로 보내는 세 자리 (2026-09-18 지시).
               문구는 ReturnPatientNotice::기본문구() 가 정본이다 — 두 벌로 적으면
               한쪽만 고쳐진다. */
            \App\Services\ReturnPatientNotice::접수 => [
                'label' => '교환·반품·취소 접수',
                'desc'  => '신청을 접수했음을 환자에게 안내 — 접수할 때 자동 발송',
                'text'  => \App\Services\ReturnPatientNotice::기본문구()[
                    \App\Services\ReturnPatientNotice::접수],
            ],
            \App\Services\ReturnPatientNotice::환불 => [
                'label' => '환불 처리 완료',
                'desc'  => '환불을 처리했음을 안내 — 환불완료 단계에서 자동 발송',
                'text'  => \App\Services\ReturnPatientNotice::기본문구()[
                    \App\Services\ReturnPatientNotice::환불],
            ],
            \App\Services\ReturnPatientNotice::환불없음 => [
                'label' => '환불 처리 완료 (환불 금액 없음)',
                'desc'  => '본인부담이 0원이라 돌려드릴 금액이 없는 건 — 환불완료 단계에서 자동 발송',
                'text'  => \App\Services\ReturnPatientNotice::기본문구()[
                    \App\Services\ReturnPatientNotice::환불없음],
            ],
            \App\Services\ReturnPatientNotice::추가입금 => [
                'label' => '추가 입금 안내',
                'desc'  => '더 내실 금액이 있음을 안내 — 금액조정 단계에서 자동 발송',
                'text'  => \App\Services\ReturnPatientNotice::기본문구()[
                    \App\Services\ReturnPatientNotice::추가입금],
            ],
            'custom' => [
                'label' => '직접 입력',
                'desc'  => '메시지를 직접 작성',
                'text'  => '',
            ],
        ];
    }

    public static function defaultAlimtalk(): array
    {
        return [
            'order_confirm'  => ['label' => '주문 접수 안내',    'desc' => '주문이 접수되었음을 환자에게 안내'],
            /* 「가상계좌 발급 안내」는 두지 않는다 — SMS 쪽과 같은 까닭이다 */
            'shipping_start' => ['label' => '배송 시작 안내',     'desc' => '운송장 번호 포함 배송 출발 안내'],
            'delivery_done'  => ['label' => '배송 완료 안내',     'desc' => '배송 완료 및 복약 안내'],
        ];
    }
}
