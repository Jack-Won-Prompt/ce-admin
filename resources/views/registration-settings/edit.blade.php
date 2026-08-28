@extends('layouts.app')

@section('title', '등록 신청서 설정')
@section('page-title', '자가도뇨 등록 신청서 설정')
@section('breadcrumb', '홈 - 설정 - 등록 신청서 설정')

@push('styles')
<style>
  /* 위임장 설정과 같은 규격이다 — 두 화면이 나란히 놓이므로 눈에 같아야 한다.
     (resources/views/delegation-settings/edit.blade.php 의 주석에 까닭이 적혀 있다) */
  .ds-form { max-width:100%; gap:12px; }
  .ds-form .ds-grid-hint { white-space:normal; overflow:visible; text-overflow:clip; margin-right:0; }
  .ds-form .ds-grid-hint > i { font-size:12px; line-height:19px; vertical-align:top; margin-right:4px; }
  .ds-form .ds-grid-hint b { font-weight:700; }
  .ds-form .ds-grid-card { overflow:visible; }
  .ds-card { background:transparent; border:none; border-radius:0; padding:12px 16px; margin-bottom:0; }
  .ds-card h3 { margin:0 0 12px; font-size:14px; font-weight:700; line-height:22px; color:var(--primary);
    display:flex; align-items:center; gap:8px; }
  .ds-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
  .ds-field { display:flex; flex-direction:column; gap:4px; }
  .ds-field.full { grid-column:1 / -1; }
  .ds-field label { font-size:13px; font-weight:500; line-height:21px; color:var(--gray-700); }
  .ds-form input { padding:5px 12px; border:1px solid var(--gray-200); border-radius:8px;
    font-size:13px; font-weight:400; line-height:20px; font-family:inherit;
    color:var(--gray-1000); background:var(--gray-0); }
  .ds-form input:focus { outline:none; border-color:var(--primary); box-shadow:0 0 0 3px var(--primary-light); }
  .ds-hint { font-size:12px; font-weight:400; line-height:18px; color:var(--text-muted); }
  .ds-pane { display:none; }
  .ds-pane.active { display:block; }
  .ds-save { display:flex; justify-content:flex-end; padding:12px 16px; margin-top:auto; flex-shrink:0; }
  .status-ok { background:var(--primary-50); border:1px solid var(--primary-200); color:var(--primary-700); border-radius:8px;
    padding:12px 16px; font-size:13px; font-weight:500; line-height:21px; }

  .pos-tbl { width:100%; border-collapse:collapse; font-size:13px; }
  .pos-tbl th { text-align:left; padding:8px 10px; border-bottom:1px solid var(--border); font-weight:700; }
  .pos-tbl td { padding:6px 10px; border-bottom:1px solid var(--border-light); }
  .pos-tbl input { width:100%; }
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

  <div class="ds-grid-hint">여기서 정한 자리는 <b>자가도뇨 소모성 재료 급여대상자 등록 신청서</b>(별지 제4호서식)
    PDF 에 그대로 반영됩니다. ② 요양기관 확인란의 <b>직인과 의사 서명 자리는 비워</b> 둡니다 —
    확인의 책임은 병원에 남아야 합니다.</div>

  <form method="POST" action="{{ route('registration-settings.update') }}" class="fill-rest fill-col">
    @csrf
    @method('PUT')

    <div class="ds-grid-card fill-rest fill-col">
    <div class="pnl-tabs" role="tablist">
      <button type="button" class="pnl-tab active" data-pane="rsp-sig"><i class="bx bx-move"></i> 신청인 서명 위치</button>
      <button type="button" class="pnl-tab" data-pane="rsp-fields"><i class="bx bx-text"></i> 글자 항목 위치</button>
      <button type="button" class="pnl-tab" data-pane="rsp-checks"><i class="bx bx-check-square"></i> 체크 표시 위치</button>
    </div>

    <div class="ds-pane active" id="rsp-sig">
    <div class="ds-card">
      <h3><i class="bx bx-move"></i> ③ 신청인 서명 위치 (원본 PDF 오버레이, 단위 mm)</h3>
      <div class="ds-grid">
        <div class="ds-field"><label>X (좌우) <span class="ds-hint">↑ 오른쪽</span></label><input type="number" step="0.1" name="sig_x" value="{{ old('sig_x', $setting->sig_x) }}" required></div>
        <div class="ds-field"><label>Y (상하) <span class="ds-hint">↑ 아래로</span></label><input type="number" step="0.1" name="sig_y" value="{{ old('sig_y', $setting->sig_y) }}" required></div>
        <div class="ds-field"><label>너비 (크기)</label><input type="number" step="0.1" name="sig_w" value="{{ old('sig_w', $setting->sig_w) }}" required></div>
      </div>
      <div class="ds-hint" style="margin-top:8px;">
        위임동의 서명 화면에서 받아 둔 그 서명을 그대로 찍습니다. 아직 서명이 없는 건은 자리를 비워
        인쇄해 손으로 받으시면 됩니다.
      </div>
    </div>
    </div>{{-- /pane --}}

    <div class="ds-pane" id="rsp-fields">
    <div class="ds-card">
      <h3><i class="bx bx-text"></i> 글자 항목 위치 (단위 mm)</h3>
      <div class="ds-hint" style="margin-bottom:12px;">
        X는 왼쪽에서, Y는 위에서 잰 거리입니다(A4 = 210 × 297). 고친 뒤
        <b>등록 신청서 PDF</b>를 내려받아 자리를 확인하세요.
      </div>
      <div style="overflow-x:auto;">
        <table class="pos-tbl">
          <thead><tr style="background:var(--gray-50);">
            <th>항목</th><th style="width:110px;">X (좌우)</th><th style="width:110px;">Y (상하)</th><th style="width:110px;">글자 크기</th>
          </tr></thead>
          <tbody>
            @foreach($fields as $key => $f)
            <tr>
              <td>{{ $f['label'] }}</td>
              <td><input type="number" step="0.1" min="0" max="210" name="fields[{{ $key }}][x]" value="{{ old("fields.$key.x", $f['x']) }}"></td>
              <td><input type="number" step="0.1" min="0" max="297" name="fields[{{ $key }}][y]" value="{{ old("fields.$key.y", $f['y']) }}"></td>
              <td><input type="number" step="0.5" min="4" max="20" name="fields[{{ $key }}][size]" value="{{ old("fields.$key.size", $f['size'] ?? 8) }}"></td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
    </div>{{-- /pane --}}

    <div class="ds-pane" id="rsp-checks">
    <div class="ds-card">
      <h3><i class="bx bx-check-square"></i> 체크 표시 위치 (단위 mm)</h3>
      <div class="ds-hint" style="margin-bottom:12px;">
        체크는 글자(✔) 대신 선 두 개로 긋습니다 — 글리프가 폰트마다 깨지기 때문입니다.
        그래서 여기 적는 것은 <b>긋기 시작할 왼쪽 아래 한 점</b>이고, 크기 칸은 없습니다.
      </div>
      <div style="overflow-x:auto;">
        <table class="pos-tbl">
          <thead><tr style="background:var(--gray-50);">
            <th>항목</th><th style="width:110px;">X (좌우)</th><th style="width:110px;">Y (상하)</th>
          </tr></thead>
          <tbody>
            @foreach($checks as $key => $c)
            <tr>
              <td>{{ $c['label'] }}</td>
              <td><input type="number" step="0.1" min="0" max="210" name="checks[{{ $key }}][x]" value="{{ old("checks.$key.x", $c['x']) }}"></td>
              <td><input type="number" step="0.1" min="0" max="297" name="checks[{{ $key }}][y]" value="{{ old("checks.$key.y", $c['y']) }}"></td>
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

  /* 감춰진 탭에 빈 필수 칸이 있으면 브라우저가 그 칸으로 가려다 막힌다 — 저장이 아무 말
     없이 멈춘 것처럼 보인다. 그 칸이 있는 탭을 먼저 펴 준다(위임장 설정과 같다). */
  const form = panes[0]?.closest('form');
  form?.addEventListener('invalid', (e) => {
    const pane = e.target.closest('.ds-pane');
    if (pane && !pane.classList.contains('active')) show(pane.id);
  }, true);
})();
</script>
@endpush
@endsection
