<?php
// app/Services/KakaoService.php
// 카카오 비즈니스 메시지 (알림톡) 발송 서비스

namespace App\Services;

use Illuminate\Support\Facades\Log;

class KakaoService
{
    private string $apiKey;
    private string $senderKey;
    private string $channelId;
    private bool   $testMode;
    private string $baseUrl = 'https://kakaoapi.aligo.in';   // 알리고 카카오 API (국내 대행사 예시)

    public function __construct()
    {
        $this->apiKey    = config('kakao.api_key', '');
        $this->senderKey = config('kakao.sender_key', '');
        $this->channelId = config('kakao.channel_id', '');
        $this->testMode  = config('kakao.test_mode', true);
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && !empty($this->senderKey);
    }

    /**
     * 알림톡 발송
     *
     * @param  string $mobile      수신자 휴대폰 번호 (01012345678 형식)
     * @param  string $templateCode 알림톡 템플릿 코드
     * @param  array  $params       템플릿 변수 치환 배열 ['#{변수}' => '값']
     * @param  string $subject      메시지 제목 (친구톡 fallback)
     * @return array  ['success' => bool, 'message' => string]
     */
    public function sendAlimtalk(string $mobile, string $templateCode, array $params, string $subject = ''): array
    {
        if ($this->testMode) {
            Log::info('[Kakao 알림톡 테스트] ' . $templateCode, [
                'mobile'  => $mobile,
                'params'  => $params,
                'subject' => $subject,
            ]);
            return ['success' => true, 'message' => '테스트 모드: 발송 성공 (실제 미전송)'];
        }

        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'Kakao API 키가 설정되지 않았습니다.'];
        }

        // 템플릿 변수 치환
        $message = $this->renderTemplate($templateCode, $params);

        $payload = [
            'apikey'      => $this->apiKey,
            'userid'      => config('kakao.user_id', ''),
            'senderkey'   => $this->senderKey,
            'tpl_code'    => $templateCode,
            'sender'      => config('kakao.sender_phone', ''),
            'receiver_1'  => $mobile,
            'recvname_1'  => $params['#{고객명}'] ?? '',
            'subject_1'   => $subject,
            'message_1'   => $message,
            'testmode_yn' => 'N',
        ];

        $ch = curl_init($this->baseUrl . '/akv10/alimtalk/send/');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
        ]);
        $response = curl_exec($ch);
        $errno    = curl_errno($ch);
        curl_close($ch);

        if ($errno) {
            Log::error('[Kakao] cURL 오류', ['errno' => $errno]);
            return ['success' => false, 'message' => '네트워크 오류가 발생했습니다.'];
        }

        $result = json_decode($response, true);
        $code   = $result['code'] ?? -1;

        if ($code === 0) {
            Log::info('[Kakao] 알림톡 발송 성공', ['template' => $templateCode, 'mobile' => $mobile]);
            return ['success' => true, 'message' => '알림톡이 발송되었습니다.'];
        }

        Log::warning('[Kakao] 발송 실패', ['code' => $code, 'message' => $result['message'] ?? '']);
        return ['success' => false, 'message' => $result['message'] ?? '알림톡 발송 실패'];
    }

    /**
     * 사전 정의된 템플릿 목록
     */
    public static function templates(): array
    {
        return \App\Models\MessageTemplate::resolve('alimtalk');
    }

    /**
     * 템플릿 코드로 미리보기 메시지 생성
     */
    public function buildPreview(string $templateCode, array $params): string
    {
        return match($templateCode) {
            'order_confirm' => "[콜로플라스트]\n안녕하세요, {$params['#{고객명}']}님.\n주문번호 {$params['#{주문번호}']}이(가) 접수되었습니다.\n\n■ 제품명: {$params['#{제품명}']}\n■ 금액: {$params['#{금액}']}원\n\n처방전 검토 후 조제를 진행합니다.\n문의: {$params['#{채널명}']}",

            /* 「가상계좌 발급 안내」는 두지 않는다 — 발급 단추를 화면에서 걷은 뒤로
               담당자가 해 줄 수 없는 것을 환자에게 약속하는 글이 되었다.
               예전에 그것으로 보낸 건이 남아 있어도 여기서는 미리보기만 없어진다. */

            'shipping_start'=> "[콜로플라스트]\n안녕하세요, {$params['#{고객명}']}님.\n주문하신 제품이 발송되었습니다.\n\n■ 택배사: {$params['#{택배사}']}\n■ 운송장: {$params['#{운송장번호}']}\n■ 배송지: {$params['#{배송지}']}\n\n문의: {$params['#{채널명}']}",

            'delivery_done' => "[콜로플라스트]\n안녕하세요, {$params['#{고객명}']}님.\n주문하신 제품 배송이 완료되었습니다.\n\n■ 제품명: {$params['#{제품명}']}\n처방전에 따라 복약 지도를 확인해주세요.\n\n문의: {$params['#{채널명}']}",

            default => '(미리보기 없음)',
        };
    }

    private function renderTemplate(string $templateCode, array $params): string
    {
        return $this->buildPreview($templateCode, $params);
    }
}
