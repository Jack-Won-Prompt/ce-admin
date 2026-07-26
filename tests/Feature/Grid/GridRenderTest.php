<?php

namespace Tests\Feature\Grid;

/**
 * 그리드 전면 교체된 전 화면이 정상 렌더(200)되고 wwGrid 마운트/스크립트가 포함되는지 검증.
 * (읽기 검증 — 컨트롤러 gridData 생성 + 뷰 렌더가 깨지지 않았음을 보장)
 */
class GridRenderTest extends CrudTestCase
{
    public function test_all_converted_screens_render_with_grid(): void
    {
        $this->actingAsAdmin();

        $screens = [
            ['/orders',                'orderGrid'],
            ['/user-logs',             'logGrid'],
            ['/patients',              'patientGrid'],
            ['/documents',             'documentGrid'],
            ['/nhis',                  'nhisGrid'],
            ['/notices',               'noticeGrid'],
            ['/inquiries',             'inquiryGrid'],
            ['/institutional-notices', 'noticeGrid'],
            ['/shop-orders',           'shopOrderGrid'],
            ['/prescriptions',         'rxGrid'],
            ['/invoice',               'invoiceGrid'],
            ['/dispatch',              'dispatchGrid'],
            ['/settlement',            'settlementGrid'],
            ['/admin/users',           'usersGrid'],
            ['/privacy-consents',      'pcGrid'],
        ];

        foreach ($screens as [$url, $gridId]) {
            $res = $this->get($url);
            $res->assertOk("화면 $url 이(가) 200이 아님");
            $res->assertSee('id="' . $gridId . '"', false);
            $res->assertSee('vendor/wwgrid/wwGrid.js', false);
        }
    }
}
