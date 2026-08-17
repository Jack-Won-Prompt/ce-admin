<?php

namespace App\Http\Controllers;

use App\Models\WithworksSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

/**
 * 위드웍스 연동 설정.
 *
 * 예전에는 .env 에 있었다. 그러면 테스트와 운영을 오갈 때마다 서버에 들어가 파일을 고치고
 * config:clear 를 해야 했고, 지금 어느 쪽에 붙어 있는지 화면에서 알 수 없었다.
 */
class WithworksSettingController extends Controller
{
    public function edit(): View
    {
        return view('withworks-settings.edit', ['s' => WithworksSetting::current()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mode'            => 'required|in:test,production',
            'test_api_url'    => 'nullable|url|max:190',
            'test_api_token'  => 'nullable|string',
            'test_account_id' => 'nullable|string|max:30',
            'prod_api_url'    => 'nullable|url|max:190',
            'prod_api_token'  => 'nullable|string',
            'prod_account_id' => 'nullable|string|max:30',
            'webhook_url'     => 'nullable|url|max:190',
            'webhook_secret'  => 'nullable|string|max:190',
            'so_type'         => 'required|string|max:20',
        ]);

        $s = WithworksSetting::current();

        /* 토큰은 화면에 내려보내지 않으므로 빈 값으로 올라온다. 그것을 그대로 저장하면
           저장 버튼을 누를 때마다 토큰이 지워진다 — 빈 값은 「안 바꿈」으로 읽는다. */
        foreach (['test_api_token', 'prod_api_token'] as $k) {
            if (($data[$k] ?? '') === '' || $data[$k] === null) {
                unset($data[$k]);
            }
        }

        $before = $s->mode;
        $s->update($data);

        // 운영으로 옮기는 것은 실수하면 아픈 일이라 누가 언제 했는지 남긴다
        if ($before !== $s->mode) {
            activity()->causedBy(Auth::user())->performedOn($s)
                ->log("위드웍스 연동 전환: {$before} → {$s->mode}");
        }

        return back()->with('status', '저장했습니다. 지금은 ' . $s->modeLabel() . '에 붙어 있습니다.');
    }

    /**
     * 지금 설정으로 실제로 닿는지 확인한다.
     *
     * 주소·토큰이 맞는지는 저장만으로 알 수 없다. 눌러서 한 번 물어보게 한다.
     */
    public function test(): RedirectResponse
    {
        $s = WithworksSetting::applyToConfig();

        if (!$s->apiUrl() || !$s->apiToken()) {
            return back()->with('test_result', '주소나 토큰이 비어 있습니다.');
        }

        try {
            $res = Http::withToken($s->apiToken())->timeout(15)
                ->get(rtrim($s->apiUrl(), '/') . '/api/v1/ce-admin/so_show', ['ce_order_number' => 'PING']);

            /* 없는 주문을 물었으므로 「찾을 수 없다」가 정상이다. 인증이 틀리면 그 전에
               막히므로, 여기까지 왔다는 것 자체가 주소·토큰이 맞다는 뜻이다. */
            $body = $res->json();
            $ok   = $res->successful() && is_array($body) && array_key_exists('success', $body);

            $msg = $ok
                ? "연결 정상 — {$s->modeLabel()} ({$s->apiUrl()})"
                : "응답이 이상합니다 — HTTP {$res->status()} · " . mb_substr($res->body(), 0, 120);
        } catch (\Throwable $e) {
            $msg = '닿지 못했습니다 — ' . mb_substr($e->getMessage(), 0, 150);
        }

        return back()->with('test_result', $msg);
    }
}
