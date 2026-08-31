{{-- 관할 청구처 찾기 — 주문 등록의 「청구처」 줄에 붙는 창.

     환자 주소에서 읍ㆍ면ㆍ동을 뽑아 쌓아 둔 청구처를 찾는다. 없으면 공단 지사찾기를
     열어 확인하고, 그 자리에서 등록한다 — 다음 건부터는 바로 뜬다. --}}
<div id="boFindPop" style="display:none;position:absolute;top:calc(100% + 8px);left:0;width:520px;
     background:var(--bg-card);border:1px solid var(--primary);border-radius:var(--radius-lg);
     box-shadow:0 8px 32px rgba(0,0,0,.18);z-index:520;">

  {{-- 머리를 잡아 옮긴다. 이 창이 가리는 것이 하필 주소ㆍ상병ㆍ수량 줄이라, 밖에서
       물어 온 후보가 우리 주소와 맞는지 견주려면 닫았다 열었다 해야 했다. --}}
  <div id="boFindHead"
       style="background:var(--primary);border-radius:var(--radius-lg) var(--radius-lg) 0 0;padding:9px 12px;
       display:flex;align-items:center;gap:8px;cursor:move;user-select:none;">
    <i class="fa-solid fa-building-columns" style="color:#fff;font-size:13px;"></i>
    <span style="font-size:12px;font-weight:700;color:#fff;flex:1;">관할 청구처 찾기</span>
    <i class="fa-solid fa-up-down-left-right" title="제목 표시줄을 끌어 이동"
       style="color:#fff;opacity:.7;font-size:11px;"></i>
    <button type="button" onclick="boFindClose()"
            style="background:none;border:none;cursor:pointer;color:#fff;font-size:15px;line-height:1;">&times;</button>
  </div>

  <div style="padding:12px;display:flex;flex-direction:column;gap:10px;">
    {{-- 할 일 둘을 첫 줄에 둔다(요청서 15쪽). 읍ㆍ면ㆍ동을 직접 적어 찾는 줄은 걷었다 —
         환자 주소에서 뽑아 창이 열리자마자 찾으므로 손으로 칠 일이 없었고, 못 뽑는
         경우에는 어차피 공단 지사찾기로 확인해 「여기에 등록」하는 것이 길이다.
         두 칸은 숨겨 남긴다 — 찾기ㆍ등록이 그 값을 읽고 쓴다. --}}
    <input type="hidden" id="boFindEmd">
    <input type="hidden" id="boFindSigungu">

    <div style="display:flex;gap:6px;">
      <button type="button" class="ds-btn" onclick="boFindOpenNhis()"
              title="공단 지사찾기 사이트를 새 창으로 엽니다">
        <i class="fa-solid fa-arrow-up-right-from-square"></i> 공단 지사찾기
      </button>
      <span style="flex:1;"></span>
      <button type="button" class="ds-btn ds-btn-primary" onclick="boFindNew()">등록하기</button>
    </div>

    {{-- 무엇으로 찾았는지ㆍ찾은 것이 있는지는 여기로 알린다 --}}
    <div id="boFindNote" style="font-size:11px;color:var(--text-muted);line-height:1.6;"></div>

    <div id="boFindList" style="display:flex;flex-direction:column;gap:4px;max-height:230px;overflow:auto;"></div>

    {{-- 밖에서 물어 온 후보 — 우리 표에 아직 없을 때만 선다.
         공단 지사찾기와 카카오 로컬에 대신 물은 것이다. 고르면 아래 등록 칸이
         그 값으로 채워져, 사람은 부서ㆍ팩스만 보태고 누른다. --}}
    <div id="boOuter" style="display:none;flex-direction:column;gap:6px;border-top:1px dashed var(--border);padding-top:10px;">
      <div style="display:flex;align-items:center;gap:6px;">
        <span style="font-size:11px;font-weight:700;color:var(--gray-700);">밖에 물어 본 것</span>
        <span id="boOuterNote" style="font-size:11px;color:var(--text-muted);flex:1;"></span>
      </div>
      <div id="boOuterList" style="display:flex;flex-direction:column;gap:4px;max-height:190px;overflow:auto;"></div>
    </div>

    {{-- 그 자리 등록 — 공단 사이트에서 확인한 것을 옮겨 적는다.
         관할 읍면동은 위에서 찾던 값이 그대로 들어간다. --}}
    <div id="boFindForm" style="display:none;border-top:1px dashed var(--border);padding-top:10px;
         display:none;flex-direction:column;gap:8px;">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
        <div>
          <label class="ds-field-label">구분</label>
          <select id="boNewKind" class="form-control" style="height:30px;">
            <option value="nhis">건강보험공단</option>
            <option value="local">지자체</option>
          </select>
        </div>
        <div>
          <label class="ds-field-label">기관명 *</label>
          <input type="text" id="boNewOffice" class="form-control" style="height:30px;" placeholder="마포지사">
        </div>
        <div>
          <label class="ds-field-label">부서</label>
          <input type="text" id="boNewDept" class="form-control" style="height:30px;" placeholder="보험급여부">
        </div>
        {{-- 담당자ㆍ직책ㆍ담당업무는 뺐다(요청서 15쪽). 사람은 자주 바뀌는데 한 번 적어
             두면 그대로 남아, 몇 달 뒤에는 없는 사람 이름이 서류에 실렸다.
             대신 적어 둘 곳이 필요해 참고사항을 둔다 — 「팩스는 오후에만 받는다」 같은 것. --}}
        <div style="grid-column:1 / -1;">
          <label class="ds-field-label">참고사항</label>
          <input type="text" id="boNewNote" class="form-control" style="height:30px;"
                 placeholder="예: 팩스 접수는 오후에만 · 접수 뒤 전화 확인 필요">
        </div>
        <div>
          <label class="ds-field-label">전화번호</label>
          <input type="text" id="boNewTel" class="form-control" style="height:30px;" data-phone>
        </div>
        <div>
          <label class="ds-field-label">팩스번호</label>
          <input type="text" id="boNewFax" class="form-control" style="height:30px;" data-phone>
        </div>
      </div>
      <div id="boNewMsg" style="display:none;font-size:11px;"></div>
      <div style="display:flex;gap:6px;justify-content:flex-end;">
        <button type="button" class="ds-btn" onclick="boFindNewCancel()">종료</button>
        <button type="button" class="ds-btn ds-btn-primary" onclick="boFindNewSave()">저장 및 선택</button>
      </div>
    </div>
  </div>
</div>
