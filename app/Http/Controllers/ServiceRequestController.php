<?php
// app/Http/Controllers/ServiceRequestController.php
// SR(Service Request) 등록·답변 관리.
// 상단 SR 패널(모든 화면 공용)과 사이드바 'SR 관리' 화면이 같은 엔드포인트를 쓴다.

namespace App\Http\Controllers;

use App\Models\ServiceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ServiceRequestController extends Controller
{
    /** 사이드바 전용 화면 */
    public function index(Request $request): View
    {
        $rows = $this->query($request)->get()->map(fn (ServiceRequest $s) => $s->toRow())->values();

        return view('service-requests.index', [
            'gridData'   => $rows,
            'total'      => $rows->count(),
            'categories' => ServiceRequest::CATEGORIES,
            'priorities' => ServiceRequest::PRIORITIES,
            'statuses'   => ServiceRequest::STATUSES,
            'counts'     => $this->statusCounts(),
        ]);
    }

    /** 패널·화면 공용 목록 (JSON) */
    public function list(Request $request): JsonResponse
    {
        $rows = $this->query($request)->limit(300)->get()
            ->map(fn (ServiceRequest $s) => $s->toRow())->values();

        return response()->json([
            'success' => true,
            'rows'    => $rows,
            'counts'  => $this->statusCounts(),
            'canAnswer' => perm('service-requests', 'update'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'      => ['required', 'string', 'max:200'],
            /* Quill 이 꾸밈을 이름표로 실어 오므로 5,000자로는 한 쪽도 못 담는다
               (2026-10-01). 칸은 text(65,535바이트)라 여유가 있다 — 그 안쪽으로 둔다. */
            'content'    => ['required', 'string', 'max:60000'],
            'category'   => ['required', 'in:' . implode(',', array_keys(ServiceRequest::CATEGORIES))],
            'priority'   => ['required', 'in:' . implode(',', array_keys(ServiceRequest::PRIORITIES))],
            'page_label' => ['nullable', 'string', 'max:100'],
            'page_url'   => ['nullable', 'string', 'max:300'],
        ]);

        /* 담기 전에 거른다 — 담은 글은 화면에 innerHTML 로 서므로 글 안의 스크립트가
           같은 화면을 보는 다른 담당자에게서 돈다(App\Support\RichText). */
        $data['content'] = \App\Support\RichText::정리($data['content']);

        if (\App\Support\RichText::빈가($data['content'])) {
            return response()->json([
                'success' => false,
                'message' => '내용을 입력해 주십시오.',
            ], 422);
        }

        $sr = ServiceRequest::create($data + [
            'user_id' => Auth::id(),
            'status'  => ServiceRequest::STATUS_DEFAULT,
        ]);

        /* 적는 중에 올려 둔 파일을 이 SR 에 건다 (2026-10-06 · SR #82ㆍ#86).

           새 SR 을 적을 때는 SR 이 아직 없어 `service_request_id` 를 비워 둔 채
           올린다. 저장하는 이 순간 그 사람의 임자 없는 줄을 모두 이 SR 로 옮긴다. */
        \App\Models\ServiceRequestFile::whereNull('service_request_id')
            ->where('user_id', Auth::id())
            ->update(['service_request_id' => $sr->id]);

        activity()->causedBy(Auth::user())->log("SR 등록: {$sr->title}");

        /* Agent 에게 넘긴다 (2026-10-02 지시) — 환경 설정 › Agent 연계에서 켰을
           때만 나간다. 켜지 않으면 한 건도 보내지 않는다. 보내다 실패해도 등록은
           이미 끝났다(AgentNotifier 안에서 모두 감쌌다). */
        \App\Support\AgentNotifier::sr등록($sr->fresh(['user']));

        return response()->json([
            'success' => true,
            'message' => 'SR 이 등록되었습니다.',
            'row'     => $sr->fresh(['user', 'files'])->toRow(),
        ]);
    }

    /**
     * 파일을 올린다 (2026-10-06 · SR #82ㆍ#86).
     *
     * 그림을 본문에 그대로 넣던 것을 걷는 자리다. Quill 은 붙인 그림을 base64 글자로
     * 바꿔 본문에 넣는데, `content` 는 TEXT(65,535바이트)이고 검사도 60,000자에서
     * 끊는다 — 갈무리 한 장이 그 몇 배라 「글자 초과」로 막혔다(SR #86).
     *
     * 적는 중에도 올릴 수 있어야 한다. 그때는 SR 이 아직 없으므로 올린 사람만 적어
     * 두었다가 저장할 때 건다(store 참고).
     */
    public function uploadFile(Request $request): JsonResponse
    {
        $request->validate([
            // 10MB — 화면 갈무리는 보통 1MB 안쪽이다. PDF 를 붙이는 일도 있어 넉넉히 둔다.
            'file'   => ['required', 'file', 'max:10240'],
            'inline' => ['nullable', 'boolean'],
        ]);

        $올린것 = $request->file('file');
        $본문그림 = $request->boolean('inline');

        /* 본문에 끼우는 것은 그림만 받는다 — 본문에 들어가는 것은 <img> 뿐이다 */
        if ($본문그림 && ! str_starts_with((string) $올린것->getMimeType(), 'image/')) {
            return response()->json([
                'success' => false,
                'message' => '본문에는 그림만 넣을 수 있습니다. 다른 파일은 붙임 파일로 올려 주십시오.',
            ], 422);
        }

        /* 공개 디스크에 두지 않는다 — SR 에는 환자 이름이 적힌 갈무리가 올라온다.
           주소만 알면 열리는 자리에 두면 로그인 없이 새어 나간다. */
        $경로 = $올린것->store('service-requests/' . Auth::id(), 'local');

        $줄 = \App\Models\ServiceRequestFile::create([
            'service_request_id' => $request->integer('service_request_id') ?: null,
            'user_id'            => Auth::id(),
            'original_name'      => mb_substr((string) $올린것->getClientOriginalName(), 0, 255),
            'path'               => $경로,
            'mime'               => $올린것->getMimeType(),
            'size'               => $올린것->getSize(),
            'inline'             => $본문그림,
        ]);

        return response()->json([
            'success' => true,
            'id'      => $줄->id,
            'url'     => route('sr.files.show', $줄),
            'name'    => $줄->original_name,
            'size'    => $줄->크기글(),
            'image'   => $줄->그림인가(),
        ]);
    }

    /**
     * 올린 파일을 내보낸다 — 이 자리에서 로그인과 권한을 본다.
     *
     * 라우트가 `auth` 와 SR 권한 아래 있으므로 여기까지 온 사람은 SR 화면을 볼 수
     * 있는 사람이다. 그 위에 하나를 더 본다: 아직 어느 SR 에도 걸리지 않은 줄은
     * **올린 사람만** 볼 수 있다. 적다 만 글의 갈무리를 남이 볼 까닭이 없다.
     */
    public function showFile(\App\Models\ServiceRequestFile $file)
    {
        if (! $file->service_request_id && $file->user_id !== Auth::id()) {
            abort(404);
        }

        if (! \Illuminate\Support\Facades\Storage::disk('local')->exists($file->path)) {
            abort(404);
        }

        return \Illuminate\Support\Facades\Storage::disk('local')->response(
            $file->path,
            $file->original_name,
            ['Content-Type' => $file->mime ?: 'application/octet-stream']
        );
    }

    /** 올린 파일을 뗀다 — 올린 사람과 관리자만 */
    public function deleteFile(\App\Models\ServiceRequestFile $file): JsonResponse
    {
        if ($file->user_id !== Auth::id() && ! perm('service-requests', 'update')) {
            return response()->json(['success' => false, 'message' => '지울 수 있는 파일이 아닙니다.'], 403);
        }

        \Illuminate\Support\Facades\Storage::disk('local')->delete($file->path);
        $file->delete();

        return response()->json(['success' => true]);
    }

    /**
     * 올린 사람이 제 글을 고친다 (2026-10-06 · SR #82).
     *
     * 답변이 달린 뒤에는 고치지 않는다 — 답변은 그때의 글을 보고 적은 것이라,
     * 글이 바뀌면 답변이 무엇에 대한 것인지 알 수 없게 된다. 관리자는 언제나 고친다.
     */
    public function updateContent(Request $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $관리자 = perm('service-requests', 'update');

        if ($serviceRequest->user_id !== Auth::id() && ! $관리자) {
            return response()->json(['success' => false, 'message' => '본인이 등록한 SR 만 수정할 수 있습니다.'], 403);
        }

        if ($serviceRequest->answer && ! $관리자) {
            return response()->json([
                'success' => false,
                'message' => '답변이 등록된 SR 은 수정할 수 없습니다. 덧붙일 내용은 새 SR 로 등록해 주십시오.',
            ], 422);
        }

        $data = $request->validate([
            'title'    => ['required', 'string', 'max:200'],
            'content'  => ['required', 'string', 'max:60000'],
            'category' => ['required', 'in:' . implode(',', array_keys(ServiceRequest::CATEGORIES))],
            'priority' => ['required', 'in:' . implode(',', array_keys(ServiceRequest::PRIORITIES))],
        ]);

        $data['content'] = \App\Support\RichText::정리($data['content']);

        if (\App\Support\RichText::빈가($data['content'])) {
            return response()->json(['success' => false, 'message' => '내용을 입력해 주십시오.'], 422);
        }

        $serviceRequest->update($data);

        /* 고치는 중에 올린 파일도 이 SR 에 건다 */
        \App\Models\ServiceRequestFile::whereNull('service_request_id')
            ->where('user_id', Auth::id())
            ->update(['service_request_id' => $serviceRequest->id]);

        activity()->causedBy(Auth::user())->performedOn($serviceRequest)
            ->log("SR 수정: {$serviceRequest->title}");

        return response()->json([
            'success' => true,
            'message' => '수정했습니다.',
            'row'     => $serviceRequest->fresh(['user', 'files'])->toRow(),
        ]);
    }

    /** 답변 등록·수정 (+ 상태 변경) */
    public function answer(Request $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $data = $request->validate([
            // 등록과 같은 까닭으로 늘린다 — Quill 이 이름표를 함께 싣는다
            'answer' => ['required', 'string', 'max:60000'],
            'status' => ['nullable', 'in:' . implode(',', array_keys(ServiceRequest::STATUSES))],
        ]);

        $답변 = \App\Support\RichText::정리($data['answer']);

        if (\App\Support\RichText::빈가($답변)) {
            return response()->json([
                'success' => false,
                'message' => '답변 내용을 입력해 주십시오.',
            ], 422);
        }

        /* 적기 전에 가린다 — 저장한 뒤에는 처음인지 고친 것인지 알 수 없다 */
        $처음인가 = \App\Support\RichText::빈가((string) $serviceRequest->answer);

        $serviceRequest->update([
            'answer'      => $답변,
            'answered_by' => Auth::id(),
            'answered_at' => now(),
            'status'      => $data['status'] ?? ServiceRequest::STATUS_ANSWERED,
        ]);

        activity()->causedBy(Auth::user())->log("SR 답변: {$serviceRequest->title}");

        /* 답변이 처음 등록될 때만 메일로 알린다 (2026-10-08 지시). 고친 답변은 보내지
           않는다 — 같은 건으로 메일이 여러 번 가지 않게 한다. 처리 완료로 옮긴 답변도
           보내지 않는다(그 잣대는 SrAnswerMail 안에 있다). 담당자가 직접 쓴 답변이라
           전문을 싣는다. */
        if ($처음인가) {
            \App\Support\SrAnswerMail::보낸다($serviceRequest->fresh(['user']), 전문: true);
        }

        return response()->json([
            'success' => true,
            'message' => '답변을 저장했습니다.',
            'row'     => $serviceRequest->fresh(['user', 'answeredBy', 'files'])->toRow(),
        ]);
    }

    /** 상태만 변경 */
    public function updateStatus(Request $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:' . implode(',', array_keys(ServiceRequest::STATUSES))],
        ]);

        $serviceRequest->update(['status' => $data['status']]);

        return response()->json([
            'success' => true,
            'message' => '상태를 변경했습니다.',
            'row'     => $serviceRequest->fresh(['user', 'answeredBy', 'files'])->toRow(),
        ]);
    }

    public function destroy(ServiceRequest $serviceRequest): JsonResponse
    {
        $title = $serviceRequest->title;
        $serviceRequest->delete();

        activity()->causedBy(Auth::user())->log("SR 삭제: {$title}");

        return response()->json(['success' => true, 'message' => 'SR 을 삭제했습니다.']);
    }

    // ──────────────────────────────────────────────────────────

    private function query(Request $request)
    {
        /* 붙임 파일도 미리 담는다 — 줄마다 묻지 않게 (2026-10-06 · SR #82) */
        $q = ServiceRequest::with(['user', 'answeredBy', 'files'])->latest('id');

        if ($request->filled('status'))   $q->where('status', $request->status);
        if ($request->filled('category')) $q->where('category', $request->category);
        if ($request->filled('q')) {
            $kw = $request->q;
            $q->where(fn ($s) => $s->where('title', 'like', "%{$kw}%")
                                   ->orWhere('content', 'like', "%{$kw}%"));
        }

        return $q;
    }

    private function statusCounts(): array
    {
        $raw = ServiceRequest::selectRaw('status, count(*) as cnt')->groupBy('status')
                             ->pluck('cnt', 'status')->toArray();

        $out = ['all' => array_sum($raw)];
        foreach (array_keys(ServiceRequest::STATUSES) as $s) {
            $out[$s] = $raw[$s] ?? 0;
        }
        return $out;
    }
}
