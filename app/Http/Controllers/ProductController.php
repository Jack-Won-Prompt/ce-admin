<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    /** 이어 받을 쪽 수 — 창고가 한 쪽에 10건씩 준다 */
    private const MAX_PAGES = 3;

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
            /* 창고는 per_page 를 듣지 않는다 — 무엇을 보내든 한 번에 10건씩 준다.
               「Cath」로 찾으면 57건 중 앞 10건만 와서, 찾는 제품이 뒤에 있으면
               영영 보이지 않았다. 다음 쪽을 이어 받는다.
               한 쪽이 0.15초라 세 쪽까지는 사람이 기다릴 만하다 — 30건이면 대개 충분하고,
               그보다 넓게 친 말은 검색어를 좁히는 편이 빠르다. */
            $fetch = fn (int $page) => Http::withToken($token)
                ->connectTimeout(5)
                ->timeout(15)
                ->get("{$baseUrl}/api/v1/item/item_list", [
                    'item' => $keyword,
                    'page' => $page,
                ]);

            $response = $fetch(1);

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

            // 뒤 쪽 이어 받기 — 세 쪽(30건)까지만
            $lastPage = (int) ($body['result']['last_page'] ?? 1);
            $total    = (int) ($body['result']['total'] ?? count($items));
            for ($page = 2; $page <= min($lastPage, self::MAX_PAGES); $page++) {
                $more = $fetch($page);
                if ($more->failed()) {
                    break;
                }
                $items = array_merge($items, $this->normalizeItems($more->json()));
            }

            // 못 보여 준 것이 있으면 숨기지 않는다 — 화면이 목록을 다 보여 준 것처럼 굴면 안 된다
            $shown   = count($items);
            $notice  = $total > $shown
                ? "{$total}건 가운데 {$shown}건입니다 — 검색어를 좁혀 주십시오."
                : null;

            Log::info('Demoworks 정규화 완료', ['count' => $shown, 'total' => $total]);

            /* 재고는 여기서 묻지 않는다.
               제품 하나의 재고 조회(inv_search/inv_info)가 7초 걸린다. 검색 결과 열 건이면
               열 번을 한꺼번에 물었고, 그동안 창고 API 가 막혀 제품 검색(평소 0.15초)마저
               10초를 넘겨 끊겼다 — 「제품 API 서버에 연결할 수 없습니다」의 정체다.
               재고는 고른 뒤 그 한 건만 따로 묻는다(/products/stock). */
            return response()->json([
                'success' => true,
                'data'    => $items,
                'total'   => $shown,
                'found'   => $total,
                'notice'  => $notice,
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
