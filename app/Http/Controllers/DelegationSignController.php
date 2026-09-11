<?php

namespace App\Http\Controllers;

use App\Models\DelegationSign;
use App\Services\Popbill\MessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * 운영 데이터 › 위임장 서명 — 담당자 쪽 화면 (2026-09-11 지시).
 *
 * 기존 처방ㆍ주문ㆍ거래처와 잇지 않는다. 명단을 올리고, 번호를 골라 보내고,
 * 받은 서명을 본다 — 그것으로 닫힌다.
 */
class DelegationSignController extends Controller
{
    public function __construct(private MessageService $sender)
    {
    }

    // ── 목록 ──────────────────────────────────────────────
    public function index(Request $request): View
    {
        $query = DelegationSign::query()->latest('id');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('signed')) {
            $request->signed === 'y'
                ? $query->whereNotNull('signed_at')
                : $query->whereNull('signed_at');
        }

        if ($request->filled('sender')) {
            $query->where('sent_by_id', $request->sender);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->filled('q')) {
            $말 = $request->q;
            $query->where(fn ($s) => $s
                ->where('customer_name', 'like', "%{$말}%")
                ->orWhere('phone1', 'like', "%{$말}%")
                ->orWhere('phone2', 'like', "%{$말}%"));
        }

        $줄 = $query->limit((int) config('delegation_sign.list_limit', 2000))->get()
            ->map(fn (DelegationSign $d) => [
                'id'         => $d->id,
                'customer'   => $d->customer_name,
                'phone1'     => \App\Support\PhoneNo::format($d->phone1),
                'phone2'     => \App\Support\PhoneNo::format($d->phone2),
                'status'     => DelegationSign::상태[$d->status] ?? $d->status,
                'delegation' => $d->동의말('agree_delegation'),
                'privacy'    => $d->동의말('agree_privacy'),
                'marketing'  => $d->동의말('agree_marketing'),
                'signed_at'  => $d->signed_at?->format('Y-m-d H:i') ?? '',
                'sender'     => $d->sent_by_name ?? '',
                'has_sign'   => (bool) ($d->sign_path || $d->sign_base64),
                'can_send'   => (bool) ($d->phone1 || $d->phone2),
            ]);

        /* 보낸 사람 고르개 — 이 표에 적힌 이름만 세운다. users 를 훑지 않는다. */
        $보낸이 = DelegationSign::whereNotNull('sent_by_id')
            ->select('sent_by_id', 'sent_by_name')->distinct()
            ->orderBy('sent_by_name')->get();

        return view('delegation-signs.index', compact('줄', '보낸이'));
    }

    // ── 명단 올리기 (CSV) ─────────────────────────────────
    /**
     * 거래처명ㆍ전화번호 1ㆍ2 세 칸짜리 CSV 를 받는다.
     *
     * 엑셀 라이브러리가 없는 환경이라 CSV 로 받는다(엑셀에서 「CSV UTF-8」 또는
     * 「CSV」로 저장). 한글 윈도우 엑셀이 cp949 로 떨어뜨리는 일이 잦아 둘 다 읽는다.
     *
     * 같은 이름ㆍ같은 번호가 이미 있으면 새로 세우지 않는다 — 명단을 두 번 올려도
     * 줄이 두 벌이 되지 않는다.
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:5120',
        ], [
            'file.mimes' => 'CSV 파일만 올릴 수 있습니다 — 엑셀에서 「CSV」로 저장해 주십시오.',
        ]);

        $글 = file_get_contents($request->file('file')->getRealPath());
        if ($글 === false) {
            return response()->json(['success' => false, 'message' => '파일을 읽지 못했습니다.'], 422);
        }

        /* 엑셀이 앞에 붙이는 BOM 을 떼고, cp949 면 UTF-8 로 옮긴다 */
        $글 = preg_replace('~^\xEF\xBB\xBF~', '', $글);
        if (! mb_check_encoding($글, 'UTF-8')) {
            $글 = mb_convert_encoding($글, 'UTF-8', 'CP949');
        }

        $줄들 = preg_split('/\r\n|\r|\n/', trim($글));
        if (! $줄들) {
            return response()->json(['success' => false, 'message' => '내용이 비어 있습니다.'], 422);
        }

        /* 첫 줄이 머리글이면 건너뛴다 — 「거래처」나 「이름」이 적혀 있으면 머리글로 본다 */
        if (preg_match('/거래처|이름|customer/i', $줄들[0])) {
            array_shift($줄들);
        }

        $세움 = 0;
        $건너뜀 = 0;
        $잘못 = [];

        foreach ($줄들 as $번 => $줄) {
            $칸 = str_getcsv($줄);
            $이름 = trim((string) ($칸[0] ?? ''));
            $번호1 = self::번호만(trim((string) ($칸[1] ?? '')));
            $번호2 = self::번호만(trim((string) ($칸[2] ?? '')));

            if ($이름 === '') {
                continue;                                   // 빈 줄은 조용히 지나간다
            }

            if (! $번호1 && ! $번호2) {
                $잘못[] = ($번 + 1) . '째 줄 — ' . $이름 . ' : 번호가 하나도 없습니다';
                continue;
            }

            /* 같은 이름ㆍ같은 번호는 다시 세우지 않는다 */
            $있나 = DelegationSign::where('customer_name', $이름)
                ->where(fn ($s) => $s->where('phone1', $번호1)->orWhere('phone2', $번호1))
                ->exists();

            if ($있나) {
                $건너뜀++;
                continue;
            }

            DelegationSign::create([
                'customer_name' => mb_substr($이름, 0, 100),
                'phone1'        => $번호1 ?: null,
                'phone2'        => $번호2 ?: null,
                'status'        => 'pending',
            ]);
            $세움++;
        }

        activity()->causedBy(Auth::user())
            ->log("위임장 서명 명단 올림 — 새로 {$세움}건 · 이미 있어 지나감 {$건너뜀}건");

        return response()->json([
            'success' => true,
            'message' => "새로 {$세움}건을 세웠습니다."
                       . ($건너뜀 ? " 이미 있어 지나간 것 {$건너뜀}건." : '')
                       . ($잘못 ? ' 넣지 못한 줄 ' . count($잘못) . '건.' : ''),
            'errors'  => array_slice($잘못, 0, 20),
        ]);
    }

    /** 숫자만 남긴다 — 화면에서 다시 꼴을 갖춘다 */
    private static function 번호만(string $값): string
    {
        $숫 = preg_replace('/\D/', '', $값);

        return (strlen($숫) >= 9 && strlen($숫) <= 11) ? $숫 : '';
    }

    // ── 발송 팝오버가 묻는 것 ─────────────────────────────
    public function show(DelegationSign $delegationSign): JsonResponse
    {
        return response()->json([
            'success'  => true,
            'id'       => $delegationSign->id,
            'customer' => $delegationSign->customer_name,
            'phone1'   => \App\Support\PhoneNo::format($delegationSign->phone1),
            'phone2'   => \App\Support\PhoneNo::format($delegationSign->phone2),
            'raw1'     => $delegationSign->phone1,
            'raw2'     => $delegationSign->phone2,
            'status'   => DelegationSign::상태[$delegationSign->status] ?? $delegationSign->status,
            'signed'   => $delegationSign->status === 'signed',
        ]);
    }

    // ── 보내기 ────────────────────────────────────────────
    /**
     * 고른 번호로 서명 링크를 보낸다.
     *
     * 서명을 이미 받아 둔 줄에 다시 보내도 **받아 둔 서명은 지우지 않는다**
     * (2026-09-11 지시). 보내다 만 건에서 서명이 사라지면 되돌릴 수 없다 —
     * 새 서명이 들어오는 그때 덮는다.
     */
    public function send(Request $request, DelegationSign $delegationSign): JsonResponse
    {
        $값 = $request->validate([
            'which' => 'required|in:phone1,phone2',
            'name'  => 'nullable|string|max:100',
        ]);

        $번호 = preg_replace('/\D/', '', (string) $delegationSign->{$값['which']});
        if (strlen($번호) < 9 || strlen($번호) > 11) {
            return response()->json([
                'success' => false,
                'message' => DelegationSign::번호자리[$값['which']] . ' 가 비었거나 꼴이 맞지 않습니다.',
            ], 422);
        }

        $이름 = trim((string) ($값['name'] ?? '')) ?: $delegationSign->customer_name;
        $토큰 = Str::random(24);
        $만료 = now()->addMinutes((int) config('delegation_sign.link_minutes', 30));

        $터 = rtrim(config('app.consent_public_url', config('app.url')), '/');
        if (str_starts_with($터, 'http://')) {
            $터 = 'https://' . substr($터, 7);          // 일부 문자 앱이 http 를 링크로 안 만든다
        }
        $길 = $터 . '/delegation/' . $토큰;

        $글 = "[콜로플라스트] {$이름}님\n요양비 청구 위임장 전자서명 요청입니다.\n서명 링크(30분 유효):\n{$길}";

        try {
            /* 발송 내역에 쌓이는 길로 보낸다 — 팝빌을 곧바로 부르면 나갔는지 알 수 없다 */
            $res = $this->sender->sendBulk('sms',
                [['rcv' => $번호, 'rcvnm' => $이름]],
                $글, null,
                ['source' => 'delegation-sign']);

            if (! ($res['success'] ?? false)) {
                throw new \RuntimeException($res['message'] ?? '문자를 보내지 못했습니다.');
            }
        } catch (\Throwable $e) {
            Log::error('[위임장 서명] 발송 실패', ['id' => $delegationSign->id, 'error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => '발송하지 못했습니다 — ' . $e->getMessage()], 500);
        }

        /* 보낸 자취만 덮는다. 서명 쪽 칸은 손대지 않는다. */
        $delegationSign->forceFill([
            'token'        => $토큰,
            'sent_to'      => $번호,
            'sent_which'   => $값['which'],
            'sent_by_id'   => Auth::id(),
            'sent_by_name' => Auth::user()?->name,
            'sent_at'      => now(),
            'expires_at'   => $만료,
            'status'       => 'sent',
        ])->save();

        activity()->causedBy(Auth::user())->performedOn($delegationSign)
            ->log("위임장 서명 발송 → {$이름} {$번호} (" . DelegationSign::번호자리[$값['which']] . ')');

        return response()->json([
            'success'    => true,
            'message'    => '서명 링크를 보냈습니다.',
            'expires_at' => $만료->format('H:i'),
        ]);
    }

    // ── 서명 이미지 보기 ──────────────────────────────────
    /** 로그인ㆍ권한을 거쳐 내보낸다 — 주소를 알아도 밖에서는 열리지 않는다. */
    public function image(DelegationSign $delegationSign)
    {
        $그림 = $delegationSign->서명그림();

        abort_if($그림 === null, 404, '서명 이미지가 없습니다.');

        return response($그림, 200, [
            'Content-Type'        => 'image/png',
            'Content-Disposition' => 'inline; filename="'
                                   . rawurlencode($delegationSign->sign_filename ?: 'sign.png') . '"',
            'Cache-Control'       => 'private, no-store',
        ]);
    }
}
