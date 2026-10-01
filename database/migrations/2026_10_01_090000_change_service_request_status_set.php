<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SR 진행 상태를 신규ㆍ진행중ㆍ완료ㆍ대기로 바꾼다 (2026-10-01 지시).
 *
 * 전에는 접수ㆍ처리중ㆍ답변완료ㆍ종결이었다. 「답변완료」는 답변을 적었는가를 말하고
 * 「완료」는 일이 끝났는가를 말한다 — 담당자가 보는 것은 뒤쪽이다. 「종결」 자리에는
 * 「대기」를 둔다. 대기는 끝난 것이 아니라 멈춰 둔 것이다.
 *
 * **열의 꼴은 바꾸지 않는다.** varchar(20) 이라 새 열쇠(new ㆍ done ㆍ hold)가 그대로
 * 들어간다. 바꾸는 것은 기본값과, 이미 담긴 줄의 값뿐이다.
 *
 * 돌릴 때 운영 service_requests 는 0줄이었다. 그래도 옮기는 코드를 둔다 — 다른 서버나
 * 뒤에 되돌린 건에는 옛 값이 있을 수 있고, 그것을 그대로 두면 화면의 상태 칸이 빈다
 * (STATUSES 에 없는 열쇠는 이름표를 찾지 못한다).
 */
return new class extends Migration
{
    /** 옛 열쇠 → 새 열쇠 */
    private const 옮김 = [
        'open'     => 'new',
        'answered' => 'done',      // 답변을 적어 둔 건은 끝난 것으로 본다
        'closed'   => 'done',      // 종결도 완료로 모은다 — 대기는 멈춘 것이라 뜻이 다르다
        // in_progress 는 이름만 「처리중 → 진행중」이고 열쇠가 같다
    ];

    public function up(): void
    {
        if (! Schema::hasTable('service_requests')) {
            return;
        }

        foreach (self::옮김 as $옛것 => $새것) {
            DB::table('service_requests')->where('status', $옛것)->update(['status' => $새것]);
        }

        /* 기본값 — doctrine/dbal 없이 바꾼다. 열의 꼴은 그대로 두고 기본값만 적는다. */
        $this->기본값($this->상태열꼴(), 'new');
    }

    public function down(): void
    {
        if (! Schema::hasTable('service_requests')) {
            return;
        }

        /* 되돌릴 때 done 은 answered 로 보낸다 — 완료로 모은 종결 건을 가릴 길이 없다.
           이 마이그레이션을 되돌리는 일은 열쇠를 되살리는 것이고, 어느 건이 원래
           종결이었는지는 되살릴 수 없다. */
        foreach (['new' => 'open', 'done' => 'answered', 'hold' => 'closed'] as $새것 => $옛것) {
            DB::table('service_requests')->where('status', $새것)->update(['status' => $옛것]);
        }

        $this->기본값($this->상태열꼴(), 'open');
    }

    /** 지금 적혀 있는 열의 꼴 — 바꾸지 않고 그대로 다시 적으려고 읽는다 */
    private function 상태열꼴(): string
    {
        $것 = DB::selectOne("SHOW COLUMNS FROM service_requests WHERE Field = 'status'");

        return $것->Type ?? 'varchar(20)';
    }

    private function 기본값(string $꼴, string $값): void
    {
        $널 = 'NOT NULL';

        DB::statement(sprintf(
            'ALTER TABLE service_requests MODIFY status %s %s DEFAULT %s',
            $꼴, $널, DB::connection()->getPdo()->quote($값)
        ));
    }
};
