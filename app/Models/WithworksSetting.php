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

    /** 위드웍스로 넘기는 판매 유형 */
    public const SO_TYPE_EUD = '5001';

    /** 교환·반품·취소를 넘길 때 쓰는 유형 — 판매와 코드가 다르다 */
    public const SO_TYPE_EUD_RETURN = '5004';

    protected $fillable = [
        'mode',
        'test_api_url', 'test_api_token', 'test_account_id',
        'prod_api_url', 'prod_api_token', 'prod_account_id',
        'webhook_url', 'webhook_secret', 'so_type', 'return_so_type',
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
            // 서버 .env 가 아직 옛 이름일 수 있어 둘 다 본다
            'test_api_token'  => env('DEMOWORKS_API_TOKEN', env('TODOWORKS_API_TOKEN')),
            'prod_api_token'  => null,
            'webhook_url'     => rtrim((string) config('app.url'), '/') . '/api/webhook/withworks',
            'webhook_secret'  => env('WITHWORKS_WEBHOOK_SECRET'),
            'so_type'         => self::SO_TYPE_EUD,
            'return_so_type'  => self::SO_TYPE_EUD_RETURN,
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
            'services.demoworks.so_type'        => $s->so_type ?: self::SO_TYPE_EUD,
            'services.demoworks.return_so_type' => $s->return_so_type ?: self::SO_TYPE_EUD_RETURN,
            'services.demoworks.mode'           => $s->mode,
        ]);

        return $s;
    }
}
