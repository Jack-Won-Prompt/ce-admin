{{-- 항목 묶음 하나. 공단 화면의 한 구획에 대응한다. --}}
@php
  $slug = \Illuminate\Support\Str::slug($name);
@endphp
<div class="grp" data-copy-scope>
  <div class="grp-hd">
    <span class="grp-name">{{ $name }}</span>
    <button class="cbtn" onclick="copyGroup(this)">그룹 복사</button>
  </div>

  @foreach($rows as $i => $r)
    @php
      $key     = $slug.'-'.$i;
      $canCopy = ($r['copy'] ?? true) && ($r['value'] ?? null) !== null;
      $isEmpty = ($r['copy'] ?? true) && ($r['value'] ?? null) === null;
    @endphp
    <div class="row {{ $isEmpty ? 'empty' : '' }}" data-key="{{ $key }}"
         data-copy="{{ $canCopy ? '1' : '0' }}"
         @if($r['reveal'] ?? false) data-reveal="1" @endif>
      <div class="lbl">{{ $r['label'] }}</div>
      <div>
        <div class="val {{ $isEmpty ? 'none' : '' }}" data-val>{{ $r['value'] ?? ($r['blank'] ?? '데이터 없음') }}</div>
        @if($r['note'] ?? null)<div class="note">{{ $r['note'] }}</div>@endif
        @if($r['warn'] ?? null)<div class="note warn">⚠ {{ $r['warn'] }}</div>@endif
      </div>
      @if($r['fixed'] ?? false)<span class="tag">고정</span>@endif
      @if($r['ask'] ?? false)<span class="tag ask">확인 필요</span>@endif
      <div class="grow"></div>
      @if(($r['copy'] ?? true))
        <button class="cbtn" onclick="copyRow(this)" {{ $canCopy ? '' : 'disabled' }}>복사</button>
      @endif
    </div>
  @endforeach
</div>
