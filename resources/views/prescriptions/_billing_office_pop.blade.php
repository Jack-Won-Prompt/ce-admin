{{-- 관할 청구처 찾기 — 주문 등록의 「청구처」 줄에 붙는 창.

     환자 주소에서 읍ㆍ면ㆍ동을 뽑아 쌓아 둔 청구처를 찾는다. 없으면 공단 지사찾기를
     열어 확인하고, 그 자리에서 등록한다 — 다음 건부터는 바로 뜬다. --}}
<div id="boFindPop" style="display:none;position:absolute;top:calc(100% + 8px);left:0;width:520px;
     background:var(--bg-card);border:1px solid var(--primary);border-radius:var(--radius-lg);
     box-shadow:0 8px 32px rgba(0,0,0,.18);z-index:520;">

  <div style="background:var(--primary);border-radius:var(--radius-lg) var(--radius-lg) 0 0;padding:9px 12px;
       display:flex;align-items:center;gap:8px;">
    <i class="fa-solid fa-building-columns" style="color:#fff;font-size:13px;"></i>
    <span style="font-size:12px;font-weight:700;color:#fff;flex:1;">관할 청구처 찾기</span>
    <button type="button" onclick="boFindClose()"
            style="background:none;border:none;cursor:pointer;color:#fff;font-size:15px;line-height:1;">&times;</button>
  </div>

  <div style="padding:12px;display:flex;flex-direction:column;gap:10px;">
    {{-- 무엇으로 찾고 있는지 먼저 보인다. 못 뽑았으면 못 뽑았다고 적는다. --}}
    <div style="display:flex;align-items:center;gap:6px;font-size:12px;">
      <span style="color:var(--text-muted);flex-shrink:0;">읍ㆍ면ㆍ동</span>
      <input type="text" id="boFindEmd" class="form-control" style="height:30px;width:120px;">
      <span style="color:var(--text-muted);flex-shrink:0;">시군구</span>
      <input type="text" id="boFindSigungu" class="form-control" style="height:30px;width:110px;">
      <button type="button" class="ds-btn" style="height:30px;" onclick="boFindRun()">찾기</button>
    </div>
    <div id="boFindNote" style="font-size:11px;color:var(--text-muted);line-height:1.6;"></div>

    <div id="boFindList" style="display:flex;flex-direction:column;gap:4px;max-height:230px;overflow:auto;"></div>

    <div style="display:flex;gap:6px;border-top:1px dashed var(--border);padding-top:10px;">
      <button type="button" class="ds-btn" onclick="boFindOpenNhis()"
              title="공단 지사찾기 사이트를 새 창으로 엽니다">
        <i class="fa-solid fa-arrow-up-right-from-square"></i> 공단 지사찾기 열기
      </button>
      <span style="flex:1;"></span>
      <button type="button" class="ds-btn ds-btn-primary" onclick="boFindNew()">+ 여기에 등록</button>
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
        <div>
          <label class="ds-field-label">담당자 · 직책</label>
          <div style="display:flex;gap:6px;">
            <input type="text" id="boNewManager" class="form-control" style="height:30px;" placeholder="이름">
            <input type="text" id="boNewTitle" class="form-control" style="height:30px;width:80px;" placeholder="주임">
          </div>
        </div>
        <div style="grid-column:1 / -1;">
          <label class="ds-field-label">담당업무</label>
          <input type="text" id="boNewDuty" class="form-control" style="height:30px;" placeholder="본인부담금환급금, 현금급여비">
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
        <button type="button" class="ds-btn" onclick="boFindNewCancel()">그만두기</button>
        <button type="button" class="ds-btn ds-btn-primary" onclick="boFindNewSave()">등록하고 고르기</button>
      </div>
    </div>
  </div>
</div>
