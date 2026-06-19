<?php

namespace App\Http\Controllers;

use App\Models\InstitutionalNotice;
use App\Services\InstitutionalNoticeCrawlerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InstitutionalNoticeController extends Controller
{
    public function index(): View
    {
        $orgs = ['MOHW', 'HIRA', 'NHIS'];
        $counts = [];
        $latestDates = [];

        foreach ($orgs as $org) {
            try {
                $counts[$org]      = InstitutionalNotice::byOrg($org)->count();
                $latestDates[$org] = InstitutionalNotice::byOrg($org)->max('crawled_at');
            } catch (\Throwable) {
                $counts[$org] = 0;
                $latestDates[$org] = null;
            }
        }

        return view('institutional-notices.index', compact('counts', 'latestDates'));
    }

    /**
     * AJAX: 기관별 목록 반환
     */
    public function list(Request $request): JsonResponse
    {
        $org     = strtoupper($request->get('org', 'MOHW'));
        $search  = $request->get('q', '');
        $impact  = $request->get('impact', '');
        $perPage = 20;

        try {
            $query = InstitutionalNotice::byOrg($org)
                ->orderByDesc('notice_date')
                ->orderByDesc('id');

            if ($search) {
                $query->where('title', 'like', "%{$search}%");
            }
            if ($impact) {
                $query->where('policy_impact', $impact);
            }

            $paginator = $query->paginate($perPage);

            return response()->json([
                'data'         => $paginator->items(),
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'total'        => $paginator->total(),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['data' => [], 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * AJAX: 공지 상세 (내용 자동 fetch)
     */
    public function show(int $id): JsonResponse
    {
        try {
            $notice = InstitutionalNotice::findOrFail($id);
            $crawler = new InstitutionalNoticeCrawlerService();
            $notice = $crawler->fetchDetail($notice);

            return response()->json($notice);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * AJAX: 크롤링 실행 (비동기 대응)
     */
    public function crawl(Request $request): JsonResponse
    {
        try {
            $crawler = new InstitutionalNoticeCrawlerService();
            $results = $crawler->crawlAll();

            $from = $results['from_date'] ?? '';
            return response()->json([
                'success' => true,
                'results' => $results,
                'message' => "{$from} 이후 — MOHW {$results['MOHW']}건, HIRA {$results['HIRA']}건, NHIS {$results['NHIS']}건 수집완료",
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * AJAX: 오늘 크롤링 여부 확인
     */
    public function checkToday(): JsonResponse
    {
        $has = InstitutionalNoticeCrawlerService::hasTodayData();
        return response()->json(['has_today' => $has]);
    }
}
