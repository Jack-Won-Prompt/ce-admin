<?php
// config/permissions.php
//
// 권한 체계의 단일 진실 공급원(single source of truth).
// 사이드바 메뉴 · 권한 그룹 관리 화면 · CheckPagePermission 미들웨어가 모두 이 파일만 읽는다.
// 페이지를 추가하려면 여기 한 곳만 고치면 메뉴 필터·권한 매트릭스·라우트 차단이 함께 따라온다.

return [

    /*
    |──────────────────────────────────────────────────────────────
    | 액션 5종
    |──────────────────────────────────────────────────────────────
    | send = 발송·발행 (SMS·카카오·팩스·세금계산서·현금영수증·NHIS 청구 등
    |        외부로 나가거나 취소가 어려운 동작). 등록/수정과 분리해 따로 통제한다.
    */
    'actions' => [
        'view'   => '조회',
        'create' => '등록',
        'update' => '수정',
        'delete' => '삭제',
        'send'   => '발송·발행',
    ],

    /*
    |──────────────────────────────────────────────────────────────
    | 메뉴 그룹 (사이드바 그룹 키와 동일해야 함)
    |──────────────────────────────────────────────────────────────
    */
    'groups' => [
        'main'     => '메인',
        'patient'  => '환자 · 처방',
        'order'    => '주문 · 재구매',
        'docs'     => '서류 · 동의',
        'billing'  => '청구 · 회계',
        'dispatch' => '발송 · 내역',
        'support'  => '지원',
        'settings' => '설정',
    ],

    /*
    |──────────────────────────────────────────────────────────────
    | 페이지
    |──────────────────────────────────────────────────────────────
    | routes  : 이 페이지에 속하는 라우트명. '이름.' 접두사로 매칭된다.
    |           exact 를 지정하면 그 라우트명만 정확히 매칭한다(접두사가 겹칠 때 우선).
    | actions : 이 페이지가 지원하는 액션. 권한 매트릭스에 이 액션만 노출된다.
    | admin_only : true 면 role=admin 만 접근(그룹 권한과 무관). 권한 관리 화면 잠김 방지용.
    */
    'pages' => [

        'dashboard' => [
            'label'   => '대시보드',
            'group'   => 'main',
            'routes'  => ['dashboard'],
            'actions' => ['view'],
        ],

        // ── 환자 · 처방 ──────────────────────────────────────────
        'patients' => [
            'label'   => '환자관리',
            'group'   => 'patient',
            'routes'  => ['patients'],
            'actions' => ['view', 'create', 'update', 'delete'],
        ],
        'prescription-upload' => [
            'label'   => '처방전 업로드',
            'group'   => 'patient',
            // prescriptions. 접두사와 겹치므로 정확히 일치하는 라우트만 이 페이지로 본다
            'exact'   => [
                'prescriptions.upload',
                'prescriptions.store',
                'prescriptions.analyze',
                'prescriptions.confirmUpload',
            ],
            'actions' => ['view', 'create'],
        ],
        'prescriptions' => [
            'label'   => '처방전 목록 · 검수',
            'group'   => 'patient',
            'routes'  => ['prescriptions'],
            'actions' => ['view', 'create', 'update', 'delete', 'send'],
        ],

        // ── 주문 · 재구매 ────────────────────────────────────────
        'orders' => [
            'label'   => '주문관리',
            'group'   => 'order',
            'routes'  => ['orders'],
            'actions' => ['view', 'create', 'update', 'delete', 'send'],
        ],
        'repurchase' => [
            'label'   => '재구매 관리',
            'group'   => 'order',
            'routes'  => ['repurchase'],
            'actions' => ['view'],
        ],
        'shop-orders' => [
            'label'   => 'CE샵 주문',
            'group'   => 'order',
            'routes'  => ['shop-orders'],
            'actions' => ['view', 'update'],
        ],

        // ── 서류 · 동의 ──────────────────────────────────────────
        'documents' => [
            'label'   => '서류 관리',
            'group'   => 'docs',
            'routes'  => ['documents'],
            'actions' => ['view', 'create'],
        ],
        'privacy-consents' => [
            'label'   => '개인정보동의',
            'group'   => 'docs',
            'routes'  => ['privacy-consents'],
            'actions' => ['view'],
        ],

        // ── 청구 · 회계 ──────────────────────────────────────────
        'nhis' => [
            'label'   => 'NHIS 청구',
            'group'   => 'billing',
            'routes'  => ['nhis'],
            'actions' => ['view', 'update', 'send'],
        ],
        'invoice' => [
            'label'   => '계산서 발행',
            'group'   => 'billing',
            'routes'  => ['invoice'],
            'actions' => ['view', 'send', 'delete'],
        ],
        'settlement' => [
            'label'   => '정산/회계',
            'group'   => 'billing',
            'routes'  => ['settlement'],
            'actions' => ['view', 'send'],
        ],
        'taxinvoice' => [
            'label'   => '전자세금계산서',
            'group'   => 'billing',
            'routes'  => ['taxinvoice'],
            'actions' => ['view'],
        ],
        'cashbill' => [
            'label'   => '현금영수증',
            'group'   => 'billing',
            'routes'  => ['cashbill'],
            'actions' => ['view'],
        ],

        // ── 발송 · 내역 ──────────────────────────────────────────
        'fax' => [
            'label'   => '팩스 발송',
            'group'   => 'dispatch',
            'routes'  => ['fax'],
            'actions' => ['view'],
        ],
        'dispatch' => [
            'label'   => '발송/발행 내역',
            'group'   => 'dispatch',
            'routes'  => ['dispatch'],
            'actions' => ['view'],
        ],

        // ── 지원 ────────────────────────────────────────────────
        'institutional-notices' => [
            'label'   => '기관 공지사항',
            'group'   => 'support',
            'routes'  => ['institutional-notices'],
            'actions' => ['view'],
        ],
        'notices' => [
            'label'   => '공지사항',
            'group'   => 'support',
            'routes'  => ['notices'],
            'actions' => ['view', 'create', 'update', 'delete'],
        ],
        'inquiries' => [
            'label'   => '문의하기',
            'group'   => 'support',
            'routes'  => ['inquiries'],
            'actions' => ['view', 'create', 'update', 'delete'],
        ],
        'service-requests' => [
            'label'   => 'SR 관리',
            'group'   => 'support',
            'routes'  => ['sr'],
            // update = 답변·상태 변경 권한
            'actions' => ['view', 'create', 'update', 'delete'],
        ],

        // ── 설정 ────────────────────────────────────────────────
        'admin-users' => [
            'label'   => '관리자 관리',
            'group'   => 'settings',
            'routes'  => ['admin.users'],
            'actions' => ['view', 'create', 'update', 'delete', 'send'],
        ],
        'permission-groups' => [
            'label'      => '권한 그룹',
            'group'      => 'settings',
            'routes'     => ['permission-groups'],
            'actions'    => ['view'],
            // 권한 설정을 잘못 저장해 아무도 되돌릴 수 없게 되는 사고를 막는다
            'admin_only' => true,
        ],
        'delegation-settings' => [
            'label'   => '위임장 설정',
            'group'   => 'settings',
            'routes'  => ['delegation-settings'],
            'actions' => ['view', 'update'],
        ],
        'ocr-settings' => [
            'label'   => 'OCR 설정',
            'group'   => 'settings',
            'routes'  => ['ocr-settings'],
            'actions' => ['view', 'update'],
        ],
        'nice-settings' => [
            'label'   => '본인확인 설정',
            'group'   => 'settings',
            'routes'  => ['nice-settings'],
            'actions' => ['view', 'update'],
        ],
        // 외부 서비스 키를 다루는 화면이라 관리자만 연다.
        'service-settings' => [
            'label'      => '서비스 연동 설정',
            'group'      => 'settings',
            'routes'     => ['service-settings'],
            'actions'    => ['view', 'update'],
            'admin_only' => true,
        ],
    ],

    /*
    |──────────────────────────────────────────────────────────────
    | 액션 override
    |──────────────────────────────────────────────────────────────
    | 기본 추론은 HTTP 메서드 기반(GET=view, POST=create, PUT/PATCH=update, DELETE=delete).
    | 그 추론이 실제 의미와 다른 라우트만 여기에 적는다. 형식: 라우트명 => [페이지, 액션]
    | 페이지를 null 로 두면 라우트명으로 찾은 페이지를 그대로 쓰고 액션만 바꾼다.
    */
    'overrides' => [
        // POST 지만 실제로는 조회/조립일 뿐인 것
        'nice-settings.test'          => [null, 'view'],
        'orders.fetchWithworksStatus' => [null, 'view'],

        // POST 지만 기존 레코드를 고치는 것
        // 빈 검수·등록 화면 열기 — GET 이지만 초안 레코드를 만드므로 등록 권한으로 본다
        'prescriptions.create'        => [null, 'create'],
        'prescriptions.reanalyze'     => [null, 'update'],
        'prescriptions.approve'       => [null, 'update'],
        'prescriptions.reject'        => [null, 'update'],
        'orders.updateStatus'         => [null, 'update'],
        'orders.updateTracking'       => [null, 'update'],
        'shop-orders.updateStatus'    => [null, 'update'],
        'shop-orders.updateMemo'      => [null, 'update'],
        'nhis.recordResult'           => [null, 'update'],

        // 외부로 나가는 발송·발행
        'prescriptions.smsSend'               => [null, 'send'],
        'prescriptions.kakaoSend'             => [null, 'send'],
        'prescriptions.faxSend'               => [null, 'send'],
        'prescriptions.consentSms'            => [null, 'send'],
        'prescriptions.faxRegenerate'         => [null, 'send'],
        'prescriptions.delegationRegenerate'  => [null, 'send'],
        'prescriptions.withworksOrder'        => [null, 'send'],
        'orders.submitNhis'                   => [null, 'send'],
        'nhis.sendFax'                        => [null, 'send'],
        'nhis.bulkSend'                       => [null, 'send'],
        'settlement.issue-va'                 => [null, 'send'],
        'settlement.resend-va-sms'            => [null, 'send'],
        'admin.users.invite'                  => ['admin-users', 'send'],
        'admin.users.invitations.resend'      => ['admin-users', 'send'],

        // 계산서·현금영수증 발행/취소는 주문 상세와 '계산서 발행' 화면 양쪽에서 호출되지만,
        // 라우트는 orders.* 다. 발행 권한은 '계산서 발행' 페이지에서 통제하는 게 맞으므로
        // invoice 페이지로 넘긴다.
        'orders.issueTaxInvoice'   => ['invoice', 'send'],
        'orders.issueCashReceipt'  => ['invoice', 'send'],
        'orders.cancelTaxInvoice'  => ['invoice', 'delete'],
        'orders.cancelCashReceipt' => ['invoice', 'delete'],
    ],

    /*
    |──────────────────────────────────────────────────────────────
    | 페이지에 속하지 않는 라우트 (미들웨어 통과)
    |──────────────────────────────────────────────────────────────
    | 위 pages 의 어느 routes/exact 에도 걸리지 않는 라우트는 통과시킨다.
    | 화면이 아니라 앱 전체가 공용으로 쓰는 유틸리티이기 때문이다:
    |   chat.*        사내 채팅 (사이드 패널, 모든 화면 공용)
    |   tour.*        화면 투어 완료 표시
    |   panel.*       레이아웃 내장 알림·문의 패널
    |   api.*, sso.*  외부/모바일 API (자체 인증)
    |   toss.*        결제 웹훅 (인증 없음)
    |   products.*    제품 검색 프록시 (검수·주문 화면 공용)
    |   database.*     운영 도구 (별도 제한)
    |   login.*, logout, privacy.*, consent.* 비로그인 공개 경로
    | 여기 나열은 문서용이며 코드가 이 목록을 읽지는 않는다(정책은 '매칭 안 되면 통과').
    */
    'unscoped_documented' => [
        // 화면 공용 유틸리티
        'chat', 'tour', 'panel', 'products',
        // 외부/모바일 API·웹훅 (자체 인증)
        'api', 'sso', 'toss', 'sanctum',
        // 운영 도구 (별도 제한)
        'database', 'user-logs', 'shop-monitoring',
        // 비로그인 공개 경로
        'login', 'logout', 'privacy', 'consent', 'welcome', 'admin.invite',
        // 프레임워크 제공
        'storage',
        // 메뉴가 아닌 셸 화면
        'workspace',
    ],
];
