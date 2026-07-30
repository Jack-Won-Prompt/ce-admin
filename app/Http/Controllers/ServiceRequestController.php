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
            'content'    => ['required', 'string', 'max:5000'],
            'category'   => ['required', 'in:' . implode(',', array_keys(ServiceRequest::CATEGORIES))],
            'priority'   => ['required', 'in:' . implode(',', array_keys(ServiceRequest::PRIORITIES))],
            'page_label' => ['nullable', 'string', 'max:100'],
            'page_url'   => ['nullable', 'string', 'max:300'],
        ]);

        $sr = ServiceRequest::create($data + [
            'user_id' => Auth::id(),
            'status'  => 'open',
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
            'answer' => ['required', 'string', 'max:5000'],
            'status' => ['nullable', 'in:' . implode(',', array_keys(ServiceRequest::STATUSES))],
        ]);

        $serviceRequest->update([
            'answer'      => $data['answer'],
            'answered_by' => Auth::id(),
            'answered_at' => now(),
            'status'      => $data['status'] ?? 'answered',
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
