<?php

namespace Tests\Feature\Popbill;

use Tests\TestCase;

class TaxinvoiceTest extends TestCase
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
        $response = $this->getJson("/api/popbill/taxinvoice/balance?corp_num={$this->corpNum}");
        $response->assertOk()->assertJsonStructure(['corp_num', 'balance']);
    }

    public function test_get_url(): void
    {
        $response = $this->getJson(
            "/api/popbill/taxinvoice/url?corp_num={$this->corpNum}&user_id={$this->userId}&togo=WRITE"
        );
        $response->assertOk()->assertJsonStructure(['url']);
        $this->assertStringStartsWith('http', $response->json('url'));
    }

    public function test_search_requires_dates(): void
    {
        $response = $this->getJson("/api/popbill/taxinvoice/search?corp_num={$this->corpNum}");
        $response->assertUnprocessable();
    }

    public function test_search_with_valid_dates(): void
    {
        $response = $this->getJson(
            "/api/popbill/taxinvoice/search?corp_num={$this->corpNum}&start_date=20250101&end_date=20250131"
        );
        $response->assertOk();
    }

    public function test_get_popup_url_requires_params(): void
    {
        $response = $this->getJson("/api/popbill/taxinvoice/popup-url?corp_num={$this->corpNum}");
        $response->assertUnprocessable();
    }
}
