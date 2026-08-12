<?php
// app/Http/Controllers/DelegationSettingController.php
// 요양비 위임장 설정 관리 (관리자)

namespace App\Http\Controllers;

use App\Models\DelegationSetting;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DelegationSettingController extends Controller
{
    public function edit(): View
    {
        $setting = DelegationSetting::current();

        return view('delegation-settings.edit', [
            'setting' => $setting,
            // config 기본값 위에 저장된 좌표를 덮은 것 — 화면은 이것 하나만 본다
            'fields'  => $setting->fields(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'provider_name'    => 'nullable|string|max:150',
            'provider_biz_no'  => 'nullable|string|max:40',
            'provider_ceo'     => 'nullable|string|max:50',
            'provider_phone'   => 'nullable|string|max:40',
            'account_receiver' => 'nullable|string|max:100',
            'account_bank'     => 'nullable|string|max:50',
            'account_holder'   => 'nullable|string|max:100',
            'account_number'   => 'nullable|string|max:50',
            'period_years'     => 'required|integer|min:1|max:5',
            'sig_x'            => 'required|numeric|min:0|max:210',
            'sig_y'            => 'required|numeric|min:0|max:297',
            'sig_w'            => 'required|numeric|min:5|max:80',
            'gsig_x'           => 'nullable|numeric|min:0|max:210',
            'gsig_y'           => 'nullable|numeric|min:0|max:297',
            'gsig_w'           => 'nullable|numeric|min:5|max:80',
            // 글자 항목 좌표 — A4 밖으로 나가면 종이에 안 찍힌다
            'fields'           => 'nullable|array',
            'fields.*.x'       => 'nullable|numeric|min:0|max:210',
            'fields.*.y'       => 'nullable|numeric|min:0|max:297',
            'fields.*.size'    => 'nullable|numeric|min:4|max:20',
        ]);

        /* config 에 있는 항목만 받아 둔다. 화면에서 온 이름을 그대로 믿고 저장하면
           쓰이지 않는 값이 쌓이고, 나중에 어느 것이 진짜인지 알 수 없게 된다. */
        $known  = array_keys(DelegationSetting::defaultFields());
        $fields = [];
        foreach ((array) ($data['fields'] ?? []) as $key => $v) {
            if (!in_array($key, $known, true)) continue;
            $row = array_filter([
                'x'    => $v['x']    ?? null,
                'y'    => $v['y']    ?? null,
                'size' => $v['size'] ?? null,
            ], fn ($n) => $n !== null && $n !== '');
            if ($row) $fields[$key] = array_map('floatval', $row);
        }
        unset($data['fields']);
        $data['field_positions'] = $fields ?: null;

        $setting = DelegationSetting::current();
        $setting->update($data);

        activity()->causedBy(auth()->user())
            ->log('요양비 위임장 설정 변경');

        return redirect()->route('delegation-settings.edit')
            ->with('status', '위임장 설정이 저장되었습니다.');
    }
}
