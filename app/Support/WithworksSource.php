<?php

namespace App\Support;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

/**
 * 위드웍스 운영 DB 로 가는 길 — **읽기만 한다** (2026-09-18 지시).
 *
 * 붙는 곳은 읽기 복제본(`cluster-ro-…`)이지만 **MySQL 쪽에서 잠겨 있지 않다**
 * (`@@read_only = 0`). 주소 이름만 믿고 「쓰기가 막혀 있다」고 여기면 안 된다 —
 * 실수로 나간 쓰기 질의가 그대로 들어간다.
 *
 * 그래서 두 겹으로 막는다.
 *
 * **① 이어 붙자마자 그 자리를 읽기 전용으로 선언한다.** `SET SESSION TRANSACTION
 *    READ ONLY` 는 그 연결에서 나가는 모든 쓰기를 DB 가 거절하게 한다. 우리 코드가
 *    아무리 잘못 짜여도 저쪽 자료는 바뀌지 않는다.
 *
 * **② 부르는 길을 하나로 둔다.** 밖에서는 이 클래스의 `조회()` 만 쓴다. `DB::connection`
 *    을 여기저기서 직접 부르기 시작하면 ①을 걸지 않은 연결이 어딘가에 생긴다.
 *
 * 계정은 DB(settings 표)에 담는다 — 설정 › 위드웍스 자료 가져오기 화면에서 넣는다.
 * 파일에 두면 서버마다 갈리고, 바꾸려면 서버를 만져야 한다.
 */
final class WithworksSource
{
    public const 창고 = 'warehouse';
    public const 관리 = 'admin';

    public const 갈래 = [
        self::창고 => '창고(warehouse)',
        self::관리 => '관리(admin)',
    ];

    /** settings 표에 담기는 이름 — 설정 화면이 같은 열쇠를 쓴다 */
    public const 묶음 = 'withworks_source';

    /**
     * 그 DB 로 가는 연결. 없으면 그 자리에서 세운다.
     *
     * 세울 때마다 읽기 전용을 건다 — 연결이 끊겼다 다시 붙어도 그 선언이 따라온다.
     */
    public static function 연결(string $갈래): Connection
    {
        $이름 = 'ww_' . $갈래;

        if (! config("database.connections.{$이름}")) {
            $c = self::계정($갈래);

            if (! $c['host'] || ! $c['username']) {
                throw new \RuntimeException(
                    "위드웍스 {$갈래} 접속 정보가 없습니다 — 설정 › 위드웍스 자료 가져오기에서 넣어 주십시오."
                );
            }

            config(["database.connections.{$이름}" => [
                'driver'    => 'mysql',
                'host'      => $c['host'],
                'port'      => $c['port'] ?: 3306,
                'database'  => $c['database'],
                'username'  => $c['username'],
                'password'  => $c['password'],
                'charset'   => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'strict'    => false,
                /* 이어 붙는 그 순간 읽기 전용으로 선언한다 — 가장 바깥의 자물쇠다 */
                'options'   => [
                    \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET SESSION TRANSACTION READ ONLY',
                ],
            ]]);
        }

        return DB::connection($이름);
    }

    /**
     * 읽는다. 밖에서 저쪽 DB 를 만지는 길은 이것뿐이다.
     *
     * @param  callable(\Illuminate\Database\Query\Builder): mixed  $하는일
     */
    public static function 조회(string $갈래, string $표, callable $하는일): mixed
    {
        return $하는일(self::연결($갈래)->table($표));
    }

    /** 담아 둔 계정 */
    public static function 계정(string $갈래): array
    {
        return [
            'host'     => self::값($갈래, 'host'),
            'port'     => (int) (self::값($갈래, 'port') ?: 3306),
            'database' => self::값($갈래, 'database') ?: $갈래,
            'username' => self::값($갈래, 'username'),
            'password' => self::값($갈래, 'password'),
        ];
    }

    public static function 값(string $갈래, string $칸): ?string
    {
        $줄 = \App\Models\Setting::where('group', self::묶음)
            ->where('key', "{$갈래}_{$칸}")->first();

        return $줄?->plainValue();
    }

    /** 닿는지 본다 — 설정 화면의 「연결 시험」 */
    public static function 닿나(string $갈래): array
    {
        try {
            $r = self::연결($갈래)->selectOne('SELECT VERSION() v, @@read_only ro');

            return [
                'ok'  => true,
                'msg' => "MySQL {$r->v} · 서버 read_only={$r->ro} · 우리 연결은 읽기 전용으로 선언했습니다.",
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'msg' => mb_substr($e->getMessage(), 0, 200)];
        }
    }
}
