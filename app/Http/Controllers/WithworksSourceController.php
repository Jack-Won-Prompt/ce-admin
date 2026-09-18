<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\WithworksImport;
use App\Support\WithworksSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 위드웍스 자료 가져오기 — 설정 화면 (2026-09-18 지시).
 *
 * 세 가지를 한 자리에서 한다.
 *
 * **① 접속 정보** — 창고(warehouse)ㆍ관리(admin) 두 DB. 비밀번호는 담을 때 암호화하고
 *    화면으로 내려보내지 않는다. 바꿀 때만 적는다.
 *
 * **② 마지막 번호** — 어디까지 담았는지. 0 으로 되돌리면 처음부터 다시 읽는다.
 *    손으로 고칠 수 있게 두는 까닭은, 저쪽에서 옛 줄을 고쳤을 때 그만큼만 되짚어
 *    다시 담기 위해서다.
 *
 * **③ 가져오기** — 눌러서 그 자리에서 담는다. 늘어난 줄만 읽으므로 대개 몇 초다.
 *    첫 판처럼 열 만 줄을 담을 때는 명령(`withworks:import`)으로 하는 편이 낫다 —
 *    웹 요청은 오래 쥐고 있으면 끊긴다.
 *
 * 저쪽 DB 는 **읽기만 한다**. 그 다짐은 WithworksSource 가 연결마다 건다.
 */
class WithworksSourceController extends Controller
{
    /** 접속 정보로 담는 칸 — 비밀은 따로 가른다 */
    private const 접속칸 = ['host', 'port', 'database', 'username', 'password'];
    private const 비밀칸 = ['password'];

    public function index(WithworksImport $svc): View
    {
        $계정 = [];
        foreach (array_keys(WithworksSource::갈래) as $갈래) {
            foreach (self::접속칸 as $칸) {
                $값 = WithworksSource::값($갈래, $칸);
                /* 비밀은 원문을 내려보내지 않는다 — 담겼는지만 알린다 */
                $계정[$갈래][$칸] = in_array($칸, self::비밀칸, true)
                    ? ['filled' => trim((string) $값) !== '', 'value' => null]
                    : ['filled' => true, 'value' => $값];
            }
        }

        return view('withworks-source.index', [
            '계정' => $계정,
            '현황' => $svc->현황(),
        ]);
    }

    /** 접속 정보와 마지막 번호를 담는다 */
    public function save(Request $request, WithworksImport $svc)
    {
        $바뀜 = 0;

        foreach (array_keys(WithworksSource::갈래) as $갈래) {
            foreach (self::접속칸 as $칸) {
                $이름 = "{$갈래}_{$칸}";

                if (! $request->has($이름)) { continue; }

                $새것 = trim((string) $request->input($이름));
                $비밀 = in_array($칸, self::비밀칸, true);

                /* 비밀은 빈칸이면 그대로 둔다 — 화면이 원문을 모르므로, 빈칸을
                   「지우기」로 받으면 열어 볼 때마다 비밀번호가 날아간다. */
                if ($비밀 && $새것 === '') { continue; }

                $줄 = Setting::firstOrNew(['group' => WithworksSource::묶음, 'key' => $이름]);
                if ($줄->exists && $줄->plainValue() === $새것) { continue; }

                $줄->setPlainValue($새것, secret: $비밀);
                $줄->save();
                $바뀜++;
            }
        }

        foreach (array_keys(WithworksImport::대상) as $열쇠) {
            $이름 = "{$열쇠}_last_id";
            if (! $request->has($이름)) { continue; }

            $새것 = max(0, (int) $request->input($이름));
            if ($새것 === $svc->마지막번호($열쇠)) { continue; }

            $svc->마지막번호저장($열쇠, $새것);
            $바뀜++;
        }

        activity()->causedBy(auth()->user())
            ->log("위드웍스 자료 가져오기 설정 변경 ({$바뀜}개 항목)");

        return redirect()->route('withworks-source.index')
            ->with('success', $바뀜 ? "{$바뀜}개 항목을 저장했습니다." : '변경된 내용이 없습니다.');
    }

    /** 닿는지 본다 */
    public function test(string $갈래): JsonResponse
    {
        abort_unless(isset(WithworksSource::갈래[$갈래]), 404);

        $r = WithworksSource::닿나($갈래);

        return response()->json([
            'success' => $r['ok'],
            'message' => $r['msg'],
        ], $r['ok'] ? 200 : 422);
    }

    /**
     * 가져온다.
     *
     * 웹 요청이라 오래 걸리면 끊긴다 — 제한을 풀되, 첫 판은 명령으로 하라고 화면이 적어 둔다.
     */
    public function import(Request $request, WithworksImport $svc): JsonResponse
    {
        $열쇠 = (string) $request->input('대상');
        abort_unless(isset(WithworksImport::대상[$열쇠]), 404);

        @set_time_limit(0);
        @ignore_user_abort(true);

        try {
            $r = $svc->가져오기($열쇠);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                /* 질의가 통째로 실린 오류는 수십만 자다 — 앞머리만 보낸다 */
                'message' => mb_substr($e->getMessage(), 0, 300),
            ], 500);
        }

        activity()->causedBy(auth()->user())->log(sprintf(
            '위드웍스 %s 가져오기 — %s줄 · 마지막 %s',
            $r['이름'], number_format($r['읽음']), number_format($r['마지막'])
        ));

        return response()->json([
            'success' => true,
            'message' => $r['읽음']
                ? sprintf('%s %s줄을 가져왔습니다. 마지막 번호 %s',
                    $r['이름'], number_format($r['읽음']), number_format($r['마지막']))
                : "{$r['이름']} — 새로 들어온 줄이 없습니다.",
            '현황' => $svc->현황()[$열쇠],
        ]);
    }
}
