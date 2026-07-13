<?php
// app/Models/PrivacyConsent.php
// mcoloplast 개인정보 수집·이용 동의서 (카테터/장루)

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PrivacyConsent extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'type', 'name', 'phone', 'phone2', 'email', 'zip', 'addr1', 'addr2',
        'insurance', 'support_qualify',
        'birth', 'product', 'hospital', 'surgery_date', 'stoma_type', 'stoma_kind',
        'agree_general', 'agree_sensitive', 'agree_third_party',
        'agree_marketing', 'agree_marketing_sensitive', 'agree_third_sensitive',
        'extra', 'ip', 'user_agent', 'submitted_at',
    ];

    protected $casts = [
        'extra'        => 'array',
        'submitted_at' => 'datetime',
    ];

    public const TYPE_LABELS = [
        'catheter' => '카테터',
        'stoma'    => '장루',
    ];

    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    /** 전체 주소 */
    public function getFullAddressAttribute(): string
    {
        return trim(($this->zip ? "({$this->zip}) " : '') . trim("{$this->addr1} {$this->addr2}"));
    }

    /** 필수 동의 완료 여부 */
    public function getRequiredAgreedAttribute(): bool
    {
        $required = $this->type === 'stoma'
            ? ['agree_general', 'agree_sensitive']
            : ['agree_general', 'agree_third_party'];
        foreach ($required as $f) {
            if ($this->{$f} !== '동의함') {
                return false;
            }
        }
        return true;
    }
}
