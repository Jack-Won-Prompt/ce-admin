<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 웹훅 항목 설명을 사무적 용어로 고쳐 쓴다 (2026-09-15 지시).
 *
 * 설정 › 웹훅 관리 화면의 항목 설명은 코드가 아니라 **표에 저장된 문구**라, 소스를
 * 고쳐도 운영 화면은 그대로다. 여기서 다시 적는다.
 *
 * 고치는 잣대 — 화면에 나가는 글은 담당자가 읽는 업무 문서다.
 *
 *   「무슨 사건인가」        → 「이벤트 구분」
 *   「열쇠」                → 「키」
 *   「우리가 매긴 / 저쪽」    → 「CE Admin / 위드웍스ㆍ토스ㆍ팝빌」
 *   「그대로 믿지 않고 다시 묻는다」 → 「값을 신뢰하지 않고 재조회합니다」
 *   「갈래」                → 「구분」
 *   「담긴 묶음」            → 「정보」
 *
 * 문구로 맞춘다 — 같은 설명이 여러 웹훅에 함께 서 있어, 문구를 열쇠로 삼으면 한 번에
 * 바뀐다. 이미 고쳐 둔 줄은 짝이 없어 그대로 지나간다.
 */
return new class extends Migration
{
    /** 예전 문구 => 새 문구 */
    private const 고칠말 = [
        '이벤트 이름'                                 => '이벤트 이름',
        '보낸 시각'                                   => '발송 시각',
        '결제 열쇠 — 이것으로 토스에 다시 묻는다'        => '결제 키 — 토스 재조회에 사용합니다',
        '우리가 매긴 주문 번호'                        => 'CE Admin 주문번호',
        '결제 상태 — 그대로 믿지 않고 재조회한다'        => '결제 상태 — 값을 신뢰하지 않고 재조회합니다',
        '무엇으로 냈는가'                              => '결제 수단',
        '승인 시각 — 결제 시각으로 쓴다'                => '승인 시각 — 결제 시각으로 사용합니다',
        '우리가 매긴 주문 번호 — 이것으로 건을 찾는다'    => 'CE Admin 주문번호 — 대상 건 조회에 사용합니다',
        'DONE 이면 입금, CANCELED 면 입금 취소'        => 'DONE 은 입금, CANCELED 는 입금 취소입니다',
        '가상계좌를 만들 때 받아 둔 값과 맞춰 본다'       => '가상계좌 발급 시 수신한 값과 대조합니다',
        '거래 열쇠'                                   => '거래 키',
        '팝빌 사업자번호 — 없으면 우리 설정값을 쓴다'     => '팝빌 사업자번호 — 없으면 시스템 설정값을 사용합니다',
        '문서번호 — 이것으로 그 한 건만 다시 읽는다'      => '문서번호 — 해당 건 재조회에 사용합니다',
        '상태 코드 — 그대로 믿지 않고 팝빌에 다시 묻는다' => '상태 코드 — 값을 신뢰하지 않고 팝빌에 재조회합니다',
        '접수번호 — 이것으로 우리 건을 찾는다'           => '접수번호 — 대상 건 조회에 사용합니다',
        '전송 상태 — 그대로 믿지 않고 팝빌에 다시 묻는다' => '전송 상태 — 값을 신뢰하지 않고 팝빌에 재조회합니다',
        '함께 정해 둔 비밀 — 틀리면 받지 않는다'         => '사전 공유한 비밀키 — 불일치 시 수신하지 않습니다',
        '사건 번호 — 같은 사건이 두 번 와도 이것으로 거른다'
            => '이벤트 번호 — 중복 수신 시 이 값으로 제외합니다',
        '저쪽에서 그 일이 일어난 때'                    => '위드웍스에서 해당 이벤트가 발생한 시각',
        '우리 주문번호 — 이것으로 건을 찾는다'           => 'CE Admin 주문번호 — 대상 건 조회에 사용합니다',
        '위드웍스 판매번호'                            => '위드웍스 판매번호',
        '판매 갈래'                                   => '판매유형',
        '저쪽 상태 코드'                               => '위드웍스 상태 코드',
        '저쪽 상태 이름'                               => '위드웍스 상태명',
        '택배사ㆍ송장번호가 담긴 묶음'                   => '택배사ㆍ송장번호 정보',
        '우리 반품 접수번호'                           => 'CE Admin 반품 접수번호',
        '되돌아온 원 판매번호'                          => '반품 대상 원 판매번호',
        '반품ㆍ교환ㆍ취소 가운데 무엇인가'                => '반품ㆍ교환ㆍ취소 구분',
    ];

    /** 「무슨 사건인가 — so.confirmed」 꼴은 사건 이름만 다르므로 한 줄로 잡는다 */
    private const 사건들 = [
        'so.confirmed', 'so.allocated', 'so.picked', 'so.invoiced',
        'so.shipped', 'so.delivered', 'so.cancelled',
        'ro.rcpt_completed', 'ro.confirmed', 'ro.cancelled',
    ];

    public function up(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('webhook_params')) {
            return;
        }

        foreach (self::고칠말 as $예전 => $새것) {
            if ($예전 === $새것) {
                continue;
            }
            DB::table('webhook_params')->where('description', $예전)
                ->update(['description' => $새것]);
        }

        foreach (self::사건들 as $사건) {
            DB::table('webhook_params')->where('description', "무슨 사건인가 — {$사건}")
                ->update(['description' => "이벤트 구분 — {$사건}"]);
        }
    }

    public function down(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('webhook_params')) {
            return;
        }

        foreach (self::고칠말 as $예전 => $새것) {
            if ($예전 === $새것) {
                continue;
            }
            DB::table('webhook_params')->where('description', $새것)
                ->update(['description' => $예전]);
        }

        foreach (self::사건들 as $사건) {
            DB::table('webhook_params')->where('description', "이벤트 구분 — {$사건}")
                ->update(['description' => "무슨 사건인가 — {$사건}"]);
        }
    }
};
