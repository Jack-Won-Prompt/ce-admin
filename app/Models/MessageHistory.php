<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 메시지 발송 이력.
 *
 * 한 행이 '한 번 누른 발송' 이다. 수신자가 여럿이면 receivers 에 함께 담긴다.
 * 팩스(fax_histories)와 같은 모양으로 두어 나중에 발송/발행 내역 화면에서 함께 읽힌다.
 */
class MessageHistory extends Model
{
    protected $fillable = [
        'channel', 'template_code', 'template_label', 'content',
        'total', 'success_count', 'fail_count',
        'receivers', 'receipt_nums', 'error', 'source', 'prescription_id', 'sent_by',
    ];

    protected $casts = [
        'receivers'     => 'array',
        'receipt_nums'  => 'array',
        'total'         => 'integer',
        'success_count' => 'integer',
        'fail_count'    => 'integer',
    ];

    public function sentBy(): BelongsTo       { return $this->belongsTo(User::class, 'sent_by'); }
    public function prescription(): BelongsTo { return $this->belongsTo(Prescription::class); }

    public function channelLabel(): string
    {
        return MessageTemplate::CHANNELS[$this->channel] ?? $this->channel;
    }

    /** 전부 성공 / 일부 실패 / 전부 실패 */
    public function resultLabel(): string
    {
        if ($this->fail_count === 0)    return '성공';
        if ($this->success_count === 0) return '실패';
        return "일부 실패 ({$this->fail_count}건)";
    }
}
