<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrescriptionDocument extends Model
{
    protected $fillable = [
        'prescription_id',
        'patient_id',
        'created_by',
        'type',
        'file_path',
        'original_filename',
    ];

    /**
     * 서류함이 바뀌면 청구 준비를 다시 따진다 (2026-09-18 지시).
     *
     * 공단에는 세금계산서를 **종이로** 올린다. 발행만 하고 서류함에 종이가 없으면
     * 올릴 것이 없어 청구가 안 되는데, 서류함은 주문을 건드리지 않고 드나든다 —
     * 정정으로 종이를 지운 뒤에도 목록은 「준비완료」로 서 있었다.
     *
     * 세금계산서만 본다. 처방전ㆍ신분증 같은 것은 판정에 들지 않는다.
     */
    protected static function booted(): void
    {
        $다시보기 = function (self $d) {
            if ($d->type === 'tax_invoice') {
                app(\App\Services\ClaimReadiness::class)->처방전다시보기($d->prescription_id);
            }
        };

        static::created($다시보기);
        static::deleted($다시보기);
    }

    /**
     * 같은 자리를 가리키는 줄이 이미 있으면 그것을 쓴다 (2026-10-06 · SR #85).
     *
     * 시스템이 만드는 서류(현금영수증ㆍ세금계산서ㆍ팩스통합본)는 자리가 정해져 있다 —
     * `cash_receipts/{주문}/{이름}.pdf` 처럼 주문과 이름으로 짜인다. 그런데 그 서류를
     * 만드는 길이 둘이고(발행할 때, 그리고 **내려받을 때**) 둘 다 줄을 새로 담았다.
     * 그래서 담당자가 내려받을 때마다 서류함에 같은 줄이 하나씩 늘었다.
     *
     * 2026-10-06 운영에서 그렇게 늘어난 줄이 여섯이었다 — (E)김철호 현금영수증 두 줄
     * (12:03 발행 · 12:38 내려받기), (E)김지수B 세 줄, (E)최우용 팩스통합본 네 줄.
     *
     * 파일은 같은 자리에 덮어 쓰이므로 줄만 늘었다 — 서류 자체는 하나다. 그 한 장을
     * 가리키는 줄도 하나여야 한다.
     *
     * 사람이 올리는 첨부는 이 길을 쓰지 않는다 — 같은 파일을 두 번 올리는 것은
     * 담당자의 뜻이고, 자리도 올릴 때마다 달라진다.
     */
    public static function 한줄만(array $열쇠, array $값 = []): self
    {
        return static::firstOrCreate($열쇠, $값);
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'consent'      => '위임동의서',
            'delegation'   => '요양비위임장',
            'registration' => '등록신청서',
            'fax'          => '팩스통합본',
            'cash_receipt' => '현금영수증',
            'tax_invoice'  => '세금계산서',
            default        => $this->type,
        };
    }

    /** 서류 관리 화면에서 직접 업로드한 건인가 (저장 경로로 판별 — 자동 생성본과 구분) */
    public function isManuallyRegistered(): bool
    {
        return str_starts_with((string) $this->file_path, 'documents/manual/');
    }

    public function sourceLabel(): string
    {
        if ($this->isManuallyRegistered()) {
            return '직접 등록';
        }

        return match ($this->type) {
            'consent'      => '서명 완료',
            'delegation'   => '서명 완료(자동)',
            'registration' => '서명 완료(자동)',
            'fax'          => '팩스 전송',
            'cash_receipt' => '현금영수증 발행',
            'tax_invoice'  => '세금계산서 발행',
            default        => '-',
        };
    }
}
