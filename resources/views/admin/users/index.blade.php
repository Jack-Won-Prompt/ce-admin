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

    <div style="overflow-x:auto;">
      <table class="table" id="usersTable">
        <thead>
          <tr>
            <th style="width:40px;">#</th>
            <th>이름</th>
            <th>이메일</th>
            <th>휴대폰</th>
            <th style="width:80px;">역할</th>
            <th style="width:70px;">상태</th>
            <th style="width:90px;">등록일</th>
            <th style="width:60px;"></th>
          </tr>
        </thead>
        <tbody id="usersBody">
          @foreach($users as $user)
          <tr id="user-row-{{ $user->id }}" data-id="{{ $user->id }}">
            <td style="color:var(--text-muted);font-size:11px;">{{ $user->id }}</td>
            <td>
              <div style="display:flex;align-items:center;gap:8px;">
                <div style="width:32px;height:32px;border-radius:50%;background:var(--primary-light);color:var(--primary);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0;">
                  {{ mb_substr($user->name, 0, 1) }}
                </div>
                <span style="font-weight:600;">{{ $user->name }}</span>
                @if($user->id === auth()->id())
                  <span style="background:#E0F2FE;color:#0284C7;font-size:10px;padding:1px 6px;border-radius:4px;">나</span>
                @endif
              </div>
            </td>
            <td style="font-size:12px;">{{ $user->email }}</td>
            <td style="font-size:12px;">{{ $user->phone ?: '—' }}</td>
            <td>
              <span class="role-badge role-{{ $user->role }}">
                {{ $user->role === 'admin' ? '관리자' : '매니저' }}
              </span>
            </td>
            <td>
              <span class="status-badge {{ $user->is_active ? 'active' : 'inactive' }}">
                {{ $user->is_active ? '활성' : '비활성' }}
              </span>
            </td>
            <td style="font-size:11px;color:var(--text-muted);">{{ $user->created_at?->format('Y-m-d') }}</td>
            <td>
              <button class="btn btn-sm btn-outline" onclick="openModal({{ $user->id }})" title="수정"
                      style="padding:3px 8px;font-size:11px;">
                <i class="bx bx-edit"></i>
              </button>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
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

    <div style="overflow-x:auto;">
      <table class="table" id="invitationsTable">
        <thead>
          <tr>
            <th>이메일</th>
            <th style="width:120px;">역할</th>
            <th style="width:100px;">상태</th>
            <th style="width:140px;">초대한 사람</th>
            <th style="width:140px;">발송일시</th>
            <th style="width:140px;">만료/수락일시</th>
            <th style="width:100px;"></th>
          </tr>
        </thead>
        <tbody id="invitationsBody">
          <tr id="inviteLoadingRow">
            <td colspan="7" style="text-align:center;padding:28px;color:var(--text-muted);font-size:13px;">
              <i class="bx bx-loader-alt bx-spin"></i> 불러오는 중...
            </td>
          </tr>
        </tbody>
      </table>
    </div>
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
<style>
/* ── 테이블 ── */
.table { width:100%; border-collapse:collapse; }
.table th { padding:10px 14px; font-size:11px; font-weight:600; color:var(--text-muted); text-transform:uppercase; letter-spacing:.4px; border-bottom:1px solid var(--border); background:var(--bg); }
.table td { padding:12px 14px; font-size:13px; border-bottom:1px solid var(--border); vertical-align:middle; }
.table tbody tr:hover { background:var(--bg); }
.table tbody tr:last-child td { border-bottom:none; }

/* ── 배지 ── */
.role-badge  { display:inline-block; padding:2px 10px; border-radius:20px; font-size:11px; font-weight:600; }
.role-admin  { background:var(--primary-light); color:var(--primary); }
.role-manager{ background:#F0FDF4; color:#16A34A; }
.status-badge{ display:inline-block; padding:2px 10px; border-radius:20px; font-size:11px; font-weight:600; }
.status-badge.active  { background:#DCFCE7; color:#16A34A; }
.status-badge.inactive{ background:#FEE2E2; color:#DC2626; }

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

/* ── 초대 상태 배지 ── */
.inv-badge   { display:inline-block; padding:2px 10px; border-radius:20px; font-size:11px; font-weight:600; }
.inv-pending { background:#FFF7ED; color:#C2410C; }
.inv-accepted{ background:#DCFCE7; color:#16A34A; }
.inv-expired { background:#F3F4F6; color:#6B7280; }

/* ── 토글 스위치 ── */
.toggle-track { width:44px; height:24px; border-radius:12px; background:var(--border); position:relative; transition:background .2s; cursor:pointer; }
.toggle-track.on { background:var(--success); }
.toggle-thumb { position:absolute; top:3px; left:3px; width:18px; height:18px; border-radius:50%; background:#fff; transition:transform .2s; box-shadow:0 1px 4px rgba(0,0,0,.2); }
.toggle-track.on .toggle-thumb { transform:translateX(20px); }
</style>
@endpush

@push('scripts')
<script>
const USERS_DATA          = @json($usersData);
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
  const body = document.getElementById('invitationsBody');
  btn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i>';
  btn.disabled  = true;

  try {
    const res  = await fetch(INVITATIONS_URL, { headers: { 'Accept': 'application/json' } });
    const data = await res.json();
    renderInvitations(data.invitations || []);
  } catch {
    body.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:20px;color:var(--danger);font-size:13px;">불러오기 실패</td></tr>';
  } finally {
    btn.innerHTML = '<i class="bx bx-refresh"></i>';
    btn.disabled  = false;
  }
}

function renderInvitations(list) {
  const body  = document.getElementById('invitationsBody');
  const badge = document.getElementById('inviteBadge');

  if (!list.length) {
    body.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:28px;color:var(--text-muted);font-size:13px;">초대 내역이 없습니다.</td></tr>';
    badge.style.display = 'none';
    return;
  }

  const pending = list.filter(i => i.status === 'pending').length;
  if (pending > 0) {
    badge.textContent    = `대기 ${pending}`;
    badge.style.display  = 'inline-block';
  } else {
    badge.style.display  = 'none';
  }

  body.innerHTML = list.map(inv => {
    const statusHtml = {
      pending:  `<span class="inv-badge inv-pending">대기중</span>`,
      accepted: `<span class="inv-badge inv-accepted">수락됨</span>`,
      expired:  `<span class="inv-badge inv-expired">만료됨</span>`,
    }[inv.status] ?? '';

    const roleHtml = inv.role === 'admin'
      ? `<span class="role-badge role-admin">관리자</span>`
      : `<span class="role-badge role-manager">매니저</span>`;

    const dateCol = inv.status === 'accepted'
      ? `<span style="color:var(--success);">${inv.accepted_at}</span>`
      : `<span style="color:${inv.status==='expired'?'var(--danger)':'var(--text-muted)'};">${inv.expires_at}</span>`;

    const actions = inv.status !== 'accepted' ? `
      <button class="btn btn-sm btn-outline" style="padding:2px 8px;font-size:11px;" onclick="resendInvitation(${inv.id})" title="재발송">
        <i class="bx bx-send"></i>
      </button>
      <button class="btn btn-sm btn-outline" style="padding:2px 8px;font-size:11px;color:var(--danger);border-color:var(--danger);" onclick="cancelInvitation(${inv.id})" title="취소">
        <i class="bx bx-trash"></i>
      </button>` : `<span style="font-size:11px;color:var(--text-muted);">—</span>`;

    return `<tr id="inv-row-${inv.id}">
      <td style="font-size:13px;">${inv.email}</td>
      <td>${roleHtml}</td>
      <td>${statusHtml}</td>
      <td style="font-size:12px;color:var(--text-muted);">${inv.invited_by}</td>
      <td style="font-size:11px;color:var(--text-muted);">${inv.created_at}</td>
      <td style="font-size:11px;">${dateCol}</td>
      <td><div style="display:flex;gap:4px;">${actions}</div></td>
    </tr>`;
  }).join('');
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
    document.getElementById(`inv-row-${id}`)?.remove();
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
    const row = document.getElementById(`user-row-${userId}`);
    row?.remove();
    document.getElementById('userCount').textContent = Object.keys(usersMap).length;
    closeModal();
    showToast('삭제되었습니다.', 'success');
  } catch {
    alert('서버 오류');
  }
}

// ── DOM 업데이트 ────────────────────────────────────────
function roleBadge(role) {
  return role === 'admin'
    ? `<span class="role-badge role-admin">관리자</span>`
    : `<span class="role-badge role-manager">매니저</span>`;
}
function statusBadge(active) {
  return active
    ? `<span class="status-badge active">활성</span>`
    : `<span class="status-badge inactive">비활성</span>`;
}
function fmtPhone(p) {
  const d = (p || '').replace(/\D/g, '');
  if (!d) return '—';
  if (d.startsWith('02')) {
    if (d.length <= 9) return d.slice(0,2)+'-'+d.slice(2,5)+'-'+d.slice(5);
    return d.slice(0,2)+'-'+d.slice(2,6)+'-'+d.slice(6,10);
  }
  if (d.length <= 10) return d.slice(0,3)+'-'+d.slice(3,6)+'-'+d.slice(6);
  return d.slice(0,3)+'-'+d.slice(3,7)+'-'+d.slice(7,11);
}

function addRowToTable(u) {
  const isSelf = u.id === ME;
  const row = document.createElement('tr');
  row.id = `user-row-${u.id}`;
  row.dataset.id = u.id;
  row.innerHTML = `
    <td style="color:var(--text-muted);font-size:11px;">${u.id}</td>
    <td>
      <div style="display:flex;align-items:center;gap:8px;">
        <div style="width:32px;height:32px;border-radius:50%;background:var(--primary-light);color:var(--primary);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0;">${u.name.slice(0,1)}</div>
        <span style="font-weight:600;">${u.name}</span>
        ${isSelf ? '<span style="background:#E0F2FE;color:#0284C7;font-size:10px;padding:1px 6px;border-radius:4px;">나</span>' : ''}
      </div>
    </td>
    <td style="font-size:12px;">${u.email}</td>
    <td style="font-size:12px;">${fmtPhone(u.phone)}</td>
    <td>${roleBadge(u.role)}</td>
    <td>${statusBadge(u.is_active)}</td>
    <td style="font-size:11px;color:var(--text-muted);">${u.created_at}</td>
    <td><button class="btn btn-sm btn-outline" onclick="openModal(${u.id})" title="수정" style="padding:3px 8px;font-size:11px;"><i class="bx bx-edit"></i></button></td>
  `;
  document.getElementById('usersBody').appendChild(row);
}

function updateRow(u) {
  const isSelf = u.id === ME;
  const row = document.getElementById(`user-row-${u.id}`);
  if (!row) return;
  row.cells[1].innerHTML = `
    <div style="display:flex;align-items:center;gap:8px;">
      <div style="width:32px;height:32px;border-radius:50%;background:var(--primary-light);color:var(--primary);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0;">${u.name.slice(0,1)}</div>
      <span style="font-weight:600;">${u.name}</span>
      ${isSelf ? '<span style="background:#E0F2FE;color:#0284C7;font-size:10px;padding:1px 6px;border-radius:4px;">나</span>' : ''}
    </div>`;
  row.cells[2].textContent = u.email;
  row.cells[3].textContent = fmtPhone(u.phone);
  row.cells[4].innerHTML   = roleBadge(u.role);
  row.cells[5].innerHTML   = statusBadge(u.is_active);
}
</script>
@endpush
