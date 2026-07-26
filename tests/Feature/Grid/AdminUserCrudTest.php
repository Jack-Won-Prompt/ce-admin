<?php

namespace Tests\Feature\Grid;

use App\Models\User;

class AdminUserCrudTest extends CrudTestCase
{
    public function test_create(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/admin/users', [
            'name'      => '신규매니저',
            'email'     => 'manager_new@ce-admin.co.kr',
            'phone'     => '01011112222',
            'role'      => 'manager',
            'is_active' => 1,
            'password'  => 'password123',
        ])->assertSuccessful()->assertJson(['success' => true]);

        $this->assertDatabaseHas('users', ['email' => 'manager_new@ce-admin.co.kr', 'role' => 'manager']);
    }

    public function test_update(): void
    {
        $this->actingAsAdmin();
        $u = User::create([
            'name' => '대상자', 'email' => 'target@ce-admin.co.kr', 'phone' => '01033334444',
            'password' => bcrypt('password123'), 'role' => 'manager', 'is_active' => 1,
        ]);

        $this->putJson("/admin/users/{$u->id}", [
            'name'      => '대상자수정',
            'email'     => 'target@ce-admin.co.kr',
            'role'      => 'admin',
            'is_active' => 0,
        ])->assertSuccessful();

        $this->assertDatabaseHas('users', ['id' => $u->id, 'name' => '대상자수정', 'role' => 'admin', 'is_active' => 0]);
    }

    public function test_delete(): void
    {
        $this->actingAsAdmin();
        $u = User::create([
            'name' => '삭제대상', 'email' => 'del@ce-admin.co.kr', 'phone' => '01044445555',
            'password' => bcrypt('password123'), 'role' => 'manager', 'is_active' => 1,
        ]);

        $this->deleteJson("/admin/users/{$u->id}")->assertSuccessful();
        $this->assertDatabaseMissing('users', ['id' => $u->id]);
    }
}
