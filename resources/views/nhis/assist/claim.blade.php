{{-- 공단 요양비지급청구서등록(2221) 입력 지원 --}}
@extends('nhis.assist._base')

@php
  $who = $order->patient?->name ?? $prescription?->patient_name_ocr ?? $order->order_number;
  $ask = 0;
  foreach ($groups as $rows) { foreach ($rows as $r) { if ($r['ask'] ?? false) { $ask++; } } }
@endphp

@section('windowTitle', '공단 요양비 청구 지원 — ' . $who)
@section('title', '공단 요양비 청구 입력 지원')
@section('subtitle')
  요양비 &gt; 요양비청구 &gt; 2221 요양비지급청구서등록 ·
  {{ $who }} · {{ $order->order_number }}@if($prescription) · {{ $prescription->rx_number }}@endif
@endsection
@section('steps')
  입력 순서 — <b>① 신청내용 입력</b> → <b>② 저장</b> → <b>③ 제출서류 파일첨부</b> → <b>④ 저장</b> → <b>⑤ 최종제출</b>
@endsection

@push('style')
<style>
  .tax { width:100%; border-collapse:collapse; font-size:12px; }
  .tax th, .tax td { border-bottom:1px solid #f2f4f6; padding:8px 14px; text-align:left; }
  .tax th { background:#fafbfc; font-size:11px; color:var(--sub); font-weight:700; }
  .tax td { font-weight:700; }
  .tax .empty-msg { color:var(--danger); font-weight:400; padding:14px; }
  .doc { display:flex; align-items:center; gap:10px; padding:9px 14px; border-bottom:1px solid #f2f4f6; }
  .doc:last-child { border-bottom:none; }
  .doc-name { flex:1; font-weight:700; }
  .doc-note { font-size:11px; color:var(--sub); font-weight:400; margin-top:2px; }
  .have { font-size:11px; font-weight:700; color:var(--ok); }
  .havent { font-size:11px; font-weight:700; color:var(--danger); }
  .cbtn.link { text-decoration:none; display:inline-block; text-align:center; }
  .cautions { font-size:11px; color:var(--sub); line-height:1.9; padding:10px 14px; }
</style>
@endpush

@section('body')

  @unless($delegated)
    <div class="banner">
      <b>위임 등록이 확인되지 않습니다.</b> 공단에 위임 등록을 먼저 마쳐야 청구가 받아들여집니다.
      위임장 서명 화면의 「공단 위임 등록」에서 등록하십시오.
    </div>
  @endunless

  @if($ask > 0)
    <div class="banner info">
      <b>확인 필요 {{ $ask }}건</b> — 공단 계산식·선택 문구가 확인되지 않아 값을 만들지 않았습니다.
      추측한 값을 옮겨 적으면 오청구가 되므로 비워 두었습니다. 해당 항목은 공단 화면을 보고 직접 입력하십시오.
    </div>
  @endif

  @foreach($groups as $groupName => $rows)
    @include('nhis.assist._group', ['name' => $groupName, 'rows' => $rows])
  @endforeach

  {{-- 국세청자료는 발행 건마다 행이 늘어난다 --}}
  <div class="grp">
    <div class="grp-hd">
      <span class="grp-name">5. 국세청자료</span>
    </div>
    <table class="tax">
      <thead>
        <tr><th style="width:110px">문서종류</th><th style="width:110px">작성일자</th><th>승인번호</th><th style="width:100px">합계금액</th><th style="width:70px"></th></tr>
      </thead>
      <tbody>
        @forelse($taxRows as $i => $t)
          <tr data-copy-scope>
            <td>{{ $t['kind'] }}</td>
            <td>{{ $t['date'] ?? '—' }}</td>
            <td style="word-break:break-all">{{ $t['no'] }}</td>
            <td>{{ $t['amount'] ?? '—' }}</td>
            <td>
              <button class="cbtn" onclick="copyGroup(this)">행 복사</button>
              {{-- 행 복사가 집어 갈 값들. 같은 행 안에 숨겨 둬야 진행률·복사 기록이 함께 남는다. --}}
              <div style="display:none">
                @foreach(['문서종류' => $t['kind'], '작성일자' => $t['date'], '승인번호' => $t['no'], '합계금액' => $t['amount']] as $k => $v)
                  <div class="row" data-key="tax-{{ $i }}-{{ $loop->index }}" data-copy="{{ $v !== null ? '1' : '0' }}">
                    <div class="lbl">{{ $k }}</div>
                    <div class="val" data-val>{{ $v }}</div>
                  </div>
                @endforeach
              </div>
            </td>
          </tr>
        @empty
          <tr><td colspan="5" class="empty-msg">발행된 세금계산서·현금영수증이 없습니다. 청구 전에 발행하십시오.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  {{-- 저장을 마친 뒤 올릴 서류 --}}
  <div class="grp">
    <div class="grp-hd"><span class="grp-name">6. 제출 서류 — 저장 후 첨부</span></div>
    @foreach($documents as $d)
      <div class="doc">
        <div>
          <div class="doc-name">{{ $d['name'] }}</div>
          @if($d['note'])<div class="doc-note">{{ $d['note'] }}</div>@endif
        </div>
        <div class="grow"></div>
        @if($d['url'])
          <span class="have">보유</span>
          <a class="cbtn link" href="{{ $d['url'] }}" target="_blank" rel="noopener">내려받기</a>
        @else
          <span class="havent">미보유</span>
        @endif
      </div>
    @endforeach
  </div>

  <div class="grp">
    <div class="grp-hd"><span class="grp-name">공단 안내</span></div>
    <div class="cautions">
      ※ 데이터를 모두 입력하고 저장을 완료해야 제출서류 첨부가 가능합니다.<br>
      ※ 사전급여제한자는 요양비 지급이 불가하므로, 수진자 자격확인(약국) 또는 관할지사에 문의바랍니다.<br>
      ※ 입력칸 비활성화 등 오류 발생 시, 타 브라우저 활용 또는 쿠키삭제를 해주시기 바랍니다.
    </div>
  </div>

@endsection
