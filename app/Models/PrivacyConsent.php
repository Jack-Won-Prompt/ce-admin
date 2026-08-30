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
        // 환자와 잇는 열쇠. 밖에서 들어오는 폼이라 비어 있을 수 있다 — 이름+전화로 맞춰 채운다.
        'patient_id',
        'type', 'source', 'name', 'phone', 'phone2', 'email', 'zip', 'addr1', 'addr2',
        'insurance', 'support_qualify',
        'birth', 'product', 'hospital', 'surgery_date', 'stoma_type', 'stoma_kind',
        'agree_general', 'agree_sensitive', 'agree_third_party',
        'agree_marketing', 'agree_marketing_sensitive', 'agree_third_sensitive',
        'extra', 'ip', 'user_agent', 'admin_memo', 'submitted_at',
    ];

    protected $casts = [
        'extra'        => 'array',
        'submitted_at' => 'datetime',
    ];

    public function patient(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public const TYPE_LABELS = [
        'catheter' => '카테터',
        'stoma'    => '장루',
    ];

    /** 구분 — 동의를 어떻게 받았는지. '유형'(카테터·장루)과는 다른 축이다. */
    public const SOURCE_LABELS = [
        'mobile' => '모바일 동의',
        'paper'  => '서면 동의',
        'phone'  => '유선 동의',
    ];

    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    public function getSourceLabelAttribute(): string
    {
        return self::SOURCE_LABELS[$this->source] ?? ($this->source ?: '모바일 동의');
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

    /** 동의 항목 이름 — 화면·요약에서 같은 말로 부른다. */
    public const AGREE_LABELS = [
        'agree_general'             => '일반정보 수집·이용',
        'agree_sensitive'           => '민감정보 수집·이용',
        'agree_third_party'         => '제3자 제공',
        'agree_third_sensitive'     => '민감정보 제3자 제공',
        'agree_marketing'           => '마케팅 활용',
        'agree_marketing_sensitive' => '민감정보 마케팅 활용',
    ];

    /**
     * 갈래마다 묻는 항목.
     *
     * 카테터에는 민감정보 물음이 없다 — 폼에도 서명 화면에도 뜨지 않는다.
     * 그런데 담긴 값에는 「동의하지 않음」이 들어 있는 건이 있다(서명 화면이 고르지
     * 않은 선택 항목을 그렇게 채워 보내던 때의 것이다). 그것을 그대로 보여 주면
     * 물어보지도 않은 것을 환자가 거절한 것처럼 읽힌다 — 갈래로 걸러 세운다.
     */
    public const TYPE_AGREES = [
        'catheter' => ['agree_general', 'agree_third_party', 'agree_marketing'],
        'stoma'    => ['agree_general', 'agree_sensitive', 'agree_third_party',
                       'agree_third_sensitive', 'agree_marketing', 'agree_marketing_sensitive'],
    ];

    /**
     * 이 사람의 가장 최근 동의.
     *
     * 동의서는 밖에서 환자가 직접 적는 폼이라 환자 번호가 비어 있는 것이 많다.
     * 이어진 것이 있으면 그것을 먼저 보고, 없으면 이름과 연락처로 맞춘다 —
     * 개인정보동의 화면이 「동의자」로 읽는 것이 이 두 칸이다.
     * 전화번호는 적는 사람마다 하이픈이 있고 없고가 달라 숫자만 견준다.
     */
    public static function findFor(?int $patientId, ?string $name = null, ?string $phone = null): ?self
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        // (E) 는 사업부 표시다 — 동의서에 적히는 이름에는 없다
        $bare   = trim(preg_replace('/^\s*\(E\)\s*/u', '', (string) $name));
        $byName = $bare !== '' && $digits !== '';

        if (!$patientId && !$byName) {
            return null;
        }

        return static::query()
            ->where(function ($q) use ($patientId, $bare, $digits, $byName) {
                if ($patientId) {
                    $q->orWhere('patient_id', $patientId);
                }
                if ($byName) {
                    $q->orWhere(function ($w) use ($bare, $digits) {
                        $w->where('name', $bare)
                          ->whereRaw("REPLACE(REPLACE(phone, '-', ''), ' ', '') = ?", [$digits]);
                    });
                }
            })
            ->orderByDesc('submitted_at')->orderByDesc('id')
            ->first();
    }

    /**
     * 주문 등록 화면의 「개인정보동의」 단추가 읽는 값.
     *
     * 받은 것이 없으면 exists=false 로만 답한다 — 단추는 그대로 「개인정보동의」다.
     */
    public static function stateFor(?int $patientId, ?string $name = null, ?string $phone = null): array
    {
        $c = static::findFor($patientId, $name, $phone);

        if (!$c) {
            return ['exists' => false, 'agreed' => false];
        }

        /* 이 갈래에서 묻는 것만 세운다. 갈래를 모르는 옛 건은 담긴 것을 그대로 보인다 —
           지우고 말하느니 있는 대로 보여 주는 편이 낫다. */
        $asked = self::TYPE_AGREES[$c->type] ?? array_keys(self::AGREE_LABELS);

        $items = [];
        foreach (self::AGREE_LABELS as $field => $label) {
            if (!in_array($field, $asked, true)) continue;
            if (($c->{$field} ?? '') !== '') {
                $items[] = ['label' => $label, 'value' => $c->{$field}];
            }
        }

        /* 신청자가 적어 준 것. 주소ㆍ이메일처럼 주문 등록에 제 칸이 있는 것은 그리로
           옮겨 적지만, 보험ㆍ지원 자격ㆍ장루 상세는 갈 자리가 없다 — 여기서 동의서
           그대로 읽는다. 빈 칸은 적지 않는다. */
        $info = [];
        foreach ([
            '주소'       => $c->full_address,
            '이메일'     => $c->email,
            '연락처2'    => $c->phone2,
            '생년월일'   => $c->birth,
            '보험'       => $c->insurance,
            '지원 자격'  => $c->support_qualify,
            '사용 제품'  => $c->product,
            '수술 병원'  => $c->hospital,
            '수술일자'   => $c->surgery_date,
            '장루 타입'  => $c->stoma_type,
            '장루 종류'  => $c->stoma_kind,
        ] as $label => $v) {
            if (trim((string) $v) !== '') {
                $info[] = ['label' => $label, 'value' => (string) $v];
            }
        }

        return [
            'exists'    => true,
            'agreed'    => $c->required_agreed,
            'name'      => $c->name,
            'phone'     => $c->phone,
            'type'      => $c->type_label,
            'source'    => $c->source_label,
            'at'        => $c->submitted_at?->format('Y-m-d H:i'),
            'marketing' => $c->agree_marketing === '동의함',
            'info'      => $info,
            'items'     => $items,
        ];
    }
}
