<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 거래처에 붙는 서류 (2026-10-07 지시 · SR #115ㆍ#123).
 *
 * 종이로 받아 둔 위임장ㆍ서명동의ㆍ신분증을 거래처에 담아 둔다. 처방전이 서기 전에
 * 받은 것도 받을 그릇이 있어야 한다 — 그것이 없어 담당자가 종이를 들고 기다렸다.
 *
 * **올린 것을 그대로 쓴다** (2026-10-07 결정 「(가)」). 위임장을 우리가 다시 그리지
 * 않으므로 서명란이 빌 걱정이 없고, 공단에는 환자가 실제로 서명한 원본이 나간다.
 */
class PatientDocument extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'patient_id', 'doc_type', 'doc_label',
        'file_path', 'file_original_name', 'file_mime_type', 'file_size',
        'signed_at', 'signature_data', 'note', 'uploaded_by',
    ];

    protected $casts = [
        'signed_at' => 'date:Y-m-d',
        'file_size' => 'integer',
    ];

    /**
     * 갈래 — 처방전 첨부의 `doc_type` 과 같은 말을 쓴다.
     *
     * 두 곳에서 이름이 다르면 팩스에 실을 때 갈래로 가리는 자리가 어긋난다.
     */
    public const 갈래 = [
        'delegation' => '요양비위임장',
        'consent'    => '개인정보 수집·이용 동의서',
        'id_card'    => '신분증',
        'etc'        => '기타 서류',
    ];

    /** 위임 서명으로 인정하는 갈래 — 위임장과 서명동의 둘이다 */
    public const 서명갈래 = ['delegation', 'consent'];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function typeLabel(): string
    {
        return $this->doc_label ?: (self::갈래[$this->doc_type] ?? $this->doc_type);
    }

    /**
     * 이 서류가 앉아 있는 디스크 (2026-10-08).
     *
     * 올리는 자리(PatientController)와 쓰는 자리(팩스)가 따로 적어 두면 한쪽만
     * 고쳐지는 날이 온다. 공개 디스크가 아니다 — 주민번호와 서명이 든 종이다.
     */
    public const 디스크 = 'local';

    /** 파일의 실제 자리 — 없으면 null */
    public function 절대경로(): ?string
    {
        if (! $this->file_path) {
            return null;
        }

        $disk = \Illuminate\Support\Facades\Storage::disk(self::디스크);

        return $disk->exists($this->file_path) ? $disk->path($this->file_path) : null;
    }

    public function isPdf(): bool
    {
        return str_contains((string) $this->file_mime_type, 'pdf')
            || strtolower(pathinfo((string) $this->file_path, PATHINFO_EXTENSION)) === 'pdf';
    }

    /**
     * 이 거래처의 종이 위임ㆍ서명동의 가운데 가장 나중에 서명한 것 — 없으면 null.
     *
     * 서명일이 없는 줄은 돌려주지 않는다. 위임 유효기간을 그 날에서 재는데(서명유효기간),
     * 날이 없으면 기간을 잴 수 없어 「기간이 남았는가」를 묻는 자리가 모두 거짓이 된다 —
     * 그 줄을 서명으로 치면 기간이 지난 위임으로 청구가 나갈 수 있다.
     */
    public static function 거래처위임(?Patient $patient): ?self
    {
        if (! $patient?->id) {
            return null;
        }

        return static::where('patient_id', $patient->id)
            ->whereIn('doc_type', self::서명갈래)
            ->whereNotNull('signed_at')
            ->orderByDesc('signed_at')
            ->orderByDesc('id')
            ->first();
    }
}
