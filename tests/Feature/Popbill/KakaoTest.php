<?php

namespace Tests\Feature\Popbill;

use Tests\TestCase;

class KakaoTest extends TestCase
{
    private string $corpNum;
    private string $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->corpNum = config('popbill.test.corp_num');
        $this->userId  = config('popbill.test.user_id');
    }

    public function test_get_balance(): void
    {
        $response = $this->getJson("/api/popbill/kakao/balance?corp_num={$this->corpNum}");
        $response->assertOk()->assertJsonStructure(['corp_num', 'balance']);
    }

    public function test_list_templates(): void
    {
        $response = $this->getJson("/api/popbill/kakao/templates?corp_num={$this->corpNum}");
        $response->assertOk();
        $this->assertIsArray($response->json());
    }

    public function test_list_plus_friends(): void
    {
        $response = $this->getJson("/api/popbill/kakao/plus-friends?corp_num={$this->corpNum}");
        $response->assertOk();
    }

    public function test_send_ats_requires_fields(): void
    {
        $response = $this->postJson("/api/popbill/kakao/send-ats", []);
        $response->assertUnprocessable();
    }

    public function test_get_sent_list_url(): void
    {
        $response = $this->getJson(
            "/api/popbill/kakao/sent-list-url?corp_num={$this->corpNum}&user_id={$this->userId}"
        );
        $response->assertOk()->assertJsonStructure(['url']);
    }

    public function test_search_requires_state(): void
    {
        $response = $this->getJson(
            "/api/popbill/kakao/search?corp_num={$this->corpNum}&start_date=20250101&end_date=20250131"
        );
        $response->assertUnprocessable();
    }
}
