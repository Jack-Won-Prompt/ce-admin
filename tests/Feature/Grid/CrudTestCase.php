<?php

namespace Tests\Feature\Grid;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * 그리드 전면 교체 후 화면별 CRUD 검증용 베이스.
 *
 * - 운영 DB(3.34.53.36/lcpoint)는 절대 건드리지 않는다.
 * - 로컬 XAMPP MySQL의 격리 테스트 DB(ceadmin_test)에서만 동작.
 * - 각 테스트는 트랜잭션으로 감싸 tearDown에서 롤백 → DB 무오염.
 * - SMS/이메일/카카오/외부HTTP는 fake 처리(실제 발송 안 함).
 */
abstract class CrudTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // ★ 운영 DB(3.34.53.36/lcpoint) 무접촉 보장:
        //   기본 mysql 뿐 아니라 ShopOrder 등이 쓰는 'lcpoint' 연결까지
        //   전부 로컬 격리 테스트 DB(ceadmin_test)로 강제 오버라이드.
        $local = [
            'driver'    => 'mysql',
            'host'      => '127.0.0.1',
            'port'      => 3306,
            'database'  => 'ceadmin_test',
            'username'  => 'root',
            'password'  => '',
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix'    => '',
            'strict'    => false,
        ];
        config([
            'database.connections.ceadmin_test' => $local,
            'database.connections.mysql'        => $local,
            'database.connections.lcpoint'      => $local,
            'database.default'                  => 'ceadmin_test',
        ]);
        foreach (self::TX_CONNECTIONS as $c) {
            DB::purge($c);
        }
        DB::setDefaultConnection('ceadmin_test');

        // 안전장치: 실제 외부 발송/호출 차단(SMS·이메일·카카오·Withworks 등)
        Http::preventStrayRequests();
        Http::fake();
        Mail::fake();
        Notification::fake();

        // 각 연결을 트랜잭션으로 감싸 tearDown에서 롤백(DB 무오염)
        foreach (self::TX_CONNECTIONS as $c) {
            DB::connection($c)->beginTransaction();
        }
    }

    /** 롤백 대상 연결(모든 모델이 이 중 하나를 쓰며, 전부 로컬 테스트DB를 가리킴) */
    private const TX_CONNECTIONS = ['ceadmin_test', 'mysql', 'lcpoint'];

    protected function tearDown(): void
    {
        foreach (self::TX_CONNECTIONS as $c) {
            try {
                DB::connection($c)->rollBack();
            } catch (\Throwable $e) {
                // 이미 롤백/커밋된 경우 무시
            }
        }
        parent::tearDown();
    }

    /** 관리자 사용자 생성 + 로그인 */
    protected function actingAsAdmin(): User
    {
        $admin = User::create([
            'name'      => '테스트관리자',
            'email'     => 'admin@ce-admin.co.kr',
            'phone'     => '01000000000',
            'password'  => bcrypt('password'),
            'role'      => 'admin',
            'is_active' => 1,
        ]);
        $this->actingAs($admin);

        return $admin;
    }
}
