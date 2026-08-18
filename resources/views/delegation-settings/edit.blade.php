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
  .ds-field input { padding:5px 12px; border:1px solid var(--gray-200); border-radius:8px;
    font-size:13px; font-weight:400; line-height:20px; font-family:inherit; }
  .ds-field input:focus { outline:none; border-color:var(--primary); box-shadow:0 0 0 3px var(--primary-light); }
  .ds-hint { font-size:12px; font-weight:400; line-height:18px; color:var(--text-muted); }
  .ds-note { background:var(--primary-light); border:1px solid var(--border); border-radius:8px;
    padding:12px 16px; font-size:12px; font-weight:400; line-height:18px; color:var(--text-secondary); margin-bottom:16px; }
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

    <div class="ds-cards">
    <div class="ds-card">
      <h3><i class="bx bx-buildings"></i> ② 준요양기관</h3>
      <div class="ds-grid">
        <div class="ds-field full"><label>상호</label><input type="text" name="provider_name" value="{{ old('provider_name', $setting->provider_name) }}" placeholder="예: 콜로플라스트코리아(주)"></div>
        <div class="ds-field"><label>사업자등록번호</label><input type="text" name="provider_biz_no" value="{{ old('provider_biz_no', $setting->provider_biz_no) }}" placeholder="000-00-00000"></div>
        <div class="ds-field"><label>대표자</label><input type="text" name="provider_ceo" value="{{ old('provider_ceo', $setting->provider_ceo) }}"></div>
        <div class="ds-field"><label>전화번호</label><input type="text" name="provider_phone" value="{{ old('provider_phone', $setting->provider_phone) }}"></div>
      </div>
    </div>

    <div class="ds-card">
      <h3><i class="bx bx-credit-card"></i> ③ 요양비 수령계좌</h3>
      <div class="ds-grid">
        <div class="ds-field"><label>수령자</label><input type="text" name="account_receiver" value="{{ old('account_receiver', $setting->account_receiver) }}"></div>
        <div class="ds-field"><label>금융기관명</label><input type="text" name="account_bank" value="{{ old('account_bank', $setting->account_bank) }}"></div>
        <div class="ds-field"><label>예금주</label><input type="text" name="account_holder" value="{{ old('account_holder', $setting->account_holder) }}"></div>
        <div class="ds-field"><label>계좌번호</label><input type="text" name="account_number" value="{{ old('account_number', $setting->account_number) }}"></div>
      </div>
    </div>

    <div class="ds-card">
      <h3><i class="bx bx-calendar"></i> ⑤ 위임기간</h3>
      <div class="ds-grid">
        <div class="ds-field"><label>위임기간(년) <span class="ds-hint">최장 5년</span></label><input type="number" name="period_years" value="{{ old('period_years', $setting->period_years) }}" min="1" max="5" required></div>
      </div>
      <div class="ds-hint" style="margin-top:8px;">서명일부터 위 기간만큼 자동 계산됩니다.</div>
    </div>

    <div class="ds-card">
      <h3><i class="bx bx-move"></i> 서명 위치 (원본 PDF 오버레이, 단위 mm)</h3>
      <div class="ds-grid">
        <div class="ds-field"><label>X (좌우) <span class="ds-hint">↑ 오른쪽</span></label><input type="number" step="0.1" name="sig_x" value="{{ old('sig_x', $setting->sig_x) }}" required></div>
        <div class="ds-field"><label>Y (상하) <span class="ds-hint">↑ 아래로</span></label><input type="number" step="0.1" name="sig_y" value="{{ old('sig_y', $setting->sig_y) }}" required></div>
        <div class="ds-field"><label>너비 (크기)</label><input type="number" step="0.1" name="sig_w" value="{{ old('sig_w', $setting->sig_w) }}" required></div>
      </div>
      <div class="ds-hint" style="margin-top:8px;">기본값: X=164, Y=266, 너비=28 (A4 기준, "(서명 또는 인)" 위).</div>
    </div>

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
    </div>{{-- /ds-cards --}}

    {{-- ── 글자 항목 위치 ────────────────────────────────────
         서명과 같은 방식으로 항목마다 위치를 정한다. 예전에는 이 값들이 코드에 박혀 있어
         양식이 조금만 달라져도 배포를 해야 했다. --}}
    <div class="ds-card" style="margin-top:16px;">
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

    <button type="submit" class="btn btn-primary" style="margin-top:16px;"><i class="bx bx-save"></i> 설정 저장</button>
  </form>
</div>
@endsection
