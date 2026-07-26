@extends('layouts.app')

@section('title', '관리자 관리')
@section('page-title', '관리자 관리')
@section('breadcrumb', '홈 / 관리자 관리')

@section('content')
<div class="content-header">
  <h4 class="content-title"><i class="bx bx-shield-quarter" style="color:var(--primary);"></i> 관리자 관리</h4>
  <p class="content-sub">시스템 관리자 계정을 추가·수정·비활성화합니다.</p>
</div>

<div class="card">
  <div class="card-body" style="padding:0;">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px 12px;">
      <span style="font-size:13px;color:var(--text-muted);">총 <b id="userCount">{{ $users->count() }}</b>명</span>
      <div style="display:flex;gap:8px;">
        <button class="btn btn-sm" style="border:1.5px solid var(--primary);color:var(--primary);background:#fff;" onclick="openInviteModal()">
          <i class="bx bx-envelope"></i> 이메일로 초대
        </button>
        <button class="btn btn-primary btn-sm" onclick="openModal()">
          <i class="bx bx-plus"></i> 관리자 추가
        </button>
      </div>
    </div>

    {{-- ── 관리자 목록 (wwGrid) ── --}}
    <div style="display:flex;gap:8px;margin:0 20px 12px;align-items:center;">
      <button type="button" class="btn btn-outline btn-sm" onclick="usersEditSelected()">
        <i class="bx bx-edit"></i> 선택 수정
      </button>
      <span style="font-size:12px;color:var(--text-muted);">← 행 체크 후 수정</span>
      <span class="badge bg-label-primary" style="margin-left:auto;">전체 {{ number_format($total) }}명</span>
    </div>
    <div style="padding:0 20px 20px;"><div id="usersGrid"></div></div>
  </div>
</div>

{{-- ── 초대 현황 카드 ── --}}
<div class="card" style="margin-top:20px;">
  <div class="card-body" style="padding:0;">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px 12px;">
      <div style="display:flex;align-items:center;gap:8px;">
        <i class="bx bx-envelope-open" style="color:var(--primary);font-size:16px;"></i>
        <span style="font-size:14px;font-weight:700;">초대 현황</span>
        <span id="inviteBadge" style="display:none;background:var(--primary-light);color:var(--primary);font-size:11px;font-weight:700;padding:1px 8px;border-radius:10px;"></span>
      </div>
      <button class="btn btn-sm btn-outline" onclick="loadInvitations()" id="refreshBtn" title="새로고침" style="padding:3px 10px;">
        <i class="bx bx-refresh"></i>
      </button>
    </div>

    {{-- ── 초대 현황 목록 (wwGrid) ── --}}
    <div style="display:flex;gap:8px;margin:0 20px 12px;align-items:center;flex-wrap:wrap;">
      <button type="button" class="btn btn-outline btn-sm" onclick="invResendSelected()">
        <i class="bx bx-send"></i> 선택 재발송
      </button>
      <button type="button" class="btn btn-outline btn-sm" style="color:var(--danger);border-color:var(--danger);" onclick="invCancelSelected()">
        <i class="bx bx-trash"></i> 선택 초대취소
      </button>
      <span style="font-size:12px;color:var(--text-muted);">← 행 체크 후 실행 (수락된 초대는 불가)</span>
    </div>
    <div style="padding:0 20px 20px;"><div id="invitationsGrid"></div></div>
  </div>
</div>

{{-- ── 초대 모달 ── --}}
<div id="inviteModal" class="modal-backdrop" onclick="if(event.target===this)closeInviteModal()">
  <div class="modal-panel" onclick="event.stopPropagation()" style="max-width:420px;">
    <div class="modal-header">
      <span class="modal-title"><i class="bx bx-envelope" style="color:var(--primary);"></i> 이메일로 초대</span>
      <button class="modal-close" onclick="closeInviteModal()"><i class="bx bx-x"></i></button>
    </div>
    <div class="modal-body">
      <p style="font-size:13px;color:var(--text-muted);margin-bottom:18px;">
        초대 링크가 담긴 이메일이 발송됩니다. 수신자가 링크를 클릭하면 이름·비밀번호를 설정하고 계정이 활성화됩니다.
      </p>
      <div class="form-group">
        <label class="form-label">초대할 이메일 <span style="color:var(--danger);">*</span></label>
        <input type="email" class="form-control" id="inviteEmail" placeholder="example@domain.com" maxlength="200">
      </div>
      <div class="form-group">
        <label class="form-label">역할 <span style="color:var(--danger);">*</span></label>
        <select class="form-control form-select" id="inviteRole">
          <option value="manager">매니저 (Manager)</option>
          <option value="admin">관리자 (Admin)</option>
        </select>
      </div>
      <div class="form-group" style="margin-bottom:0;">
        <label class="form-label">초대 메시지 <span style="color:var(--text-muted);font-size:11px;">(선택)</span></label>
        <textarea class="form-control" id="inviteMessage" rows="3"
          placeholder="함께하게 되어 반갑습니다. 궁금한 점이 있으면 언제든지 연락 주세요."
          maxlength="500" style="resize:vertical;min-height:72px;"></textarea>
        <div style="font-size:11px;color:var(--text-muted);margin-top:4px;text-align:right;">
          <span id="msgCount">0</span>/500
        </div>
      </div>
      <div id="inviteError" style="display:none;background:#FEF2F2;color:var(--danger);padding:10px 14px;border-radius:8px;font-size:13px;margin-top:12px;"></div>
      <div id="inviteSuccess" style="display:none;background:#F0FDF4;color:#16A34A;padding:10px 14px;border-radius:8px;font-size:13px;margin-top:12px;"></div>
    </div>
    <div class="modal-footer">
      <div style="margin-left:auto;display:flex;gap:8px;">
        <button type="button" class="btn btn-sm btn-outline" onclick="closeInviteModal()">취소</button>
        <button type="button" class="btn btn-sm btn-primary" id="inviteBtn" onclick="sendInvite()">
          <i class="bx bx-send"></i> 초대 발송
        </button>
      </div>
    </div>
  </div>
</div>

{{-- ── 편집 모달 ── --}}
<div id="userModal" class="modal-backdrop" onclick="if(event.target===this)closeModal()">
  <div class="modal-panel" onclick="event.stopPropagation()">
    <div class="modal-header">
      <span id="modalTitle" class="modal-title"><i class="bx bx-user-plus" style="color:var(--primary);"></i> 관리자 추가</span>
      <button class="modal-close" onclick="closeModal()"><i class="bx bx-x"></i></button>
    </div>
    <div class="modal-body">
      <form id="userForm" onsubmit="submitForm(event)">
        <input type="hidden" id="formUserId" value="">

        <div class="form-row2">
          <div class="form-group">
            <label class="form-label">이름 <span style="color:var(--danger);">*</span></label>
            <input type="text" class="form-control" id="fName" placeholder="홍길동" required maxlength="100">
          </div>
          <div class="form-group">
            <label class="form-label">역할 <span style="color:var(--danger);">*</span></label>
            <select class="form-control form-select" id="fRole">
              <option value="admin">관리자 (Admin)</option>
              <option value="manager">매니저 (Manager)</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">이메일 <span style="color:var(--danger);">*</span></label>
          <input type="email" class="form-control" id="fEmail" placeholder="example@domain.com" required maxlength="200">
        </div>

        <div class="form-group">
          <label class="form-label">휴대폰</label>
          <input type="text" class="form-control" id="fPhone" placeholder="010-0000-0000" data-phone maxlength="20">
        </div>

        <div class="form-group">
          <label class="form-label" id="pwLabel">비밀번호 <span style="color:var(--danger);">*</span></label>
          <div style="position:relative;">
            <input type="password" class="form-control" id="fPassword" placeholder="8자 이상" minlength="8" style="padding-right:36px;">
            <button type="button" onclick="togglePw()" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-muted);padding:0;">
              <i class="bx bx-show" id="pwEye"></i>
            </button>
          </div>
          <div id="pwHint" style="font-size:11px;color:var(--text-muted);margin-top:4px;display:none;">
            빈칸으로 두면 기존 비밀번호를 유지합니다.
          </div>
        </div>

        <div class="form-group" id="activeToggleWrap">
          <label class="form-label">계정 상태</label>
          <label style="display:flex;align-items:center;gap:10px;cursor:pointer;user-select:none;">
            <div class="toggle-switch" onclick="toggleActive(this)">
              <input type="checkbox" id="fIsActive" style="display:none;" checked>
              <div class="toggle-track" id="toggleTrack">
                <div class="toggle-thumb"></div>
              </div>
            </div>
            <span id="activeLabel" style="font-size:13px;font-weight:600;color:var(--success);">활성</span>
          </label>
        </div>

        <div id="formError" style="display:none;background:#FEF2F2;color:var(--danger);padding:10px 14px;border-radius:8px;font-size:13px;margin-top:8px;"></div>
      </form>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-sm btn-outline" id="deleteBtn" onclick="deleteUser()" style="display:none;color:var(--danger);border-color:var(--danger);">
        <i class="bx bx-trash"></i> 삭제
      </button>
      <div style="display:flex;gap:8px;margin-left:auto;">
        <button type="button" class="btn btn-sm btn-outline" onclick="closeModal()">취소</button>
        <button type="button" class="btn btn-sm btn-primary" id="submitBtn" onclick="submitForm(event)">
          <i class="bx bx-save"></i> 저장
        </button>
      </div>
    </div>
  </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('vendor/wwgrid/wwGrid.css') }}?v=4">
<style>
/* ── 모달 ── */
.modal-backdrop { display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:2000; align-items:center; justify-content:center; }
.modal-backdrop.open { display:flex; }
.modal-panel { background:#fff; border-radius:16px; width:100%; max-width:480px; margin:16px; box-shadow:0 20px 60px rgba(0,0,0,.2); animation:modalIn .2s ease; overflow:hidden; }
@keyframes modalIn { from { transform:translateY(20px); opacity:0; } to { transform:translateY(0); opacity:1; } }
.modal-header { display:flex; align-items:center; justify-content:space-between; padding:18px 20px 14px; border-bottom:1px solid var(--border); }
.modal-title  { font-size:15px; font-weight:700; }
.modal-close  { background:none; border:none; cursor:pointer; color:var(--text-muted); font-size:20px; line-height:1; padding:0; }
.modal-body   { padding:20px; max-height:70vh; overflow-y:auto; }
.modal-footer { display:flex; align-items:center; padding:14px 20px; border-top:1px solid var(--border); gap:8px; }
.form-row2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.form-group { margin-bottom:14px; }
.form-group:last-child { margin-bottom:0; }

/* ── 토글 스위치 ── */
.toggle-track { width:44px; height:24px; border-radius:12px; background:var(--border); position:relative; transition:background .2s; cursor:pointer; }
.toggle-track.on { background:var(--success); }
.toggle-thumb { position:absolute; top:3px; left:3px; width:18px; height:18px; border-radius:50%; background:#fff; transition:transform .2s; box-shadow:0 1px 4px rgba(0,0,0,.2); }
.toggle-track.on .toggle-thumb { transform:translateX(20px); }
</style>
@endpush

@push('scripts')
<script src="{{ asset('vendor/wwgrid/wwGrid.js') }}?v=4"></script>
<script>
const USERS_DATA          = @json($usersData);
const USERS_GRID_DATA     = @json($gridData);
const CSRF                = '{{ csrf_token() }}';
const ME                  = {{ auth()->id() }};
const USERS_BASE_URL      = '{{ url("admin/users") }}';
const INVITE_URL          = '{{ route("admin.users.invite") }}';
const INVITATIONS_URL     = '{{ route("admin.users.invitations") }}';

let usersMap = {};
USERS_DATA.forEach(u => usersMap[u.id] = u);

document.addEventListener('DOMContentLoaded', loadInvitations);

// ── 초대 현황 ──────────────────────────────────────────
async function loadInvitations() {
  const btn  = document.getElementById('refreshBtn');
  btn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i>';
  btn.disabled  = true;

  try {
    const res  = await fetch(INVITATIONS_URL, { headers: { 'Accept': 'application/json' } });
    const data = await res.json();
    renderInvitations(data.invitations || []);
  } catch {
    showToast('초대 목록을 불러오지 못했습니다.', 'error');
  } finally {
    btn.innerHTML = '<i class="bx bx-refresh"></i>';
    btn.disabled  = false;
  }
}

function renderInvitations(list) {
  const badge = document.getElementById('inviteBadge');

  const pending = list.filter(i => i.status === 'pending').length;
  if (pending > 0) {
    badge.textContent    = `대기 ${pending}`;
    badge.style.display  = 'inline-block';
  } else {
    badge.style.display  = 'none';
  }

  const statusText = { pending: '대기중', accepted: '수락됨', expired: '만료됨' };

  const rows = list.map(inv => ({
    id:         inv.id,
    email:      inv.email,
    role:       inv.role === 'admin' ? '관리자' : '매니저',
    status:     statusText[inv.status] ?? inv.status,
    invited_by: inv.invited_by,
    created:    inv.created_at,
    date:       inv.status === 'accepted' ? inv.accepted_at : inv.expires_at,
    accepted:   inv.status === 'accepted',
  }));

  if (window.__invGrid) window.__invGrid.setData(rows);
}

async function resendInvitation(id) {
  if (!confirm('초대 이메일을 재발송하시겠습니까?\n기존 링크는 만료되고 새 링크가 발송됩니다.')) return;

  try {
    const res  = await fetch(`${INVITATIONS_URL}/${id}/resend`, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
    });
    const data = await res.json();
    if (!data.success) { showToast(data.message || '재발송 실패', 'error'); return; }
    showToast('재발송되었습니다.', 'success');
    loadInvitations();
  } catch {
    showToast('서버 오류', 'error');
  }
}

async function cancelInvitation(id) {
  if (!confirm('초대를 취소하시겠습니까?')) return;

  try {
    const res  = await fetch(`${INVITATIONS_URL}/${id}`, {
      method: 'DELETE',
      headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
    });
    const data = await res.json();
    if (!data.success) { showToast(data.message || '취소 실패', 'error'); return; }
    showToast('초대가 취소되었습니다.', 'success');
    loadInvitations();
  } catch {
    showToast('서버 오류', 'error');
  }
}

// ── 초대 모달 ──────────────────────────────────────────
function openInviteModal() {
  document.getElementById('inviteEmail').value   = '';
  document.getElementById('inviteRole').value    = 'manager';
  document.getElementById('inviteMessage').value = '';
  document.getElementById('msgCount').textContent = '0';
  document.getElementById('inviteError').style.display   = 'none';
  document.getElementById('inviteSuccess').style.display = 'none';
  document.getElementById('inviteModal').classList.add('open');
  setTimeout(() => document.getElementById('inviteEmail').focus(), 100);
}

document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('inviteMessage').addEventListener('input', function() {
    document.getElementById('msgCount').textContent = this.value.length;
  });
});

function closeInviteModal() {
  document.getElementById('inviteModal').classList.remove('open');
}

async function sendInvite() {
  const email   = document.getElementById('inviteEmail').value.trim();
  const role    = document.getElementById('inviteRole').value;
  const errEl   = document.getElementById('inviteError');
  const succEl  = document.getElementById('inviteSuccess');
  const btn     = document.getElementById('inviteBtn');

  errEl.style.display  = 'none';
  succEl.style.display = 'none';

  if (!email) { errEl.textContent = '이메일을 입력하세요.'; errEl.style.display = 'block'; return; }

  const message = document.getElementById('inviteMessage').value.trim();

  btn.disabled = true;
  btn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> 발송 중...';

  try {
    const res  = await fetch(INVITE_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
      body: JSON.stringify({ email, role, message }),
    });
    const ct = res.headers.get('content-type') || '';
    if (!ct.includes('application/json')) {
      const text = await res.text();
      if (res.status === 419) {
        errEl.textContent = '세션이 만료되었습니다. 페이지를 새로고침해주세요. (F5)';
      } else {
        errEl.textContent = `서버 오류 (${res.status}) — JSON이 아닌 응답입니다. 콘솔을 확인하세요.`;
        console.error('Non-JSON response:', res.status, text.substring(0, 1000));
      }
      errEl.style.display = 'block';
      return;
    }
    const data = await res.json();
    if (!res.ok || !data.success) {
      const msgs = data.errors ? Object.values(data.errors).flat().join('<br>') : (data.message || '발송 실패');
      errEl.innerHTML = msgs;
      errEl.style.display = 'block';
    } else {
      succEl.textContent = data.message || '초대 이메일이 발송되었습니다.';
      succEl.style.display = 'block';
      loadInvitations();
      setTimeout(closeInviteModal, 2000);
    }
  } catch (err) {
    errEl.textContent = '오류: ' + (err.message || String(err));
    errEl.style.display = 'block';
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="bx bx-send"></i> 초대 발송';
  }
}

// ── 모달 열기 ──────────────────────────────────────────
function openModal(userId = null) {
  const modal = document.getElementById('userModal');
  const isNew = !userId;

  document.getElementById('modalTitle').innerHTML =
    isNew ? '<i class="bx bx-user-plus" style="color:var(--primary);"></i> 관리자 추가'
          : '<i class="bx bx-edit" style="color:var(--primary);"></i> 관리자 수정';
  document.getElementById('formUserId').value = userId ?? '';
  document.getElementById('fPassword').required = isNew;
  document.getElementById('pwHint').style.display = isNew ? 'none' : 'block';
  document.getElementById('pwLabel').innerHTML = isNew
    ? '비밀번호 <span style="color:var(--danger);">*</span>'
    : '비밀번호 <span style="color:var(--text-muted);font-size:11px;">(변경 시에만 입력)</span>';
  document.getElementById('deleteBtn').style.display = (!isNew && userId !== ME) ? 'inline-flex' : 'none';
  document.getElementById('formError').style.display = 'none';
  document.getElementById('activeToggleWrap').style.display = (userId === ME) ? 'none' : 'block';

  if (isNew) {
    document.getElementById('fName').value  = '';
    document.getElementById('fEmail').value = '';
    document.getElementById('fPhone').value = '';
    document.getElementById('fRole').value  = 'manager';
    document.getElementById('fPassword').value = '';
    setActive(true);
  } else {
    const u = usersMap[userId];
    document.getElementById('fName').value  = u.name;
    document.getElementById('fEmail').value = u.email;
    document.getElementById('fPhone').value = u.phone;
    document.getElementById('fRole').value  = u.role;
    document.getElementById('fPassword').value = '';
    setActive(u.is_active);
    // role 변경 잠금 (자기 자신)
    document.getElementById('fRole').disabled = (userId === ME);
  }

  modal.classList.add('open');
  setTimeout(() => document.getElementById('fName').focus(), 100);
}

function closeModal() {
  document.getElementById('userModal').classList.remove('open');
  document.getElementById('fRole').disabled = false;
}

// ── 토글 ───────────────────────────────────────────────
function setActive(val) {
  const track = document.getElementById('toggleTrack');
  const label = document.getElementById('activeLabel');
  const cb    = document.getElementById('fIsActive');
  cb.checked = val;
  if (val) { track.classList.add('on'); label.textContent = '활성'; label.style.color = 'var(--success)'; }
  else      { track.classList.remove('on'); label.textContent = '비활성'; label.style.color = 'var(--danger)'; }
}

function toggleActive() {
  setActive(!document.getElementById('fIsActive').checked);
}

// ── 비밀번호 표시 토글 ─────────────────────────────────
function togglePw() {
  const input = document.getElementById('fPassword');
  const eye   = document.getElementById('pwEye');
  if (input.type === 'password') { input.type = 'text';     eye.className = 'bx bx-hide'; }
  else                           { input.type = 'password'; eye.className = 'bx bx-show'; }
}

// ── 저장 ───────────────────────────────────────────────
async function submitForm(e) {
  e?.preventDefault();
  const userId = document.getElementById('formUserId').value;
  const isNew  = !userId;
  const errEl  = document.getElementById('formError');
  errEl.style.display = 'none';

  const payload = {
    name:      document.getElementById('fName').value.trim(),
    email:     document.getElementById('fEmail').value.trim(),
    phone:     document.getElementById('fPhone').value.replace(/\D/g, ''),
    role:      document.getElementById('fRole').value,
    is_active: document.getElementById('fIsActive').checked ? 1 : 0,
    password:  document.getElementById('fPassword').value,
  };

  const url    = isNew ? USERS_BASE_URL : `${USERS_BASE_URL}/${userId}`;
  const method = isNew ? 'POST' : 'PUT';
  const btn = document.getElementById('submitBtn');
  btn.disabled = true;
  btn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> 저장 중...';

  try {
    const res = await fetch(url, {
      method,
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
      body: JSON.stringify(payload),
    });

    const ct = res.headers.get('content-type') || '';
    if (!ct.includes('application/json')) {
      const text = await res.text();
      if (res.status === 419) {
        errEl.textContent = '세션이 만료되었습니다. 페이지를 새로고침해주세요. (F5)';
      } else {
        errEl.textContent = `서버 오류 (${res.status}) — JSON이 아닌 응답입니다. 콘솔을 확인하세요.`;
        console.error('Non-JSON response:', res.status, text.substring(0, 1000));
      }
      errEl.style.display = 'block';
      return;
    }

    const data = await res.json();

    if (!res.ok || !data.success) {
      const msgs = data.errors ? Object.values(data.errors).flat().join('<br>') : (data.message || '저장 실패');
      errEl.innerHTML = msgs;
      errEl.style.display = 'block';
      return;
    }

    const u = data.user;
    usersMap[u.id] = u;
    if (isNew) {
      addRowToTable(u);
      document.getElementById('userCount').textContent = Object.keys(usersMap).length;
    } else {
      updateRow(u);
    }
    closeModal();
    showToast(isNew ? '관리자가 추가되었습니다.' : '저장되었습니다.', 'success');
  } catch (err) {
    errEl.textContent = '오류: ' + (err.message || String(err));
    errEl.style.display = 'block';
    console.error('submitForm error:', err);
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="bx bx-save"></i> 저장';
  }
}

// ── 삭제 ───────────────────────────────────────────────
async function deleteUser() {
  const userId = document.getElementById('formUserId').value;
  if (!userId) return;
  if (!confirm(`정말 삭제하시겠습니까?`)) return;

  try {
    const res = await fetch(`${USERS_BASE_URL}/${userId}`, {
      method: 'DELETE',
      headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
    });
    const data = await res.json();
    if (!data.success) { alert(data.message || '삭제 실패'); return; }

    delete usersMap[userId];
    refreshUsersGrid();
    document.getElementById('userCount').textContent = Object.keys(usersMap).length;
    closeModal();
    showToast('삭제되었습니다.', 'success');
  } catch {
    alert('서버 오류');
  }
}

// ── wwGrid 동기화 (CRUD 후 그리드 갱신) ──────────────────
function userGridRow(u) {
  return {
    id:      u.id,
    name:    u.name + (u.id === ME ? ' (나)' : ''),
    email:   u.email,
    phone:   u.phone ? u.phone : '—',
    role:    u.role === 'admin' ? '관리자' : '매니저',
    status:  u.is_active ? '활성' : '비활성',
    created: u.created_at || '',
  };
}

function refreshUsersGrid() {
  if (!window.__usersGrid) return;
  const rows = Object.values(usersMap)
    .sort((a, b) => (a.role === b.role
        ? (a.name < b.name ? -1 : a.name > b.name ? 1 : 0)
        : (a.role < b.role ? -1 : 1)))
    .map(userGridRow);
  window.__usersGrid.setData(rows);
}

// submitForm 성공 시 호출되는 기존 훅 → 그리드 갱신으로 위임
function addRowToTable(u) { refreshUsersGrid(); }
function updateRow(u)     { refreshUsersGrid(); }
</script>

{{-- ── wwGrid 생성 + 외부 액션 버튼 ── --}}
<script>
(function () {
  // 두 그리드가 한 화면(뷰포트)에 페이지 스크롤 없이 들어오도록 높이를 동적 배분.
  // (제목·카드헤더·버튼·여백 등 그리드 외 요소를 CHROME으로 빼고 남은 높이를 나눔)
  const CHROME = 320;
  const budget = Math.max(320, window.innerHeight - CHROME);
  const USERS_H = Math.max(220, Math.round(budget * 0.56));
  const INV_H   = Math.max(150, Math.round(budget * 0.44));

  // 관리자 그리드
  window.__usersGrid = new wwGrid({
    el: document.getElementById('usersGrid'),
    height: USERS_H, editable: false, rowCheckbox: true, rowNumber: true, toolbar: true, summary: false,
    footer: { total: true, selected: true, modified: false },
    columns: [
      { header: 'ID',     name: 'id',      width: 60,  align: 'center', sortable: true },
      { header: '이름',   name: 'name',    width: 160, sortable: true },
      { header: '이메일', name: 'email',   width: 220, sortable: true },
      { header: '휴대폰', name: 'phone',   width: 140 },
      { header: '역할',   name: 'role',    width: 90,  align: 'center', sortable: true },
      { header: '상태',   name: 'status',  width: 80,  align: 'center', sortable: true },
      { header: '등록일', name: 'created', width: 110, align: 'center', sortable: true },
    ],
    data: USERS_GRID_DATA,
  });

  window.usersEditSelected = function () {
    const c = window.__usersGrid.getCheckedRows();
    if (!c.length)    { showToast('수정할 관리자를 체크하세요.', 'warning'); return; }
    if (c.length > 1) { showToast('한 건만 선택하세요.', 'warning'); return; }
    openModal(c[0].id);
  };

  // 초대 현황 그리드 (데이터는 loadInvitations()가 setData로 주입)
  window.__invGrid = new wwGrid({
    el: document.getElementById('invitationsGrid'),
    height: INV_H, editable: false, rowCheckbox: true, rowNumber: true, toolbar: true, summary: false,
    footer: { total: true, selected: true, modified: false },
    columns: [
      { header: '이메일',       name: 'email',      width: 220, sortable: true },
      { header: '역할',         name: 'role',       width: 90,  align: 'center', sortable: true },
      { header: '상태',         name: 'status',     width: 90,  align: 'center', sortable: true },
      { header: '초대한 사람',  name: 'invited_by', width: 140 },
      { header: '발송일시',     name: 'created',    width: 150, sortable: true },
      { header: '만료/수락일시', name: 'date',      width: 150 },
    ],
    data: [],
  });

  // 두 그리드가 한 화면에 페이지 스크롤 없이 들어오도록 높이 배분 + 반복 보정(리사이즈 대응)
  function fitTwoGrids() {
    const uw = window.__usersGrid && window.__usersGrid._wrapEl;
    const iw = window.__invGrid && window.__invGrid._wrapEl;
    if (!uw || !iw) return;
    // 시작 배분(관리자 58% / 초대 42%)
    const budget2 = Math.max(240, window.innerHeight - CHROME);
    uw.style.height = Math.max(110, Math.round(budget2 * 0.58)) + 'px';
    iw.style.height = Math.max(110, Math.round(budget2 * 0.42)) + 'px';
    // 넘치면 두 그리드에서 분담(관리자 60%/초대 40%)해 줄임 — 수렴까지 반복
    for (let i = 0; i < 4; i++) {
      const overflow = document.documentElement.scrollHeight - window.innerHeight;
      if (overflow <= 0) break;
      const uh = uw.getBoundingClientRect().height;
      const ih = iw.getBoundingClientRect().height;
      uw.style.height = Math.max(90, Math.floor(uh - Math.ceil(overflow * 0.6))) + 'px';
      iw.style.height = Math.max(90, Math.floor(ih - Math.ceil(overflow * 0.4))) + 'px';
    }
  }
  requestAnimationFrame(fitTwoGrids);
  window.addEventListener('resize', fitTwoGrids);

  function invPickSelected(actionLabel) {
    const c = window.__invGrid.getCheckedRows();
    if (!c.length)    { showToast(actionLabel + '할 초대를 체크하세요.', 'warning'); return null; }
    if (c.length > 1) { showToast('한 건만 선택하세요.', 'warning'); return null; }
    if (c[0].accepted) { showToast('수락된 초대는 처리할 수 없습니다.', 'warning'); return null; }
    return c[0];
  }

  window.invResendSelected = function () {
    const row = invPickSelected('재발송');
    if (row) resendInvitation(row.id);
  };
  window.invCancelSelected = function () {
    const row = invPickSelected('취소');
    if (row) cancelInvitation(row.id);
  };
})();
</script>
@endpush
