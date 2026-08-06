{{-- 생성 서류 (위임동의서·요양비위임장·팩스통합본 등 시스템 생성 PDF) — $prescription 필요
     시안 137:961. 카드 머리에 제목과 건수, 본문에 서류마다 한 칸씩.
     칸 안은 위·아래 두 줄이다 — 위는 파일 정보, 아래는 보기·다운로드·갱신.
     한 줄에 몰아넣으면 360 폭 뷰어에서 파일명이 먼저 잘린다. --}}
@if($prescription->documents->isNotEmpty())
<div class="vw-card">
  <div class="vw-card-head">
    <span class="vw-card-title">생성 서류 <b>{{ $prescription->documents->count() }}</b></span>
  </div>

  <div class="gd-list">
    @foreach($prescription->documents as $gdoc)
    <div class="gd-item">
      <div class="gd-top">
        <i class="fa-regular fa-file-pdf gd-icon"></i>
        <div class="gd-info">
          <span class="gd-name" title="{{ $gdoc->original_filename }}">{{ $gdoc->original_filename ?: $gdoc->typeLabel() }}</span>
          <span class="gd-meta">
            <span>{{ $gdoc->created_at->format('Y-m-d') }}</span>
            <span>{{ $gdoc->created_at->format('H:i') }}</span>
            <span class="gd-dot"></span>
            <span class="gd-state">{{ $gdoc->sourceLabel() }}</span>
          </span>
        </div>
      </div>

      <div class="gd-acts">
        <a class="gd-btn" href="{{ route('documents.preview', $gdoc) }}" target="_blank">
          <i class="fa-solid fa-eye"></i> 보기
        </a>
        <a class="gd-btn" href="{{ route('documents.download', $gdoc) }}">
          <i class="fa-solid fa-download"></i> 다운로드
        </a>
        @if($gdoc->type === 'delegation')
        <button type="button" class="gd-btn" onclick="regenerateDelegation(this)"
                data-url="{{ route('prescriptions.delegationRegenerate', $prescription) }}"
                title="현재 위임장 설정(기관·계좌·서명위치)으로 내용을 갱신합니다">
          <i class="fa-solid fa-rotate"></i> 갱신
        </button>
        @endif
        @if($gdoc->type === 'fax')
        <button type="button" class="gd-btn" onclick="regenerateFax(this)"
                data-url="{{ route('prescriptions.faxRegenerate', $prescription) }}"
                title="현재 데이터로 팩스통합본을 재생성합니다 (요양비위임장 포함)">
          <i class="fa-solid fa-rotate"></i> 갱신
        </button>
        @endif
      </div>
    </div>
    @endforeach
  </div>
</div>
@endif
