{{-- 서류를 한꺼번에 내려받는 두 단추 (2026-09-28 지시).

     문서 카드 머리(「첨부문서 추가」 옆) 한 자리에만 선다. 한때 썸네일 줄 아래에도
     같은 두 단추를 두었는데(썸네일이 길어지면 카드 머리가 화면 위로 밀려나서),
     한 화면에 같은 단추가 두 벌 서서 어느 것이 무엇인지 묻게 되어 걷었다
     (2026-09-29 지시).

     따로 두는 까닭은 markup 을 두 번 적지 않기 위해서다 — 자리를 다시 늘릴 일이
     생기면 `$자리` 로 id 만 갈라 붙인다(둘이 같으면 누르는 동안 바뀌는 글이 엉뚱한
     쪽에 선다).

     $자리 — 지금은 'head' 하나뿐 --}}
@php $_자리 = $자리 ?? 'head'; @endphp

<button type="button" class="vw-btn-sm" id="btnDocsMerged-{{ $_자리 }}"
        onclick="downloadDocsMerged(event)"
        title="올린 서류와 만들어진 서류를 한 PDF 로 묶어 내려받습니다 — 공단에 낼 한 벌">
  <i class="fa-regular fa-file-pdf"></i> 모두 PDF로 다운로드
</button>
<button type="button" class="vw-btn-sm" id="btnDocsZip-{{ $_자리 }}"
        onclick="downloadDocsZip(event)"
        title="올라온 파일을 그대로 압축해 내려받습니다 — 원본이 그대로 필요할 때">
  <i class="fa-solid fa-file-zipper"></i> 모두 압축파일 다운로드
</button>
