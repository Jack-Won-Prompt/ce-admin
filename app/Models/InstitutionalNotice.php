<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstitutionalNotice extends Model
{
    protected $fillable = [
        'source_org',
        'notice_type',
        'title',
        'notice_date',
        'content',
        'url',
        'content_hash',
        'attachments',
        'policy_impact',
        'fee_impact',
        'crawled_at',
    ];

    protected $casts = [
        'attachments' => 'array',
        'fee_impact'  => 'boolean',
        'notice_date' => 'date',
        'crawled_at'  => 'datetime',
    ];

    public function scopeByOrg($query, string $org)
    {
        return $query->where('source_org', $org);
    }

    public function getPolicyImpactBadgeClass(): string
    {
        return match($this->policy_impact) {
            'HIGH'   => 'danger',
            'MEDIUM' => 'warning',
            default  => 'secondary',
        };
    }
}
