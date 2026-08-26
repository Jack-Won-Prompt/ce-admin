@extends('layouts.app')

@section('title', '위임장 설정')
@section('page-title', '요양비 위임장 설정')
@section('breadcrumb', '홈 - 설정 - 위임장 설정')

@push('styles')
<style>
  /* 껍데기 — 블록 사이 12. .page-body 가 제 자식들에게 두는 간격과 같다. */
  .ds-form { max-width:100%; gap:12px; }

  /* ── 안내문 ──────────────────────────────────────────
     전역 .ds-grid-hint 규격 그대로다 — 12/500 · #656C74 · 앞에 12 아이콘 · gap 4 · 상자 없음.
     전에는 파란 상자(bg #E9F9FB · 1px 테두리 · r8 · pad 12/16 · 12/400)였는데
     목록 화면 스물넷 어디에도 그런 상자가 없다.
     결과바에서는 한 줄로 줄이지만(말줄임) 여기서는 줄을 접어 낱말을 다 보인다. */
  .ds-form .ds-grid-hint { white-space:normal; overflow:visible; text-overflow:clip; margin-right:0; }
  /* 개발자가 넣어 둔 info-circle 을 그대로 쓴다(전역 규칙이 제 mask 아이콘을 접는다).
     크기를 시안값 12 로, 글자와의 사이를 4 로 못박는다. */
  .ds-form .ds-grid-hint > i { font-size:12px; line-height:19px; vertical-align:top; margin-right:4px; }
  /* <b> 의 브라우저 기본 bolder 는 500 위에서 700 으로 풀린다 — 시안 굵기로 못박는다 */
  .ds-form .ds-grid-hint b { font-weight:700; }

  /* ── 흰 카드 ──────────────────────────────────────────
     탭줄과 폼과 저장 단추를 전역 .ds-grid-card 하나가 담는다 — /documents 와 같은 부품이다
     (흰 바탕 · r12 · 테두리 없음 · 그림자 없음. 시안 148:6653 · 156:7261).
     전에는 흰 카드가 눌려 있어(.ds-pane .ds-card { border:none; padding:0 })
     폼이 회색 바탕 위에 바로 놓였다.
     한 가지만 되돌린다 — overflow. 목록 카드는 hidden 이라 그리드가 안에서 스크롤하지만,
     「글자 항목 위치」 탭은 표가 뷰포트보다 길어 hidden 이면 잘리고 스크롤도 안 생긴다. */
  .ds-form .ds-grid-card { overflow:visible; }

  /* 카드 안여백 12/16 */
  .ds-card { background:transparent; border:none; border-radius:0; padding:12px 16px; margin-bottom:0; }
  /* 구획 제목 = 14px/700. 바로 위 탭줄이 이미 경계선이라 제목은 선을 또 긋지 않는다. */
  .ds-card h3 { margin:0 0 12px; font-size:14px; font-weight:700; line-height:22px; color:var(--primary);
    display:flex; align-items:center; gap:8px; }
  .ds-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
  .ds-field { display:flex; flex-direction:column; gap:4px; }
  .ds-field.full { grid-column:1 / -1; }
  .ds-field label { font-size:13px; font-weight:500; line-height:21px; color:var(--gray-700); }
  /* 입력 h32 = pad 5 + lh 20 + pad 5 + 테두리 2 */
  /* 좌표 표 안의 입력 예순 개가 이 규칙 밖(표의 td 안)이라 브라우저 기본 그대로였다 —
     25 높이에 2px #767676 테두리, 모서리는 각졌다. 이 화면의 모든 입력에 걸리게 한다. */
  .ds-form input { padding:5px 12px; border:1px solid var(--gray-200); border-radius:8px;
    font-size:13px; font-weight:400; line-height:20px; font-family:inherit;
    color:var(--gray-1000); background:var(--gray-0); }
  .ds-form input:focus { outline:none; border-color:var(--primary); box-shadow:0 0 0 3px var(--primary-light); }
  .ds-hint { font-size:12px; font-weight:400; line-height:18px; color:var(--text-muted); }

  /* ── 가로 탭 ──────────────────────────────────────────
     구획 여섯이 세로로 쌓여 있어 좌표 하나 고치려면 한참 내려가야 했다. 가로로 갈라
     한 번에 한 구획만 보이게 한다. 저장 단추는 탭 밖에 두어 어느 탭에서든 누른다
     (칸은 모두 한 폼 안에 남아 있어, 감춰진 탭의 값도 함께 저장된다).
     줄 자체는 전역 .pnl-tabs / .pnl-tab 을 가져다 쓴다 —
     h44 · pad 0/16 · gap 8 · 13/500 · 밑줄 1px. 전에는 이 화면만 h40 · gap 4 · 13/700 · 밑줄 2px 이었다. */
  .ds-pane { display:none; }
  .ds-pane.active { display:block; }

  /* ── 저장 단추 ────────────────────────────────────────
     카드 오른쪽 아래. 설정 화면 둘(/settings/services · /settings/withworks)이 같은 자리다 —
     카드 오른쪽 안여백 16 에 맞춰 선다. 전에는 카드 밖 왼쪽 아래(x336)에 홀로 떠 있었다.
     margin-top:auto 가 짧은 탭에서 단추를 카드 바닥으로 내려보내고,
     표가 긴 「글자 항목 위치」 탭에서는 0 이 되어 표 바로 아래에 붙는다. */
  .ds-save { display:flex; justify-content:flex-end; padding:12px 16px; margin-top:auto; flex-shrink:0; }

  /* ── 아래를 채운다 ──────────────────────────────────────
     한 번에 탭 하나만 보이는 화면이라, 짧은 탭에서는 흰 카드 아래로 회색이
     754~866 드러났다. 껍데기(.ds-form) → FORM → 흰 .ds-grid-card 순서로
     .fill-rest / .fill-col 이 남는 높이를 내려보낸다.
     늘어나는 것은 카드의 빈 아래쪽이다 — 입력칸·표 행은 제 높이 그대로다.
     판(.ds-pane)에는 .fill-rest 를 걸지 않는다. 그 자리는 저장 단추의 margin-top:auto 가
     대신한다 — min-height:0 인 판이 긴 탭에서 제 내용보다 짧게 줄어드는 일이 없다. */

  /* 성공은 primary 램프로만 표현한다(시안에 초록이 없다) */
  .status-ok { background:var(--primary-50); border:1px solid var(--primary-200); color:var(--primary-700); border-radius:8px;
    padding:12px 16px; font-size:13px; font-weight:500; line-height:21px; }
</style>
@endpush

@section('content')
<div class="ds-form fill-rest fill-col">
  @if(session('status'))
    <div class="status-ok"><i class="bx bx-check-circle"></i> {{ session('status') }}</div>
  @endif
  @if($errors->any())
    <div class="status-ok" style="background:var(--alert-100);border-color:var(--alert-500);color:var(--alert-500);">
      입력값을 확인해 주세요: {{ implode(' / ', $errors->all()) }}
    </div>
  @endif

  {{-- 앞 아이콘은 전역 .ds-grid-hint::before 가 그린다 (12×12 alert-circle · mr 4) --}}
  <div class="ds-grid-hint">여기서 설정한 값은 <b>요양비 지급청구 위임장 PDF</b>의 ② 준요양기관 · ③ 수령계좌 ·
    ⑤ 위임기간과 서명 위치에 자동으로 반영됩니다.</div>

  <form method="POST" action="{{ route('delegation-settings.update') }}" class="fill-rest fill-col">
    @csrf
    @method('PUT')

    <div class="ds-grid-card fill-rest fill-col">
    {{-- 탭줄 — 구획 이름 그대로다 --}}
    <div class="pnl-tabs" role="tablist">
      <button type="button" class="pnl-tab active" data-pane="dsp-provider"><i class="bx bx-buildings"></i> ② 준요양기관</button>
      <button type="button" class="pnl-tab" data-pane="dsp-account"><i class="bx bx-credit-card"></i> ③ 수령계좌</button>
      <button type="button" class="pnl-tab" data-pane="dsp-period"><i class="bx bx-calendar"></i> ⑤ 위임기간</button>
      <button type="button" class="pnl-tab" data-pane="dsp-sig"><i class="bx bx-move"></i> 서명 위치</button>
      <button type="button" class="pnl-tab" data-pane="dsp-gsig"><i class="bx bx-user-check"></i> 보호자 서명 위치</button>
      <button type="button" class="pnl-tab" data-pane="dsp-fields"><i class="bx bx-text"></i> 글자 항목 위치</button>
    </div>

    <div class="ds-pane active" id="dsp-provider">
    <div class="ds-card">
      <h3><i class="bx bx-buildings"></i> ② 준요양기관</h3>
      <div class="ds-grid">
        <div class="ds-field full"><label>상호</label><input type="text" name="provider_name" value="{{ old('provider_name', $setting->provider_name) }}" placeholder="예: 콜로플라스트코리아(주)"></div>
        <div class="ds-field"><label>사업자등록번호</label><input type="text" name="provider_biz_no" value="{{ old('provider_biz_no', $setting->provider_biz_no) }}" placeholder="000-00-00000"></div>
        <div class="ds-field"><label>대표자</label><input type="text" name="provider_ceo" value="{{ old('provider_ceo', $setting->provider_ceo) }}"></div>
        <div class="ds-field"><label>전화번호</label><input type="text" name="provider_phone" value="{{ old('provider_phone', $setting->provider_phone) }}"></div>
      </div>
    </div>

    </div>{{-- /pane --}}

    <div class="ds-pane" id="dsp-account">
    <div class="ds-card">
      <h3><i class="bx bx-credit-card"></i> ③ 요양비 수령계좌</h3>
      <div class="ds-grid">
        <div class="ds-field"><label>수령자</label><input type="text" name="account_receiver" value="{{ old('account_receiver', $setting->account_receiver) }}"></div>
        <div class="ds-field"><label>금융기관명</label><input type="text" name="account_bank" value="{{ old('account_bank', $setting->account_bank) }}"></div>
        <div class="ds-field"><label>예금주</label><input type="text" name="account_holder" value="{{ old('account_holder', $setting->account_holder) }}"></div>
        <div class="ds-field"><label>계좌번호</label><input type="text" name="account_number" value="{{ old('account_number', $setting->account_number) }}"></div>
      </div>
    </div>

    </div>{{-- /pane --}}

    <div class="ds-pane" id="dsp-period">
    <div class="ds-card">
      <h3><i class="bx bx-calendar"></i> ⑤ 위임기간</h3>
      <div class="ds-grid">
        <div class="ds-field"><label>위임기간(년) <span class="ds-hint">최장 5년</span></label><input type="number" name="period_years" value="{{ old('period_years', $setting->period_years) }}" min="1" max="5" required></div>
      </div>
      <div class="ds-hint" style="margin-top:8px;">서명일부터 위 기간만큼 자동 계산됩니다.</div>
    </div>

    </div>{{-- /pane --}}

    <div class="ds-pane" id="dsp-sig">
    <div class="ds-card">
      <h3><i class="bx bx-move"></i> 서명 위치 (원본 PDF 오버레이, 단위 mm)</h3>
      <div class="ds-grid">
        <div class="ds-field"><label>X (좌우) <span class="ds-hint">↑ 오른쪽</span></label><input type="number" step="0.1" name="sig_x" value="{{ old('sig_x', $setting->sig_x) }}" required></div>
        <div class="ds-field"><label>Y (상하) <span class="ds-hint">↑ 아래로</span></label><input type="number" step="0.1" name="sig_y" value="{{ old('sig_y', $setting->sig_y) }}" required></div>
        <div class="ds-field"><label>너비 (크기)</label><input type="number" step="0.1" name="sig_w" value="{{ old('sig_w', $setting->sig_w) }}" required></div>
      </div>
      <div class="ds-hint" style="margin-top:8px;">기본값: X=164, Y=266, 너비=28 (A4 기준, "(서명 또는 인)" 위).</div>
    </div>

    </div>{{-- /pane --}}

    <div class="ds-pane" id="dsp-gsig">
    <div class="ds-card">
      <h3><i class="bx bx-user-check"></i> 보호자 서명 위치 (미성년자, 단위 mm)</h3>
      <div class="ds-grid">
        <div class="ds-field"><label>X (좌우)</label><input type="number" step="0.1" name="gsig_x" value="{{ old('gsig_x', $setting->gsig_x ?? config('delegation.guardian_signature.x')) }}"></div>
        <div class="ds-field"><label>Y (상하)</label><input type="number" step="0.1" name="gsig_y" value="{{ old('gsig_y', $setting->gsig_y ?? config('delegation.guardian_signature.y')) }}"></div>
        <div class="ds-field"><label>너비 (크기)</label><input type="number" step="0.1" name="gsig_w" value="{{ old('gsig_w', $setting->gsig_w ?? config('delegation.guardian_signature.w')) }}"></div>
      </div>
      <div class="ds-hint" style="margin-top:8px;">
        위임인이 만 {{ (int) config('delegation.minor_age', 19) }}세 미만일 때만 찍힙니다.
      </div>
    </div>
    </div>{{-- /pane --}}

    {{-- ── 글자 항목 위치 ────────────────────────────────────
         서명과 같은 방식으로 항목마다 위치를 정한다. 예전에는 이 값들이 코드에 박혀 있어
         양식이 조금만 달라져도 배포를 해야 했다. --}}
    <div class="ds-pane" id="dsp-fields">
    <div class="ds-card">
      <h3><i class="bx bx-text"></i> 글자 항목 위치 (원본 PDF 오버레이, 단위 mm)</h3>
      <div class="ds-hint" style="margin-bottom:12px;">
        X는 왼쪽에서, Y는 위에서 잰 거리입니다(A4 = 210 × 297). 값이 비면 기본값을 씁니다.
        고친 뒤 <b>위임장 PDF</b>를 내려받아 자리를 확인하세요.
      </div>
      <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
          <thead>
            <tr style="background:var(--gray-50);">
              <th style="text-align:left;padding:8px 10px;border-bottom:1px solid var(--border);font-weight:700;">항목</th>
              <th style="text-align:left;padding:8px 10px;border-bottom:1px solid var(--border);font-weight:700;width:110px;">X (좌우)</th>
              <th style="text-align:left;padding:8px 10px;border-bottom:1px solid var(--border);font-weight:700;width:110px;">Y (상하)</th>
              <th style="text-align:left;padding:8px 10px;border-bottom:1px solid var(--border);font-weight:700;width:110px;">글자 크기</th>
            </tr>
          </thead>
          <tbody>
            @foreach($fields as $key => $f)
            <tr>
              <td style="padding:6px 10px;border-bottom:1px solid var(--border-light);">{{ $f['label'] }}</td>
              <td style="padding:6px 10px;border-bottom:1px solid var(--border-light);">
                <input type="number" step="0.1" min="0" max="210" style="width:100%;"
                       name="fields[{{ $key }}][x]" value="{{ old("fields.$key.x", $f['x']) }}">
              </td>
              <td style="padding:6px 10px;border-bottom:1px solid var(--border-light);">
                <input type="number" step="0.1" min="0" max="297" style="width:100%;"
                       name="fields[{{ $key }}][y]" value="{{ old("fields.$key.y", $f['y']) }}">
              </td>
              <td style="padding:6px 10px;border-bottom:1px solid var(--border-light);">
                <input type="number" step="0.5" min="4" max="20" style="width:100%;"
                       name="fields[{{ $key }}][size]" value="{{ old("fields.$key.size", $f['size'] ?? 8) }}">
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
    </div>{{-- /pane --}}

    <div class="ds-save">
      <button type="submit" class="ds-btn ds-btn-primary"><i class="bx bx-save"></i> 설정 저장</button>
    </div>
    </div>{{-- /card --}}
  </form>
</div>

@push('scripts')
<script>
(function () {
  const tabs  = [...document.querySelectorAll('.ds-form .pnl-tab')];
  const panes = [...document.querySelectorAll('.ds-pane')];
  if (!tabs.length) return;

  function show(id) {
    tabs.forEach(t => t.classList.toggle('active', t.dataset.pane === id));
    panes.forEach(p => p.classList.toggle('active', p.id === id));
  }
  tabs.forEach(t => t.addEventListener('click', () => show(t.dataset.pane)));

  /* 감춰진 탭에 빈 필수 칸이 있으면 브라우저가 그 칸으로 가려다 막힌다(보이지 않는 칸은
     초점을 받지 못한다) — 저장이 아무 말 없이 멈춘 것처럼 보인다. 그 칸이 있는 탭을
     먼저 펴 준다. */
  // 이 화면의 폼을 콕 집는다 — 레이아웃에도 폼이 있어 첫 번째를 잡으면 엉뚱한 것을 잡는다
  const form = panes[0]?.closest('form');
  form?.addEventListener('invalid', (e) => {
    const pane = e.target.closest('.ds-pane');
    if (pane && !pane.classList.contains('active')) show(pane.id);
  }, true);
})();
</script>
@endpush
@endsection
