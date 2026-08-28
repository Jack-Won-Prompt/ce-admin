<?php
// app/Http/Controllers/RegistrationSettingController.php
// 자가도뇨 소모성 재료 등록 신청서(별지 제4호서식) 설정 관리 (관리자)

namespace App\Http\Controllers;

use App\Models\RegistrationSetting;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RegistrationSettingController extends Controller
{
    public function edit(): View
    {
        $setting = RegistrationSetting::current();

        return view('registration-settings.edit', [
            'setting' => $setting,
            // 파일 기본값 위에 저장된 좌표를 덮은 것 — 화면은 이것만 본다
            'fields'  => $setting->fields(),
            'checks'  => $setting->checks(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'sig_x'          => 'required|numeric|min:0|max:210',
            'sig_y'          => 'required|numeric|min:0|max:297',
            'sig_w'          => 'required|numeric|min:5|max:80',
            // A4 밖으로 나가면 종이에 안 찍힌다
            'fields'         => 'nullable|array',
            'fields.*.x'     => 'nullable|numeric|min:0|max:210',
            'fields.*.y'     => 'nullable|numeric|min:0|max:297',
            'fields.*.size'  => 'nullable|numeric|min:4|max:20',
            'checks'         => 'nullable|array',
            'checks.*.x'     => 'nullable|numeric|min:0|max:210',
            'checks.*.y'     => 'nullable|numeric|min:0|max:297',
        ]);

        $setting = RegistrationSetting::current();
        $setting->update([
            'sig_x' => $data['sig_x'],
            'sig_y' => $data['sig_y'],
            'sig_w' => $data['sig_w'],
            'field_positions' => self::clean($data['fields'] ?? [], RegistrationSetting::defaultFields(), ['x', 'y', 'size']),
            'check_positions' => self::clean($data['checks'] ?? [], RegistrationSetting::defaultChecks(), ['x', 'y']),
        ]);

        activity()->causedBy(auth()->user())->log('자가도뇨 등록 신청서 설정 변경');

        return redirect()->route('registration-settings.edit')
            ->with('status', '등록 신청서 설정이 저장되었습니다.');
    }

    /**
     * 파일에 있는 항목만 받아 둔다.
     *
     * 화면에서 온 이름을 그대로 믿고 저장하면 쓰이지 않는 값이 쌓이고, 나중에 어느 것이
     * 진짜인지 알 수 없게 된다(위임장 설정과 같은 규칙).
     */
    private static function clean(array $in, array $known, array $keys): ?array
    {
        $out = [];
        foreach ($in as $key => $v) {
            if (! array_key_exists($key, $known) || ! is_array($v)) continue;
            $row = [];
            foreach ($keys as $k) {
                if (isset($v[$k]) && $v[$k] !== '') $row[$k] = (float) $v[$k];
            }
            if ($row) $out[$key] = $row;
        }
        return $out ?: null;
    }
}
