<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 저장할 때마다 시각이 「지금」으로 당겨지던 칸 셋을 바로잡는다
 * (2026-09-22 확인요청 2쪽 시험 중에 드러남).
 *
 * MySQL 은 **표의 첫 TIMESTAMP 칸**에 아무 말이 없으면
 * `DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` 를 저 혼자 붙인다
 * (explicit_defaults_for_timestamp 가 꺼진 서버). 우리는 `useCurrent()` 로 기본값만
 * 적었는데 ON UPDATE 까지 따라붙었다.
 *
 * 그래서 **그 줄을 한 번이라도 고치면 시각이 지금으로 덮였다.**
 *
 *   prescription_consents.expires_at
 *       서명 링크의 유효시각이다. 환자가 서명하는 동안 그 줄에 무엇이든 적히면
 *       (NICE 본인확인 결과ㆍ신분증 파일ㆍ상태) 유효시각이 그 순간으로 당겨져
 *       **링크가 그 자리에서 만료**된다. 남은 시간을 세는 remainingMinutes()ㆍ
 *       isExpired()ㆍisPending() 이 모두 이 값을 본다.
 *
 *   login_otp_tokens.expires_at
 *       일회용 비밀번호의 유효시각. 같은 까닭으로 저장 한 번에 만료된다.
 *
 *   prescription_reupload_requests.requested_at
 *       「언제 다시 올려 달라고 했는가」다. 그 요청을 처리하며 줄을 고칠 때마다
 *       청한 때가 지워지고 마지막으로 손댄 때로 바뀐다 — 자취가 사라진다.
 *
 * 기본값(DEFAULT CURRENT_TIMESTAMP)은 그대로 둔다. NOT NULL 인 timestamp 라
 * 기본값을 떼면 explicit_defaults_for_timestamp 가 켜진 서버에서 표를 다루지 못한다.
 * 값은 코드가 늘 채운다 — 떼는 것은 ON UPDATE 뿐이다.
 */
return new class extends Migration
{
    /** 표 => 칸 */
    private const 고칠것 = [
        'prescription_consents'          => 'expires_at',
        'login_otp_tokens'               => 'expires_at',
        'prescription_reupload_requests' => 'requested_at',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;   // sqlite 에는 이 버릇이 없다
        }

        foreach (self::고칠것 as $표 => $칸) {
            if (! Schema::hasTable($표) || ! Schema::hasColumn($표, $칸)) {
                continue;
            }

            /* MODIFY 에 DEFAULT 를 똑똑히 적으면 저 혼자 붙었던 ON UPDATE 가 떨어진다.
               다시 적지 않으면 MySQL 이 첫 TIMESTAMP 칸이라며 또 붙인다. */
            DB::statement("ALTER TABLE `{$표}` MODIFY `{$칸}` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");
        }
    }

    /**
     * 되돌리지 않는다.
     *
     * 되돌린다는 것은 「저장할 때마다 시각을 지금으로 덮는다」를 되살리는 일이다.
     * 그것으로 이로울 것이 없고, 되살리는 사이에 고쳐진 줄들이 다시 망가진다.
     */
    public function down(): void
    {
        //
    }
};
