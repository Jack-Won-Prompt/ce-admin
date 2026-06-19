{{-- resources/views/prescriptions/list.blade.php --}}
@extends('layouts.app')

@section('title', '처방전 목록')
@section('page-title', '처방전 목록')
@section('breadcrumb', '홈 / 처방전 목록')

@section('help-title', '처방전 목록 도움말')
@section('help-content')
<div class="help-section">
  <div class="help-section-title">화면 소개</div>
  <div class="help-tip"><i class="bx bx-info-circle"></i>모바일/웹에서 업로드된 처방전을 조회하고 검수·주문 연계를 관리하는 화면입니다.</div>
</div>
<div class="help-section">
  <div class="help-section-title">상태 탭 설명</div>
  <div class="help-item">
    <div class="help-item-icon warn"><i class="bx bx-error"></i></div>
    <div class="help-item-text"><strong>검수 필요</strong>OCR 결과를 사람이 직접 확인해야 하는 처방전입니다. 우선 처리하세요.</div>
  </div>
  <div class="help-item">
    <div class="help-item-icon info"><i class="bx bx-scan"></i></div>
    <div class="help-item-text"><strong>OCR 처리중</strong>자동 인식이 진행 중입니다. 잠시 후 새로고침하세요.</div>
  </div>
  <div class="help-item">
    <div class="help-item-icon success"><i class="bx bx-check-circle"></i></div>
    <div class="help-item-text"><strong>검수 완료</strong>확인된 처방전입니다. 주문 연계 대기 상태입니다.</div>
  </div>
  <div class="help-item">
    <div class="help-item-icon"><i class="bx bx-cart-alt"></i></div>
    <div class="help-item-text"><strong>주문 미등록</strong>검수는 완료됐지만 Withworks 주문이 아직 없는 건입니다.</div>
  </div>
</div>
<div class="help-section">
  <div class="help-section-title">주요 기능</div>
  <div class="help-item">
    <div class="help-item-icon"><i class="bx bx-user-check"></i></div>
    <div class="help-item-text"><strong>담당자 지정</strong>각 행의 담당 셀렉트박스에서 즉시 변경 가능합니다.</div>
  </div>
  <div class="help-item">
    <div class="help-item-icon purple"><i class="bx bx-link-external"></i></div>
    <div class="help-item-text"><strong>주문/SO 번호</strong>Withworks 판매주문번호(SO)가 연계된 경우 파란 모노스페이스 폰트로 표시됩니다.</div>
  </div>
</div>
@endsection

@section('header-actions')
  <a href="{{ route('prescriptions.upload') }}" class="btn btn-primary btn-sm">
    <i class="fa-solid fa-upload"></i> 처방전 업로드
  </a>
@endsection

@push('styles')
<style>
  .filter-bar { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-bottom: 18px; }
  .filter-bar .form-control { height: 36px; font-size: 13px; }
  .filter-bar .btn { height: 36px; white-space: nowrap; }

  /* ── Vuexy pill status tabs ── */
  .status-tabs { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 18px; }
  .status-tab {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 16px; border-radius: 20px; font-size: 12.5px; font-weight: 600;
    border: 1.5px solid var(--border); background: #fff;
    color: var(--text-secondary); cursor: pointer; text-decoration: none;
    transition: var(--transition);
  }
  .status-tab:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-light); }
  .status-tab.active { background: var(--primary); border-color: var(--primary); color: #fff; }
  .status-tab .tab-count {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 20px; height: 18px; padding: 0 6px;
    border-radius: 20px; font-size: 10.5px; font-weight: 700;
    background: rgba(255,255,255,.25); color: inherit;
  }
  .status-tab:not(.active) .tab-count { background: var(--border-light); color: var(--text-muted); }

  .rx-id { font-family: monospace; font-size: 12px; color: var(--primary); font-weight: 700; }
  .rx-date { font-size: 11px; color: var(--text-muted); }
  .ocr-bar { display: flex; align-items: center; gap: 6px; }
  .ocr-bar-track { flex: 1; height: 5px; background: var(--border); border-radius: 3px; min-width: 40px; overflow: hidden; }
  .ocr-bar-fill { height: 100%; border-radius: 3px; }
  .ocr-pct { font-size: 11px; color: var(--text-muted); white-space: nowrap; }
  .table-actions { display: flex; gap: 4px; }
  .empty-state { text-align: center; padding: 56px 24px; color: var(--text-muted); }
  .empty-state i { font-size: 44px; margin-bottom: 12px; display: block; opacity: .3; }
  .empty-state p { font-size: 13px; margin: 0; }

  /* 담당자 인라인 셀렉트 */
  .assign-select {
    font-size: 12px; padding: 3px 24px 3px 10px; height: 28px;
    border: 1.5px dashed var(--border); border-radius: 20px;
    background: var(--bg) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23a5a3ae'/%3E%3C/svg%3E") no-repeat right 8px center;
    background-size: 8px 5px;
    color: var(--text-muted);
    cursor: pointer; max-width: 120px;
    appearance: none; -webkit-appearance: none;
    transition: border-color .15s, background-color .15s, color .15s, box-shadow .15s;
  }
  .assign-select.assigned {
    border-style: solid; border-color: var(--primary);
    background-color: var(--primary-light);
    color: var(--primary); font-weight: 600;
  }
  .assign-select:hover {
    border-color: var(--primary); border-style: solid;
    background-color: var(--primary-light); color: var(--primary);
    box-shadow: 0 0 0 3px rgba(27,102,245,.12);
  }
  .assign-select:focus { outline: none; border-color: var(--primary); border-style: solid;
    box-shadow: 0 0 0 3px rgba(27,102,245,.2); }
  .assign-select.saving { opacity: .5; pointer-events: none; }
</style>
@endpush

@section('content')

  {{-- 상태 탭 --}}
  <div class="status-tabs">
    <a href="{{ route('prescriptions.index') }}"
       class="status-tab {{ !request('status') ? 'active' : '' }}">
      전체 <span class="tab-count">{{ $statusCounts['all'] }}</span>
    </a>
    <a href="{{ route('prescriptions.index', ['status' => 'review_needed'] + request()->except('status', 'page')) }}"
       class="status-tab {{ request('status') === 'review_needed' ? 'active' : '' }}"
       style="{{ !request('status') || request('status') === 'review_needed' ? '' : '' }}">
      검수 필요 <span class="tab-count">{{ $statusCounts['review_needed'] }}</span>
    </a>
    <a href="{{ route('prescriptions.index', ['status' => 'ocr_processing'] + request()->except('status', 'page')) }}"
       class="status-tab {{ request('status') === 'ocr_processing' ? 'active' : '' }}">
      OCR 처리중 <span class="tab-count">{{ $statusCounts['ocr_processing'] }}</span>
    </a>
    <a href="{{ route('prescriptions.index', ['status' => 'approved'] + request()->except('status', 'page')) }}"
       class="status-tab {{ request('status') === 'approved' ? 'active' : '' }}">
      검수 완료 <span class="tab-count">{{ $statusCounts['approved'] }}</span>
    </a>
    <a href="{{ route('prescriptions.index', ['status' => 'no_order'] + request()->except('status', 'page')) }}"
       class="status-tab {{ request('status') === 'no_order' ? 'active' : ($statusCounts['no_order'] > 0 ? 'tab-warn' : '') }}">
      주문 미등록 <span class="tab-count">{{ $statusCounts['no_order'] }}</span>
    </a>
    <a href="{{ route('prescriptions.index', ['status' => 'ordered'] + request()->except('status', 'page')) }}"
       class="status-tab {{ request('status') === 'ordered' ? 'active' : '' }}">
      주문 완료 <span class="tab-count">{{ $statusCounts['ordered'] }}</span>
    </a>
    <a href="{{ route('prescriptions.index', ['status' => 'rejected'] + request()->except('status', 'page')) }}"
       class="status-tab {{ request('status') === 'rejected' ? 'active' : '' }}">
      반려 <span class="tab-count">{{ $statusCounts['rejected'] }}</span>
    </a>
  </div>

  {{-- 검색 / 필터 --}}
  <form method="GET" action="{{ route('prescriptions.index') }}" class="filter-bar">
    @if(request('status'))
      <input type="hidden" name="status" value="{{ request('status') }}">
    @endif
    <input type="text" name="search" class="form-control" style="width:220px;"
           placeholder="처방번호 · 환자명 · 병원명" value="{{ request('search') }}">
    <input type="date" name="date_from" class="form-control" style="width:150px;"
           value="{{ request('date_from', now()->subDays(60)->format('Y-m-d')) }}">
    <span style="font-size:13px;color:var(--text-muted);flex-shrink:0;">~</span>
    <input type="date" name="date_to" class="form-control" style="width:150px;"
           value="{{ request('date_to', now()->format('Y-m-d')) }}">
    <button type="submit" class="btn btn-primary btn-sm">
      <i class="fa-solid fa-magnifying-glass"></i> 검색
    </button>
    @if(request()->hasAny(['search', 'date_from', 'date_to']))
      <a href="{{ route('prescriptions.index', request()->only('status', 'per_page')) }}" class="btn btn-outline btn-sm">
        <i class="fa-solid fa-xmark"></i> 초기화
      </a>
    @endif
    <div style="margin-left:auto;display:flex;align-items:center;gap:10px;">
      <select name="per_page" class="form-control form-select" style="width:100px;height:36px;font-size:13px;"
              onchange="this.form.submit()">
        @foreach([10, 20, 50, 100] as $n)
          <option value="{{ $n }}" {{ (int)request('per_page', 10) === $n ? 'selected' : '' }}>
            {{ $n }}개씩
          </option>
        @endforeach
      </select>
      <span style="font-size:12px;color:var(--text-muted);white-space:nowrap;">
        총 {{ number_format($prescriptions->total()) }}건
      </span>
    </div>
  </form>

  {{-- 목록 테이블 --}}
  <div class="card">
    <div class="card-body" style="padding:0;">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>처방번호</th>
              <th>환자명</th>
              <th>병원</th>
              <th>발행일</th>
              <th>OCR 신뢰도</th>
              <th>상태</th>
              <th>판매유형</th>
              <th>주문 / Withworks SO</th>
              <th>담당</th>
              <th>접수일시</th>
              <th>액션</th>
            </tr>
          </thead>
          <tbody>
            @forelse($prescriptions as $rx)
            <tr>
              <td>
                <a href="{{ route('prescriptions.show', $rx) }}" class="rx-id" style="text-decoration:none;">{{ $rx->rx_number }}</a>
                @if($rx->upload_source === 'mobile')
                  <span class="badge badge-info" style="font-size:9px;padding:1px 5px;margin-left:3px;">모바일</span>
                @endif
              </td>
              <td>
                <b>{{ $rx->patient?->name ?? $rx->patient_name_ocr ?? '-' }}</b>
                @if($rx->patient_name_ocr && $rx->patient?->name && $rx->patient->name !== $rx->patient_name_ocr)
                  <div style="font-size:10px;color:var(--text-muted);">OCR: {{ $rx->patient_name_ocr }}</div>
                @endif
              </td>
              <td style="max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                {{ $rx->hospital_name ?? '-' }}
              </td>
              <td>{{ $rx->issued_date?->format('Y-m-d') ?? '-' }}</td>
              <td>
                @if($rx->ocr_confidence !== null)
                <div class="ocr-bar">
                  <div class="ocr-bar-track">
                    <div class="ocr-bar-fill" style="width:{{ min($rx->display_confidence, 100) }}%;background:{{ $rx->ocr_confidence >= 85 ? 'var(--success)' : ($rx->ocr_confidence >= 70 ? 'var(--warning)' : 'var(--danger)') }};"></div>
                  </div>
                  <span class="ocr-pct">{{ $rx->display_confidence }}%</span>
                </div>
                @else
                  <span style="font-size:11px;color:var(--text-muted);">-</span>
                @endif
              </td>
              <td><span class="badge badge-{{ $rx->status_badge }}">{{ $rx->status_label }}</span></td>
              <td>
                @if($rx->order?->so_type)
                  @php $soLabel = \App\Models\Order::SO_TYPE_LABELS[$rx->order->so_type] ?? [$rx->order->so_type, 'secondary']; @endphp
                  <span class="badge badge-{{ $soLabel[1] }}" style="font-size:11px;">{{ $soLabel[0] }}</span>
                @else
                  <span style="font-size:11px;color:var(--text-muted);">-</span>
                @endif
              </td>
              <td>
                @if($rx->order)
                  <div style="font-size:11px;color:var(--text-secondary);font-weight:600;">
                    {{ $rx->order->order_number }}
                  </div>
                  @if($rx->order->withworks_so_no)
                    <div style="font-size:11px;font-family:monospace;color:var(--primary);font-weight:700;">
                      {{ $rx->order->withworks_so_no }}
                    </div>
                  @else
                    <div style="font-size:10px;color:var(--text-muted);">SO 미연계</div>
                  @endif
                @else
                  <span class="badge badge-secondary" style="font-size:10px;">주문대기</span>
                @endif
              </td>
              <td>
                <select class="assign-select form-select {{ $rx->assigned_user_id ? 'assigned' : '' }}"
                        data-rx="{{ $rx->id }}"
                        data-url="{{ route('prescriptions.assign', $rx) }}"
                        onchange="assignUser(this)">
                  <option value="">— 미지정 —</option>
                  @foreach($managers as $m)
                    <option value="{{ $m->id }}" {{ $rx->assigned_user_id == $m->id ? 'selected' : '' }}>
                      {{ $m->name }}
                    </option>
                  @endforeach
                </select>
              </td>
              <td>
                <div class="rx-date">{{ $rx->created_at->format('Y-m-d') }}</div>
                <div class="rx-date">{{ $rx->created_at->format('H:i') }}</div>
              </td>
              <td>
                <div class="table-actions">
                  <a href="{{ route('prescriptions.show', $rx) }}"
                     class="btn btn-sm {{ in_array($rx->status, ['review_needed', 'ocr_done']) ? 'btn-warning' : 'btn-outline' }}">
                    {{ in_array($rx->status, ['review_needed', 'ocr_done']) ? '검수' : '보기' }}
                  </a>
                </div>
              </td>
            </tr>
            @empty
            <tr>
              <td colspan="11">
                <div class="empty-state">
                  <i class="fa-regular fa-file-medical"></i>
                  <p>처방전이 없습니다.</p>
                </div>
              </td>
            </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
    @if($prescriptions->hasPages())
    <div class="card-footer" style="padding:12px 16px;border-top:1px solid var(--border);">
      {{ $prescriptions->links() }}
    </div>
    @endif
  </div>

@endsection

@push('scripts')
<script>
window.HELP_TOUR_STEPS = [
  {
    selector: '.status-tabs',
    title: '상태 탭',
    body: '처방전을 상태별로 필터링합니다. <b>검수 필요</b> 탭을 먼저 확인하여 처리 대기 중인 처방전을 처리하세요.'
  },
  {
    selector: '.filter-bar',
    title: '검색 및 필터',
    body: '환자명, 처방번호, 병원명으로 검색하거나 날짜 범위를 지정해 조회할 수 있습니다.'
  },
  {
    selector: 'table thead tr',
    title: '목록 컬럼 안내',
    body: '<b>판매유형</b>과 <b>Withworks SO</b> 컬럼에서 주문 연계 상태를 한눈에 확인할 수 있습니다.'
  },
  {
    selector: '.assign-select',
    title: '담당자 지정',
    body: '목록에서 바로 담당자를 변경할 수 있습니다. 변경 즉시 저장됩니다.'
  },
  {
    selector: 'table tbody tr:first-child .table-actions',
    title: '검수/보기 버튼',
    body: '<b>검수 필요</b> 상태이면 주황색 검수 버튼이 표시됩니다. 클릭하면 처방전 상세 화면으로 이동합니다.'
  },
];

async function assignUser(sel) {
  sel.classList.add('saving');
  try {
    const res = await fetch(sel.dataset.url, {
      method: 'PATCH',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        'Accept': 'application/json',
      },
      body: JSON.stringify({ assigned_user_id: sel.value || null }),
    });
    const data = await res.json();
    if (data.success) {
      sel.classList.toggle('assigned', !!sel.value);
      showToast(data.name !== '-' ? `담당자: ${data.name}` : '담당자 해제', 'success');
    } else {
      showToast('저장 실패', 'danger');
    }
  } catch {
    showToast('오류가 발생했습니다.', 'danger');
  } finally {
    sel.classList.remove('saving');
  }
}
</script>
@endpush
