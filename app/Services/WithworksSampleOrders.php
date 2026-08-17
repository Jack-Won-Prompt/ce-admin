<?php

namespace App\Services;

use App\Models\SampleOrder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * CE 샘플주문을 위드웍스로 넘긴다.
 *
 * 판매(so_store)와 길을 갈라 둔다. 샘플은 처방이 없고 확정도 하지 않으며, 창고에서도
 * 따로 보여야 한다 — 같은 길로 보내면 판매와 뒤섞인다.
 */
class WithworksSampleOrders
{
    public function configured(): bool
    {
        return (bool) (config('services.demoworks.api_url') && config('services.demoworks.token'));
    }

    /**
     * 샘플주문을 창고에 알린다.
     *
     * 실패해도 샘플 자체는 살려 둔다 — 창고에 알리지 못한 것과 등록하지 못한 것은
     * 다른 일이다. 대신 왜 못 갔는지를 적어 두어 다시 보낼 수 있게 한다.
     */
    public function push(SampleOrder $sample): bool
    {
        if (!$this->configured()) {
            return $this->fail($sample, '위드웍스 연동 설정이 없습니다');
        }

        if ($sample->withworks_so_no) {
            return true;   // 이미 보냈다 — 샘플번호로 멱등이지만 부르지 않는 편이 낫다
        }

        $items = $sample->items
            ->filter(fn ($i) => $i->product_code)
            ->map(fn ($i) => [
                'item_code'  => $i->product_code,
                'qty'        => max(1, (int) $i->quantity),
                'unit_price' => (int) $i->unit_price,
            ])
            ->values()
            ->all();

        if ($items === []) {
            return $this->fail($sample, '보낼 품목이 없습니다 — 제품코드를 확인하십시오');
        }

        $res = $this->call('post', 'sample_store', [
            'ce_sample_number' => $sample->sample_no,
            'customer_name'    => $sample->account_name ?: ($sample->patient?->name ?? ''),
            'recipient_name'   => $sample->recipient_name,
            'mobile'           => $sample->mobile,
            'zipcode'          => $sample->postcode,
            // 기본과 상세를 따로 보낸다 — 합쳐 보내면 저쪽이 상세를 한 번 더 붙인다
            'address'          => $sample->address,
            'address_detail'   => $sample->address_detail,
            'delivery_date'    => $sample->delivery_date?->format('Y-m-d'),
            'purpose'          => $sample->purpose,
            'items'            => $items,
        ]);

        if ($res === null || !($res['success'] ?? false)) {
            return $this->fail($sample, $res['message'] ?? '샘플주문을 보내지 못했습니다');
        }

        $r = $res['result'] ?? [];

        $sample->forceFill([
            'withworks_so_no'   => $this->fit($r['so_no'] ?? null, 50),
            'withworks_so_id'   => $r['so_id'] ?? null,
            'withworks_status'  => $this->fit($r['status'] ?? null, 50),
            'withworks_sent_at' => now(),
            'withworks_error'   => null,
            'status'            => 'sent',
        ])->save();

        // 등록 응답에는 상태 코드만 있고 이름이 없다 — 바로 되짚어 채운다
        if (!$sample->withworks_status_label) {
            $this->pull($sample);
        }

        return true;
    }

    /** 창고가 어디까지 했는지 물어와 적는다 */
    public function pull(SampleOrder $sample): ?array
    {
        if (!$this->configured() || !$sample->withworks_so_no) {
            return null;
        }

        $res = $this->call('get', 'sample_show', ['ce_sample_number' => $sample->sample_no]);

        if ($res === null || !($res['success'] ?? false)) {
            return null;
        }

        $r = $res['result'] ?? [];

        $sample->forceFill([
            'withworks_status'       => $this->fit($r['status'] ?? null, 50) ?? $sample->withworks_status,
            'withworks_status_label' => $this->fit($r['status_label'] ?? null, 100)
                ?? $sample->withworks_status_label,
            // 출고가 잡히면 우리 상태도 따라간다
            'status'                 => $r['ship_no'] ? 'shipped' : $sample->status,
        ])->save();

        return $r;
    }

    private function fail(SampleOrder $sample, string $message): bool
    {
        $sample->forceFill([
            'withworks_error'   => $this->fit($message, 500),
            'withworks_sent_at' => now(),
        ])->save();

        Log::warning('Withworks 샘플주문 전달 실패', [
            'sample' => $sample->sample_no, 'message' => $message,
        ]);

        return false;
    }

    private function call(string $method, string $path, array $payload): ?array
    {
        $baseUrl = rtrim((string) config('services.demoworks.api_url'), '/');

        try {
            $req = Http::withToken(config('services.demoworks.token'))->timeout(20);

            $res = $method === 'get'
                ? $req->get("{$baseUrl}/api/v1/ce-admin/{$path}", $payload)
                : $req->asForm()->post("{$baseUrl}/api/v1/ce-admin/{$path}", $payload);

            return $res->json() ?? ['success' => false, 'message' => 'HTTP ' . $res->status()];
        } catch (\Throwable $e) {
            Log::warning('Withworks 호출 실패', ['path' => $path, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /** 그쪽이 정하는 값이라 얼마나 길지 알 수 없다 — 칸에 맞춰 자른다 */
    private function fit($value, int $max): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return mb_substr((string) $value, 0, $max);
    }
}
