<?php
// database/seeders/DevResetSeeder.php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Patient;
use App\Models\Prescription;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DevResetSeeder extends Seeder
{
    // ── 임의 데이터 풀 ────────────────────────────────────────
    private array $surnames     = ['김','이','박','최','정','강','조','윤','장','임','한','오','서','신','권','황','안','송','유','홍'];
    private array $maleNames    = ['준혁','민준','성진','지훈','동현','재원','현우','영수','승민','태양','진호','철수','민성','도현','하준'];
    private array $femaleNames  = ['지수','서연','민지','예진','수빈','하은','지현','나연','유리','소연','혜진','아영','채원','다연','은서'];
    private array $cities       = [
        '서울특별시 강남구', '서울특별시 서초구', '서울특별시 마포구', '서울특별시 송파구',
        '부산광역시 해운대구', '부산광역시 부산진구',
        '대구광역시 수성구', '대구광역시 달서구',
        '인천광역시 남동구', '인천광역시 연수구',
        '광주광역시 서구', '대전광역시 서구',
        '경기도 수원시', '경기도 성남시', '경기도 고양시', '경기도 용인시',
        '경상북도 포항시', '경상남도 창원시', '충청북도 청주시', '전라북도 전주시',
    ];
    private array $streets      = ['중앙대로','번영로','대학로','시청길','동문로','서부로','남산길','북로','태평로','한강대로'];
    private array $hospitals    = [
        ['name' => '경북대학교병원',     'code' => '37100017'],
        ['name' => '서울대학교병원',     'code' => '11100017'],
        ['name' => '세브란스병원',       'code' => '11100023'],
        ['name' => '삼성서울병원',       'code' => '11100024'],
        ['name' => '아산의료원',         'code' => '11100025'],
        ['name' => '가톨릭대학교서울성모병원', 'code' => '11100026'],
        ['name' => '고려대학교병원',     'code' => '11100027'],
        ['name' => '부산대학교병원',     'code' => '26100017'],
        ['name' => '전남대학교병원',     'code' => '61100017'],
        ['name' => '충북대학교병원',     'code' => '43100017'],
    ];
    private array $doctors      = ['유은상','박철환','이준영','김민수','정현식','강태훈','조성일','윤진호','서혜연','최지현'];
    private array $diseases     = [
        ['name' => '하반신마비 및 사지마비',   'code' => 'G82.x'],
        ['name' => '척수 손상',               'code' => 'S14.x'],
        ['name' => '신경인성 방광',            'code' => 'N31.9'],
        ['name' => '배뇨 기능 장애',           'code' => 'R33.x'],
        ['name' => '전립선 비대증',            'code' => 'N40.x'],
        ['name' => '요도 협착',               'code' => 'N35.x'],
        ['name' => '다발성 경화증',            'code' => 'G35.x'],
        ['name' => '파킨슨병',                'code' => 'G20.x'],
        ['name' => '요실금',                  'code' => 'N39.3'],
        ['name' => '방광 기능 이상',           'code' => 'N32.x'],
    ];
    private array $products     = [
        ['name' => 'SpeediCath Compact Male',   'code' => 'SC-CM-14'],
        ['name' => 'SpeediCath Compact Female', 'code' => 'SC-CF-14'],
        ['name' => 'SpeediCath Standard Male',  'code' => 'SC-SM-14'],
        ['name' => 'Lofric Primo Male',         'code' => 'LP-M-14'],
        ['name' => 'Lofric Primo Female',       'code' => 'LP-F-14'],
        ['name' => 'Lofric Origo Male',         'code' => 'LO-M-14'],
        ['name' => 'EasiCath Male',             'code' => 'EC-M-14'],
        ['name' => 'EasiCath Female',           'code' => 'EC-F-14'],
    ];
    private array $statuses     = [
        'pending'       => 5,
        'ocr_done'      => 8,
        'review_needed' => 10,
        'approved'      => 17,
        'ordered'       => 10,
    ];

    public function run(): void
    {
        // ── 1. 외래키 체크 해제 후 전체 초기화 ───────────────
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('nhis_fax_logs')->truncate();
        DB::table('toss_payments')->truncate();
        DB::table('orders')->truncate();
        DB::table('prescription_items')->truncate();
        DB::table('prescriptions')->truncate();
        DB::table('patients')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->command->info('기존 데이터 삭제 완료');

        // ── 2. 운영 유저 ID 가져오기 ──────────────────────────
        $userIds = DB::table('users')->pluck('id')->all();
        if (empty($userIds)) {
            $this->command->error('users 테이블에 유저가 없습니다. 먼저 DatabaseSeeder를 실행하세요.');
            return;
        }
        $adminId    = DB::table('users')->where('role', 'admin')->value('id') ?? $userIds[0];
        $managerIds = DB::table('users')->where('role', 'manager')->pluck('id')->all()
                        ?: $userIds;

        // ── 3. 환자 50명 생성 ─────────────────────────────────
        $patients = [];
        for ($i = 0; $i < 50; $i++) {
            $gender    = ($i % 2 === 0) ? 'M' : 'F';
            $firstName = $gender === 'M'
                ? $this->maleNames[array_rand($this->maleNames)]
                : $this->femaleNames[array_rand($this->femaleNames)];
            $surname   = $this->surnames[array_rand($this->surnames)];
            $name      = $surname . $firstName;

            // 생년월일 (1960~2000)
            $birthYear  = rand(1960, 2000);
            $birthMonth = str_pad(rand(1, 12), 2, '0', STR_PAD_LEFT);
            $birthDay   = str_pad(rand(1, 28), 2, '0', STR_PAD_LEFT);
            $birthDate  = "{$birthYear}-{$birthMonth}-{$birthDay}";

            // 주민등록번호 (형식만)
            $yy         = substr($birthYear, 2);
            $genderCode = ($gender === 'M') ? (($birthYear >= 2000) ? '3' : '1') : (($birthYear >= 2000) ? '4' : '2');
            $residentNo = "{$yy}{$birthMonth}{$birthDay}-{$genderCode}" . str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);

            // 연락처
            $mobile     = '010-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT) . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);

            // 주소
            $city    = $this->cities[array_rand($this->cities)];
            $street  = $this->streets[array_rand($this->streets)];
            $address = "{$city} {$street} " . rand(1, 999) . '번길 ' . rand(1, 50);

            $nhisEligible    = (rand(1, 10) <= 7); // 70% 건강보험 적용
            $nhisCoverageRate = $nhisEligible ? (rand(0, 1) ? 90.00 : 80.00) : 0.00;

            $patient = Patient::create([
                'name'                => $name,
                'resident_no'         => $residentNo,
                'birth_date'          => $birthDate,
                'gender'              => $gender,
                'mobile'              => $mobile,
                'address'             => $address,
                'is_nhis_eligible'    => $nhisEligible,
                'nhis_coverage_rate'  => $nhisCoverageRate,
            ]);
            $patients[] = $patient;
        }

        $this->command->info('환자 50명 생성 완료');

        // ── 4. 처방전 50건 생성 ───────────────────────────────
        // 상태별 수량 배열로 풀기
        $statusPool = [];
        foreach ($this->statuses as $status => $count) {
            for ($c = 0; $c < $count; $c++) {
                $statusPool[] = $status;
            }
        }
        shuffle($statusPool);

        $today       = now();
        $orderSeq    = 1;

        foreach ($patients as $idx => $patient) {
            $status    = $statusPool[$idx];
            $daysAgo   = rand(1, 90);
            $createdAt = $today->copy()->subDays($daysAgo)->subHours(rand(0, 23))->subMinutes(rand(0, 59));
            $issuedDate = $createdAt->copy()->subDays(rand(0, 3))->format('Y-m-d');
            $rxDate    = $createdAt->format('Ymd');
            $rxNumber  = sprintf('RX-%s-%03d', $rxDate, $idx + 1);

            $hospital  = $this->hospitals[array_rand($this->hospitals)];
            $doctor    = $this->doctors[array_rand($this->doctors)];
            $disease   = $this->diseases[array_rand($this->diseases)];
            $product   = $this->products[array_rand($this->products)];
            $assignedId = $managerIds[array_rand($managerIds)];

            $dailyCount  = rand(4, 8);
            $totalDays   = [30, 60, 90][array_rand([30, 60, 90])];
            $totalCount  = $dailyCount * $totalDays;

            $productPrice = rand(40000, 80000);
            $nhisEligible = $patient->is_nhis_eligible;
            $nhisRate     = $nhisEligible ? $patient->nhis_coverage_rate / 100 : 0;
            $nhisAmount   = $nhisEligible ? round($productPrice * $nhisRate) : 0;
            $patientCopay = $productPrice - $nhisAmount;

            // 검수 완료 상태면 reviewer 지정
            $isReviewed  = in_array($status, ['approved', 'ordered']);
            $reviewedBy  = $isReviewed ? $managerIds[array_rand($managerIds)] : null;
            $reviewedAt  = $isReviewed ? $createdAt->copy()->addHours(rand(1, 8)) : null;

            // 재구매 예정일 (ordered/approved의 40%에 설정)
            $repurchaseDate = null;
            if (in_array($status, ['approved', 'ordered']) && rand(1, 10) <= 4) {
                $repurchaseDate = $today->copy()->addDays(rand(-30, 60))->format('Y-m-d');
            }

            // postcode + address
            $postcode = str_pad(rand(10000, 99999), 5, '0', STR_PAD_LEFT);

            $rx = Prescription::create([
                'rx_number'         => $rxNumber,
                'patient_id'        => $patient->id,
                'assigned_user_id'  => $assignedId,
                'created_by'        => $adminId,
                'upload_source'     => (rand(1, 3) === 1) ? 'mobile' : 'web',
                'status'            => $status,
                'ocr_confidence'    => in_array($status, ['pending']) ? null : rand(70, 99),
                'patient_name_ocr'  => $patient->name,
                'hospital_name'     => $hospital['name'],
                'hospital_code'     => $hospital['code'],
                'doctor_name'       => $doctor,
                'specialty'         => '비뇨의학과',
                'license_no'        => str_pad(rand(10000, 99999), 5, '0', STR_PAD_LEFT),
                'department'        => '비뇨기의학과',
                'disease_name'      => $disease['name'],
                'disease_code'      => $disease['code'],
                'daily_count'       => $dailyCount,
                'total_days'        => $totalDays,
                'total_count'       => $totalCount,
                'usage_period'      => '교부일로부터 처방기간까지',
                'issued_date'       => $issuedDate,
                'product_name'      => $product['name'],
                'product_code'      => $product['code'],
                'quantity'          => $totalCount,
                'nhis_status'       => $nhisEligible ? 'eligible' : 'ineligible',
                'product_price'     => $productPrice,
                'nhis_amount'       => $nhisAmount,
                'patient_copay'     => $patientCopay,
                'reviewed_by'       => $reviewedBy,
                'reviewed_at'       => $reviewedAt,
                'postcode'          => $postcode,
                'address_detail'    => $patient->address,
                'repurchase_date'   => $repurchaseDate,
                'created_at'        => $createdAt,
                'updated_at'        => $createdAt,
            ]);

            // ordered 상태면 주문 생성
            if ($status === 'ordered') {
                $orderNumber = sprintf('ORD-%04d', $orderSeq++);
                $shippingFee  = 3000;
                $totalAmount  = $patientCopay + $shippingFee;
                $orderCreatedAt = $reviewedAt->copy()->addHours(rand(1, 6));

                $nhisClaimStatuses = ['pending', 'submitted', 'approved', 'rejected'];
                $nhisClaimWeights  = [3, 3, 3, 1]; // pending/submitted/approved 더 많이
                $nhisClaimStatus   = $this->weightedRandom($nhisClaimStatuses, $nhisClaimWeights);

                $orderStatuses = ['confirmed', 'shipping', 'delivered'];
                $orderStatus   = $orderStatuses[array_rand($orderStatuses)];

                $trackingNumber  = null;
                $deliveredAt     = null;
                $estimatedDelivery = $orderCreatedAt->copy()->addDays(rand(2, 5))->format('Y-m-d');

                if ($orderStatus === 'shipping' || $orderStatus === 'delivered') {
                    $trackingNumber = str_pad(rand(100000000000, 999999999999), 12, '0', STR_PAD_LEFT);
                }
                if ($orderStatus === 'delivered') {
                    $deliveredAt = $orderCreatedAt->copy()->addDays(rand(3, 7));
                }

                Order::create([
                    'order_number'       => $orderNumber,
                    'prescription_id'    => $rx->id,
                    'patient_id'         => $patient->id,
                    'created_by'         => $assignedId,
                    'product_name'       => $product['name'],
                    'product_code'       => $product['code'],
                    'quantity'           => $totalCount,
                    'unit_price'         => $productPrice,
                    'nhis_amount'        => $nhisAmount,
                    'patient_copay'      => $patientCopay,
                    'shipping_fee'       => $shippingFee,
                    'total_amount'       => $totalAmount,
                    'status'             => $orderStatus,
                    'shipping_address'   => $patient->address,
                    'tracking_number'    => $trackingNumber,
                    'estimated_delivery' => $estimatedDelivery,
                    'delivered_at'       => $deliveredAt,
                    'nhis_claim_status'  => $nhisClaimStatus,
                    'nhis_submitted_at'  => in_array($nhisClaimStatus, ['submitted','approved','rejected'])
                                            ? $orderCreatedAt->copy()->addDays(rand(1, 3))
                                            : null,
                    'nhis_approved_at'   => $nhisClaimStatus === 'approved'
                                            ? $orderCreatedAt->copy()->addDays(rand(5, 14))
                                            : null,
                    'nhis_reimbursement' => $nhisClaimStatus === 'approved' ? $nhisAmount : null,
                    'created_at'         => $orderCreatedAt,
                    'updated_at'         => $orderCreatedAt,
                ]);
            }
        }

        $this->command->info('처방전 50건 생성 완료 (주문 ' . ($orderSeq - 1) . '건 포함)');
    }

    private function weightedRandom(array $items, array $weights): mixed
    {
        $total = array_sum($weights);
        $rand  = rand(1, $total);
        $sum   = 0;
        foreach ($items as $i => $item) {
            $sum += $weights[$i];
            if ($rand <= $sum) return $item;
        }
        return $items[array_key_last($items)];
    }
}
