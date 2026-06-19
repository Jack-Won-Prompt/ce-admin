<?php

namespace Tests\Feature\Popbill;

use Tests\TestCase;

class FaxTest extends TestCase
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
        $response = $this->getJson("/api/popbill/fax/balance?corp_num={$this->corpNum}");
        $response->assertOk()->assertJsonStructure(['corp_num', 'balance']);
    }

    public function test_get_sender_numbers(): void
    {
        $response = $this->getJson("/api/popbill/fax/sender-numbers?corp_num={$this->corpNum}");
        $response->assertOk();
    }

    public function test_send_requires_files(): void
    {
        $response = $this->postJson('/api/popbill/fax/send', [
            'sender'    => $this->senderNum,
            'receivers' => [['rcv' => config('popbill.test.receiver_fax'), 'rcvnm' => '테스트']],
        ]);
        $response->assertUnprocessable();
    }

    public function test_search_requires_send_type(): void
    {
        $response = $this->getJson(
            "/api/popbill/fax/search?corp_num={$this->corpNum}&start_date=20250101&end_date=20250131"
        );
        $response->assertUnprocessable();
    }

    public function test_search(): void
    {
        $response = $this->getJson(
            "/api/popbill/fax/search?corp_num={$this->corpNum}&send_type=S&start_date=20250101&end_date=20250131"
        );
        $response->assertOk();
    }

    public function test_get_sent_list_url(): void
    {
        $response = $this->getJson(
            "/api/popbill/fax/sent-list-url?corp_num={$this->corpNum}&user_id={$this->userId}"
        );
        $response->assertOk()->assertJsonStructure(['url']);
    }
}
