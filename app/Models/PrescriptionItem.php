<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrescriptionItem extends Model
{
    protected $fillable = [
        'prescription_id','product_name','product_code','quantity',
        'product_price','insurance_price','nhis_status',
        'nhis_amount','patient_copay','sort_order',
    ];

    protected $casts = [
        'product_price'   => 'float',
        'insurance_price' => 'float',
        'nhis_amount'     => 'float',
        'patient_copay'   => 'float',
    ];

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }
}
