<?php

namespace App\Support;

use App\Models\Order;
use App\Models\TossPayment;

/**
 * 토스페이먼츠 에스크로 배송정보 엑셀 — 양식과 값을 한 곳에서 정한다.
 *
 * ## 왜 필요한가
 *
 * 에스크로 결제는 **배송정보를 등록하거나 구매자가 구매확정을 해야** 정산이 돈다.
 * 등록하지 않으면 돈이 들어오지 않는다 (SR #141 「토스에 상품 전달･배송 정보 입력을
 * 해야 정산 금액 입금 되는 것 같습니다」). 여태 담당자가 토스 상점관리자에서 건마다
 * 손으로 적었다.
 *
 * ## 양식
 *
 * 2026-10-13 에 담당자가 토스 상점관리자의 「배송정보 한 번에 등록하기」에서 받은
 * **빈 양식**을 주었다. 그 파일을 그대로 본떠 짓는다 — 장 셋, 칸 이름, 예시 줄,
 * 안내문, 택배사 코드 191개, 고르는 목록까지 같다.
 *
 *   · `배송정보`   A 상점ID(MID) · B 주문번호 · C 발송택배사 코드 · D 운송장번호
 *   · `수령정보`   구매자가 **직접 수령**했을 때만 쓴다 — 우리는 택배로 보내므로 비운다
 *   · `택배사코드` 고르는 목록이 가리키는 자리
 *
 * **발송일자 칸은 없다.** 토스가 받는 것은 택배사와 운송장번호 둘뿐이다.
 * 2026-10-08 에 손으로 만들어 쓰던 목록에 「발송일시(YYYYMMDDhhmm)」가 있었는데,
 * 양식을 받아 보니 그런 칸이 없었다 — 뺐다.
 *
 * ## 토스가 적어 둔 주의사항 (양식 안에 그대로 실려 간다)
 *
 *   1. 예시로 기재된 내용은 수정하지 말아주세요.
 *   2. 아직 배송정보가 없는 주문번호를 배송정보 시트에 채워드리고 있어요.
 *      **추가로 없는 주문번호를 입력하면 등록이 되지 않으니 유의해주세요.**
 *   3. 발송택배사 코드는 선택지에 있는 것만 입력 가능합니다.
 *
 * 둘째 줄이 뜻하는 바 — 토스는 **저쪽이 아는 미등록 주문번호**를 미리 채워 준다.
 * 우리가 담는 것은 **우리가 아는** 에스크로 결제 완료 건이라 둘이 꼭 같지는 않다.
 * 2026-10-13 에 재어 보니 토스 27건 가운데 26건이 우리 목록에 있었고, 우리 쪽에만
 * 있는 19건은 이미 등록을 마친 건들이었다. 그런 줄은 올려도 등록되지 않을 뿐이다.
 */
class EscrowSheet
{
    /**
     * 택배사.
     *
     * 우리 자료에는 택배사 칸이 **없다** — 표를 다 훑어도 없다(2026-10-13 확인).
     * 창고(위드웍스)가 CJ 로만 내보내고 송장번호도 「6006-7119-9303」처럼 CJ 꼴이다.
     * 양식이 요구하는 꼴은 코드만이 아니라 「CJ: CJ대한통운」 통째다.
     */
    public const 택배사 = 'CJ: CJ대한통운';

    /** 양식의 `택배사코드` 장 그대로 — 191개. 고르는 목록이 이 자리를 가리킨다. */
    public const 택배사코드들 = [
        'CJ: CJ대한통운', 'PO: 우체국택배', 'HJ: 한진택배',
        'LOTTE: 롯데택배', 'LG: 로젠택배', 'CN: 편의점택배',
        'KD: 경동택배', 'DS: 대신택배', 'IY: 일양로지스',
        'CI: 천일택배', 'HA: 합동택배', 'DHL: DHL',
        'FEDEX: FedEx', 'UPS: UPS', 'REGISTPOST: 우체국미니(등기)',
        'HOMEPICK: 홈픽택배', 'EMS: EMS', 'BGF: BGF포스트',
        'KOREXG: CJ대한통운국제특송', 'HANDEX: 한덱스', 'PANTOS: LX판토스',
        'HPL: 한의사랑택배', 'KUNYOUNG: 건영택배', 'HONAM: 호남택배',
        'SLX: SLX', 'YONGMA: 용마로지스', 'SEBANG: 세방택배',
        'WONDERS: 원더스퀵', 'NHLOGIS: 농협택배', 'HILOGIS: HI택배',
        'LOTTEGLOBAL: 롯데글로벌', 'TNT: TNT', 'USPS: USPS',
        'IPARCEL: i-parcel', 'GSMNTON: GSM NtoN', 'SWGEXP: 성원글로벌',
        'ACIEXPRESS: ACI Express', 'AIRBOY: 에어보이익스프레스', 'DAEWOON: 대운글로벌',
        'KGLNET: KGL네트웍스', 'LINEEXP: LineExpress', 'TWOFASTEXP: 2FastExpress',
        'GSIEXPRESS: GSI익스프레스', 'GTSLOGIS: GTS로지스', 'ECMSEXPRESS: ECMS익스프레스',
        'EFS: EFS', 'DHLGLOBALMAIL: DHL GlobalMail', 'GPSLOGIX: GPS로직스',
        'CRLX: 시알로지텍', 'BRIDGE: 브릿지로지스', 'HOMEINNOV: 홈이노베이션로지스',
        'CWAY: cway express', 'GNETWORK: 자이언트', 'ACEEXP: ACE Express',
        'WEVILL: 우리동네택배', 'FOREVERPS: 퍼레버택배', 'WARPEX: 워펙스',
        'QXPRESS: 트랙스로지스', 'SMARTLOGIS: 스마트로지스', 'ESTHER: 에스더쉬핑',
        'INTRAS: 로토스', 'EUNHA: 은하쉬핑', 'UFREIGHT: 유프레이트 코리아',
        'LSERVICE: 엘서비스', 'TPMLOGIS: 로지스밸리택배', 'ZENIELSYSTEM: 제니엘시스템',
        'ANYTRACK: 애니트랙', 'JLOGIST: 제이로지스트', 'CHAINLOGIS: 두발히어로(4시간당일택배)',
        'QRUN: 큐런', 'HIVECITY: 하이브시티', 'HANSSEM: 한샘',
        'GOODSTOLUCK: 굿투럭', 'GENIEGO: 지니고', 'TODAYPICKUP: 오늘의픽업',
        'NOGOK: 노곡물류', 'SPASYS1: 스페이시스원', 'LTL: LTL',
        'LOGISPOT: 로지스팟', 'FRESHMATES: 프레시메이트', 'VROONG: 부릉',
        'NKLS: NK로지솔루션', 'DODOFLEX: 만나플렉스', 'SHIPNERGY: 쉽너지',
        'ETOMARS: 이투마스', 'VENDORPIA: 벤더피아', 'COSHIP: 캐나다쉬핑',
        'GDAKOREA: 지디에이코리아', 'BABABA: 바바바로지스', 'TEAMFRESH: 팀프레시',
        'ALLTAKOREA: 올타코리아', 'LOTTECHILSUNG: 롯데칠성', 'YUNDA: yunda express',
        'VALEX: 발렉스', 'KOKUSAI: 국제익스플레스', 'XINPATEK: 윈핸드해운항공',
        'HEREWEGO: 탱고앤고', 'WOONGJI: 웅지익스프레스', 'SBGLS: sbgls',
        'PINGPONG: 핑퐁', 'YDH: YDH', 'CARGOPLEASE: 화물을부탁해',
        'HOME1004: 1004홈', 'NAEUN: 나은물류', 'ACCCARGO: acccargo',
        'NTLPS: 엔티엘피스', 'EKDP: 삼다수가정배송', 'DRABBIT: 딜리래빗',
        'HOMEPICKTODAY: 홈픽오늘도착', 'DAERIM: 대림통운', 'LOGISPARTNER: UFO로지스',
        'GOBOX: 고박스', 'FASTBOX: 패스트박스', 'PANSTAR: 팬스타국제특송',
        'ACTCORE: 에이씨티앤코아물류', 'KJT: 케이제이티', 'THEBAO: 더바오',
        'RUSH: 오늘회러쉬', 'KT: kt express', 'IBP: ibpcorp',
        'HY: 한국야구르트', 'LOGISVALLEY: 로지스밸리', 'TODAY: 투데이',
        'ONEDAYLOGIS: 라스트마일시스템즈', 'HKHOLDINGS: 에이치케이홀딩스', 'JIKGUMOON: 직구문',
        'GBS: 지비에스', 'BANPOOM: 반품구조대', 'GLOVIS: 현대글로비스',
        'ARGO: 아르고', 'MTINTER: 엠티인터네셔널', 'GDSP: 골드스넵스',
        'WINION: 위니온로지스', 'YJSGLOBAL: YJS글로벌', 'SAGAWA: 사가와',
        'YAMATO: 야마토', 'JPOST: 일본우편', 'INTERLOGIS: 인터로지스',
        'WOOJIN: 우진글로벌로지스틱스', 'GHSPEED: 지에이치스피드', 'HOWSER: 하우저',
        'WIDETECH: 와이드테크', 'ECOHAI: 에코하이', 'TONAMI: 토나미',
        'DAIICHI: 제1화물', 'JMNP: 중앙일보엠앤피', 'FUKUYAMA: 후쿠야마통운',
        'KURLYNEXTMILE: 컬리넥스트마일', 'ARAMEX: ARAMEX', 'BISNZ: BISNZ',
        'INNOS: 이노스', 'SEORIM: 서림물류', 'WEMOVE: 위무브',
        'POOLATHOME: 풀앳홈', 'SPARKLE: 스파클', 'ICS: ICS',
        'HANMI: 한미포스트', 'HWATONG: 화통', 'ESTLA: 자하',
        'IK: IK물류', 'PULMUONEWATER: 풀무원샘물', 'TSG: 티에스지로지스',
        'OCS: ocs', 'MDLOGIS: 모든로지스', 'GCS: 지씨에스',
        'FTF: 물류대장(LCS)', 'WOORIHB: 우리한방택배', 'JWTNL: JWTNL',
        'LETUS: 레터스', 'JCLS: JCLS', 'GKGLOBAL: 지케이글로벌',
        'GONELO: 고넬로', 'CASA: 신세계까사', 'BRCH: 비알씨에이치',
        'HIDE: W7(리핑무역)', 'MORNINGGLOBAL: 모닝글로벌', 'ONTRAC: OnTrac',
        'HERMES: Hermes', 'RETURNALL: 리터놀', 'EMART24: 이마트24',
        'SEVENELEVEN: 세븐일레븐', 'DUXGLOBAL: 유로택배', 'CAINIAO: cainiao',
        'DNDN: 든든택배', 'HLDE: W7', 'ONDAY: 온데이 당일택배',
        'WINIONAC: 위니온 에어컨', 'MYALLTV: 마이로지스', 'CONTROLLOGIS: 컨트롤로지스',
        'HANSSEMSOFA: 한샘소파', 'WINIONBI: 위니온빌트인',
    ];

    /** 수령정보 장의 「주문자와의 관계」 선택지 */
    public const 관계들 = ['본인', '부모', '형제', '자녀', '경비원', '이웃', '동료', '친구', '기타'];

    /** 양식의 안내문 — 한 글자도 바꾸지 않는다 */
    private const 배송안내 = "1. 예시로 기재된 내용은 수정하지 말아주세요.\n2. 아직 배송정보가 없는 주문번호를 배송정보 시트에 채워드리고 있어요. 추가로 없는 주문번호를 입력하면 등록이 되지 않으니 유의해주세요.\n3. 발송택배사 코드는 선택지에 있는 것만 입력 가능합니다. 이용 중인 택배사가 선택지에 없다면 수령정보 입력으로 대신해주세요.";

    private const 수령안내 = "1. 예시로 기재된 내용은 수정하지 말아주세요.\n2. 아직 수령정보가 없는 주문번호를 배송정보 시트에 채워드리고 있어요. 추가로 없는 주문번호를 입력하면 등록이 되지 않으니 유의해주세요.\n3. 주문자와의 관계는 선택지에 있는 것만 입력 가능합니다.";

    /**
     * 이 주문이 에스크로 배송정보를 등록할 차례인가.
     *
     * 세 가지를 본다 —
     *
     *   · 토스 결제 줄이 있고
     *   · 그 결제가 **에스크로**이고(`useEscrow`. 카드는 에스크로를 쓰지 않는다)
     *   · 결제가 **끝났고**(DONE)
     *
     * 입금을 기다리는 건과 취소된 건은 담지 않는다 — 등록할 것이 없다.
     *
     * 송장이 아직 없는 건은 **담는다.** 택배사ㆍ송장을 비운 채 선다 — 토스가 준 빈
     * 양식도 그 두 칸이 비어 있다. 빼 버리면 담당자가 다 끝난 줄 안다.
     */
    public static function 담을것인가(?TossPayment $결제): bool
    {
        if (! $결제 || $결제->status !== 'DONE') {
            return false;
        }

        $원본 = $결제->raw_response;

        return is_array($원본) && ($원본['useEscrow'] ?? false) === true;
    }

    /**
     * 배송정보 한 줄 — [상점ID, 주문번호, 발송택배사 코드, 운송장번호].
     *
     * @return array{0:string,1:string,2:string,3:string}|null 담을 차례가 아니면 null
     */
    public static function 줄(Order $주문): ?array
    {
        $결제 = $주문->tossPayment;

        if (! self::담을것인가($결제)) {
            return null;
        }

        $원본 = is_array($결제->raw_response) ? $결제->raw_response : [];

        /* 운송장번호는 **숫자만** 적는다 — 양식의 예시가 「8952388931」이다 */
        $송장 = preg_replace('/\D/', '',
            (string) ($주문->tracking_number ?: $주문->withworks_tracking_no)) ?? '';

        return [
            (string) ($원본['mId'] ?? ''),
            (string) $결제->toss_order_id,
            $송장 !== '' ? self::택배사 : '',
            $송장,
        ];
    }

    /**
     * 토스 양식 그대로의 장 셋 — `XlsxWriter::여러장` 에 그대로 넘긴다.
     *
     * @param  array<int, array{0:string,1:string,2:string,3:string}>  $줄들
     */
    public static function 장들(array $줄들): array
    {
        $줄들 = array_values($줄들);

        return [self::배송정보장($줄들), self::수령정보장($줄들), self::택배사코드장()];
    }

    /**
     * `배송정보` 장.
     *
     * 1줄 머리글 · 2줄 예시 · 3줄부터 값이다. 오른쪽 G 칸에는 토스가 적어 둔 안내문과
     * 택배사 코드값이 선다 — 값 줄과 **같은 줄에** 서므로 줄을 통째로 짓는다.
     */
    private static function 배송정보장(array $줄들): array
    {
        /* G 칸 — 양식이 세워 둔 자리 그대로다 */
        $곁 = [2 => '입력 시 주의사항', 3 => self::배송안내, 11 => '발송택배사 코드값'];

        foreach (self::택배사코드들 as $자리 => $코드) {
            $곁[12 + $자리] = $코드;
        }

        $마지막 = max(2 + count($줄들), max(array_keys($곁)));

        $장줄들 = [
            ['상점ID(MID)', '주문번호', '발송택배사 코드', '운송장번호'],
            ['toss_payments', 1000001, self::택배사, 8952388931, '(예시)', '', $곁[2]],
        ];

        for ($번호 = 3; $번호 <= $마지막; $번호++) {
            $값 = $줄들[$번호 - 3] ?? ['', '', '', ''];
            $장줄들[] = [$값[0], $값[1], $값[2], $값[3], '', '', $곁[$번호] ?? ''];
        }

        return [
            '이름'   => '배송정보',
            '줄들'   => $장줄들,
            /* B 와 D 를 숫자로 — 값이 숫자일 때만 먹는다. 주문번호는 글자가 섞여 있어
               그대로 글로 서고, 예시의 1000001 과 운송장번호만 숫자가 된다.

               **정수칸으로 담는다** (2026-10-15). 엑셀의 기본 서식은 열한 자리까지만
               자릿수 그대로 보이고 열두 자리부터 지수 표기로 바꾼다. CJ 운송장번호가
               열두 자리라 운영에서 `6.00885E+11` 로 보였다 — 양식의 예시
               `8952388931` 은 열 자리여서 멀쩡했고, 그래서 더 늦게 드러났다.
               담긴 값은 그대로이므로 토스가 읽는 것은 달라지지 않는다. */
            '숫자칸' => [1, 3],
            '정수칸' => [1, 3],
            '굵은줄' => [1],
            '너비'   => [16, 52, 22, 18, 10, 6, 70],
            '목록'   => [[
                '범위' => 'C2:C1048576',
                '값'   => "'택배사코드'!\$A\$1:\$A\$" . count(self::택배사코드들),
            ]],
        ];
    }

    /**
     * `수령정보` 장 — 비워 둔다.
     *
     * 「수령정보는 구매자가 직접 상품을 수령했을 때만 이용해주세요」. 우리는 택배로
     * 보내므로 쓸 일이 없다. 그래도 장은 둔다 — 받은 양식과 같은 꼴이어야 한다.
     * 주문번호까지는 채워 두어, 직접 수령한 건이 생기면 담당자가 날짜와 이름만
     * 적으면 되게 한다.
     */
    private static function 수령정보장(array $줄들): array
    {
        $곁 = [3 => '입력 시 주의사항', 4 => self::수령안내, 11 => '수령인과 주문자 사이의 관계'];

        foreach (self::관계들 as $자리 => $관계) {
            $곁[12 + $자리] = $관계;
        }

        $마지막 = max(3 + count($줄들), max(array_keys($곁)));

        $장줄들 = [
            ['수령정보는 구매자가 직접 상품을 수령했을 때만 이용해주세요'],
            ['상점ID(MID)', '주문번호', '수령일자', '수령인명', '주문자와의 관계'],
            ['toss_payments', 1000001, '2021.10.11', '김토스', '본인', '(예시)', '', $곁[3]],
        ];

        for ($번호 = 4; $번호 <= $마지막; $번호++) {
            $값 = $줄들[$번호 - 4] ?? ['', '', '', ''];
            $장줄들[] = [$값[0], $값[1], '', '', '', '', '', $곁[$번호] ?? ''];
        }

        return [
            '이름'   => '수령정보',
            '줄들'   => $장줄들,
            '숫자칸' => [1],
            '정수칸' => [1],
            '굵은줄' => [2],
            '너비'   => [16, 52, 14, 14, 18, 10, 6, 70],
            '목록'   => [[
                '범위' => 'E3:E' . max(3, 3 + count($줄들)),
                '값'   => '"' . implode(',', self::관계들) . '"',
            ]],
        ];
    }

    /** `택배사코드` 장 — 고르는 목록이 가리키는 자리 */
    private static function 택배사코드장(): array
    {
        return [
            '이름' => '택배사코드',
            '줄들' => array_map(fn ($코드) => [$코드], self::택배사코드들),
            '너비' => [34],
        ];
    }
}
