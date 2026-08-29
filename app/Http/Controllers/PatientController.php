<?php
// app/Http/Controllers/PatientController.php

namespace App\Http\Controllers;

use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PatientController extends Controller
{
    // ── 목록 ──────────────────────────────────────────────
    /**
     * 환자마다 '가장 최근 동의 건' 하나.
     *
     * 동의는 처방전에 매달려 있고 환자에게는 처방전이 여럿일 수 있다. 서류 발행과 같은
     * 기준(최신 건)으로 맞춘다.
     *
     * 표가 아직 없을 수 있다(마이그레이션 전 배포) — 그때는 빈 것을 준다.
     */
    private function latestConsentByPatient(): \Illuminate\Support\Collection
    {
        if (!\Illuminate\Support\Facades\Schema::hasColumn('prescription_consents', 'is_minor')) {
            return collect();
        }

        return \App\Models\PrescriptionConsent::query()
            ->with('prescription:id,rx_number,patient_id')
            ->whereHas('prescription', fn ($q) => $q->whereNotNull('patient_id'))
            ->orderByDesc('id')
            ->get()
            // 내림차순이라 환자별 첫 건이 곧 최신이다
            ->unique(fn ($c) => $c->prescription?->patient_id)
            ->keyBy(fn ($c) => $c->prescription?->patient_id);
    }

    public function index(Request $request): View|\Illuminate\Http\JsonResponse
    {
        $query = Patient::withCount('prescriptions')
                        ->withMax('prescriptions', 'repurchase_date')
                        ->latest();

        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('mobile', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            });
        }

        /* ── 찾는 조건들 ────────────────────────────────────
           날짜 두 칸은 한쪽만 채워도 걸린다 — 「언제부터」만, 「언제까지」만 찾는 일이 잦다. */

        // 생성일자
        if ($request->filled('created_from')) $query->whereDate('created_at', '>=', $request->created_from);
        if ($request->filled('created_to'))   $query->whereDate('created_at', '<=', $request->created_to);

        // 생년 — 네 자리 연도. 생년월일이 비어 있는 환자는 걸리지 않는다.
        if ($request->filled('birth_year')) {
            $query->whereYear('birth_date', (int) $request->birth_year);
        }

        // 건보 위임 종료일
        if ($request->filled('agree_end_from')) $query->whereDate('nhis_agree_end', '>=', $request->agree_end_from);
        if ($request->filled('agree_end_to'))   $query->whereDate('nhis_agree_end', '<=', $request->agree_end_to);

        // 상병타입 — 환자에 붙는 구분(SB/SCI)이다
        if ($request->filled('sb_sci')) {
            $query->where('sb_sci', $request->sb_sci);
        }

        // 사업부 — IC(카테터) · OC(장루). 두 사업부는 다루는 물건도 서류도 다르다
        if ($request->filled('care_type') && Patient::hasCareTypeColumn()) {
            $query->where('care_type', $request->care_type);
        }

        /* 개인정보 동의 여부 — privacy_consents 를 환자로 이어 본다.
           이 표는 밖에서 들어오는 폼이라 patient_id 가 비어 있을 수 있다(이름+전화로 채운다).
           그래서 「아니오」는 「이어진 동의서가 없다」는 뜻이지 「동의하지 않았다」가 아니다. */
        if ($request->filled('privacy_consent')) {
            $has = $request->privacy_consent === 'y';
            $query->{$has ? 'whereHas' : 'whereDoesntHave'}('privacyConsents');
        }

        // 공단 위임장 동의 여부 — 처방전에 딸린 동의가 하나라도 agreed 인가
        if ($request->filled('nhis_consent')) {
            $has = $request->nhis_consent === 'y';
            $fn  = fn ($q) => $q->whereHas('consents', fn ($c) => $c->where('status', 'agreed'));
            $query->{$has ? 'whereHas' : 'whereDoesntHave'}('prescriptions', $fn);
        }

        // 재구매일 기간 필터
        if ($request->filled('repurchase_within')) {
            $days = (int) $request->repurchase_within;
            $query->whereHas('prescriptions', function ($sub) use ($days) {
                $sub->whereNotNull('repurchase_date')
                    ->whereBetween('repurchase_date', [today(), today()->addDays($days)]);
            });
        }

        $consents = $this->latestConsentByPatient();

        /* 주소가 언제 바뀌었는지도 함께 보여 준다 — 「이 주소가 언제부터인가」를
           모르면 지난 주문이 어디로 갔는지 되짚을 수 없다. */
        $query->with(['creator:id,name', 'updater:id,name', 'addresses']);

        // ── wwGrid 데이터 ──────────────────────────────────
        $gridData = $query->get()->map(function ($p) use ($consents) {
            // 생년월일 + 나이
            $birth = $p->birth_date
                ? $p->birth_date->format('Y-m-d') . ' (만 ' . $p->age . '세)'
                : '-';

            // 성별 배지 → 텍스트
            $gender = match ($p->gender) {
                'male'   => '남',
                'female' => '여',
                default  => '-',
            };

            // 재구매일 + D-day
            $rd = $p->prescriptions_max_repurchase_date;
            if ($rd) {
                $rdDate = \Carbon\Carbon::parse($rd);
                $diff   = (int) today()->diffInDays($rdDate, false);
                $repurchase = $rdDate->format('Y-m-d') . ($diff >= 0 ? ' (D-' . $diff . ')' : ' (D+' . abs($diff) . ')');
            } else {
                $repurchase = '-';
            }

            // 위임 서명 — 이 환자의 가장 최근 동의 건
            $c       = $consents[$p->id] ?? null;
            $agreed  = $c && $c->status === 'agreed';
            $minorRx = $c && $c->is_minor;

            $addr = $p->addresses->first();

            return [
                'id'              => $p->id,
                'care_type'       => $p->care_type ?: '',
                'name'            => $p->name,
                'resident_no'     => $p->masked_resident_no ?? '-',
                // 생년월일은 세 가지 표기로 나란히 둔다 — 위드웍스 표와 눈으로 맞춰 본다
                'birth_dotted'    => $p->birth_dotted,
                'birth_iso'       => $p->birth_iso,
                'birth_year'      => $p->birth_year,
                'age'             => $p->age !== null ? '만 ' . $p->age . '세' : '',
                // 옛 칸 — 다른 화면이 아직 이 이름으로 읽는다
                'birth_date'      => $birth,
                'gender'          => $gender,
                'mobile'          => $p->mobile ?? '-',
                'phone2'          => $p->phone ?? '',

                // ── 연락 ──
                'contact_status'  => $p->contactStatusLabel(),
                'contact_channel' => $p->contactChannelLabel(),
                'email'           => $p->email ?? '',
                'fax'             => $p->fax ?? '',
                'address'         => $p->full_address,
                'address_at'      => $addr?->created_at?->format('Y-m-d') ?? '',

                // ── 돈 ──
                'remitter'        => $p->remitter_name ?? '',
                'deduction'       => $p->deduction ?? '',
                'cash_receipt_no' => $p->cash_receipt_no ?? '',

                // ── 공단ㆍ기초 ──
                'nhis_reg'        => $p->nhis_reg_status ?? '',
                'nhis_reg_date'   => $p->nhis_reg_date ? \Carbon\Carbon::parse($p->nhis_reg_date)->format('Y-m-d') : '',
                'nhis_renew'      => $p->nhis_renew ?? '',
                'nhis_renew_due'  => $p->nhis_renew_due ? \Carbon\Carbon::parse($p->nhis_renew_due)->format('Y-m-d') : '',
                'agree_start'     => $p->nhis_agree_start ? \Carbon\Carbon::parse($p->nhis_agree_start)->format('Y-m-d') : '',
                'agree_end'       => $p->nhis_agree_end ? \Carbon\Carbon::parse($p->nhis_agree_end)->format('Y-m-d') : '',
                'basic_reeval'    => $p->basic_reeval ?? '',
                'basic_due'       => $p->basic_reeval_due ? \Carbon\Carbon::parse($p->basic_reeval_due)->format('Y-m-d') : '',

                'sb_sci'          => $p->sb_sci ?? '',
                'memo'            => $p->note ?: ($p->memo ?? ''),

                // ── 남긴 사람 ──
                'creator'         => $p->creator?->name ?? '',
                'updater'         => $p->updater?->name ?? '',
                'updated'         => $p->updated_at?->format('Y-m-d H:i') ?? '',

                // ── 위임 서명 ──
                'signed'      => $c ? $c->statusLabel() : '',
                'minor'       => $minorRx ? '미성년' : ($c ? '성년' : ''),
                'g_relation'  => $minorRx ? ($c->guardian_relation ?? '') : '',
                'g_name'      => $minorRx ? ($c->guardian_name ?? '') : '',
                'g_birth'     => $minorRx ? ($c->guardian_birth_date?->format('Y-m-d') ?? '') : '',
                'g_id'        => $minorRx && $c->guardian_id_path ? '있음' : '',
                // 이미지는 실을 수 없다(한 장에 수십 KB). 볼 때만 권한을 거쳐 부르는 주소를 준다.
                'sign_url'    => $agreed && $c->signature_data && $c->prescription
                                   ? route('prescriptions.consentSignature', $c->prescription) : null,
                
                'g_id_url'    => $minorRx && $c->guardian_id_path
                                   ? route('files.consent-guardian-id', $c) : null,

                'rx_count'        => (int) $p->prescriptions_count,
                'repurchase_date' => $repurchase,
                'created'         => $p->created_at?->format('Y-m-d') ?? '',
            ];
        });

        $total = $gridData->count();

        return view('patients.index', compact('gridData', 'total'));
    }

    // ── 상세/편집 화면 ────────────────────────────────────
    public function show(Patient $patient): View
    {
        // 주문 줄까지 미리 읽는다 — 표에서 한 건을 열면 그 주문의 제품 목록을 바로 보여준다
        $patient->load(['prescriptions' => fn ($q) => $q->with('order.items')->latest()->take(50)]);

        /* 표에 실을 값은 여기서 만든다 — 화면에서 관계를 타고 다니면 한 줄마다 질의가 나간다.
           상담만 적고 아직 처방·주문이 없는 건도 이 목록에 있다(주문번호가 빈 줄). */
        $rxRows = $patient->prescriptions->map(fn ($rx) => [
            'id'        => $rx->id,
            'rx_number' => $rx->rx_number,
            'order_no'  => $rx->order?->order_number ?: '',
            'hospital'  => $rx->hospital_name ?: '-',
            'amount'    => (int) ($rx->order?->total_amount ?? 0),
            'date'      => $rx->created_at?->format('Y-m-d') ?? '',
            'status'    => $rx->status_label,
            'url'       => route('prescriptions.show', $rx),
            /* 무엇을 샀는지는 한 칸에 다 들어가지 않는다 — 제품명 칸을 빼고, 대신 그
               주문의 제품 줄을 통째로 실어 둔다. 한 건을 열면 옆 탭에서 펼친다. */
            'items'     => $this->orderItemRows($rx->order),
            'ship'      => (int) ($rx->order?->shipping_fee ?? 0),
            'total_amt' => (int) ($rx->order?->total_amount ?? 0),
        ])->values();

        /* 일일 도뇨 횟수ㆍFive/SixㆍFive/Six(110days)ㆍ다음 재구매 가능일은 처방에 붙는
           값이라 환자에는 칸이 없다. 가장 최근에 적힌 것을 끌어와 보여 준다 —
           고치는 자리는 주문 등록의 병원ㆍ처방 정보다(요청서 4쪽 «역으로 연결»). */
        $rxFacts = $this->rxFacts($patient);

        return view('patients.show', compact('patient', 'rxRows', 'rxFacts'));
    }

    /**
     * 처방에서 끌어오는 환자 요약값.
     *
     * 처방마다 적히는 값이라 비어 있는 건이 섞인다 — 최근 것부터 훑어 처음 만나는
     * 값을 쓴다. 「최근 처방에 안 적혀 있으니 없다」로 보이면 안 된다.
     */
    private function rxFacts(Patient $patient): array
    {
        $rows = $patient->prescriptions;

        $first = fn (string $col) => $rows->pluck($col)->first(fn ($v) => $v !== null && $v !== '');

        return [
            'daily'   => \App\Support\CatheterFrequency::label($first('diverticulums')),
            'five'    => match ((string) $first('five_program')) {
                '05' => 'Five', '06' => 'Six', '00' => 'N/A', default => '',
            },
            'five110' => (string) ($first('five_110days') ?? ''),
            'next'    => ($n = $first('next_repurchase'))
                ? \Carbon\Carbon::parse($n)->format('Y-m-d')
                : (($r = $rows->pluck('repurchase_date')->first(fn ($v) => $v))
                    ? \Carbon\Carbon::parse($r)->format('Y-m-d') : ''),
        ];
    }

    /**
     * 주문 한 건의 제품 줄.
     *
     * 제품을 여러 줄로 담는 order_items 는 나중에 들어온 그릇이라, 지금 있는 주문은
     * 대부분(26건 중 25건) 제품 하나를 주문 자체에 적어 두고 있다. 줄이 없다고
     * 「제품 없음」이라고 하면 실제로는 산 물건이 있는데도 빈 표가 뜬다 —
     * 줄이 없으면 주문에 적힌 그 하나를 한 줄로 만들어 보여 준다.
     */
    private function orderItemRows(?\App\Models\Order $order): \Illuminate\Support\Collection
    {
        if (!$order) {
            return collect();
        }

        if ($order->items->isNotEmpty()) {
            return $order->items->map(fn ($it) => [
                'name'       => $it->product_name ?: '-',
                'code'       => $it->product_code ?: '',
                'qty'        => (int) $it->quantity,
                'unit_price' => (int) $it->unit_price,
                'nhis'       => (int) $it->nhis_amount,
                'copay'      => (int) $it->patient_copay,
                'total'      => (int) round($it->unit_price * max(1, (int) $it->quantity)),
            ])->values();
        }

        if (!$order->product_name && !$order->quantity) {
            return collect();
        }

        $qty  = max(1, (int) $order->quantity);
        $unit = (int) round($order->unit_price ?? 0);

        return collect([[
            'name'       => $order->product_name ?: '-',
            'code'       => $order->product_code ?: '',
            'qty'        => $qty,
            'unit_price' => $unit,
            'nhis'       => (int) round($order->nhis_amount ?? 0),
            'copay'      => (int) round($order->patient_copay ?? 0),
            // 제품값 합계다 — 배송비가 붙은 주문 총액과는 다르다
            'total'      => $unit * $qty,
        ]]);
    }

    /** 환자 이력(처방전·상담·구매) — 목록 화면 우측 상세 탭용 JSON */
    public function histories(Patient $patient): \Illuminate\Http\JsonResponse
    {
        $rx = $patient->prescriptions()->with(['creator', 'updater', 'counselOrder'])->latest()->take(50)->get();

        $prescriptions = $rx->map(fn ($p) => [
            'rx_number' => $p->rx_number,
            'hospital'  => $p->hospital_name ?? '-',
            'date'      => $p->created_at->format('Y-m-d'),
            'status'    => $p->status_label,
            'url'       => route('prescriptions.show', $p),
        ])->values();

        /* 상담 한 줄에 담는 것 — 무엇을 했나 · 언제 · 어디까지 왔나 · 무슨 갈래 · 누가.
           상담 유형·상태는 코드로 저장돼 있어(1013 · 02 …) 그대로 두면 읽을 수 없다. */
        $counselTypes = ['1013' => '구매', '1016' => '개인구매', '1020' => '반품',
                         '1030' => '문의', '1050' => '기타'];
        $counselStates = ['02' => '등록', '50' => '재상담', '95' => '확정', '99' => '취소'];

        $counseling = $rx->filter(fn ($p) => !empty($p->counsel_no))->map(fn ($p) => [
            'id'         => $p->id,
            // 상담을 다시 찾을 때 쓰는 열쇠 — 처방전은 처방번호로 찾는다(getRouteKeyName)
            'key'        => $p->rx_number,
            'counsel_no' => $p->counsel_no ?: '-',
            /* 상담이 어느 주문 이야기였나. 주문은 여러 번 있고 처방 없이 사는 때도 있어,
               상담을 적을 때 주문이력에서 골라 잇는다. 잇지 않은 상담도 있다(주문 전 문의). */
            'order_id'   => $p->counsel_order_id,
            'order_no'   => $p->counselOrder?->order_number ?: '',
            'date'       => $p->counsel_date ?: $p->created_at->format('Y-m-d'),
            'note'       => $p->counsel_contents ?: ($p->review_memo ?? ''),
            'type'       => $counselTypes[(string) $p->counsel_type] ?? ($p->counsel_type ?: ''),
            'status'     => $counselStates[(string) $p->counsel_status] ?? ($p->counsel_status ?: ''),
            'call_no'    => $p->counsel_call_no ?: '',
            're_date'    => $p->counsel_re_date ?: '',
            // 상담을 마지막으로 만진 사람. 고친 적이 없으면 등록한 사람이다.
            'by'         => $p->updater?->name ?: ($p->creator?->name ?: ''),
            'url'        => route('prescriptions.show', $p),
        ])->values();

        $purchases = $patient->orders()->latest()->take(50)->get()->map(fn ($o) => [
            'order_number' => $o->order_number,
            'product'      => $o->product_name ?? '-',
            'qty'          => (int) ($o->quantity ?? 1),
            'amount'       => (int) $o->total_amount,
            'status'       => \App\Models\Order::STATUS_LABELS[$o->status]['label'] ?? $o->status,
            'date'         => $o->created_at->format('Y-m-d'),
            'url'          => route('orders.show', $o),
        ])->values();

        return response()->json([
            'name'          => $patient->name,
            'prescriptions' => $prescriptions,
            'counseling'    => $counseling,
            'purchases'     => $purchases,
        ]);
    }

    /**
     * 상담 한 건을 적어 둔다.
     *
     * 상담은 처방전 레코드에 붙어 산다(counsel_* 칸). 통화만 하고 끝나는 상담도 있어
     * 처방 내용은 비운 채로 세운다 — 나중에 그 상담이 주문으로 이어지면 같은 건에
     * 처방을 채워 넣으면 된다. 새 상담마다 한 건이므로 이력이 겹쳐 덮이지 않는다.
     */
    public function storeCounsel(Request $request, Patient $patient): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'counsel_date'     => 'required|date',
            'counsel_type'     => 'nullable|string|max:10',
            'counsel_status'   => 'nullable|string|max:10',
            'counsel_call_no'  => 'nullable|string|max:30',
            'counsel_re_date'  => 'nullable|date',
            'counsel_contents' => 'required|string|max:2000',
            'counsel_order_id' => 'nullable|exists:orders,id',
        ]);

        $rx = \App\Models\Prescription::create(array_merge($data, [
            'rx_number'        => \App\Models\Prescription::generateRxNumber(),
            'counsel_no'       => \App\Models\Prescription::generateCounselNo(),
            'patient_id'       => $patient->id,
            'patient_name_ocr' => $patient->name,
            'mobile_ocr'       => $patient->mobile ?: $patient->phone,
            'status'           => 'pending',
            // upload_source 는 mobile·web 둘뿐이다. 상담도 웹 화면에서 적은 것이다.
            'upload_source'    => 'web',
            'created_by'       => \Illuminate\Support\Facades\Auth::id(),
            'updated_by'       => \Illuminate\Support\Facades\Auth::id(),
        ]));

        activity()->causedBy(\Illuminate\Support\Facades\Auth::user())->performedOn($rx)
            ->log("{$patient->name} 상담 기록 ({$rx->counsel_no})");

        return response()->json([
            'success'    => true,
            'message'    => '상담을 적어 두었습니다.',
            'counsel_no' => $rx->counsel_no,
        ]);
    }

    /**
     * 이 사람의 지난 상담.
     *
     * 「상담하기」를 누르면 새 상담부터 열어 주던 것을 그만둔다 — 지난 통화를 이어 갈
     * 길이 없어, 같은 이야기가 상담 여러 건으로 갈라져 쌓였다. 먼저 보여 주고 고르게
     * 한다. 이을 것이 없으면 그때 새로 연다(화면이 알아서 건너뛴다).
     */
    public function counsels(Patient $patient): \Illuminate\Http\JsonResponse
    {
        $types  = ['1013' => '구매', '1016' => '개인구매', '1020' => '반품', '1030' => '문의', '1050' => '기타'];
        $states = ['02' => '등록', '50' => '재상담', '95' => '확정', '99' => '취소'];

        $rows = $patient->prescriptions()
            ->whereNotNull('counsel_no')
            // 누가 받은 통화였는지 — 이어 걸 때 그 사람에게 먼저 물어보게 된다
            ->with(['counselOrder', 'creator', 'updater'])
            ->latest('id')->take(100)->get()
            ->map(fn ($p) => [
                'id'           => $p->id,
                'counsel_no'   => (string) $p->counsel_no,
                'rx_number'    => (string) $p->rx_number,
                'date'         => (string) ($p->counsel_date ?: $p->created_at?->format('Y-m-d')),
                'type'         => (string) ($p->counsel_type ?? ''),
                'type_label'   => $types[(string) $p->counsel_type] ?? '',
                'status'       => (string) ($p->counsel_status ?? ''),
                'status_label' => $states[(string) $p->counsel_status] ?? '',
                'call_no'      => (string) ($p->counsel_call_no ?? ''),
                're_date'      => (string) ($p->counsel_re_date ?? ''),
                'contents'     => (string) ($p->counsel_contents ?? ''),
                'order_id'     => $p->counsel_order_id,
                'order_no'     => (string) ($p->counselOrder?->order_number ?? ''),
                /* 상담원 — 처음 받은 사람이다. 이어 적은 사람이 다르면 그 이름을 함께
                   적는다(「김선미 → 강정석」). 이어 걸 때 누구에게 물어볼지가 갈린다. */
                'by'           => trim(($p->creator?->name ?? '')
                                    . ($p->updater && $p->updater->id !== $p->creator?->id
                                        ? ' → ' . $p->updater->name : '')),
            ])->values();

        return response()->json(['rows' => $rows]);
    }

    /**
     * 지난 상담을 이어 적는다.
     *
     * 상담 한 건은 처방전 한 줄에 붙어 산다(counsel_* 칸). 다시 걸어 온 통화까지 새 건으로
     * 세우면 같은 이야기가 둘로 갈라진다 — 재상담으로 두었던 그 건을 고쳐 잇는다.
     *
     * 주소가 가리키는 것은 처방번호다(Prescription::getRouteKeyName 이 rx_number 다).
     */
    public function updateCounsel(
        Request $request,
        Patient $patient,
        \App\Models\Prescription $prescription
    ): \Illuminate\Http\JsonResponse {
        // 남의 상담을 고치지 못하게 — 주소를 손으로 고쳐도 이 문턱은 지난다
        if ((int) $prescription->patient_id !== (int) $patient->id || !$prescription->counsel_no) {
            abort(404, '이 사람의 상담이 아닙니다.');
        }

        $data = $request->validate([
            'counsel_date'     => 'required|date',
            'counsel_type'     => 'nullable|string|max:10',
            'counsel_status'   => 'nullable|string|max:10',
            'counsel_call_no'  => 'nullable|string|max:30',
            'counsel_re_date'  => 'nullable|date',
            'counsel_contents' => 'required|string|max:2000',
            'counsel_order_id' => 'nullable|exists:orders,id',
        ]);

        $prescription->forceFill(array_merge($data, [
            'updated_by' => \Illuminate\Support\Facades\Auth::id(),
        ]))->save();

        activity()->causedBy(\Illuminate\Support\Facades\Auth::user())->performedOn($prescription)
            ->log("{$patient->name} 상담 이어 적음 ({$prescription->counsel_no})");

        return response()->json([
            'success'    => true,
            'message'    => '상담을 이어 적었습니다.',
            'counsel_no' => $prescription->counsel_no,
        ]);
    }

    /** 이 환자의 주문 — 상담을 어느 건에 이을지 고를 때 본다 */
    public function orders(Patient $patient): \Illuminate\Http\JsonResponse
    {
        $rows = $patient->orders()->with('prescription')->latest('id')->take(100)->get()
            ->map(fn ($o) => [
                'id'        => $o->id,
                'order_no'  => $o->order_number,
                'date'      => $o->created_at?->format('Y-m-d') ?? '',
                'product'   => $o->product_name ?: '-',
                'amount'    => (int) $o->total_amount,
                'status'    => \App\Models\Order::STATUS_LABELS[$o->status]['label'] ?? $o->status,
                // 처방으로 산 것인지 처방 없이 산 것인지 — 고를 때 그것부터 눈에 들어와야 한다
                'rx_number' => $o->prescription?->rx_number ?: '',
                'kind'      => $o->prescription_id ? '처방' : '처방 없음',
            ])->values();

        return response()->json(['rows' => $rows]);
    }

    /** 이어 둔 주문을 고친다 — 잘못 이은 것을 그 자리에서 바로잡는다 */
    public function updateCounselOrder(Request $request, \App\Models\Prescription $prescription): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate(['counsel_order_id' => 'nullable|exists:orders,id']);

        $prescription->forceFill([
            'counsel_order_id' => $data['counsel_order_id'] ?? null,
            'updated_by'       => \Illuminate\Support\Facades\Auth::id(),
        ])->save();

        $no = $prescription->refresh()->counselOrder?->order_number;

        return response()->json([
            'success'  => true,
            'message'  => $no ? "{$no} 주문에 이었습니다." : '주문 연결을 풀었습니다.',
            'order_no' => $no ?: '',
        ]);
    }

    // ── 등록 ──────────────────────────────────────────────
    /**
     * 등록ㆍ수정이 함께 쓰는 규칙.
     *
     * 두 벌로 두었더니 칸을 늘릴 때마다 한쪽만 늘어, 등록으로는 담기는데 수정으로는
     * 사라지는 값이 생겼다.
     */
    private function patientRules(): array
    {
        return [
            'name'               => 'required|string|max:50',
            'care_type'          => 'nullable|in:IC,OC',
            'resident_no'        => 'nullable|string|max:20',
            'birth_date'         => 'nullable|date',
            'gender'             => 'nullable|in:male,female',
            'mobile'             => 'nullable|string|max:30',
            'phone'              => 'nullable|string|max:30',
            'address'            => 'nullable|string|max:300',
            'postcode'           => 'nullable|string|max:10',
            'address_detail'     => 'nullable|string|max:200',
            'health_insurance_no'=> 'nullable|string|max:20',
            'is_nhis_eligible'   => 'boolean',
            'nhis_coverage_rate' => 'nullable|integer|min:0|max:100',
            'note'               => 'nullable|string|max:1000',

            // ── 화면 확정요청 2026-08-27 (2ㆍ3쪽) ──
            'email'           => 'nullable|email|max:190',
            'fax'             => 'nullable|string|max:30',
            'sb_sci'          => 'nullable|string|max:10',
            'remitter_name'   => 'nullable|string|max:50',
            'contact_channel' => 'nullable|in:' . implode(',', array_keys(\App\Models\Patient::CONTACT_CHANNELS)),
            'contact_status'  => 'nullable|in:' . implode(',', array_keys(\App\Models\Patient::CONTACT_STATUSES)),
            'deduction'       => 'nullable|in:' . implode(',', \App\Models\Patient::DEDUCTION_TYPES),
            'cash_receipt_no' => 'nullable|string|max:30',

            'nhis_reg_status'  => 'nullable|string|max:30',
            'nhis_reg_date'    => 'nullable|date',
            'nhis_renew'       => 'nullable|string|max:100',
            'nhis_renew_due'   => 'nullable|date',
            'nhis_agree_start' => 'nullable|date',
            'nhis_agree_end'   => 'nullable|date',
            'basic_reeval'     => 'nullable|string|max:100',
            'basic_reeval_due' => 'nullable|date',
            'new_patient_date' => 'nullable|date',
        ];
    }

    /** 자진발급이면 번호가 정해져 있다 — 담당자가 매번 외워 치지 않게 여기서 채운다 */
    private function fillSelfIssue(array $data): array
    {
        if (($data['deduction'] ?? null) === '자진발급' && empty($data['cash_receipt_no'])) {
            $data['cash_receipt_no'] = \App\Models\Patient::SELF_ISSUE_NO;
        }

        return $data;
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $this->fillSelfIssue($request->validate($this->patientRules()));

        // 칸이 없는 서버에서는 사업부를 빼고 저장한다 — 넣으면 질의가 깨진다
        if (!Patient::hasCareTypeColumn()) {
            unset($data['care_type']);
        }

        $patient = Patient::create($data);

        activity()->causedBy(auth()->user())->performedOn($patient)
            ->log("{$patient->name} 거래처 등록");

        return response()->json([
            'success' => true,
            'message' => "{$patient->name} 거래처로 등록되었습니다.",
            'id'      => $patient->id,
        ]);
    }

    // ── 수정 ──────────────────────────────────────────────
    public function update(Request $request, Patient $patient): \Illuminate\Http\JsonResponse
    {
        $data = $this->fillSelfIssue($request->validate($this->patientRules()));

        // 칸이 없는 서버에서는 사업부를 빼고 저장한다 — 넣으면 질의가 깨진다
        if (!Patient::hasCareTypeColumn()) {
            unset($data['care_type']);
        }

        $patient->update($data);

        activity()->causedBy(auth()->user())->performedOn($patient)
            ->log("{$patient->name} 환자 정보 수정");

        return response()->json(['success' => true, 'message' => '저장되었습니다.']);
    }

    // ── 삭제 (소프트) ─────────────────────────────────────
    public function destroy(Patient $patient): \Illuminate\Http\JsonResponse
    {
        $name = $patient->name;
        $patient->delete();

        activity()->causedBy(auth()->user())
            ->log("{$name} 환자 삭제");

        return response()->json(['success' => true, 'message' => "{$name} 환자가 삭제되었습니다."]);
    }
}
