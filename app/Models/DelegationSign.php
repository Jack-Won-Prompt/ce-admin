<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * 운영 데이터 › 위임장 서명 (2026-09-11 지시).
 *
 * 기존 처방ㆍ주문ㆍ거래처와 잇지 않는 별도 기능이다. 받는 사람의 이름과 번호를
 * 제 칸으로 들고, 다른 표를 하나도 참조하지 않는다 — 표 하나와 폴더 하나만
 * 운영 서버로 옮기면 되도록.
 */
class DelegationSign extends Model
{
    use HasFactory;

    /** 서명 그림을 두는 곳 — 폴더째 옮긴다. 웹에서 바로 열리지 않는 local 디스크다. */
    public const 폴더 = 'delegation-signs';

    /**
     * 위임을 받는 곳 — 늘 한 곳이다 (2026-09-11 지시).
     *
     * 명단이 적어 보낸 판매처는 하이메드ㆍ(주)서호메디코로 갈리지만, 링크를 받은
     * 사람이 위임하는 상대는 콜로플라스트 코리아뿐이다. 서명을 받고 나면 그 사람의
     * 판매처는 여기로 바뀐다 — 위임이 끝난 뒤의 판매처는 대리점이 아니다.
     *
     * 적는 말은 명단이 쓰던 것을 그대로 따른다. 「콜로플라스트 코리아(주)」로
     * 달리 적었더니 판매처 고르개에 닮은 이름이 두 줄로 섰다.
     */
    public const 위임받는곳 = '콜로플라스트 코리아 주식회사';

    protected $fillable = [
        'customer_name', 'phone',
        'src_no', 'dealer_name', 'next_repurchase_at', 'last_register_at', 'rx_days',
        'last_confirm_at', 'src_status', 'rx_type', 'benefit_class', 'last_sale_status',
        'token', 'sent_to', 'sent_by_id', 'sent_by_name', 'sent_at', 'expires_at',
        'status',
        'agree_delegation', 'agree_privacy', 'agree_marketing',
        'signed_at', 'sign_path', 'sign_filename', 'sign_base64',
        'nice_verified_at', 'nice_name', 'nice_birthdate', 'nice_gender',
        'nice_mobile', 'nice_ci', 'nice_di',
        'ip', 'user_agent',
    ];

    protected $casts = [
        'sent_at'          => 'datetime',
        'expires_at'       => 'datetime',
        'signed_at'        => 'datetime',
        'nice_verified_at' => 'datetime',
        'next_repurchase_at' => 'date',
        'last_register_at'   => 'date',
        'last_confirm_at'    => 'date',
        'agree_delegation' => 'boolean',
        'agree_privacy'    => 'boolean',
        'agree_marketing'  => 'boolean',
    ];

    public const 상태 = [
        'pending'  => '발송 전',
        'sent'     => '서명 대기',
        'signed'   => '서명 완료',
        'declined' => '동의 거절',
    ];

    /** 지금 이 링크로 서명할 수 있는가 */
    public function 열려있나(): bool
    {
        return $this->status === 'sent'
            && $this->token
            && $this->expires_at
            && $this->expires_at->isFuture();
    }

    /**
     * 서명 그림 — 파일을 먼저 보고, 없으면 표에 담아 둔 것으로 그린다.
     *
     * 두 벌을 함께 남기는 뜻이 여기서 드러난다. 폴더를 옮기지 못했거나 파일이
     * 어긋나도 표 하나로 서명이 되살아난다.
     */
    public function 서명그림(): ?string
    {
        if ($this->sign_path && Storage::disk('local')->exists($this->sign_path)) {
            return Storage::disk('local')->get($this->sign_path);
        }

        if (! $this->sign_base64) {
            return null;
        }

        $조각 = preg_replace('~^data:image/\w+;base64,~', '', $this->sign_base64);

        return base64_decode($조각, true) ?: null;
    }

    /** 목록에 세우는 말 — 「서명함 / 아직」처럼 읽히게 */
    public function 동의말(string $칸): string
    {
        if ($this->status !== 'signed') {
            return '—';
        }

        return $this->{$칸} ? '동의함' : '동의하지 않음';
    }
}
