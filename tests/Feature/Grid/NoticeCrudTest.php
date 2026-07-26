<?php

namespace Tests\Feature\Grid;

use App\Models\Notice;

class NoticeCrudTest extends CrudTestCase
{
    public function test_notice_full_crud_and_grid_list(): void
    {
        $this->actingAsAdmin();

        // 목록(그리드) 화면 렌더 200
        $this->get('/notices')->assertOk()->assertSee('id="noticeGrid"', false);

        // CREATE
        $res = $this->post('/notices', [
            'title'     => '테스트 공지 제목',
            'content'   => '테스트 공지 본문입니다.',
            'is_pinned' => 1,
            'is_active' => 1,
        ]);
        $res->assertRedirect();
        $this->assertDatabaseHas('notices', ['title' => '테스트 공지 제목', 'is_pinned' => 1]);
        $notice = Notice::where('title', '테스트 공지 제목')->firstOrFail();

        // READ(상세)
        $this->get("/notices/{$notice->id}")->assertOk()->assertSee('테스트 공지 제목');

        // UPDATE
        $this->put("/notices/{$notice->id}", [
            'title'     => '수정된 공지 제목',
            'content'   => '수정된 본문',
            'is_pinned' => 0,
            'is_active' => 1,
        ])->assertRedirect();
        $this->assertDatabaseHas('notices', ['id' => $notice->id, 'title' => '수정된 공지 제목', 'is_pinned' => 0]);

        // DELETE
        $this->delete("/notices/{$notice->id}")->assertRedirect();
        $this->assertDatabaseMissing('notices', ['id' => $notice->id]);
    }
}
