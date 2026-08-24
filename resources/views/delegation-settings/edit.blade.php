@extends('layouts.app')

@section('title', '위임장 설정')
@section('page-title', '요양비 위임장 설정')
@section('breadcrumb', '홈 - 설정 - 위임장 설정')

@push('styles')
<style>
  .ds-form { max-width:100%; }
  /* 카드 2열 배치로 우측 공백 최소화(좁은 화면은 1열) */
  .ds-cards { display:grid; grid-template-columns:repeat(2, 1fr); gap:16px; align-items:start; }
  @media (max-width:820px) { .ds-cards { grid-template-columns:1fr; } }
  .ds-card { background:var(--gray-0); border:1px solid var(--border); border-radius:var(--radius-lg); padding:20px; margin-bottom:0; }
  /* 섹션 제목 = 14px/700 */
  .ds-card h3 { margin:0 0 16px; font-size:14px; font-weight:700; line-height:22px; color:var(--primary);
    padding-bottom:12px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:8px; }
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
  .ds-note { background:var(--primary-light); border:1px solid var(--border); border-radius:8px;
    padding:12px 16px; font-size:12px; font-weight:400; line-height:18px; color:var(--text-secondary); margin-bottom:16px; }
  /* ── 가로 탭 ──────────────────────────────────────────
     구획 여섯이 세로로 쌓여 있어 좌표 하나 고치려면 한참 내려가야 했다. 가로로 갈라
     한 번에 한 구획만 보이게 한다. 저장 단추는 탭 밖에 두어 어느 탭에서든 누른다
     (칸은 모두 한 폼 안에 남아 있어, 감춰진 탭의 값도 함께 저장된다). */
  .ds-tabs { display:flex; gap:4px; flex-wrap:wrap; border-bottom:1px solid var(--border);
             margin-bottom:16px; }
  .ds-tab  { padding:9px 14px; border:none; background:none; cursor:pointer;
             font-size:13px; font-weight:500; line-height:20px; color:var(--gray-700);
             border-bottom:2px solid transparent; margin-bottom:-1px; white-space:nowrap;
             display:inline-flex; align-items:center; gap:6px; }
  .ds-tab:hover  { color:var(--primary); }
  .ds-tab.active { color:var(--primary); font-weight:700; border-bottom-color:var(--primary); }
  .ds-pane { display:none; }
  .ds-pane.active { display:block; }
  /* 탭 안에서는 카드가 하나뿐이라 테두리를 겹쳐 두지 않는다 */
  .ds-pane .ds-card { border:none; padding:0; }
  .ds-pane .ds-card h3 { margin-top:0; }

  /* 성공은 primary 램프로만 표현한다(시안에 초록이 없다) */
  .status-ok { background:var(--primary-50); border:1px solid var(--primary-200); color:var(--primary-700); border-radius:8px;
    padding:12px 16px; font-size:13px; font-weight:500; line-height:21px; margin-bottom:16px; }
</style>
@endpush

@section('content')
<div class="ds-form">
  @if(session('status'))
    <div class="status-ok"><i class="bx bx-check-circle"></i> {{ session('status') }}</div>
  @endif
  @if($errors->any())
    <div class="status-ok" style="background:var(--alert-100);border-color:var(--alert-500);color:var(--alert-500);">
      입력값을 확인해 주세요: {{ implode(' / ', $errors->all()) }}
    </div>
  @endif

  <div class="ds-note">
    <i class="bx bx-info-circle"></i> 여기서 설정한 값은 <b>요양비 지급청구 위임장 PDF</b>의 ② 준요양기관 · ③ 수령계좌 ·
    ⑤ 위임기간과 서명 위치에 자동으로 반영됩니다.
  </div>

  <form method="POST" action="{{ route('delegation-settings.update') }}">
    @csrf
    @method('PUT')

    {{-- 탭줄 — 구획 이름 그대로다 --}}
    <div class="ds-tabs" role="tablist">
      <button type="button" class="ds-tab active" data-pane="dsp-provider"><i class="bx bx-buildings"></i> ② 준요양기관</button>
      <button type="button" class="ds-tab" data-pane="dsp-account"><i class="bx bx-credit-card"></i> ③ 수령계좌</button>
      <button type="button" class="ds-tab" data-pane="dsp-period"><i class="bx bx-calendar"></i> ⑤ 위임기간</button>
      <button type="button" class="ds-tab" data-pane="dsp-sig"><i class="bx bx-move"></i> 서명 위치</button>
      <button type="button" class="ds-tab" data-pane="dsp-gsig"><i class="bx bx-user-check"></i> 보호자 서명 위치</button>
      <button type="button" class="ds-tab" data-pane="dsp-fields"><i class="bx bx-text"></i> 글자 항목 위치</button>
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

    <button type="submit" class="btn btn-primary" style="margin-top:16px;"><i class="bx bx-save"></i> 설정 저장</button>
  </form>
</div>

@push('scripts')
<script>
(function () {
  const tabs  = [...document.querySelectorAll('.ds-tab')];
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
