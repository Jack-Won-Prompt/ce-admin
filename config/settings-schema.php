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
            'ip_restrict' => ['label' => 'IP 제한',    'config' => 'popbill.IPRestrictOnOff', 'type' => 'bool'],
            'use_static_ip' => ['label' => '고정 IP',  'config' => 'popbill.UseStaticIP', 'type' => 'bool'],
            'test_corp_num'     => ['label' => '테스트 사업자번호', 'config' => 'popbill.test.corp_num'],
            'test_user_id'      => ['label' => '테스트 아이디',     'config' => 'popbill.test.user_id'],
            'test_cert_key'     => ['label' => '테스트 인증키',     'config' => 'popbill.test.cert_key', 'type' => 'password'],
            'test_sender_num'   => ['label' => '발신 번호',        'config' => 'popbill.test.sender_num'],

            /* 걷어낸 것 — 문자 시뮬레이션 (2026-09-07 지시).
               문자를 실제로 가르는 것은 「테스트 설정」의 문자 발송(실제ㆍ우리에게만ㆍ
               시늉)이다. 이 스위치가 보던 sms_simulate 는 그 값이 없을 때만 보는 옛
               열쇠라, 지금은 켜도 문자가 그대로 나갔다 — 아무 일도 하지 않는 설정은
               거짓말을 한다. 하이팩스를 걷어낸 것과 같은 자리다.

               걷어낸 것 — 테스트 수신 휴대폰ㆍ테스트 수신 팩스 (2026-09-07 지시).
               같은 값을 「테스트 설정」 탭이 이미 들고 있다. 한 값을 두 화면이 각각
               세우면 어느 쪽에서 고쳤는지에 따라 서로 덮고, 고친 사람은 저장이 된
               줄 안다 — 실제로 그 일이 났다. 받는 곳을 정하는 자리는 그 곳을 쓸지
               말지를 정하는 자리(문자·팩스 발송 세 갈래) 옆이어야 한다. */
        ],
    ],

    'toss' => [
        'label' => '토스페이먼츠',
        'desc'  => '결제 · 가상계좌 발급',
        'fields' => [
            /* 두 벌을 다 담아 두고 이 한 칸으로 고른다. 예전에는 키를 갈아 끼웠는데,
               바꾸는 사람이 「테스트 모드」를 함께 끄는 것을 잊었다. */
            'env' => ['label' => '사용 환경', 'config' => 'toss.env', 'type' => 'select',
                      'options' => ['test' => '테스트 (실제 결제 없음)', 'live' => '운영 (실제 결제)'],
                      'help'  => '고른 쪽의 키로 돈다. 테스트 모드도 이 값이 정한다.', 'width' => 3],

            'test_client_key' => ['label' => '테스트 클라이언트 키', 'config' => 'toss.test.client_key',
                             'help'  => 'test_ck_ 로 시작한다. 결제창을 여는 데 쓰며 브라우저에 노출된다.', 'width' => 3],
            'test_secret_key' => ['label' => '테스트 시크릿 키',     'config' => 'toss.test.secret_key', 'type' => 'password',
                             'help'  => 'test_sk_ 로 시작한다. 서버에서 토스 API 를 부를 때 쓴다.', 'width' => 3],

            'live_client_key' => ['label' => '운영 클라이언트 키', 'config' => 'toss.live.client_key',
                             'help'  => 'live_ck_ 로 시작한다.', 'width' => 3],
            'live_secret_key' => ['label' => '운영 시크릿 키',     'config' => 'toss.live.secret_key', 'type' => 'password',
                             'help'  => 'live_sk_ 로 시작한다. 이 키로 돌면 실제 결제가 일어난다.', 'width' => 3],

            'webhook_secret' => ['label' => '웹훅 보안 키', 'config' => 'toss.webhook_secret', 'type' => 'password',
                             'help'  => '서명이 붙은 웹훅을 검증한다. 가상계좌 입금 웹훅은 서명이 없어 이 값이 비어도 입금 처리는 된다.',
                             'width' => 3],
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
            'nhis_fax_on_consent' => [
                'label'  => '동의 완료에 공단 팩스 발송',
                'config' => 'order.nhis_fax_on_consent',
                'type'   => 'bool',
                'help'   => '동의가 끝나면 등록신청서ㆍ결과지ㆍ요양비위임장ㆍ신분증을 관할 지사로 보낸다. '
                          . '하나라도 빠졌으면 보내지 않고 담당자에게 알린다 — 팩스 창에서 손으로 보내면 된다.',
            ],
            'nhis_fax_on_id_card' => [
                'label'  => '신분증이 들어오면 공단 팩스 다시 시도',
                'config' => 'order.nhis_fax_on_id_card',
                'type'   => 'bool',
                'help'   => '위 설정은 동의가 끝나는 그때만 잰다 — 그때 신분증이 없으면 걸리고, 뒤에 '
                          . '신분증이 들어와도 다시 재지 않았다. 이것을 켜면 신분증 링크로 받거나 첨부로 '
                          . '올릴 때 한 번 더 잰다. 이미 보낸 건은 다시 보내지 않는다.',
            ],
            'rx_received_sms_on_first_save' => [
                'label'  => '첫 저장에 접수 안내 발송',
                'config' => 'order.rx_received_sms_on_first_save',
                'type'   => 'bool',
                'help'   => '「처방전이 접수되었습니다」를 보낸다. 위임동의 링크와는 다른 통이다 — '
                          . '동의를 이미 받아 둔 사람에게는 링크가 가지 않아, 합쳐 두면 접수됐다는 것조차 듣지 못한다.',
            ],
            'consent_sms_on_first_save' => [
                'label'  => '첫 저장에 위임동의 발송',
                'config' => 'order.consent_sms_on_first_save',
                'type'   => 'bool',
                'help'   => '주문 등록에서 처음 저장해 처방전이 사람에게 붙는 그때 서명 SMS 를 함께 보낸다. '
                          . '이미 동의를 받았거나 살아 있는 링크가 있으면 보내지 않는다. 손으로 보내는 단추는 그대로다.',
            ],
            'consent_sms_hours' => [
                'label'  => '위임동의 발송 시간',
                'config' => 'order.consent_sms_hours',
                'width'  => 3,
                'help'   => '이 시간 밖에 저장하면 보내지 않고 화면에 알린다 — 서명 링크가 30분만 열려, '
                          . '밤에 보내면 환자가 아침에 열어 이미 만료다. 예: 09:00-20:00 · 비우면 가리지 않는다.',
            ],
            'ship_notice_on_shipped' => [
                'label'  => '출고되면 배송 안내 발송',
                'config' => 'order.ship_notice_on_shipped',
                'type'   => 'bool',
                'help'   => '창고가 출고했다고 알려 오면(so.shipped) 운송장 번호를 담아 환자에게 문자를 보낸다. '
                          . '문구는 메시지 유형 ▸ SMS ▸ 배송 시작 에서 고친다. 한 건에 한 번만 나간다.',
            ],
        ],
    ],

    'returns' => [
        'label' => '교환·반품',
        'desc'  => '「입고일로부터 2영업일 이내 검수 · 3영업일 이내 출고」를 재는 기준',
        'fields' => [
            'inspect_days' => [
                'label'  => '검수 기한 (영업일)',
                'config' => 'returns.inspect_days',
                'type'   => 'int',
                'width'  => 1,
                'help'   => '3PL 창고에 물건이 들어온 날부터 센다. 접수일이 아니다.',
            ],
            'ship_days' => [
                'label'  => '출고·발행 기한 (영업일)',
                'config' => 'returns.ship_days',
                'type'   => 'int',
                'width'  => 1,
                'help'   => '교환은 재발송까지, 반품은 마이너스 발행까지의 기한이다.',
            ],
            'holidays' => [
                'label'  => '쉬는 날',
                'config' => 'returns.holidays',
                'type'   => 'textarea',
                'width'  => 3,
                'help'   => 'YYYY-MM-DD 를 쉼표나 줄바꿈으로 나눠 적는다. 토·일은 적지 않아도 쉰다. '
                          . '대체공휴일·임시공휴일은 해마다 관보로 정해지므로 그해 것을 확인해 고쳐 넣는다.',
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
            /* 알림톡과는 다른 열쇠다 — 카카오 개발자센터의 REST API 키.
               관할 청구처 찾기가 주소로 행정동과 행정복지센터를 물을 때 쓴다. */
            'local_rest_key' => ['label' => '로컬 REST 키', 'config' => 'services.kakao_local.rest_key',
                                 'type' => 'password', 'width' => 3,
                                 'help' => '관할 청구처 찾기의 행정복지센터 조회에 쓴다(카카오 개발자센터 REST API 키).'],
            'test_mode'    => ['label' => '테스트 모드', 'config' => 'kakao.test_mode', 'type' => 'bool',
                               'help'  => '켜면 실제로 보내지 않는다.'],
        ],
    ],

    /*
     * 팩스 관련 여섯 칸을 걷어냈다 (2026-09-07).
     *
     * 「팩스 방식」(시뮬레이션ㆍ하이팩스코리아ㆍeFaxㆍ팝빌)과 그에 딸린 주소ㆍ키가
     * 서 있었는데, **어느 것도 코드가 읽지 않았다.** 공단 팩스를 어느 업체로 보낼지
     * 정하기 전에 자리만 미리 만들어 둔 것이고, 그 뒤 팝빌로 정해져
     * App\Services\Popbill\FaxService 가 유일한 길이 되었다.
     *
     * 아무 일도 하지 않는 칸은 거짓말을 한다. 「팩스 방식」을 시뮬레이션으로 두고
     * 안심한 채 시험을 돌리면 공단으로 진짜 팩스가 나간다 — 실제로 그 값이
     * simulation 인 채로 실전송되어 접수번호가 돌아왔다.
     *
     * 팩스를 실제로 가르는 스위치는 「테스트 설정」 탭의 fax_mode 다.
     *
     * 「출고 시 자동 청구」도 함께 걷었다 — 읽는 코드가 없다. 청구는 담당자가
     * 청구 관리에서 손으로 한다.
     */
    'nhis' => [
        'label' => '건강보험공단',
        'desc'  => '요양비 청구 서류에 찍히는 기관 정보 (팩스는 「테스트 설정」 탭에서 가른다)',
        'fields' => [
            'institution_name' => ['label' => '기관명',       'config' => 'nhis.institution.name',
                                   'help'  => '요양비위임장ㆍ공단 팩스 표지에 찍힌다.'],
            'institution_code' => ['label' => '요양기관기호', 'config' => 'nhis.institution.code', 'width' => 1,
                                   'help'  => '같은 서류에 찍힌다.'],
            'biz_no'           => ['label' => '사업자번호',   'config' => 'nhis.institution.biz_no', 'width' => 1,
                                   'help'  => '공단 청구 화면의 예금주 사업자번호로 쓴다.'],
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

            /* 아이디ㆍ비밀번호 길을 웹과 앱에서 따로 여닫는다. 한쪽만 SSO 로
               옮겨 가는 동안 다른 쪽은 그대로 써야 하기 때문이다. */
            'password_web' => ['label' => '웹 — 아이디·비밀번호 사용', 'config' => 'auth.password_login.web', 'type' => 'bool',
                               'help'  => '끄면 관리자 화면에서 아이디·비밀번호 칸이 사라지고 Microsoft 계정만 남는다. ⚠ SSO 가 아직 동작하지 않으므로 끄면 아무도 들어올 수 없다.'],
            'password_app' => ['label' => '앱 — 아이디·비밀번호 사용', 'config' => 'auth.password_login.app', 'type' => 'bool',
                               'help'  => '끄면 모바일 앱에서 아이디·비밀번호 칸이 사라진다. ⚠ 위와 같은 까닭으로, 지금 끄면 앱으로 들어올 수 없다.'],
        ],
    ],
    'mobile' => [
        'label' => '모바일 앱',
        'desc'  => '앱이 스스로 새 판을 확인해 알린다',
        'fields' => [
            'latest_version' => ['label' => '최신 판', 'config' => 'mobile.latest_version', 'width' => 1,
                                 'help'  => '스토어에 올린 판(예: 1.1.0). 이보다 낮은 판에게 새 판이 있다고 알린다. 비우면 알리지 않는다.'],
            'min_version'    => ['label' => '최소 판', 'config' => 'mobile.min_version', 'width' => 1,
                                 'help'  => '이 판보다 낮으면 쓸 수 없다 — 넘길 수 없는 안내가 뜬다. 서버와 주고받는 약속이 바뀌었을 때만 올린다.'],
            'store_url'      => ['label' => '스토어 주소', 'config' => 'mobile.store_url', 'width' => 3],
            'notice'         => ['label' => '안내 문구', 'config' => 'mobile.notice', 'type' => 'textarea', 'width' => 3,
                                 'help'  => '새 판에서 무엇이 달라졌는지 한두 줄. 비우면 기본 문구가 나간다.'],
        ],
    ],
    /**
     * 웹 화면이 지금 시험 중인가, 운영 중인가.
     *
     * 시험할 때는 화면에 적는 전화번호가 곧 문자가 가는 곳이다. 손으로 치다
     * 한 자만 틀려도 남의 전화로 안내가 가고, 맞게 쳐도 시험 문자가 실제
     * 환자에게 간다. 그래서 시험 중에는 **CE Admin 에 등록된 사람의 번호**
     * 가운데서 고르게 한다 — 문자가 우리 손 안에서만 돈다.
     *
     * 운영에서는 그대로 손으로 적는다. 환자 번호는 목록에 있을 까닭이 없다.
     */
    'web' => [
        'label' => '테스트 설정',
        'desc'  => '테스트 중에 무엇이 밖으로 나가고 무엇이 우리 손 안에 머무는가',
        'fields' => [
            'mode' => ['label' => '웹 화면', 'config' => 'web.mode', 'type' => 'select', 'width' => 1,
                       'options' => ['live' => '운영 (번호를 손으로 적는다)',
                                     'test' => '테스트 (등록된 사람 번호에서 고른다)'],
                       'help'  => '테스트로 두면 주문 등록 화면의 휴대폰 칸이 고르는 칸으로 바뀐다. '
                                . '문자ㆍ알림톡이 실제 환자에게 가지 않도록 우리 사람 번호만 세운다.'],

            /* 여태 시늉이냐 아니냐 둘뿐이라 「정말 나가는지」를 볼 길이 없었다.
               가운데를 둔다 — 실제로 보내되 우리에게만 온다. */
            'sms_mode' => ['label' => '문자 발송', 'config' => 'popbill.sms_mode', 'type' => 'select', 'width' => 1,
                           'options' => ['live'     => '실제 (적힌 번호로)',
                                         'redirect' => '우리에게만 (테스트 번호로 돌린다)',
                                         'simulate' => '시늉 (보내지 않는다)'],
                           'help'  => '「우리에게만」은 정말 팝빌로 나간다 — 받는 곳이 선택한 테스트 번호로 바뀐다.'],

            'fax_mode' => ['label' => '팩스 발송', 'config' => 'popbill.fax_mode', 'type' => 'select', 'width' => 1,
                           'options' => ['live'     => '실제 (관할 지사로)',
                                         'redirect' => '우리에게만 (테스트 팩스로 돌린다)',
                                         'simulate' => '시늉 (보내지 않는다)'],
                           'help'  => '여태 이 칸이 없어, 테스트할 때마다 기준정보의 팩스번호를 바꿔 두었다. '
                                    . '되돌리기를 잊으면 운영에서 공단으로 팩스가 안 간다 — 그 일을 없앤다.'],

            /* 받는 곳은 여기 한 곳에서만 고친다. 팝빌 탭에도 같은 칸이 서 있었는데,
               한 값을 두 화면이 각각 세우니 서로 덮었다(2026-09-07 걷어냄). */
            'test_receiver_hp'  => ['label' => '테스트 받는 번호', 'config' => 'popbill.test.receiver_hp', 'width' => 1,
                                    'help'  => '「우리에게만」일 때 문자가 오는 곳. 비어 있으면 발송이 막힌다.'],
            'test_receiver_fax' => ['label' => '테스트 받는 팩스', 'config' => 'popbill.test.receiver_fax', 'width' => 1,
                                    'help'  => '「우리에게만」일 때 팩스가 오는 곳. 비어 있으면 발송이 막힌다.'],

            /* 연계가 잘 갔는지는 저쪽 화면에 들어가 눈으로 찾아야 안다. 무엇을 어떤
               값으로 보냈는지는 로그에만 남고, 로그는 서버에 들어가야 읽는다. */
            'withworks_email' => ['label' => '위드웍스 연계 확인 이메일', 'config' => 'web.withworks_email', 'width' => 3,
                                  'help'  => '위드웍스로 주문ㆍ반품을 보낼 때 그 내용을 이 주소로도 보낸다. '
                                           . '저쪽 화면과 나란히 놓고 견줄 수 있다. 비우면 보내지 않는다.'],

            /* 본인확인은 여태 .env 로만 있어, 운영 전환 뒤 되돌렸는지 화면에서 볼 수 없었다. */
            'nice_simulate' => ['label' => '본인확인 시늉(NICE)', 'config' => 'nice.simulate', 'type' => 'bool',
                                'help'  => '켜면 위임동의 화면의 본인확인이 실제 인증 없이 「확인됨 (테스트)」으로 넘어간다. '
                                         . '운영에서는 반드시 꺼 두어야 한다.'],
        ],
    ],
];
