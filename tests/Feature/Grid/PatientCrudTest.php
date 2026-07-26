<?php

namespace Tests\Feature\Grid;

use App\Models\Patient;

class PatientCrudTest extends CrudTestCase
{
    private function makePatient(): Patient
    {
        return Patient::create([
            'name'             => '홍길동',
            'resident_no'      => '900101-1234567',
            'birth_date'       => '1990-01-01',
            'gender'           => 'male',
            'mobile'           => '01012345678',
            'is_nhis_eligible' => 1,
            'nhis_coverage_rate' => 90,
        ]);
    }

    public function test_create(): void
    {
        $this->actingAsAdmin();

        $res = $this->postJson('/patients', [
            'name'               => '김환자',
            'mobile'             => '01099998888',
            'gender'             => 'female',
            'is_nhis_eligible'   => 1,
            'nhis_coverage_rate' => 90,
        ]);
        $res->assertSuccessful()->assertJson(['success' => true]);
        $this->assertDatabaseHas('patients', ['name' => '김환자', 'mobile' => '01099998888']);
    }

    public function test_update(): void
    {
        $this->actingAsAdmin();
        $p = $this->makePatient();

        $this->putJson("/patients/{$p->id}", [
            'name'   => '홍길동수정',
            'mobile' => '01055554444',
            'gender' => 'male',
        ])->assertSuccessful();

        $this->assertDatabaseHas('patients', ['id' => $p->id, 'name' => '홍길동수정', 'mobile' => '01055554444']);
    }

    public function test_delete_soft(): void
    {
        $this->actingAsAdmin();
        $p = $this->makePatient();

        $this->deleteJson("/patients/{$p->id}")->assertSuccessful();
        // Patient는 SoftDeletes → 소프트 삭제 확인
        $this->assertSoftDeleted('patients', ['id' => $p->id]);
    }
}
