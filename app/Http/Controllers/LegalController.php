<?php
// app/Http/Controllers/LegalController.php
//
// 스토어 심사와 앱에서 여는 문서 세 장 — 이용약관ㆍ개인정보처리방침ㆍ계정 삭제.
// 로그인 없이 열려야 한다. 구글이 심사할 때도, 계정을 지운 사람이 열 때도 그렇다.
//
// 회사 정보와 보유 기간은 설정에서 읽는다 — 화면에 박아 두면 상호나 기간이 바뀔 때
// 방침만 옛말을 하게 된다. 방침에 적힌 기간과 시스템이 실제로 지키는 기간은 같아야 한다.

namespace App\Http\Controllers;

use Illuminate\View\View;

class LegalController extends Controller
{
    /** 방침 시행일. 내용을 고칠 때 이 날짜도 함께 옮긴다. */
    private const EFFECTIVE_DATE = '2026년 9월 6일';

    public function terms(): View
    {
        return view('legal.terms', $this->shared());
    }

    public function privacy(): View
    {
        return view('legal.privacy', $this->shared());
    }

    public function deletion(): View
    {
        return view('legal.deletion', $this->shared());
    }

    private function shared(): array
    {
        return [
            'effectiveDate' => self::EFFECTIVE_DATE,
            // 주민등록번호 보유 기간은 RoPA 기재값이다(config/rrn.php). 방침도 같은 값을 말한다.
            'rrnYears'      => (int) config('rrn.retention.years', 5),
            'company'       => [
                'name'  => config('popbill.company.corp_name') ?: '콜로플라스트 코리아 주식회사',
                'ceo'   => config('popbill.company.ceo_name')  ?: '',
                'addr'  => config('popbill.company.addr')      ?: '',
                'tel'   => config('popbill.company.tel')       ?: '',
                'email' => config('popbill.company.email')     ?: '',
            ],
        ];
    }
}
