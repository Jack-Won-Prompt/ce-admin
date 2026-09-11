<?php

namespace App\Http\Controllers;

use App\Models\DelegationSign;
use App\Services\MessageSender;
use App\Support\PhoneNo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 운영 데이터 › 위임장 서명 — 담당자 쪽 화면 (2026-09-11 지시).
 *
 * 기존 처방ㆍ주문ㆍ거래처와 잇지 않는다. 명단을 올리고, 번호를 골라 보내고,
 * 받은 서명을 본다 — 그것으로 닫힌다.
 */
class DelegationSignController extends Controller
{
    public function __construct(private readonly MessageSender $sender)
    {
    }

    /**
     * 목록과 내보내기가 함께 쓰는 거르개.
     *
     * 화면은 백 줄만 보지만 내보내기는 걸러진 전부를 받는다. 두 곳이 같은 조건을
     * 봐야 「화면에 보이는 것을 받는다」가 어긋나지 않는다.
     */
    private function 거른것(Request $request)
    {
        $query = DelegationSign::query()->latest('id');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('sender')) {
            $query->where('sent_by_id', $request->sender);
        }

        if ($request->filled('dealer')) {
            $query->where('dealer_name', $request->dealer);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->filled('q')) {
            $말 = $request->q;

            /* 번호는 하이픈 없이 담겨 있는데 목록에는 010-3422-7121 로 그려 준다.
               보이는 대로 긁어 붙이면 한 건도 안 나왔다 — 숫자만 남긴 것으로도 훑는다. */
            $숫자 = preg_replace('/\D/', '', $말);

            $query->where(function ($s) use ($말, $숫자) {
                $s->where('customer_name', 'like', "%{$말}%")
                    ->orWhere('dealer_name', 'like', "%{$말}%")
                    ->orWhere('phone', 'like', "%{$말}%");

                if ($숫자 !== '' && $숫자 !== $말) {
                    $s->orWhere('phone', 'like', "%{$숫자}%");
                }
            });
        }

        return $query;
    }

    // ── 목록 ──────────────────────────────────────────────
    public function index(Request $request): View
    {
        $query = $this->거른것($request);

        /* 한 쪽에 백 줄씩 (2026-09-11 지시).

           명단이 삼천 줄 가까이 된다. 한 번에 다 그리면 화면이 한참 멎고, 담당자는
           그 가운데 몇 줄만 본다 — 굴려서 찾는 것보다 걸러서 찾는 것이 빠르다. */
        $쪽 = $query->paginate(100)->withQueryString();

        $줄 = collect($쪽->items())
            ->map(fn (DelegationSign $d) => [
                'id'         => $d->id,
                'no'         => $d->src_no,
                'customer'   => $d->customer_name,
                'phone'      => \App\Support\PhoneNo::format($d->phone),
                'status'     => DelegationSign::상태[$d->status] ?? $d->status,
                'delegation' => $d->동의말('agree_delegation'),
                'privacy'    => $d->동의말('agree_privacy'),
                'marketing'  => $d->동의말('agree_marketing'),
                'signed_at'  => $d->signed_at?->format('Y-m-d H:i') ?? '',
                'sender'     => $d->sent_by_name ?? '',
                'has_sign'   => (bool) ($d->sign_path || $d->sign_base64),
                'can_send'   => (bool) $d->phone,
                /* 명단에 딸려 온 값 — 누구에게 왜 보내는지를 가리는 자리다 */
                'dealer'     => $d->dealer_name ?? '',
                'repurchase' => $d->next_repurchase_at?->format('Y-m-d') ?? '',
                'registered' => $d->last_register_at?->format('Y-m-d') ?? '',
                'rx_days'    => $d->rx_days,
                'confirmed'  => $d->last_confirm_at?->format('Y-m-d') ?? '',
                'src_status' => $d->src_status ?? '',
                'rx_type'    => $d->rx_type ?? '',
                'benefit'    => $d->benefit_class ?? '',
                'sale'       => $d->last_sale_status ?? '',
            ]);

        /* 보낸 사람 고르개 — 이 표에 적힌 이름만 세운다. users 를 훑지 않는다. */
        $보낸이 = DelegationSign::whereNotNull('sent_by_id')
            ->select('sent_by_id', 'sent_by_name')->distinct()
            ->orderBy('sent_by_name')->get();

        /* 판매처 고르개 — 명단이 세 곳뿐이라 고르면 바로 좁혀진다 */
        $판매처 = DelegationSign::whereNotNull('dealer_name')
            ->select('dealer_name')->distinct()->orderBy('dealer_name')->pluck('dealer_name');

        return view('delegation-signs.index', compact('줄', '쪽', '보낸이', '판매처'));
    }

    // ── 엑셀로 내보내기 ───────────────────────────────────
    /**
     * 걸러진 전부를 내려 준다 (2026-09-11 지시).
     *
     * 화면의 엑셀 받기는 그려 둔 백 줄만 담았다. 담당자가 받고 싶은 것은 본 쪽이
     * 아니라 걸러 낸 전체다 — 「김」으로 좁힌 581건이면 581건 모두.
     *
     * 삼천 줄을 한꺼번에 메모리에 세우지 않도록 덩어리로 흘려 쓴다.
     */
    public function export(Request $request): StreamedResponse
    {
        $query = $this->거른것($request);
        $파일 = '위임장서명_' . now()->format('Ymd_Hi') . '.csv';

        $머리글 = [
            'No', '거래처명', '전화번호', '판매처', '다음재구매가능일', '마지막 등록일',
            '처방기간', '마지막 구매확정일', '처방여부', '자격', '마지막 판매상태',
            '위임장 서명 여부', '상태', '개인정보동의 서명 여부', '마케팅 활용 동의 여부',
            '위임장 서명 일자', '위임장 서명 전송 담당자', '발송 번호', '발송 일시',
            '서명 그림 파일명', 'Status', '등록일',
        ];

        return response()->streamDownload(function () use ($query, $머리글) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");   // 엑셀이 한글을 깨뜨리지 않게
            fputcsv($out, $머리글);

            $query->chunk(500, function ($덩어리) use ($out) {
                foreach ($덩어리 as $d) {
                    fputcsv($out, [
                        $d->src_no,
                        $d->customer_name,
                        PhoneNo::format($d->phone),
                        $d->dealer_name,
                        $d->next_repurchase_at?->format('Y-m-d'),
                        $d->last_register_at?->format('Y-m-d'),
                        $d->rx_days,
                        $d->last_confirm_at?->format('Y-m-d'),
                        $d->rx_type,
                        $d->benefit_class,
                        $d->last_sale_status,
                        $d->동의말('agree_delegation'),
                        DelegationSign::상태[$d->status] ?? $d->status,
                        $d->동의말('agree_privacy'),
                        $d->동의말('agree_marketing'),
                        $d->signed_at?->format('Y-m-d H:i'),
                        $d->sent_by_name,
                        $d->sent_to ? PhoneNo::format($d->sent_to) : '',
                        $d->sent_at?->format('Y-m-d H:i'),
                        $d->sign_filename,
                        $d->src_status,
                        $d->created_at?->format('Y-m-d H:i'),
                    ]);
                }
            });

            fclose($out);
        }, $파일, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ── 줄 삭제 ───────────────────────────────────────────
    /**
     * 명단에서 한 줄을 지운다 (2026-09-11 지시).
     *
     * 잘못 올라온 줄을 화면에서 걷을 길이 없었다. 서명 그림도 함께 지운다 —
     * 줄이 사라진 뒤 폴더에만 남은 그림은 누구의 것인지 알 수 없다.
     */
    public function destroy(DelegationSign $delegationSign): JsonResponse
    {
        $이름 = $delegationSign->customer_name;

        if ($delegationSign->sign_path) {
            Storage::disk(DelegationSign::디스크)->delete($delegationSign->sign_path);
        }

        activity('delegation-sign')
            ->performedOn($delegationSign)
            ->causedBy(Auth::user())
            ->withProperties([
                '거래처' => $이름,
                '상태'   => DelegationSign::상태[$delegationSign->status] ?? $delegationSign->status,
            ])
            ->log('위임장 서명 줄 삭제');

        $delegationSign->delete();

        return response()->json(['ok' => true, '말' => $이름 . ' 줄을 지웠습니다.']);
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

        /* 엑셀이 앞에 붙이는 BOM 을 떼고, 한글 윈도우 꼴이면 UTF-8 로 옮긴다.

           mbstring 이 아는 이름이 자리마다 다르다 — 여기 PHP 에는 CP949 가 있는데
           서버에는 UHC 와 EUC-KR 뿐이다. 없는 이름을 대면 조용히 빈손이 돌아와,
           머리글을 못 알아보고 줄 번호를 이름으로 읽었다 (2026-09-11).
           있는 것부터 차례로 짚고, 하나도 없으면 그렇게 말한다. */
        $글 = preg_replace('~^\xEF\xBB\xBF~', '', $글);
        if (! mb_check_encoding($글, 'UTF-8')) {
            $쓸것 = collect(['UHC', 'CP949', 'EUC-KR'])
                ->first(fn ($e) => in_array($e, mb_list_encodings(), true));

            if (! $쓸것) {
                return response()->json([
                    'success' => false,
                    'message' => 'UTF-8 이 아닌 파일인데 한글 인코딩을 다룰 수 없습니다 — 엑셀에서 「CSV UTF-8」로 저장해 주십시오.',
                ], 422);
            }

            $글 = mb_convert_encoding($글, 'UTF-8', $쓸것);
        }

        /* 줄바꿈만 턴다. trim() 을 그냥 쓰면 첫 줄 맨 앞의 탭까지 먹어, 빈 머리글
           칸 하나가 사라지면서 칸이 통째로 한 칸씩 밀린다 — 이름 자리에 줄 번호가
           들어가 2,896줄이 모두 버려졌다 (2026-09-11). */
        $줄들 = preg_split('/\r\n|\r|\n/', trim($글, "\r\n"));
        if (! $줄들) {
            return response()->json(['success' => false, 'message' => '내용이 비어 있습니다.'], 422);
        }

        /* 쉼표로 나뉜 것과 탭으로 나뉜 것을 둘 다 받는다 (2026-09-11).

           받은 명단(위임 필요 리스트)은 이름이 .csv 인데 속은 탭으로 나뉘어 있었다.
           엑셀에서 「텍스트(탭 분리)」로 저장하면 그렇게 된다 — 파일 이름만 보고
           쉼표로 끊으면 한 줄이 통째로 첫 칸에 들어간다. 첫 줄에 무엇이 더 많은지
           세어 정한다. */
        $나눔 = substr_count($줄들[0], "\t") > substr_count($줄들[0], ',') ? "\t" : ',';

        /* 머리글이 있으면 칸 차례를 거기서 읽는다. 없으면 이름ㆍ전화번호 차례로 본다. */
        $자리 = null;
        if (preg_match('/거래처|이름|customer/i', $줄들[0])) {
            $머리 = array_map(fn ($x) => trim($x, " \t\"'"), str_getcsv(array_shift($줄들), $나눔));
            $자리 = self::칸찾기($머리);
        } elseif (count(str_getcsv($줄들[0], $나눔)) > 3) {
            /* 칸이 넷 이상인데 머리글을 못 알아봤다. 첫 칸을 이름으로 짐작하면
               줄 번호를 이름으로 읽어 온 줄이 버려진다 — 짐작하지 않고 말한다. */
            return response()->json([
                'success' => false,
                'message' => '머리글을 알아보지 못했습니다 — 첫 줄에 「환자거래처 명」ㆍ「전화번호」가 적혀 있어야 합니다.',
            ], 422);
        }

        $세움 = 0;
        $건너뜀 = 0;
        $잘못 = [];

        foreach ($줄들 as $번 => $줄) {
            $칸 = str_getcsv($줄, $나눔);
            $값 = fn (?int $i) => $i === null ? '' : trim((string) ($칸[$i] ?? ''));

            if ($자리) {
                $이름 = $값($자리['이름']);
                $번호 = self::번호만($값($자리['번호']));
            } else {
                $이름 = $값(0);
                $번호 = self::번호만($값(1));
            }

            if ($이름 === '') {
                continue;                                   // 빈 줄은 조용히 지나간다
            }

            if (! $번호) {
                $잘못[] = ($번 + 1) . '째 줄 — ' . $이름 . ' : 전화번호가 없습니다';
                continue;
            }

            /* 명단에 딸려 온 값 — 다시 올리면 이것만 새로 적는다 */
            $명단값 = $자리 ? [
                'src_no'             => ($n = $값($자리['줄번호'])) !== '' ? (int) $n : null,
                'dealer_name'        => mb_substr($값($자리['판매처']), 0, 100) ?: null,
                'next_repurchase_at' => self::날짜($값($자리['다음재구매'])),
                'last_register_at'   => self::날짜($값($자리['마지막등록'])),
                'rx_days'            => ($d = $값($자리['처방기간'])) !== '' ? (int) $d : null,
                'last_confirm_at'    => self::날짜($값($자리['마지막확정'])),
                'src_status'         => mb_substr($값($자리['상태']), 0, 30) ?: null,
                'rx_type'            => mb_substr($값($자리['처방여부']), 0, 30) ?: null,
                'benefit_class'      => mb_substr($값($자리['자격']), 0, 30) ?: null,
                'last_sale_status'   => mb_substr($값($자리['판매상태']), 0, 30) ?: null,
            ] : [];

            /* 같은 이름ㆍ같은 번호가 이미 있으면 새로 세우지 않고 **명단 칸만 새로 적는다**
               (2026-09-11). 명단은 주마다 새로 뽑혀 온다 — 다음 재구매일이나 판매상태가
               바뀐 것을 받아 적어야 하는데, 여태 그냥 지나가 옛 값이 남았다.

               발송ㆍ서명 칸은 건드리지 않는다. 받아 둔 서명은 명단을 다시 올린다고
               사라져서는 안 된다. */
            $이미 = DelegationSign::where('customer_name', $이름)->where('phone', $번호)->first();

            if ($이미) {
                if ($명단값) {
                    /* 서명을 받아 둔 사람의 판매처는 이미 콜로플라스트 코리아로 넘어갔다
                       (2026-09-11 지시). 명단은 아직 대리점 이름을 들고 오므로, 다시
                       올릴 때 그 한 칸만 덮지 않는다 — 넘어간 것을 되돌리는 셈이 된다. */
                    $적을것 = $명단값;
                    if ($이미->status === 'signed') {
                        unset($적을것['dealer_name']);
                    }

                    $이미->forceFill($적을것)->save();
                }
                $건너뜀++;
                continue;
            }

            DelegationSign::create([
                'customer_name' => mb_substr($이름, 0, 100),
                'phone'         => $번호,
                'status'        => 'pending',
            ] + $명단값);
            $세움++;
        }

        activity()->causedBy(Auth::user())
            ->log("위임장 서명 명단 올림 — 새로 {$세움}건 · 이미 있어 새로 적음 {$건너뜀}건");

        return response()->json([
            'success' => true,
            'message' => "새로 {$세움}건을 세웠습니다."
                       . ($건너뜀 ? " 이미 있던 {$건너뜀}건은 명단 값을 새로 적었습니다." : '')
                       . ($잘못 ? ' 넣지 못한 줄 ' . count($잘못) . '건.' : ''),
            'errors'  => array_slice($잘못, 0, 20),
        ]);
    }

    /**
     * 머리글을 보고 어느 칸이 무엇인지 정한다.
     *
     * 명단의 칸 차례는 뽑아 주는 사람에 따라 달라진다. 자리를 고정으로 박아 두면
     * 다음 명단에서 이름 자리에 전화번호가 들어간다 — 머리글을 읽어 맞춘다.
     */
    private static function 칸찾기(array $머리): array
    {
        $찾 = function (array $말들, bool $뒤에서 = false) use ($머리): ?int {
            $것 = null;
            foreach ($머리 as $i => $h) {
                foreach ($말들 as $말) {
                    if ($h !== '' && mb_strpos($h, $말) !== false) {
                        if (! $뒤에서) {
                            return $i;
                        }
                        $것 = $i;
                    }
                }
            }

            return $것;
        };

        return [
            /* 서명하는 사람은 「환자거래처」다. 그냥 「거래처명」은 판매처(대리점)이므로
               환자 쪽을 먼저 찾고, 없을 때만 거래처명을 쓴다. */
            '이름'       => $찾(['환자거래처', '환자명', '고객명']) ?? $찾(['거래처명', '이름']) ?? 0,
            '판매처'     => $찾(['환자거래처']) !== null ? $찾(['거래처명']) : null,
            '번호'       => $찾(['전화번호', '휴대폰', '연락처']),
            /* 줄 번호 칸은 머리글이 비어 있기 일쑤다 — 엑셀이 왼쪽 끝에 붙이는 것이라
               이름이 없다. 「No」를 못 찾고 첫 칸이 빈 머리글이면 그 자리로 본다. */
            '줄번호'     => $찾(['No', 'no', '순번']) ?? (($머리[0] ?? null) === '' ? 0 : null),
            '다음재구매' => $찾(['다음재구매']),
            '마지막등록' => $찾(['마지막 등록', '마지막등록']),
            '처방기간'   => $찾(['처방기간']),
            '마지막확정' => $찾(['구매확정']),
            '상태'       => $찾(['Status']),
            '처방여부'   => $찾(['처방여부']),
            '자격'       => $찾(['자격']),
            '판매상태'   => $찾(['판매상태']),
        ];
    }

    /**
     * 숫자만 남긴다 — 화면에서 다시 꼴을 갖춘다.
     *
     * 엑셀이 번호를 수로 읽어 앞의 0 을 떨어뜨린 것을 되살린다 (2026-09-11).
     * 받은 명단 2,896건 가운데 165건이 「1086047612」처럼 열 자리로 적혀 있었다 —
     * 그대로 두면 닿지 않는 번호로 나간다.
     */
    private static function 번호만(string $값): string
    {
        $숫 = preg_replace('/\D/', '', $값);

        if (strlen($숫) === 10 && str_starts_with($숫, '1')) {
            $숫 = '0' . $숫;
        }

        return (strlen($숫) >= 9 && strlen($숫) <= 11) ? $숫 : '';
    }

    /**
     * YYYY-MM-DD 만 받는다 — 알아볼 수 없는 것은 비워 둔다.
     *
     * 엑셀은 빈 날짜를 「1900-01-00」으로 적는다. 꼴만 보면 맞아 보여 그대로
     * 담았더니 MySQL 이 1899-12-31 로 바꿔 놓았다 — 비어 있다는 뜻이 「아주 오래
     * 전」으로 바뀌어, 다음 재구매가 지난 건으로 읽혔다 (2026-09-11 · 205건).
     * 달과 날이 실제로 있는 날인지 센다.
     */
    private static function 날짜(string $값): ?string
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $값, $m)) {
            return null;
        }

        [, $해, $달, $날] = $m;

        return checkdate((int) $달, (int) $날, (int) $해) && (int) $해 >= 2000 ? $값 : null;
    }

    // ── 발송 팝오버가 묻는 것 ─────────────────────────────
    public function show(DelegationSign $delegationSign): JsonResponse
    {
        return response()->json([
            'success'  => true,
            'id'       => $delegationSign->id,
            'customer' => $delegationSign->customer_name,
            'phone'    => \App\Support\PhoneNo::format($delegationSign->phone),
            'raw'      => $delegationSign->phone,
            'dealer'   => $delegationSign->dealer_name,
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
            'name' => 'nullable|string|max:100',
        ]);

        $번호 = preg_replace('/\D/', '', (string) $delegationSign->phone);
        if (strlen($번호) < 9 || strlen($번호) > 11) {
            return response()->json([
                'success' => false,
                'message' => '전화번호가 비었거나 꼴이 맞지 않습니다.',
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
            'sent_by_id'   => Auth::id(),
            'sent_by_name' => Auth::user()?->name,
            'sent_at'      => now(),
            'expires_at'   => $만료,
            'status'       => 'sent',
        ])->save();

        activity()->causedBy(Auth::user())->performedOn($delegationSign)
            ->log("위임장 서명 발송 → {$이름} {$번호}");

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
