<?php

namespace Tests\Feature\Popbill;

use Tests\TestCase;

class MessageTest extends TestCase
{
    private string $corpNum;
    private string $userId;
    private string $senderNum;

    protected function setUp(): void
    {
        parent::setUp();
        $this->corpNum   = config('popbill.test.corp_num');
        $this->userId    = config('popbill.test.user_id');
        $this->senderNum = config('popbill.test.sender_num');
    }

    public function test_get_balance(): void
    {
        $response = $this->getJson("/api/popbill/message/balance?corp_num={$this->corpNum}");
        $response->assertOk()->assertJsonStructure(['corp_num', 'balance']);
    }

    public function test_get_sender_numbers(): void
    {
        $response = $this->getJson("/api/popbill/message/sender-numbers?corp_num={$this->corpNum}");
        $response->assertOk();
    }

    public function test_send_sms_requires_fields(): void
    {
        $response = $this->postJson('/api/popbill/message/send-sms', []);
        $response->assertUnprocessable();
    }

    public function test_send_sms(): void
    {
        $response = $this->postJson('/api/popbill/message/send-sms', [
            'corp_num' => $this->corpNum,
            'sender'   => $this->senderNum,
            'content'  => '[테스트] 팝빌 SMS 전송 테스트',
            'messages' => [
                ['rcv' => config('popbill.test.receiver_hp'), 'rcvnm' => '테스트'],
            ],
        ]);
        $response->assertOk()->assertJsonStructure(['receipt_num']);
    }

    public function test_search_requires_message_type(): void
    {
        $response = $this->getJson(
            "/api/popbill/message/search?corp_num={$this->corpNum}&start_date=20250101&end_date=20250131"
        );
        $response->assertUnprocessable();
    }

    public function test_search(): void
    {
        $response = $this->getJson(
            "/api/popbill/message/search?corp_num={$this->corpNum}&message_type=SMS&start_date=20250101&end_date=20250131"
        );
        $response->assertOk();
    }

    public function test_get_sent_list_url(): void
    {
        $response = $this->getJson(
            "/api/popbill/message/sent-list-url?corp_num={$this->corpNum}&user_id={$this->userId}"
        );
        $response->assertOk()->assertJsonStructure(['url']);
    }
}
