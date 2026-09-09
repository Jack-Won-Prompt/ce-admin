<?php

namespace App\Support;

/**
 * 저장 이력에 남기지 않을 칸과, 화면에 보일 칸 이름.
 *
 * 이력은 「누가 언제 무엇을 무엇에서 무엇으로」를 남긴다. 그런데 그 「무엇」에 주민등록
 * 번호가 들어가면 암호로 감춰 둔 것이 이력 표에 평문으로 쌓인다 — 목록ㆍ팩스에는 가린
 * 것만 쓴다는 규칙이 이력에서 무너진다. 그런 칸은 아예 남기지 않는다.
 *
 * 서명 그림ㆍ해시ㆍ암호문도 같다. 사람이 읽을 수 없는 값이라 이력에 남겨도 쓸모가 없고,
 * 길이만 길어 표를 못 읽게 만든다.
 */
final class ChangeLog
{
    /** 이력에 남기지 않는 칸 */
    public const 감출칸 = [
        /* 고유식별정보 — **원문ㆍ암호문ㆍ해시는 남기지 않는다.**

           다만 주민등록번호를 고친 것도 이력에 남아야 한다(2026-09-09 지시).
           그래서 **가린 값(_masked)만** 남긴다 — 「850203-1******」 꼴이라
           바뀐 사실과 앞자리는 확인되면서 원문은 이력 표에 쌓이지 않는다. */
        'resident_no', 'resident_no_enc', 'resident_no_hash',
        'resident_no_ocr', 'resident_no_ocr_enc',
        'guardian_resident_no', 'guardian_resident_no_enc',
        // 사람이 읽을 수 없는 값
        'signature_data', 'ocr_raw_text', 'raw_payload', 'raw_response',
        'password', 'remember_token',
        // 시스템이 쥐고 있는 값 — 사람이 고친 것이 아니다
        'id', 'created_at', 'updated_at', 'deleted_at', 'updated_by',
    ];

    /** 화면에 보일 칸 이름 — 없으면 칸 이름을 그대로 쓴다 */
    public const 이름표 = [
        // 상담ㆍ환자
        'patient_id'            => '거래처',
        'patient_name_ocr'      => '이름',
        'resident_no_masked'          => '주민등록번호',
        'resident_no_ocr_masked'      => '주민등록번호(처방전)',
        'guardian_resident_no_masked' => '보호자 주민등록번호',
        'mobile_ocr'            => '전화번호',
        'address_ocr'           => '주소',
        'counsel_status'        => '상담 진행',
        'counsel_contents'      => '상담 내용',
        'counsel_call_no'       => '상담 연락처',
        'counsel_acc_add_type'  => '유형',
        'admin_note'            => '등록 메모',
        'review_memo'           => '참고 사항',
        'review_request_memo'   => '검수 요청 메모',
        'status'                => '상태',
        'reviewed_by'           => '검수자',
        'reviewed_at'           => '검수 일시',
        // 병원ㆍ처방
        'hospital'              => '병원명',
        'hospital_code'         => '요양병원 코드',
        'doctor_name'           => '담당 의사명',
        'license_no'            => '의사면허번호',
        'specialty'             => '진료과목',
        'disease_name'          => '상병 명',
        'disease_code'          => '상병코드',
        'disease_class'         => '상병 구분',
        'diagnosis_date'        => '진단 확인일',
        'uro_date'              => '요류역학검사일',
        'daily_count'           => '1일 처방 개수',
        'total_days'            => '총 처방일수',
        'total_count'           => '총계',
        'issued_date'           => '처방전 발행일',
        'rx_use_period'         => '처방전 사용 기간',
        'rx_end_date'           => '처방전종료일',
        'repurchase_date'       => '재구매일',
        'next_repurchase'       => '다음 재구매 가능일',
        'daily_use_qty'         => '하루 사용 수량',
        'five_six'              => 'Five/Six',
        'diverticulums'         => '일일 도뇨 횟수',
        'buy_type'              => '신구매/재구매',
        'buy_date'              => '구입일',
        'pay_date'              => '결제일',
        'use_start_date'        => '사용 개시일',
        'benefit_end_date'      => '급여 종료일',
        'inmarket_due'          => '인마켓 마감일',
        // 급여ㆍ청구
        'benefit_class'         => '자격',
        'claim_agency'          => '청구처',
        'billing_office_id'     => '관할 청구처',
        'local_gov'             => '관할 지자체',
        'billing_strategy'      => '청구전략',
        'nhis_reg_status'       => '건보등록',
        'nhis_reg_date'         => '건보등록일',
        'nhis_renew_target'     => '건보 재등록 대상자',
        'nhis_renew_due'        => '건보 재등록 기한',
        'sb_sci'                => '구분(SB/SCI)',
        'manager_id'            => '주문 담당자',
        // 주문
        'order_number'          => '주문번호',
        'product_name'          => '제품명',
        'product_code'          => '제품 코드',
        'quantity'              => '수량',
        'unit_price'            => '단가',
        'nhis_amount'           => '기관 부담금',
        'patient_copay'         => '본인 부담금',
        'total_amount'          => '결제 금액',
        'pay_method'            => '결제수단',
        'deposit_confirmed_at'  => '입금확인',
        'deposit_amount'        => '입금액',
        'shipping_recipient'    => '받는 사람',
        'shipping_address'      => '배송지',
        'shipping_address_detail' => '배송지 상세',
        'shipping_postcode'     => '우편번호',
        'warehouse_note'        => '창고 전달 메모',
        'withworks_so_no'       => '판매번호',
        'tax_invoice_status'    => '세금계산서',
        'cash_receipt_status'   => '현금영수증',
        // 거래처
        'name'                  => '이름',
        'mobile'                => '환자 전화번호',
        'phone'                 => '보호자 전화번호',
        'main_contact'          => '주 연락처',
        'email'                 => '이메일',
        'address'               => '주소',
        'address_detail'        => '상세 주소',
        'postcode'              => '우편번호',
        'birth_date'            => '생년월일',
        'gender'                => '성별',
        'care_type'             => '사업부',
        'patient_type'          => '환자구분',
        'contact_status'        => '연락 상태',
        'contact_channel'       => '연락 선호 방식',
        'deduction'             => '현금영수증 구분',
        'cash_receipt_no'       => '현금영수증 번호',
        'remitter'              => '송금자명',
        'guardian_relation'     => '보호자 관계',
        'guardian_name'         => '보호자 성명',
        'guardian_birth_date'   => '보호자 생년월일',
        'guardian_phone'        => '보호자 연락처',
    ];

    /** 이력에 남길 칸인가 */
    public static function 남기나(string $칸): bool
    {
        return ! in_array($칸, self::감출칸, true);
    }

    /** 화면에 보일 이름 */
    public static function 이름(string $칸): string
    {
        return self::이름표[$칸] ?? $칸;
    }
}
