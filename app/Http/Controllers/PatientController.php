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

        if ($request->filled('nhis')) {
            $query->where('is_nhis_eligible', $request->nhis === '1');
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

            // 건보 배지 → 텍스트
            $nhis = $p->is_nhis_eligible
                ? '급여 ' . $p->nhis_coverage_rate . '%'
                : '비급여';

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

            return [
                'id'              => $p->id,
                'name'            => $p->name,
                'resident_no'     => $p->masked_resident_no ?? '-',
                'birth_date'      => $birth,
                'gender'          => $gender,
                'mobile'          => $p->mobile ?? $p->phone ?? '-',
                'nhis'            => $nhis,

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
        $patient->load(['prescriptions' => fn($q) => $q->latest()->take(20)]);
        return view('patients.show', compact('patient'));
    }

    /** 환자 이력(처방전·상담·구매) — 목록 화면 우측 상세 탭용 JSON */
    public function histories(Patient $patient): \Illuminate\Http\JsonResponse
    {
        $rx = $patient->prescriptions()->with(['creator', 'updater'])->latest()->take(50)->get();

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
            'counsel_no' => $p->counsel_no ?: '-',
            'rx_number'  => $p->rx_number,
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

    // ── 등록 ──────────────────────────────────────────────
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'name'               => 'required|string|max:50',
            'resident_no'        => 'nullable|string|max:20',
            'birth_date'         => 'nullable|date',
            'gender'             => 'nullable|in:male,female',
            'mobile'             => 'nullable|string|max:30',
            'phone'              => 'nullable|string|max:30',
            'address'            => 'nullable|string|max:300',
            'health_insurance_no'=> 'nullable|string|max:20',
            'is_nhis_eligible'   => 'boolean',
            'nhis_coverage_rate' => 'nullable|integer|min:0|max:100',
            'note'               => 'nullable|string|max:1000',
        ]);

        $patient = Patient::create($data);

        activity()->causedBy(auth()->user())->performedOn($patient)
            ->log("{$patient->name} 환자 등록");

        return response()->json([
            'success' => true,
            'message' => "{$patient->name} 환자가 등록되었습니다.",
            'id'      => $patient->id,
        ]);
    }

    // ── 수정 ──────────────────────────────────────────────
    public function update(Request $request, Patient $patient): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'name'               => 'required|string|max:50',
            'resident_no'        => 'nullable|string|max:20',
            'birth_date'         => 'nullable|date',
            'gender'             => 'nullable|in:male,female',
            'mobile'             => 'nullable|string|max:30',
            'phone'              => 'nullable|string|max:30',
            'address'            => 'nullable|string|max:300',
            'health_insurance_no'=> 'nullable|string|max:20',
            'is_nhis_eligible'   => 'boolean',
            'nhis_coverage_rate' => 'nullable|integer|min:0|max:100',
            'note'               => 'nullable|string|max:1000',
        ]);

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
