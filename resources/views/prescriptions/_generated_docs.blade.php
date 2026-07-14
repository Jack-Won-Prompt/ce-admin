{{-- 생성 서류 (위임동의서·요양비위임장·팩스통합본 등 시스템 생성 PDF) — $prescription 필요 --}}
@if($prescription->documents->isNotEmpty())
<div class="mt-3">
  <div style="font-size:11px;font-weight:700;color:var(--text-secondary);margin-bottom:6px;display:flex;align-items:center;gap:6px;">
    <i class="fa-solid fa-file-lines"></i> 생성 서류 (<span>{{ $prescription->documents->count() }}</span>건)
  </div>
  <div style="display:flex;flex-direction:column;gap:5px;">
    @foreach($prescription->documents as $gdoc)
    <div style="display:flex;align-items:center;gap:8px;padding:7px 10px;border:1px solid var(--border);border-radius:var(--radius);font-size:12px;background:var(--bg-card);">
      <i class="fa-regular fa-file-pdf" style="color:var(--danger);font-size:17px;flex-shrink:0;"></i>
      <div style="flex:1;min-width:0;">
        <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
          <span style="font-weight:700;">{{ $gdoc->typeLabel() }}</span>
          <span style="font-size:10px;background:var(--success-light);color:var(--success);border:1px solid #86efac;border-radius:3px;padding:1px 5px;">{{ $gdoc->sourceLabel() }}</span>
        </div>
        <div style="font-size:10px;color:var(--text-muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $gdoc->created_at->format('Y-m-d H:i') }} · {{ $gdoc->original_filename }}</div>
      </div>
      <a href="{{ route('documents.preview', $gdoc) }}" target="_blank" class="btn btn-outline btn-sm" style="padding:3px 9px;font-size:11px;white-space:nowrap;"><i class="fa-solid fa-eye"></i> 보기</a>
      <a href="{{ route('documents.download', $gdoc) }}" class="btn btn-outline btn-sm" style="padding:3px 9px;font-size:11px;white-space:nowrap;" title="다운로드"><i class="fa-solid fa-download"></i></a>
      @if($gdoc->type === 'delegation')
      <button type="button" onclick="regenerateDelegation(this)"
              data-url="{{ route('prescriptions.delegationRegenerate', $prescription) }}"
              class="btn btn-outline btn-sm" style="padding:3px 9px;font-size:11px;white-space:nowrap;"
              title="현재 위임장 설정(기관·계좌·서명위치)으로 내용을 갱신합니다"><i class="fa-solid fa-rotate"></i> 갱신</button>
      @endif
      @if($gdoc->type === 'fax')
      <button type="button" onclick="regenerateFax(this)"
              data-url="{{ route('prescriptions.faxRegenerate', $prescription) }}"
              class="btn btn-outline btn-sm" style="padding:3px 9px;font-size:11px;white-space:nowrap;"
              title="현재 데이터로 팩스통합본을 재생성합니다 (요양비위임장 포함)"><i class="fa-solid fa-rotate"></i> 갱신</button>
      @endif
    </div>
    @endforeach
  </div>
</div>
@endif
