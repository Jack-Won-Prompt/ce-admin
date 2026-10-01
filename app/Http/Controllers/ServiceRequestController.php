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

        activity()->causedBy(Auth::user())->log("SR 등록: {$sr->title}");

        return response()->json([
            'success' => true,
            'message' => 'SR 이 등록되었습니다.',
            'row'     => $sr->fresh(['user'])->toRow(),
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

        $serviceRequest->update([
            'answer'      => $답변,
            'answered_by' => Auth::id(),
            'answered_at' => now(),
            'status'      => $data['status'] ?? ServiceRequest::STATUS_ANSWERED,
        ]);

        activity()->causedBy(Auth::user())->log("SR 답변: {$serviceRequest->title}");

        return response()->json([
            'success' => true,
            'message' => '답변을 저장했습니다.',
            'row'     => $serviceRequest->fresh(['user', 'answeredBy'])->toRow(),
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
            'row'     => $serviceRequest->fresh(['user', 'answeredBy'])->toRow(),
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
        $q = ServiceRequest::with(['user', 'answeredBy'])->latest('id');

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
