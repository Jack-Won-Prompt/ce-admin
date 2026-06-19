<?php

namespace Tests\Feature\Popbill;

use Tests\TestCase;

class CashbillTest extends TestCase
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
        $response = $this->getJson("/api/popbill/cashbill/balance?corp_num={$this->corpNum}");
        $response->assertOk()->assertJsonStructure(['corp_num', 'balance']);
    }

    public function test_get_url(): void
    {
        $response = $this->getJson(
            "/api/popbill/cashbill/url?corp_num={$this->corpNum}&user_id={$this->userId}&togo=HOME"
        );
        $response->assertOk()->assertJsonStructure(['url']);
    }

    public function test_search_requires_dates(): void
    {
        $response = $this->getJson("/api/popbill/cashbill/search?corp_num={$this->corpNum}");
        $response->assertUnprocessable();
    }

    public function test_search_with_valid_dates(): void
    {
        $response = $this->getJson(
            "/api/popbill/cashbill/search?corp_num={$this->corpNum}&start_date=20250101&end_date=20250131"
        );
        $response->assertOk();
    }
}
