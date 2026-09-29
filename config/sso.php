<?php

/*
 * SSO — 아직 정해지지 않은 것을 담아 두는 자리.
 *
 * **Tenant IDㆍClient IDㆍClient SecretㆍRedirect URI 는 여기 없다.** 그것들은 코드에도
 * .env 에도 두지 않고 관리 화면에서 DB 에 담는다(App\Support\SsoSettings · 지시서 §3).
 *
 * 여기 있는 것은 「정책」이다. 지시서가 [확인 필요]로 남긴 것들이라 기본값을 골라
 * 두되, 고르는 순간 그것이 결정처럼 굳지 않게 **아무 일도 하지 않는 값**으로 둔다.
 */

return [

    'web' => [

        /*
         * 등록되지 않은 사람이 SSO 로 들어왔을 때 계정을 만들어 줄 것인가.
         *
         * 기본은 만들지 않는다 — 지시서 §5-2 의 「사전 등록 사용자만 허용」이다.
         * 켜고 끄는 것이 정책이라 [확인 필요]로 남아 있다(Coloplast Chris).
         */
        'jit_create' => (bool) env('SSO_WEB_JIT_CREATE', false),

        /* 만들어 줄 때 어떤 역할로 만들 것인가 — 위를 켤 때만 뜻이 있다 */
        'jit_role' => env('SSO_WEB_JIT_ROLE', 'manager'),

        /*
         * 만들어 줄 때 어떤 권한 그룹에 넣을 것인가 (2026-09-29 지시).
         *
         * **넣지 않으면 대시보드만 보인다.** 권한은 역할이 아니라 권한 그룹이 정하고
         * (`PermissionService::allows`), 그룹이 없는 사람에게는 대시보드 보기만 내준다.
         * 그래서 SSO 로 들어온 사람이 메뉴가 하나뿐이라고 물어 왔다(2026-09-29).
         *
         * 'full' 이면 전권 그룹(is_full_access)을 찾아 넣는다 — 지시가 「SSO 로 등록된
         * 사용자는 모든 메뉴가 보여야 함」이다. 숫자를 적으면 그 그룹에 넣는다.
         * 빈 값이면 넣지 않는다(옛 동작).
         */
        'jit_permission_group' => env('SSO_WEB_JIT_PERMISSION_GROUP', 'full'),

        /*
         * Entra App Roles(roles claim) → 우리 역할.
         *
         * **아직 정해지지 않았다**(지시서 §5-3 · §9-5). 비어 있으면 역할을 손대지
         * 않는다 — 짝이 없다고 역할을 지우면, 이미 쓰고 있던 사람이 SSO 로 한 번
         * 들어왔다는 이유로 권한을 잃는다.
         *
         * 정해지면 이런 꼴이 된다:
         *   'CEAdmin.Admin'   => 'admin',
         *   'CEAdmin.Manager' => 'manager',
         *
         * groups claim 은 쓰지 않는다 — 150개가 넘으면 빠지고 온다.
         */
        'role_map' => [],
    ],
];
