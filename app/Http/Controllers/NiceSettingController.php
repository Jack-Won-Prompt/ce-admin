<?php
// app/Http/Controllers/NiceSettingController.php
// NICE 본인확인 자격증명·정책 설정 관리 (관리자)

namespace App\Http\Controllers;

use App\Models\NiceSetting;
use App\Services\Nice\NiceIdentityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NiceSettingController extends Controller
{
    public function edit(): View
    {
        $setting = NiceSetting::current();

        return view('nice-settings.edit', [
            'setting'         => $setting,
            'configured'      => $setting->isConfigured(),
            'hasSecret'       => trim((string) $setting->client_secret) !== '',
            'defaultApiBase'  => config('nice.api_base'),
            'defaultStdUrl'   => config('nice.standard_url'),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'client_id'     => 'nullable|string|max:190',
            'client_secret' => 'nullable|string|max:255',
            'product_id'    => 'nullable|string|max:100',
            'api_base'      => 'nullable|url|max:190',
            'standard_url'  => 'nullable|url|max:190',
            'enforce'       => 'nullable|boolean',
            'match_name'    => 'nullable|boolean',
            'match_birth'   => 'nullable|boolean',
        ]);

        $setting = NiceSetting::current();

        // 비밀키는 화면에 원문을 내보내지 않으므로, 빈 값으로 제출되면 기존 값을 유지한다.
        if (trim((string) ($data['client_secret'] ?? '')) === '') {
            unset($data['client_secret']);
        }

        $setting->update($data + [
            'enforce'     => $request->boolean('enforce'),
            'match_name'  => $request->boolean('match_name'),
            'match_birth' => $request->boolean('match_birth'),
        ]);

        // 자격증명이 바뀌었을 수 있으므로 기관 토큰 캐시를 버린다.
        $setting->forgetAccessToken();

        activity()->causedBy(auth()->user())
            ->log('NICE 본인확인 설정 변경'.($setting->isConfigured() ? ' (활성)' : ' (자격증명 미완성)'));

        return redirect()->route('nice-settings.edit')
            ->with('status', 'NICE 본인확인 설정이 저장되었습니다.');
    }

    /**
     * 저장된 자격증명으로 NICE 기관토큰·암호화토큰 발급을 실제로 시도한다.
     * (표준창은 열지 않으므로 실제 본인확인 요금은 발생하지 않는다.)
     */
    public function test(): JsonResponse
    {
        $result = app(NiceIdentityService::class)->testConnection();

        if ($result['ok']) {
            NiceSetting::current()->update(['tested_at' => now()]);
        }

        activity()->causedBy(auth()->user())
            ->log('NICE 연결 테스트: '.($result['ok'] ? '성공' : '실패 — '.$result['message']));

        return response()->json($result);
    }
}
