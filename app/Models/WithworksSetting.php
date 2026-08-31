<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 위드웍스 연동 설정 (단일 행).
 *
 * 테스트(데모웍스)와 운영(위드웍스)의 값을 나란히 담고 mode 로 어느 쪽을 쓸지 고른다.
 * 한 벌만 두고 갈아 끼우면 전환할 때마다 반대쪽 값을 잃는다.
 *
 * 값이 없으면 .env 에 있던 것으로 시드한다 — 화면으로 옮겼다고 해서 이미 돌던 연동이
 * 끊기면 안 된다.
 */
class WithworksSetting extends Model
{
    public const MODE_TEST = 'test';
    public const MODE_PROD = 'production';

    public const MODES = [
        self::MODE_TEST => '테스트 (데모웍스)',
        self::MODE_PROD => '운영 (위드웍스)',
    ];

    /**
     * 위드웍스로 넘기는 판매 유형 — 처음 열었을 때 채워 둘 값이다.
     *
     * 코드는 위드웍스 code_list 가 정한다. 2026-08-18 개편으로 5000·6000번대가 지워지고
     * 1500·1600번대가 그 자리를 받았다 — 판매 1501 · 샘플 1601 · 반품 1505(샘플 1605).
     * 값은 설정 화면에서 적어 넣는다. 여기 있는 것은 첫 화면을 빈칸으로 두지 않기 위한
     * 출발점일 뿐이다.
     */
    public const SO_TYPE_EUD = '1501';

    /**
     * 되돌리는 주문의 판매유형 — 종류마다 따로 둔다.
     *
     * 창고가 하는 일이 셋 다 다르다. 반품은 수거해서 넣고, 교환은 넣었다가 다시 내보내고,
     * 취소는 아무것도 움직이지 않는다. 한 코드로 묶으면 창고 담당자가 비고를 읽어야
     * 무엇을 할지 알 수 있다.
     *
     * 2026-08-31 위드웱스가 세 가지를 다 열었다 — 취소 1504 ㆍ 반품 1505 ㆍ 교환 1506.
     * 전에는 반품만 있어 둘을 비워 두었다. 여기 값은 첫 화면을 빈칸으로 두지 않기
     * 위한 출발점일 뿐이다 — 저쪽이 바꾸면 설정 화면에서 고친다.
     */
    public const SO_TYPE_EUD_CANCEL   = '1504';
    public const SO_TYPE_EUD_RETURN   = '1505';
    public const SO_TYPE_EUD_EXCHANGE = '1506';

    /**
     * 일반 환불(자격 변경 등)이 세우는 금액조정 주문 — 전산판매(금액조정).
     *
     * 물건은 움직이지 않고 금액만 마이너스로 맞춘다. 반품 코드로 보내면 창고가
     * 오지 않을 물건을 기다린다.
     */
    public const SO_TYPE_ADJUST = '1092';

    protected $fillable = [
        'mode',
        'test_api_url', 'test_api_token', 'test_account_id',
        'prod_api_url', 'prod_api_token', 'prod_account_id',
        'webhook_url', 'webhook_secret', 'so_type',
        'return_so_type', 'cancel_so_type', 'exchange_so_type', 'adjust_so_type',
    ];

    /** 처음 열었을 때 빈 화면을 보여 주지 않도록 아는 값으로 채워 둔다 */
    public static function current(): self
    {
        return static::first() ?? static::create([
            'mode'            => self::MODE_TEST,
            'test_api_url'    => 'https://www.demoworks.co.kr',
            'test_account_id' => '136155',
            'prod_api_url'    => 'https://www.withworks.co.kr',
            'prod_account_id' => '148659',
            // 이미 .env 로 돌던 값이 있으면 그대로 물려받는다
            /* 처음 한 번만, 이미 .env 로 돌던 값을 물려받는다. 배포하자마자 연동이 끊기면
               안 되기 때문이다. 이 뒤로는 DB 가 유일한 출처다 — 화면에서 바꾼 값이 언제나
               이긴다. 서버 .env 가 아직 옛 이름일 수 있어 둘 다 본다. */
            'test_api_token'  => env('DEMOWORKS_API_TOKEN', env('TODOWORKS_API_TOKEN')),
            'prod_api_token'  => null,
            'webhook_url'     => rtrim((string) config('app.url'), '/') . '/api/webhook/withworks',
            'webhook_secret'  => env('WITHWORKS_WEBHOOK_SECRET'),
            'so_type'          => self::SO_TYPE_EUD,
            'cancel_so_type'   => self::SO_TYPE_EUD_CANCEL,
            'return_so_type'   => self::SO_TYPE_EUD_RETURN,
            'exchange_so_type' => self::SO_TYPE_EUD_EXCHANGE,
            'adjust_so_type'   => self::SO_TYPE_ADJUST,
        ]);
    }

    public function isProduction(): bool
    {
        return $this->mode === self::MODE_PROD;
    }

    public function modeLabel(): string
    {
        return self::MODES[$this->mode] ?? $this->mode;
    }

    /** 지금 붙어 있는 곳 */
    public function apiUrl(): ?string
    {
        return $this->isProduction() ? $this->prod_api_url : $this->test_api_url;
    }

    public function apiToken(): ?string
    {
        return $this->isProduction() ? $this->prod_api_token : $this->test_api_token;
    }

    /** 콜로플라스트 거래처 id — 환경마다 다르다 */
    public function accountId(): ?string
    {
        return $this->isProduction() ? $this->prod_account_id : $this->test_account_id;
    }

    /**
     * DB 설정을 config 로 올린다.
     *
     * 위드웍스를 부르거나 콜백을 받는 코드는 전부 config 를 보므로, 그 앞에서 한 번
     * 부르면 나머지는 손대지 않아도 된다. AppServiceProvider 에서 요청마다 한 번 부른다.
     */
    public static function applyToConfig(): self
    {
        $s = static::current();

        config([
            'services.demoworks.api_url'        => $s->apiUrl(),
            'services.demoworks.token'          => $s->apiToken(),
            'services.demoworks.webhook_secret' => $s->webhook_secret,
            'services.demoworks.account_id'     => $s->accountId(),
            'services.demoworks.so_type'          => $s->so_type ?: self::SO_TYPE_EUD,
            'services.demoworks.cancel_so_type'   => $s->cancel_so_type ?: self::SO_TYPE_EUD_CANCEL,
            'services.demoworks.return_so_type'   => $s->return_so_type ?: self::SO_TYPE_EUD_RETURN,
            'services.demoworks.exchange_so_type' => $s->exchange_so_type ?: self::SO_TYPE_EUD_EXCHANGE,
            'services.demoworks.adjust_so_type'   => $s->adjust_so_type ?: self::SO_TYPE_ADJUST,
            'services.demoworks.mode'           => $s->mode,
        ]);

        return $s;
    }
}
