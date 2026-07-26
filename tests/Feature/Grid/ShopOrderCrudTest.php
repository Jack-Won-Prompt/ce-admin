<?php

namespace Tests\Feature\Grid;

use App\Models\ShopOrder;

class ShopOrderCrudTest extends CrudTestCase
{
    private function makeShopOrder(): ShopOrder
    {
        return ShopOrder::create([
            'shop_order_id' => 'SO-TEST-1',
            'order_number'  => 'SHOP-0001',
            'customer_name' => '쇼핑고객',
            'items'         => [['name' => '제품A', 'qty' => 2]],
            'total_amount'  => 30000,
            'status'        => 'confirmed',
        ]);
    }

    public function test_update_memo(): void
    {
        $this->actingAsAdmin();
        $o = $this->makeShopOrder();

        $this->postJson("/shop-orders/{$o->id}/memo", [
            'admin_memo' => '관리자 메모 테스트',
        ])->assertSuccessful();

        // ShopOrder는 'lcpoint' 연결(테스트 중 로컬로 오버라이드됨)을 쓰므로 해당 연결로 검증
        $this->assertDatabaseHas('shop_orders', ['id' => $o->id, 'admin_memo' => '관리자 메모 테스트'], 'lcpoint');
    }

    public function test_update_status(): void
    {
        $this->actingAsAdmin();
        $o = $this->makeShopOrder();

        $this->postJson("/shop-orders/{$o->id}/status", [
            'status' => 'shipped',
        ])->assertSuccessful();

        $this->assertDatabaseHas('shop_orders', ['id' => $o->id, 'status' => 'shipped'], 'lcpoint');
    }
}
