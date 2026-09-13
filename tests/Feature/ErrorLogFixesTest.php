<?php

namespace Tests\Feature;

use App\Models\AdminInvitation;
use App\Models\ErrorLog;
use App\Models\User;
use App\Support\ErrorRecorder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Tests\Feature\Grid\CrudTestCase;

/**
 * 오류 기록 분석(작업지시 #55, 2026-09-13)에서 나온 결함의 되풀이 막기.
 *
 * - 초대 수락: 이메일이 이미 사용자로 있으면 500 이 아니라 로그인 안내로 보낸다.
 * - 오류 기록: QueryException 의 보낸 값(비밀번호 해시 등)을 담지 않고, 값만 다른
 *   같은 결함은 한 줄로 묶는다. 로컬에서 난 오류는 담지 않는다.
 *
 * 로컬 격리 DB(ceadmin_test)에서만 돈다 — CrudTestCase 참고.
 */
class ErrorLogFixesTest extends CrudTestCase
{
    private function invitation(string $email): AdminInvitation
    {
        /* invited_by 는 users 를 가리키는 외래키라 초대한 사람이 있어야 한다 */
        $inviter = User::firstOrCreate(['email' => 'inviter@example.com'], [
            'name' => '초대한 사람', 'password' => 'password123', 'role' => 'admin', 'is_active' => true,
        ]);

        return AdminInvitation::create([
            'email'      => $email,
            'role'       => 'manager',
            'token'      => Str::random(48),
            'invited_by' => $inviter->id,
            'expires_at' => now()->addHours(72),
        ]);
    }

    public function test_confirm_creates_user_and_marks_invitation_accepted(): void
    {
        $inv = $this->invitation('invite-new@example.com');

        $this->post(route('admin.invite.confirm', $inv->token), [
            'name'                  => '새 사용자',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('dashboard'));

        $this->assertSame(1, User::where('email', 'invite-new@example.com')->count());
        $this->assertNotNull($inv->fresh()->accepted_at);
    }

    public function test_confirm_with_already_registered_email_redirects_to_login_instead_of_500(): void
    {
        User::create([
            'name' => '먼저 있던 사람', 'email' => 'invite-dup@example.com',
            'password' => 'password123', 'role' => 'manager', 'is_active' => true,
        ]);
        $inv = $this->invitation('invite-dup@example.com');

        $this->post(route('admin.invite.confirm', $inv->token), [
            'name'                  => '다시 누른 사람',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('login'))->assertSessionHasErrors('email');

        $this->assertSame(1, User::where('email', 'invite-dup@example.com')->count());
        $this->assertNotNull($inv->fresh()->accepted_at, '초대는 수락으로 닫혀야 한다');
    }

    public function test_accept_page_with_already_registered_email_redirects_to_login(): void
    {
        User::create([
            'name' => '먼저 있던 사람', 'email' => 'invite-dup2@example.com',
            'password' => 'password123', 'role' => 'manager', 'is_active' => true,
        ]);
        $inv = $this->invitation('invite-dup2@example.com');

        $this->get(route('admin.invite.accept', $inv->token))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
    }

    private function queryException(string $hash): QueryException
    {
        return new QueryException(
            'mysql',
            'insert into `users` (`email`, `password`) values (?, ?)',
            ['dup@example.com', $hash],
            new \PDOException("SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry for key 'users_email_unique'")
        );
    }

    public function test_query_exception_is_recorded_without_bound_values_and_merged(): void
    {
        ErrorRecorder::담기($this->queryException('$2y$12$firstSecretHashValue'));
        ErrorRecorder::담기($this->queryException('$2y$12$otherSecretHashValue'));

        $rows = ErrorLog::where('kind', 'QueryException')->get();

        $this->assertCount(1, $rows, '값만 다른 같은 결함은 한 줄로 묶여야 한다');
        $this->assertSame(2, (int) $rows[0]->hit);
        $this->assertStringNotContainsString('SecretHash', (string) $rows[0]->message);
        $this->assertStringNotContainsString('dup@example.com', (string) $rows[0]->message);
        $this->assertStringContainsString('values (?, ?)', (string) $rows[0]->message);
    }

    public function test_errors_in_local_environment_are_not_recorded(): void
    {
        $env = $this->app['env'];
        $this->app['env'] = 'local';

        try {
            config(['errors.skip_local' => true]);
            ErrorRecorder::담기(new \RuntimeException('로컬에서 난 오류'));
            $this->assertSame(0, ErrorLog::where('message', '로컬에서 난 오류')->count());

            config(['errors.skip_local' => false]);
            ErrorRecorder::담기(new \RuntimeException('로컬에서 난 오류'));
            $this->assertSame(1, ErrorLog::where('message', '로컬에서 난 오류')->count());
        } finally {
            $this->app['env'] = $env;
        }
    }
}
