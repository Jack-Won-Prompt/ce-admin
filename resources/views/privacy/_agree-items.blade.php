{{-- 카테터 동의 항목 — 공개 폼과 위임 서명 화면이 함께 쓴다 (2026-09-10 지시).

     본문은 App\Support\ConsentTerms 한 곳에만 있다. 두 화면이 각각 글을 들고 있던
     동안 두 곳의 문구가 서로 달랐고, 기존 동의서와도 달랐다.

     쓰는 쪽이 정해 주는 것
       $idp     아이디 앞머리 — 한 화면에 두 벌이 서면 아이디가 겹친다
       $chip    라디오를 .radio-chip 으로 감쌀지 (공개 폼)
       $detail  「자세히보기」 단추 class
       $box     펼쳐지는 칸 class
       $onchange 고를 때 부를 것 (서명 화면은 refreshAgree())
       $useOld  old() 로 되살릴지 (공개 폼)
       $agreeAttr 동의함 라디오에 붙일 것 (공개 폼의 data-agree)
       $only    data-only 값 — 서명 화면에서 갈래를 가릴 때 쓴다 --}}

@php
  $idp       = $idp       ?? 'ag';
  $chip      = $chip      ?? false;
  $detail    = $detail    ?? 'detail-toggle';
  $box       = $box       ?? 'detail-box';
  $onchange  = $onchange  ?? '';
  $useOld    = $useOld    ?? false;
  $agreeAttr = $agreeAttr ?? '';
  $only      = $only      ?? null;
@endphp

@foreach(App\Support\ConsentTerms::카테터() as $_i => $item)
  <div class="agree-item" @if($only) data-only="{{ $only }}" @endif>
    <div class="agree-head">
      <span class="tag {{ $item['must'] ? 'must' : 'opt' }}">{{ $item['must'] ? '필수' : '선택' }}</span>
      {{ $item['no'] }} {{ $item['title'] }}
    </div>

    @if(!empty($item['ask']))
      <div class="agree-ask">{{ $item['ask'] }}</div>
    @endif

    @foreach($item['fields'] as $_f => $field)
      @php $fid = $idp . $_i . $_f; @endphp

      @if($field['label'])
        <div class="agree-subline">· {{ $field['label'] }}</div>
      @endif

      <div class="agree-radios">
        @foreach(['동의함', '동의하지 않음'] as $_v => $값)
          @php
            $rid  = $fid . ($_v ? 'n' : 'y');
            $찍힘 = $useOld && old($field['name']) === $값;
          @endphp
          @if($chip)
            <div class="radio-chip">
          @else
            <div>
          @endif
            <input type="radio" id="{{ $rid }}" name="{{ $field['name'] }}" value="{{ $값 }}"
                   @if($_v === 0 && $agreeAttr) {!! $agreeAttr !!} @endif
                   @if($_v === 0 && $item['must'] && $useOld) required @endif
                   @if($찍힘) checked @endif
                   @if($onchange) onchange="{{ $onchange }}" @endif>
            <label for="{{ $rid }}">{{ $값 }}</label>
          </div>
        @endforeach
      </div>
    @endforeach

    @if(!empty($item['blocks']))
      @php
        /* 펼침 칸은 pre-line 이라 줄바꿈이 그대로 보인다 — 블레이드가 남기는 들여쓰기가
           빈 줄로 새어 나오지 않게 여기서 한 덩이로 엮는다 */
        $속 = '';
        foreach ($item['blocks'] as $block) {
            if (isset($block['text'])) {
                $속 .= e($block['text']);
            }
            if (isset($block['table'])) {
                $t = '<div class="agree-table-wrap"><table class="agree-table"><thead><tr>';
                foreach ($block['table']['head'] as $h) { $t .= '<th>' . e($h) . '</th>'; }
                $t .= '</tr></thead><tbody>';
                foreach ($block['table']['rows'] as $row) {
                    $t .= '<tr>';
                    foreach ($row as $cell) { $t .= '<td>' . e($cell) . '</td>'; }
                    $t .= '</tr>';
                }
                $속 .= $t . '</tbody></table></div>';
            }
        }
      @endphp
      <button type="button" class="{{ $detail }}" onclick="{{ $chip ? 'toggleDetail' : 'toggleAgreeBox' }}(this)">자세히보기 ▼</button>
      <div class="{{ $box }}">{!! $속 !!}</div>
    @endif
  </div>
@endforeach
