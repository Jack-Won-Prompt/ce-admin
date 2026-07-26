<?php

namespace Tests\Feature\Grid;

use App\Models\Inquiry;
use App\Models\User;

class InquiryCrudTest extends CrudTestCase
{
    private function makeInquiry(User $user): Inquiry
    {
        return Inquiry::create([
            'user_id'  => $user->id,
            'title'    => '문의 제목',
            'content'  => '문의 내용입니다.',
            'category' => 'general',
            'status'   => 'pending',
        ]);
    }

    public function test_create(): void
    {
        $this->actingAsAdmin();

        $this->post('/inquiries', [
            'title'    => '새 문의',
            'content'  => '문의 본문',
            'category' => 'technical',
        ])->assertRedirect();

        $this->assertDatabaseHas('inquiries', ['title' => '새 문의', 'category' => 'technical']);
    }

    public function test_reply(): void
    {
        $admin = $this->actingAsAdmin();
        $inq = $this->makeInquiry($admin);

        $this->post("/inquiries/{$inq->id}/reply", [
            'answer' => '답변 내용입니다.',
        ])->assertRedirect();

        $this->assertDatabaseHas('inquiries', [
            'id'     => $inq->id,
            'answer' => '답변 내용입니다.',
            'status' => 'answered',
        ]);
    }

    public function test_delete(): void
    {
        $admin = $this->actingAsAdmin();
        $inq = $this->makeInquiry($admin);

        $this->delete("/inquiries/{$inq->id}")->assertRedirect();
        $this->assertDatabaseMissing('inquiries', ['id' => $inq->id]);
    }
}
