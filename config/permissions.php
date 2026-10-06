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
        // 처방전 검수 완료ㆍ반려, 교환·반품의 검수 확정ㆍ전자 승인.
        // 적는 사람(update)과 승인하는 사람을 가르려고 따로 둔다.
        'approve' => '검수 완료 · 전자 승인',

        /* 교환ㆍ반품의 두 걸음 결재 (2026-09-28 지시).

           창고가 입고 검수를 마치고 승인을 청하면 **책임자**가 보고 승인ㆍ반려하고,
           승인된 건은 **최종승인자**가 서명한다. 서명이 곧 실행이라 — 돈이 나가거나
           고객에게 청구가 간다 — 두 사람을 갈라야 한다. 여태 approve 하나로 묶여
           있어 한 사람이 두 걸음을 다 누를 수 있었다.

           쓰는 페이지는 교환/반품/취소 하나뿐이다. 권한 매트릭스는 그 페이지가 쓰지
           않는 동작을 「—」로 그리므로 다른 페이지는 손댈 것이 없다. */
        'inspect_approve' => '책임자 검수 승인 · 반려',
        'final_approve'   => '최종승인자 서명',
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
        'billing'  => '청구 · 회계',
        'docs'     => '서류 · 동의',
        'dispatch' => '발송 · 내역',
        'opdata'   => '운영 데이터',
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

        /* 운영 데이터 › 위임장 서명 (2026-09-11 지시).

           기존 처방ㆍ주문ㆍ거래처와 잇지 않는 별도 기능이다. 보내는 일은 밖으로
           나가는 문자라 send 로 따로 통제한다. */
        'delegation-signs' => [
            'label'   => '위임장 서명',
            'group'   => 'opdata',
            'routes'  => ['delegation-signs'],
            'actions' => ['view', 'create', 'send', 'delete'],
        ],
        /* 위드웍스에서 옮겨 담은 자료 — 보기만 한다 (2026-09-18 지시) */
        'ww-prescriptions' => [
            'label'   => '처방전 정보',
            'group'   => 'opdata',
            'routes'  => ['ww-data.prescriptions'],
            'actions' => ['view'],
        ],
        'ww-customers' => [
            'label'   => '고객 정보',
            'group'   => 'opdata',
            'routes'  => ['ww-data.customers', 'ww-data.addresses'],
            'actions' => ['view'],
        ],


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
            // 메뉴에서는 「주문」(처방전을 열어 주문을 만드는 화면)과 「처방전 목록」 두 자리로
            // 나뉘어 있으나 같은 라우트 묶음이라 권한은 하나로 준다.
            'label'   => '주문 · 처방전 목록',
            'group'   => 'patient',
            'routes'  => ['prescriptions'],
            // approve = 「검수 완료」. 담당자는 적고 요청까지(update), 완료는 검수자만 누른다.
            'actions' => ['view', 'create', 'update', 'delete', 'send', 'approve'],
        ],

        // ── 주문 · 재구매 ────────────────────────────────────────
        'orders' => [
            'label'   => '주문관리',
            'group'   => 'order',
            'routes'  => ['orders'],
            'actions' => ['view', 'create', 'update', 'delete', 'send'],
        ],
        'order-returns' => [
            'label'   => '교환/반품/취소',
            'group'   => 'order',
            'routes'  => ['order-returns'],
            // 단계를 옮기는 것은 POST 라 create 로 추론된다 — 아래 overrides 에서 바로잡는다
            /* approve = 「검수 확정」ㆍ「전자 승인」. 절차서가 승인자를 따로 두라고 한다.

               2026-09-28 부터 그 둘을 두 사람으로 가른다 —
                 inspect_approve  「검수 확정」 = 책임자 검수 승인ㆍ반려
                 final_approve    「전자 승인」 = 최종승인자 서명

               approve 는 **거두지 않는다.** 지금 그것만 가진 사람이 갑자기 아무것도
               누르지 못하면 안 된다. 둘 중 하나라도 있으면 통과하고, 권한을 나눠
               부여한 뒤에 approve 를 거두면 된다. */
            'actions' => ['view', 'create', 'update', 'delete', 'send', 'approve',
                          'inspect_approve', 'final_approve'],
        ],
        'sample-orders' => [
            'label'   => 'CE 샘플주문',
            'group'   => 'order',
            'routes'  => ['sample-orders'],
            'actions' => ['view', 'create', 'update', 'delete'],
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
        'prescription-consents' => [
            'label'   => '위임장 서명',
            'group'   => 'docs',
            'routes'  => ['prescription-consents'],
            'actions' => ['view', 'send'],   // send = 위임동의 SMS 발송
        ],
        'privacy-consents' => [
            'label'   => '개인정보동의',
            'group'   => 'docs',
            'routes'  => ['privacy-consents'],
            'actions' => ['view'],
        ],

        // ── 청구 · 회계 ──────────────────────────────────────────
        /* 통장에 무엇이 들어왔는가(요청서 5쪽). 정산/회계와 따로 둔다 — 그쪽은 「얼마를
           받아야 하는가」이고 이쪽은 「무엇이 들어왔는가」다. 맞추는 일이 그 사이에 있다. */
        'deposits' => [
            'label'   => '입금 내역',
            'group'   => 'billing',
            'routes'  => ['deposits'],
            'actions' => ['view', 'update'],
        ],
        /* 한 주문에 두 번 들어온 돈을 찾아 돌려준다 (2026-10-02 지시).

           환불은 되돌릴 수 없고 곧 돈이 나가는 일이라 **요청과 승인을 나눈다** —
           create 로 올리고 final_approve 로 승인한다. 한 사람에게 둘 다 주면
           결재가 뜻을 잃는다(반품 결재와 같은 결). */
        'duplicate-payments' => [
            'label'   => '중복 결제',
            'group'   => 'billing',
            'routes'  => ['duplicate-payments'],
            'actions' => ['view', 'create', 'final_approve'],
        ],
        /* 재무가 보는 여섯 목록(요청서 14~19쪽) — 통합주문ㆍ환자결제ㆍ공단지자체ㆍ
           미정산ㆍ반품환불ㆍ부가세신고. 보고 내려받는 자리라 손댈 것이 없다. */
        'finance' => [
            'label'   => 'Finance',
            'group'   => 'billing',
            'routes'  => ['finance'],
            'actions' => ['view'],
        ],
        /* 토스페이먼츠를 거친 결제(요청서 7쪽). 입금 내역과 다른 것을 본다 —
           그쪽은 통장에 찍힌 줄이고 이쪽은 PG 를 거친 결제다. */
        'payments' => [
            'label'   => 'PG 결제',
            'group'   => 'billing',
            'routes'  => ['payments'],
            'actions' => ['view'],
        ],
        'nhis' => [
            'label'   => '청구 관리',
            'group'   => 'billing',
            'routes'  => ['nhis'],
            'actions' => ['view', 'update', 'send'],
        ],
        /* 「계산서 발행」 화면은 2026-09-01 요청으로 없앴다. 권한 묶음은 남긴다 —
           주문 상세의 세금계산서ㆍ현금영수증 발행과 취소가 이 묶음을 본다
           (아래 orders.issueTaxInvoice 등). 갈 화면이 없으므로 routes 는 비운다. */
        'invoice' => [
            'label'   => '세무 증빙 발행',
            'group'   => 'billing',
            'routes'  => [],
            'actions' => ['send', 'delete'],
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
            'label'   => '공단 팩스 발송',
            'group'   => 'dispatch',
            'routes'  => ['fax'],
            'actions' => ['view'],
        ],
        'messages' => [
            'label'   => '메시지 관리',
            'group'   => 'dispatch',
            'routes'  => ['messages'],
            // 유형을 고치는 것과 실제로 보내는 것은 다른 권한이다
            'actions' => ['view', 'create', 'update', 'delete', 'send'],
        ],
        'dispatch' => [
            'label'   => '발송/발행 내역',
            'group'   => 'dispatch',
            'routes'  => ['dispatch'],
            'actions' => ['view'],
        ],

        // ── 지원 ────────────────────────────────────────────────
        /* 걷어낸 것 — 기관 공지사항 (2026-09-20 지시).

           표(institutional_notices)가 운영에 없어 메뉴를 열면 깨진다. 권한 화면에
           남겨 두면 담당자가 켜 줄 수 있는 자리로 보이므로 함께 뺀다. 표를 세우기로
           하면 이 묶음을 그대로 되살린다 —

             'institutional-notices' => [
                 'label'   => '기관 공지사항',
                 'group'   => 'support',
                 'routes'  => ['institutional-notices'],
                 'actions' => ['view'],
             ], */
        'notices' => [
            'label'   => '공지사항',
            'group'   => 'support',
            'routes'  => ['notices'],
            'actions' => ['view', 'create', 'update', 'delete'],
        ],
        'inquiries' => [
            'label'   => '환자 문의',
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
        'masters' => [
            'label'   => '마스터 관리',
            'group'   => 'settings',
            'routes'  => ['masters'],
            'actions' => ['view', 'create', 'update', 'delete'],
        ],
        // 화면마다 쓰는 목록을 다룬다 — 잘못 고치면 여러 화면이 함께 흔들린다
        'common-codes' => [
            'label'   => '환경 설정',
            'group'   => 'settings',
            'routes'  => ['common-codes'],
            'actions' => ['view', 'create', 'update', 'delete'],
        ],
        'withworks-settings' => [
            'label'   => '위드웍스 연동 설정',
            'group'   => 'settings',
            'routes'  => ['withworks-settings'],
            // 연결 확인은 POST 라 create 로 추론된다 — 아래 overrides 에서 바로잡는다
            'actions' => ['view', 'update'],
        ],
        'delegation-settings' => [
            'label'   => '위임장 설정',
            'group'   => 'settings',
            'routes'  => ['delegation-settings'],
            'actions' => ['view', 'update'],
        ],
        'medical-aid-claim-settings' => [
            'label'   => '지급청구서 설정',
            'group'   => 'settings',
            'routes'  => ['medical-aid-claim-settings'],
            'actions' => ['view', 'update'],
        ],
        'ocr-settings' => [
            'label'   => 'OCR 설정',
            'group'   => 'settings',
            'routes'  => ['ocr-settings'],
            'actions' => ['view', 'update'],
        ],
        /* SSO 자격증명을 다루는 자리다 — 권한 그룹처럼 관리자만 연다.
           여기가 열리면 회사 전체 로그인을 남의 테넌트로 돌릴 수 있다. */
        'sso-settings' => [
            'label'      => 'SSO 설정',
            'group'      => 'settings',
            'routes'     => ['sso-settings'],
            'actions'    => ['view', 'update'],
            'admin_only' => true,
        ],
        'nice-settings' => [
            'label'   => '본인확인 설정',
            'group'   => 'settings',
            'routes'  => ['nice-settings'],
            'actions' => ['view', 'update'],
        ],
        /* 웹훅 관리 — 밖과 주고받는 알림의 얼개와 그 기록 (2026-09-10 지시).
           주소ㆍ파라미터를 고치면 저쪽에서 오는 알림이 우리 자리를 못 찾는다.
           로그에는 오간 값이 담겨 있어 아무나 볼 자리가 아니다. */
        'webhooks' => [
            'label'      => '웹훅 관리',
            'group'      => 'settings',
            'routes'     => ['webhooks'],
            'actions'    => ['view', 'create', 'update', 'delete'],
            'admin_only' => true,
        ],
        /* 병원 관리 (2026-10-02 지시) — 요양기관번호가 겹치면 청구가 남의 병원으로
           간다. 고치는 일은 담당자만 하게 두고, 보는 것은 주문을 다루는 사람에게 연다. */
        'hospitals' => [
            'label'   => '병원 관리',
            'group'   => 'settings',
            'routes'  => ['hospitals'],
            /* 삭제를 더한다 (2026-10-06 지시 · SR #75) — 「추가는 누구나, 삭제는
               Admin 기능을 가진 사람만」. 추가는 아래 overrides 에서 조회로 낮춘다. */
            'actions' => ['view', 'update', 'delete'],
        ],
        /* 시스템 감시 — 기계ㆍDBㆍ웹ㆍAWS 과금 (2026-10-02 지시).
           AWS 신분(계정 번호ㆍ역할)과 과금 금액이 보이는 자리라 관리자만 연다. */
        'monitoring' => [
            'label'      => '시스템 모니터링',
            'group'      => 'settings',
            'routes'     => ['monitoring'],
            'actions'    => ['view'],
            'admin_only' => true,
        ],
        /* 오류 기록 — 서버에서 난 잘못 (2026-09-11 지시).
           보낸 값과 쌓인 자취가 담겨 있어 아무나 볼 자리가 아니다. */
        /* 오류 이력은 **한 계정만** 연다 (2026-10-03 지시).
        
           관리자(role=admin)가 다섯인데, 이 화면은 코드 파일 경로ㆍ줄 번호ㆍ예외
           자취ㆍ요청에 담겨 온 값까지 그대로 보여 준다. 업무를 보는 자리가 아니라
           고치는 자리다. 그래서 역할이 아니라 **계정**으로 가린다. */
        'error-logs' => [
            'label'      => '오류 이력',
            'group'      => 'settings',
            'routes'     => ['error-logs'],
            'actions'    => ['view', 'update', 'delete'],
            'admin_only' => true,
            'only_email' => ['admin@ce-admin.co.kr'],
        ],
        /* 남의 운영 DB 계정을 다루는 화면이라 관리자만 연다 (2026-09-18 지시) */
        'withworks-source' => [
            'label'      => '위드웍스 자료 가져오기',
            'group'      => 'settings',
            'routes'     => ['withworks-source'],
            'actions'    => ['view', 'update'],
            'admin_only' => true,
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
        /* 병원 등록은 병원 관리를 여는 사람이면 누구나 (2026-10-06 지시 · SR #75).

           「기존 병원명 조회 안되는 것이 있고… 조회가 안되어 새로 생성을 하는 등
           불편함이 있으니」. 병원 마스터에 없는 병원을 만나면 그 자리에서 담을 수
           있어야 접수가 멈추지 않는다. 잘못 담아도 「겹친 번호 모아보기」로 합치면
           되고, 같은 이름ㆍ같은 요양기관번호는 담는 자리에서 이미 막는다.

           삭제는 그대로 delete 다 — 관리자만 지난다(컨트롤러가 역할을 다시 본다). */
        'hospitals.store' => ['hospitals', 'view'],

        /* SR — 올린 사람이 제 글을 고치고 파일을 붙이는 길 (2026-10-06 · SR #82ㆍ#86).

           `update` 는 **답변ㆍ상태 변경** 권한이다(위 service-requests 주석). 그런데
           라우트 이름만 보면 PUT 은 update, DELETE 는 delete 로 읽혀, 답변 권한이 없는
           담당자가 **제가 올린 글조차** 고치거나 제 파일을 뗄 수 없게 된다.

           그래서 이 넷은 조회로 둔다. 「내 글인가ㆍ답변이 달렸는가」는 컨트롤러가
           본다(updateContentㆍdeleteFile) — 권한 설정이 아니라 그 자리에서 가릴 일이다. */
        'sr.update'        => ['service-requests', 'view'],
        'sr.files.store'   => ['service-requests', 'view'],
        'sr.files.show'    => ['service-requests', 'view'],
        'sr.files.destroy' => ['service-requests', 'view'],

        // 처방전 이미지·첨부 파일 내보내기 — 라우트 이름이 files.* 라 페이지를 못 찾는다.
        // 처방전을 볼 수 있는 사람만 그 파일도 볼 수 있게 명시한다.
        // 단계를 옮기는 것은 새로 만드는 일이 아니라 고치는 일이다
        'order-returns.advance'         => ['order-returns', 'update'],
        // 검수 확정ㆍ전자 승인은 승인자 몫이라 approve 로 가른다
        'order-returns.confirmInspection' => ['order-returns', 'approve'],
        'order-returns.approve'           => ['order-returns', 'approve'],
        // 두 걸음 결재 (2026-09-28) — 책임자와 최종승인자를 가른다.
        // 「확인했습니다」와 최종승인자 목록은 값을 만들지 않는다 — 조회로 둔다
        // (POST 라 그냥 두면 create 로 읽혀, 볼 수만 있는 사람이 표시를 못 내린다).
        'order-returns.seenInspection'    => ['order-returns', 'view'],
        'order-returns.approverList'      => ['order-returns', 'view'],
        'order-returns.inspectionDetail'  => ['order-returns', 'view'],
        'order-returns.approvalDetail'    => ['order-returns', 'view'],
        'order-returns.managerApprove'    => ['order-returns', 'inspect_approve'],
        'order-returns.managerReject'     => ['order-returns', 'inspect_approve'],
        'order-returns.finalSignSend'     => ['order-returns', 'final_approve'],
        'order-returns.finalSign'         => ['order-returns', 'final_approve'],
        'order-returns.finalReject'       => ['order-returns', 'final_approve'],
        // 실행이 막히면 다시 시도한다 — 돈이 나가는 일이라 send 로 가른다
        'order-returns.retryRefund'       => ['order-returns', 'send'],
        'order-returns.sendTopupLink'     => ['order-returns', 'send'],
        // 증빙 재발행은 국세청까지 간다 — 등록ㆍ수정과 따로 통제한다
        'order-returns.reissueDocs'       => ['order-returns', 'send'],
        // 마이너스 발행은 국세청으로 나간다 — 등록ㆍ수정과 따로 통제한다
        'order-returns.issueCredit'       => ['order-returns', 'send'],
        // 창고 검수 결과를 물어 오는 것은 조회다
        'order-returns.pullInspection'    => ['order-returns', 'view'],
        // 연결 확인은 값을 만들지 않는다 — 설정을 쓰는 일이다
        'withworks-settings.test'       => ['withworks-settings', 'update'],
        'files.prescription-image'      => ['prescriptions', 'view'],
        'files.prescription-attachment' => ['prescriptions', 'view'],
        'files.prescription-temp'       => ['prescriptions', 'view'],
        'files.consent-guardian-id'     => ['prescriptions', 'view'],

        // POST 지만 실제로는 조회/조립일 뿐인 것
        'nice-settings.test'          => [null, 'view'],
        'orders.fetchWithworksStatus' => [null, 'view'],

        // POST 지만 기존 레코드를 고치는 것
        // 빈 검수·등록 화면 열기 — GET 이지만 초안 레코드를 만드므로 등록 권한으로 본다
        'prescriptions.create'        => [null, 'create'],
        'prescriptions.reanalyze'     => [null, 'update'],
        // 검수 완료ㆍ반려는 검수자 몫이라 approve 로 가른다. 검수 요청은 담당자 몫(update).
        'prescriptions.approve'        => [null, 'approve'],
        'prescriptions.reject'         => [null, 'approve'],
        'prescriptions.request-review' => [null, 'update'],
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
        // 이름·번호만 받아 처방전을 만들고 곧바로 보낸다 — 발송 권한으로 본다
        'prescription-consents.store'         => [null, 'send'],
        // 거래처에 문자·알림톡을 보낸다 — POST 지만 등록이 아니라 발송이다
        'messages.send'                       => [null, 'send'],
        'prescriptions.faxRegenerate'         => [null, 'send'],
        'prescriptions.delegationRegenerate'  => [null, 'send'],
        'prescriptions.withworksOrder'        => [null, 'send'],
        'settlement.issue-va'                 => [null, 'send'],
        'settlement.resend-va-sms'            => [null, 'send'],
        'admin.users.invite'                  => ['admin-users', 'send'],
        'admin.users.invitations.resend'      => ['admin-users', 'send'],

        // 세금계산서·현금영수증 발행/취소는 라우트가 orders.* 이지만, 주문을 보는 권한과
        // 국세청에 신고가 들어가는 권한은 달라야 한다. 그래서 별도의 'invoice' 묶음으로
        // 넘긴다 — 화면은 없어졌어도(2026-09-01) 이 권한은 그대로 쓰인다.
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
