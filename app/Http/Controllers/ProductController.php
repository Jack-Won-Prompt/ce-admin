<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\Pool;

class ProductController extends Controller
{
    /**
     * todoworks.co.kr API를 통해 제품을 검색하는 프록시 엔드포인트.
     */
    public function search(Request $request): JsonResponse
    {
        $keyword = trim($request->get('q', ''));

        if ($keyword === '') {
            return response()->json(['success' => false, 'message' => '검색어를 입력해주세요.', 'data' => []]);
        }

        $baseUrl = rtrim(config('services.todoworks.api_url'), '/');
        $token   = config('services.todoworks.token');

        Log::info('Todoworks 제품 검색 요청', [
            'keyword' => $keyword,
            'api_url' => $baseUrl,
            'has_token' => !empty($token),
        ]);

        try {
            $response = Http::withToken($token)
                ->timeout(10)
                ->get("{$baseUrl}/api/v1/item/item_list", [
                    'item'     => $keyword,
                    'per_page' => 30,
                ]);

            Log::info('Todoworks 응답', [
                'status' => $response->status(),
                'ok'     => $response->ok(),
            ]);

            if ($response->failed()) {
                Log::warning('Todoworks API 오류', [
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
                Log::error('Todoworks API 인증 오류', ['error' => $apiErr, 'body' => $body]);
                return response()->json([
                    'success' => false,
                    'message' => "Todoworks 인증 실패 ({$apiErr}). 관리자에게 토큰 갱신을 요청하세요.",
                    'data'    => [],
                ]);
            }

            $items = $this->normalizeItems($body);

            Log::info('Todoworks 정규화 완료', ['count' => count($items)]);

            // 재고 병렬 조회 — 코드가 있는 아이템만
            $codes = array_values(array_filter(array_column($items, 'code')));
            if (!empty($codes)) {
                try {
                    $responses = Http::pool(fn (Pool $pool) => array_map(
                        fn ($code) => $pool->as($code)
                            ->withToken($token)
                            ->timeout(5)
                            ->get("{$baseUrl}/api/v1/inv_search/inv_info", ['item_code' => $code]),
                        $codes
                    ));

                    $stockMap = [];
                    foreach ($codes as $code) {
                        $res = $responses[$code] ?? null;
                        if ($res instanceof \Illuminate\Http\Client\Response && $res->ok()) {
                            $b = $res->json();
                            if (!empty($b['success'])) {
                                $invData        = $b['data']['invData'] ?? [];
                                $stockMap[$code] = collect($invData)->sum(fn ($r) => (int) ($r['avail_qty'] ?? 0));
                            }
                        }
                    }

                    foreach ($items as &$item) {
                        $item['stock'] = $stockMap[$item['code']] ?? null;
                    }
                    unset($item);
                } catch (\Exception $e) {
                    Log::warning('재고 병렬 조회 실패', ['error' => $e->getMessage()]);
                }
            }

            return response()->json([
                'success' => true,
                'data'    => $items,
                'total'   => count($items),
            ]);
        } catch (\Exception $e) {
            Log::error('Todoworks API 연결 실패', ['error' => $e->getMessage()]);

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

        $baseUrl = rtrim(config('services.todoworks.api_url'), '/');
        $token   = config('services.todoworks.token');

        try {
            $response = Http::withToken($token)
                ->timeout(8)
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
            Log::warning('Todoworks 재고 조회 실패', ['code' => $code, 'error' => $e->getMessage()]);
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
