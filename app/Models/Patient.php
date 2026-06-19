<?php
// app/Models/Patient.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patient extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'resident_no', 'birth_date', 'gender',
        'phone', 'mobile', 'address',
        'health_insurance_no', 'is_nhis_eligible', 'nhis_coverage_rate', 'note',
    ];

    protected $casts = [
        'birth_date'       => 'date',
        'is_nhis_eligible' => 'boolean',
        'nhis_coverage_rate' => 'float',
    ];

    protected $hidden = ['resident_no']; // API 응답에서 숨김

    public function getAgeAttribute(): ?int
    {
        return $this->birth_date?->age;
    }

    public function getMaskedResidentNoAttribute(): ?string
    {
        if (!$this->resident_no) return null;
        $parts = explode('-', $this->resident_no);
        if (count($parts) === 2) {
            return $parts[0] . '-' . substr($parts[1], 0, 1) . '******';
        }
        return $this->resident_no;
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
