<?php

return [
    'LinkID'            => env('POPBILL_ID'),
    'SecretKey'         => env('POPBILL_SECRET_KEY'),
    'IsTest'            => env('POPBILL_IS_TEST', true),
    'IPRestrictOnOff'   => env('POPBILL_IP_RESTRICT_ON_OFF', true),
    'UseStaticIP'       => env('POPBILL_USE_STATIC_IP', false),
    'UseLocalTimeYN'    => env('POPBILL_USE_LOCAL_TIME_YN', true),
    'LINKHUB_COMM_MODE' => env('POPBILL_LINKHUB_COMM_MODE', 'CURL'),

    /*
     * 문자를 실제로 보내지 않고 로그만 남길지.
     *
     * 예전 기본값은 true 였다. 그래서 팝빌을 운영으로 돌린 뒤에도(POPBILL_IS_TEST=false)
     * 세금계산서ㆍ현금영수증ㆍ팩스는 실제로 나가는데 문자만 조용히 시뮬레이션이었다 —
     * 화면은 「발송되었습니다」라고 하는데 아무도 받지 못했다. 문자만 다르게 서 있을
     * 이유가 없어 팝빌 시험 여부를 그대로 따르게 한다.
     *
     * 그래도 따로 끄고 켤 수 있게 열쇠는 남긴다(POPBILL_SMS_SIMULATE).
     */
    'sms_simulate'      => env('POPBILL_SMS_SIMULATE', env('POPBILL_IS_TEST', true)),

    /*
     * 발행ㆍ취소를 팝빌에 보내지 않고 성공으로 처리할지.
     *
     * 팝빌이 운영이면(IS_TEST=false) 발행은 곧 국세청 신고다. 화면을 처음부터 끝까지
     * 훑어 보는 시험에서는 그 신고가 나가면 안 된다 — 되돌리려면 취소 신고를 또 해야
     * 하고, 그것도 남의 회사 이름으로 남는다. 이 열쇠가 켜져 있으면 조회는 그대로
     * 팝빌에 묻고, 쓰는 일(즉시발행ㆍ임시저장ㆍ발행ㆍ삭제ㆍ취소)만 막는다.
     */
    'issue_simulate'    => env('POPBILL_ISSUE_SIMULATE', false),

    /*
     * 팩스를 실제로 보내지 않고 로그만 남길지.
     *
     * 문자에는 이 열쇠가 있었는데(sms_simulate) 팩스에는 없었다. 그래서 화면을 처음부터
     * 끝까지 훑어 보는 시험에서 「팩스 발송」을 누르면 팝빌로 진짜 나갔다 — 종이가 나가고
     * 포인트가 깎인다. 받는 번호를 시험 번호로 돌려 두어도 나가는 것은 나가는 것이다.
     *
     * 켜 두면 합본 만들기ㆍ서류 세기ㆍ받는 곳 고르기까지 그대로 하고 마지막 한 걸음만
     * 막는다. 접수번호 자리에는 SIMFAX- 로 시작하는 번호가 선다.
     */
    'fax_simulate'      => env('POPBILL_FAX_SIMULATE', false),

    /*
     * 문자ㆍ팩스를 어디로 보내는가 — 세 갈래 (2026-09-07 지시).
     *
     *   live     적힌 곳으로 보낸다
     *   redirect 적힌 곳을 무시하고 **시험 수신처로 돌린다**
     *   simulate 아예 보내지 않고 로그만 남긴다
     *
     * 여태 시뮬레이션이냐 아니냐 둘뿐이었다. 그래서 **정말 나가는지**를 볼 길이 없었다 —
     * 시뮬레이션을 끄면 실제 환자에게 가고, 켜 두면 아무것도 안 나간다. 가운데가 없었다.
     *
     * 팩스는 그 없음이 더 나빴다. 시험할 때마다 **기준정보의 팩스번호를 시험용으로
     * 바꿔 두었는데**, 되돌리기를 잊으면 운영에서 공단으로 팩스가 안 간다. 업무
     * 자료를 만져 시험하는 일은 이 갈래가 없앤다.
     *
     * 예전 열쇠(sms_simulate·fax_simulate)가 켜져 있으면 그것을 따른다 — 서버
     * 설정을 손대지 않아도 지금 하던 대로 돈다.
     */
    'sms_mode' => env('POPBILL_SMS_MODE')
                  ?: (env('POPBILL_SMS_SIMULATE', env('POPBILL_IS_TEST', true)) ? 'simulate' : 'live'),
    'fax_mode' => env('POPBILL_FAX_MODE')
                  ?: (env('POPBILL_FAX_SIMULATE', false) ? 'simulate' : 'live'),

    /*
     * 어느 것으로 도는가 — test(시험) · live(운영) (2026-09-18 지시).
     *
     * 여태 IsTest 한 칸으로만 갈랐는데, **계정은 한 벌뿐**이었다. 팝빌은 시험 계정과
     * 운영 계정이 서로 다른 연동키ㆍ사업자번호ㆍ아이디를 쓰므로, 갈래만 바꾸면
     * 시험 갈래인데 운영 계정으로 붙는다 — 실제로 그렇게 서 있었고, 시험으로
     * 보낸 팩스가 운영 팝빌에 남았다.
     *
     * 토스와 같은 방식으로 맞춘다. 두 벌을 다 담아 두고 이 한 칸으로 고른다.
     * 고르는 일은 PopbillEnvironment 한 곳에서 하고, 쓰는 쪽은 예전 그대로
     * popbill.test.* 를 읽는다 — 그 자리가 백 곳이 넘어 하나씩 고치면 반드시 빠뜨린다.
     */
    'env' => env('POPBILL_ENV')
             ?: (filter_var(env('POPBILL_IS_TEST', true), FILTER_VALIDATE_BOOLEAN) ? 'test' : 'live'),

    /*
     * 서비스마다 갈래를 따로 고른다 (2026-09-19 지시).
     *
     * 시험 도중에도 **문자만은 운영으로** 보내야 할 때가 있다. 문자는 받아 보아야
     * 글이 맞는지ㆍ링크가 열리는지 알 수 있는데, 테스트베드 계정에는 승인된 발신번호가
     * 없어 한 통도 나가지 않는다(-15001014 미등록 발신번호). 그렇다고 위의 한 칸을
     * 운영으로 올리면 세금계산서ㆍ현금영수증까지 국세청으로 간다.
     *
     * 비워 두면 위의 env 를 따른다 — 손대지 않은 서버는 예전 그대로 돈다.
     * 고르는 일은 PopbillEnvironment 한 곳에서 한다.
     */
    'service_env' => [
        'sms'        => env('POPBILL_ENV_SMS',        ''),
        'fax'        => env('POPBILL_ENV_FAX',        ''),
        'taxinvoice' => env('POPBILL_ENV_TAXINVOICE', ''),
        'cashbill'   => env('POPBILL_ENV_CASHBILL',   ''),
        'kakao'      => env('POPBILL_ENV_KAKAO',      ''),
    ],

    /*
     * 시험 계정 — 팝빌 테스트베드(test.popbill.com).
     *
     * 아래 live 와 이름이 같은 칸들은 갈래를 고르면 PopbillEnvironment 가
     * 쓰이는 자리(popbill.test.*)에 앉힌다. 그래서 이 묶음은 「지금 쓰는 값」이
     * 아니라 「시험일 때 쓸 값」이다.
     */
    'accounts' => [
        'test' => [
            'link_id'    => env('POPBILL_TEST_ID',         env('POPBILL_ID')),
            'secret_key' => env('POPBILL_TEST_SECRET_KEY', env('POPBILL_SECRET_KEY')),
            'corp_num'   => env('POPBILL_TEST_CORP_NUM'),
            'user_id'    => env('POPBILL_TEST_USER_ID'),
            'cert_key'   => env('POPBILL_TEST_CERT_KEY'),
            'sender_num' => env('POPBILL_TEST_SENDER_NUM'),
            'sms_sender' => env('POPBILL_TEST_SMS_SENDER_NUM', env('POPBILL_SMS_SENDER_NUM')),
            'fax_sender' => env('POPBILL_TEST_FAX_SENDER_NUM', env('POPBILL_FAX_SENDER_NUM')),
        ],

        /* 운영 계정 — 팝빌 운영(popbill.co.kr). 여기서 낸 것은 국세청까지 간다. */
        'live' => [
            'link_id'    => env('POPBILL_LIVE_ID',         env('POPBILL_ID')),
            'secret_key' => env('POPBILL_LIVE_SECRET_KEY', env('POPBILL_SECRET_KEY')),
            'corp_num'   => env('POPBILL_LIVE_CORP_NUM'),
            'user_id'    => env('POPBILL_LIVE_USER_ID'),
            'cert_key'   => env('POPBILL_LIVE_CERT_KEY'),
            'sender_num' => env('POPBILL_LIVE_SENDER_NUM'),
            'sms_sender' => env('POPBILL_LIVE_SMS_SENDER_NUM'),
            'fax_sender' => env('POPBILL_LIVE_FAX_SENDER_NUM'),
        ],
    ],

    /*
     * 지금 쓰이는 값 — 위 두 벌 가운데 고른 것이 여기 들어온다.
     *
     * 이름이 test 인 것은 예전부터 그랬기 때문이다. 백 곳이 넘는 자리가 이 이름을
     * 읽고 있어 그대로 둔다 — 「시험용」이 아니라 「지금 쓰는 값」으로 읽으면 된다.
     */
    'test' => [
        'corp_num'     => env('POPBILL_TEST_CORP_NUM'),
        'user_id'      => env('POPBILL_TEST_USER_ID'),
        'cert_key'     => env('POPBILL_TEST_CERT_KEY'),
        'receiver_hp'  => env('POPBILL_TEST_RECEIVER_HP'),
        /*
         * 발신번호는 문자와 팩스가 따로 승인된다. 콜로플라스트 계정에서도 서로 다른 번호가
         * 승인돼 있어(문자 1588-7866 · 팩스 02-722-6002) 한 값을 같이 쓰면 한쪽이 미등록으로
         * 걸린다. 서비스별 값을 먼저 보고, 없으면 예전 단일 키로 떨어진다.
         */
        'sender_num'   => env('POPBILL_TEST_SENDER_NUM'),

        /* 서비스별 값은 **그 열쇠가 있을 때만** 세운다 (2026-09-07).
           예전에는 없으면 일반 발신번호를 여기에 미리 구워 넣었는데, 그것은 서버가
           뜰 때의 .env 값이라 화면(설정 › 서비스 연동 설정 › 팝빌 › 발신 번호)에서
           고친 값보다 늘 먼저 읽혔다 — 화면에 있는데 아무 일도 하지 않는 칸이었다.
           비워 두면 아래 쓰는 자리에서 `?:` 로 일반 발신번호로 떨어진다. */
        'sms_sender'   => env('POPBILL_SMS_SENDER_NUM'),
        'fax_sender'   => env('POPBILL_FAX_SENDER_NUM'),
        'receiver_fax' => env('POPBILL_TEST_RECEIVER_FAX'),
    ],

    'company' => [
        'corp_name' => env('COMPANY_CORP_NAME', ''),
        'ceo_name'  => env('COMPANY_CEO_NAME',  ''),
        'addr'      => env('COMPANY_ADDR',       ''),
        'biz_class' => env('COMPANY_BIZ_CLASS',  ''),
        'biz_type'  => env('COMPANY_BIZ_TYPE',   ''),
        'email'     => env('COMPANY_EMAIL',      ''),
        'tel'       => env('COMPANY_TEL',        ''),
    ],

    /* 거래명세서의 공급자 주소 (2026-09-17 지시).
     *
     * 거래명세서는 위드웍스도 같은 종이를 낸다 — 두 종이의 글이 한 자라도 달라서는
     * 안 된다. 저쪽은 창고 거래처 자료의 주소(「서울 중구 …」)를 찍는데, 우리 쪽
     * company.addr 은 세금계산서에 쓰는 등기 주소라 같은 곳을 더 길게 적는다.
     * 그래서 명세서에만 쓰는 주소를 따로 둔다.
     *
     * 비워 두면 위 company.addr 을 쓴다.
     */
    'statement_addr' => env('COMPANY_STATEMENT_ADDR', '서울 중구 서소문로11길 19 배재정동빌딩 B동 10층'),
];
