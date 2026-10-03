<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaxHistory extends Model
{
    /**
     * 화면에 세우는 바닥 날짜 (2026-10-03 지시).
     *
     * 「공단 팩스 발송 이력 => 2026-10-01 이전 건은 안 보이게」
     *
     * 이 날 전의 43건은 운영 전환 전에 시험으로 보낸 것이다 — 모두 팝빌에서 끌어온
     * 거울이라 처방전ㆍ받는번호ㆍ보낸이가 비어 있고, 결과코드도 499ㆍ505ㆍ517ㆍ999 가
     * 많다(닿지 않는 번호로 보낸 것이다).
     *
     * **지우지 않는다**(2026-10-03 지시 「지우지 않기, 그냥 두기」). 팝빌이 원본을
     * 들고 있어 지워도 다음 동기화에 다시 끌려오고, 그쪽 이력은 우리가 지울 수 없다.
     * 담아 두되 화면에 세우지 않는다.
     *
     * 바닥은 한 곳에 둔다 — 공단 팩스 발송 화면과 발송 이력 화면이 같은 값을 본다.
     * 따로 적어 두면 한쪽만 고쳐지는 날이 온다.
     */
    public const 보이는바닥 = '2026-10-01';

    /** 화면에 세울 것만 — 바닥 날짜 뒤에 보낸 것이다 */
    public function scope보일것($query)
    {
        return $query->whereDate($query->getModel()->getTable() . '.created_at', '>=', self::보이는바닥);
    }

    protected $fillable = [
        'prescription_id',
        'corp_num', 'receipt_num', 'sender', 'sender_name',
        'title', 'receivers', 'file_names', 'reserve_dt',
        'request_num', 'sent_by',
        'fax_no', 'recipient_type', 'documents', 'attachment_ids', 'pdf_path',
        'popbill_state', 'popbill_result', 'synced_at',
    ];

    protected $casts = [
        'receivers'      => 'array',
        'file_names'     => 'array',
        'documents'      => 'array',
        'attachment_ids' => 'array',
        'popbill_state'  => 'integer',
        'popbill_result' => 'integer',
        'synced_at'      => 'datetime',
    ];

    /*
     * 팝빌이 알려 주는 팩스 전송 상태 — 저쪽 값을 그대로 담는다.
     *
     *   0 접수    팝빌이 전송 요청을 받은 상태
     *   1 변환중  팩스 형식으로 바꾸는 중
     *   2 전송중  통신사가 보내는 중
     *   3 완료    통신사가 전송을 마친 상태
     *   4 취소    예약 전송을 취소한 상태
     *
     * **2026-09-30 실전 시험에서 바로잡았다.** 여태 이 상수는
     * `0 대기 · 1 전송중 · 2 성공 · 3 실패 · 4 취소` 였다 — 팝빌 값과 뜻이 다르다.
     * 그래서 정상 전송된 팩스(팝빌 3 완료 · 결과 100 성공)가 우리 표에 **실패**로
     * 적혔고, 아직 보내는 중인 건(팝빌 2)이 **성공**으로 적혔다. 화면에 실패로 보이고,
     * 재등록 기한은 영원히 비고, 이미 보낸 건을 다시 보내는 문제까지 함께 딸려 있었다.
     *
     * **성공ㆍ실패는 상태가 아니라 결과코드로 가린다.** 완료(3)라도 결과코드가
     * 100 이 아니면 상대 팩스에 닿지 않은 것이다.
     */
    public const STATE_RECEIVED   = 0;
    public const STATE_CONVERTING = 1;
    public const STATE_SENDING    = 2;
    public const STATE_DONE       = 3;
    public const STATE_CANCEL     = 4;

    /** 팝빌에 접수조차 되지 않은 건 — 팝빌에 없는 값이라 우리가 따로 둔다 */
    public const STATE_NOT_SENT = 9;

    /** 통신사 결과코드 가운데 성공 */
    public const RESULT_OK = 100;

    /** 상태 이름 — 화면과 내보내기가 같은 말을 쓰도록 여기 하나에 둔다 */
    public static function 상태이름들(): array
    {
        return [
            self::STATE_RECEIVED   => '접수',
            self::STATE_CONVERTING => '변환 중',
            self::STATE_SENDING    => '전송 중',
            self::STATE_DONE       => '완료',
            self::STATE_CANCEL     => '취소',
            self::STATE_NOT_SENT   => '발송 실패',
        ];
    }

    /**
     * 사람이 읽을 상태 — 완료된 건은 결과코드까지 보아 성공ㆍ실패를 가른다.
     *
     * 「완료」만 적으면 닿지 않은 팩스도 끝난 것으로 읽힌다. 공단 제출 서류라
     * 닿았는지가 알고 싶은 값이다.
     */
    public function 상태이름(): string
    {
        if ($this->popbill_state === self::STATE_DONE) {
            return $this->성공인가() ? '전송 성공' : '전송 실패';
        }

        return self::상태이름들()[$this->popbill_state] ?? '알 수 없음';
    }

    /** 상대 팩스에 닿았는가 */
    public function 성공인가(): bool
    {
        return $this->popbill_state === self::STATE_DONE
            && (int) $this->popbill_result === self::RESULT_OK;
    }

    /** 닿지 못한 것으로 판가름 났는가 — 다시 보내야 하는 건이다 */
    public function 실패인가(): bool
    {
        if ($this->popbill_state === self::STATE_NOT_SENT) {
            return true;
        }

        return $this->popbill_state === self::STATE_DONE
            && $this->popbill_result !== null
            && (int) $this->popbill_result !== self::RESULT_OK;
    }

    /** 더 물어볼 것이 없는 건 */
    public function 끝났나(): bool
    {
        return in_array($this->popbill_state,
            [self::STATE_DONE, self::STATE_CANCEL, self::STATE_NOT_SENT], true);
    }

    /** 아직 완료되지 않은 건 (동기화 대상) */
    public function scopePending($query)
    {
        return $query->whereNotIn('popbill_state',
            [self::STATE_DONE, self::STATE_CANCEL, self::STATE_NOT_SENT]);
    }

    /** 닿은 건만 — 재등록 기한처럼 「정말 갔는가」를 따지는 자리에서 쓴다 */
    public function scopeSucceeded($query)
    {
        return $query->where('popbill_state', self::STATE_DONE)
                     ->where('popbill_result', self::RESULT_OK);
    }

    /** 닿지 못한 것으로 판가름 난 건 — 다시 보내야 한다 */
    public function scopeUndelivered($query)
    {
        return $query->where(function ($q) {
            $q->where('popbill_state', self::STATE_NOT_SENT)
              ->orWhere('popbill_state', self::STATE_CANCEL)
              ->orWhere(fn ($q2) => $q2->where('popbill_state', self::STATE_DONE)
                                       ->whereNotNull('popbill_result')
                                       ->where('popbill_result', '!=', self::RESULT_OK));
        });
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Prescription::class);
    }

    public function pdfUrl(): ?string
    {
        if (!$this->pdf_path) return null;
        return rtrim(request()->root(), '/') . '/storage/' . $this->pdf_path;
    }
}
