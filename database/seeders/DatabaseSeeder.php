<?php
// database/seeders/DatabaseSeeder.php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\Order;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── 관리자 / 매니저 계정 생성 ────────────────────
        $admin = User::create([
            'name'     => '관리자',
            'email'    => 'admin@ce-admin.co.kr',
            'phone'    => '01012340000',
            'password' => Hash::make('password'),
            'role'     => 'admin',
        ]);

        $managers = collect([
            ['name' => '홍매니저', 'email' => 'hong@ce-admin.co.kr', 'phone' => '01011112222'],
            ['name' => '김매니저', 'email' => 'kim@ce-admin.co.kr',  'phone' => '01022223333'],
            ['name' => '박매니저', 'email' => 'park@ce-admin.co.kr', 'phone' => '01033334444'],
            ['name' => '이매니저', 'email' => 'lee@ce-admin.co.kr',  'phone' => '01044445555'],
        ])->map(fn($m) => User::create([...$m, 'password' => Hash::make('password'), 'role' => 'manager']));

        // ── 샘플 환자 ─────────────────────────────────────
        $patients = collect([
            ['name' => '홍길동', 'birth_date' => '1980-01-01', 'gender' => 'M', 'mobile' => '010-1234-5678', 'is_nhis_eligible' => true],
            ['name' => '김영희', 'birth_date' => '1985-06-15', 'gender' => 'F', 'mobile' => '010-2345-6789', 'is_nhis_eligible' => true],
            ['name' => '이철수', 'birth_date' => '1975-03-22', 'gender' => 'M', 'mobile' => '010-3456-7890', 'is_nhis_eligible' => true],
            ['name' => '박지수', 'birth_date' => '1988-07-04', 'gender' => 'F', 'mobile' => '010-4567-8901', 'is_nhis_eligible' => false],
            ['name' => '이승관', 'birth_date' => '1967-02-03', 'gender' => 'M', 'mobile' => '010-6239-4993', 'is_nhis_eligible' => true],
        ])->map(fn($p) => Patient::create([...$p, 'nhis_coverage_rate' => 90.00]));

        // ── 샘플 처방전 ───────────────────────────────────
        $statuses = ['approved', 'approved', 'review_needed', 'ocr_done', 'ordered'];
        foreach ($patients as $i => $patient) {
            $rx = Prescription::create([
                'rx_number'        => 'RX-20260412-' . str_pad($i + 1, 3, '0', STR_PAD_LEFT),
                'patient_id'       => $patient->id,
                'assigned_user_id' => $managers->random()->id,
                'upload_source'    => $i === 0 ? 'mobile' : 'web',
                'status'           => $statuses[$i],
                'ocr_confidence'   => rand(75, 98),
                'patient_name_ocr' => $patient->name,
                'hospital_name'    => '경북대학교병원',
                'hospital_code'    => '37100017',
                'doctor_name'      => '유은상',
                'specialty'        => '비뇨의학과',
                'license_no'       => '56553',
                'department'       => '비뇨기의학과',
                'disease_name'     => '하반신마비 및 사지마비',
                'disease_code'     => 'G82.x',
                'daily_count'      => 6,
                'total_days'       => 90,
                'total_count'      => 540,
                'usage_period'     => '교부일로부터 처방기간까지',
                'issued_date'      => '2026-04-12',
                'product_name'     => 'SpeediCath Compact Male',
                'quantity'         => 90,
                'nhis_status'      => $patient->is_nhis_eligible ? 'eligible' : 'ineligible',
                'product_price'    => 50000,
                'nhis_amount'      => $patient->is_nhis_eligible ? 45000 : 0,
                'patient_copay'    => $patient->is_nhis_eligible ? 5000 : 50000,
                'reviewed_by'      => in_array($statuses[$i], ['approved','ordered']) ? $managers->random()->id : null,
                'reviewed_at'      => in_array($statuses[$i], ['approved','ordered']) ? now()->subHours(rand(1, 5)) : null,
            ]);

            // 주문 연계 샘플
            if ($statuses[$i] === 'ordered') {
                Order::create([
                    'order_number'     => Order::generateOrderNumber(),
                    'prescription_id'  => $rx->id,
                    'patient_id'       => $patient->id,
                    'created_by'       => $managers->random()->id,
                    'product_name'     => $rx->product_name,
                    'quantity'         => $rx->quantity,
                    'unit_price'       => $rx->product_price,
                    'nhis_amount'      => $rx->nhis_amount,
                    'patient_copay'    => $rx->patient_copay,
                    'shipping_fee'     => 3000,
                    'total_amount'     => $rx->patient_copay + 3000,
                    'status'           => 'confirmed',
                    'estimated_delivery' => now()->addDays(3)->format('Y-m-d'),
                    'nhis_claim_status'=> 'pending',
                ]);
            }
        }
    }
}
