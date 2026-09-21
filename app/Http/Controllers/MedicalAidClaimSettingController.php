<?php
// app/Http/Controllers/MedicalAidClaimSettingController.php
// 요양비 지급청구서[별지 제12호] 글자 자리 설정 (관리자). 위임장 설정과 같은 방식이다.

namespace App\Http\Controllers;

use App\Models\MedicalAidClaimSetting;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MedicalAidClaimSettingController extends Controller
{
    public function edit(): View
    {
        $setting = MedicalAidClaimSetting::current();

        return view('medical-aid-claim-settings.edit', [
            'setting' => $setting,
            // config 기본값 위에 담아 둔 좌표를 덮은 것 — 화면은 이것 하나만 본다
            'fields'  => $setting->fields(),
        ]);
    }

    public function update(Request $request)
    {
        /* 이 서식은 **가로 A4** 라 x 가 297, y 가 210 까지다.
           위임장(세로 A4)의 한계값을 그대로 옮기면 x 가 210 에서 잘린다. */
        $가로 = MedicalAidClaimSetting::가로;
        $세로 = MedicalAidClaimSetting::세로;

        $data = $request->validate([
            'sig_x'         => "required|numeric|min:0|max:{$가로}",
            'sig_y'         => "required|numeric|min:0|max:{$세로}",
            'sig_w'         => 'required|numeric|min:5|max:80',
            // 글자 칸 좌표 — 종이 밖으로 나가면 찍히지 않는다
            'fields'        => 'nullable|array',
            'fields.*.x'    => "nullable|numeric|min:0|max:{$가로}",
            'fields.*.y'    => "nullable|numeric|min:0|max:{$세로}",
            'fields.*.size' => 'nullable|numeric|min:4|max:20',
        ]);

        /* config 에 있는 칸만 받아 둔다. 화면에서 온 이름을 그대로 믿고 담으면
           쓰이지 않는 값이 쌓이고, 나중에 어느 것이 진짜인지 알 수 없게 된다. */
        $known  = array_keys(MedicalAidClaimSetting::defaultFields());
        $fields = [];

        foreach ((array) ($data['fields'] ?? []) as $key => $v) {
            if (! in_array($key, $known, true)) {
                continue;
            }

            $row = array_filter([
                'x'    => $v['x']    ?? null,
                'y'    => $v['y']    ?? null,
                'size' => $v['size'] ?? null,
            ], fn ($n) => $n !== null && $n !== '');

            if ($row) {
                $fields[$key] = array_map('floatval', $row);
            }
        }

        unset($data['fields']);
        $data['field_positions'] = $fields ?: null;

        MedicalAidClaimSetting::current()->update($data);

        activity()->causedBy(auth()->user())
            ->log('요양비 지급청구서 글자 자리 설정 변경');

        return redirect()->route('medical-aid-claim-settings.edit')
            ->with('status', '요양비 지급청구서 설정이 저장되었습니다.');
    }
}
