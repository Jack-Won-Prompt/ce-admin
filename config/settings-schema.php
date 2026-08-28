<?php
// config/settings-schema.php
//
// 관리자 화면에서 관리하는 외부 서비스 키·설정의 목록.
// 화면·검증·암호화·config 덮어쓰기가 모두 이 파일 하나를 읽는다.
// 항목을 늘리려면 여기에 한 줄 더하면 되고, 화면 코드는 손대지 않는다.
// (권한 그룹이 config/permissions.php 를 쓰는 것과 같은 결)
//
// 필드 옵션
//   label   화면에 보이는 이름 (필수)
//   config  이 값으로 덮어쓸 런타임 설정 키 (필수) — 예: 'toss.secret_key'
//   type    text | password | bool | int | select | textarea   (기본 text)
//   options select 일 때의 선택지 [값 => 라벨]
//   help    입력란 아래 안내
//   width   1(좁게) | 2(보통, 기본) | 3(넓게)
//
// type 이 password 면 자동으로 비밀값이 된다 — DB 에 암호화 저장되고,
// 화면에는 값을 내려보내지 않으며, 빈칸으로 저장하면 기존 값을 지킨다.
//
// ⚠ 여기에 넣지 않는 것
//   · DB 접속 정보 — 설정을 읽으려면 DB 가 먼저 붙어야 한다(닭과 달걀)
//   · APP_KEY / RRN_ENCRYPTION_KEY / RRN_HASH_PEPPER — DB 를 푸는 열쇠다.
//     열쇠를 자물쇠 안에 두면 암호화가 의미를 잃는다. .env 에만 둔다.

return [

    'company' => [
        'label' => '회사 정보',
        'desc'  => '세금계산서·현금영수증·요양비 서류에 찍히는 사업자 정보',
        'fields' => [
            'corp_name' => ['label' => '상호',     'config' => 'popbill.company.corp_name'],
            'ceo_name'  => ['label' => '대표자',   'config' => 'popbill.company.ceo_name', 'width' => 1],
            'biz_type'  => ['label' => '업태',     'config' => 'popbill.company.biz_type', 'width' => 1],
            'biz_class' => ['label' => '종목',     'config' => 'popbill.company.biz_class'],
            'addr'      => ['label' => '주소',     'config' => 'popbill.company.addr', 'width' => 3],
            'tel'       => ['label' => '전화번호', 'config' => 'popbill.company.tel', 'width' => 1],
            'email'     => ['label' => '이메일',   'config' => 'popbill.company.email'],
        ],
    ],

    'popbill' => [
        'label' => '팝빌',
        'desc'  => '현금영수증 · 세금계산서 · 팩스 · 문자 발송',
        'fields' => [
            'link_id'    => ['label' => '링크아이디', 'config' => 'popbill.LinkID',
                             'help'  => '팝빌에서 발급한 연동 아이디'],
            'secret_key' => ['label' => '시크릿 키',  'config' => 'popbill.SecretKey', 'type' => 'password',
                             'width' => 3],
            'is_test'    => ['label' => '테스트 모드', 'config' => 'popbill.IsTest', 'type' => 'bool',
                             'help'  => '켜면 팝빌 테스트 서버로 나간다. 실제 발행·발송이 되지 않는다.'],
            'sms_simulate' => ['label' => '문자 시뮬레이션', 'config' => 'popbill.sms_simulate', 'type' => 'bool',
                             'help'  => '켜면 문자를 실제로 보내지 않고 보낸 것으로 처리한다.'],
            'ip_restrict' => ['label' => 'IP 제한',    'config' => 'popbill.IPRestrictOnOff', 'type' => 'bool'],
            'use_static_ip' => ['label' => '고정 IP',  'config' => 'popbill.UseStaticIP', 'type' => 'bool'],
            'test_corp_num'     => ['label' => '테스트 사업자번호', 'config' => 'popbill.test.corp_num'],
            'test_user_id'      => ['label' => '테스트 아이디',     'config' => 'popbill.test.user_id'],
            'test_cert_key'     => ['label' => '테스트 인증키',     'config' => 'popbill.test.cert_key', 'type' => 'password'],
            'test_sender_num'   => ['label' => '발신 번호',        'config' => 'popbill.test.sender_num'],
            'test_receiver_hp'  => ['label' => '테스트 수신 휴대폰', 'config' => 'popbill.test.receiver_hp'],
            'test_receiver_fax' => ['label' => '테스트 수신 팩스',  'config' => 'popbill.test.receiver_fax'],
        ],
    ],

    'toss' => [
        'label' => '토스페이먼츠',
        'desc'  => '결제 · 가상계좌 발급',
        'fields' => [
            'client_key' => ['label' => '클라이언트 키', 'config' => 'toss.client_key',
                             'help'  => '결제창을 여는 데 쓴다. 브라우저에 노출되는 값이다.', 'width' => 3],
            'secret_key' => ['label' => '시크릿 키',     'config' => 'toss.secret_key', 'type' => 'password',
                             'help'  => '서버에서 토스 API 를 부를 때 쓴다.', 'width' => 3],
            'webhook_secret' => ['label' => '웹훅 보안 키', 'config' => 'toss.webhook_secret', 'type' => 'password',
                             'help'  => '서명이 붙은 웹훅을 검증한다. 가상계좌 입금 웹훅은 서명이 없어 이 값이 비어도 입금 처리는 된다.',
                             'width' => 3],
            'test_mode'  => ['label' => '테스트 모드',   'config' => 'toss.test_mode', 'type' => 'bool',
                             'help'  => '끄면 실제 결제가 발생한다.'],
            'va_enabled' => ['label' => '가상계좌 발급', 'config' => 'toss.virtual_account_enabled', 'type' => 'bool',
                             'help'  => '끄면 토스를 부르지 않고 아래 대체 계좌로 문자만 보낸다.'],
            'va_bank'        => ['label' => '가상계좌 은행 코드', 'config' => 'toss.virtual_account.bank', 'width' => 1],
            'va_valid_hours' => ['label' => '입금 기한(시간)',    'config' => 'toss.virtual_account.valid_hours', 'type' => 'int', 'width' => 1],
            'va_fallback_bank'    => ['label' => '입금계좌 은행',   'config' => 'toss.virtual_account.fallback_bank',
                                      'help'  => '무통장입금 안내와 가상계좌 대체 안내에 함께 쓴다.'],
            'va_fallback_account' => ['label' => '입금계좌 번호', 'config' => 'toss.virtual_account.fallback_account',
                                      'help'  => '비워 두면 무통장입금 문자에 「담당자에게 문의」로 나간다.'],
        ],
    ],

    'order' => [
        'label' => '주문',
        'desc'  => '창고에 판매주문이 선 뒤 고객에게 보내는 결제 안내',
        'fields' => [
            'confirm_pay_method' => [
                'label'   => '주문 확정 안내',
                'config'  => 'order.confirm_pay_method',
                'type'    => 'select',
                'options' => [
                    'bank' => '무통장입금 (입금계좌를 문자로 안내)',
                    'card' => '링크페이 (토스페이먼츠 승인 후 사용)',
                ],
                'width'   => 3,
                'help'    => '위드웍스에 판매주문이 선 뒤 고객에게 이 방식으로 결제를 안내한다. '
                           . '링크페이는 토스페이먼츠 승인을 받은 뒤에 고른다 — 승인 전에는 키가 비어 있어 '
                           . '보낼 수 없으므로 무통장입금으로 내려가 보낸다.',
            ],
        ],
    ],

    'kakao' => [
        'label' => '카카오 알림톡',
        'desc'  => '알림톡 발송 채널·발신 정보',
        'fields' => [
            'api_key'      => ['label' => 'API 키',     'config' => 'kakao.api_key', 'type' => 'password', 'width' => 3],
            'sender_key'   => ['label' => '발신 프로필 키', 'config' => 'kakao.sender_key', 'type' => 'password', 'width' => 3],
            'user_id'      => ['label' => '사용자 아이디', 'config' => 'kakao.user_id'],
            'sender_phone' => ['label' => '발신 번호',   'config' => 'kakao.sender_phone'],
            'channel_id'   => ['label' => '채널 아이디', 'config' => 'kakao.channel_id', 'help' => '@채널명'],
            'channel_url'  => ['label' => '채널 주소',   'config' => 'kakao.channel_url', 'help' => 'https://pf.kakao.com/_xxxxx'],
            'test_mode'    => ['label' => '테스트 모드', 'config' => 'kakao.test_mode', 'type' => 'bool',
                               'help'  => '켜면 실제로 보내지 않는다.'],
        ],
    ],

    'nhis' => [
        'label' => '건강보험공단',
        'desc'  => '요양비 청구 기관 정보와 팩스 전송',
        'fields' => [
            'institution_name' => ['label' => '기관명',       'config' => 'nhis.institution.name'],
            'institution_code' => ['label' => '요양기관기호', 'config' => 'nhis.institution.code', 'width' => 1],
            'biz_no'           => ['label' => '사업자번호',   'config' => 'nhis.institution.biz_no', 'width' => 1],
            'efax_driver'      => ['label' => '팩스 방식',    'config' => 'nhis.efax.driver', 'type' => 'select',
                                   'options' => ['simulation' => '시뮬레이션(발송 안 함)', 'hifaxkorea' => '하이팩스코리아', 'efax' => 'eFax', 'popbill' => '팝빌'],
                                   'help'    => '시뮬레이션이면 팩스를 보내지 않고 보낸 것으로 기록한다.'],
            'efax_sender'      => ['label' => '팩스 발신번호', 'config' => 'nhis.efax.sender_number'],
            'nhis_fax_number'  => ['label' => '공단 수신 팩스', 'config' => 'nhis.efax.nhis_fax_number'],
            'hifax_api_url'    => ['label' => '하이팩스 API 주소',  'config' => 'nhis.efax.hifaxkorea.api_url', 'width' => 3],
            'hifax_api_key'    => ['label' => '하이팩스 API 키',    'config' => 'nhis.efax.hifaxkorea.api_key', 'type' => 'password'],
            'hifax_api_secret' => ['label' => '하이팩스 API 시크릿', 'config' => 'nhis.efax.hifaxkorea.api_secret', 'type' => 'password'],
            'efax_api_url'     => ['label' => 'eFax API 주소',   'config' => 'nhis.efax.efax.api_url', 'width' => 3],
            'efax_account_id'  => ['label' => 'eFax 계정',       'config' => 'nhis.efax.efax.account_id'],
            'efax_api_key'     => ['label' => 'eFax API 키',     'config' => 'nhis.efax.efax.api_key', 'type' => 'password'],
            'auto_submit'      => ['label' => '출고 시 자동 청구', 'config' => 'nhis.claim.auto_submit_on_delivery', 'type' => 'bool'],
        ],
    ],

    'aws' => [
        'label' => 'AWS Textract',
        'desc'  => '처방전 OCR 판독 (읽을 화면: 설정 › OCR 설정에서 판독기를 고른다)',
        'fields' => [
            'key'     => ['label' => '액세스 키',    'config' => 'ocr.textract.key', 'type' => 'password', 'width' => 3],
            'secret'  => ['label' => '시크릿 키',    'config' => 'ocr.textract.secret', 'type' => 'password', 'width' => 3],
            'region'  => ['label' => '리전',        'config' => 'ocr.textract.region', 'width' => 1],
            'enabled' => ['label' => 'Textract 사용', 'config' => 'ocr.textract.enabled', 'type' => 'bool'],
        ],
    ],

    // NICE 만 저장 위치가 다르다. 전용 테이블(nice_settings)이 이미 있고,
    // NiceIdentityService 가 호출 시점마다 NiceSetting::applyToConfig() 로
    // 값을 다시 덮어쓴다. 여기서 settings 테이블에 따로 담으면 그 호출에
    // 밀려 조용히 무시된다. 그래서 화면만 여기에 얹고 저장은 원래 테이블에 한다.
    'nice' => [
        'label' => '본인확인(NICE)',
        'desc'  => '위임동의 링크에서 휴대폰 본인확인. 자격증명 3개가 모두 채워져야 켜진다.',
        'model' => \App\Models\NiceSetting::class,
        // 이 묶음에는 「연결 테스트」 단추가 선다. 전용 화면(/settings/nice)에만 있던 것을
        // 여기서도 누를 수 있게 한다 — 그 화면은 메뉴에 없어 주소를 알아야 닿는다.
        // route 만 적으면 화면이 알아서 단추를 그린다. 다른 묶음도 같은 방법으로 붙일 수 있다.
        'test' => [
            'route' => 'nice-settings.test',
            'label' => '연결 테스트',
            'help'  => '저장된 자격증명으로 기관토큰ㆍ암호화토큰 발급까지만 확인합니다. '
                     . '표준창을 열지 않으므로 본인확인 건당 요금은 발생하지 않습니다. 값을 바꿨다면 먼저 저장하세요.',
        ],
        'fields' => [
            'client_id'     => ['label' => '클라이언트 ID', 'column' => 'client_id', 'width' => 3],
            'client_secret' => ['label' => '클라이언트 시크릿', 'column' => 'client_secret', 'type' => 'password', 'width' => 3],
            'product_id'    => ['label' => '상품 ID',       'column' => 'product_id'],
            'enforce'       => ['label' => '본인확인 필수', 'column' => 'enforce', 'type' => 'bool',
                                'help'  => '켜면 본인확인을 마쳐야 서명으로 넘어간다.'],
            'match_name'    => ['label' => '이름 일치 확인', 'column' => 'match_name', 'type' => 'bool'],
            'match_birth'   => ['label' => '생년월일 일치 확인', 'column' => 'match_birth', 'type' => 'bool'],
            'api_base'      => ['label' => 'API 주소',      'column' => 'api_base', 'width' => 3,
                                'help'  => '비워두면 기본값을 쓴다.'],
            'standard_url'  => ['label' => '표준창 주소',   'column' => 'standard_url', 'width' => 3,
                                'help'  => '비워두면 기본값을 쓴다.'],
        ],
    ],

    'mail' => [
        'label' => '메일(SMTP)',
        'desc'  => '관리자 초대 메일 발송',
        'fields' => [
            'host'         => ['label' => 'SMTP 주소', 'config' => 'mail.mailers.smtp.host'],
            'port'         => ['label' => '포트',      'config' => 'mail.mailers.smtp.port', 'type' => 'int', 'width' => 1],
            'scheme'       => ['label' => '보안',      'config' => 'mail.mailers.smtp.scheme', 'type' => 'select',
                               'options' => ['smtps' => 'SSL (smtps)', 'smtp' => '없음 / STARTTLS (smtp)'], 'width' => 1],
            'username'     => ['label' => '계정',      'config' => 'mail.mailers.smtp.username'],
            'password'     => ['label' => '비밀번호',  'config' => 'mail.mailers.smtp.password', 'type' => 'password'],
            'from_address' => ['label' => '보내는 주소', 'config' => 'mail.from.address'],
            'from_name'    => ['label' => '보내는 이름', 'config' => 'mail.from.name'],
        ],
    ],

    'ce_shop' => [
        'label' => 'CE샵',
        'desc'  => 'CE샵에서 넘어오는 주문을 받고, 배지 건수를 물어볼 때 쓴다',
        'fields' => [
            'base_url'       => ['label' => 'CE샵 주소',  'config' => 'services.ce_shop.base_url', 'width' => 3,
                                 'help' => '배지 건수를 물어볼 때 부르는 주소'],
            // 이 값으로 들어오는 주문의 진위를 가린다 — 틀리면 401 로 거절한다
            'webhook_secret' => ['label' => '웹훅 공유 비밀', 'config' => 'services.ce_shop.webhook_secret', 'type' => 'password',
                                 'help' => 'CE샵 쪽에 같은 값이 들어가야 한다'],
            'api_enabled'    => ['label' => 'CE샵 조회 사용', 'config' => 'services.ce_shop.api_enabled', 'type' => 'bool',
                                 'help' => '끄면 배지 건수를 우리 표에서 센다'],
        ],
    ],
    'login' => [
        'label' => '로그인',
        'desc'  => '관리자 화면과 모바일 앱의 로그인 2차 인증',
        'fields' => [
            // 끈다면 이메일·비밀번호만으로 들어온다. 웹과 앱이 같은 값을 본다.
            'otp_enabled' => ['label' => '문자 인증 사용', 'config' => 'auth.otp_enabled', 'type' => 'bool',
                              'help'  => '켜면 비밀번호 뒤에 문자로 받은 인증번호를 한 번 더 넣어야 한다. 휴대폰 번호가 없는 계정은 로그인할 수 없다.'],
        ],
    ],
];
