<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    /**
     * demoworks.co.kr API를 통해 제품을 검색하는 프록시 엔드포인트.
     */
    public function search(Request $request): JsonResponse
    {
        $keyword = trim($request->get('q', ''));

        if ($keyword === '') {
            return response()->json(['success' => false, 'message' => '검색어를 입력해주세요.', 'data' => []]);
        }

        $baseUrl = rtrim(config('services.demoworks.api_url'), '/');
        $token   = config('services.demoworks.token');

        Log::info('Demoworks 제품 검색 요청', [
            'keyword' => $keyword,
            'api_url' => $baseUrl,
            'has_token' => !empty($token),
        ]);

        try {
            $response = Http::withToken($token)
                ->connectTimeout(5)
                ->timeout(15)
                ->get("{$baseUrl}/api/v1/item/item_list", [
                    'item'     => $keyword,
                    'per_page' => 30,
                ]);

            Log::info('Demoworks 응답', [
                'status' => $response->status(),
                'ok'     => $response->ok(),
            ]);

            if ($response->failed()) {
                Log::warning('Demoworks API 오류', [
                    'status' => $response->status(),
                    'body'   => substr($response->body(), 0, 500),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => "제품 검색 중 오류가 발생했습니다. (HTTP {$response->status()})",
                    'data'    => [],
                ]);
            }

            $body = $response->json();

            if (!empty($body['error']) || (isset($body['code']) && (string)$body['code'] === '403')) {
                $apiErr = $body['error'] ?? 'Unauthorized';
                Log::error('Demoworks API 인증 오류', ['error' => $apiErr, 'body' => $body]);
                return response()->json([
                    'success' => false,
                    'message' => "Demoworks 인증 실패 ({$apiErr}). 관리자에게 토큰 갱신을 요청하세요.",
                    'data'    => [],
                ]);
            }

            $items = $this->normalizeItems($body);

            Log::info('Demoworks 정규화 완료', ['count' => count($items)]);

            /* 재고는 여기서 묻지 않는다.
               제품 하나의 재고 조회(inv_search/inv_info)가 7초 걸린다. 검색 결과 열 건이면
               열 번을 한꺼번에 물었고, 그동안 창고 API 가 막혀 제품 검색(평소 0.15초)마저
               10초를 넘겨 끊겼다 — 「제품 API 서버에 연결할 수 없습니다」의 정체다.
               재고는 고른 뒤 그 한 건만 따로 묻는다(/products/stock). */
            return response()->json([
                'success' => true,
                'data'    => $items,
                'total'   => count($items),
            ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            // 끊긴 것과 답이 늦은 것은 다르다. 담당자가 할 일도 다르다 — 늦은 것은 다시 눌러 보면 된다.
            Log::warning('Demoworks 제품 검색 지연·끊김', ['keyword' => $keyword, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => '창고 서버가 제때 답하지 않았습니다. 잠시 뒤 다시 검색해 주십시오.',
                'data'    => [],
            ]);
        } catch (\Exception $e) {
            Log::error('Demoworks API 연결 실패', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => '제품 API 서버에 연결할 수 없습니다: ' . $e->getMessage(),
                'data'    => [],
            ]);
        }
    }

    /**
     * 제품 재고 수량 조회 프록시 (inv_search/inv_info).
     * GET /products/stock?code={item_code}
     */
    public function stock(Request $request): JsonResponse
    {
        $code = trim($request->get('code', ''));

        if ($code === '') {
            return response()->json(['success' => false, 'qty' => null, 'message' => '제품코드가 필요합니다.']);
        }

        $baseUrl = rtrim(config('services.demoworks.api_url'), '/');
        $token   = config('services.demoworks.token');

        try {
            // 창고의 재고 조회는 한 건에 7초 안팎이다 — 8초로는 자주 놓친다
            $response = Http::withToken($token)
                ->connectTimeout(5)
                ->timeout(20)
                ->get("{$baseUrl}/api/v1/inv_search/inv_info", [
                    'item_code' => $code,
                ]);

            if ($response->failed()) {
                return response()->json(['success' => false, 'qty' => null]);
            }

            $body = $response->json();

            if (empty($body['success'])) {
                return response()->json(['success' => false, 'qty' => null]);
            }

            // invData 배열의 avail_qty 합산 → 가용 재고
            $invData  = $body['data']['invData'] ?? [];
            $totalQty = collect($invData)->sum(fn($row) => (int) ($row['avail_qty'] ?? 0));

            return response()->json([
                'success' => true,
                'qty'     => $totalQty,
            ]);
        } catch (\Exception $e) {
            Log::warning('Demoworks 재고 조회 실패', ['code' => $code, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'qty' => null]);
        }
    }

    /**
     * 다양한 응답 형태를 단일 배열 형태로 정규화.
     * r_box, 재고(stock) 포함.
     */
    private function normalizeItems(mixed $body): array
    {
        $raw = $body['result']['data']
            ?? $body['result']
            ?? $body['data']
            ?? (is_array($body) ? $body : []);

        if (!is_array($raw)) {
            return [];
        }

        $result = [];
        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }

            $result[] = [
                'code'  => (string) ($item['item_code']    ?? $item['code']         ?? $item['product_code'] ?? ''),
                'name'  => (string) ($item['item_name']    ?? $item['name']         ?? $item['product_name'] ?? ''),
                'price' => isset($item['sales_price'])  ? (float) $item['sales_price']
                         : (isset($item['price'])       ? (float) $item['price']    : null),
                'spec'  => (string) ($item['description']  ?? $item['spec']         ?? ''),
                'unit'  => (string) ($item['basic_unit']   ?? $item['unit']         ?? $item['unit_name']    ?? ''),
                'r_box' => (string) ($item['r_box']        ?? ''),
            ];
        }

        return $result;
    }
}
