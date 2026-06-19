<?php

namespace App\Services\Popbill;

use Linkhub\Popbill\PopbillException;

abstract class PopbillBaseService
{
    protected string $linkId;
    protected string $secretKey;
    protected bool $isTest;
    protected bool $ipRestrictOnOff;
    protected bool $useStaticIp;
    protected bool $useLocalTimeYn;
    protected string $corpNum;
    protected string $userId;
    protected string $certKey;
    protected string $senderNum;

    public function __construct()
    {
        $this->linkId          = config('popbill.LinkID');
        $this->secretKey       = config('popbill.SecretKey');
        $this->isTest          = (bool) config('popbill.IsTest', true);
        $this->ipRestrictOnOff = (bool) config('popbill.IPRestrictOnOff', true);
        $this->useStaticIp     = (bool) config('popbill.UseStaticIP', false);
        $this->useLocalTimeYn  = (bool) config('popbill.UseLocalTimeYN', true);
        $this->corpNum         = config('popbill.test.corp_num', '');
        $this->userId          = config('popbill.test.user_id', '');
        $this->certKey         = config('popbill.test.cert_key', '');
        $this->senderNum       = config('popbill.test.sender_num', '');
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
