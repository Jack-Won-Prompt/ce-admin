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

        // ② 이름 + 휴대폰으로 검색
        if (!$patient && $mobile) {
            $patient = Patient::where('name', $name)
                ->where('mobile', $mobile)
                ->first();
        }

        // ③ 이름만으로 검색 (동명이인 주의 — 하나일 때만 연결)
        if (!$patient) {
            $sameNamePatients = Patient::where('name', $name)->get();
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
