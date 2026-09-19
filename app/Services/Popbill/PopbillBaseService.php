<?php

namespace App\Services\Popbill;

use App\Support\PopbillEnvironment;
use Linkhub\Popbill\PopbillException;

abstract class PopbillBaseService
{
    /**
     * 이 서비스의 갈래 이름 — PopbillEnvironment::SERVICES 의 열쇠.
     *
     * 빈 값이면 갈래를 따로 고르지 않고 전체 설정을 그대로 따른다.
     */
    protected const SERVICE = '';

    protected string $linkId;
    protected string $secretKey;
    protected bool $isTest;
    protected bool $ipRestrictOnOff;
    protected bool $useStaticIp;
    protected bool $useLocalTimeYn;
    protected string $corpNum;
    protected string $userId;
    protected string $senderNum;

    /** 이 서비스가 어느 갈래로 도는가 — test · live */
    protected string $popbillEnv;

    public function __construct()
    {
        /* 갈래를 서비스마다 따로 고른다 (2026-09-19 지시).

           여태 이 자리가 전역 config 한 벌만 읽어, 다섯 갈래가 늘 함께 움직였다.
           시험 중에 문자만 운영으로 내보내려면 세금계산서ㆍ현금영수증까지 운영으로
           올려야 했고, 그것은 곧 국세청 신고다.

           서비스는 저마다 클래스가 따로이고 싱글턴으로 각각 만들어지므로, 자기 몫의
           계정을 여기서 한 번 집으면 그만이다 — 부르는 자리를 고칠 일이 없다. */
        /* 갈래를 밝히지 않은 서비스(계좌조회 등)는 전체 설정을 그대로 따른다 —
           accountFor('') 는 popbill.env 를 집는다. */
        $계정 = PopbillEnvironment::accountFor(static::SERVICE);

        $this->popbillEnv      = $계정['env'];
        $this->linkId          = $계정['link_id'];
        $this->secretKey       = $계정['secret_key'];
        $this->isTest          = static::SERVICE === ''
                                 ? (bool) config('popbill.IsTest', true)
                                 : $계정['is_test'];
        $this->ipRestrictOnOff = (bool) config('popbill.IPRestrictOnOff', true);
        $this->useStaticIp     = (bool) config('popbill.UseStaticIP', false);
        $this->useLocalTimeYn  = (bool) config('popbill.UseLocalTimeYN', true);
        $this->corpNum         = $계정['corp_num'];
        $this->userId          = $계정['user_id'];

        /* 발신번호는 갈래마다 다르다 — 팝빌은 서버마다 따로 승인한다.
           문자와 팩스도 서로 다른 번호가 승인되므로 제 것을 먼저 본다. */
        $this->senderNum = static::SERVICE === 'fax'
            ? ($계정['fax_sender'] ?: $계정['sender_num'])
            : ($계정['sms_sender'] ?: $계정['sender_num']);
    }

    /** 이 서비스가 도는 갈래 — 화면과 기록이 「무엇으로 나갔나」를 적을 때 쓴다 */
    public function env(): string
    {
        return $this->popbillEnv;
    }

    public function envLabel(): string
    {
        return PopbillEnvironment::LABELS[$this->popbillEnv] ?? $this->popbillEnv;
    }

    protected function handleException(PopbillException $e): never
    {
        throw new \RuntimeException(
            "[{$e->getCode()}] {$e->getMessage()}",
            (int) $e->getCode(),
            $e
        );
    }

    abstract protected function newService(): object;
}
