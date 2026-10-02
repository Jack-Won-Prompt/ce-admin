<?php
// app/Http/Controllers/ServiceSettingController.php
// 외부 서비스 키·설정 관리 (관리자). 항목 목록은 config/settings-schema.php 가 쥔다.

namespace App\Http\Controllers;

use App\Support\ServiceSettings;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceSettingController extends Controller
{
    /**
     * 이 묶음은 이 계정에만 보인다 (2026-10-02 지시).
     *
     * Agent 연계는 오류와 SR 을 밖으로 내보내는 자리다 — 그 짐에는 환자 이름ㆍ
     * 주민등록번호가 보이는 캡처가 실릴 수 있다. 켜고 끄는 일을 한 사람에게만
     * 둔다. 화면에서 가리는 것만으로는 닫은 것이 아니므로 저장도 함께 막는다.
     */
    private const 전용묶음 = ['agent' => 'admin@ce-admin.co.kr'];

    /** 이 사람이 그 묶음을 다룰 수 있는가 */
    private function 다룰수있나(string $group): bool
    {
        $주인 = self::전용묶음[$group] ?? null;

        return $주인 === null || auth()->user()?->email === $주인;
    }

    public function index(Request $request): View
    {
        $schema = collect(ServiceSettings::schema())
            ->filter(fn ($_, $group) => $this->다룰수있나($group))
            ->all();

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

        /* 화면에 없던 묶음은 저장도 받지 않는다 — 요청은 직접 보낼 수 있다.
           없는 것처럼 404 로 답한다(있다는 것조차 알릴 까닭이 없다). */
        abort_if(! $this->다룰수있나($group), 404);

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
