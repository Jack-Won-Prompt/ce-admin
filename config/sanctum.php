<?php

use Laravel\Sanctum\Sanctum;

return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    |
    | Requests from the following domains / hosts will receive stateful API
    | authentication cookies. Typically, these should include your local
    | and production domains which access your API via a frontend SPA.
    |
    */

    /* 여기 적힌 자리에서 온 `/api/*` 요청만 세션으로 사람을 가린다. 적히지 않은 자리는
       토큰만 받으므로 세션이 있어도 401 이 된다.

       H5(`/m/*`)는 앱과 같은 `/api/*` 를 세션으로 부른다. 그래서 `APP_URL` 하나만
       등록되어 있으면, 같은 서버를 `www` 를 뗀 주소로 들어온 사람은 `/api/auth/me`
       부터 401 을 받고 — 모바일 화면이 401 을 보면 `/m/login` 으로 되돌려 보내고,
       `/m/login` 은 이미 로그인한 사람을 `dashboard` 로 보내므로 — 폰에 웹 화면이
       뜬다. 2026-10-10 에 `ceadmin.co.kr`(www 없음)에서 그대로 일어났다.

       도메인을 바꿀 때도 같은 자리에서 조용히 끊기니, 우리가 쓰는 자리를 www 있는
       것과 없는 것 모두 적어 둔다. `SANCTUM_STATEFUL_DOMAINS` 를 .env 에 두면
       그 값이 이 목록을 대신한다. */
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s%s',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
        Sanctum::currentApplicationUrlWithPort(),
        ',75.2.99.52,ceadmin.co.kr,www.ceadmin.co.kr,mycolo.co.kr,www.mycolo.co.kr',
    ))),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Guards
    |--------------------------------------------------------------------------
    |
    | This array contains the authentication guards that will be checked when
    | Sanctum is trying to authenticate a request. If none of these guards
    | are able to authenticate the request, Sanctum will use the bearer
    | token that's present on an incoming request for authentication.
    |
    */

    'guard' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Expiration Minutes
    |--------------------------------------------------------------------------
    |
    | This value controls the number of minutes until an issued token will be
    | considered expired. This will override any values set in the token's
    | "expires_at" attribute, but first-party sessions are not affected.
    |
    */

    'expiration' => null,

    /*
    |--------------------------------------------------------------------------
    | Token Prefix
    |--------------------------------------------------------------------------
    |
    | Sanctum can prefix new tokens in order to take advantage of numerous
    | security scanning initiatives maintained by open source platforms
    | that notify developers if they commit tokens into repositories.
    |
    | See: https://docs.github.com/en/code-security/secret-scanning/about-secret-scanning
    |
    */

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Middleware
    |--------------------------------------------------------------------------
    |
    | When authenticating your first-party SPA with Sanctum you may need to
    | customize some of the middleware Sanctum uses while processing the
    | request. You may change the middleware listed below as required.
    |
    */

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],

];
