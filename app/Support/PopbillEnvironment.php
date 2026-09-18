<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * 팝빌을 시험으로 돌 것인가 운영으로 돌 것인가 (2026-09-18 지시).
 *
 * 여태 IsTest 한 칸으로만 갈랐는데 **계정은 한 벌뿐**이었다. 팝빌은 테스트베드
 * (test.popbill.com)와 운영(popbill.co.kr)에 **각각 가입**하고, 인증키도 서버마다
 * 따로 받는다 — 사업자번호와 아이디는 회사가 하나라 같을 수 있다.
 *
 * 한 벌만 담아 두면 갈래를 바꿔도 같은 값으로 붙어, 「시험」인 줄 알고 보낸 것이
 * 운영에 남는다. 실제로 그렇게 서 있었고 시험 팩스가 운영 팝빌에 쌓였다.
 *
 * 토스와 같은 방식으로 맞춘다(TossEnvironment). 두 벌을 다 담아 두고 한 칸으로
 * 고르며, 고르는 일은 여기서만 한다 — 값을 부르는 자리가 백 곳이 넘어 그 자리마다
 * 갈래를 따지게 하면 한 곳만 빠뜨려도 운영 계정으로 시험이 나간다.
 */
final class PopbillEnvironment
{
    public const TEST = 'test';
    public const LIVE = 'live';

    public const LABELS = [
        self::TEST => '테스트',
        self::LIVE => '운영',
    ];

    /** 지금 고른 갈래 */
    public static function current(): string
    {
        return config('popbill.env') === self::LIVE ? self::LIVE : self::TEST;
    }

    public static function isLive(): bool
    {
        return self::current() === self::LIVE;
    }

    public static function label(): string
    {
        return self::LABELS[self::current()];
    }

    /**
     * 고른 갈래의 계정을 쓰이는 자리에 앉힌다.
     *
     * 부팅할 때 설정을 읽어 온 바로 뒤에 한 번 부른다.
     *
     * 고른 갈래의 값이 비어 있으면 그 칸은 건드리지 않는다 — 아직 두 벌을 담기 전인
     * 서버가 갑자기 계정을 잃고 멈추면 안 된다.
     */
    public static function apply(): void
    {
        $갈래  = self::current();
        $계정  = (array) config("popbill.accounts.{$갈래}", []);
        $빈것  = [];

        /* 연동키는 묶음의 뿌리다 — 이것이 갈리면 붙는 서버 자체가 갈린다 */
        $짝 = [
            'link_id'    => 'popbill.LinkID',
            'secret_key' => 'popbill.SecretKey',
            'corp_num'   => 'popbill.test.corp_num',
            'user_id'    => 'popbill.test.user_id',
            'sender_num' => 'popbill.test.sender_num',
            'sms_sender' => 'popbill.test.sms_sender',
            'fax_sender' => 'popbill.test.fax_sender',
        ];

        foreach ($짝 as $열쇠 => $자리) {
            $값 = trim((string) ($계정[$열쇠] ?? ''));

            if ($값 !== '') {
                config([$자리 => $값]);
            } elseif (in_array($열쇠, ['corp_num', 'user_id'], true)) {
                $빈것[] = $열쇠;
            }
        }

        /* 테스트 모드는 이제 갈래가 정한다. 예전에는 따로 켜고 껐는데, 운영 계정을
           넣고 이것을 끄는 것을 잊거나 그 반대인 일이 있었다. */
        config(['popbill.IsTest' => $갈래 === self::TEST]);

        if ($빈것) {
            Log::warning('[팝빌] 고른 갈래의 계정이 비어 있어 예전 설정을 씁니다', [
                'env' => $갈래, '빈 칸' => $빈것,
            ]);
        }
    }

    /**
     * 고른 갈래와 실제로 붙는 곳이 어긋나는가 — 설정 화면이 알린다.
     *
     * 운영을 골랐는데 시험 계정으로 붙어 있으면 발행이 되는 것처럼 보이지만 국세청에는
     * 아무것도 가지 않는다. 그 반대는 더 나쁘다 — 시험인 줄 알고 누른 것이 신고된다.
     */
    public static function mismatch(): ?string
    {
        $갈래 = self::current();
        $계정 = (array) config("popbill.accounts.{$갈래}", []);

        if (trim((string) ($계정['corp_num'] ?? '')) === '') {
            return self::LABELS[$갈래] . ' 계정의 사업자번호가 비어 있습니다 — 예전 설정으로 돌고 있습니다.';
        }

        if ((bool) config('popbill.IsTest') !== ($갈래 === self::TEST)) {
            return '테스트 모드와 사용 환경이 어긋납니다.';
        }

        /* 두 갈래가 같은 사업자번호인 것은 잘못이 아니다 (2026-09-18 확인).

           팝빌은 테스트베드와 운영이 **같은 사업자번호로 각각 가입**한다 — 회사는
           하나이기 때문이다. 갈리는 것은 붙는 서버(IsTest)와 그 서버에 쌓인 자료다.
           실제로 같은 번호인데도 잔액이 0원과 2,709,980원으로 달랐고, 운영으로 보낸
           접수번호는 테스트베드에서 「존재하지 않습니다」로 나왔다.

           한때 이것을 어긋남으로 잡아 두었는데, 바른 설정에 대고 틀렸다고 말하는
           경고는 읽는 사람이 곧 무시하게 된다. */

        return null;
    }
}
