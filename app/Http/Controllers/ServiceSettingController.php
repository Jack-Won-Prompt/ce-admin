<?php
// app/Http/Controllers/ServiceSettingController.php
// 외부 서비스 키·설정 관리 (관리자). 항목 목록은 config/settings-schema.php 가 쥔다.

namespace App\Http\Controllers;

use App\Support\ServiceSettings;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceSettingController extends Controller
{
    public function index(Request $request): View
    {
        $schema = ServiceSettings::schema();
        $active = $request->query('tab');
        if (! isset($schema[$active])) {
            $active = array_key_first($schema);
        }

        $values = [];
        foreach ($schema as $group => $_) {
            $values[$group] = ServiceSettings::valuesFor($group);
        }

        return view('service-settings.index', [
            'schema' => $schema,
            'values' => $values,
            'active' => $active,
        ]);
    }

    public function update(Request $request, string $group)
    {
        $def = ServiceSettings::group($group);
        abort_if(! $def, 404);

        $rules = [];
        foreach ($def['fields'] as $key => $f) {
            $rules[$key] = match ($f['type'] ?? 'text') {
                'bool'   => 'nullable',
                'int'    => 'nullable|integer',
                'select' => 'nullable|string|in:'.implode(',', array_keys($f['options'] ?? [])),
                default  => 'nullable|string|max:500',
            };
        }
        $request->validate($rules);

        $changed = ServiceSettings::save($group, $request->all());

        // 무엇이 바뀌었는지만 남긴다. 키 값 자체는 로그에 절대 남기지 않는다.
        activity()->causedBy(auth()->user())
            ->log(sprintf('서비스 설정 변경 — %s (%d개 항목)', $def['label'], $changed));

        return redirect()
            ->route('service-settings.index', ['tab' => $group])
            ->with('success', $changed
                ? sprintf('%s 설정 %d개 항목을 저장했습니다.', $def['label'], $changed)
                : '변경된 내용이 없습니다.');
    }
}
