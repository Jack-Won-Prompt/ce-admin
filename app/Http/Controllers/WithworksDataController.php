<?php

namespace App\Http\Controllers;

use App\Services\WithworksImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * 위드웍스에서 옮겨 담은 자료를 본다 — 운영 데이터 (2026-09-18 지시).
 *
 * 우리 표(`ww_*`)만 본다. 화면이 저쪽 운영 DB 를 곧바로 두드리지 않는다 — 사람이 목록을
 * 굴릴 때마다 남의 운영 DB 에 질의가 나가면 안 된다. 담는 일은 설정 화면에서 한다.
 *
 * `udf` 칸은 이름만으로는 뜻을 알 수 없어, 우리가 아는 짝을 머리글에 적어 준다.
 * 짝은 우리가 그쪽으로 **보낼 때** 쓰는 자리와 같다(PrescriptionController).
 */
class WithworksDataController extends Controller
{
    /** 처방전 정보의 udf 짝 — 아는 것만 적는다 */
    public const 처방전짝 = [
        'udf1'  => '주민등록번호',  'udf2'  => '진단일',      'udf3'  => '상병분류',
        'udf4'  => '건보재등록',    'udf5'  => '상병코드',    'udf6'  => 'SB/SCI',
        'udf7'  => '비뇨일',        'udf8'  => '1일 사용량',  'udf9'  => '처방일수',
        'udf10' => '처방수량',      'udf11' => '자격',        'udf13' => '처방사용기간',
        'udf14' => '처방종료일',    'udf15' => '처방 의사',   'udf16' => '이관 대리점',
        'udf17' => '신구매/재구매', 'udf18' => '특례',        'udf19' => '공단등록상태',
        'udf20' => '사유',          'udf22' => '공제',        'udf23' => '현금영수증',
        'udf24' => '보호자',        'udf25' => '주문담당',    'udf28' => '공단 지사',
        'udf30' => '다음재구매',    'udf32' => '신환등록일',  'udf33' => '병원(코드-이름)',
        'udf34' => '병원',          'udf39' => '요양기관번호', 'udf42' => '위임 시작',
        'udf43' => '위임 종료',
    ];

    /** 처방전 정보 */
    public function prescriptions(Request $request, WithworksImport $svc): View
    {
        $q = DB::table('ww_prescription_infos');

        $this->찾기($q, $request, [
            'add_no', 'descr', 'udf1', 'udf15', 'udf24', 'udf25', 'udf28', 'udf33', 'udf34',
        ]);

        if ($request->filled('대리점')) { $q->where('account_id', $request->input('대리점')); }
        if ($request->filled('부터'))   { $q->whereDate('reg_date', '>=', $request->input('부터')); }
        if ($request->filled('까지'))   { $q->whereDate('reg_date', '<=', $request->input('까지')); }

        $줄 = (clone $q)->orderByDesc('ww_id')->limit(1000)->get();

        /* 대리점 고르개 — 담긴 자료에서 뽑는다. 저쪽에 묻지 않는다. */
        $대리점 = DB::table('ww_prescription_infos')
            ->selectRaw('account_id, COUNT(*) n')->groupBy('account_id')
            ->orderByDesc('n')->get()
            ->map(fn ($r) => [
                'id'   => $r->account_id,
                'name' => DB::table('ww_customers')->where('ww_id', $r->account_id)->value('account_name')
                          ?? ('#' . $r->account_id),
                'n'    => $r->n,
            ]);

        return view('withworks-data.prescriptions', [
            '줄'     => $줄,
            '전체'   => (clone $q)->count(),
            '대리점' => $대리점,
            '짝'     => self::처방전짝,
            '현황'   => $svc->현황()['prescription_infos'],
        ]);
    }

    /** 고객 정보 — 주소를 함께 붙인다 */
    public function customers(Request $request, WithworksImport $svc): View
    {
        $q = DB::table('ww_customers');

        $this->찾기($q, $request, [
            'account_code', 'account_name', 'phone_1', 'phone_2', 'resident_no', 'udf10',
        ]);

        $줄 = (clone $q)->orderByDesc('ww_id')->limit(1000)->get();

        /* 주소는 한 번에 모아 온다 — 줄마다 물으면 천 줄에 천 번이다 */
        $주소 = DB::table('ww_customer_addresses')
            ->whereIn('account_id', $줄->pluck('ww_id'))
            ->orderBy('ww_id')
            ->get()
            ->groupBy('account_id');

        return view('withworks-data.customers', [
            '줄'   => $줄,
            '전체' => (clone $q)->count(),
            '주소' => $주소,
            '현황' => $svc->현황(),
        ]);
    }

    /** 한 고객의 주소 — 줄을 눌렀을 때 */
    public function addresses(int $고객)
    {
        $줄 = DB::table('ww_customer_addresses')->where('account_id', $고객)
            ->orderByDesc('ww_id')->get();

        return response()->json([
            'success' => true,
            'rows'    => $줄->map(fn ($a) => [
                'ww_id'   => $a->ww_id,
                'name'    => $a->address_name,
                'code'    => $a->address_code,
                'type'    => $a->address_type,
                'zipcode' => $a->zipcode,
                'addr'    => trim(implode(' ', array_filter([
                    $a->address_line_3, $a->address_line_4, $a->address_line_1, $a->address_line_2,
                ]))),
                'phone'   => $a->phone1,
                'use_yn'  => $a->use_yn,
            ]),
        ]);
    }

    /**
     * 찾는 말 하나로 여러 칸을 훑는다.
     *
     * 칸이 여든 개라 칸마다 찾는 자리를 두면 화면이 찾는 칸으로 덮인다.
     */
    private function 찾기($q, Request $request, array $칸들): void
    {
        $말 = trim((string) $request->input('찾기'));
        if ($말 === '') { return; }

        $q->where(function ($w) use ($칸들, $말) {
            foreach ($칸들 as $칸) {
                $w->orWhere($칸, 'like', '%' . $말 . '%');
            }
        });
    }
}
