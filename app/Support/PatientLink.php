<?php
// app/Support/PatientLink.php
// 처방전에 환자를 잇는다 — 없으면 만든다.

namespace App\Support;

use App\Models\Patient;
use App\Models\Prescription;
use Illuminate\Support\Facades\Auth;

/**
 * 처방전과 환자를 잇는 한 가지 규칙.
 *
 * 주문 등록의 저장이 쓰고, 위임동의 서명도 쓴다. 두 벌로 두었더니 한쪽만 고쳐져
 * 같은 사람이 길에 따라 다르게 이어질 자리가 생겼다 — 한 곳에 둔다.
 */
class PatientLink
{
    public static function attach(Prescription $prescription, array $d): ?Patient
    {
        $name       = $d['patient_name'] ?? $d['patient_name_ocr'] ?? null;
        $residentNo = $d['resident_no']  ?? null;
        $mobile     = $d['mobile'] ?? $d['phone'] ?? null;
        $address    = $d['address'] ?? null;

        // 이름이 없으면 연결 불가
        if (empty($name)) {
            return null;
        }

        $patient = null;

        // ① 주민등록번호로 기존 환자 검색 (가장 정확)
        //    평문 비교가 아니라 조회용 해시로 찾는다 — 평문 컬럼은 곧 사라진다(P0-1)
        if ($residentNo) {
            $patient = Patient::whereResidentNo($residentNo)->first();
        }

        /* **이름은 「(E)」를 떼고 맞댄다** (2026-10-01 지시).

           거래처는 담길 때 사업부가 IC 면 이름 앞에 「(E)」가 붙는다
           (Patient::nameWithCareTag). 그런데 여기서는 **맨 이름으로 찾고 있었다** —
           이관한 12,608명이 모두 「(E)」를 달고 있으므로 한 명도 걸리지 않았고,
           주민번호나 휴대폰이 어긋나는 건마다 같은 사람을 새로 만들었다.

           2026-10-01 (E)김광연이 그랬다 — 서명이 들어온 순간 거래처가 하나 더 생겨,
           위임 자료는 새 거래처의 주문에, 제품은 본래 거래처의 주문에 갈려 담겼다.

           찾을 때는 두 꼴을 다 본다. LIKE 로 넓히지 않는다 — 「김광연」이 「김광연수」를
           끌어오면 남의 자료에 이어 붙는다. */
        $맨이름  = Patient::bare($name);
        $이름들  = array_values(array_unique(array_filter([$맨이름, '(E)' . $맨이름])));

        /* 휴대폰도 꼴을 맞춰 맞댄다 — 한쪽은 「010-5118-2497」, 다른 쪽은
           「01051182497」로 담긴다. 글자 그대로 견주면 같은 번호가 다른 번호가 된다. */
        $번호 = preg_replace('/\D/', '', (string) $mobile);

        // ② 이름 + 휴대폰으로 검색
        if (!$patient && $번호 !== '') {
            $patient = Patient::whereIn('name', $이름들)
                ->whereRaw("REPLACE(REPLACE(COALESCE(mobile,''),'-',''),' ','') = ?", [$번호])
                ->first();
        }

        // ③ 이름만으로 검색 (동명이인 주의 — 하나일 때만 연결)
        if (!$patient) {
            $sameNamePatients = Patient::whereIn('name', $이름들)->get();
            if ($sameNamePatients->count() === 1) {
                $patient = $sameNamePatients->first();
            }
        }

        /* 생년월일·성별은 주민번호 앞 7자리에서 나온다. 원문을 열 필요가 없다(P0-1).
           이 값을 채우지 않아 거래처 관리 그리드의 두 칸이 늘 비어 있었다. */
        $masked = ResidentNo::mask($residentNo);
        $birth  = ResidentNo::birthDateFromMasked($masked);
        $gender = ResidentNo::genderFromMasked($masked);

        if ($patient) {
            // 기존 환자 — 비어있는 필드만 OCR 값으로 채움
            $updates = [];
            if (!$patient->resident_no && $residentNo) $updates['resident_no'] = $residentNo;
            if (!$patient->mobile      && $mobile)     $updates['mobile']      = $mobile;
            if (!$patient->address     && $address)    $updates['address']     = $address;
            if (!$patient->birth_date  && $birth)      $updates['birth_date']  = $birth;
            if (!$patient->gender      && $gender)     $updates['gender']      = $gender;
            if ($updates) {
                $patient->update($updates);
            }
        } else {
            // 신규 환자 등록
            $attrs = [
                'name'        => $name,
                'resident_no' => $residentNo,
                'mobile'      => $mobile,
                'address'     => $address,
                'birth_date'  => $birth,
                'gender'      => $gender,
            ];
            // 사업부는 골랐을 때만 넣는다 — 칸이 없는 서버에서 빈 값을 끼우면 질의가 깨진다
            if (!empty($d['care_type'])) {
                $attrs['care_type'] = $d['care_type'];
            }

            $patient = Patient::create($attrs);

            /* 기존에 없던 사람이니 이 건은 그 사람의 첫 구매다. 화면이 미리 세워 두지만,
               다른 길(모바일ㆍ자동 등록)로 들어온 건에도 같게 적어 둔다.
               담당자가 골라 둔 값이 있으면 손대지 않는다. */
            if (empty($prescription->purchase_type)) {
                $prescription->update(['purchase_type' => '신구매']);
            }

            activity()
                ->causedBy(Auth::user())
                ->performedOn($patient)
                ->log("{$name} 환자 자동 등록 (처방전 {$prescription->rx_number})");
        }

        // 처방전에 patient_id 연결
        $prescription->update(['patient_id' => $patient->id]);

        return $patient;
    }
}
