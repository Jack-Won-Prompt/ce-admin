<?php

namespace App\Http\Controllers;

use App\Events\ChatMessageSent;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\ShopOrder;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ShopOrderWebhookController extends Controller
{
    public function receive(Request $request): JsonResponse
    {
        // 시크릿 검증
        $secret = config('services.ce_shop.webhook_secret', 'ce-shop-secret-2026');
        $provided = $request->header('X-Shop-Secret') ?? $request->input('secret');
        if ($provided !== $secret) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $jsonDecoded = json_decode($request->getContent(), true) ?? [];
        $input = !empty($jsonDecoded) ? $jsonDecoded : $request->all();
        $validator = Validator::make($input, [
            'shop_order_id'   => 'required|integer',
            'order_number'    => 'required|string',
            'customer'        => 'required|array',
            'items'           => 'required|array',
            'subtotal'        => 'required|numeric',
            'discount_rate'   => 'nullable|numeric',
            'discount_amount' => 'nullable|numeric',
            'shipping_fee'    => 'nullable|numeric',
            'total_amount'    => 'required|numeric',
            'delivery_method' => 'nullable|string',
            'delivery'        => 'nullable|array',
            'buyer_note'      => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }
        $data = $validator->validated();

        // 중복 처리 방지
        if (ShopOrder::where('shop_order_id', $data['shop_order_id'])->exists()) {
            return response()->json(['message' => 'Already processed']);
        }

        $delivery = $data['delivery'] ?? [];

        $order = ShopOrder::create([
            'shop_order_id'   => $data['shop_order_id'],
            'order_number'    => $data['order_number'],
            'customer_name'   => $data['customer']['name'] ?? '',
            'customer_phone'  => $data['customer']['phone'] ?? null,
            'customer_company'=> $data['customer']['company'] ?? null,
            'items'           => $data['items'],
            'subtotal'        => $data['subtotal'],
            'discount_rate'   => $data['discount_rate'] ?? 0,
            'discount_amount' => $data['discount_amount'] ?? 0,
            'shipping_fee'    => $data['shipping_fee'] ?? 0,
            'total_amount'    => $data['total_amount'],
            'delivery_method' => $data['delivery_method'] ?? null,
            'delivery_name'   => $delivery['name'] ?? null,
            'delivery_phone'  => $delivery['phone'] ?? null,
            'delivery_zipcode'=> $delivery['zipcode'] ?? null,
            'delivery_address'=> $delivery['address'] ?? null,
            'delivery_note'   => $delivery['note'] ?? null,
            'buyer_note'      => $data['buyer_note'] ?? null,
            'status'          => 'confirmed',
        ]);

        // 전체 사용자에게 채팅 알림
        $this->notifyAllUsers($order);

        // Withworks 판매주문 등록
        $this->syncToWithworks($order);

        return response()->json(['message' => 'OK', 'id' => $order->id]);
    }

    private function notifyAllUsers(ShopOrder $order): void
    {
        try {
            $userIds = User::pluck('id')->toArray();
            if (empty($userIds)) return;

            // 전체 알림 그룹방 조회 or 생성
            $room = ChatRoom::where('type', 'group')->where('name', 'CE샵 주문알림')->first();
            if (!$room) {
                $room = ChatRoom::create(['type' => 'group', 'name' => 'CE샵 주문알림']);
                $room->users()->attach($userIds, ['last_read_at' => now()]);
            } else {
                // 새 사용자가 있으면 추가
                $existing = $room->users()->pluck('user_id')->toArray();
                $newUsers = array_diff($userIds, $existing);
                if ($newUsers) {
                    $room->users()->attach($newUsers, ['last_read_at' => now()]);
                }
            }

            $itemSummary = collect($order->items)
                ->map(fn ($i) => "{$i['product_name']} x{$i['quantity']}")
                ->take(3)->implode(', ');
            if (count($order->items) > 3) $itemSummary .= ' 외 ' . (count($order->items) - 3) . '건';

            $body = "🛒 CE샵 신규 주문\n"
                . "주문번호: {$order->order_number}\n"
                . "고객: {$order->customer_name}" . ($order->customer_company ? " ({$order->customer_company})" : '') . "\n"
                . "상품: {$itemSummary}\n"
                . "결제금액: " . number_format($order->total_amount) . "원";

            $adminUser = User::first();
            $message = ChatMessage::create([
                'chat_room_id' => $room->id,
                'user_id'      => $adminUser->id,
                'body'         => $body,
            ]);

            broadcast(new ChatMessageSent($message));
        } catch (\Throwable $e) {
            Log::error('ShopOrder chat notify failed', ['error' => $e->getMessage()]);
        }
    }

    private function syncToWithworks(ShopOrder $order): void
    {
        $baseUrl = rtrim(config('services.todoworks.api_url', ''), '/');
        $token   = config('services.todoworks.token');

        if (!$baseUrl || !$token) {
            Log::info('Withworks not configured, skipping sync', ['order' => $order->order_number]);
            return;
        }

        $items = collect($order->items)->map(function ($item) use ($baseUrl, $token, $order) {
            $itemCode = $this->resolveWithworksItemCode($item, $baseUrl, $token);

            if (!$itemCode) {
                Log::warning('ShopOrder Withworks item code unresolved', [
                    'order' => $order->order_number,
                    'item' => $item,
                ]);
                return null;
            }

            return [
                'item_code'  => $itemCode,
                'qty'        => (int) ($item['quantity'] ?? 0),
                'unit_price' => (float) ($item['unit_price'] ?? 0),
            ];
        })->filter()->values()->toArray();

        if (empty($items)) {
            Log::warning('ShopOrder Withworks: no valid items', ['order' => $order->order_number]);
            return;
        }

        $payload = [
            'ce_order_number'  => $order->order_number,
            'rx_number'        => $order->order_number,
            'patient_name'     => $order->customer_name,
            'patient_mobile'   => $order->customer_phone,
            'patient_zipcode'  => $order->delivery_zipcode,
            'shipping_address' => $order->delivery_address,
            'recipient_name'   => $order->delivery_name,
            'remark'           => $order->buyer_note,
            'items'            => $items,
            'so_type'          => '1016',
        ];

        try {
            $response = Http::withToken($token)->timeout(15)->asForm()
                ->post("{$baseUrl}/api/v1/ce-admin/so_store", $payload);

            $body = $response->json();
            if ($response->successful() && ($body['success'] ?? false)) {
                $order->update([
                    'withworks_so_no' => $body['result']['so_no'] ?? null,
                    'withworks_so_id' => $body['result']['so_id'] ?? null,
                ]);
                Log::info('ShopOrder Withworks SO created', ['so_no' => $body['result']['so_no'] ?? null]);
            } else {
                Log::warning('ShopOrder Withworks SO failed', ['body' => $body]);
            }
        } catch (\Throwable $e) {
            Log::error('ShopOrder Withworks exception', ['error' => $e->getMessage()]);
        }
    }

    private function resolveWithworksItemCode(array $item, string $baseUrl, string $token): ?string
    {
        $candidates = collect([
            $item['product_sku'] ?? null,
            $item['model_number'] ?? null,
            !empty($item['model_number']) ? $item['model_number'] . '#' : null,
            $item['insurance_code'] ?? null,
            $item['product_name'] ?? null,
        ])->filter(fn ($value) => filled($value))
          ->map(fn ($value) => trim((string) $value))
          ->unique()
          ->values();

        foreach ($candidates as $candidate) {
            $resolved = $this->searchTodoworksItemCode($baseUrl, $token, $candidate);
            if ($resolved) {
                return $resolved;
            }
        }

        return null;
    }

    private function searchTodoworksItemCode(string $baseUrl, string $token, string $keyword): ?string
    {
        try {
            $response = Http::withToken($token)
                ->timeout(10)
                ->get("{$baseUrl}/api/v1/item/item_list", [
                    'item'     => $keyword,
                    'per_page' => 10,
                ]);

            if (!$response->successful()) {
                return null;
            }

            $items = $response->json('result.data') ?? [];
            if (!is_array($items) || empty($items)) {
                return null;
            }

            foreach ($items as $item) {
                $code = trim((string) ($item['item_code'] ?? ''));
                if ($code !== '' && strcasecmp($code, $keyword) === 0) {
                    return $code;
                }
            }

            foreach ($items as $item) {
                $fields = [
                    trim((string) ($item['item_code'] ?? '')),
                    trim((string) ($item['erp_code'] ?? '')),
                    trim((string) ($item['edi_code'] ?? '')),
                    trim((string) ($item['item_name'] ?? '')),
                    trim((string) ($item['udi_model_name'] ?? '')),
                ];

                if (collect($fields)->contains(fn ($field) => $field !== '' && strcasecmp($field, $keyword) === 0)) {
                    return trim((string) ($item['item_code'] ?? '')) ?: null;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Todoworks item search failed', [
                'keyword' => $keyword,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }
}
