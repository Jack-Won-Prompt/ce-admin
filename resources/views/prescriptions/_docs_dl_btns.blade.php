{{-- 서류를 한꺼번에 내려받는 두 단추 (2026-09-28 지시).

     화면의 두 자리에 똑같이 선다 — 문서 카드 머리(「첨부문서 추가」 옆)와 미리보기
     썸네일 줄 바로 아래. 서류가 여럿이면 썸네일 줄이 길어져 카드 머리가 화면 위로
     밀려나므로, 보고 있던 자리에서 손을 떼지 않고 누를 수 있어야 한다.

     같은 markup 을 두 번 적지 않는다 — 한쪽만 고치면 두 자리가 다른 일을 한다.
     id 는 자리마다 갈라 붙인다(둘이 같으면 누르는 동안 바뀌는 글이 엉뚱한 쪽에 선다).

     $자리 — 'head' 또는 'foot' --}}
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
